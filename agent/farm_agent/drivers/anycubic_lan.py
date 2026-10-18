"""
Anycubic LAN mode driver - Anycubic Kobra X (and the Kobra 3 / S1 generation) with the stock firmware in "LAN mode",
through the protocol the Anycubic Slicer Next uses on the local network (client: kobra-connect, docs/AM.md).

  handshake  GET  http://<ip>:18910/info  -> signed POST /ctrl -> AES  = MQTT credentials (kobra_connect.handshake)
             "ctrlType": "cloud" = the printer is not in LAN mode (CloudModeError): told to the operator as such
  reports    MQTT/TLS <ip>:9883, anycubic/anycubicCloud/v1/printer/public/<model>/<device>/<type>/report
  status     query `info` (state, temperatures, `project` while printing, `last_project` after it, `urls`)
  upload     POST <urls.fileUploadurl> (http://<ip>:18910/gcode_upload?s=<token>), multipart field `file`
  start      MQTT slicer/.../print  {"type": "print", "action": "start", "data": {"filename", "filepath", "filetype", "taskid": "-1"}}
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
import threading
import time
import uuid
from typing import Callable, Optional

import aiohttp

from .base import (ERROR, IDLE, JOB_CANCELLED, JOB_DONE, JOB_FAILED, JOB_PAUSED, JOB_PRINTING, OFFLINE, PAUSED, PRINTING,
                   DriverError, JobStatus, PrinterDriver, PrinterStatus)

log = logging.getLogger("farm_agent.anycubic_lan")

PREFIX = "anycubic/anycubicCloud/v1"

# what the dashboard says when the printer answers but is not in LAN mode (structured key, the dashboard words it)
CLOUD_MODE = "cloud_mode"

# project.state -> job state; the printer's own words (Kobra X firmware 2.0.2.2: "printing", "finished"; the Kobra 3
# family adds "stoped" [sic] for a cancelled print and "failed")
JOB_STATES = {
    "printing": JOB_PRINTING, "preheating": JOB_PRINTING, "busy": JOB_PRINTING, "downloading": JOB_PRINTING,
    "checking": JOB_PRINTING, "leveling": JOB_PRINTING, "resuming": JOB_PRINTING, "pausing": JOB_PRINTING,
    "paused": JOB_PAUSED,
    "finished": JOB_DONE, "complete": JOB_DONE, "done": JOB_DONE,
    "stoped": JOB_CANCELLED, "stopped": JOB_CANCELLED, "canceled": JOB_CANCELLED, "cancelled": JOB_CANCELLED, "stoping": JOB_CANCELLED,
    "failed": JOB_FAILED, "error": JOB_FAILED,
}

# project.pause: 0 printing, 1 paused, 2 pausing, 3 resuming, 4 stopping (kobra_connect.models.PauseState)
PAUSED_FLAGS = (1, 2)


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
            self._seq[kind] = self._seq.get(kind, 0) + 1
            self._cond.notify_all()
        if self.on_report:
            self.on_report(kind, data)

    def last(self, kind: str) -> Optional[dict]:
        with self._cond:
            return self._reports.get(kind)

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
        self.retry_seconds = float(options.get("retry_seconds", 30))
        self.camera = bool(options.get("camera", True))
        self.use_light = bool(options.get("light", False))
        # tests hand in a fake link (no network); the real one is KobraLink
        self._make_link: Callable[[str, Callable], KobraLink] = options.get("_link") or (lambda ip, cb: KobraLink(ip, cb))
        self._link: Optional[KobraLink] = None
        self._problem = ""                 # why there is no link right now (for the dashboard)
        self._task: Optional[asyncio.Task] = None
        self._loop: Optional[asyncio.AbstractEventLoop] = None
        self._last_state: Optional[str] = None
        self._urls: dict = {}
        self._opening = asyncio.Lock()

    # -- connection --------------------------------------------------------------------------------------------
    async def connect(self) -> None:
        self._loop = asyncio.get_running_loop()
        self._task = asyncio.create_task(self._keep(), name=f"lan-{self.key}")

    async def close(self) -> None:
        if self._task:
            self._task.cancel()
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
        state = (data.get("data") or {}).get("state") if isinstance(data.get("data"), dict) else data.get("state")
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
        return self._map(report.get("data") or {})

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
        url = self._urls.get("rtspUrl")
        if not self.camera or not url:
            return None
        try:
            return await asyncio.wait_for(asyncio.to_thread(_flv_frame, url), timeout=20)
        except Exception as e:  # noqa: BLE001 - a missing picture never disturbs a print
            log.debug("%s: snapshot failed: %s", self.key, e)
            return None

    async def light(self, on: bool) -> None:
        if not self.use_light:
            return
        try:
            await self._send("light", "control", {"type": 3, "status": 1 if on else 0, "brightness": 100 if on else 0}, web=True)
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
        await self._upload(url, gcode_path, filename)
        # the G-code selects the spool itself (T<slot> after the start macro); the start command carries no mapping
        log.info("%s: starting %s (slot %d)", self.key, filename, slot + 1)
        await self._send("print", "start", {"taskid": "-1", "filename": filename, "filepath": "/", "filetype": 1, "task_mode": 1})
        deadline = time.monotonic() + self.start_wait
        while True:
            await asyncio.sleep(self.start_poll)
            now = await self.status()
            if now.state in (PRINTING, PAUSED) and (not now.job or now.job.filename == _base_name(filename)):
                log.info("%s: the printer took %s", self.key, filename)
                return
            if now.state == ERROR:
                raise DriverError(f"print start: the printer reports an error: {now.telemetry.get('error') or 'no message'}")
            if time.monotonic() > deadline:
                raise DriverError(f"print start: the printer did not begin {filename} in {self.start_wait:.0f} s")

    async def _upload(self, url: str, path: str, filename: str) -> None:
        size = os.path.getsize(path)
        with open(path, "rb") as fh:
            md5 = hashlib.md5(fh.read()).hexdigest()  # noqa: S324 - the printer's checksum, not security
        form = aiohttp.FormData()
        with open(path, "rb") as fh:
            form.add_field("file", fh, filename=filename, content_type="application/octet-stream")
            try:
                async with aiohttp.ClientSession() as s:
                    async with s.post(url, data=form, timeout=aiohttp.ClientTimeout(total=self.upload_timeout)) as r:
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


def _base_name(name: str) -> str:
    """The name the printer reports may carry a folder; ".gcode.3mf" is reported as ".gcode"."""
    return os.path.basename(name.replace("\\", "/"))


def _flv_frame(url: str) -> Optional[bytes]:
    """The first picture of the printer's HTTP-FLV stream as JPEG (PyAV decodes H.264 without an ffmpeg install)."""
    try:
        import av  # noqa: WPS433 - optional
    except ImportError:
        log.debug("PyAV is not installed: no camera picture")
        return None
    with av.open(url, options={"rw_timeout": "8000000"}, timeout=10) as container:
        for frame in container.decode(video=0):
            buf = io.BytesIO()
            frame.to_image().save(buf, format="JPEG", quality=80)
            return buf.getvalue()
    return None
