"""
The agent: one process, many printers.

  every poll_seconds (and at once when a driver hears a change)
      read every printer -> POST /api/agent/sync -> carry out the commands that came back
  every snapshot_seconds, for printers with a running job (once a minute when idle, so the operator sees the plate)
      camera picture -> POST /api/agent/printers/{key}/snapshot

The agent never starts anything by itself. A print starts only on a `start` command, which the server issues only
after an operator confirmed in the admin that the plate is empty.
"""
from __future__ import annotations

import asyncio
import json
import logging
import os
from dataclasses import asdict, dataclass
from typing import Optional

from . import __version__
from .config import Config
from .drivers import DriverError, PrinterDriver, create
from .drivers.base import JOB_CANCELLED, JOB_DONE, JOB_FAILED, JOB_PAUSED, JOB_PRINTING, OFFLINE
from .server import Server, ServerError

log = logging.getLogger("farm_agent")

# a tracked file the printer does not talk about for this many polls in a row is given up as failed
LOST_AFTER_POLLS = 8


@dataclass
class Tracked:
    job_id: int
    filename: str
    seen_printing: bool = False     # terminal states are trusted only after we saw this very file printing
    misses: int = 0
    final: bool = False             # a terminal state was put into a report; forget the job once the server has it


class PrinterWorker:
    def __init__(self, key: str, driver: PrinterDriver, work_dir: str):
        self.key = key
        self.driver = driver
        self.work_dir = work_dir
        self.tracked: Optional[Tracked] = None
        self.busy = asyncio.Lock()      # one command at a time per printer
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
            t.seen_printing, t.misses = True, 0
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
        self.workers = {p.key: PrinterWorker(p.key, create(p.key, p.driver, p.options), config.work_dir) for p in config.printers}
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
            await asyncio.gather(self._sync_loop(), self._snapshot_loop())
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

    # -- commands ------------------------------------------------------------------------------------------------
    async def _execute(self, w: PrinterWorker, cmd: dict) -> None:
        ok, message = True, ""
        async with w.busy:
            try:
                kind = cmd["type"]
                if kind == "start":
                    await self._start(w, cmd)
                elif kind == "pause":
                    await w.driver.pause()
                elif kind == "resume":
                    await w.driver.resume()
                elif kind == "cancel":
                    await w.driver.cancel()
                elif kind == "light":
                    await w.driver.light(bool((cmd.get("payload") or {}).get("on", True)))
                elif kind == "dry":
                    p = cmd.get("payload") or {}
                    await w.driver.dry(bool(p.get("on", True)), int(p.get("temp", 45)), int(p.get("minutes", 240)))
                else:
                    raise DriverError(f"unknown command '{kind}'")
                log.info("%s: %s done", w.key, kind)
            except (DriverError, ServerError, OSError, KeyError) as e:
                ok, message = False, str(e)
                log.error("%s: %s failed: %s", w.key, cmd.get("type"), e)
        try:
            await self.server.command_result(int(cmd["id"]), ok, message)
        except ServerError as e:
            log.warning("could not report the result of command %s: %s", cmd.get("id"), e)
        self._wake.set()

    async def _start(self, w: PrinterWorker, cmd: dict) -> None:
        if w.tracked and not w.tracked.final:
            raise DriverError(f"job {w.tracked.job_id} is still running on this printer")
        payload = cmd["payload"]
        job_id = int(cmd["job_id"])
        # the job id makes the name unique: a re-print of the same order is never mistaken for the finished one
        filename = f"mp{job_id}-" + os.path.basename(str(payload.get("filename") or "print.gcode"))
        local = os.path.join(self.config.work_dir, w.key, filename)
        await self.server.download_gcode(str(payload["gcode_url"]), local)
        await w.driver.light(True)     # the camera wants to see something
        await w.driver.start(local, filename, int(payload.get("slot", 0)))
        w.tracked = Tracked(job_id=job_id, filename=filename)
        self._save_state()

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
                self.workers[key].tracked = Tracked(job_id=int(t["job_id"]), filename=str(t["filename"]), seen_printing=bool(t.get("seen_printing")))
