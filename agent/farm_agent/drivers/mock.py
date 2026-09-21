"""
Simulated printer: the whole order flow can be tried without touching a real machine.

Options (config.yaml):
  print_seconds   how long a print takes (default 90)
  heat_seconds    "heating" before progress starts (default 5)
  fail_at         fail the print at this percentage (optional; e.g. 40)
  filament_mm     filament length reported at 100 % (default 3600)

A started print heats, runs to 100 % and finishes; pause, resume and cancel behave like on a real printer.
After a finished print the mock stays idle with the last job reported as done - exactly what Moonraker does.
"""
from __future__ import annotations

import base64
import os
import time
from typing import Optional

from .base import (ERROR, IDLE, JOB_CANCELLED, JOB_DONE, JOB_FAILED, JOB_PAUSED, JOB_PRINTING, PAUSED, PRINTING, DriverError,
                   JobStatus, PrinterDriver, PrinterStatus)

# a real 16 x 12 px grey JPEG (the server checks the content, not the file name): enough to exercise the snapshot upload
_JPEG = base64.b64decode(
    "/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAA0JCgsKCA0LCgsODg0PEyAVExISEyccHhcgLikxMC4pLSwzOko+MzZGNywtQFdBRkxOUlNSMj5aYVpQYEpRUk//2wBDAQ4O"
    "DhMREyYVFSZPNS01T09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT0//wAARCAAMABADASIAAhEBAxEB/8QAHwAAAQUBAQEB"
    "AQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0"
    "NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi"
    "4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEI"
    "FEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaan"
    "qKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwCGiiimI//Z")


class MockDriver(PrinterDriver):
    def __init__(self, key: str, options: dict):
        super().__init__(key, options)
        self.print_seconds = float(options.get("print_seconds", 90))
        self.heat_seconds = float(options.get("heat_seconds", 5))
        self.fail_at = options.get("fail_at")
        self.filament_mm = float(options.get("filament_mm", 3600))
        self._file: Optional[str] = None
        self._state = "standby"          # Klipper's words: standby | printing | paused | complete | cancelled | error
        self._printed = 0.0              # seconds of actual printing so far
        self._since: Optional[float] = None
        self._message = ""

    def _advance(self) -> None:
        if self._state == "printing" and self._since is not None:
            now = time.monotonic()
            self._printed += now - self._since
            self._since = now
            progress = self._progress()
            if self.fail_at is not None and progress >= float(self.fail_at):
                self._state, self._message, self._since = "error", "MOCK: simulated failure (heater fault)", None
            elif progress >= 100:
                self._state, self._since = "complete", None

    def _progress(self) -> float:
        return max(0.0, min(100.0, (self._printed - self.heat_seconds) / self.print_seconds * 100))

    async def status(self) -> PrinterStatus:
        self._advance()
        printer = {"standby": IDLE, "printing": PRINTING, "paused": PAUSED, "complete": IDLE, "cancelled": IDLE, "error": ERROR}[self._state]
        heating = self._state == "printing" and self._printed < self.heat_seconds
        hot = self._state in ("printing", "paused")
        telemetry = {
            "extruder": round(25 + 195 * min(1.0, self._printed / max(self.heat_seconds, 0.1)), 1) if hot else 25.0,
            "extruder_target": 220.0 if hot else 0.0,
            "bed": round(25 + 30 * min(1.0, self._printed / max(self.heat_seconds, 0.1)), 1) if hot else 25.0,
            "bed_target": 55.0 if hot else 0.0,
            "klipper_state": "heating" if heating else self._state,
            "mock": True,
        }
        job = None
        if self._file and self._state != "standby":
            p = self._progress()
            job = JobStatus(
                filename=self._file,
                status={"printing": JOB_PRINTING, "paused": JOB_PAUSED, "complete": JOB_DONE, "cancelled": JOB_CANCELLED, "error": JOB_FAILED}[self._state],
                progress=round(p, 2),
                print_duration=int(self._printed),
                filament_used=round(self.filament_mm * p / 100, 1),
                message=self._message,
            )
        return PrinterStatus(printer, telemetry, job)

    async def start(self, gcode_path: str, filename: str, slot: int) -> None:
        self._advance()
        if self._state in ("printing", "paused"):
            raise DriverError("printer is busy")
        if os.path.getsize(gcode_path) == 0:
            raise DriverError("empty G-code file")
        self._file, self._state, self._printed, self._since, self._message = filename, "printing", 0.0, time.monotonic(), ""
        await self._changed()

    async def pause(self) -> None:
        self._advance()
        if self._state != "printing":
            raise DriverError("nothing is printing")
        self._state, self._since = "paused", None
        await self._changed()

    async def resume(self) -> None:
        if self._state != "paused":
            raise DriverError("nothing is paused")
        self._state, self._since = "printing", time.monotonic()
        await self._changed()

    async def cancel(self) -> None:
        self._advance()
        if self._state not in ("printing", "paused"):
            raise DriverError("nothing to cancel")
        self._state, self._since = "cancelled", None
        await self._changed()

    async def snapshot(self) -> Optional[bytes]:
        return _JPEG

    async def _changed(self) -> None:
        if self.on_change:
            await self.on_change()
