"""
Moonraker (Klipper) driver - Anycubic Kobra S1 with the Rinkhals firmware overlay, or any other Klipper printer.

  status     GET  /printer/objects/query?print_stats&virtual_sdcard&extruder&heater_bed&display_status
  upload     POST /server/files/upload            (multipart, root=gcodes)
  start      POST /printer/print/start?filename=
  control    POST /printer/print/pause | resume | cancel
  reset      POST /printer/gcode/script?script=SDCARD_RESET_FILE (+ /printer/firmware_restart after a Klipper shutdown,
             /printer/print/cancel as the fallback on GoKlipper) - clears the "error" a failed print leaves behind
  changes    websocket /websocket, printer.objects.subscribe -> notify_status_update (instant state changes)
  camera     GET  <snapshot_url>                  (Rinkhals: http://<ip>/webcam/?action=snapshot)

  messages   display_status.message (M117), print_stats.message, webhooks.state_message, the filament_hub's slots and
             hub status, and while paused or in error the last error lines of GET /server/gcode_store (the only place
             GoKlipper under Rinkhals tells why it stopped: "!! error: ... filament hub feed filament timeout")
  files      GET  /server/files/directory?path=gcodes (names, sizes, dates, disk_usage) | GET /server/files/gcodes/<name>
             | DELETE /server/files/gcodes/<name>
  light      POST /machine/device_power/device?device=<light_device>&action=on|off   (Rinkhals: "chamber_light")
  dryer      POST /printer/gcode/script?script=MMU_DRYER_START UNIT=0 DURATION=<min> TEMP=<°C> | MMU_DRYER_STOP UNIT=0
             (Rinkhals mmu_ace component; the live dryer values come back in the `filament_hub` object, `mmu_machine`
             only mirrors the state and the target)

Options (config.yaml): url, api_key (optional), snapshot_url (optional), light_device (optional, default chamber_light
on Rinkhals; "" = the printer has no light), timeout (seconds, default 15), start_timeout (seconds the print/start call
may take, default 120), start_wait (seconds to watch the printer after a start call that gave no answer, default 60).
"""
from __future__ import annotations

import asyncio
import json
import logging
import os
import re
import time
from typing import Optional
from urllib.parse import quote

import aiohttp

from .base import (ERROR, IDLE, JOB_CANCELLED, JOB_DONE, JOB_FAILED, JOB_PAUSED, JOB_PRINTING, OFFLINE, PAUSED, PRINTING,
                   DriverError, JobStatus, PrinterDriver, PrinterStatus, safe_name)

log = logging.getLogger("farm_agent.moonraker")

OBJECTS = "print_stats&virtual_sdcard&extruder&heater_bed&display_status&ota_filament_hub&filament_hub&mmu_machine&mmu&webhooks"

# the console lines worth telling the operator: GoKlipper's own errors ("!! error: typ = WebRequestError, code =
# 10011710, message = filament hub feed filament timeout") and the spool command before them
CONSOLE_ERROR = re.compile(r"^(!!|error|Error)")
CONSOLE_COMMAND = re.compile(r"^(FEED_FILAMENT|UNWIND_FILAMENT|UNWIND_ALL_FILAMENT|T\d\b|M600|PAUSE|RESUME)")
CONSOLE_LINES = 50
CONSOLE_CACHE_SECONDS = 20.0
MESSAGE_LIMIT = 300

# print_stats.state -> (printer state, job state)
STATES = {
    "standby": (IDLE, None),
    "printing": (PRINTING, JOB_PRINTING),
    "paused": (PAUSED, JOB_PAUSED),
    "complete": (IDLE, JOB_DONE),
    "cancelled": (IDLE, JOB_CANCELLED),
    "error": (ERROR, JOB_FAILED),
}


class MoonrakerDriver(PrinterDriver):
    def __init__(self, key: str, options: dict):
        super().__init__(key, options)
        self.url = str(options["url"]).rstrip("/")
        self.timeout = aiohttp.ClientTimeout(total=float(options.get("timeout", 15)))
        self.start_timeout = float(options.get("start_timeout", 120))
        self.start_wait = float(options.get("start_wait", 60))
        self.start_poll = float(options.get("start_poll", 5))
        self.reset_settle = float(options.get("reset_settle", 5))
        self.headers = {"X-Api-Key": options["api_key"]} if options.get("api_key") else {}
        self.snapshot_url = options.get("snapshot_url")
        self.light_device = options.get("light_device", "chamber_light")
        self._session: Optional[aiohttp.ClientSession] = None
        self._ws_task: Optional[asyncio.Task] = None

    async def connect(self) -> None:
        self._session = aiohttp.ClientSession(headers=self.headers)
        self._ws_task = asyncio.create_task(self._listen(), name=f"ws-{self.key}")

    async def close(self) -> None:
        if self._ws_task:
            self._ws_task.cancel()
        if self._session:
            await self._session.close()

    # -- reading -----------------------------------------------------------------------------------------------
    async def status(self) -> PrinterStatus:
        try:
            data = await self._get(f"/printer/objects/query?{OBJECTS}")
        except Exception as e:  # noqa: BLE001 - whatever went wrong, the printer is not reachable for us
            log.debug("%s: status failed: %s", self.key, e)
            return PrinterStatus(OFFLINE)
        s = data.get("result", {}).get("status", {})
        stats, sd = s.get("print_stats", {}), s.get("virtual_sdcard", {})
        printer_state, job_state = STATES.get(stats.get("state", ""), (ERROR, None))
        telemetry = {
            "extruder": _num(s.get("extruder", {}).get("temperature")),
            "extruder_target": _num(s.get("extruder", {}).get("target")),
            "bed": _num(s.get("heater_bed", {}).get("temperature")),
            "bed_target": _num(s.get("heater_bed", {}).get("target")),
            "klipper_state": stats.get("state"),
        }
        # what went wrong, in the printer's words: a print that ended in error leaves it in print_stats.message, a
        # Klipper shutdown (MCU, heater, "nozzle must be hot first" from a macro) in webhooks.state_message. Without
        # this the operator had to walk to the printer to read the screen (U #2, 12 Oct 2026).
        hooks = s.get("webhooks") or {}
        if hooks.get("state") and hooks["state"] != "ready":
            telemetry["klippy"] = str(hooks["state"])
        error = str(stats.get("message") or "") if printer_state == ERROR else ""
        if not error and hooks.get("state") in ("shutdown", "error"):
            error = str(hooks.get("state_message") or "")
        if error:
            telemetry["error"] = " ".join(error.split())[:160]
        # everything the printer has to say, in one line for the operator (docs/AM.md §10): the screen's M117 text,
        # print_stats.message, a Klipper shutdown, the ACE's hub and slots, and while it stands still the last error
        # lines of the console. Rinkhals shows the reason of a pause nowhere else: the screen only offers resume or stop.
        message = await self._message(s, printer_state, stats, hooks)
        if message:
            telemetry["message"] = message
        confirmed = self.plate_confirmed(terminal=stats.get("state") in ("complete", "cancelled", "error") and bool(stats.get("filename")),
                                         idle=stats.get("state") == "standby", now=time.time())
        if confirmed:
            telemetry["bed_cleared_at"] = confirmed
        # Rinkhals (Kobra S1) adds layers and the remaining time; plain Klipper has neither, both are optional
        info = stats.get("info") or {}
        if info.get("total_layer"):
            telemetry["layer"] = f"{info.get('current_layer', 0)}/{info['total_layer']}"
        if sd.get("remain_time"):
            telemetry["remain_min"] = round(float(sd["remain_time"]) / 60)
        # Anycubic ACE (Rinkhals object ota_filament_hub): standby, or busy while it changes the spool
        hub = s.get("ota_filament_hub") or {}
        if hub.get("state"):
            telemetry["ace"] = hub["state"] if hub["state"] == "standby" else f"{hub['state']} {hub.get('progress', 0)}%"
        # the ACE's built-in filament dryer, so the operator sees what it does. Rinkhals' `filament_hub` object carries
        # the live values (box temperature, remaining seconds, humidity); its Happy-Hare mirror `mmu_machine` knows only
        # the state and the target (a Kobra S1 on Rinkhals 20260901_01 reported 0/50 °C, 0 min there while drying),
        # so it is the fallback. Structured keys: the dashboard words them in the operator's language.
        hubs = (s.get("filament_hub") or {}).get("filament_hubs") or []
        hub0 = hubs[0] if hubs and isinstance(hubs[0], dict) else {}
        dry = hub0.get("dryer_status") if isinstance(hub0.get("dryer_status"), dict) else {}
        unit = ((s.get("mmu_machine") or {}).get("unit_0")) or {}
        dryer_state = dry.get("status") or unit.get("dryer_status")
        if dryer_state:
            temp = _num(hub0.get("temp")) if hub0.get("temp") is not None else _num(unit.get("dryer_temp"))
            target = _num(dry.get("target_temp")) if dry.get("target_temp") is not None else _num(unit.get("dryer_target_temp"))
            remain = round(float(dry["remain_time"]) / 60) if dry.get("remain_time") else int(unit.get("dryer_remaining") or 0)
            humidity = _num(hub0.get("humidity")) if hub0.get("humidity") is not None else _num(unit.get("dryer_humidity"))
            off = dryer_state == "stop"
            telemetry["dryer_state"] = "off" if off else str(dryer_state)
            telemetry["dryer_temp"] = temp or 0
            telemetry["dryer_target"] = 0 if off else (target or 0)
            telemetry["dryer_remain_min"] = 0 if off else remain
            telemetry["dryer_rh"] = humidity or 0
        job = None
        if job_state and stats.get("filename"):
            # Verified on a Kobra S1 (Rinkhals 20260901_01): during the start macro (heating, LeviQ, purge line)
            # progress stays 0 and print_duration is reset to 0 when the first layer begins, so print_duration is
            # the pure printing time - the number the price calibration wants.
            progress = sd.get("progress")
            if progress is None:
                progress = s.get("display_status", {}).get("progress") or 0
            job = JobStatus(
                filename=os.path.basename(stats["filename"]),
                status=job_state,
                progress=100.0 if job_state == JOB_DONE else round(float(progress) * 100, 2),
                print_duration=int(stats.get("print_duration") or 0),
                filament_used=float(stats.get("filament_used") or 0),
                # a print that stands still carries the whole reason, a running one only what print_stats says
                message=message if job_state in (JOB_PAUSED, JOB_FAILED) else str(stats.get("message") or ""),
            )
        return PrinterStatus(printer_state, {k: v for k, v in telemetry.items() if v is not None}, job)

    async def _message(self, s: dict, printer_state: str, stats: dict, hooks: dict) -> str:
        parts: list[str] = []

        def add(text) -> None:
            # the same thing said twice (print_stats and the console, a msg with and without its code) is said once
            text = " ".join(str(text or "").split())
            if not text:
                return
            for i, known in enumerate(parts):
                if text in known:
                    return
                if known in text:
                    parts[i] = text
                    return
            parts.append(text)

        add((s.get("display_status") or {}).get("message"))
        add(stats.get("message"))
        if hooks.get("state") in ("shutdown", "error"):
            add(hooks.get("state_message"))
        hubs = (s.get("filament_hub") or {}).get("filament_hubs") or []
        hub0 = hubs[0] if hubs and isinstance(hubs[0], dict) else {}
        if hub0.get("status") and hub0["status"] != "ready":
            add(f"ACE {hub0['status']}")
        for slot in hub0.get("slots") or []:
            if isinstance(slot, dict) and str(slot.get("status") or "") not in ("", "ready"):
                add(f"ACE slot {int(slot.get('index', 0)) + 1} {slot['status']}")
        ota = s.get("ota_filament_hub") or {}
        if ota.get("state") and ota["state"] != "standby":
            add(f"ACE {ota['state']} {ota.get('progress', 0)}%")
        if printer_state in (PAUSED, ERROR):
            # what the console said since this print began (total_duration is the time since its start)
            since = time.time() - float(stats.get("total_duration") or 3600)
            for line in await self._console_errors(since):
                add(line)
        return " · ".join(parts)[:MESSAGE_LIMIT]

    _console_cache: tuple = (0.0, [])

    async def _console_errors(self, since: float) -> list:
        """The error lines of Moonraker's console store after `since` (unix time), with the spool command before them;
        at most one request every CONSOLE_CACHE_SECONDS, nothing when the request fails."""
        at, lines = self._console_cache
        if time.monotonic() - at < CONSOLE_CACHE_SECONDS:
            return lines
        try:
            store = (await self._get(f"/server/gcode_store?count={CONSOLE_LINES}")).get("result", {}).get("gcode_store") or []
        except Exception as e:  # noqa: BLE001 - the reason is a courtesy; the state itself is already known
            log.debug("%s: gcode_store failed: %s", self.key, e)
            store = []
        errors, command = [], ""
        for item in store:
            if not isinstance(item, dict) or float(item.get("time") or 0) < since:
                continue
            text = str(item.get("message") or "").split("\n", 1)[0].strip()
            if item.get("type") == "command":
                if CONSOLE_COMMAND.match(text):
                    command = text
            elif CONSOLE_ERROR.match(text):
                errors.append(text.lstrip("! ").strip())
        out = ([f"last command {command}"] if command and errors else []) + errors[-3:]
        self._console_cache = (time.monotonic(), out)
        return out

    async def slot_status(self, slot: int) -> Optional[str]:
        """The ACE's own word for the slot (Rinkhals filament_hub: "ready", "empty", "runout"), else Happy Hare's gate
        status from the mmu object (1 available, 0 empty); None when neither says anything."""
        try:
            s = (await self._get("/printer/objects/query?filament_hub&mmu"))["result"]["status"]
        except Exception as e:  # noqa: BLE001 - no answer, no check
            log.debug("%s: slot status failed: %s", self.key, e)
            return None
        hubs = (s.get("filament_hub") or {}).get("filament_hubs") or []
        hub0 = hubs[0] if hubs and isinstance(hubs[0], dict) else {}
        for item in hub0.get("slots") or []:
            if isinstance(item, dict) and int(item.get("index", -1)) == int(slot) and item.get("status"):
                return str(item["status"])
        gates = (s.get("mmu") or {}).get("gate_status") or []
        if 0 <= int(slot) < len(gates):
            return {1: "ready", 0: "empty"}.get(int(gates[int(slot)]))
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

    async def head(self) -> Optional[tuple[float, float, float]]:
        if not self._session:
            return None
        try:
            s = (await self._get("/printer/objects/query?motion_report=live_position,live_velocity&toolhead=position&print_stats=state"))["result"]["status"]
        except Exception:  # noqa: BLE001 - no position, no picture; the print goes on
            return None
        if s.get("print_stats", {}).get("state") != "printing":
            return None
        live = (s.get("motion_report") or {}).get("live_position") or []
        if len(live) >= 2:
            return float(live[0]), float(live[1]), float(s["motion_report"].get("live_velocity") or 0.0)
        # GoKlipper (Rinkhals on the Kobra S1) has no motion_report: toolhead.position is where the head is GOING,
        # speed unknown (None) - the agent then waits until the head can have arrived
        planned = (s.get("toolhead") or {}).get("position") or []
        return (float(planned[0]), float(planned[1]), None) if len(planned) >= 2 else None

    async def light(self, on: bool) -> None:
        if not self.light_device or not self._session:
            return
        try:
            # Moonraker's shell device does not learn about the printer's own light button: when it believes the light
            # is on already, "on" does nothing, so switch off first (Kobra S1, 23 Sep 2026: status "on", camera black)
            for action in (("off", "on") if on else ("off",)):
                await self._post("/machine/device_power/device", params={"device": self.light_device, "action": action})
        except DriverError as e:
            log.debug("%s: light %s failed: %s", self.key, "on" if on else "off", e)

    async def dry(self, on: bool, temp: int = 45, minutes: int = 240) -> None:
        # Rinkhals wraps the ACE dryer as Happy-Hare style macros (MMU_DRYER_START / MMU_DRYER_STOP); the ACE Pro
        # goes up to 55 °C and dries all four spools in the box at once
        script = f"MMU_DRYER_START UNIT=0 DURATION={int(minutes)} TEMP={int(temp)}" if on else "MMU_DRYER_STOP UNIT=0"
        await self._post("/printer/gcode/script", params={"script": script}, timeout=60)

    # -- the printer's storage -------------------------------------------------------------------------------------
    async def files(self) -> dict:
        try:
            result = (await self._get("/server/files/directory?path=gcodes")).get("result") or {}
        except Exception as e:  # noqa: BLE001
            raise DriverError(f"file list: {e.__class__.__name__} {e}") from e
        files = [{"name": str(f.get("filename")), "size": int(f.get("size") or 0), "modified": int(float(f.get("modified") or 0))}
                 for f in result.get("files") or [] if isinstance(f, dict) and f.get("filename")]
        files.sort(key=lambda f: f["modified"], reverse=True)
        usage = result.get("disk_usage") if isinstance(result.get("disk_usage"), dict) else {}
        return {"files": files, "free": _int(usage.get("free")), "total": _int(usage.get("total"))}

    async def fetch(self, name: str, target: str) -> int:
        assert self._session
        name = safe_name(name)
        os.makedirs(os.path.dirname(target) or ".", exist_ok=True)
        size = 0
        try:
            async with self._session.get(self.url + "/server/files/gcodes/" + quote(name), timeout=aiohttp.ClientTimeout(total=1800)) as r:
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
        assert self._session
        name = safe_name(name)
        try:
            async with self._session.delete(self.url + "/server/files/gcodes/" + quote(name), timeout=self.timeout) as r:
                body = await r.text()
                if r.status >= 400:
                    raise DriverError(f"delete {name}: HTTP {r.status} {_error_text(body)}")
        except (aiohttp.ClientError, asyncio.TimeoutError) as e:
            raise DriverError(f"delete {name}: {e.__class__.__name__} {e}") from e

    # -- acting ------------------------------------------------------------------------------------------------
    async def start(self, gcode_path: str, filename: str, slot: int) -> None:
        now = await self.status()
        if now.state != IDLE:
            raise DriverError(f"printer is {now.state}, not idle")
        form = aiohttp.FormData()
        form.add_field("root", "gcodes")
        with open(gcode_path, "rb") as fh:
            form.add_field("file", fh, filename=filename, content_type="application/octet-stream")
            await self._post("/server/files/upload", data=form, timeout=aiohttp.ClientTimeout(total=600))
        # the G-code already selects the spool position (T<slot>); nothing to tell the printer separately
        log.info("%s: starting %s (slot %d)", self.key, filename, slot + 1)
        try:
            await self._post("/printer/print/start", params={"filename": filename}, timeout=aiohttp.ClientTimeout(total=self.start_timeout))
        except DriverError as e:
            if "TimeoutError" not in str(e):
                raise
            # Seen on a Kobra S1 (U #2, 11-12 Oct 2026, four times): the call runs past the limit, yet the printer has
            # taken the file - it is heating, or it has already failed on the start. Giving up here left the server with
            # a "failed" job and the printer with a print nobody tracked, so look at what the printer does first.
            log.warning("%s: print/start gave no answer in %.0f s, watching the printer", self.key, self.start_timeout)
            deadline = time.monotonic() + self.start_wait
            while time.monotonic() < deadline:
                await asyncio.sleep(self.start_poll)
                now = await self.status()
                if now.state in (PRINTING, PAUSED) and (not now.job or now.job.filename == filename):
                    log.info("%s: the printer took %s after all", self.key, filename)
                    return
                if now.state == ERROR:
                    said = (now.job.message if now.job else "") or now.telemetry.get("error") or "no message"
                    raise DriverError(f"print start: the printer reports an error: {said}") from e
            raise

    async def pause(self) -> None:
        await self._post("/printer/print/pause")

    async def resume(self) -> None:
        await self._post("/printer/print/resume")

    async def cancel(self) -> None:
        await self._post("/printer/print/cancel")

    async def reset(self) -> None:
        """Clear the error the last print left behind, so the printer reports idle again and the queue may go on.
        Klipper keeps print_stats.state = "error" until the next print or SDCARD_RESET_FILE; a shutdown of Klipper
        itself (MCU, heater) needs FIRMWARE_RESTART. GoKlipper (the stock Anycubic firmware under Rinkhals) may not know
        SDCARD_RESET_FILE; a cancel after a failed print takes it to "cancelled", which is idle for us. Nothing moves and
        nothing heats on any of these."""
        now = await self.status()
        if now.state in (PRINTING, PAUSED):
            raise DriverError("a print is running, nothing to reset")
        if now.telemetry.get("klippy") in ("shutdown", "error"):
            log.info("%s: Klipper is %s, FIRMWARE_RESTART", self.key, now.telemetry["klippy"])
            await self._post("/printer/firmware_restart", timeout=aiohttp.ClientTimeout(total=60))
            await asyncio.sleep(self.reset_settle)
        try:
            await self._post("/printer/gcode/script", params={"script": "SDCARD_RESET_FILE"}, timeout=aiohttp.ClientTimeout(total=60))
        except DriverError as e:
            log.info("%s: SDCARD_RESET_FILE refused (%s), trying a cancel", self.key, e)
        now = await self.status()
        if now.state == ERROR:
            try:
                await self._post("/printer/print/cancel")
            except DriverError as e:
                log.info("%s: cancel refused too: %s", self.key, e)
            await asyncio.sleep(min(1.0, self.reset_settle))
            now = await self.status()
        if now.state == ERROR:
            raise DriverError(f"the printer still reports an error: {now.telemetry.get('error') or 'no message'}")
        log.info("%s: error cleared, the printer is %s", self.key, now.state)

    # -- plumbing ----------------------------------------------------------------------------------------------
    async def _get(self, path: str) -> dict:
        assert self._session
        async with self._session.get(self.url + path, timeout=self.timeout) as r:
            r.raise_for_status()
            return await r.json()

    async def _post(self, path: str, *, params=None, data=None, timeout=None) -> dict:
        assert self._session
        try:
            async with self._session.post(self.url + path, params=params, data=data, timeout=timeout or self.timeout) as r:
                body = await r.text()
                if r.status >= 400:
                    raise DriverError(f"{path}: HTTP {r.status} {_error_text(body)}")
                return json.loads(body) if body else {}
        except (aiohttp.ClientError, asyncio.TimeoutError) as e:
            raise DriverError(f"{path}: {e.__class__.__name__} {e}") from e

    async def _listen(self) -> None:
        """Websocket subscription: a state change reaches the server at once instead of at the next poll."""
        ws_url = self.url.replace("http", "ws", 1) + "/websocket"
        while True:
            try:
                assert self._session
                async with self._session.ws_connect(ws_url, heartbeat=30) as ws:
                    await ws.send_json({"jsonrpc": "2.0", "method": "printer.objects.subscribe", "id": 1,
                                        "params": {"objects": {"print_stats": ["state", "filename"]}}})
                    async for msg in ws:
                        if msg.type != aiohttp.WSMsgType.TEXT:
                            continue
                        event = json.loads(msg.data)
                        if event.get("method") == "notify_status_update" and "print_stats" in (event.get("params") or [{}])[0]:
                            if self.on_change:
                                await self.on_change()
                        elif event.get("method") in ("notify_klippy_shutdown", "notify_klippy_disconnected"):
                            if self.on_change:
                                await self.on_change()
            except asyncio.CancelledError:
                raise
            except Exception as e:  # noqa: BLE001 - printer switched off, network down... polling still works meanwhile
                log.debug("%s: websocket down: %s", self.key, e)
            await asyncio.sleep(10)


def _num(v) -> Optional[float]:
    return round(float(v), 1) if isinstance(v, (int, float)) else None


def _int(v) -> Optional[int]:
    return int(v) if isinstance(v, (int, float)) else None


def _error_text(body: str) -> str:
    try:
        return str(json.loads(body).get("error", {}).get("message", ""))[:200]
    except ValueError:
        return body[:200]
