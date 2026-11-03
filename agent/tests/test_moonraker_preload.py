"""
The Moonraker driver 0.2.10 against the fake Moonraker: the preload of the slot before a start (option
preload_filament, off by default) and the word for a print that stands at the cold-feed dialog 10539 / 10011715
(TODO #65, docs/AM.md §11).   Run:  python -m pytest tests   (from agent/)

The samples are what U2 (Kobra S1, Rinkhals 20260901_01) logged on 1-3 Nov 2026: Rinkhals' own auto-feed sent
FEED_FILAMENT during the leveling, GoKlipper ran it cold and answered "Extruder must heat up first".
"""
import copy
import os
import sys
import tempfile
import time
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from farm_agent.drivers.base import PRINTING, DriverError  # noqa: E402
from farm_agent.drivers.moonraker import MoonrakerDriver  # noqa: E402
from test_moonraker import FakeMoonraker  # noqa: E402
from test_moonraker_messages import HUB  # noqa: E402

COLD_FEED = [
    {"message": "leviQ3 auto_zoffset_on_off: 1\n // The SAVE_CONFIG command will update the printer config file", "type": "response"},
    {"message": "FEED_FILAMENT ID=0 INDEX=3 LENGTH=80 SPEED=25", "type": "command"},
    {"message": "!! error: typ = WebRequestError, code = 10011715, message = Extruder must heat up first", "type": "response"},
]


def hub(current: str = "") -> dict:
    h = copy.deepcopy(HUB)
    h["current_filament"] = current
    return h


class MoonrakerPreloadTest(unittest.IsolatedAsyncioTestCase):
    async def asyncSetUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.gcode = os.path.join(self.tmp.name, "print.gcode")
        with open(self.gcode, "wb") as fh:
            fh.write(b"; HEADER_BLOCK_START\n; HEADER_BLOCK_END\nG9111 bedTemp=100 extruderTemp=270\nM117\nM104 S270 ; matplace slot: heat before the spool change\n"
                     b"M109 S270 ; matplace slot: wait for it\nT3 ; slot chosen by matplace farm\nG1 X10 Y10\n")

    def driver(self, fake, **options):
        return MoonrakerDriver("u2", {"url": fake.url, "light_device": "", "timeout": 2, "start_timeout": 0.3, "start_wait": 3,
                                      "start_poll": 0.05, "preload_timeout": 2} | options)

    async def test_without_the_option_a_start_sends_no_gcode_at_all(self):
        async with FakeMoonraker() as fake:
            fake.filament_hub = hub("")
            d = self.driver(fake)
            await d.connect()
            try:
                await d.start(self.gcode, "mp1-print.gcode", 3)
            finally:
                await d.close()
            self.assertEqual(fake.scripts, [], "today's behaviour: the file alone")
            self.assertEqual(fake.started, ["mp1-print.gcode"])

    async def test_an_empty_head_is_heated_to_the_files_temperature_and_fed_before_the_start(self):
        async with FakeMoonraker() as fake:
            fake.filament_hub = hub("")
            d = self.driver(fake, preload_filament=True)
            await d.connect()
            try:
                await d.start(self.gcode, "mp2-print.gcode", 3)
            finally:
                await d.close()
            self.assertEqual(fake.scripts, ["M104 S270", "FEED_FILAMENT ID=0 INDEX=3 LENGTH=80 SPEED=25"], "heat, then the feed Rinkhals itself would send")
            self.assertEqual(fake.filament_hub["current_filament"], "0-3")
            self.assertEqual(fake.uploaded, ["mp2-print.gcode"], "uploaded after the feed")
            self.assertEqual(fake.started, ["mp2-print.gcode"])

    async def test_a_loaded_head_is_left_to_the_files_own_tool_line(self):
        async with FakeMoonraker() as fake:
            fake.filament_hub = hub("0-1")         # another slot: the hot T3 after the macro changes it, as since 12 Oct 2026
            d = self.driver(fake, preload_filament=True)
            await d.connect()
            try:
                await d.start(self.gcode, "mp3-print.gcode", 3)
                self.assertEqual(fake.scripts, [])
                fake.print_stats.update(state="standby", filename="")
                fake.filament_hub = hub("0-3")     # the same slot: nothing to do either
                await d.start(self.gcode, "mp4-print.gcode", 3)
                self.assertEqual(fake.scripts, [])
                fake.print_stats.update(state="standby", filename="")
                fake.filament_hub = None           # no ACE at all (plain Klipper): nothing to ask
                await d.start(self.gcode, "mp5-print.gcode", 0)
                self.assertEqual(fake.scripts, [])
            finally:
                await d.close()
            self.assertEqual(fake.started, ["mp3-print.gcode", "mp4-print.gcode", "mp5-print.gcode"])

    async def test_a_refused_feed_fails_the_start_in_the_printers_words_and_switches_the_heater_off(self):
        async with FakeMoonraker() as fake:
            fake.filament_hub = hub("")
            fake.feed_error = "filament hub feed filament timeout"
            d = self.driver(fake, preload_filament=True)
            await d.connect()
            try:
                with self.assertRaises(DriverError) as caught:
                    await d.start(self.gcode, "mp6-print.gcode", 2)
            finally:
                await d.close()
            self.assertIn("filament hub feed filament timeout", str(caught.exception))
            self.assertEqual(fake.scripts, ["M104 S270", "FEED_FILAMENT ID=0 INDEX=2 LENGTH=80 SPEED=25", "M104 S0"])
            self.assertEqual(fake.uploaded, [], "nothing goes to the printer after a failed preload")
            self.assertEqual(fake.started, [])

    async def test_a_nozzle_that_never_heats_is_a_timeout_not_a_feed(self):
        async with FakeMoonraker() as fake:
            fake.filament_hub = hub("")
            fake.heats = False
            d = self.driver(fake, preload_filament=True, preload_timeout=0.3, preload_temp=240)
            await d.connect()
            try:
                with self.assertRaises(DriverError) as caught:
                    await d.start(self.gcode, "mp7-print.gcode", 1)
            finally:
                await d.close()
            self.assertIn("240 wanted", str(caught.exception))
            self.assertEqual(fake.scripts, ["M104 S240", "M104 S0"], "the option's temperature wins over the file's; no feed into a cold nozzle")
            self.assertEqual(fake.started, [])

    async def test_a_print_standing_at_the_cold_feed_dialog_says_so_while_the_head_is_empty(self):
        async with FakeMoonraker() as fake:
            now = time.time()
            fake.print_stats.update(state="printing", filename="mp184-matplace-F26-000122.gcode", total_duration=420)
            fake.filament_hub = hub("")
            fake.gcode_store = [dict(e, time=now - 300 + i) for i, e in enumerate(COLD_FEED)]
            d = self.driver(fake)
            await d.connect()
            try:
                s = await d.status()
                self.assertEqual(s.state, PRINTING, "Rinkhals maps the firmware's heating to printing: no pause to see")
                self.assertEqual(s.job.status, "printing")
                self.assertIn("10011715", s.telemetry["message"])
                self.assertIn("Extruder must heat up first", s.telemetry["message"])
                self.assertIn("dialog 10539", s.telemetry["message"])
                self.assertEqual(s.job.message, s.telemetry["message"], "the job carries it too, so the order's history shows it")
                self.assertLessEqual(len(s.job.message), 300)

                # the retried feed landed: the head holds the slot, the words are gone (the console still has the line)
                fake.filament_hub = hub("0-3")
                d._console_cache = (0.0, [])
                s = await d.status()
                self.assertNotIn("message", s.telemetry)
                self.assertEqual(s.job.message, "")
            finally:
                await d.close()

    async def test_an_old_cold_feed_line_or_another_error_is_not_this_prints_dialog(self):
        async with FakeMoonraker() as fake:
            now = time.time()
            fake.print_stats.update(state="printing", filename="x.gcode", total_duration=60)
            fake.filament_hub = hub("")
            fake.gcode_store = [dict(e, time=now - 3600) for e in COLD_FEED]      # the print before this one
            d = self.driver(fake)
            await d.connect()
            try:
                s = await d.status()
                self.assertNotIn("message", s.telemetry)
                self.assertEqual(s.job.message, "")
                # another error line of this print: a running print keeps only print_stats' own words, as before
                fake.gcode_store = [{"message": "!! error: typ = WebRequestError, code = 10011711, message = filament hub unwind filament timeout", "time": now - 10, "type": "response"}]
                d._console_cache = (0.0, [])
                s = await d.status()
                self.assertNotIn("message", s.telemetry)
                self.assertEqual(s.job.message, "")
            finally:
                await d.close()


if __name__ == "__main__":
    unittest.main()
