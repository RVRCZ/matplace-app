"""
matplace side of the agent. Every call goes OUT from the farm's network over HTTPS with the agent's bearer token;
nothing ever connects in, so the printers stay behind NAT with no open port.
"""
from __future__ import annotations

import asyncio
import hashlib
import os
from typing import Optional

import aiohttp


class ServerError(Exception):
    pass


class Server:
    def __init__(self, base: str, token: str, verify_tls: bool = True):
        self.base = base
        self.headers = {"Authorization": f"Bearer {token}", "Accept": "application/json"}
        self.verify_tls = verify_tls
        self._session: Optional[aiohttp.ClientSession] = None

    async def open(self) -> None:
        self._session = aiohttp.ClientSession(headers=self.headers, connector=aiohttp.TCPConnector(ssl=None if self.verify_tls else False))

    async def close(self) -> None:
        if self._session:
            await self._session.close()

    async def sync(self, version: str, printers: list[dict]) -> dict:
        return await self._json("POST", "/api/agent/sync", json={"version": version, "printers": printers}, timeout=20)

    async def command_result(self, command_id: int, ok: bool, message: str = "") -> None:
        await self._json("POST", f"/api/agent/commands/{command_id}/result", json={"ok": ok, "message": message[:300]}, timeout=20)

    async def snapshot(self, printer_key: str, jpeg: bytes, job_id: Optional[int]) -> None:
        form = aiohttp.FormData()
        form.add_field("image", jpeg, filename="snapshot.jpg", content_type="image/jpeg")
        if job_id:
            form.add_field("job_id", str(job_id))
        await self._json("POST", f"/api/agent/printers/{printer_key}/snapshot", data=form, timeout=60)

    async def download_gcode(self, url: str, target: str) -> str:
        """Stream the G-code to `target`, verify it against the hash the server sent. Returns the sha256."""
        assert self._session
        if not url.startswith(self.base + "/"):
            raise ServerError("G-code URL does not belong to the configured server")
        os.makedirs(os.path.dirname(target), exist_ok=True)
        digest = hashlib.sha256()
        try:
            async with self._session.get(url, timeout=aiohttp.ClientTimeout(total=900)) as r:
                if r.status != 200:
                    raise ServerError(f"G-code download: HTTP {r.status}")
                expected = r.headers.get("X-Content-Sha256", "")
                with open(target + ".part", "wb") as fh:
                    async for chunk in r.content.iter_chunked(1 << 16):
                        digest.update(chunk)
                        fh.write(chunk)
        except (aiohttp.ClientError, asyncio.TimeoutError) as e:
            raise ServerError(f"G-code download: {e.__class__.__name__} {e}") from e
        if not expected or digest.hexdigest() != expected:
            os.remove(target + ".part")
            raise ServerError("G-code download: checksum mismatch")
        os.replace(target + ".part", target)
        return digest.hexdigest()

    async def _json(self, method: str, path: str, *, timeout: float, **kw) -> dict:
        assert self._session
        try:
            async with self._session.request(method, self.base + path, timeout=aiohttp.ClientTimeout(total=timeout), **kw) as r:
                if r.status == 401:
                    raise ServerError("the server rejected the agent token (401)")
                if r.status >= 400:
                    raise ServerError(f"{path}: HTTP {r.status} {(await r.text())[:200]}")
                return await r.json()
        except (aiohttp.ClientError, asyncio.TimeoutError) as e:
            raise ServerError(f"{path}: {e.__class__.__name__} {e}") from e
