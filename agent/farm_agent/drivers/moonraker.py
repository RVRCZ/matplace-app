"""
Moonraker (Klipper) driver - Anycubic Kobra S1 with the Rinkhals firmware overlay, or any other Klipper printer.

  status     GET  /printer/objects/query?print_stats&virtual_sdcard&extruder&heater_bed&display_status
  upload     POST /server/files/upload            (multipart, root=gcodes)
  start      POST /printer/print/start?filename=
  control    POST /printer/print/pause | resume | cancel
  changes    websocket /websocket, printer.objects.subscribe -> notify_status_update (instant state changes)
  camera     GET  <snapshot_url>                  (Rinkhals: http://<ip>/webcam/?action=snapshot)

  light      POST /machine/device_power/device?device=<light_device>&action=on|off   (Rinkhals: "chamber_light")
  dryer      POST /printer/gcode/script?script=MMU_DRYER_START UNIT=0 DURATION=<min> TEMP=<°C> | MMU_DRYER_STOP UNIT=0
             (Rinkhals mmu_ace component; the dryer state comes back in the `mmu_machine` object)

Options (config.yaml): url, api_key (optional), snapshot_url (optional), light_device (optional, default chamber_light
on Rinkhals; "" = the printer has no light), timeout (seconds, default 15).
"""
from __future__ import annotations

import asyncio
import json
import logging
import os
from typing import Optional

import aiohttp

from .base import (ERROR, IDLE, JOB_CANCELLED, JOB_DONE, JOB_FAILED, JOB_PAUSED, JOB_PRINTING, OFFLINE, PAUSED, PRINTING,
                   DriverError, JobStatus, PrinterDriver, PrinterStatus)

log = logging.getLogger("farm_agent.moonraker")

OBJECTS = "print_stats&virtual_sdcard&extruder&heater_bed&display_status&ota_filament_hub&mmu_machine"

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
        # the ACE's built-in filament dryer (Rinkhals mmu_ace): what it does right now, so the operator sees it
        unit = ((s.get("mmu_machine") or {}).get("unit_0")) or {}
        if unit.get("dryer_status"):
            if unit["dryer_status"] == "stop":
                telemetry["dryer"] = "off" + (f", {unit['dryer_humidity']}% RH" if unit.get("dryer_humidity") else "")
            else:
                telemetry["dryer"] = (f"{unit['dryer_status']} {unit.get('dryer_temp', 0)}/{unit.get('dryer_target_temp', 0)} °C, "
                                      f"{unit.get('dryer_remaining', 0)} min left, {unit.get('dryer_humidity', 0)}% RH")
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
                message=str(stats.get("message") or ""),
            )
        return PrinterStatus(printer_state, {k: v for k, v in telemetry.items() if v is not None}, job)

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
        await self._post("/printer/print/start", params={"filename": filename})

    async def pause(self) -> None:
        await self._post("/printer/print/pause")

    async def resume(self) -> None:
        await self._post("/printer/print/resume")

    async def cancel(self) -> None:
        await self._post("/printer/print/cancel")

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


def _error_text(body: str) -> str:
    try:
        return str(json.loads(body).get("error", {}).get("message", ""))[:200]
    except ValueError:
        return body[:200]
