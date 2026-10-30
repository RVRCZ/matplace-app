"""
The Moonraker driver 0.2.9 against the fake Moonraker: the printer's words (M117, print_stats, the ACE's slots, the
console's error lines while paused), the spool position before a start, the files of the printer, and the reset of a
finished print as the plate confirmation.   Run:  python -m pytest tests   (from agent/)

The samples are what U1 and U2 (Kobra S1, Rinkhals 20260901_01) answered on 30 Oct 2026, docs/AM.md §10.
"""
import asyncio
import os
import sys
import tempfile
import time
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from farm_agent.drivers.base import ERROR, IDLE, PAUSED, PRINTING, DriverError  # noqa: E402
from farm_agent.drivers.moonraker import MoonrakerDriver  # noqa: E402
from test_moonraker import FakeMoonraker  # noqa: E402

# filament_hub of U1, 30 Oct 2026 (four spools in the ACE Pro, nothing fed)
HUB = {"auto_refill": 1, "current_filament": "", "cutter_state": 0, "ext_spool": 0, "ext_spool_status": "ready", "filament_present": 0,
       "filament_hubs": [{"id": 0, "status": "ready", "dryer_status": {"status": "stop", "target_temp": 0, "duration": 0, "remain_time": 0}, "temp": 37, "humidity": 0,
                          "slots": [{"index": 0, "status": "ready", "type": "PLA Silk", "color": [137, 168, 79]}, {"index": 1, "status": "ready", "type": "PLA", "color": [33, 39, 33]},
                                    {"index": 2, "status": "ready", "type": "PLA Silk", "color": [0, 71, 187]}, {"index": 3, "status": "ready", "type": "PLA", "color": [0, 156, 189]}]}]}

# the console of U2 after a feed that failed (gcode_store, 30 Oct 2026): the command, GoKlipper's own error lines
STORE = [
    {"message": "leviQ3 auto_zoffset_on_off: 1\n // The SAVE_CONFIG command will update the printer config file", "time": 0, "type": "response"},
    {"message": "FEED_FILAMENT ID=0 INDEX=3 LENGTH=80 SPEED=25", "time": 0, "type": "command"},
    {"message": "!! error: typ = WebRequestError, code = 10011715, message = Extruder must heat up first", "time": 0, "type": "response"},
    {"message": "!! error: typ = WebRequestError, code = 10011710, message = filament hub feed filament timeout", "time": 0, "type": "response"},
]


def hub_with(slot: int, status: str) -> dict:
    import copy
    hub = copy.deepcopy(HUB)
    hub["filament_hubs"][0]["slots"][slot]["status"] = status
    return hub


class MoonrakerMessagesTest(unittest.IsolatedAsyncioTestCase):
    async def asyncSetUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.gcode = os.path.join(self.tmp.name, "print.gcode")
        with open(self.gcode, "wb") as fh:
            fh.write(b"G9111 bedTemp=55 extruderTemp=220\nM109 S220\nT3\nG1 X10 Y10\n")

    def driver(self, fake, **options):
        return MoonrakerDriver("b3", {"url": fake.url, "light_device": "", "timeout": 2, "start_timeout": 0.3, "start_wait": 3, "start_poll": 0.1} | options)

    async def test_a_pause_carries_the_consoles_error_lines_and_the_command_before_them(self):
        async with FakeMoonraker() as fake:
            now = time.time()
            fake.print_stats.update(state="paused", filename="mp164-matplace-F26-000108.gcode", total_duration=600)
            fake.filament_hub = HUB
            fake.gcode_store = [dict(e, time=now - 300 + i) for i, e in enumerate(STORE)]
            d = self.driver(fake)
            await d.connect()
            try:
                s = await d.status()
            finally:
                await d.close()
            self.assertEqual(s.state, PAUSED)
            self.assertEqual(s.job.status, "paused")
            self.assertIn("last command FEED_FILAMENT ID=0 INDEX=3", s.job.message)
            self.assertIn("filament hub feed filament timeout", s.job.message)
            self.assertIn("Extruder must heat up first", s.job.message)
            self.assertNotIn("leviQ3", s.job.message, "only the error lines, not the console's chatter")
            self.assertNotIn("!!", s.job.message)
            self.assertEqual(s.telemetry["message"], s.job.message, "the same words at the printer, even outside a job")
            self.assertLessEqual(len(s.job.message), 300)

    async def test_console_lines_from_before_this_print_are_not_its_reason(self):
        async with FakeMoonraker() as fake:
            fake.print_stats.update(state="paused", filename="x.gcode", total_duration=60)
            fake.gcode_store = [dict(e, time=time.time() - 3600) for e in STORE]      # an hour ago, a print earlier
            d = self.driver(fake)
            await d.connect()
            try:
                s = await d.status()
            finally:
                await d.close()
            self.assertEqual(s.state, PAUSED)
            self.assertEqual(s.job.message, "", "a pause the printer gives no reason for carries no words")
            self.assertNotIn("message", s.telemetry)

    async def test_the_screen_text_the_hub_and_an_empty_slot_speak_outside_a_print_too(self):
        async with FakeMoonraker() as fake:
            fake.display = {"message": "Load filament"}
            fake.filament_hub = hub_with(3, "runout")
            fake.filament_hub["filament_hubs"][0]["status"] = "busy"
            d = self.driver(fake)
            await d.connect()
            try:
                s = await d.status()
            finally:
                await d.close()
            self.assertEqual(s.state, IDLE)
            self.assertIsNone(s.job)
            self.assertEqual(s.telemetry["message"], "Load filament · ACE busy · ACE slot 4 runout")

    async def test_a_print_in_error_says_what_print_stats_and_the_console_say(self):
        async with FakeMoonraker() as fake:
            fake.print_stats.update(state="error", filename="x.gcode", message="Move out of range: 300.0 0.0 0.5", total_duration=100)
            fake.gcode_store = [{"message": "!! Move out of range: 300.0 0.0 0.5", "time": time.time() - 10, "type": "response"}]
            d = self.driver(fake)
            await d.connect()
            try:
                s = await d.status()
            finally:
                await d.close()
            self.assertEqual(s.state, ERROR)
            self.assertEqual(s.job.message, "Move out of range: 300.0 0.0 0.5", "the same line once")
            self.assertEqual(s.telemetry["error"], "Move out of range: 300.0 0.0 0.5")

    async def test_slot_status_is_the_hubs_word_or_nothing(self):
        async with FakeMoonraker() as fake:
            d = self.driver(fake)
            await d.connect()
            try:
                self.assertIsNone(await d.slot_status(3), "no ACE: nothing to say")
                fake.filament_hub = hub_with(3, "empty")
                self.assertEqual(await d.slot_status(3), "empty")
                self.assertEqual(await d.slot_status(2), "ready")
                self.assertIsNone(await d.slot_status(7), "a position the hub does not have")
            finally:
                await d.close()

    async def test_the_files_of_the_printer_with_the_disks_free_space(self):
        async with FakeMoonraker() as fake:
            fake.files = {"3DBenchy_PLA_0.2_1h2m.gcode": (1_153_356, 1790022115), "mp91-matplace-T26-000030.gcode": (3_667_786, 1791632678)}
            d = self.driver(fake)
            await d.connect()
            try:
                listing = await d.files()
                self.assertEqual([f["name"] for f in listing["files"]], ["mp91-matplace-T26-000030.gcode", "3DBenchy_PLA_0.2_1h2m.gcode"], "newest first")
                self.assertEqual(listing["files"][0], {"name": "mp91-matplace-T26-000030.gcode", "size": 3_667_786, "modified": 1791632678})
                self.assertEqual((listing["free"], listing["total"]), (2967470080, 6228017152))

                target = os.path.join(self.tmp.name, "down", "3DBenchy_PLA_0.2_1h2m.gcode")
                size = await d.fetch("3DBenchy_PLA_0.2_1h2m.gcode", target)
                self.assertEqual(size, os.path.getsize(target))
                self.assertGreater(size, 1_000_000)
                with self.assertRaises(DriverError):
                    await d.fetch("missing.gcode", target + ".2")
                with self.assertRaises(DriverError):
                    await d.fetch("../printer.cfg", target + ".3")

                await d.delete("3DBenchy_PLA_0.2_1h2m.gcode")
                self.assertEqual(fake.deleted, ["3DBenchy_PLA_0.2_1h2m.gcode"])
                self.assertEqual([f["name"] for f in (await d.files())["files"]], ["mp91-matplace-T26-000030.gcode"])
                with self.assertRaises(DriverError) as caught:
                    await d.delete("3DBenchy_PLA_0.2_1h2m.gcode")
                self.assertIn("404", str(caught.exception))
                with self.assertRaises(DriverError):
                    await d.delete("a/b.gcode")
            finally:
                await d.close()

    async def test_a_finished_print_reset_to_standby_is_the_plate_confirmation_once(self):
        async with FakeMoonraker() as fake:
            fake.print_stats.update(state="complete", filename="mp159-matplace-F26-000103.gcode")
            d = self.driver(fake)
            await d.connect()
            try:
                s = await d.status()
                self.assertNotIn("bed_cleared_at", s.telemetry, "complete alone is what U1 shows for days")
                fake.print_stats.update(state="standby", filename="")
                first = (await d.status()).telemetry["bed_cleared_at"]
                self.assertAlmostEqual(first, time.time(), delta=3)
                self.assertEqual((await d.status()).telemetry["bed_cleared_at"], first, "the same moment in the next reports")
                # standby stays: no new confirmation out of nothing
                d._plate_cleared_at = time.time() - 120
                self.assertNotIn("bed_cleared_at", (await d.status()).telemetry)
            finally:
                await d.close()

    async def test_a_silent_resume_is_a_timeout_for_the_driver_the_agent_judges_the_state(self):
        async with FakeMoonraker() as fake:
            fake.print_stats.update(state="paused", filename="x.gcode")
            fake.pause_answers = False
            d = self.driver(fake, timeout=0.3)
            await d.connect()
            try:
                with self.assertRaises(DriverError) as caught:
                    await d.resume()
                self.assertIn("TimeoutError", str(caught.exception))
                self.assertEqual((await d.status()).state, PRINTING, "the printer resumed all the same")
            finally:
                await d.close()


if __name__ == "__main__":
    unittest.main()
