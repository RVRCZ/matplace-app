"""Configuration: one YAML file. See config.example.yaml."""
from __future__ import annotations

import os
from dataclasses import dataclass, field

import yaml


@dataclass
class PrinterConfig:
    key: str                     # the printer's "key" in the matplace admin
    driver: str                  # moonraker | mock
    options: dict = field(default_factory=dict)


@dataclass
class Config:
    server: str                  # https://beta.matplace.com
    token: str                   # agent token from /admin/farm/agents
    printers: list[PrinterConfig]
    poll_seconds: float = 5.0
    snapshot_seconds: float = 15.0
    work_dir: str = "./work"
    verify_tls: bool = True
    log_level: str = "INFO"


def load(path: str) -> Config:
    with open(path, encoding="utf-8") as fh:
        raw = yaml.safe_load(fh) or {}
    server = str(raw.get("server", "")).rstrip("/")
    token = str(os.environ.get("FARM_AGENT_TOKEN") or raw.get("token", ""))
    if not server.startswith(("https://", "http://localhost", "http://127.0.0.1")):
        raise ValueError("config: 'server' must be an https:// address (http only for localhost)")
    if not token:
        raise ValueError("config: 'token' is missing (or set FARM_AGENT_TOKEN)")
    printers = []
    for p in raw.get("printers") or []:
        options = {k: v for k, v in p.items() if k not in ("key", "driver")}
        printers.append(PrinterConfig(key=str(p["key"]), driver=str(p.get("driver", "moonraker")), options=options))
    if not printers:
        raise ValueError("config: no printers")
    if len({p.key for p in printers}) != len(printers):
        raise ValueError("config: printer keys must be unique")
    return Config(
        server=server, token=token, printers=printers,
        poll_seconds=max(2.0, float(raw.get("poll_seconds", 5))),
        snapshot_seconds=max(5.0, float(raw.get("snapshot_seconds", 15))),
        work_dir=str(raw.get("work_dir", "./work")),
        verify_tls=bool(raw.get("verify_tls", True)),
        log_level=str(raw.get("log_level", "INFO")).upper(),
    )
