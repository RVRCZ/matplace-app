"""
PrusaLink driver - Prusa CORE One (with or without the INDX tool changer), MK4/MK4S, XL, MINI: the web API the Buddy
firmware serves itself. Written against the OpenAPI spec (github.com/prusa3d/Prusa-Link-Web, spec/openapi.yaml) and
the firmware's handlers (Prusa-Firmware-Buddy lib/WUI/link_content, lib/WUI/nhttp, src/state/printer_state.cpp, read
4 Nov 2026), checked against the owner's CORE One+ INDX (firmware 8.0.0, PrusaLink api 2.0.0; docs/AM.md §12).

  auth       X-Api-Key: <PrusaLink password>  (the printer: Settings -> Network -> PrusaLink -> Password); without it
             or with a wrong one HTTP 401 and `WWW-Authenticate: ApiKey realm=...`. The same password works as HTTP
             digest for the user "maker" (auth: digest), which aiohttp's digest middleware answers.
  status     GET /api/v1/status   printer{state IDLE|BUSY|PRINTING|PAUSED|FINISHED|STOPPED|ERROR|ATTENTION|READY,
                                  temp_nozzle, target_nozzle, temp_bed, target_bed, axis_z, flow, speed, fan_hotend,
                                  fan_print}, job{id, progress, time_remaining, time_printing} only while a job runs
             GET /api/v1/job      204 when nothing prints (also after a print: FINISHED/STOPPED name no file), else
                                  {id, state PRINTING|PAUSED|FINISHED|STOPPED|ERROR, progress, time_printing,
                                  time_remaining, file{name = the 8.3 short name, display_name = the real name, path,
                                  refs.download}}
             GET /api/v1/info     nozzle_diameter, mmu (false on an INDX), serial, hostname; nothing about the tools
  upload     PUT /api/v1/files/usb/<name>  the file as the body, Content-Length, Overwrite: ?1, Print-After-Upload: ?0
             201 Created; 409 "File is busy" | "Another transfer in progress"; 415 "Not a GCODE" (.gcode .gco .g .gc
             .bgcode .bgc only); 431 name longer than 167; 507 USB full
  start      POST /api/v1/files/usb/<name>  204; 409 when the printer cannot print now. The firmware starts the print
             with "skip the preview where able": the tools-mapping screen is skipped only when every tool the file uses
             holds the filament type the file names and the nozzles agree (docs/AM.md §12c); otherwise the printer
             waits on its screen and reports ATTENTION.
  control    PUT /api/v1/job/<id>/pause | /resume, DELETE /api/v1/job/<id> (stop): 204; 409 in the wrong state
  files      GET /api/v1/files/usb/ (Accept: application/json) -> {type FOLDER, children[{name (8.3), display_name,
             type PRINT_FILE|FILE|FOLDER, m_timestamp, ro, refs{download: "/usb/<8.3 name>"}}]}, no sizes, no free
             space; GET <refs.download> is the file itself; DELETE /api/v1/files/usb/<8.3 name> 204, 409 while printed
  camera     none in the firmware; an optional snapshot_url (any address answering a JPEG) as with the Moonraker driver
  reset      none: an error has to be confirmed on the printer's screen

Options (config.yaml): url (http://<ip>), api_key (the PrusaLink password; only in config.yaml), auth (key | digest,
default key), username (digest, default maker), timeout (seconds, default 15), upload_timeout (default 1800: the
printer writes the USB stick at a few MB/s and may hang on Wi-Fi), start_wait (seconds to watch the printer after the
start call for the print to begin, default 180: the firmware scans the file first), start_poll (default 3),
snapshot_url (optional).
"""
from __future__ import annotations

import asyncio
import json
import logging
import os
import time
from typing import Optional
from urllib.parse import quote

import aiohttp

from .base import (ERROR, IDLE, JOB_CANCELLED, JOB_DONE, JOB_FAILED, JOB_PAUSED, JOB_PRINTING, OFFLINE, PAUSED, PRINTING,
                   DriverError, JobStatus, PrinterDriver, PrinterStatus, safe_name)

log = logging.getLogger("farm_agent.prusalink")

# the printer's words (printer_state::to_str) -> (printer state, the running job's state)
STATES = {
    "IDLE": (IDLE, None),
    "READY": (IDLE, None),           # "Set ready" on the printer or in Connect: idle and waiting for the next job
    "BUSY": (PRINTING, None),        # homing, crash recovery, finishing: not free, not necessarily printing a file
    "PRINTING": (PRINTING, JOB_PRINTING),
    "PAUSED": (PAUSED, JOB_PAUSED),
    "ATTENTION": (PAUSED, JOB_PAUSED),   # a dialog on the screen: tools mapping, a filament question, an error
    "FINISHED": (IDLE, JOB_DONE),
    "STOPPED": (IDLE, JOB_CANCELLED),
    "ERROR": (ERROR, JOB_FAILED),
}
TERMINAL = ("FINISHED", "STOPPED", "ERROR")

# what the firmware prints from (filename_type.cpp) and how long a name it takes (filename_defs.hpp)
PRINTABLE = (".gcode", ".gco", ".gc", ".g", ".bgcode", ".bgc")
MAX_NAME = 167

ATTENTION_MESSAGE = "the printer waits for a touch on its screen (tools mapping, a filament question or an error)"
ERROR_MESSAGE = "error on the printer's screen; PrusaLink carries no text of it"

MEMORY_FILE = "{key}.prusalink.json"


class PrusaLinkDriver(PrinterDriver):
    def __init__(self, key: str, options: dict):
        super().__init__(key, options)
        self.url = str(options["url"]).rstrip("/")
        self.api_key = str(options.get("api_key") or "")
        self.auth = str(options.get("auth") or "key").lower()
        self.username = str(options.get("username") or "maker")
        self.timeout = aiohttp.ClientTimeout(total=float(options.get("timeout", 15)))
        self.upload_timeout = float(options.get("upload_timeout", 1800))
        self.start_wait = float(options.get("start_wait", 180))
        self.start_poll = float(options.get("start_poll", 3))
        self.snapshot_url = options.get("snapshot_url")
        self._session: Optional[aiohttp.ClientSession] = None
        # the last job the printer named, kept because FINISHED / STOPPED / ERROR come without the file
        self._last: dict = {}
        self._memory = os.path.join(str(options.get("_work_dir") or "."), MEMORY_FILE.format(key=key))
        # the USB listing as last read: the real name -> its 8.3 name and download path (deletes and fetches need them)
        self._listing: dict = {}
        self._load_memory()

    # -- connection --------------------------------------------------------------------------------------------
    async def connect(self) -> None:
        headers = {}
        middlewares = ()
        if self.api_key and self.auth != "digest":
            headers["X-Api-Key"] = self.api_key
        if self.api_key and hasattr(aiohttp, "DigestAuthMiddleware"):
            # answers a Digest challenge (realm "Printer API", no qop) with the same password; idle while the key is taken
            middlewares = (aiohttp.DigestAuthMiddleware(self.username, self.api_key),)
        elif self.auth == "digest":
            log.error("%s: auth digest needs aiohttp 3.12 or newer, sending the key as X-Api-Key instead", self.key)
            headers["X-Api-Key"] = self.api_key
        self._session = aiohttp.ClientSession(headers=headers, middlewares=middlewares) if middlewares else aiohttp.ClientSession(headers=headers)

    async def close(self) -> None:
        if self._session:
            await self._session.close()

    # -- reading -----------------------------------------------------------------------------------------------
    async def status(self) -> PrinterStatus:
        try:
            data = await self._get_json("/api/v1/status")
        except Unauthorized:
            log.warning("%s: PrusaLink refuses the api_key", self.key)
            return PrinterStatus(OFFLINE, {"link": "unauthorized"})
        except Exception as e:  # noqa: BLE001 - whatever went wrong, the printer is not reachable for us
            log.debug("%s: status failed: %s", self.key, e)
            return PrinterStatus(OFFLINE)
        printer = data.get("printer") if isinstance(data.get("printer"), dict) else {}
        word = str(printer.get("state") or "").upper()
        printer_state, job_state = STATES.get(word, (ERROR, None))
        telemetry = {
            "extruder": _num(printer.get("temp_nozzle")), "extruder_target": _num(printer.get("target_nozzle")),
            "bed": _num(printer.get("temp_bed")), "bed_target": _num(printer.get("target_bed")),
            "printer_state": word or None, "z": _num(printer.get("axis_z")),
            "speed": _int(printer.get("speed")), "flow": _int(printer.get("flow")),
            "fan_hotend": _int(printer.get("fan_hotend")), "fan_print": _int(printer.get("fan_print")),
        }
        live = data.get("job") if isinstance(data.get("job"), dict) else {}
        if live.get("time_remaining") is not None:
            telemetry["remain_min"] = round(float(live["time_remaining"]) / 60)
        job = None
        if job_state in (JOB_PRINTING, JOB_PAUSED):
            job = await self._running_job(word, job_state, live)
        elif self._last:
            job = self._ended_job(word, job_state)
        if word == "ATTENTION":
            telemetry["message"] = ATTENTION_MESSAGE
        elif word == "ERROR":
            telemetry["error"] = telemetry["message"] = ERROR_MESSAGE
        # the operator left the "print finished" screen (FINISHED/STOPPED -> IDLE, or "Set ready" -> READY) = the plate
        # is being cleared; the same signal as the Moonraker driver's complete -> standby (docs/AM.md §10)
        confirmed = self.plate_confirmed(terminal=word in TERMINAL and bool(self._last), idle=word in ("IDLE", "READY"), now=time.time())
        if confirmed:
            telemetry["bed_cleared_at"] = confirmed
        return PrinterStatus(printer_state, {k: v for k, v in telemetry.items() if v is not None}, job)

    async def _running_job(self, word: str, job_state: str, live: dict) -> Optional[JobStatus]:
        """The job the printer runs, named by GET /api/v1/job (the status endpoint carries only its numbers)."""
        try:
            data = await self._get_json("/api/v1/job")
        except Exception as e:  # noqa: BLE001 - the numbers of the status answer still stand
            log.debug("%s: job failed: %s", self.key, e)
            data = None
        file = (data or {}).get("file") if isinstance((data or {}).get("file"), dict) else {}
        filename = str(file.get("display_name") or file.get("name") or self._last.get("filename") or "")
        if not filename:
            return None
        progress = float((data or {}).get("progress", live.get("progress")) or 0)
        duration = int((data or {}).get("time_printing", live.get("time_printing")) or 0)
        self._remember({"filename": filename, "id": (data or {}).get("id", live.get("id")), "progress": progress,
                        "duration": duration, "ended": None})
        return JobStatus(filename=filename, status=job_state, progress=round(max(0.0, min(100.0, progress)), 2), print_duration=duration,
                         message=ATTENTION_MESSAGE if word == "ATTENTION" else "")

    def _ended_job(self, word: str, job_state: Optional[str]) -> Optional[JobStatus]:
        """The job that was running a moment ago: the firmware names no file once it is over, so the end is judged
        from the printer's word (FINISHED, STOPPED, ERROR) or, when the printer went straight to idle, from how far the
        print got. Reported until a new job appears, as the Moonraker driver reports `complete`."""
        last = self._last
        ended = last.get("ended")
        if not ended:
            if job_state in (JOB_DONE, JOB_CANCELLED, JOB_FAILED):
                ended = job_state
            elif word in ("IDLE", "READY") and float(last.get("progress") or 0) >= 99.0:
                ended = JOB_DONE
            elif word in ("IDLE", "READY"):
                ended = JOB_CANCELLED
            else:
                return None     # BUSY between two states: nothing to say yet
            last = {**last, "ended": ended, "ended_word": word}
            self._remember(last)
        message = "" if ended != JOB_FAILED else ERROR_MESSAGE
        if ended == JOB_CANCELLED and last.get("ended_word") in ("IDLE", "READY"):
            message = f"the print ended at {float(last.get('progress') or 0):.0f} % without a finished state"
        return JobStatus(filename=str(last.get("filename")), status=ended, progress=100.0 if ended == JOB_DONE else float(last.get("progress") or 0),
                         print_duration=int(last.get("duration") or 0), message=message)

    async def slot_status(self, slot: int) -> Optional[str]:
        """PrusaLink says nothing about the tools of an INDX (info reports mmu false, status has no tool list), so a
        slot is never checked here: the firmware checks the loaded filaments itself when the print starts."""
        return None

    async def snapshot(self) -> Optional[bytes]:
        if not self.snapshot_url or not self._session:
            return None
        try:
            async with self._session.get(self.snapshot_url, timeout=self.timeout) as r:
                if r.status == 200 and r.content_type.startswith("image/"):
                    return await r.read()
        except Exception as e:  # noqa: BLE001 - a missing picture is never a reason to disturb a print
            log.debug("%s: snapshot failed: %s", self.key, e)
        return None

    # -- the printer's storage -------------------------------------------------------------------------------------
    async def files(self) -> dict:
        try:
            data = await self._get_json("/api/v1/files/usb/")
        except Exception as e:  # noqa: BLE001
            raise DriverError(f"file list: {e.__class__.__name__} {e}") from e
        files, listing = [], {}
        for child in data.get("children") or []:
            if not isinstance(child, dict) or child.get("type") not in ("PRINT_FILE", "FILE"):
                continue
            name = str(child.get("display_name") or child.get("name") or "")
            if not name:
                continue
            refs = child.get("refs") if isinstance(child.get("refs"), dict) else {}
            listing[name] = {"short": str(child.get("name") or name), "download": refs.get("download"), "printing": not refs.get("download")}
            # the firmware tells no sizes in a listing (only per file on request); 0 = unknown
            files.append({"name": name, "size": int(child.get("size") or 0), "modified": int(child.get("m_timestamp") or 0)})
        files.sort(key=lambda f: f["modified"], reverse=True)
        self._listing = listing
        return {"files": files, "free": None, "total": None}

    async def fetch(self, name: str, target: str) -> int:
        assert self._session
        name = safe_name(name)
        entry = await self._entry(name)
        if not entry.get("download"):
            raise DriverError(f"file {name}: the printer offers no download (it is being printed)")
        os.makedirs(os.path.dirname(target) or ".", exist_ok=True)
        size = 0
        try:
            async with self._session.get(self.url + str(entry["download"]), timeout=aiohttp.ClientTimeout(total=self.upload_timeout)) as r:
                if r.status >= 400:
                    raise DriverError(f"file {name}: HTTP {r.status}")
                with open(target + ".part", "wb") as fh:
                    async for chunk in r.content.iter_chunked(1 << 16):
                        fh.write(chunk)
                        size += len(chunk)
        except (aiohttp.ClientError, asyncio.TimeoutError, OSError) as e:
            raise DriverError(f"file {name}: {e.__class__.__name__} {e}") from e
        os.replace(target + ".part", target)
        return size

    async def delete(self, name: str) -> None:
        name = safe_name(name)
        entry = await self._entry(name)
        await self._call("DELETE", "/api/v1/files/usb/" + quote(entry["short"]), what=f"delete {name}")

    async def _entry(self, name: str) -> dict:
        """The listing's row of a file by its real name (the firmware addresses files by their 8.3 names)."""
        if name not in self._listing:
            await self.files()
        entry = self._listing.get(name)
        if not entry:
            raise DriverError(f"{name}: the printer has no such file")
        return entry

    # -- acting ------------------------------------------------------------------------------------------------
    async def start(self, gcode_path: str, filename: str, slot: int) -> None:
        now = await self.status()
        if now.state != IDLE:
            raise DriverError(f"printer is {now.state}, not idle")
        filename = safe_name(filename)
        if not filename.lower().endswith(PRINTABLE):
            raise DriverError(f"{filename}: the printer prints only {', '.join(PRINTABLE)} files")
        if len(filename) > MAX_NAME:
            raise DriverError(f"{filename}: the printer takes names up to {MAX_NAME} characters, this one has {len(filename)}")
        # an INDX selects its tool by the T<n> lines the slicer wrote; the slot is the tool number and nothing is sent
        # for it separately (docs/AM.md §12). The firmware maps the file's tools to its own 1:1 and checks the filaments.
        log.info("%s: uploading %s (%d bytes) for tool %d", self.key, filename, os.path.getsize(gcode_path), slot)
        with open(gcode_path, "rb") as fh:
            await self._call("PUT", "/api/v1/files/usb/" + quote(filename), data=fh, what=f"upload {filename}",
                             headers={"Content-Type": "application/octet-stream", "Overwrite": "?1", "Print-After-Upload": "?0",
                                      "Content-Length": str(os.path.getsize(gcode_path))},
                             timeout=aiohttp.ClientTimeout(total=self.upload_timeout))
        self._listing = {}
        self._remember({})
        log.info("%s: starting %s", self.key, filename)
        await self._call("POST", "/api/v1/files/usb/" + quote(filename), what=f"print start {filename}")
        self._plate_terminal_seen = False       # the finished screen gives way to our print, not to a cleared plate
        # the call answers before the firmware has read the file: the printer reports IDLE while it scans it, then
        # PRINTING (heating, homing, the mesh and the purge are part of the print), or ATTENTION when a dialog waits
        deadline = time.monotonic() + self.start_wait
        word = ""
        while time.monotonic() < deadline:
            await asyncio.sleep(self.start_poll)
            now = await self.status()
            word = str(now.telemetry.get("printer_state") or now.state)
            if now.state in (PRINTING, PAUSED) and now.job and now.job.filename == filename:
                if word == "ATTENTION":
                    log.warning("%s: %s waits on the printer's screen (tools mapping or a filament question)", self.key, filename)
                else:
                    log.info("%s: the printer took %s", self.key, filename)
                return
            if now.state == ERROR:
                raise DriverError(f"print start: the printer reports an error ({ERROR_MESSAGE})")
            if now.job and now.job.filename == filename and now.job.status in (JOB_CANCELLED, JOB_FAILED):
                raise DriverError(f"print start: the printer ended {filename} at once ({now.job.status})")
        raise DriverError(f"print start: the printer did not begin {filename} in {self.start_wait:.0f} s (it says {word})")

    async def pause(self) -> None:
        await self._call("PUT", f"/api/v1/job/{await self._job_id()}/pause", what="pause")

    async def resume(self) -> None:
        await self._call("PUT", f"/api/v1/job/{await self._job_id()}/resume", what="resume")

    async def cancel(self) -> None:
        await self._call("DELETE", f"/api/v1/job/{await self._job_id()}", what="cancel")

    async def reset(self) -> None:
        raise DriverError("the error has to be confirmed on the printer's screen; PrusaLink has no reset")

    async def _job_id(self) -> int:
        try:
            data = await self._get_json("/api/v1/job")
        except Unauthorized:
            raise DriverError("PrusaLink refuses the api_key") from None
        except Exception as e:  # noqa: BLE001
            raise DriverError(f"job: {e.__class__.__name__} {e}") from e
        if not data or data.get("id") is None:
            raise DriverError("the printer runs no job")
        return int(data["id"])

    # -- what survives a restart -----------------------------------------------------------------------------------
    def _remember(self, last: dict) -> None:
        if last == self._last:
            return
        self._last = last
        try:
            os.makedirs(os.path.dirname(self._memory) or ".", exist_ok=True)
            with open(self._memory + ".tmp", "w", encoding="utf-8") as fh:
                json.dump(last, fh)
            os.replace(self._memory + ".tmp", self._memory)
        except OSError as e:
            log.debug("%s: memory not written: %s", self.key, e)

    def _load_memory(self) -> None:
        try:
            with open(self._memory, encoding="utf-8") as fh:
                data = json.load(fh)
            self._last = data if isinstance(data, dict) else {}
        except (OSError, ValueError):
            self._last = {}

    # -- plumbing ----------------------------------------------------------------------------------------------
    async def _get_json(self, path: str) -> dict:
        """A GET that answers JSON; {} on 204 (no job, no transfer)."""
        assert self._session
        async with self._session.get(self.url + path, headers={"Accept": "application/json"}, timeout=self.timeout) as r:
            if r.status in (401, 403):
                raise Unauthorized(f"{path}: HTTP {r.status}")
            if r.status == 204:
                return {}
            r.raise_for_status()
            body = await r.text()
            data = json.loads(body) if body else {}
            return data if isinstance(data, dict) else {}

    async def _call(self, method: str, path: str, *, what: str, data=None, headers=None, timeout=None) -> None:
        assert self._session
        try:
            async with self._session.request(method, self.url + path, data=data, headers={"Accept": "application/json", **(headers or {})},
                                             timeout=timeout or self.timeout) as r:
                body = await r.text()
                if r.status in (401, 403):
                    raise DriverError(f"{what}: PrusaLink refuses the api_key (HTTP {r.status})")
                if r.status >= 400:
                    raise DriverError(f"{what}: HTTP {r.status} {_error_text(body)}".rstrip())
        except (aiohttp.ClientError, asyncio.TimeoutError) as e:
            raise DriverError(f"{what}: {e.__class__.__name__} {e}") from e


class Unauthorized(Exception):
    """The printer answered 401/403: the api_key is missing or wrong."""


def _num(v) -> Optional[float]:
    return round(float(v), 1) if isinstance(v, (int, float)) else None


def _int(v) -> Optional[int]:
    return int(v) if isinstance(v, (int, float)) else None


def _error_text(body: str) -> str:
    """The firmware's error page: JSON {title, message|text, code} when asked for JSON, plain text otherwise."""
    try:
        data = json.loads(body)
    except ValueError:
        return " ".join(body.split())[:200]
    if not isinstance(data, dict):
        return str(body)[:200]
    parts = [str(data[k]) for k in ("title", "message", "text") if data.get(k)]
    return " ".join(dict.fromkeys(parts))[:200]
