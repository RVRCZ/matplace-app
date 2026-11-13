"""
Agent 0.2.14: a printer whose changer (ACE) was taken off prints from the spool fed straight into its head
(docs/AL.md §24). The server marks such a start `direct`; the agent then asks about no position of the changer. The
Moonraker driver tells how many ACE units answer, so the admin sees when the printer and its switch disagree.

Run:  python -m unittest discover -s tests   (from agent/)
"""
import os
import sys
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import test_agent as base  # noqa: E402  (the module, not its classes: an imported TestCase would be collected here again)


class DirectSpoolStart(base.AgentTest):
    """The same agent and fake server as test_agent; only the start payload and the mock's slot words differ."""

    def setUp(self):
        # the inherited tests of AgentTest run in their own file; here only the ones below
        name = self._testMethodName
        if not name.startswith("test_direct") and not name.startswith("test_without"):
            self.skipTest("covered by test_agent")

    @staticmethod
    def direct_cmd(**payload):
        cmd = base.start_cmd()
        cmd["payload"].update(payload)
        return cmd

    async def test_without_the_mark_an_empty_position_refuses_the_start(self):
        agent = self.agent(slots={0: "empty"})
        self.server.queue.append(base.start_cmd())
        await self.rounds(agent, 4)
        self.assertEqual([r[:2] for r in self.server.results], [(1, False)])
        self.assertIn("slot 1 is empty", self.server.results[0][2])
        self.assertEqual(self.server.jobs(), [])

    async def test_direct_start_asks_about_no_position(self):
        # the hub is gone, so whatever the firmware says about position 1 ("empty") must not stop the print
        agent = self.agent(slots={0: "empty"})
        self.server.queue.append(self.direct_cmd(direct=True))
        await self.rounds(agent, 12)
        self.assertEqual(self.server.results, [(1, True, "")], "started, and no note about an unchecked slot")
        self.assertEqual([j["status"] for j in self.server.jobs()][-1], "done")

    async def test_direct_start_ignores_a_list_of_filaments_too(self):
        agent = self.agent(slots={0: "empty", 2: "runout"})
        self.server.queue.append(self.direct_cmd(direct=True, filaments=[0, 2]))
        await self.rounds(agent, 4)
        self.assertEqual(self.server.results, [(1, True, "")])

    async def test_direct_false_checks_as_before(self):
        agent = self.agent(slots={0: "ready"})
        self.server.queue.append(self.direct_cmd(direct=False))
        await self.rounds(agent, 4)
        self.assertEqual(self.server.results, [(1, True, "")], "a position that holds a spool: started, nothing to say")


class MoonrakerAceUnits(unittest.IsolatedAsyncioTestCase):
    _driver = staticmethod(base.MoonrakerDryerTelemetry._driver)

    async def test_one_ace_answers(self):
        st = await self._driver({"print_stats": {"state": "standby"},
                                 "filament_hub": {"filament_hubs": [{"id": 0, "status": "ready", "slots": []}]}}).status()
        self.assertEqual(st.telemetry["ace_units"], 1)

    async def test_the_hub_object_without_a_unit_is_no_ace(self):
        for hub in ({"filament_hubs": []}, {}, {"filament_hubs": None}):
            st = await self._driver({"print_stats": {"state": "standby"}, "filament_hub": hub}).status()
            self.assertEqual(st.telemetry["ace_units"], 0, hub)

    async def test_a_firmware_without_the_hub_object_says_nothing(self):
        for status in ({"print_stats": {"state": "standby"}}, {"print_stats": {"state": "standby"}, "filament_hub": None}):
            st = await self._driver(status).status()
            self.assertNotIn("ace_units", st.telemetry)


if __name__ == "__main__":
    unittest.main()
