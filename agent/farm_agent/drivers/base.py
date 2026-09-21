"""
Printer driver contract. The agent only ever talks to a printer through this interface, so another kind of
printer (PrusaLink, SimplyPrint, ...) is one new file in this package and a `driver:` name in the config.
"""
from __future__ import annotations

import abc
from dataclasses import dataclass, field
from typing import Awaitable, Callable, Optional

# printer states the server understands
IDLE, PRINTING, PAUSED, ERROR, OFFLINE = "idle", "printing", "paused", "error", "offline"

# states of the file the printer is (or was last) printing
JOB_PRINTING, JOB_PAUSED, JOB_DONE, JOB_CANCELLED, JOB_FAILED = "printing", "paused", "done", "cancelled", "failed"


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

    @abc.abstractmethod
    async def pause(self) -> None: ...

    @abc.abstractmethod
    async def resume(self) -> None: ...

    @abc.abstractmethod
    async def cancel(self) -> None: ...

    async def snapshot(self) -> Optional[bytes]:
        """JPEG from the printer's camera, or None when there is none."""
        return None
