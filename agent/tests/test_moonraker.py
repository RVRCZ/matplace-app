"""
The Moonraker driver against a fake Moonraker (aiohttp).   Run:  python -m unittest discover -s tests   (from agent/)

What the fake has to be able to do: answer the objects query from a dict the test edits, accept an upload, and make the
print/start call slow or silent - the Kobra S1 did exactly that (U #2, 11-12 Oct 2026).
"""
import asyncio
import os
import sys
import tempfile
import unittest

from aiohttp import web

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from farm_agent.drivers.base import ERROR, IDLE, PRINTING, DriverError  # noqa: E402
from farm_agent.drivers.moonraker import MoonrakerDriver  # noqa: E402


class FakeMoonraker:
    def __init__(self):
        self.print_stats = {"state": "standby", "filename": "", "message": ""}
        self.webhooks = {"state": "ready", "state_message": "Printer is ready"}
        self.start_delay = 0.0          # seconds print/start sleeps before it answers
        self.start_answers = True       # False: print/start never answers (the test moves the state itself)
        self.knows_reset = True         # False: GoKlipper, SDCARD_RESET_FILE is an unknown command
        self.cancel_clears = True       # a cancel after a failed print goes to "cancelled" (False: stays in error)
        self.uploaded, self.started, self.scripts, self.restarts, self.cancels = [], [], [], [], 0
        self.runner = None
        self.url = ""

    async def __aenter__(self):
        app = web.Application()
        app.router.add_get("/printer/objects/query", self.query)
        app.router.add_post("/server/files/upload", self.upload)
        app.router.add_post("/printer/print/start", self.start)
        app.router.add_post("/machine/device_power/device", self.ok)
        app.router.add_post("/printer/gcode/script", self.script)
        app.router.add_post("/printer/firmware_restart", self.firmware_restart)
        app.router.add_post("/printer/print/cancel", self.cancel)
        self.runner = web.AppRunner(app, shutdown_timeout=0.1)   # a start handler asleep "for ever" must not hold the test
        await self.runner.setup()
        site = web.TCPSite(self.runner, "127.0.0.1", 0)
        await site.start()
        port = site._server.sockets[0].getsockname()[1]
        self.url = f"http://127.0.0.1:{port}"
        return self

    async def __aexit__(self, *exc):
        await self.runner.cleanup()

    async def query(self, request):
        return web.json_response({"result": {"status": {
            "print_stats": dict(self.print_stats), "virtual_sdcard": {"progress": 0.0},
            "extruder": {"temperature": 25.0, "target": 0.0}, "heater_bed": {"temperature": 24.0, "target": 0.0},
            "webhooks": dict(self.webhooks),
        }}})

    async def upload(self, request):
        reader = await request.multipart()
        async for part in reader:
            if part.name == "file":
                self.uploaded.append(part.filename)
                await part.read()
        return web.json_response({"result": "ok"})

    async def start(self, request):
        self.started.append(request.query.get("filename"))
        if not self.start_answers:
            await asyncio.sleep(3600)
        await asyncio.sleep(self.start_delay)
        self.print_stats.update(state="printing", filename=request.query.get("filename"))
        return web.json_response({"result": "ok"})

    async def ok(self, request):
        return web.json_response({"result": "ok"})

    async def script(self, request):
        script = request.query.get("script", "")
        self.scripts.append(script)
        if script == "SDCARD_RESET_FILE":
            if not self.knows_reset:
                return web.json_response({"error": {"code": 400, "message": "Unknown command:\"SDCARD_RESET_FILE\""}}, status=400)
            self.print_stats.update(state="standby", filename="", message="")
        return web.json_response({"result": "ok"})

    async def firmware_restart(self, request):
        self.restarts.append(1)
        self.webhooks.update(state="ready", state_message="Printer is ready")
        self.print_stats.update(state="standby", filename="", message="")
        return web.json_response({"result": "ok"})

    async def cancel(self, request):
        self.cancels += 1
        if self.print_stats["state"] == "error" and self.cancel_clears:
            self.print_stats.update(state="cancelled")
        return web.json_response({"result": "ok"})


class MoonrakerDriverTest(unittest.IsolatedAsyncioTestCase):
    async def asyncSetUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.gcode = os.path.join(self.tmp.name, "print.gcode")
        with open(self.gcode, "wb") as fh:
            fh.write(b"G9111 bedTemp=100 extruderTemp=255\nM109 S255\nT2\nG1 X10 Y10\n")

    def driver(self, fake, **options):
        d = MoonrakerDriver("p1", {"url": fake.url, "light_device": "", "timeout": 2,
                                   "start_timeout": 0.3, "start_wait": 3, "start_poll": 0.1, "reset_settle": 0.05} | options)
        return d

    async def test_reset_clears_the_error_a_failed_print_left_behind(self):
        async with FakeMoonraker() as fake:
            fake.print_stats.update(state="error", filename="x.gcode", message="nozzle must be hot first")
            d = self.driver(fake)
            await d.connect()
            try:
                self.assertEqual((await d.status()).state, ERROR)
                await d.reset()
                self.assertEqual((await d.status()).state, IDLE)
            finally:
                await d.close()
            self.assertEqual(fake.scripts, ["SDCARD_RESET_FILE"])
            self.assertEqual((fake.restarts, fake.cancels), ([], 0), "no restart, no cancel when the reset alone does it")

    async def test_reset_falls_back_to_a_cancel_on_goklipper(self):
        async with FakeMoonraker() as fake:
            fake.print_stats.update(state="error", filename="x.gcode", message="filament runout")
            fake.knows_reset = False
            d = self.driver(fake)
            await d.connect()
            try:
                await d.reset()
                self.assertEqual((await d.status()).state, IDLE)
            finally:
                await d.close()
            self.assertEqual(fake.cancels, 1)

    async def test_reset_restarts_klipper_after_a_shutdown(self):
        async with FakeMoonraker() as fake:
            fake.print_stats.update(state="error", filename="x.gcode", message="Heater extruder not heating at expected rate")
            fake.webhooks.update(state="shutdown", state_message="Heater extruder not heating at expected rate")
            d = self.driver(fake)
            await d.connect()
            try:
                await d.reset()
                s = await d.status()
                self.assertEqual(s.state, IDLE)
                self.assertNotIn("klippy", s.telemetry)
            finally:
                await d.close()
            self.assertEqual(fake.restarts, [1])

    async def test_reset_says_what_the_printer_says_when_the_error_stays(self):
        async with FakeMoonraker() as fake:
            fake.print_stats.update(state="error", filename="x.gcode", message="Move out of range")
            fake.knows_reset, fake.cancel_clears = False, False
            d = self.driver(fake)
            await d.connect()
            try:
                with self.assertRaises(DriverError) as caught:
                    await d.reset()
            finally:
                await d.close()
            self.assertIn("Move out of range", str(caught.exception))

    async def test_reset_refuses_while_a_print_runs(self):
        async with FakeMoonraker() as fake:
            fake.print_stats.update(state="printing", filename="x.gcode")
            d = self.driver(fake)
            await d.connect()
            try:
                with self.assertRaises(DriverError):
                    await d.reset()
            finally:
                await d.close()
            self.assertEqual(fake.scripts, [])

    async def test_a_quick_start_is_simply_started(self):
        async with FakeMoonraker() as fake:
            d = self.driver(fake)
            await d.connect()
            try:
                await d.start(self.gcode, "mp1-print.gcode", 2)
                self.assertEqual((await d.status()).state, PRINTING)
            finally:
                await d.close()
            self.assertEqual(fake.uploaded, ["mp1-print.gcode"])
            self.assertEqual(fake.started, ["mp1-print.gcode"])

    async def test_a_start_call_that_outlives_its_limit_is_fine_when_the_printer_prints(self):
        async with FakeMoonraker() as fake:
            fake.start_delay = 0.8          # past the 0.3 s limit, then the printer prints
            d = self.driver(fake)
            await d.connect()
            try:
                await d.start(self.gcode, "mp2-print.gcode", 2)      # no exception: the printer took the file
                self.assertEqual((await d.status()).state, PRINTING)
            finally:
                await d.close()
            self.assertEqual(fake.started, ["mp2-print.gcode"])

    async def test_a_silent_start_call_reports_the_printers_error_in_its_words(self):
        async with FakeMoonraker() as fake:
            fake.start_answers = False

            async def fail_soon():
                await asyncio.sleep(0.6)
                fake.print_stats.update(state="error", filename="mp3-print.gcode", message="nozzle must be hot first")

            d = self.driver(fake)
            await d.connect()
            try:
                asyncio.create_task(fail_soon())
                with self.assertRaises(DriverError) as caught:
                    await d.start(self.gcode, "mp3-print.gcode", 2)
            finally:
                await d.close()
            self.assertIn("nozzle must be hot first", str(caught.exception))

    async def test_a_silent_start_call_with_a_silent_printer_is_still_a_timeout(self):
        async with FakeMoonraker() as fake:
            fake.start_answers = False
            d = self.driver(fake, start_wait=0.5)
            await d.connect()
            try:
                with self.assertRaises(DriverError) as caught:
                    await d.start(self.gcode, "mp4-print.gcode", 2)
            finally:
                await d.close()
            self.assertIn("TimeoutError", str(caught.exception))
            self.assertEqual(fake.started, ["mp4-print.gcode"])

    async def test_status_carries_the_printers_error_text(self):
        async with FakeMoonraker() as fake:
            d = self.driver(fake)
            await d.connect()
            try:
                self.assertEqual((await d.status()).state, IDLE)
                fake.print_stats.update(state="error", filename="x.gcode", message="Move out of range: 300.0 0.0 0.5")
                s = await d.status()
                self.assertEqual(s.state, ERROR)
                self.assertEqual(s.telemetry["error"], "Move out of range: 300.0 0.0 0.5")
                self.assertEqual(s.job.message, "Move out of range: 300.0 0.0 0.5")
                # a Klipper shutdown: print_stats says nothing, webhooks does
                fake.print_stats.update(state="standby", filename="", message="")
                fake.webhooks.update(state="shutdown", state_message="Heater extruder not heating at expected rate\nOnce the underlying issue is corrected, use the FIRMWARE_RESTART command")
                s = await d.status()
                self.assertEqual(s.telemetry["klippy"], "shutdown")
                self.assertTrue(s.telemetry["error"].startswith("Heater extruder not heating at expected rate Once"))
                self.assertLessEqual(len(s.telemetry["error"]), 160)
                fake.webhooks.update(state="ready", state_message="Printer is ready")
                self.assertNotIn("error", (await d.status()).telemetry)
            finally:
                await d.close()


if __name__ == "__main__":
    unittest.main()
