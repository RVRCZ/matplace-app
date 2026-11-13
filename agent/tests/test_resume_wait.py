"""
Agent 0.2.15: one resume is enough (docs/AM.md §15, F26-000162 on U2, 13 Nov 2026).

  a Kobra S1 under Rinkhals answers print/resume with TimeoutError and stays "paused" while it heats the nozzle and
  feeds the filament; on 13 Nov two resume commands ended "the printer's state did not change in 60 s" and only the
  third was confirmed. A resume now waits RESUME_WAIT_SECONDS for the state (a pause keeps CONFIRM_WAIT_SECONDS):
    the state turns to printing after longer than the pause's limit -> the one command is done, the call went out once
    the state never turns -> the command fails after the resume's limit, with the limit in the sentence
    a cancel that arrives while the resume waits ends the wait at once and is carried out; the resume says why it gave up

Against the simulated printer only: no real printer was asked.
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
from farm_agent import __version__  # noqa: E402
from farm_agent.agent import Agent  # noqa: E402
from farm_agent.config import Config, PrinterConfig  # noqa: E402
from farm_agent.drivers.base import IDLE, PAUSED, PRINTING  # noqa: E402
from test_agent import FakeServer, start_cmd  # noqa: E402

# the pause's limit is shorter than the printer's delay, the resume's limit longer: a resume judged by the pause's limit fails
WAITS = {"CONFIRM_WAIT_SECONDS": 0.2, "RESUME_WAIT_SECONDS": 3.0, "CONFIRM_POLL_SECONDS": 0.05, "FALLBACK_WAIT_SECONDS": 0.3, "COOL_WAIT_SECONDS": 0.2}
REAL = {name: getattr(agent_module, name) for name in WAITS}


def cmd(kind, cmd_id, job_id=7, printer="p1"):
    return {"id": cmd_id, "printer": printer, "type": kind, "job_id": job_id, "payload": {}}


class ResumeWaitTest(unittest.IsolatedAsyncioTestCase):
    def setUp(self):
        for name, value in WAITS.items():
            self.addCleanup(setattr, agent_module, name, getattr(agent_module, name))
            setattr(agent_module, name, value)

    def agent(self, **mock):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        options = {"print_seconds": 60, "heat_seconds": 0.05} | mock
        config = Config(server="https://x", token="t", work_dir=self.tmp.name, printers=[PrinterConfig("p1", "mock", options)])
        self.server = FakeServer()
        return Agent(config, self.server)

    async def rounds(self, agent, n, pause=0.06, wait=True):
        for _ in range(n):
            await agent.sync_once()
            await asyncio.sleep(pause)
            if wait:
                await asyncio.gather(*agent._tasks, return_exceptions=True)

    async def paused(self, **mock):
        agent = self.agent(**mock)
        self.server.queue.append(start_cmd())
        await self.rounds(agent, 3)
        self.server.queue.append(cmd("pause", 2))
        await self.rounds(agent, 2)
        self.assertEqual(self.server.results[-1], (2, True, ""))
        driver = agent.workers["p1"].driver
        self.assertEqual((await driver.status()).state, PAUSED)
        return agent, driver

    def test_the_version_and_the_limits(self):
        self.assertEqual(__version__, "0.2.15")
        # the limits as shipped (setUp shortens them for the tests): a resume waits five minutes, a pause one
        self.assertEqual((REAL["RESUME_WAIT_SECONDS"], REAL["CONFIRM_WAIT_SECONDS"]), (300.0, 60.0))

    async def test_one_resume_is_enough_when_the_printer_takes_longer_than_a_pause_may(self):
        agent, driver = await self.paused(resume_delay=0.6)
        self.server.queue.append(cmd("resume", 3))
        await self.rounds(agent, 2)
        cmd_id, ok, note = self.server.results[-1]
        self.assertEqual((cmd_id, ok), (3, True), note)
        self.assertIn("confirmed by the printer's state after", note)
        self.assertIn("TimeoutError", note)
        self.assertEqual(driver.resume_calls, 1, "the call went to the printer once")
        self.assertEqual((await driver.status()).state, PRINTING)
        self.assertEqual(self.server.jobs()[-1]["status"], "printing")

    async def test_a_resume_the_printer_never_takes_fails_after_the_resumes_own_limit(self):
        agent_module.RESUME_WAIT_SECONDS = 0.5
        agent, driver = await self.paused(resume_delay=600)
        began = time.monotonic()
        self.server.queue.append(cmd("resume", 3))
        await self.rounds(agent, 2)
        cmd_id, ok, note = self.server.results[-1]
        self.assertEqual((cmd_id, ok), (3, False), note)
        self.assertTrue(note.endswith("the printer's state did not change in 0 s") or note.endswith("the printer's state did not change in 1 s"), note)
        self.assertGreaterEqual(time.monotonic() - began, 0.5, "it waited the resume's limit, not the pause's 0.2 s")
        self.assertEqual((await driver.status()).state, PAUSED)

    async def test_a_pause_keeps_its_own_shorter_limit(self):
        agent = self.agent()
        self.server.queue.append(cmd("pause", 1, job_id=None))        # nothing prints: the call is refused, the state never turns
        began = time.monotonic()
        await self.rounds(agent, 2)
        cmd_id, ok, note = self.server.results[-1]
        self.assertEqual((cmd_id, ok), (1, False), note)
        self.assertIn("did not change in 0 s", note)
        self.assertLess(time.monotonic() - began, 2.0, "a pause does not wait the resume's three seconds")

    async def test_a_cancel_ends_the_wait_of_a_resume_and_is_carried_out(self):
        agent_module.RESUME_WAIT_SECONDS = 30.0
        agent, driver = await self.paused(resume_delay=600)
        self.server.queue.append(cmd("resume", 3))
        await self.rounds(agent, 3, wait=False)            # the resume is waiting for the printer's state now
        self.assertEqual([r[0] for r in self.server.results], [1, 2], "the resume has not answered yet")
        began = time.monotonic()
        self.server.queue.append(cmd("cancel", 4))
        await self.rounds(agent, 3)
        self.assertLess(time.monotonic() - began, 5.0, "the cancel did not wait behind the resume's thirty seconds")
        results = {r[0]: r for r in self.server.results}
        self.assertFalse(results[3][1], results[3])
        self.assertRegex(results[3][2], r"a cancel came after \d+ s; the wait for the printer was given up$")
        self.assertTrue(results[4][1], results[4])
        self.assertEqual((await driver.status()).state, IDLE)
        self.assertEqual(self.server.jobs()[-1]["status"], "cancelled")
        self.assertFalse(agent.workers["p1"].interrupt.is_set(), "the next pause or resume waits as usual")

    async def test_a_cancel_that_came_first_does_not_cut_a_later_resume_short(self):
        agent, driver = await self.paused(resume_delay=0.4)
        worker = agent.workers["p1"]
        worker.interrupt.set()          # as left by a cancel of an earlier print that has been carried out since
        worker.interrupt.clear()
        self.server.queue.append(cmd("resume", 3))
        await self.rounds(agent, 2)
        self.assertEqual(self.server.results[-1][:2], (3, True), self.server.results[-1])


if __name__ == "__main__":
    unittest.main()
