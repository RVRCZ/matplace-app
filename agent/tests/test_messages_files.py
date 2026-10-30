"""
Agent 0.2.9 against the simulated printer and the fake server: the printer's words in every report, a pause with and
without a reason, a resume the printer answers too late, an empty spool position before a start, the printer's files
(list, fetch, delete, cleanup that leaves the running job's file alone).   Run:  python -m pytest tests   (from agent/)
"""
import asyncio
import os
import sys
import tempfile
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import farm_agent.agent as agent_module  # noqa: E402
from farm_agent.agent import Agent  # noqa: E402
from farm_agent.config import Config, PrinterConfig  # noqa: E402
from test_agent import GCODE, FakeServer, start_cmd  # noqa: E402


class MessagesAndFilesTest(unittest.IsolatedAsyncioTestCase):
    def agent(self, **mock):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        options = {"print_seconds": 0.3, "heat_seconds": 0.05} | mock
        config = Config(server="https://x", token="t", work_dir=self.tmp.name, printers=[PrinterConfig("p1", "mock", options)])
        self.server = FakeServer()
        return Agent(config, self.server)

    async def rounds(self, agent, n, pause=0.06):
        for _ in range(n):
            await agent.sync_once()
            await asyncio.sleep(pause)
            await asyncio.gather(*agent._tasks, return_exceptions=True)

    def telemetry(self):
        return [p["telemetry"] for r in self.server.reports for p in r if p["key"] == "p1"]

    # -- messages ----------------------------------------------------------------------------------------------
    async def test_a_pause_with_a_reason_is_in_every_report_at_the_printer_and_at_the_job(self):
        agent = self.agent(print_seconds=5, pause_at=5, pause_message="last command FEED_FILAMENT ID=0 INDEX=3 · error: typ = WebRequestError, code = 10011710, message = filament hub feed filament timeout")
        self.server.queue.append(start_cmd())
        await self.rounds(agent, 8)
        paused = [j for j in self.server.jobs() if j["status"] == "paused"]
        self.assertGreaterEqual(len(paused), 2, "the pause is reported again and again, not once")
        for j in paused:
            self.assertIn("filament hub feed filament timeout", j["message"])
        said = [t.get("message") for t in self.telemetry() if t.get("message")]
        self.assertTrue(said and all("10011710" in m for m in said), "the printer's words travel in the telemetry too")
        self.assertEqual(self.server.reports[-1][0]["state"], "paused")

    async def test_a_pause_without_a_reason_carries_an_empty_message(self):
        agent = self.agent(print_seconds=5, pause_at=5)
        self.server.queue.append(start_cmd())
        await self.rounds(agent, 6)
        paused = [j for j in self.server.jobs() if j["status"] == "paused"]
        self.assertTrue(paused)
        self.assertEqual({j["message"] for j in paused}, {""})
        self.assertNotIn("message", self.server.reports[-1][0]["telemetry"])

    # -- commands judged by the printer's state -----------------------------------------------------------------
    async def test_a_resume_the_printer_answers_too_late_is_done_when_the_print_goes_on(self):
        agent = self.agent(print_seconds=5, pause_at=2, resume_timeout=True)
        agent_module.CONFIRM_WAIT_SECONDS, agent_module.CONFIRM_POLL_SECONDS = 2.0, 0.05
        self.addCleanup(lambda: setattr(agent_module, "CONFIRM_WAIT_SECONDS", 60.0))
        self.addCleanup(lambda: setattr(agent_module, "CONFIRM_POLL_SECONDS", 2.0))
        self.server.queue.append(start_cmd())
        await self.rounds(agent, 7)
        self.assertEqual(self.server.jobs()[-1]["status"], "paused")
        self.server.queue.append({"id": 2, "printer": "p1", "type": "resume", "job_id": 7, "payload": {}})
        await self.rounds(agent, 3)
        result = self.server.results[-1]
        self.assertEqual(result[:2], (2, True), result)
        self.assertIn("confirmed by the printer's state", result[2])
        self.assertIn("TimeoutError", result[2])
        self.assertEqual(self.server.jobs()[-1]["status"], "printing")

    async def test_a_resume_of_nothing_stays_failed_when_the_state_does_not_change(self):
        agent = self.agent(print_seconds=5)
        agent_module.CONFIRM_WAIT_SECONDS, agent_module.CONFIRM_POLL_SECONDS = 0.3, 0.05
        self.addCleanup(lambda: setattr(agent_module, "CONFIRM_WAIT_SECONDS", 60.0))
        self.addCleanup(lambda: setattr(agent_module, "CONFIRM_POLL_SECONDS", 2.0))
        self.server.queue.append({"id": 1, "printer": "p1", "type": "resume", "job_id": None, "payload": {}})
        await self.rounds(agent, 2, pause=0.2)
        self.assertEqual(self.server.results[-1][:2], (1, False))
        self.assertIn("nothing is paused", self.server.results[-1][2])
        self.assertIn("did not change", self.server.results[-1][2])

    # -- the spool position before a start ------------------------------------------------------------------------
    async def test_an_empty_spool_position_refuses_the_start_before_anything_is_sent(self):
        agent = self.agent(print_seconds=5, slots={0: "empty", 1: "ready"})
        self.server.queue.append(start_cmd())
        await self.rounds(agent, 2)
        self.assertEqual(self.server.results[-1][:2], (1, False))
        self.assertIn("slot 1 is empty", self.server.results[-1][2])
        self.assertIsNone(agent.workers["p1"].tracked)
        self.assertEqual(agent.workers["p1"].driver._file, None, "nothing was uploaded or started")

    async def test_a_plate_of_several_filaments_checks_every_slot_and_a_known_slot_leaves_no_note(self):
        agent = self.agent(print_seconds=5, slots={0: "ready", 2: "ready", 1: "runout"})
        cmd = start_cmd()
        cmd["payload"]["filaments"] = [0, 2]
        self.server.queue.append(cmd)
        await self.rounds(agent, 2)
        self.assertEqual(self.server.results[-1], (1, True, ""))
        cmd = start_cmd(cmd_id=2, job_id=8)
        cmd["payload"]["filaments"] = [0, 1]
        self.server.queue.append({"id": 9, "printer": "p1", "type": "cancel", "job_id": 7, "payload": {}})
        await self.rounds(agent, 3)
        self.server.queue.append(cmd)
        await self.rounds(agent, 2)
        self.assertEqual(self.server.results[-1][:2], (2, False))
        self.assertIn("slot 2 is runout", self.server.results[-1][2])

    # -- the operator confirmed the finished print on the printer --------------------------------------------------
    async def test_the_reset_of_a_finished_print_is_reported_as_the_plate_cleared(self):
        agent = self.agent()
        self.server.queue.append(start_cmd())
        await self.rounds(agent, 12)
        self.assertEqual(self.server.jobs()[-1]["status"], "done")
        self.assertFalse([t for t in self.telemetry() if "bed_cleared_at" in t], "a finished print alone is no confirmation")
        # what SDCARD_RESET_FILE (Mainsail's reset of the finished job) does on Rinkhals: complete -> standby
        agent.workers["p1"].driver._state = "standby"
        await self.rounds(agent, 3)
        marks = {t["bed_cleared_at"] for t in self.telemetry() if "bed_cleared_at" in t}
        self.assertEqual(len(marks), 1, "one moment, repeated in several reports")
        self.assertGreaterEqual(sum(1 for t in self.telemetry() if "bed_cleared_at" in t), 3)

    # -- the printer's storage -------------------------------------------------------------------------------------
    async def test_the_file_list_with_the_free_space_goes_to_the_server(self):
        agent = self.agent(files={"3DBenchy_PLA_0.2_1h2m.gcode": 1_153_356, "mp91-matplace-T26-000030.gcode": 3_667_786})
        self.server.queue.append({"id": 1, "printer": "p1", "type": "files", "job_id": None, "payload": {}})
        await self.rounds(agent, 2)
        self.assertEqual(self.server.results[-1], (1, True, "2 files"))
        key, listing = self.server.listings[-1]
        self.assertEqual(key, "p1")
        self.assertEqual({f["name"] for f in listing["files"]}, {"3DBenchy_PLA_0.2_1h2m.gcode", "mp91-matplace-T26-000030.gcode"})
        self.assertEqual(listing["total"] - listing["free"], 1_153_356 + 3_667_786)
        self.assertTrue(all({"name", "size", "modified"} <= set(f) for f in listing["files"]))

    async def test_a_file_is_fetched_to_the_server_and_a_big_one_is_refused_before_the_download(self):
        agent = self.agent(files={"small.gcode": 1200, "huge.gcode": 400 << 20})
        self.server.queue.append({"id": 1, "printer": "p1", "type": "fetch", "job_id": None, "payload": {"name": "small.gcode"}})
        self.server.queue.append({"id": 2, "printer": "p1", "type": "fetch", "job_id": None, "payload": {"name": "huge.gcode"}})
        self.server.queue.append({"id": 3, "printer": "p1", "type": "fetch", "job_id": None, "payload": {"name": "../etc/passwd"}})
        self.server.queue.append({"id": 4, "printer": "p1", "type": "fetch", "job_id": None, "payload": {"name": "missing.gcode"}})
        await self.rounds(agent, 3)
        results = dict((r[0], r[1:]) for r in self.server.results)
        self.assertEqual(results[1], (True, "1200 bytes"))
        self.assertEqual(self.server.uploads, [("p1", "small.gcode", 1200)])
        self.assertFalse(results[2][0])
        self.assertIn("at most 300 MB", results[2][1])
        self.assertFalse(results[3][0])
        self.assertIn("not a plain file name", results[3][1])
        self.assertFalse(results[4][0])
        self.assertIn("no such file", results[4][1])
        self.assertFalse(os.path.exists(os.path.join(self.tmp.name, "p1", "fetched", "small.gcode")), "nothing stays on the agent's disk")

    async def test_delete_removes_one_file_but_never_the_one_being_printed(self):
        agent = self.agent(print_seconds=5, files={"old.gcode": 500})
        self.server.queue.append(start_cmd())
        await self.rounds(agent, 2)
        running = agent.workers["p1"].tracked.filename
        self.server.queue.append({"id": 2, "printer": "p1", "type": "delete", "job_id": None, "payload": {"name": running}})
        self.server.queue.append({"id": 3, "printer": "p1", "type": "delete", "job_id": None, "payload": {"name": "old.gcode"}})
        await self.rounds(agent, 3)
        results = dict((r[0], r[1:]) for r in self.server.results)
        self.assertFalse(results[2][0])
        self.assertIn("is being printed", results[2][1])
        self.assertEqual(results[3], (True, "deleted old.gcode"))
        self.assertEqual(set(agent.workers["p1"].driver.files_on_disk), {running})
        key, listing = self.server.listings[-1]
        self.assertEqual(listing["deleted"], [{"name": "old.gcode", "size": 500}])
        self.assertEqual(listing["freed"], 500)

    async def test_cleanup_deletes_the_farms_own_files_only_and_keeps_what_runs_or_waits(self):
        files = {"mp5-matplace-F26-000005.gcode": 1000, "mp6-matplace-F26-000006.gcode": 2000, "mp8-matplace-F26-000008.gcode.3mf": 4000,
                 "3DBenchy_PLA_0.2_1h2m.gcode": 3000, "customer-matplace-copy.gcode": 100}
        agent = self.agent(print_seconds=5, files=files)
        self.server.queue.append(start_cmd())           # job 7 -> mp7-matplace-F26-000001.gcode, running
        await self.rounds(agent, 2)
        self.server.queue.append({"id": 2, "printer": "p1", "type": "cleanup", "job_id": None, "payload": {"keep": ["mp8-matplace-F26-000008.gcode"]}})
        await self.rounds(agent, 3)
        self.assertEqual(self.server.results[-1], (2, True, "deleted 2 files, 0 MB freed"))
        left = set(agent.workers["p1"].driver.files_on_disk)
        self.assertEqual(left, {"mp7-matplace-F26-000001.gcode", "mp8-matplace-F26-000008.gcode.3mf", "3DBenchy_PLA_0.2_1h2m.gcode", "customer-matplace-copy.gcode"})
        key, listing = self.server.listings[-1]
        self.assertEqual({d["name"] for d in listing["deleted"]}, {"mp5-matplace-F26-000005.gcode", "mp6-matplace-F26-000006.gcode"})
        self.assertEqual(listing["freed"], 3000)
        self.assertEqual({f["name"] for f in listing["files"]}, left, "the fresh list goes with the summary")

    async def test_a_printer_without_a_file_list_says_so(self):
        agent = self.agent()
        driver = agent.workers["p1"].driver

        async def no_files():
            from farm_agent.drivers.base import DriverError
            raise DriverError("the printer refuses the file list: 10112")
        driver.files = no_files
        self.server.queue.append({"id": 1, "printer": "p1", "type": "files", "job_id": None, "payload": {}})
        await self.rounds(agent, 2)
        self.assertEqual(self.server.results[-1][:2], (1, False))
        self.assertIn("10112", self.server.results[-1][2])
        self.assertEqual(self.server.listings, [])

    async def test_the_gcode_fixture_is_what_the_fake_server_hands_out(self):
        self.assertTrue(GCODE.startswith(b"G9111"))


if __name__ == "__main__":
    unittest.main()
