"""
The PrusaLink driver against a fake PrusaLink (aiohttp) shaped after the Buddy firmware's answers (docs/AM.md §12):
X-Api-Key or a digest challenge, /api/v1/status + /api/v1/job (204 when nothing prints, 8.3 short names next to
the real ones), PUT upload with Overwrite / Print-After-Upload, POST to start, the IDLE moment while the firmware
scans the file, the ATTENTION state of the tools-mapping screen, FINISHED / STOPPED without a file, the USB listing
without sizes.   Run:  python -m pytest tests   (from agent/)
"""
import asyncio
import hashlib
import os
import re
import sys
import tempfile
import unittest

from aiohttp import web

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from farm_agent.drivers.base import ERROR, IDLE, JOB_CANCELLED, JOB_DONE, JOB_FAILED, JOB_PAUSED, JOB_PRINTING, OFFLINE, PAUSED, PRINTING, DriverError  # noqa: E402
from farm_agent.drivers.prusalink import PrusaLinkDriver  # noqa: E402

KEY = "s3cret-from-the-screen"
REALM = "Printer API"


def short_name(name: str) -> str:
    """An 8.3 name the way FAT makes one: MP12-M~1.GCO for mp12-matplace-x.gcode."""
    base, _, ext = name.rpartition(".")
    base = re.sub(r"[^A-Za-z0-9]", "", base or name).upper()[:6] or "FILE"
    return f"{base}~1.{(ext or 'GCO').upper()[:3]}"


class FakePrusaLink:
    def __init__(self):
        self.state = "IDLE"              # the printer's word in /api/v1/status
        self.job = None                  # {"id", "name", "short", "progress", "time_printing", "time_remaining"}
        self.files = {}                  # real name -> {"short", "data", "m_timestamp"}
        self.next_id = 17
        self.start_delay = 0.0           # seconds the printer stays IDLE after a start call (the firmware scans the file)
        self.start_result = "PRINTING"   # or ATTENTION (the tools-mapping screen waits), ERROR
        self.can_print = True            # False: POST answers 409 "Can't print right now"
        self.digest = False              # True: the key header is refused, a Digest challenge is sent
        self.uploads, self.started, self.deleted, self.commands, self.auth_headers = [], [], [], [], []
        self.runner = None
        self.url = ""

    async def __aenter__(self):
        app = web.Application(middlewares=[self.auth])
        app.router.add_get("/api/version", self.version)
        app.router.add_get("/api/v1/info", self.info)
        app.router.add_get("/api/v1/status", self.status)
        app.router.add_get("/api/v1/job", self.job_get)
        app.router.add_put("/api/v1/job/{id}/{command}", self.job_command)
        app.router.add_delete("/api/v1/job/{id}", self.job_stop)
        app.router.add_get("/api/v1/files/usb/", self.listing)
        app.router.add_put("/api/v1/files/usb/{name}", self.upload)
        app.router.add_post("/api/v1/files/usb/{name}", self.start)
        app.router.add_delete("/api/v1/files/usb/{name}", self.delete)
        app.router.add_get("/usb/{short}", self.download)
        self.runner = web.AppRunner(app, shutdown_timeout=0.1)
        await self.runner.setup()
        site = web.TCPSite(self.runner, "127.0.0.1", 0)
        await site.start()
        self.url = f"http://127.0.0.1:{site._server.sockets[0].getsockname()[1]}"
        return self

    async def __aexit__(self, *exc):
        await self.runner.cleanup()

    # -- the printer's life, as the tests move it ---------------------------------------------------------------
    def begin(self, name: str):
        self.job = {"id": self.next_id, "name": name, "short": self.files[name]["short"], "progress": 0.0, "time_printing": 0, "time_remaining": 600}
        self.next_id += 1

    def finish(self):
        """The print is over: the finished screen shows, the API names no job any more."""
        self.state, self.job = "FINISHED", None

    def stop(self):
        self.state, self.job = "STOPPED", None

    def dismiss(self):
        """The operator left the finished screen."""
        self.state = "IDLE"

    # -- handlers ---------------------------------------------------------------------------------------------
    @web.middleware
    async def auth(self, request, handler):
        if request.path.startswith("/usb/") or request.path.startswith("/api/"):
            if self.digest:
                header = request.headers.get("Authorization", "")
                self.auth_headers.append(header)
                if not self._digest_ok(request.method, header):
                    return web.Response(status=401, headers={"WWW-Authenticate": f'Digest realm="{REALM}", nonce="00000000deadbeef", stale=false'}, text="")
            elif request.headers.get("X-Api-Key") != KEY:
                return web.Response(status=401, headers={"WWW-Authenticate": f'ApiKey realm="{REALM}"'}, text="")
        return await handler(request)

    def _digest_ok(self, method: str, header: str) -> bool:
        if not header.startswith("Digest "):
            return False
        fields = {k: v for k, v in re.findall(r'(\w+)="?([^",]*)"?', header[7:])}
        ha1 = hashlib.md5(f"maker:{REALM}:{KEY}".encode()).hexdigest()
        ha2 = hashlib.md5(f"{method}:{fields.get('uri', '')}".encode()).hexdigest()
        expected = hashlib.md5(f"{ha1}:{fields.get('nonce', '')}:{ha2}".encode()).hexdigest()
        return fields.get("username") == "maker" and fields.get("response") == expected

    async def version(self, request):
        return web.json_response({"api": "2.0.0", "server": "2.1.2", "nozzle_diameter": 0.4, "text": "PrusaLink", "hostname": "prusa-core-one",
                                  "firmware": "8.0.0+17068", "printer": "7.10.0", "capabilities": {"upload-by-put": True}})

    async def info(self, request):
        return web.json_response({"nozzle_diameter": 0.4, "mmu": False, "serial": "x", "hostname": "prusa-core-one", "min_extrusion_temp": 170})

    def _has_job(self) -> bool:
        return self.job is not None and self.state in ("PRINTING", "PAUSED", "ATTENTION")

    async def status(self, request):
        body = {"storage": {"path": "/usb/", "name": "usb", "read_only": False},
                "printer": {"state": self.state, "temp_bed": 59.9, "target_bed": 60.0, "temp_nozzle": 214.9, "target_nozzle": 215.0,
                            "axis_z": 1.2, "flow": 100, "speed": 100, "fan_hotend": 5000, "fan_print": 0}}
        if self._has_job():
            body["job"] = {"id": self.job["id"], "progress": self.job["progress"], "time_remaining": self.job["time_remaining"], "time_printing": self.job["time_printing"]}
        return web.json_response(body)

    async def job_get(self, request):
        if not self._has_job():
            return web.Response(status=204)
        j = self.job
        return web.json_response({"id": j["id"], "state": "PAUSED" if self.state in ("PAUSED", "ATTENTION") else "PRINTING", "progress": j["progress"],
                                  "time_remaining": j["time_remaining"], "time_printing": j["time_printing"],
                                  "file": {"refs": {"icon": f"/thumb/s/usb/{j['short']}", "thumbnail": f"/thumb/l/usb/{j['short']}", "download": f"/usb/{j['short']}"},
                                           "name": j["short"], "display_name": j["name"], "path": "/usb", "size": 0, "m_timestamp": 1793824818}})

    async def job_command(self, request):
        if not self.job or int(request.match_info["id"]) != self.job["id"]:
            return web.Response(status=404)
        command = request.match_info["command"]
        self.commands.append(command)
        if command == "pause" and self.state == "PRINTING":
            self.state = "PAUSED"
            return web.Response(status=204)
        if command == "resume" and self.state == "PAUSED":
            self.state = "PRINTING"
            return web.Response(status=204)
        return web.Response(status=409, text="")

    async def job_stop(self, request):
        if not self.job or int(request.match_info["id"]) != self.job["id"]:
            return web.Response(status=404)
        if self.state not in ("PRINTING", "PAUSED", "ATTENTION"):
            return web.Response(status=409, text="")
        self.commands.append("stop")
        self.stop()
        return web.Response(status=204)

    async def listing(self, request):
        children = [{"name": "FILES_~1", "ro": False, "type": "FOLDER", "m_timestamp": 1738856058, "display_name": "Files_English"}]
        for name, f in self.files.items():
            printing = self._has_job() and self.job["name"] == name
            refs = {"icon": f"/thumb/s/usb/{f['short']}", "thumbnail": f"/thumb/l/usb/{f['short']}"}
            if not printing:
                refs["download"] = f"/usb/{f['short']}"
            children.append({"name": f["short"], "ro": printing, "type": "PRINT_FILE", "m_timestamp": f["m_timestamp"], "refs": refs, "display_name": name})
        return web.json_response({"type": "FOLDER", "ro": False, "name": "usb", "children": children})

    async def upload(self, request):
        name = request.match_info["name"]
        if len(name) > 167:
            return web.json_response({"title": "431: Request Header Fields Too Large", "message": "File name too long"}, status=431)
        if not name.lower().endswith((".gcode", ".gco", ".g", ".gc", ".bgcode", ".bgc")):
            return web.json_response({"title": "415: Unsupported Media Type", "message": "Not a GCODE"}, status=415)
        if name in self.files and request.headers.get("Overwrite") != "?1":
            return web.json_response({"title": "409: Conflict", "message": "File already exists"}, status=409)
        data = await request.read()
        self.uploads.append({"name": name, "size": len(data), "content_length": request.headers.get("Content-Length"),
                             "overwrite": request.headers.get("Overwrite"), "print_after_upload": request.headers.get("Print-After-Upload")})
        self.files[name] = {"short": short_name(name), "data": data, "m_timestamp": 1793824818 + len(self.files)}
        if request.headers.get("Print-After-Upload") == "?1":
            return await self.start(request)
        return web.Response(status=201)

    async def start(self, request):
        name = request.match_info["name"]
        if name not in self.files:
            return web.Response(status=404)
        if not self.can_print or self.state in ("PRINTING", "PAUSED", "ATTENTION", "BUSY"):
            return web.json_response({"title": "409: Conflict", "message": "Can't print right now"}, status=409)
        self.started.append(name)
        self.begin(name)
        self.state = "IDLE"              # the firmware scans the file first

        async def go():
            await asyncio.sleep(self.start_delay)
            self.state = self.start_result
            if self.start_result == "ERROR":
                self.job = None
        asyncio.get_event_loop().create_task(go())
        return web.Response(status=204)

    async def delete(self, request):
        short = request.match_info["name"]
        name = next((n for n, f in self.files.items() if f["short"] == short), None)
        if name is None:
            return web.Response(status=404)
        if self._has_job() and self.job["name"] == name:
            return web.json_response({"title": "409: Conflict", "message": "File is busy"}, status=409)
        del self.files[name]
        self.deleted.append(short)
        return web.Response(status=204)

    async def download(self, request):
        short = request.match_info["short"]
        f = next((f for f in self.files.values() if f["short"] == short), None)
        if f is None:
            return web.Response(status=404)
        return web.Response(body=f["data"], content_type="application/octet-stream")


class PrusaLinkDriverTest(unittest.IsolatedAsyncioTestCase):
    async def asyncSetUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.gcode = os.path.join(self.tmp.name, "print.gcode")
        with open(self.gcode, "wb") as fh:
            fh.write(b"M862.3 P \"COREONEINDX\"\nT0 S1 L0 D0\nG1 X10 Y10\nT1 S1 L0 D0\nG1 X20\n" * 40)

    def driver(self, fake, **options):
        return PrusaLinkDriver("c1", {"url": fake.url, "api_key": KEY, "timeout": 2, "start_wait": 3, "start_poll": 0.1,
                                      "_work_dir": self.tmp.name} | options)

    async def test_status_idle_and_printing_with_the_real_file_name(self):
        async with FakePrusaLink() as fake:
            d = self.driver(fake)
            await d.connect()
            try:
                s = await d.status()
                self.assertEqual(s.state, IDLE)
                self.assertIsNone(s.job)
                self.assertEqual((s.telemetry["extruder"], s.telemetry["bed_target"], s.telemetry["printer_state"]), (214.9, 60.0, "IDLE"))
                # the fans in rpm, named so (agent 0.2.12): the dashboard prints the key with the figure
                self.assertEqual((s.telemetry["fan_hotend_rpm"], s.telemetry["fan_print_rpm"]), (5000, 0))
                self.assertNotIn("fan_hotend", s.telemetry)
                fake.files["greek-godess_0.4n_0.2mm_PET_COREONEINDX_1h28m.bgcode"] = {"short": "GREEK-~3.BGC", "data": b"x", "m_timestamp": 1}
                fake.begin("greek-godess_0.4n_0.2mm_PET_COREONEINDX_1h28m.bgcode")
                fake.job.update(progress=72.0, time_printing=4282, time_remaining=1380)
                fake.state = "PRINTING"
                s = await d.status()
                self.assertEqual(s.state, PRINTING)
                self.assertEqual(s.job.filename, "greek-godess_0.4n_0.2mm_PET_COREONEINDX_1h28m.bgcode", "the real name, not the 8.3 one")
                self.assertEqual((s.job.status, s.job.progress, s.job.print_duration), (JOB_PRINTING, 72.0, 4282))
                self.assertEqual(s.telemetry["remain_min"], 23)
                self.assertIsNone(await d.slot_status(3), "PrusaLink tells nothing about the tools")
            finally:
                await d.close()

    async def test_a_wrong_key_is_offline_with_the_reason(self):
        async with FakePrusaLink() as fake:
            d = self.driver(fake, api_key="wrong")
            await d.connect()
            try:
                s = await d.status()
                self.assertEqual((s.state, s.telemetry.get("link")), (OFFLINE, "unauthorized"))
                with self.assertRaises(DriverError) as caught:
                    await d.pause()
                self.assertIn("api_key", str(caught.exception))
            finally:
                await d.close()

    async def test_an_unreachable_printer_is_offline(self):
        d = PrusaLinkDriver("c1", {"url": "http://127.0.0.1:9", "api_key": KEY, "timeout": 1, "_work_dir": self.tmp.name})
        await d.connect()
        try:
            self.assertEqual((await d.status()).state, OFFLINE)
        finally:
            await d.close()

    async def test_start_uploads_with_overwrite_then_posts_and_waits_for_printing(self):
        async with FakePrusaLink() as fake:
            d = self.driver(fake)
            await d.connect()
            try:
                await d.start(self.gcode, "mp7-matplace-F26-000120.gcode", 3)
                s = await d.status()
                self.assertEqual(s.state, PRINTING)
                self.assertEqual(s.job.filename, "mp7-matplace-F26-000120.gcode")
            finally:
                await d.close()
            up = fake.uploads[0]
            self.assertEqual(up["name"], "mp7-matplace-F26-000120.gcode")
            self.assertEqual((up["overwrite"], up["print_after_upload"]), ("?1", "?0"))
            self.assertEqual((up["size"], int(up["content_length"])), (os.path.getsize(self.gcode), os.path.getsize(self.gcode)))
            self.assertEqual(fake.started, ["mp7-matplace-F26-000120.gcode"])

    async def test_the_scan_before_the_print_is_waited_for(self):
        async with FakePrusaLink() as fake:
            fake.start_delay = 0.6          # IDLE for a while after the start call, as the firmware reads the file
            d = self.driver(fake)
            await d.connect()
            try:
                await d.start(self.gcode, "mp8-matplace-slow.gcode", 0)
                self.assertEqual((await d.status()).state, PRINTING)
            finally:
                await d.close()

    async def test_a_printer_that_never_begins_is_a_start_error_with_its_word(self):
        async with FakePrusaLink() as fake:
            fake.start_delay = 3600
            d = self.driver(fake, start_wait=0.5)
            await d.connect()
            try:
                with self.assertRaises(DriverError) as caught:
                    await d.start(self.gcode, "mp9-matplace-never.gcode", 0)
            finally:
                await d.close()
            self.assertIn("did not begin", str(caught.exception))
            self.assertIn("IDLE", str(caught.exception))

    async def test_a_start_that_waits_on_the_screen_is_paused_with_the_reason(self):
        async with FakePrusaLink() as fake:
            fake.start_result = "ATTENTION"      # the tools-mapping screen: a filament type did not match
            d = self.driver(fake)
            await d.connect()
            try:
                await d.start(self.gcode, "mp10-matplace-map.gcode", 2)      # no exception: the file is on the printer
                s = await d.status()
                self.assertEqual(s.state, PAUSED)
                self.assertEqual(s.job.status, JOB_PAUSED)
                self.assertIn("screen", s.telemetry["message"])
                self.assertIn("screen", s.job.message)
            finally:
                await d.close()

    async def test_a_start_the_printer_refuses_says_why_and_a_busy_printer_is_refused_first(self):
        async with FakePrusaLink() as fake:
            fake.can_print = False
            d = self.driver(fake)
            await d.connect()
            try:
                with self.assertRaises(DriverError) as caught:
                    await d.start(self.gcode, "mp11-matplace-no.gcode", 0)
                self.assertIn("Can't print right now", str(caught.exception))
                self.assertEqual(len(fake.uploads), 1, "the file went up, the start was refused")
                fake.state = "PRINTING"
                with self.assertRaises(DriverError) as caught:
                    await d.start(self.gcode, "mp12-matplace-busy.gcode", 0)
                self.assertIn("not idle", str(caught.exception))
            finally:
                await d.close()
            self.assertEqual(len(fake.uploads), 1)

    async def test_bad_names_are_refused_before_anything_is_sent(self):
        async with FakePrusaLink() as fake:
            d = self.driver(fake)
            await d.connect()
            try:
                with self.assertRaises(DriverError):
                    await d.start(self.gcode, "mp13-matplace.txt", 0)
                with self.assertRaises(DriverError) as caught:
                    await d.start(self.gcode, "mp14-" + "x" * 170 + ".gcode", 0)
                self.assertIn("167", str(caught.exception))
            finally:
                await d.close()
            self.assertEqual(fake.uploads, [])

    async def test_pause_resume_and_cancel_go_by_the_job_id(self):
        async with FakePrusaLink() as fake:
            d = self.driver(fake)
            await d.connect()
            try:
                with self.assertRaises(DriverError) as caught:
                    await d.pause()
                self.assertIn("no job", str(caught.exception))
                await d.start(self.gcode, "mp15-matplace-ctl.gcode", 0)
                await d.pause()
                self.assertEqual((await d.status()).state, PAUSED)
                with self.assertRaises(DriverError) as caught:
                    await d.pause()                 # already paused: 409
                self.assertIn("409", str(caught.exception))
                await d.resume()
                self.assertEqual((await d.status()).state, PRINTING)
                await d.cancel()
                s = await d.status()
                self.assertEqual(s.state, IDLE)
                self.assertEqual((s.job.filename, s.job.status), ("mp15-matplace-ctl.gcode", JOB_CANCELLED))
                self.assertEqual(s.job.message, "")
                fake.dismiss()
                s = await d.status()
                self.assertEqual((s.job.status, s.job.message), (JOB_CANCELLED, ""), "a stop the printer reported stays a plain stop")
            finally:
                await d.close()
            self.assertEqual(fake.commands, ["pause", "pause", "resume", "stop"])

    async def test_a_finished_print_is_done_and_leaving_the_screen_clears_the_plate(self):
        async with FakePrusaLink() as fake:
            d = self.driver(fake)
            await d.connect()
            try:
                await d.start(self.gcode, "mp16-matplace-done.gcode", 1)
                fake.job.update(progress=57.5, time_printing=1200)
                self.assertEqual((await d.status()).job.progress, 57.5)
                fake.finish()
                s = await d.status()
                self.assertEqual(s.state, IDLE)
                self.assertEqual((s.job.filename, s.job.status, s.job.progress, s.job.print_duration), ("mp16-matplace-done.gcode", JOB_DONE, 100.0, 1200))
                self.assertNotIn("bed_cleared_at", s.telemetry)
                fake.dismiss()
                s = await d.status()
                self.assertEqual(s.job.status, JOB_DONE, "still reported until a new job comes")
                self.assertIn("bed_cleared_at", s.telemetry)
                # a restarted agent remembers the ended job (the firmware names no file any more)
                d2 = self.driver(fake)
                await d2.connect()
                try:
                    s = await d2.status()
                    self.assertEqual((s.job.filename, s.job.status), ("mp16-matplace-done.gcode", JOB_DONE))
                finally:
                    await d2.close()
            finally:
                await d.close()

    async def test_a_print_that_went_straight_to_idle_is_judged_by_its_progress(self):
        async with FakePrusaLink() as fake:
            d = self.driver(fake)
            await d.connect()
            try:
                await d.start(self.gcode, "mp17-matplace-a.gcode", 0)
                fake.job.update(progress=99.6)
                await d.status()
                fake.state, fake.job = "IDLE", None          # the screen was dismissed between two polls
                s = await d.status()
                self.assertEqual((s.job.status, s.job.progress), (JOB_DONE, 100.0))
                await d.start(self.gcode, "mp18-matplace-b.gcode", 0)
                fake.job.update(progress=40.0)
                await d.status()
                fake.state, fake.job = "IDLE", None
                s = await d.status()
                self.assertEqual(s.job.status, JOB_CANCELLED)
                self.assertIn("40 %", s.job.message)
            finally:
                await d.close()

    async def test_an_error_names_the_last_job_as_failed(self):
        async with FakePrusaLink() as fake:
            d = self.driver(fake)
            await d.connect()
            try:
                await d.start(self.gcode, "mp19-matplace-err.gcode", 0)
                fake.state, fake.job = "ERROR", None
                s = await d.status()
                self.assertEqual(s.state, ERROR)
                self.assertEqual((s.job.filename, s.job.status), ("mp19-matplace-err.gcode", JOB_FAILED))
                self.assertIn("screen", s.telemetry["error"])
                with self.assertRaises(DriverError):
                    await d.reset()
            finally:
                await d.close()

    async def test_files_fetch_and_delete_go_by_the_short_names(self):
        async with FakePrusaLink() as fake:
            fake.files["Benchy_0.4n_0.2mm_PLA_COREONEINDX_32m.bgcode"] = {"short": "BENCHY~1.BGC", "data": b"binary" * 100, "m_timestamp": 1793227746}
            d = self.driver(fake)
            await d.connect()
            try:
                await d.start(self.gcode, "mp20-matplace-files.gcode", 0)
                listing = await d.files()
                names = [f["name"] for f in listing["files"]]
                self.assertEqual(names, ["mp20-matplace-files.gcode", "Benchy_0.4n_0.2mm_PLA_COREONEINDX_32m.bgcode"], "newest first, real names")
                self.assertEqual(listing["files"][1]["modified"], 1793227746)
                self.assertIsNone(listing["free"], "the firmware tells no free space")
                target = os.path.join(self.tmp.name, "fetched", "benchy.bgcode")
                size = await d.fetch("Benchy_0.4n_0.2mm_PLA_COREONEINDX_32m.bgcode", target)
                self.assertEqual((size, os.path.getsize(target)), (600, 600))
                with self.assertRaises(DriverError) as caught:
                    await d.fetch("mp20-matplace-files.gcode", target)         # being printed: no download ref
                self.assertIn("printed", str(caught.exception))
                with self.assertRaises(DriverError) as caught:
                    await d.delete("mp20-matplace-files.gcode")
                self.assertIn("File is busy", str(caught.exception))
                await d.delete("Benchy_0.4n_0.2mm_PLA_COREONEINDX_32m.bgcode")
                with self.assertRaises(DriverError):
                    await d.delete("nothing-like-it.gcode")
            finally:
                await d.close()
            self.assertEqual(fake.deleted, ["BENCHY~1.BGC"])

    async def test_digest_auth_answers_the_printers_challenge(self):
        async with FakePrusaLink() as fake:
            fake.digest = True
            d = self.driver(fake, auth="digest")
            await d.connect()
            try:
                s = await d.status()
                self.assertEqual(s.state, IDLE)
                self.assertTrue(any(h.startswith("Digest ") for h in fake.auth_headers))
                await d.start(self.gcode, "mp21-matplace-digest.gcode", 0)
                self.assertEqual((await d.status()).state, PRINTING)
            finally:
                await d.close()


if __name__ == "__main__":
    unittest.main()
