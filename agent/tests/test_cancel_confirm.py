"""
Agent 0.2.13: a cancel is done when the printer's state says so (docs/AM.md §13, F26-000158 on U2, 11 Nov 2026).

  cancel answered ok, the print stays paused -> the driver's second step (SDCARD_RESET_FILE) -> confirmed, said so
  the second step changes nothing / is refused / does not exist -> the command fails with the reason
  a file that is not the farm's is never ended by force
  a pause while the printer is still getting ready is refused at once with a plain sentence; a cancel then goes through
  a cancelled print that left the heaters on gets them switched off

Against the simulated printer, and once against the fake Moonraker with the real driver (the incident replayed).
Run:  python -m pytest tests   (from agent/)
"""
import asyncio
import os
import sys
import tempfile
import time
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import farm_agent.agent as agent_module  # noqa: E402
from farm_agent.agent import PAUSE_WHILE_STARTING, Agent  # noqa: E402
from farm_agent.config import Config, PrinterConfig  # noqa: E402
from farm_agent.drivers.base import IDLE, PAUSED  # noqa: E402
from farm_agent.drivers.moonraker import MoonrakerDriver  # noqa: E402
from test_agent import FakeServer, start_cmd  # noqa: E402
from test_moonraker import FakeMoonraker  # noqa: E402

WAITS = {"CONFIRM_WAIT_SECONDS": 0.3, "CONFIRM_POLL_SECONDS": 0.05, "FALLBACK_WAIT_SECONDS": 0.3, "COOL_WAIT_SECONDS": 0.2}


def cancel_cmd(cmd_id=2, job_id=7, printer="p1"):
    return {"id": cmd_id, "printer": printer, "type": "cancel", "job_id": job_id, "payload": {}}


class Base(unittest.IsolatedAsyncioTestCase):
    def setUp(self):
        for name, value in WAITS.items():
            self.addCleanup(setattr, agent_module, name, getattr(agent_module, name))
            setattr(agent_module, name, value)

    async def rounds(self, agent, n, pause=0.06):
        for _ in range(n):
            await agent.sync_once()
            await asyncio.sleep(pause)
            await asyncio.gather(*agent._tasks, return_exceptions=True)


class CancelConfirmTest(Base):
    def agent(self, **mock):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        options = {"print_seconds": 30, "heat_seconds": 0.05} | mock
        config = Config(server="https://x", token="t", work_dir=self.tmp.name, printers=[PrinterConfig("p1", "mock", options)])
        self.server = FakeServer()
        return Agent(config, self.server)

    async def printing(self, **mock):
        agent = self.agent(**mock)
        self.server.queue.append(start_cmd())
        await self.rounds(agent, 3)
        self.assertEqual(self.server.jobs()[-1]["status"], "printing")
        return agent, agent.workers["p1"].driver

    # -- A: the cancel is confirmed by the state ----------------------------------------------------------------
    async def test_a_cancel_the_printer_takes_is_done_without_a_note_and_without_a_second_step(self):
        agent, driver = await self.printing()
        self.server.queue.append(cancel_cmd())
        await self.rounds(agent, 3)
        self.assertEqual(self.server.results[-1], (2, True, ""))
        self.assertEqual(driver.steps, [])
        self.assertEqual(self.server.jobs()[-1]["status"], "cancelled")
        self.assertIsNone(agent.workers["p1"].tracked)

    async def test_cancel_answered_ok_but_the_print_stays_paused_the_second_step_ends_it(self):
        agent, driver = await self.printing(cancel_stuck=True)
        self.server.queue.append(cancel_cmd())
        await self.rounds(agent, 4)
        cmd_id, ok, note = self.server.results[-1]
        self.assertEqual((cmd_id, ok), (2, True), note)
        self.assertIn("cancel was answered ok, but the printer stayed paused", note)
        self.assertIn("SDCARD_RESET_FILE ended the print", note)
        self.assertEqual(driver.steps, ["SDCARD_RESET_FILE"])
        self.assertEqual((await driver.status()).state, IDLE)
        # the printer names no file any more: the job ends cancelled at once, not "failed" eight polls later
        last = self.server.jobs()[-1]
        self.assertEqual((last["id"], last["status"]), (7, "cancelled"))
        self.assertNotIn("failed", [j["status"] for j in self.server.jobs()])
        self.assertIsNone(agent.workers["p1"].tracked, "the job left the agent once the server had the report")

    async def test_the_second_step_changes_nothing_the_command_fails_with_the_reason(self):
        agent, driver = await self.printing(cancel_stuck=True, cancel_fallback="fails")
        self.server.queue.append(cancel_cmd())
        await self.rounds(agent, 4)
        cmd_id, ok, note = self.server.results[-1]
        self.assertEqual((cmd_id, ok), (2, False), note)
        self.assertTrue(note.startswith("cancel not confirmed: cancel was answered ok, but the printer is still paused"), note)
        self.assertIn("after SDCARD_RESET_FILE", note)
        self.assertIn("stop the print on the printer's screen", note)
        self.assertLessEqual(len(note), 300, "the server keeps 300 characters of a result")
        self.assertEqual((await driver.status()).state, PAUSED)
        self.assertEqual(self.server.jobs()[-1]["status"], "paused", "the job is still reported as it is, nothing is made up")
        self.assertIsNotNone(agent.workers["p1"].tracked)

    async def test_the_second_step_refused_fails_with_the_printers_words(self):
        agent, driver = await self.printing(cancel_stuck=True, cancel_fallback="refused")
        self.server.queue.append(cancel_cmd())
        await self.rounds(agent, 4)
        cmd_id, ok, note = self.server.results[-1]
        self.assertEqual((cmd_id, ok), (2, False), note)
        self.assertIn("SDCARD_RESET_FILE was refused", note)
        self.assertIn("Unknown command", note)

    async def test_a_printer_without_a_second_step_fails_and_says_so(self):
        agent, driver = await self.printing(cancel_stuck=True, cancel_fallback="none")
        self.server.queue.append(cancel_cmd())
        await self.rounds(agent, 4)
        cmd_id, ok, note = self.server.results[-1]
        self.assertEqual((cmd_id, ok), (2, False), note)
        self.assertIn("has no second way to end a print", note)
        self.assertEqual(driver.steps, [])

    async def test_a_file_that_is_not_the_farms_is_never_ended_by_force(self):
        agent, driver = await self.printing(cancel_stuck=True)
        driver._file = "benchy-from-the-screen.gcode"      # somebody started another print at the printer meanwhile
        self.server.queue.append(cancel_cmd())
        await self.rounds(agent, 4)
        cmd_id, ok, note = self.server.results[-1]
        self.assertEqual((cmd_id, ok), (2, False), note)
        self.assertIn("a file that is not the farm's (benchy-from-the-screen.gcode)", note)
        self.assertEqual(driver.steps, [], "no SDCARD_RESET_FILE for somebody else's print")

    async def test_a_cancel_the_printer_refuses_while_nothing_prints_is_confirmed_by_the_state(self):
        agent = self.agent()
        self.server.queue.append(cancel_cmd(cmd_id=1, job_id=None))
        await self.rounds(agent, 2)
        cmd_id, ok, note = self.server.results[-1]
        self.assertEqual((cmd_id, ok), (1, True), note)
        self.assertIn("confirmed by the printer's state", note)
        self.assertIn("nothing to cancel", note)

    async def test_a_restart_after_the_confirmed_cancel_still_reports_the_job_cancelled(self):
        agent, driver = await self.printing(cancel_stuck=True)
        w = agent.workers["p1"]
        await agent._cancel(w, await driver.status(), None, 7)       # confirmed by the second step, not yet reported
        self.assertTrue(w.tracked.cancelled)
        again = Agent(agent.config, self.server)
        again._load_state()
        self.assertTrue(again.workers["p1"].tracked.cancelled)
        self.assertEqual((await again.workers["p1"].report())["job"]["status"], "cancelled")

    # -- B: pause and cancel while the printer is getting ready ---------------------------------------------------
    async def test_a_pause_while_the_printer_is_getting_ready_is_refused_at_once_in_plain_words(self):
        agent = self.agent(heat_seconds=0.6)
        driver = agent.workers["p1"].driver
        self.server.queue.append(start_cmd())
        await self.rounds(agent, 2)
        self.assertTrue((await driver.status()).job.starting)
        self.server.queue.append({"id": 2, "printer": "p1", "type": "pause", "job_id": 7, "payload": {}})
        started = time.monotonic()
        await self.rounds(agent, 1)
        self.assertLess(time.monotonic() - started, 0.25, "no minute of waiting for a state that cannot come")
        self.assertEqual(self.server.results[-1], (2, False, PAUSE_WHILE_STARTING))
        self.assertEqual(driver._state, "printing", "the pause was not sent to the printer")
        # once the first layer runs the same command works
        await asyncio.sleep(0.6)
        self.assertFalse((await driver.status()).job.starting)
        self.server.queue.append({"id": 3, "printer": "p1", "type": "pause", "job_id": 7, "payload": {}})
        await self.rounds(agent, 2)
        self.assertEqual(self.server.results[-1][:2], (3, True))
        self.assertEqual(self.server.jobs()[-1]["status"], "paused")

    async def test_a_cancel_while_the_printer_is_getting_ready_goes_through_and_leaves_it_cold(self):
        agent = self.agent(heat_seconds=5, heaters_stay=True)
        driver = agent.workers["p1"].driver
        self.server.queue.append(start_cmd())
        await self.rounds(agent, 2)
        self.assertTrue((await driver.status()).job.starting)
        self.server.queue.append(cancel_cmd())
        await self.rounds(agent, 4)
        cmd_id, ok, note = self.server.results[-1]
        self.assertEqual((cmd_id, ok), (2, True), note)
        self.assertIn("the heaters were left on (nozzle 220, bed 55), switched off with TURN_OFF_HEATERS", note)
        now = await driver.status()
        self.assertEqual((now.state, now.telemetry["extruder_target"], now.telemetry["bed_target"]), (IDLE, 0.0, 0.0))
        self.assertEqual(self.server.jobs()[-1]["status"], "cancelled")

    async def test_heaters_left_on_by_the_second_step_are_switched_off_too(self):
        agent, driver = await self.printing(cancel_stuck=True, heaters_stay=True)
        self.server.queue.append(cancel_cmd())
        await self.rounds(agent, 4)
        cmd_id, ok, note = self.server.results[-1]
        self.assertEqual((cmd_id, ok), (2, True), note)
        self.assertEqual(driver.steps, ["SDCARD_RESET_FILE", "TURN_OFF_HEATERS"])
        self.assertIn("SDCARD_RESET_FILE ended the print; the heaters were left on", note)

    async def test_heaters_this_printer_cannot_switch_off_are_named_in_the_result(self):
        agent, driver = await self.printing(heaters_stay=True, heaters_off="none")
        self.server.queue.append(cancel_cmd())
        await self.rounds(agent, 4)
        cmd_id, ok, note = self.server.results[-1]
        self.assertEqual((cmd_id, ok), (2, True), note)
        self.assertEqual(note, "the heaters are still on (nozzle 220, bed 55); switch them off at the printer")


class MoonrakerCancelTest(Base):
    """The real driver against the fake Moonraker."""

    async def asyncSetUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)

    def agent(self, fake):
        config = Config(server="https://x", token="t", work_dir=self.tmp.name,
                        printers=[PrinterConfig("u2", "moonraker", {"url": fake.url, "light_device": "", "timeout": 2, "start_poll": 0.1})])
        self.server = FakeServer()
        return Agent(config, self.server)

    async def test_the_incident_replayed_stop_on_the_screen_cancel_ok_twice_reset_file_standby(self):
        async with FakeMoonraker() as fake:
            agent = self.agent(fake)
            driver = agent.workers["u2"].driver
            await driver.connect()
            try:
                self.server.queue.append(start_cmd(printer="u2"))
                await self.rounds(agent, 2)
                self.assertEqual(self.server.jobs("u2")[-1]["status"], "printing")
                # 20:31:32 the print is stopped on the printer's screen: paused, heaters still on, cancel changes nothing
                fake.print_stats.update(state="paused")
                fake.targets.update(extruder=220.0, heater_bed=55.0)
                fake.cancel_ends = False
                await self.rounds(agent, 1)
                self.assertEqual(self.server.jobs("u2")[-1]["status"], "paused")
                self.server.queue.append(cancel_cmd(printer="u2"))
                await self.rounds(agent, 4)
            finally:
                await driver.close()
            cmd_id, ok, note = self.server.results[-1]
            self.assertEqual((cmd_id, ok), (2, True), note)
            self.assertEqual(fake.cancels, 1)
            self.assertEqual(fake.scripts, ["SDCARD_RESET_FILE", "TURN_OFF_HEATERS"])
            self.assertEqual((fake.print_stats["state"], fake.print_stats["filename"]), ("standby", ""))
            self.assertEqual(fake.targets, {"extruder": 0.0, "heater_bed": 0.0})
            self.assertIn("SDCARD_RESET_FILE ended the print", note)
            self.assertIn("switched off with TURN_OFF_HEATERS", note)
            self.assertEqual(self.server.jobs("u2")[-1]["status"], "cancelled")
            self.assertEqual(self.server.reports[-1][0]["state"], "idle")

    async def test_reset_file_that_changes_nothing_fails_the_cancel(self):
        async with FakeMoonraker() as fake:
            agent = self.agent(fake)
            driver = agent.workers["u2"].driver
            await driver.connect()
            try:
                self.server.queue.append(start_cmd(printer="u2"))
                await self.rounds(agent, 2)
                fake.print_stats.update(state="paused")
                fake.cancel_ends = fake.reset_ends = False
                self.server.queue.append(cancel_cmd(printer="u2"))
                await self.rounds(agent, 4)
            finally:
                await driver.close()
            cmd_id, ok, note = self.server.results[-1]
            self.assertEqual((cmd_id, ok), (2, False), note)
            self.assertIn("still paused", note)
            self.assertIn("after SDCARD_RESET_FILE", note)
            self.assertEqual(fake.print_stats["state"], "paused")

    async def test_goklipper_without_reset_file_fails_the_cancel_with_its_words(self):
        async with FakeMoonraker() as fake:
            fake.print_stats.update(state="paused", filename="mp7-matplace-F26-000001.gcode")
            fake.cancel_ends, fake.knows_reset = False, False
            agent = self.agent(fake)
            driver = agent.workers["u2"].driver
            await driver.connect()
            try:
                self.server.queue.append(cancel_cmd(cmd_id=1, printer="u2"))
                await self.rounds(agent, 3)
            finally:
                await driver.close()
            cmd_id, ok, note = self.server.results[-1]
            self.assertEqual((cmd_id, ok), (1, False), note)
            self.assertIn("SDCARD_RESET_FILE was refused", note)

    async def test_heaters_off_falls_back_to_m104_and_m140(self):
        async with FakeMoonraker() as fake:
            fake.knows_heaters_off = False
            fake.targets.update(extruder=220.0, heater_bed=55.0)
            d = MoonrakerDriver("u2", {"url": fake.url, "light_device": "", "timeout": 2})
            await d.connect()
            try:
                self.assertEqual(await d.heaters_off(), "M104 S0 and M140 S0")
            finally:
                await d.close()
            self.assertEqual(fake.scripts, ["TURN_OFF_HEATERS", "M104 S0", "M140 S0"])
            self.assertEqual(fake.targets, {"extruder": 0.0, "heater_bed": 0.0})

    async def test_the_start_phase_is_told_by_three_signs_together(self):
        async with FakeMoonraker() as fake:
            d = MoonrakerDriver("u2", {"url": fake.url, "light_device": "", "timeout": 2})
            await d.connect()
            try:
                fake.print_stats.update(state="printing", filename="mp7-matplace-F26-000001.gcode", print_duration=41.0, filament_used=0.0,
                                        info={"current_layer": 0, "total_layer": 190})
                self.assertTrue((await d.status()).job.starting, "the file has not moved, no layer, nothing extruded")
                fake.print_stats.update(filament_used=12.5)
                self.assertFalse((await d.status()).job.starting, "something was extruded")
                fake.print_stats.update(filament_used=0.0, info={"current_layer": 1, "total_layer": 190})
                self.assertFalse((await d.status()).job.starting, "a layer is counted")
                fake.print_stats.update(info={"current_layer": 0, "total_layer": 190})
                fake.sd["progress"] = 0.004
                self.assertFalse((await d.status()).job.starting, "the file moved")
                fake.sd["progress"] = 0.0
                fake.print_stats.update(state="paused")
                self.assertFalse((await d.status()).job.starting, "only a printing printer can be starting")
            finally:
                await d.close()

    async def test_a_pause_during_the_start_macro_never_reaches_the_printer(self):
        async with FakeMoonraker() as fake:
            agent = self.agent(fake)
            driver = agent.workers["u2"].driver
            await driver.connect()
            try:
                self.server.queue.append(start_cmd(printer="u2"))
                await self.rounds(agent, 2)
                fake.print_stats.update(print_duration=11.0, filament_used=0.0)
                fake.pause_answers = False      # what U2 did on 11 Nov: the call would hang past its limit
                self.server.queue.append({"id": 2, "printer": "u2", "type": "pause", "job_id": 7, "payload": {}})
                await self.rounds(agent, 2)
            finally:
                await driver.close()
            self.assertEqual(self.server.results[-1], (2, False, PAUSE_WHILE_STARTING))
            self.assertEqual(fake.print_stats["state"], "printing")


if __name__ == "__main__":
    unittest.main()
