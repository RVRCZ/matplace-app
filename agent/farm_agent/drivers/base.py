"""
Printer driver contract. The agent only ever talks to a printer through this interface, so another kind of
printer (PrusaLink, SimplyPrint, ...) is one new file in this package and a `driver:` name in the config.
"""
from __future__ import annotations

import abc
import os
from dataclasses import dataclass, field
from typing import Awaitable, Callable, Optional

# printer states the server understands
IDLE, PRINTING, PAUSED, ERROR, OFFLINE = "idle", "printing", "paused", "error", "offline"

# states of the file the printer is (or was last) printing
JOB_PRINTING, JOB_PAUSED, JOB_DONE, JOB_CANCELLED, JOB_FAILED = "printing", "paused", "done", "cancelled", "failed"

# what a printer says about a spool position with nothing in it (slot_status); any other word means a spool is there
EMPTY_SLOT_WORDS = ("empty", "runout")

# how long after the operator confirmed the finished print on the printer the telemetry keeps saying so (several
# reports, so the server cannot miss it)
PLATE_CONFIRM_SECONDS = 60.0


@dataclass
class JobStatus:
    filename: str
    status: str                       # JOB_*
    progress: float = 0.0             # 0-100
    print_duration: int = 0           # seconds the printer was actually printing
    filament_used: float = 0.0        # millimetres
    message: str = ""


@dataclass
class PrinterStatus:
    state: str                        # IDLE | PRINTING | PAUSED | ERROR | OFFLINE
    telemetry: dict = field(default_factory=dict)
    job: Optional[JobStatus] = None   # what the printer says about its current / last file, if anything


class DriverError(Exception):
    """The printer refused or could not be reached. The message goes to the operator."""


def safe_name(name: str) -> str:
    """A file name on the printer as the agent accepts it from the server: a plain name, no folder, no way up."""
    name = str(name or "").strip()
    if not name or name in (".", "..") or "/" in name or "\\" in name or name != os.path.basename(name) or len(name) > 200:
        raise DriverError(f"not a plain file name: {name!r}")
    return name


class PrinterDriver(abc.ABC):
    def __init__(self, key: str, options: dict):
        self.key = key
        self.options = options
        # set by the agent: called (without arguments) when the driver hears about a change by itself
        self.on_change: Optional[Callable[[], Awaitable[None]]] = None

    async def connect(self) -> None:
        """Open long-lived connections (websocket...). Must not raise when the printer is off: status() reports that."""

    async def close(self) -> None:
        """Release everything."""

    @abc.abstractmethod
    async def status(self) -> PrinterStatus:
        """Never raises: an unreachable printer is PrinterStatus(OFFLINE)."""

    @abc.abstractmethod
    async def start(self, gcode_path: str, filename: str, slot: int) -> None:
        """Send the file to the printer and start printing it. Raises DriverError when the print did not start."""

    async def slot_status(self, slot: int) -> Optional[str]:
        """What the printer says about a spool position before a start: a word of its own ("ready", "loaded"; "empty"
        or "runout" = nothing to print from, EMPTY_SLOT_WORDS), None when the firmware does not tell. Never raises."""
        return None

    # -- the printer's storage (docs/AM.md §10) --------------------------------------------------------------------
    async def files(self) -> dict:
        """What the printer's storage holds: {"files": [{"name", "size", "modified"}, ...] newest first, "free": bytes
        or None, "total": bytes or None}. Raises DriverError when the printer does not list its files."""
        raise DriverError("this printer does not list its files")

    async def fetch(self, name: str, target: str) -> int:
        """Copy one file of the printer's storage to `target` on this machine; returns its size. Raises DriverError."""
        raise DriverError("this printer does not hand out its files")

    async def delete(self, name: str) -> None:
        """Remove one file from the printer's storage. Raises DriverError."""
        raise DriverError("this printer does not delete its files from here")

    # -- the operator confirmed the finished print on the printer ---------------------------------------------------
    _plate_terminal_seen: bool = False
    _plate_cleared_at: float = 0.0

    def plate_confirmed(self, terminal: bool, idle: bool, now: float) -> Optional[int]:
        """The moment the printer went from a finished print (`terminal`: complete, cancelled, error, the file still
        named) to plain idle (`idle`) without a new print: on a Kobra S1 with Rinkhals that is what the operator's
        reset of the finished job does (docs/AM.md §10), the display's own OK leaves no trace. Returns the unix time of
        that moment for PLATE_CONFIRM_SECONDS, so the telemetry carries it in several reports; None otherwise."""
        if terminal:
            self._plate_terminal_seen = True
        elif idle and self._plate_terminal_seen:
            self._plate_terminal_seen = False
            self._plate_cleared_at = now
        if self._plate_cleared_at and now - self._plate_cleared_at <= PLATE_CONFIRM_SECONDS:
            return int(self._plate_cleared_at)
        return None

    @abc.abstractmethod
    async def pause(self) -> None: ...

    @abc.abstractmethod
    async def resume(self) -> None: ...

    @abc.abstractmethod
    async def cancel(self) -> None: ...

    async def snapshot(self) -> Optional[bytes]:
        """JPEG from the printer's camera, or None when there is none."""
        return None

    async def head(self) -> Optional[tuple[float, float, Optional[float]]]:
        """Where the print head is while printing (x, y, speed in mm/s), None when unknown or not printing. Speed None:
        the printer reports only the planned target (the head may still be travelling there).
        Used for the layer-synced time-lapse: the G-code parks the head after every layer and the agent takes the
        picture while it stands there."""
        return None

    async def light(self, on: bool) -> None:
        """Chamber light, when the printer has one: on for the camera during a print. Never raises."""

    async def dry(self, on: bool, temp: int = 45, minutes: int = 240) -> None:
        """Filament dryer of the spool unit (Anycubic ACE): start for `minutes` at `temp` °C, or stop. Raises DriverError."""
        raise DriverError("this printer has no filament dryer")

    async def reset(self) -> None:
        """Clear the error state the last print left behind so the printer reports idle again. Nothing moves, nothing
        heats. Raises DriverError when the printer stays in error (its message goes to the operator)."""
        raise DriverError("this printer cannot be reset from here")
