"""
The agent: one process, many printers.

  every poll_seconds (and at once when a driver hears a change)
      read every printer -> POST /api/agent/sync -> carry out the commands that came back
  every snapshot_seconds, for printers with a running job (once a minute when idle, so the operator sees the plate)
      camera picture -> POST /api/agent/printers/{key}/snapshot
  four times a second, for a job whose G-code parks the head after every layer (server's TimelapseGcode)
      head standing at the park position -> camera picture -> the same endpoint with frame=layer

The agent never starts anything by itself. A print starts only on a `start` command, which the server issues only
after an operator confirmed in the admin that the plate is empty.

Every report carries what the printer has to say (telemetry.message, job.message), every poll again, so a message
cannot be lost by being sent once (docs/AM.md §10). Commands on the printer's storage: files (the list goes to the
server), fetch (one file up to the server), delete, cleanup (the farm's own files not in use).
"""
from __future__ import annotations

import asyncio
import json
import logging
import os
import re
import time
from dataclasses import asdict, dataclass
from typing import Optional

from . import __version__
from .config import Config
from .drivers import DriverError, PrinterDriver, create
from .drivers.base import (EMPTY_SLOT_WORDS, JOB_CANCELLED, JOB_DONE, JOB_FAILED, JOB_PAUSED, JOB_PRINTING, OFFLINE, PAUSED, PRINTING,
                           safe_name)
from .server import Server, ServerError

log = logging.getLogger("farm_agent")

# a tracked file the printer does not talk about for this many polls in a row is given up as failed
LOST_AFTER_POLLS = 8
ERROR_AFTER_POLLS = 12          # an "error" of our print is believed after this many polls in a row (about a minute at 5 s)

# pause / resume / cancel: a call the printer refused or did not answer is judged by the printer's state for this long
# (a Kobra S1 with Rinkhals answers print/resume only after it has fed the filament, past the call's limit, and resumes
# all the same: F26-000108, 29 Oct 2026)
CONFIRM_WAIT_SECONDS = 60.0
CONFIRM_POLL_SECONDS = 2.0

# the printer's storage: what a file may weigh to go up to the server, and which files are the farm's own (the agent
# names every print mp<job>-<server name>, docs/AM.md §10); a cleanup never touches anything else
FETCH_LIMIT_BYTES = 300 << 20
OUR_FILES = re.compile(r"^mp\d+-matplace-.*\.gcode(\.3mf)?$")

TIMELAPSE_HEADER = "; matplace timelapse "
PARK_TOLERANCE_MM = 1.0
HEAD_POLL_SECONDS = 0.25
# planned-position printers (GoKlipper on the Kobra S1) run their moves up to ~2 s behind what they report
# (measured 27 Sep 2026: the head reached the park position 2.5-3 s after toolhead.position showed it)
PLANNER_LEAD_SECONDS = 2.5
BURST_INTERVAL_SECONDS = 0.3


def pick_still(frames: list) -> Optional[bytes]:
    """The picture taken while nothing moved: the head standing at the park position during the dwell.
    Neighbouring pictures of a still scene are nearly the same; while printing the head changes the picture."""
    if not frames:
        return None
    try:
        from io import BytesIO

        from PIL import Image, ImageChops, ImageStat
    except ImportError:
        return frames[len(frames) * 2 // 3]      # no Pillow: late in the burst, where the dwell usually is
    small = []
    for jpeg in frames:
        try:
            small.append(Image.open(BytesIO(jpeg)).convert("L").resize((96, 54)))
        except Exception:  # noqa: BLE001 - a broken picture simply never wins
            small.append(None)

    def diff(a, b) -> float:
        return ImageStat.Stat(ImageChops.difference(a, b)).mean[0] if a is not None and b is not None else 1e9

    best, score = None, 1e18
    for i in range(1, len(frames) - 1):
        d = diff(small[i - 1], small[i]) + diff(small[i], small[i + 1])
        if d < score:
            best, score = i, d
    return frames[best] if best is not None else frames[-1]


def _same_file(a: str, b: str) -> bool:
    """The same file whether or not it carries the Kobra X package suffix (.gcode.3mf is reported as .gcode)."""
    strip = lambda n: os.path.basename(str(n or "")).removesuffix(".3mf")  # noqa: E731
    return bool(a) and strip(a) == strip(b)


def read_park(path: str) -> Optional[list]:
    """[x, y, dwell s, travel mm/s] from the first line the server writes into a layer-synced G-code, None otherwise."""
    try:
        with open(path, encoding="utf-8", errors="replace") as fh:
            first = fh.readline()
    except OSError:
        return None
    if not first.startswith(TIMELAPSE_HEADER):
        return None
    values = dict(p.split("=", 1) for p in first[len(TIMELAPSE_HEADER):].split() if "=" in p)
    try:
        return [float(values["park_x"]), float(values["park_y"]), float(values.get("dwell", 1000)) / 1000,
                float(values.get("travel", 200))]
    except (KeyError, ValueError):
        return None


@dataclass
class Tracked:
    job_id: int
    filename: str
    seen_printing: bool = False     # terminal states are trusted only after we saw this very file printing
    misses: int = 0
    errors: int = 0                 # polls in a row the printer called our print an error (a Kobra S1 says so for a moment while it starts)
    final: bool = False             # a terminal state was put into a report; forget the job once the server has it
    park: Optional[list] = None     # [x, y] when the G-code parks the head after every layer for a picture


class PrinterWorker:
    def __init__(self, key: str, driver: PrinterDriver, work_dir: str):
        self.key = key
        self.driver = driver
        self.work_dir = work_dir
        self.tracked: Optional[Tracked] = None
        self.busy = asyncio.Lock()      # one command at a time per printer
        self.parked = False             # the picture of this stop at the park position is taken (or given up)
        self.last_head: Optional[tuple] = None   # last position away from the park position
        self.burst: Optional[asyncio.Task] = None     # planned-position printers: pictures around one stop
        self.dirty = False              # tracked state changed and is not on disk yet

    async def report(self) -> dict:
        status = await self.driver.status()
        out: dict = {"key": self.key, "state": status.state, "telemetry": status.telemetry, "job": None}
        t = self.tracked
        if not t or status.state == OFFLINE:
            return out      # an unreachable printer says nothing about the job; the server shows it as unknown

        j = status.job
        ours = j is not None and j.filename == t.filename
        if ours and j.status in (JOB_PRINTING, JOB_PAUSED):
            self.dirty = self.dirty or not t.seen_printing     # worth remembering across a restart
            t.seen_printing, t.misses, t.errors = True, 0, 0
        # "error" has to stay: a Kobra S1 (Rinkhals) reports print_stats.state "error" for a poll or two while it heats and
        # levels, then prints on; a failure reported on that one sample closed the order, gave the credit back and left
        # the print running unwatched (F26-000059, 12 Oct 2026)
        # (an error the printer gives a reason for - a heater fault, a runout - is believed at once)
        if ours and j.status == JOB_FAILED and t.seen_printing and not (j.message or "").strip():
            t.errors += 1
            if t.errors < ERROR_AFTER_POLLS:
                out["job"] = {"id": t.job_id, "status": "unknown", "progress": j.progress, "print_duration": j.print_duration,
                              "filament_used": j.filament_used, "message": "the printer reports an error; waiting to see whether it stays"}
                return out
        terminal = ours and j.status in (JOB_DONE, JOB_CANCELLED, JOB_FAILED) and t.seen_printing
        if ours and (j.status in (JOB_PRINTING, JOB_PAUSED) or terminal):
            out["job"] = {"id": t.job_id, "status": j.status, "progress": j.progress, "print_duration": j.print_duration,
                          "filament_used": j.filament_used, "message": j.message}
            t.final = terminal
            return out

        # the printer reports another file, nothing, or a finished state of a file we never saw printing
        t.misses += 1
        if t.misses >= LOST_AFTER_POLLS:
            out["job"] = {"id": t.job_id, "status": JOB_FAILED, "message": "the printer no longer reports this print"}
            t.final = True
        return out


class Agent:
    def __init__(self, config: Config, server: Optional[Server] = None):
        self.config = config
        self.server = server or Server(config.server, config.token, config.verify_tls)
        # a driver may keep a small memory in the work dir (anycubic_lan: the running print's minutes and millimetres)
        self.workers = {p.key: PrinterWorker(p.key, create(p.key, p.driver, {**p.options, "_work_dir": config.work_dir}), config.work_dir) for p in config.printers}
        self._wake = asyncio.Event()
        self._state_file = os.path.join(config.work_dir, "state.json")
        self._tasks: set[asyncio.Task] = set()

    # -- life cycle ----------------------------------------------------------------------------------------------
    async def run(self) -> None:
        os.makedirs(self.config.work_dir, exist_ok=True)
        self._load_state()
        await self.server.open()
        for w in self.workers.values():
            w.driver.on_change = self._on_change
            await w.driver.connect()
        log.info("farm-agent %s: %d printer(s) -> %s", __version__, len(self.workers), self.config.server)
        try:
            await asyncio.gather(self._sync_loop(), self._snapshot_loop(), self._layer_loop())
        finally:
            for w in self.workers.values():
                await w.driver.close()
            await self.server.close()

    async def _on_change(self) -> None:
        self._wake.set()

    async def _sync_loop(self) -> None:
        while True:
            try:
                await self.sync_once()
            except ServerError as e:
                log.warning("sync failed: %s", e)
            except Exception:  # noqa: BLE001 - the loop must survive anything; the next round starts clean
                log.exception("sync round crashed")
            try:
                await asyncio.wait_for(self._wake.wait(), timeout=self.config.poll_seconds)
            except asyncio.TimeoutError:
                pass
            self._wake.clear()

    async def sync_once(self) -> None:
        reports = await asyncio.gather(*(w.report() for w in self.workers.values()))
        if any(w.dirty for w in self.workers.values()):
            self._save_state()
        answer = await self.server.sync(__version__, list(reports))
        # the server has the terminal reports now: those jobs are closed on our side too
        for w in self.workers.values():
            if w.tracked and w.tracked.final:
                self._forget(w)
        for cmd in answer.get("commands", []):
            worker = self.workers.get(cmd.get("printer"))
            if worker:
                task = asyncio.create_task(self._execute(worker, cmd))
                self._tasks.add(task)
                task.add_done_callback(self._tasks.discard)

    IDLE_SNAPSHOT_SECONDS = 60

    async def _snapshot_loop(self) -> None:
        last_idle: dict[str, float] = {}
        while True:
            await asyncio.sleep(self.config.snapshot_seconds)
            now = asyncio.get_event_loop().time()
            for w in self.workers.values():
                if not w.tracked:
                    if now - last_idle.get(w.key, 0.0) < self.IDLE_SNAPSHOT_SECONDS:
                        continue
                    last_idle[w.key] = now
                try:
                    jpeg = await w.driver.snapshot()
                    if jpeg:
                        await self.server.snapshot(w.key, jpeg, w.tracked.job_id if w.tracked else None)
                except Exception as e:  # noqa: BLE001 - pictures are a courtesy
                    log.debug("%s: snapshot not sent: %s", w.key, e)

    async def _layer_loop(self) -> None:
        while True:
            await asyncio.sleep(HEAD_POLL_SECONDS)
            for w in self.workers.values():
                if w.tracked and w.tracked.park and not w.tracked.final:
                    await self.layer_frame(w)

    async def layer_frame(self, w: PrinterWorker) -> bool:
        """One look at the head: once per stop at the park position, one picture. True when one was sent (or,
        for a planned-position printer, a burst of pictures started).

        A printer that reports its real position (motion_report): the picture when the head stands there.
        A printer that reports only the planned target (GoKlipper on the Kobra S1): the target shows the park
        position seconds before the head gets there, so a burst of pictures covers the next seconds and the
        stillest one (the dwell) is sent."""
        head = await w.driver.head()
        park = w.tracked.park if w.tracked else None
        at = bool(head and park and abs(head[0] - park[0]) <= PARK_TOLERANCE_MM and abs(head[1] - park[1]) <= PARK_TOLERANCE_MM)
        if not at:
            w.parked = False
            if head:
                w.last_head = head
            return False
        if w.parked:
            return False
        if head[2] is None:
            w.parked = True
            if w.burst and not w.burst.done():
                return False        # the previous stop is still being photographed
            travel = park[3] if len(park) > 3 else 200.0
            dwell = park[2] if len(park) > 2 else 1.0
            came = w.last_head or head
            distance = ((came[0] - park[0]) ** 2 + (came[1] - park[1]) ** 2) ** 0.5
            window = PLANNER_LEAD_SECONDS + distance / max(travel, 1.0) + dwell + 0.8
            w.burst = asyncio.create_task(self.burst_frame(w, w.tracked.job_id, window))
            self._tasks.add(w.burst)
            w.burst.add_done_callback(self._tasks.discard)
            return True
        if head[2] >= 1.0:
            return False        # real position: still moving
        w.parked = True
        try:
            jpeg = await w.driver.snapshot()
            if jpeg:
                await self.server.snapshot(w.key, jpeg, w.tracked.job_id, frame="layer")
                return True
        except Exception as e:  # noqa: BLE001 - a missed layer is a missed frame, nothing more
            log.debug("%s: layer frame not sent: %s", w.key, e)
        return False

    async def burst_frame(self, w: PrinterWorker, job_id: int, window: float, interval: float = BURST_INTERVAL_SECONDS) -> bool:
        """Pictures every `interval` seconds for `window` seconds, the stillest one goes to the server."""
        frames: list = []
        loop = asyncio.get_event_loop()
        end = loop.time() + window
        while loop.time() < end:
            try:
                jpeg = await w.driver.snapshot()
                if jpeg:
                    frames.append(jpeg)
            except Exception:  # noqa: BLE001
                pass
            await asyncio.sleep(interval)
        chosen = pick_still(frames)
        if not chosen:
            return False
        try:
            await self.server.snapshot(w.key, chosen, job_id, frame="layer")
            return True
        except Exception as e:  # noqa: BLE001 - a missed layer is a missed frame, nothing more
            log.debug("%s: layer frame not sent: %s", w.key, e)
            return False

    # -- commands ------------------------------------------------------------------------------------------------
    async def _execute(self, w: PrinterWorker, cmd: dict) -> None:
        ok, message = True, ""
        async with w.busy:
            try:
                kind = cmd["type"]
                p = cmd.get("payload") or {}
                if kind == "start":
                    message = await self._start(w, cmd)
                elif kind in ("pause", "resume", "cancel"):
                    message = await self._control(w, kind)
                elif kind == "reset":
                    await w.driver.reset()
                elif kind == "light":
                    await w.driver.light(bool(p.get("on", True)))
                elif kind == "dry":
                    await w.driver.dry(bool(p.get("on", True)), int(p.get("temp", 45)), int(p.get("minutes", 240)))
                elif kind == "files":
                    message = await self._files(w)
                elif kind == "fetch":
                    message = await self._fetch(w, p)
                elif kind == "delete":
                    message = await self._delete(w, p)
                elif kind == "cleanup":
                    message = await self._cleanup(w, p)
                else:
                    raise DriverError(f"unknown command '{kind}'")
                log.info("%s: %s done%s", w.key, kind, f" ({message})" if message else "")
            except (DriverError, ServerError, OSError, KeyError, ValueError) as e:
                ok, message = False, str(e)
                log.error("%s: %s failed: %s", w.key, cmd.get("type"), e)
        try:
            await self.server.command_result(int(cmd["id"]), ok, message)
        except ServerError as e:
            log.warning("could not report the result of command %s: %s", cmd.get("id"), e)
        self._wake.set()

    async def _start(self, w: PrinterWorker, cmd: dict) -> str:
        """Returns a note for the command's result ("" when there is nothing to say)."""
        if w.tracked and not w.tracked.final:
            raise DriverError(f"job {w.tracked.job_id} is still running on this printer")
        payload = cmd["payload"]
        job_id = int(cmd["job_id"])
        slot = int(payload.get("slot", 0))
        # every spool position the plate prints from must hold a spool: B3 paused at its first T<n> because the ACE
        # had nothing to feed, and said so only on its screen (F26-000108, 29 Oct 2026). A printer that does not
        # report its slots starts as before, and the note says so.
        notes = []
        for s in sorted({int(x) for x in (payload.get("filaments") or [slot])}):
            state = await w.driver.slot_status(s)
            if state is None:
                notes.append(f"slot {s + 1} not checked, the printer does not report its slots")
            elif state.lower() in EMPTY_SLOT_WORDS:
                raise DriverError(f"slot {s + 1} is {state}: load a spool into it and confirm the plate again")
        # the job id makes the name unique: a re-print of the same order is never mistaken for the finished one
        filename = f"mp{job_id}-" + os.path.basename(str(payload.get("filename") or "print.gcode"))
        local = os.path.join(self.config.work_dir, w.key, filename)
        await self.server.download_gcode(str(payload["gcode_url"]), local)
        await w.driver.light(True)     # the camera wants to see something
        await w.driver.start(local, filename, slot)
        w.tracked = Tracked(job_id=job_id, filename=filename, park=read_park(local))
        w.parked = False
        self._save_state()
        return "; ".join(notes)

    async def _control(self, w: PrinterWorker, kind: str) -> str:
        """pause / resume / cancel. A call the printer refused or left unanswered is not the end of it: the printer's
        state over the next CONFIRM_WAIT_SECONDS decides (a Kobra S1 under Rinkhals answers print/resume only after it
        has fed the filament, so the call ran into TimeoutError while the print went on; the admin saw "failed" twice
        on F26-000108). Returns a note for the command's result."""
        try:
            await getattr(w.driver, kind)()
            return ""
        except DriverError as e:
            said = str(e).strip()
        wanted = {
            "pause": lambda st: st.state == PAUSED,
            "resume": lambda st: st.state == PRINTING,
            "cancel": lambda st: st.state not in (PRINTING, PAUSED) or (st.job is not None and st.job.status == JOB_CANCELLED),
        }[kind]
        log.warning("%s: %s said %s, watching the printer's state for %.0f s", w.key, kind, said, CONFIRM_WAIT_SECONDS)
        started = time.monotonic()
        while time.monotonic() - started < CONFIRM_WAIT_SECONDS:
            await asyncio.sleep(CONFIRM_POLL_SECONDS)
            now = await w.driver.status()
            if now.state != OFFLINE and wanted(now):
                took = time.monotonic() - started
                log.info("%s: %s confirmed by the printer's state after %.0f s", w.key, kind, took)
                return f"confirmed by the printer's state after {took:.0f} s; the call itself said: {said}"
        raise DriverError(f"{said}; the printer's state did not change in {CONFIRM_WAIT_SECONDS:.0f} s")

    # -- the printer's storage -------------------------------------------------------------------------------------
    async def _files(self, w: PrinterWorker, extra: Optional[dict] = None) -> str:
        """The list of the printer's files (and the disk's free space) to the server."""
        listing = await w.driver.files()
        await self.server.files(w.key, {**listing, **(extra or {})})
        return f"{len(listing.get('files') or [])} files"

    async def _fetch(self, w: PrinterWorker, payload: dict) -> str:
        """One file of the printer up to the server, through this machine, never bigger than FETCH_LIMIT_BYTES."""
        name = safe_name(payload.get("name"))
        listed = next((f for f in (await w.driver.files()).get("files") or [] if f.get("name") == name), None)
        if listed is None:
            raise DriverError(f"{name}: the printer has no such file")
        if int(listed.get("size") or 0) > FETCH_LIMIT_BYTES:
            raise DriverError(f"{name} has {int(listed['size']) >> 20} MB, the server takes at most {FETCH_LIMIT_BYTES >> 20} MB")
        local = os.path.join(self.config.work_dir, w.key, "fetched", name)
        try:
            size = await w.driver.fetch(name, local)
            await self.server.upload_file(w.key, local, name)
        finally:
            for path in (local, local + ".part"):
                try:
                    os.remove(path)
                except OSError:
                    pass
        return f"{size} bytes"

    async def _in_use(self, w: PrinterWorker, name: str) -> bool:
        """The file of the job this agent runs here, or of whatever the printer prints or pauses right now (a file
        started on the touchscreen counts too). ".gcode.3mf" and ".gcode" are the same file to a Kobra X."""
        if w.tracked and not w.tracked.final and _same_file(w.tracked.filename, name):
            return True
        now = await w.driver.status()
        return now.job is not None and now.job.status in (JOB_PRINTING, JOB_PAUSED) and _same_file(now.job.filename, name)

    async def _delete(self, w: PrinterWorker, payload: dict) -> str:
        name = safe_name(payload.get("name"))
        if await self._in_use(w, name):
            raise DriverError(f"{name} is being printed, it stays")
        size = next((int(f.get("size") or 0) for f in (await w.driver.files()).get("files") or [] if f.get("name") == name), 0)
        await w.driver.delete(name)
        await self._files(w, {"deleted": [{"name": name, "size": size}], "freed": size})
        return f"deleted {name}"

    async def _cleanup(self, w: PrinterWorker, payload: dict) -> str:
        """The farm's own files that no job needs any more: not the one printing, not the ones the server says to keep
        (its running and queued jobs). Files that are not the farm's are never touched here."""
        keep = {safe_name(n) for n in (payload.get("keep") or [])}
        deleted, freed = [], 0
        for f in (await w.driver.files()).get("files") or []:
            name = str(f.get("name") or "")
            if not OUR_FILES.match(name) or any(_same_file(name, k) for k in keep) or await self._in_use(w, name):
                continue
            await w.driver.delete(name)
            deleted.append({"name": name, "size": int(f.get("size") or 0)})
            freed += int(f.get("size") or 0)
        await self._files(w, {"deleted": deleted, "freed": freed})
        return f"deleted {len(deleted)} files, {freed / (1 << 20):.0f} MB freed"

    # -- what survives a restart -----------------------------------------------------------------------------------
    def _forget(self, w: PrinterWorker) -> None:
        if w.tracked:
            asyncio.get_event_loop().create_task(w.driver.light(False))
            try:
                os.remove(os.path.join(self.config.work_dir, w.key, w.tracked.filename))
            except OSError:
                pass
        w.tracked = None
        self._save_state()

    def _save_state(self) -> None:
        data = {k: asdict(w.tracked) for k, w in self.workers.items() if w.tracked}
        for w in self.workers.values():
            w.dirty = False
        tmp = self._state_file + ".tmp"
        with open(tmp, "w", encoding="utf-8") as fh:
            json.dump(data, fh)
        os.replace(tmp, self._state_file)

    def _load_state(self) -> None:
        """A restart in the middle of a print (power cut of the Pi, update) keeps reporting the running job."""
        try:
            with open(self._state_file, encoding="utf-8") as fh:
                data = json.load(fh)
        except (OSError, ValueError):
            return
        for key, t in data.items():
            if key in self.workers:
                self.workers[key].tracked = Tracked(job_id=int(t["job_id"]), filename=str(t["filename"]), seen_printing=bool(t.get("seen_printing")),
                                                    park=t.get("park"))
