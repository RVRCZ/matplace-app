"""
The Anycubic LAN driver 0.2.9 against the fake Kobra X: the printer's words at a pause and a failure, the spool
position before a start, and the file list the Kobra X refuses (another firmware may answer).   Run:  python -m pytest tests
"""
import os
import sys
import tempfile
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from farm_agent.drivers import create  # noqa: E402
from farm_agent.drivers.base import IDLE, JOB_FAILED, PAUSED, PRINTING, DriverError  # noqa: E402
from test_anycubic_lan import FakePrinter  # noqa: E402


class AnycubicLanMessagesTest(unittest.IsolatedAsyncioTestCase):
    async def driver(self, printer: FakePrinter, **options):
        d = create("kobra-x-01", "anycubic_lan", {"ip": "192.168.8.44", "_link": printer.link, "start_poll": 0.01, "start_wait": 0.5,
                                                  "retry_seconds": 60, "answer_poll": 0.01, "answer_wait": 0.1, "busy_retry": 0.01, **options})
        await d.connect()
        self.addAsyncCleanup(d.close)
        return d

    def gcode(self) -> str:
        fd, path = tempfile.mkstemp(suffix=".gcode")
        with os.fdopen(fd, "wb") as fh:
            fh.write(b"; HEADER_BLOCK_START\n; total layer number: 50\n; HEADER_BLOCK_END\n\nG9111 bedTemp=55 extruderTemp=225\nM109 S225\nT0 ; slot chosen by matplace farm\n"
                     b"G1 X10 Y10\n; CONFIG_BLOCK_START\n; filament_colour = #F2754E\n; filament_type = PLA\n; CONFIG_BLOCK_END\n")
        self.addCleanup(os.remove, path)
        return path

    async def test_a_pause_and_a_failure_carry_the_printers_words(self):
        async with FakePrinter() as p:
            d = await self.driver(p)
            await d.start(self.gcode(), "mp1-cube.gcode", 0)
            self.assertEqual((await d.status()).state, PRINTING)
            self.assertNotIn("message", (await d.status()).telemetry, "a running print with nothing to say")
            await d.pause()
            s = await d.status()
            self.assertEqual(s.state, PAUSED)
            # the protocol gives the pause flag and nothing else: the screen's dialog never reaches the network
            self.assertEqual(s.job.message, "paused")
            self.assertEqual(s.telemetry["message"], "paused")
            await d.resume()
            self.assertEqual((await d.status()).state, PRINTING)
            # the printer ends the task with its reason, as the Kobra family words a runout (kobra.py: code 10107)
            p.info.update(state="free", project=None)
            p.report("print", {"action": "start", "state": "failed", "code": 10107, "msg": "Filament broken. Please load new filament.",
                               "data": {"taskid": "-1", "filename": "mp1-cube.gcode", "progress": 3}})
            s = await d.status()
            self.assertEqual(s.job.status, JOB_FAILED)
            self.assertEqual(s.job.message, "Filament broken. Please load new filament. (code 10107)", "once, with the code")
            self.assertEqual(s.telemetry["message"], s.job.message)

    async def test_a_feeding_state_is_in_the_message_while_it_lasts(self):
        async with FakePrinter() as p:
            d = await self.driver(p)
            await d.start(self.gcode(), "mp2-cube.gcode", 3)
            p.info["project"] = dict(p.info["project"], state="ace_filament_feeding")
            s = await d.status()
            self.assertEqual(s.state, PRINTING)
            self.assertEqual(s.telemetry["message"], "ace filament feeding")
            self.assertEqual(s.telemetry["spool_change"], "ace_filament_feeding")
            self.assertEqual(s.job.message, "", "a running print's job message stays the printer's err_msg")

    async def test_slot_status_is_the_boxs_word_for_the_position(self):
        async with FakePrinter() as p:
            d = await self.driver(p)
            self.assertEqual(await d.slot_status(0), "loaded")     # rack 1, status 5
            self.assertEqual(await d.slot_status(5), "ready")      # ACE 3, status 4
            self.assertIsNone(await d.slot_status(9), "a position the printer does not have")
            p.boxes[1]["slots"][0]["status"] = 0
            self.assertEqual(await d.slot_status(3), "status 0", "an unknown number is said as it is, never 'empty'")
            p.boxes = []
            self.assertIsNone(await d.slot_status(0), "no boxes reported: nothing to check")

    async def test_the_kobra_x_refuses_its_file_list_and_another_firmware_may_give_one(self):
        async with FakePrinter() as p:
            d = await self.driver(p)
            with self.assertRaises(DriverError) as caught:
                await d.files()
            self.assertIn("10112", str(caught.exception))
            p.file_list = [{"filename": "Dragon Textured_PLA_0.2_9h20m.gcode.3mf", "fileSize": 78779, "createTime": 1779615629},
                           {"filename": "cube.gcode", "fileSize": 1200, "createTime": 1779700000}]
            listing = await d.files()
            self.assertEqual([f["name"] for f in listing["files"]], ["cube.gcode", "Dragon Textured_PLA_0.2_9h20m.gcode.3mf"])
            self.assertEqual(listing["files"][1], {"name": "Dragon Textured_PLA_0.2_9h20m.gcode.3mf", "size": 78779, "modified": 1779615629})
            self.assertIsNone(listing["free"], "the protocol tells no free space")
            with self.assertRaises(DriverError):
                await d.fetch("cube.gcode", os.path.join(tempfile.gettempdir(), "x.gcode"))
            with self.assertRaises(DriverError):
                await d.delete("cube.gcode")
            self.assertEqual((await d.status()).state, IDLE)


if __name__ == "__main__":
    unittest.main()
