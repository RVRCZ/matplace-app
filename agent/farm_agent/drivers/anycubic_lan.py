"""
Anycubic LAN mode driver - Anycubic Kobra X (and the Kobra 3 / S1 generation) with the stock firmware in "LAN mode",
through the protocol the Anycubic Slicer Next uses on the local network (client: kobra-connect, docs/AM.md).

  handshake  GET  http://<ip>:18910/info  -> signed POST /ctrl -> AES  = MQTT credentials (kobra_connect.handshake)
             "ctrlType": "cloud" = the printer is not in LAN mode (CloudModeError): told to the operator as such
  reports    MQTT/TLS <ip>:9883, anycubic/anycubicCloud/v1/printer/public/<model>/<device>/<type>/report
  status     query `info` (state, temperatures, `project` while printing, `last_project` after it, `urls`)
  upload     POST <urls.fileUploadurl> (http://<ip>:18910/gcode_upload?s=<token>), multipart field `file`
  start      MQTT slicer/.../print  action start, the whole shape the Slicer Next sends: filename, url (placeholder),
             md5, filesize, filetype 1, project_type 1, ams_settings.ams_box_mapping [{paint 0 -> spool position}],
             task_settings; the file as a .gcode.3mf package (anycubic_pkg)
  control    MQTT slicer/.../print  action pause | resume | stop
  camera     <urls.rtspUrl> = HTTP-FLV (H.264) on :18088; one frame decoded with PyAV (`pip install av`), else no picture
  light      MQTT web/.../light {"type": 3 (camera light), "status": 0|1, "brightness"} - only with `light: true`

Options (config.yaml): ip (required), timeout (seconds a query waits for its report, default 8), start_wait (seconds to
watch the printer after the start command for the print to begin, default 120), upload_timeout (default 600),
camera (true/false, default true: the picture comes from the printer's own stream), light (true/false, default false).
"""
from __future__ import annotations

import asyncio
import hashlib
import io
import json
import logging
import os
import socket
import threading
import time
import uuid
from dataclasses import dataclass
from typing import Callable, Optional

import aiohttp

from .anycubic_pkg import config_value, facts_of, package, prepared_gcode, rgb_of
from .base import (ERROR, IDLE, JOB_CANCELLED, JOB_DONE, JOB_FAILED, JOB_PAUSED, JOB_PRINTING, OFFLINE, PAUSED, PRINTING,
                   DriverError, JobStatus, PrinterDriver, PrinterStatus)

log = logging.getLogger("farm_agent.anycubic_lan")

PREFIX = "anycubic/anycubicCloud/v1"

# what the dashboard says when the printer answers but is not in LAN mode (structured key, the dashboard words it)
CLOUD_MODE = "cloud_mode"

# the `url` the Anycubic Slicer Next puts into every LAN start (its own placeholder): the printer prints the file it
# was given and never fetches it, but refuses a start that has no url at all (18 Oct 2026)
SLICER_URL = "https://anycubic.com/store/aaa.gcode"

# project.state -> job state; the printer's own words (Kobra X firmware 2.0.2.2: "printing", "finished"; the Kobra 3
# family adds "stoped" [sic] for a cancelled print and "failed")
JOB_STATES = {
    "printing": JOB_PRINTING, "preheating": JOB_PRINTING, "busy": JOB_PRINTING, "downloading": JOB_PRINTING,
    "checking": JOB_PRINTING, "leveling": JOB_PRINTING, "resuming": JOB_PRINTING, "pausing": JOB_PRINTING,
    # the start of a Kobra X print (18 Oct 2026: heating, then "extfilbox_filament_feeding" from the rack or
    # "ace_filament_feeding" from an ACE) and the S1 sequence; anything unknown counts as printing too
    "ace_filament_feeding": JOB_PRINTING, "extfilbox_filament_feeding": JOB_PRINTING, "auto_leveling": JOB_PRINTING, "vibrating": JOB_PRINTING,
    "flow_calibrating": JOB_PRINTING, "resumed": JOB_PRINTING, "updated": JOB_PRINTING, "init": JOB_PRINTING,
    "nozzle_heating": JOB_PRINTING, "hotbed_heating": JOB_PRINTING, "nozzle_purging": JOB_PRINTING, "nozzle_cooling": JOB_PRINTING,
    "nozzle_wiping": JOB_PRINTING,
    # a stop takes a few seconds ("stopping", the printer still busy) before the task ends as "stoped"
    "stopping": JOB_PRINTING,
    "paused": JOB_PAUSED,
    "finished": JOB_DONE, "complete": JOB_DONE, "done": JOB_DONE,
    "stoped": JOB_CANCELLED, "stopped": JOB_CANCELLED, "canceled": JOB_CANCELLED, "cancelled": JOB_CANCELLED,
    "failed": JOB_FAILED, "error": JOB_FAILED,
}

# project.pause: 0 printing, 1 paused, 2 pausing, 3 resuming, 4 stopping (kobra_connect.models.PauseState)
PAUSED_FLAGS = (1, 2)

# the light the camera looks with (light/report type 3; 1 is the head light, 2 a chamber light on other models)
LIGHT_CAMERA = 3

# the printer's own spool rack (box id -1) offers 4 positions; with an ACE unit attached only 3, the ACE's feed takes
# the 4th (the printer still lists it, with edit_status 3; Kobra X, 18 Oct 2026)
RACK_ID = -1
RACK_POSITIONS, RACK_POSITIONS_WITH_ACE, ACE_POSITIONS = 4, 3, 4


@dataclass
class Position:
    """One spool position as matplace numbers it (slot 0.. across the rack and then the ACE units) and as the printer
    addresses it (box id, index in the box, ams_index of the start command)."""

    slot: int
    box: int            # the box's id: -1 the rack, 0.. an ACE unit
    ordinal: int        # the box's place in the order rack, ACE, second ACE...
    index: int          # the slot's index inside the box
    name: str           # "Rack 2", "ACE 1": the position as the screen shows it
    colour: str = "#000000"
    material: str = "PLA"
    status: int = 0     # the printer's slot status (5 loaded, 4 ready)

    @property
    def ams_index(self) -> int:
        """What the start's ams_box_mapping calls the position: the boxes in the order the printer lists them, four
        numbers each (rack 0-3, ACE 4-7). Verified 18 Oct 2026: the Slicer Next addressed the rack's index 1 as 1, and
        ams_index 4 fed from the ACE's slot 0 (report "ace_filament_feeding", unit_no 1, channel_no 1; docs/AM.md §6)."""
        return self.ordinal * 4 + self.index


@dataclass
class Spools:
    """The printer's spool positions in matplace's order, the one whose spool is in the head right now, and the
    ACE's dryer as the dashboard's telemetry keys (dryer_state off|drying, dryer_temp, dryer_target, dryer_remain_min,
    dryer_rh; empty without an ACE)."""

    positions: list
    loaded: Optional[Position] = None
    boxes: int = 0
    dryer: dict = None  # type: ignore[assignment]

    def __post_init__(self) -> None:
        if self.dryer is None:
            self.dryer = {}

    def at(self, slot: int) -> Optional[Position]:
        return next((p for p in self.positions if p.slot == slot), None)


class LinkError(Exception):
    """The printer could not be reached or did not answer."""


class KobraLink:
    """One MQTT connection to the printer, kept in a thread of its own (paho). Reports are remembered per type; a query
    waits for the report that answers it. Everything here blocks: the driver calls it through asyncio.to_thread."""

    def __init__(self, ip: str, on_report: Optional[Callable[[str, dict], None]] = None):
        self.ip = ip
        self.on_report = on_report
        self._client = None
        self._hs = None
        self._lock = threading.Lock()
        self._cond = threading.Condition()
        self._reports: dict[str, dict] = {}
        self._history: dict[str, list[dict]] = {}     # the last reports of each type, newest last
        self._seq: dict[str, int] = {}

    def connect(self) -> None:
        try:
            from kobra_connect import KobraClient  # noqa: WPS433 - optional dependency, only for this driver
            from kobra_connect.handshake import CloudModeError
        except ImportError as e:   # pragma: no cover - a missing package is told in the log and on the dashboard
            raise LinkError(f"kobra_connect is not installed: {e}") from e
        client = KobraClient(self.ip)
        try:
            client.connect(timeout=10)
        except CloudModeError as e:
            raise LinkError(CLOUD_MODE) from e
        except Exception as e:  # noqa: BLE001 - handshake, TLS, broker: all "not reachable"
            raise LinkError(f"{e.__class__.__name__}: {e}") from e
        client.on_report = self._heard
        self._client, self._hs = client, client._hs  # noqa: SLF001 - the credentials the topics need

    def close(self) -> None:
        if self._client:
            try:
                self._client.disconnect()
            finally:
                self._client = None

    @property
    def connected(self) -> bool:
        mqtt = getattr(self._client, "_client", None)
        return bool(mqtt and mqtt.is_connected())

    def _heard(self, topic: str, data: dict) -> None:
        parts = topic.split("/")
        kind = parts[-2] if parts[-1] == "report" else parts[-1]
        with self._cond:
            self._reports[kind] = data
            self._history.setdefault(kind, []).append(data)
            del self._history[kind][:-50]
            self._seq[kind] = self._seq.get(kind, 0) + 1
            self._cond.notify_all()
        if self.on_report:
            self.on_report(kind, data)

    def last(self, kind: str) -> Optional[dict]:
        with self._cond:
            return self._reports.get(kind)

    def count(self, kind: str) -> int:
        """How many reports of this type arrived so far (to tell a new one from an old one)."""
        with self._cond:
            return self._seq.get(kind, 0)

    def since(self, kind: str, n: int) -> list[dict]:
        """The reports of this type that came after the first n (a count() taken before a command), oldest first.
        A printer answers a start with two reports within a millisecond (the refusal, then the task's "update"), so
        the last one alone is not the answer."""
        with self._cond:
            kept, total = self._history.get(kind, []), self._seq.get(kind, 0)
            return list(kept[len(kept) - (total - n):]) if total > n else []

    def publish(self, kind: str, action: str, data, *, web: bool = False) -> str:
        if not self._client or not self._hs:
            raise LinkError("not connected")
        msgid = uuid.uuid4().hex
        topic = f"{PREFIX}/{'web' if web else 'slicer'}/printer/{self._hs.model_id}/{self._hs.device_id}/{kind}"
        body = json.dumps({"type": kind, "action": action, "timestamp": int(time.time() * 1000), "msgid": msgid, "data": data})
        info = self._client._client.publish(topic, body)  # noqa: SLF001 - paho client of kobra_connect
        if getattr(info, "rc", 0) != 0:
            raise LinkError(f"publish {kind}/{action} failed (rc {info.rc})")
        return msgid

    def ask(self, kind: str, action: str = "query", data=None, *, web: bool = True, timeout: float = 8.0) -> dict:
        """Send and wait for the next report of the same type (the printer answers on <type>/report)."""
        with self._lock:
            with self._cond:
                seen = self._seq.get(kind, 0)
            self.publish(kind, action, data, web=web)
            deadline = time.monotonic() + timeout
            with self._cond:
                while self._seq.get(kind, 0) == seen:
                    left = deadline - time.monotonic()
                    if left <= 0:
                        raise LinkError(f"no answer to {kind}/{action} in {timeout:.0f} s")
                    self._cond.wait(left)
                return self._reports[kind]


class AnycubicLanDriver(PrinterDriver):
    def __init__(self, key: str, options: dict):
        super().__init__(key, options)
        self.ip = str(options["ip"])
        self.timeout = float(options.get("timeout", 8))
        self.start_wait = float(options.get("start_wait", 120))
        self.start_poll = float(options.get("start_poll", 5))
        self.upload_timeout = float(options.get("upload_timeout", 600))
        self.answer_wait = float(options.get("answer_wait", 8))     # the printer's report on a start
        self.answer_poll = float(options.get("answer_poll", 1))
        self.as_package = bool(options.get("package", True))        # the file goes up as .gcode.3mf (anycubic_pkg)
        self.auto_leveling = bool(options.get("auto_leveling", True))   # the printer levels before every print
        self.start_attempts = int(options.get("start_attempts", 3))
        self.busy_retry = float(options.get("busy_retry", 5))
        self.retry_seconds = float(options.get("retry_seconds", 30))
        self.camera = bool(options.get("camera", True))
        self._camera: Optional[Camera] = None
        self.use_light = bool(options.get("light", True))
        # tests hand in a fake link (no network); the real one is KobraLink
        self._make_link: Callable[[str, Callable], KobraLink] = options.get("_link") or (lambda ip, cb: KobraLink(ip, cb))
        self._link: Optional[KobraLink] = None
        self._problem = ""                 # why there is no link right now (for the dashboard)
        self._task: Optional[asyncio.Task] = None
        self._loop: Optional[asyncio.AbstractEventLoop] = None
        self._last_state: Optional[str] = None
        # the task that ended by a report (it leaves no trace in last_project), until the next start, and the last
        # progress report of the running task (its minutes and millimetres)
        self._ended: Optional[dict] = None
        self._last_progress: dict = {}
        # the layer-synced time-lapse (docs/AM.md §7): where the farm's G-code parks the head after every layer (its
        # first line says), the layer the printer last reported and when it changed
        self._park: Optional[tuple] = None          # (x, y, dwell s, travel mm/s, sync)
        self._frames = options.get("_frames")       # tests hand in pictures (a callable giving JPEG bytes) instead of the camera
        self._layer: Optional[int] = None
        self._layer_at: float = 0.0
        self.layer_window = float(options.get("layer_window", 0))   # by hand: seconds head() reports the park after a report (0 = measured timing)
        # when the head reaches the park after the printer reports the layer, at the earliest and at the latest: measured
        # 2.2-4.0 s on a cube (docs/AM.md §7d), longer on bigger parts; with the server's M400 before the layer marker
        # (sync=m400 in the park line) only the park move itself remains, 0-2 s. None = by the park line.
        self.park_lead_min: Optional[float] = _opt_float(options, "park_lead_min")
        self.park_lead: Optional[float] = _opt_float(options, "park_lead")
        self.park_window_min = float(options.get("park_window_min", 0.5))   # a sure window shorter than this is no use (the agent looks every 0.25 s)
        # the camera as the position sensor (docs/AM.md §7e): the park is told only when the picture shows the head
        # away; head_region = the part of the picture the printing head fills (fractions left, top, right, bottom),
        # head_away = at most this many bright pixels there when the head is parked; camera_check: false = timing only
        self.camera_check = bool(options.get("camera_check", True))
        self.head_region = tuple(float(v) for v in options.get("head_region", (0.375, 0.11, 0.75, 0.61)))
        self.head_away = int(options.get("head_away", 20))
        self._head_seen = 0          # the most bright pixels seen while printing: a camera that never sees the head is not trusted
        self._urls: dict = {}
        self._opening = asyncio.Lock()

    # -- connection --------------------------------------------------------------------------------------------
    async def connect(self) -> None:
        self._loop = asyncio.get_running_loop()
        self._task = asyncio.create_task(self._keep(), name=f"lan-{self.key}")

    async def close(self) -> None:
        if self._task:
            self._task.cancel()
        if self._camera:
            self._camera.stop()
            self._camera = None
        if self._link:
            await asyncio.to_thread(self._link.close)
            self._link = None

    async def _keep(self) -> None:
        """Keeps the link up: a printer switched off, a router restart or a new MQTT password after a reboot all end in
        a new handshake. Never raises."""
        while True:
            try:
                if not self._link or not self._link.connected:
                    await self._open()
            except asyncio.CancelledError:
                raise
            except Exception as e:  # noqa: BLE001 - logged, retried
                log.debug("%s: link check failed: %s", self.key, e)
            await asyncio.sleep(self.retry_seconds)

    async def _open(self) -> bool:
        # the keeper and a status call may both find the link down at the same moment: one handshake, not two links
        async with self._opening:
            if self._link and self._link.connected:
                return True
            return await self._reopen()

    async def _reopen(self) -> bool:
        if self._link:
            await asyncio.to_thread(self._link.close)
            self._link = None
        link = self._make_link(self.ip, self._heard)
        try:
            await asyncio.to_thread(link.connect)
        except LinkError as e:
            self._problem = str(e)
            if self._problem == CLOUD_MODE:
                log.warning("%s: the printer is in cloud mode - switch LAN mode on at the printer", self.key)
            else:
                log.info("%s: no link to %s: %s", self.key, self.ip, e)
            return False
        self._link, self._problem = link, ""
        log.info("%s: connected to %s (LAN mode)", self.key, self.ip)
        return True

    def _heard(self, kind: str, data: dict) -> None:
        """Called in paho's thread for every report: a change of the printer's state wakes the agent at once."""
        details = data.get("data") if isinstance(data.get("data"), dict) else {}
        if kind == "print" and details.get("filename"):
            name = _base_name(str(details["filename"]))
            ended = {"finished": JOB_DONE, "failed": JOB_FAILED, "stoped": JOB_CANCELLED, "stopped": JOB_CANCELLED}.get(str(data.get("state") or ""))
            if ended:
                # a task that ended, by finishing, failing or a stop, is gone from the next info report and never
                # enters last_project (Kobra X, 18 Oct 2026): remembered here with the numbers of its last progress
                # report, so the agent hears "done" or "cancelled" and not "the printer no longer reports this print"
                numbers = self._last_progress if _base_name(str(self._last_progress.get("filename") or "")) == name else {}
                self._ended = {**numbers, **details, "status": ended, "err_msg": str(data.get("msg") or "") if ended == JOB_FAILED else ""}
            elif "progress" in details:
                self._last_progress = details          # print_time (minutes) and supplies_usage (mm) of the running task
                self._note_layer(details.get("curr_layer"))
        state = details.get("state") if details else data.get("state")
        if kind in ("info", "print", "status") and state and state != self._last_state:
            self._last_state = state
            if self.on_change and self._loop:
                asyncio.run_coroutine_threadsafe(self.on_change(), self._loop)

    async def _ensure(self) -> KobraLink:
        if not self._link or not self._link.connected:
            if not await self._open():
                raise DriverError(self._why())
        assert self._link
        return self._link

    def _why(self) -> str:
        if self._problem == CLOUD_MODE:
            return "the printer is not in LAN mode (switch LAN mode on at the printer)"
        return f"printer not reachable: {self._problem or 'no link'}"

    async def _ask(self, kind: str, action: str = "query", data=None, *, web: bool = True) -> dict:
        link = await self._ensure()
        try:
            return await asyncio.to_thread(link.ask, kind, action, data, web=web, timeout=self.timeout)
        except LinkError as e:
            raise DriverError(str(e)) from e

    async def _send(self, kind: str, action: str, data, *, web: bool = False) -> str:
        link = await self._ensure()
        try:
            return await asyncio.to_thread(link.publish, kind, action, data, web=web)
        except LinkError as e:
            raise DriverError(str(e)) from e

    # -- reading -----------------------------------------------------------------------------------------------
    async def status(self) -> PrinterStatus:
        try:
            report = await self._ask("info")
        except DriverError as e:
            log.debug("%s: status failed: %s", self.key, e)
            telemetry = {"link": CLOUD_MODE} if self._problem == CLOUD_MODE else {}
            return PrinterStatus(OFFLINE, telemetry)
        status = self._map(report.get("data") or {})
        await self._extras(status)
        return status

    async def _extras(self, status: PrinterStatus) -> None:
        """What the dashboard shows besides the print: the spool in the head (as the screen names it and as matplace's
        slot), the ACE's dryer with the keys the dashboard reads (dryer_state...), and the camera light. Each is a
        query of its own; one that fails leaves its keys out."""
        try:
            spools = await self.spools()
        except DriverError:
            spools = None
        if spools:
            if spools.loaded and status.state in (PRINTING, PAUSED):
                status.telemetry["spool"] = spools.loaded.name
                status.telemetry["slot"] = spools.loaded.slot
            status.telemetry.update(spools.dryer)
        try:
            lights = ((await self._ask("light")).get("data") or {}).get("lights") or []
        except DriverError:
            lights = []
        camera = next((l for l in lights if isinstance(l, dict) and l.get("type") == LIGHT_CAMERA), None)
        if camera:
            status.telemetry["light"] = "on" if int(camera.get("status") or 0) else "off"

    def _map(self, d: dict) -> PrinterStatus:
        self._urls = d.get("urls") or self._urls
        temp = d.get("temp") or {}
        telemetry = {
            "extruder": _num(temp.get("curr_nozzle_temp")), "extruder_target": _num(temp.get("target_nozzle_temp")),
            "bed": _num(temp.get("curr_hotbed_temp")), "bed_target": _num(temp.get("target_hotbed_temp")),
            "printer_state": d.get("state"),
        }
        project = d.get("project") if isinstance(d.get("project"), dict) else None
        state = str(d.get("state") or "")
        # an uploaded file the printer only noted (a task in "init" on a "free" printer) is no print: the printer is idle
        if project and state == "free" and str(project.get("state") or "") == "init":
            project = None
        if project:
            job_state = JOB_STATES.get(str(project.get("state") or ""), JOB_PRINTING)
            if job_state == JOB_PRINTING and int(project.get("pause") or 0) in PAUSED_FLAGS:
                job_state = JOB_PAUSED
            printer_state = {JOB_PRINTING: PRINTING, JOB_PAUSED: PAUSED, JOB_FAILED: ERROR}.get(job_state, IDLE)
        else:
            last = d.get("last_project") if isinstance(d.get("last_project"), dict) else None
            project = last
            job_state = JOB_STATES.get(str((last or {}).get("state") or ""), JOB_DONE) if last else None
            printer_state = IDLE if state in ("free", "") else (ERROR if state in ("error", "failed") else PRINTING)
            if job_state in (JOB_PRINTING, JOB_PAUSED):     # a "last" project that still says printing is over
                job_state = JOB_FAILED if printer_state == ERROR else JOB_DONE
            ended = self._ended
            if ended and _base_name(str(ended.get("filename") or "")) != _base_name(str((last or {}).get("filename") or "")):
                project, job_state = ended, str(ended["status"])
        if project and printer_state in (PRINTING, PAUSED):
            self._note_layer(project.get("curr_layer"))
        if project and project.get("total_layers"):
            telemetry["layer"] = f"{project.get('curr_layer', 0)}/{project['total_layers']}"
        if project and printer_state in (PRINTING, PAUSED) and project.get("remain_time") is not None:
            telemetry["remain_min"] = int(project.get("remain_time") or 0)
        error = d.get("error") or d.get("err_msg") or ""
        if printer_state == ERROR and error:
            telemetry["error"] = " ".join(str(error).split())[:160]
        job = None
        if project and job_state and project.get("filename"):
            progress = float(project.get("progress") or 0)
            job = JobStatus(
                filename=_base_name(str(project["filename"])),
                status=job_state,
                progress=100.0 if job_state == JOB_DONE else max(0.0, min(100.0, progress)),
                print_duration=int(project.get("print_time") or 0) * 60,     # minutes on this firmware
                filament_used=float(project.get("supplies_usage") or 0),     # millimetres
                message=str(project.get("err_msg") or ""),
            )
        return PrinterStatus(printer_state, {k: v for k, v in telemetry.items() if v not in (None, "")}, job)

    async def snapshot(self) -> Optional[bytes]:
        """The latest picture of the printer's camera stream. The stream is kept open while pictures are wanted (a
        burst of them for the layer time-lapse needs one every 0.3 s; opening the stream takes ~10 s, the first key
        frame) and closed after 20 s without a request."""
        if self._frames:
            return self._frames()
        url = self._urls.get("rtspUrl")
        if not self.camera or not url:
            return None
        if self._camera is None or self._camera.url != url:
            if self._camera:
                self._camera.stop()
            self._camera = Camera(url, f"cam-{self.key}")
        try:
            return await asyncio.wait_for(asyncio.to_thread(self._camera.frame), timeout=20)
        except Exception as e:  # noqa: BLE001 - a missing picture never disturbs a print
            log.debug("%s: snapshot failed: %s", self.key, e)
            return None

    async def dry(self, on: bool, temp: int = 45, minutes: int = 240) -> None:
        """The dryer of the first ACE unit: multiColorBox setDry with drying_status {status 1, target_temp, duration in
        minutes}; status 0 stops it (the shape chrisfore captured on a Kobra S1 Max; Kobra X: docs/AM.md §7). The
        printer's own rack has no dryer."""
        spools = await self.spools()
        ace = next((p.box for p in spools.positions if p.box != RACK_ID), None)
        if ace is None:
            raise DriverError("this printer has no ACE unit to dry in")
        wanted = {"status": 1, "target_temp": int(temp), "duration": int(minutes)} if on else {"status": 0}
        report = await self._ask("multiColorBox", "setDry", {"multi_color_box": [{"id": ace, "drying_status": wanted}]})
        if report.get("state") == "failed" or report.get("code") not in (None, 0, 200):
            raise DriverError(f"dryer: {report.get('code')} {report.get('msg') or ''}".strip())
        log.info("%s: dryer %s", self.key, f"{temp} °C for {minutes} min" if on else "off")

    async def head(self) -> Optional[tuple[float, float, Optional[float]]]:
        """Where the head is, as far as a printer without coordinates lets us say (docs/AM.md §7d). The farm's G-code
        parks the head after every layer (its first line says where and for how long). The printer reports the new
        layer when it reads the G-code, 2.2-4.0 s BEFORE the head reaches the park (the planner runs ahead; measured
        on 11 layers, 18 Oct 2026). So, with the report at T and the dwell D:
          - D long enough for a sure window (D >= park_lead - park_lead_min + 0.5 s, i.e. 3 s): the park is reported
            as a real, standing position (speed 0) during [T + park_lead, T + park_lead_min + D], and the agent takes
            one picture there;
          - a shorter D: the park is reported as a planned position (speed None) from T + park_lead_min for D + the
            spread, and the agent takes a burst of pictures and keeps the stillest (less sure on small parts).
        None outside, so the agent sees the head leave."""
        if not self._park or self._layer is None:
            return None
        since = time.monotonic() - self._layer_at
        x, y, dwell, _travel, sync = self._park
        if self.layer_window:                                   # a window set by hand: planned position, as before
            return (x, y, None) if since <= self.layer_window else None
        lead_min = self.park_lead_min if self.park_lead_min is not None else (0.0 if sync == "m400" else 2.2)
        lead_max = self.park_lead if self.park_lead is not None else (2.0 if sync == "m400" else 4.0)
        # the camera decides when it can: within the whole span the park may fall into, the park is told the moment
        # the picture shows the head away (docs/AM.md §7e)
        if self.camera_check and lead_min <= since <= lead_max + dwell:
            seen = await self._head_pixels()
            if seen is not None:
                if seen > self.head_away:
                    self._head_seen = max(self._head_seen, seen)
                    return None
                if self._head_seen >= 2 * self.head_away:       # the camera has seen the head print: it can tell "away"
                    return x, y, 0.0
        sure_from, sure_to = lead_max, lead_min + dwell
        if sure_to - sure_from >= self.park_window_min:
            return (x, y, 0.0) if sure_from <= since <= sure_to else None
        spread = lead_max - lead_min
        if lead_min <= since <= lead_min + dwell + spread:
            return x, y, None
        return None

    async def _head_pixels(self) -> Optional[int]:
        """How much of the head the camera sees right now (head_in_view), None without a picture."""
        jpeg = await self.snapshot()
        return head_in_view(jpeg, self.head_region) if jpeg else None

    def _note_layer(self, layer) -> None:
        """A layer the printer reports (print/report or info): the moment it changes is when the head goes to park."""
        if layer is None:
            return
        try:
            n = int(layer)
        except (TypeError, ValueError):
            return
        if self._layer is None:
            self._layer = n
        elif n != self._layer:
            self._layer = n
            if n >= 2:      # the farm's G-code parks from the second layer on (TimelapseGcode skips the first marker)
                self._layer_at = time.monotonic()

    async def light(self, on: bool) -> None:
        """The camera light (type 3): the printer itself switches it on while it feeds and levels, and reports
        light/control {type, status, brightness} (18 Oct 2026); the same shape switches it from here."""
        if not self.use_light:
            return
        try:
            await self._send("light", "control", {"type": LIGHT_CAMERA, "status": 1 if on else 0, "brightness": 100 if on else 0}, web=True)
        except DriverError as e:
            log.debug("%s: light %s failed: %s", self.key, "on" if on else "off", e)

    # -- acting ------------------------------------------------------------------------------------------------
    async def start(self, gcode_path: str, filename: str, slot: int) -> None:
        now = await self.status()
        if now.state != IDLE:
            raise DriverError(f"printer is {now.state}, not idle")
        url = self._urls.get("fileUploadurl")
        if not url:
            raise DriverError("the printer offers no upload address (urls.fileUploadurl missing in its info)")
        await self._clear_stale()
        self._ended, self._last_progress = None, {}
        self._layer, self._layer_at = None, 0.0
        # What the Slicer Next sends (captured and tried out 18 Oct 2026, docs/AM.md): T<n> in the G-code is a "paint"
        # the printer maps to a spool position by ams_box_mapping. matplace's GcodeSlot wrote the spool position as
        # T<n> (docs/V.md), so the file goes up as paint 0 and that position goes into the mapping. The file needs the
        # Slicer's paint_info and statistics lines (anycubic_pkg), and the start the fields the Slicer sends (url, md5,
        # filesize, the whole task_settings): without them the printer refuses it with 10301 "device busy, state unknown".
        with open(gcode_path, "rb") as fh:
            raw = fh.read()
        self._park = _park_of(raw)
        facts = facts_of(raw)
        slot_no = facts.tool if facts.tool else int(slot)
        pos = await self.spool_at(slot_no, config_value(raw, b"filament_colour") or "#000000", config_value(raw, b"filament_type") or "PLA")
        colour, material = pos.colour, pos.material
        if self.as_package:
            data, _ = package(raw, colour, material)
            upload_name = filename + ".3mf" if filename.lower().endswith(".gcode") else filename
        else:
            data, upload_name = prepared_gcode(raw, colour, material, facts), filename
        await self._upload(url, data, upload_name)
        rgba = rgb_of(colour) + [255]
        mapping = {"paint_index": 0, "ams_index": pos.ams_index, "paint_color": rgba, "ams_color": rgba, "material_type": material.split()[0].upper()}
        log.info("%s: starting %s (T%d -> slot %d = %s, ams_index %d, %s %s)", self.key, filename, facts.tool, slot_no, pos.name, pos.ams_index, material, colour)
        command = {
            # the printer prints the file just uploaded and never fetches the address: the Slicer sends this very
            # placeholder, and a start without url, md5 and filesize is refused
            "taskid": "-1", "url": SLICER_URL, "filename": filename, "md5": hashlib.md5(data).hexdigest(),  # noqa: S324
            "filepath": None, "filetype": 1, "project_type": 1, "filesize": len(data),
            "ams_settings": {"use_ams": True, "padding1": 0, "padding2": 0, "padding3": 0, "ams_box_mapping": [mapping]},
            "task_settings": {"auto_leveling": 1 if self.auto_leveling else 0, "vibration_compensation": 0, "flow_calibration": 0, "dry_mode": 0,
                              "ai_settings": {"status": 0, "count": 32765, "type": 32, "mode": 0},
                              "timelapse": {"status": 0, "count": 32765, "type": 32, "mode": 0},
                              "drying_settings": {"status": 0, "target_temp": 0, "duration": 0, "remain_time": 0},
                              "model_objects_skip_parts": []},
        }
        # the printer answers a start on print/report at once: "checking" when it takes it, "failed" with a code when not
        link = await self._ensure()
        for attempt in range(1, self.start_attempts + 1):
            seen = link.count("print")
            await self._send("print", "start", command)
            refusal = await self._start_answer(link, seen)
            if not refusal:
                break
            code, text = refusal
            if code != 10301 or attempt == self.start_attempts:
                raise DriverError(f"print start refused: {code} {text}".strip())
            log.info("%s: the printer is still busy with the file (%s), start again in %.0f s", self.key, code, self.busy_retry)
            await asyncio.sleep(self.busy_retry)
        deadline = time.monotonic() + self.start_wait
        while True:
            await asyncio.sleep(self.start_poll)
            now = await self.status()
            # a task the printer only noted ("init") on a printer still "free" has not begun
            begun = now.telemetry.get("printer_state") not in ("free", None)
            if begun and now.state in (PRINTING, PAUSED) and (not now.job or now.job.filename == _base_name(filename)):
                log.info("%s: the printer took %s", self.key, filename)
                return
            if now.state == ERROR:
                raise DriverError(f"print start: the printer reports an error: {now.telemetry.get('error') or 'no message'}")
            if time.monotonic() > deadline:
                raise DriverError(f"print start: the printer did not begin {filename} in {self.start_wait:.0f} s")

    async def _start_answer(self, link: KobraLink, seen: int) -> Optional[tuple[int, str]]:
        """The printer's answer to a start: None when it took it (or said nothing), else (code, text) of the refusal."""
        deadline = time.monotonic() + self.answer_wait
        while time.monotonic() < deadline:
            await asyncio.sleep(self.answer_poll)
            for report in link.since("print", seen):
                if report.get("action") != "start":
                    continue
                if report.get("state") == "failed":
                    return int(report.get("code") or 0), str(report.get("msg") or "")
                return None
        return None

    async def _clear_stale(self) -> None:
        """A task the printer only noted and never began ("init" on a free printer: a start it refused) is ended first;
        print/stop does that (18 Oct 2026)."""
        project = ((await self._ask("info")).get("data") or {}).get("project") or {}
        if not project:
            return
        log.info("%s: stopping the stale task %s (%s)", self.key, project.get("filename"), project.get("state"))
        await self._send("print", "stop", {"taskid": "-1"})
        for _ in range(10):
            await asyncio.sleep(self.answer_poll * 2)
            if not (((await self._ask("info")).get("data") or {}).get("project")):
                return
        log.info("%s: the stale task is still there, starting anyway", self.key)

    async def spools(self) -> Spools:
        """The spool positions the printer has, in matplace's order: the rack (box id -1) first, then the ACE units by
        id; slot n is the n-th position across them. With an ACE attached the rack offers 3 positions, alone 4
        (RACK_POSITIONS). Raises DriverError when the printer does not tell."""
        report = await self._ask("multiColorBox", "getInfo")
        boxes = [b for b in (report.get("data") or {}).get("multi_color_box") or [] if isinstance(b, dict)]
        boxes.sort(key=lambda b: (int(b.get("id", 0)) != RACK_ID, int(b.get("id", 0))))
        spools = Spools(positions=[], boxes=len(boxes))
        for ordinal, box in enumerate(boxes):
            box_id = int(box.get("id", 0))
            rack = box_id == RACK_ID
            count = (RACK_POSITIONS_WITH_ACE if len(boxes) > 1 else RACK_POSITIONS) if rack else ACE_POSITIONS
            unit = "Rack" if rack else ("ACE" if ordinal <= 1 else f"ACE{ordinal}")
            slots = sorted((s for s in box.get("slots") or [] if isinstance(s, dict)), key=lambda s: int(s.get("index", 0)))[:count]
            for s in slots:
                rgb = list(s.get("color") or [])[:3]
                p = Position(slot=len(spools.positions), box=box_id, ordinal=ordinal, index=int(s.get("index", 0)),
                             name=f"{unit} {int(s.get('index', 0)) + 1}", colour="#%02X%02X%02X" % tuple(rgb) if len(rgb) == 3 else "#000000",
                             material=str(s.get("type") or "PLA"), status=int(s.get("status") or 0))
                if int(box.get("loaded_slot", -1)) == p.index:
                    spools.loaded = p
                spools.positions.append(p)
            if not rack and not spools.dryer:
                # the ACE's dryer (the first ACE; a second one is not shown): the keys the dashboard reads
                dry = box.get("drying_status") if isinstance(box.get("drying_status"), dict) else {}
                on = bool(int(dry.get("status") or 0))
                spools.dryer = {"dryer_state": "drying" if on else "off", "dryer_temp": _num(box.get("temp")) or 0,
                                "dryer_target": (_num(dry.get("target_temp")) or 0) if on else 0,
                                "dryer_remain_min": int(dry.get("remain_time") or 0) if on else 0, "dryer_rh": _num(box.get("humidity")) or 0}
        return spools

    async def spool_at(self, slot: int, colour: str, material: str) -> Position:
        """The position matplace's slot means on this printer, with the spool's colour and material (what the file and
        the start say the print is made of). A printer that does not tell its boxes gets the slot as the rack's index
        and the G-code's own colour and material; a slot beyond the positions is refused with the list."""
        try:
            spools = await self.spools()
        except DriverError as e:
            log.info("%s: no spool information (%s): slot %d taken as the rack's index", self.key, e, slot)
            return Position(slot=slot, box=RACK_ID, ordinal=0, index=slot, name=f"position {slot + 1}", colour=colour, material=material)
        p = spools.at(slot)
        if not p:
            raise DriverError(f"slot {slot + 1}: the printer offers {len(spools.positions)} spool positions ({', '.join(q.name for q in spools.positions)})")
        return p

    async def _upload(self, url: str, data: bytes, filename: str) -> None:
        size = len(data)
        md5 = hashlib.md5(data).hexdigest()  # noqa: S324 - the printer's checksum, not security
        # the form exactly as Anycubic Slicer Next 2.0.0.3 sends it (captured 18 Oct 2026, docs/AM.md): the name first as a
        # plain field, then the file in the field "gcode", and its size in X-File-Length. Anything else is
        # {"code": 19005, "message": "Parsing form fail"}.
        form = aiohttp.FormData()
        form.add_field("filename", filename)
        form.add_field("gcode", io.BytesIO(data), filename=filename, content_type="application/octet-stream")
        headers = {"X-File-Length": str(size), "User-Agent": "matplace-farm-agent"}
        try:
            async with aiohttp.ClientSession() as s:
                async with s.post(url, data=form, headers=headers, timeout=aiohttp.ClientTimeout(total=self.upload_timeout)) as r:
                    body = await r.text()
                    if r.status >= 400:
                        raise DriverError(f"upload: HTTP {r.status} {body[:200]}")
        except (aiohttp.ClientError, asyncio.TimeoutError) as e:
            raise DriverError(f"upload: {e.__class__.__name__} {e}") from e
        log.info("%s: uploaded %s (%d bytes, md5 %s): %s", self.key, filename, size, md5, body[:200])
        try:
            answer = json.loads(body) if body else {}
        except ValueError:
            answer = {}
        if isinstance(answer, dict) and answer.get("code") not in (None, 0, 200):
            raise DriverError(f"upload refused: {answer.get('code')} {answer.get('message') or answer.get('msg') or ''}".strip())

    async def pause(self) -> None:
        await self._send("print", "pause", {"taskid": "-1"})

    async def resume(self) -> None:
        await self._send("print", "resume", {"taskid": "-1"})

    async def cancel(self) -> None:
        await self._send("print", "stop", {"taskid": "-1"})

    async def reset(self) -> None:
        now = await self.status()
        if now.state in (PRINTING, PAUSED):
            raise DriverError("a print is running, nothing to reset")
        if now.state == ERROR:
            raise DriverError(f"the printer reports an error, clear it on its screen: {now.telemetry.get('error') or 'no message'}")


def _num(v) -> Optional[float]:
    return round(float(v), 1) if isinstance(v, (int, float)) else None


def _park_of(raw: bytes) -> Optional[tuple[float, float, float, float, str]]:
    """(x, y, dwell s, travel mm/s, sync) from the first line the farm writes into a layer-synced G-code
    ("; matplace timelapse park_x=250 park_y=250 dwell=1000 travel=200 frames=49 sync=m400"), None for any other
    file. `sync` says what the server did about the printer's planner running ahead ("m400": an M400 before every
    layer marker, so the marker is read only when the layer's moves are done), "" when nothing."""
    first = raw.split(b"\n", 1)[0].decode("utf-8", "replace")
    if not first.startswith("; matplace timelapse "):
        return None
    values = dict(p.split("=", 1) for p in first.split() if "=" in p)
    try:
        return (float(values["park_x"]), float(values["park_y"]), float(values.get("dwell", 1000)) / 1000.0, float(values.get("travel", 200)),
                str(values.get("sync", "")).lower())
    except (KeyError, ValueError):
        return None


def head_in_view(jpeg: bytes, region: tuple[float, float, float, float], bright: int = 200) -> Optional[int]:
    """How much of the print head the camera sees: the number of bright pixels in `region` (fractions of the picture:
    left, top, right, bottom) of the picture scaled to 320 x 180. The Kobra X head is a white box; while it prints it
    fills the middle of the picture (100-300 pixels there), parked at X0 it stands small at the right edge (0-1 pixel in
    the middle band; 18 Oct 2026). None when the picture cannot be read (or Pillow is missing)."""
    try:
        from PIL import Image  # noqa: WPS433 - optional, installed on the farm PC (install-farma.ps1)
        im = Image.open(io.BytesIO(jpeg)).convert("L").resize((320, 180))
    except Exception:  # noqa: BLE001 - no picture, no opinion
        return None
    x0, y0, x1, y1 = (int(region[0] * 320), int(region[1] * 180), int(region[2] * 320), int(region[3] * 180))
    px = im.load()
    return sum(1 for y in range(y0, y1) for x in range(x0, x1) if px[x, y] > bright)


def _opt_float(options: dict, key: str) -> Optional[float]:
    """A number option, None when it is not set (so a default can depend on something else)."""
    value = options.get(key)
    return None if value is None or value == "" else float(value)


def _base_name(name: str) -> str:
    """The name the printer reports may carry a folder; ".gcode.3mf" is reported as ".gcode"."""
    return os.path.basename(name.replace("\\", "/"))


class _StreamBody:
    """The body of the printer's HTTP-FLV stream as a file object for PyAV. ffmpeg's own HTTP reader probes the
    stream for ~10 s, then hands over a burst of a hundred frames and fails on the next ones (Kobra X, 18 Oct 2026);
    fetched by a plain socket and demuxed as FLV with a short probe, the stream decodes live at 10 pictures a second,
    the first one ~4 s after the connection (the first key frame)."""

    def __init__(self, url: str, timeout: float = 15.0):
        parts = url.split("/", 3)                     # http: / / host:port / path
        host, _, port = parts[2].partition(":")
        self.sock = socket.create_connection((host, int(port or 80)), timeout=timeout)
        self.sock.sendall(f"GET /{parts[3] if len(parts) > 3 else ''} HTTP/1.1\r\nHost: {parts[2]}\r\nUser-Agent: matplace-farm-agent\r\nAccept: */*\r\n\r\n".encode())
        head = b""
        while b"\r\n\r\n" not in head:
            more = self.sock.recv(4096)
            if not more:
                raise OSError("the camera stream closed before its headers")
            head += more
        status, _, self._rest = head.partition(b"\r\n\r\n")
        if b" 200 " not in status[:20] and b" 206 " not in status[:20]:
            first_line = status.split(b"\r\n", 1)[0].decode("latin-1")
            raise OSError(f"camera stream: {first_line}")

    def read(self, n: int = -1) -> bytes:
        if self._rest:
            out, self._rest = (self._rest[:n], self._rest[n:]) if n > 0 else (self._rest, b"")
            return out
        return self.sock.recv(n if n > 0 else 65536)

    def close(self) -> None:
        try:
            self.sock.close()
        except OSError:
            pass


class Camera:
    """The printer's HTTP-FLV camera stream (H.264, decoded by PyAV without an ffmpeg install, see _StreamBody), read
    in a thread of its own while pictures are wanted: the latest frame is always at hand, so a picture costs nothing
    but the JPEG encoding - the layer time-lapse takes a burst of them 0.3 s apart. Without a request for
    `idle_seconds` the stream is closed; the next request opens it again (~4 s to the first picture)."""

    def __init__(self, url: str, name: str, idle_seconds: float = 20.0):
        self.url = url
        self.name = name
        self.idle_seconds = idle_seconds
        self._lock = threading.Lock()
        self._ready = threading.Condition(self._lock)
        self._frame = None            # the latest decoded frame
        self._seq = 0
        self._jpeg: Optional[tuple[int, bytes]] = None
        self._wanted = 0.0            # when a picture was last asked for
        self._thread: Optional[threading.Thread] = None
        self._stop = threading.Event()
        self._error: Optional[str] = None

    def frame(self, wait: float = 15.0) -> Optional[bytes]:
        """The latest picture as JPEG; starts the stream when it is not running and waits for its first frame."""
        with self._lock:
            self._wanted = time.monotonic()
            if not self._thread or not self._thread.is_alive():
                self._stop.clear()
                self._frame, self._error = None, None
                self._thread = threading.Thread(target=self._run, name=self.name, daemon=True)
                self._thread.start()
            deadline = time.monotonic() + wait
            while self._frame is None and self._error is None and time.monotonic() < deadline:
                self._ready.wait(deadline - time.monotonic())
            if self._frame is None:
                return None
            if self._jpeg and self._jpeg[0] == self._seq:
                return self._jpeg[1]
            frame, seq = self._frame, self._seq
        buf = io.BytesIO()
        frame.to_image().save(buf, format="JPEG", quality=80)
        with self._lock:
            self._jpeg = (seq, buf.getvalue())
        return buf.getvalue()

    def stop(self) -> None:
        self._stop.set()

    def _run(self) -> None:
        try:
            import av  # noqa: WPS433 - optional
        except ImportError:
            with self._lock:
                self._error = "PyAV is not installed: no camera picture"
                self._ready.notify_all()
            log.debug("%s", self._error)
            return
        body = None
        try:
            body = _StreamBody(self.url)
            with av.open(body, format="flv", options={"probesize": "65536", "analyzeduration": "0", "fflags": "nobuffer"}) as container:
                stream = container.streams.video[0]
                stream.thread_type = "AUTO"
                for packet in container.demux(stream):
                    try:
                        frames = packet.decode()
                    except Exception:  # noqa: BLE001 - a damaged packet: the next key frame heals the picture
                        continue
                    for frame in frames:
                        with self._lock:
                            self._frame, self._seq = frame, self._seq + 1
                            self._ready.notify_all()
                    with self._lock:
                        idle = time.monotonic() - self._wanted
                    if self._stop.is_set() or idle > self.idle_seconds:
                        break
        except Exception as e:  # noqa: BLE001 - the stream went away: the next request opens it again
            with self._lock:
                self._error = f"{e.__class__.__name__}: {e}"
                self._ready.notify_all()
            log.debug("%s: camera stream ended: %s", self.name, e)
        finally:
            if body:
                body.close()
