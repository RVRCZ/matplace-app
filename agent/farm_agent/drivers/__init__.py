"""Printer drivers. Register a new one here; the rest of the agent does not change."""
from .base import DriverError, JobStatus, PrinterDriver, PrinterStatus
from .mock import MockDriver
from .moonraker import MoonrakerDriver

DRIVERS = {
    "moonraker": MoonrakerDriver,
    "mock": MockDriver,
    # later: "prusalink": PrusaLinkDriver, "simplyprint": SimplyPrintDriver
}


def create(key: str, driver: str, options: dict) -> PrinterDriver:
    try:
        return DRIVERS[driver](key, options)
    except KeyError:
        raise ValueError(f"printer '{key}': unknown driver '{driver}' (known: {', '.join(DRIVERS)})") from None


__all__ = ["DRIVERS", "create", "DriverError", "JobStatus", "PrinterDriver", "PrinterStatus"]
