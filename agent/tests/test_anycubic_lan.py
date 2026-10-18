"""
The Anycubic LAN driver (Kobra X in LAN mode) against a fake link and a fake upload address - no printer, no network
beyond 127.0.0.1.   Run:  python -m pytest tests   (from agent/)

The fake link answers the `info` query from a dict the test edits (shaped like the Kobra X 2.0.2.2 reports, docs/AM.md)
and acts on the commands the driver publishes: start begins a project, pause and stop change it.
"""
import asyncio
import os
import sys
import tempfile
import unittest

from aiohttp import web

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from farm_agent.drivers import create  # noqa: E402
from farm_agent.drivers.anycubic_lan import CLOUD_MODE, LinkError  # noqa: E402
from farm_agent.drivers.base import (ERROR, IDLE, JOB_CANCELLED, JOB_DONE, JOB_PRINTING, OFFLINE, PAUSED, PRINTING,  # noqa: E402
                                     DriverError)

FREE = {
    "state": "free", "version": "2.0.2.2", "model": "Anycubic Kobra X", "project": None,
    "temp": {"curr_hotbed_temp": 24, "curr_nozzle_temp": 29, "target_hotbed_temp": 0, "target_nozzle_temp": 0},
    "last_project": {"curr_layer": 375, "filename": "old part_PLA_0.2_5h51m15s.gcode", "print_time": 364, "progress": 1,
                     "remain_time": 0, "state": "finished", "supplies_usage": 39240.36, "task_id": 131875271, "total_layers": 375},
    "urls": {},
}


class FakeLink:
    """Stands for KobraLink. `begins`: a start command turns into a running project (False: the printer ignores it)."""

    def __init__(self, printer: "FakePrinter", on_report):
        self.p = printer
        self.on_report = on_report
        self.open = False

    def connect(self):
        self.p.opened += 1
        if self.p.refuse:
            raise LinkError(self.p.refuse)
        self.open = True

    def close(self):
        self.open = False

    @property
    def connected(self):
        return self.open and not self.p.dropped

    def ask(self, kind, action="query", data=None, *, web=True, timeout=8.0):
        if not self.connected:
            raise LinkError("not connected")
        return {"type": kind, "state": "done", "code": 200, "data": dict(self.p.info)}

    def publish(self, kind, action, data, *, web=False):
        if not self.connected:
            raise LinkError("not connected")
        self.p.sent.append((kind, action, data))
        info = self.p.info
        if (kind, action) == ("print", "start") and self.p.begins:
            info.update(state="busy", project={"state": "printing", "filename": data["filename"], "progress": 0, "pause": 0,
                                               "curr_layer": 0, "total_layers": 50, "remain_time": 6, "print_time": 0})
        elif (kind, action) == ("print", "pause") and info.get("project"):
            info["project"] = dict(info["project"], pause=1, state="paused")
        elif (kind, action) == ("print", "resume") and info.get("project"):
            info["project"] = dict(info["project"], pause=0, state="printing")
        elif (kind, action) == ("print", "stop") and info.get("project"):
            info.update(state="free", last_project=dict(info["project"], state="stoped"), project=None)
        return "msg"


class FakePrinter:
    def __init__(self):
        self.info = {k: (dict(v) if isinstance(v, dict) else v) for k, v in FREE.items()}
        self.refuse = None        # LinkError text connect() raises (CLOUD_MODE, "timed out"...)
        self.dropped = False      # the link went down after connecting
        self.begins = True
        self.opened = 0
        self.sent = []
        self.uploads = []
        self.runner = None

    def link(self, ip, on_report):
        return FakeLink(self, on_report)

    async def __aenter__(self):
        app = web.Application()
        app.router.add_post("/gcode_upload", self.upload)
        self.runner = web.AppRunner(app, shutdown_timeout=0.1)
        await self.runner.setup()
        site = web.TCPSite(self.runner, "127.0.0.1", 0)
        await site.start()
        port = site._server.sockets[0].getsockname()[1]
        self.info["urls"] = {"fileUploadurl": f"http://127.0.0.1:{port}/gcode_upload?s=tok", "rtspUrl": ""}
        return self

    async def __aexit__(self, *exc):
        await self.runner.cleanup()

    async def upload(self, request):
        form = await request.post()
        f = form["file"]
        self.uploads.append((request.query.get("s"), f.filename, f.file.read()))
        return web.json_response({"code": 200, "message": "success"})


class AnycubicLanTest(unittest.IsolatedAsyncioTestCase):
    async def driver(self, printer: FakePrinter, **options):
        d = create("kobra-x-01", "anycubic_lan", {"ip": "192.168.8.44", "_link": printer.link, "start_poll": 0.01,
                                                  "start_wait": 0.5, "retry_seconds": 60, **options})
        await d.connect()
        self.addAsyncCleanup(d.close)
        return d

    def gcode(self) -> str:
        fd, path = tempfile.mkstemp(suffix=".gcode")
        with os.fdopen(fd, "wb") as fh:
            fh.write(b";FLAVOR:Klipper\nG28\nT0\nG1 X10 Y10\n")
        self.addCleanup(os.remove, path)
        return path

    async def test_a_free_printer_is_idle_and_tells_the_last_print_as_done(self):
        async with FakePrinter() as p:
            d = await self.driver(p)
            s = await d.status()
        self.assertEqual(IDLE, s.state)
        self.assertEqual(24.0, s.telemetry["bed"])
        self.assertEqual(29.0, s.telemetry["extruder"])
        self.assertEqual("375/375", s.telemetry["layer"])
        self.assertEqual(JOB_DONE, s.job.status)
        self.assertEqual(100.0, s.job.progress)
        self.assertEqual(364 * 60, s.job.print_duration)
        self.assertAlmostEqual(39240.36, s.job.filament_used)

    async def test_a_running_print_reports_progress_layers_and_minutes_left_and_a_pause(self):
        async with FakePrinter() as p:
            p.info.update(state="busy", project={"state": "printing", "filename": "mp7-cube.gcode", "progress": 42, "pause": 0,
                                                 "curr_layer": 21, "total_layers": 50, "remain_time": 4, "print_time": 3})
            d = await self.driver(p)
            s = await d.status()
            self.assertEqual(PRINTING, s.state)
            self.assertEqual((JOB_PRINTING, 42.0, "mp7-cube.gcode"), (s.job.status, s.job.progress, s.job.filename))
            self.assertEqual(("21/50", 4), (s.telemetry["layer"], s.telemetry["remain_min"]))
            p.info["project"]["pause"] = 1
            self.assertEqual(PAUSED, (await d.status()).state)

    async def test_a_cancelled_print_is_idle_with_the_job_cancelled(self):
        async with FakePrinter() as p:
            p.info.update(state="free", project=None, last_project={"state": "stoped", "filename": "mp7-cube.gcode", "progress": 30})
            s = await (await self.driver(p)).status()
        self.assertEqual((IDLE, JOB_CANCELLED, 30.0), (s.state, s.job.status, s.job.progress))

    async def test_start_uploads_the_file_sends_the_start_and_waits_until_it_prints(self):
        async with FakePrinter() as p:
            d = await self.driver(p)
            await d.status()
            await d.start(self.gcode(), "mp7-cube.gcode", 0)
            self.assertEqual("tok", p.uploads[0][0])
            self.assertEqual("mp7-cube.gcode", p.uploads[0][1])
            self.assertIn(b"T0", p.uploads[0][2])
            kind, action, data = p.sent[-1]
            self.assertEqual(("print", "start", "mp7-cube.gcode", "-1"), (kind, action, data["filename"], data["taskid"]))
            await d.cancel()
            self.assertEqual(("print", "stop"), p.sent[-1][:2])
            s = await d.status()
            self.assertEqual((IDLE, JOB_CANCELLED), (s.state, s.job.status))

    async def test_start_refuses_a_busy_printer_and_says_when_the_print_never_began(self):
        async with FakePrinter() as p:
            p.info.update(state="busy", project={"state": "printing", "filename": "someone else.gcode", "progress": 5, "pause": 0})
            d = await self.driver(p)
            with self.assertRaisesRegex(DriverError, "not idle"):
                await d.start(self.gcode(), "mp8-cube.gcode", 0)
            self.assertEqual([], p.uploads)

            p.info.update(state="free", project=None)
            p.begins = False
            with self.assertRaisesRegex(DriverError, "did not begin"):
                await d.start(self.gcode(), "mp8-cube.gcode", 0)

    async def test_pause_and_resume_go_to_the_printer(self):
        async with FakePrinter() as p:
            d = await self.driver(p)
            await d.status()
            await d.start(self.gcode(), "mp9-cube.gcode", 0)
            await d.pause()
            self.assertEqual(PAUSED, (await d.status()).state)
            await d.resume()
            self.assertEqual(PRINTING, (await d.status()).state)

    async def test_a_printer_in_cloud_mode_is_offline_with_the_reason_and_cannot_start(self):
        async with FakePrinter() as p:
            p.refuse = CLOUD_MODE
            d = await self.driver(p)
            s = await d.status()
            self.assertEqual(OFFLINE, s.state)
            self.assertEqual(CLOUD_MODE, s.telemetry["link"])
            with self.assertRaisesRegex(DriverError, "LAN mode"):
                await d.pause()

    async def test_a_lost_link_is_opened_again_on_the_next_status(self):
        async with FakePrinter() as p:
            d = await self.driver(p)
            self.assertEqual(IDLE, (await d.status()).state)
            p.dropped = True
            p.refuse = "timed out"
            self.assertEqual(OFFLINE, (await d.status()).state)
            p.dropped, p.refuse = False, None
            self.assertEqual(IDLE, (await d.status()).state)
            self.assertGreaterEqual(p.opened, 3)

    async def test_a_change_of_state_heard_from_the_printer_wakes_the_agent(self):
        async with FakePrinter() as p:
            d = await self.driver(p)
            woken = asyncio.Event()

            async def wake():
                woken.set()
            d.on_change = wake
            await asyncio.to_thread(d._heard, "info", {"data": {"state": "busy"}})
            await asyncio.wait_for(woken.wait(), 2)

    async def test_no_camera_address_or_no_decoder_means_no_picture(self):
        async with FakePrinter() as p:
            d = await self.driver(p)
            await d.status()
            self.assertIsNone(await d.snapshot())
            d2 = await self.driver(p, camera=False)
            self.assertIsNone(await d2.snapshot())

    async def test_a_print_that_failed_is_an_error_until_cleared_on_the_screen(self):
        async with FakePrinter() as p:
            p.info.update(state="busy", project={"state": "failed", "filename": "mp7-cube.gcode", "progress": 12, "err_msg": "nozzle clog"})
            d = await self.driver(p)
            s = await d.status()
            self.assertEqual(ERROR, s.state)
            self.assertEqual("nozzle clog", s.job.message)
            with self.assertRaisesRegex(DriverError, "screen"):
                await d.reset()


if __name__ == "__main__":
    unittest.main()
