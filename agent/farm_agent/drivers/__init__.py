"""Printer drivers. Register a new one here; the rest of the agent does not change."""
from .anycubic_lan import AnycubicLanDriver
from .base import DriverError, JobStatus, PrinterDriver, PrinterStatus
from .mock import MockDriver
from .moonraker import MoonrakerDriver

DRIVERS = {
    "moonraker": MoonrakerDriver,
    "anycubic_lan": AnycubicLanDriver,     # Kobra X (Kobra 3 / S1 family) with the stock firmware in LAN mode
    "mock": MockDriver,
    # later: "prusalink": PrusaLinkDriver, "simplyprint": SimplyPrintDriver
}


def create(key: str, driver: str, options: dict) -> PrinterDriver:
    try:
        return DRIVERS[driver](key, options)
    except KeyError:
        raise ValueError(f"printer '{key}': unknown driver '{driver}' (known: {', '.join(DRIVERS)})") from None


__all__ = ["DRIVERS", "create", "DriverError", "JobStatus", "PrinterDriver", "PrinterStatus"]
