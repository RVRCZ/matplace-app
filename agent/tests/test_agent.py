"""
Agent against the simulated printer and a fake server.   Run:  python -m unittest discover -s tests   (from agent/)
"""
import asyncio
import hashlib
import os
import sys
import tempfile
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from farm_agent.agent import LOST_AFTER_POLLS, Agent  # noqa: E402
from farm_agent.config import Config, PrinterConfig, load  # noqa: E402
from farm_agent.drivers import create  # noqa: E402
from farm_agent.drivers.base import DriverError  # noqa: E402
from farm_agent.server import ServerError  # noqa: E402

GCODE = b"G9111 bedTemp=55 extruderTemp=220\nM117\nT0\nG1 X10 Y10\n"


class FakeServer:
    """Stands in for matplace: hands out queued commands once, remembers everything it was told."""

    def __init__(self):
        self.queue, self.reports, self.results, self.snapshots = [], [], [], []
        self.down = False

    async def open(self): ...
    async def close(self): ...

    async def sync(self, version, printers):
        if self.down:
            raise ServerError("server unreachable")
        self.reports.append(printers)
        commands, self.queue = self.queue, []
        return {"commands": commands, "printers": [p["key"] for p in printers]}

    async def command_result(self, command_id, ok, message=""):
        self.results.append((command_id, ok, message))

    async def snapshot(self, key, jpeg, job_id):
        self.snapshots.append((key, len(jpeg), job_id))

    async def download_gcode(self, url, target):
        os.makedirs(os.path.dirname(target), exist_ok=True)
        with open(target, "wb") as fh:
            fh.write(GCODE)
        return hashlib.sha256(GCODE).hexdigest()

    def jobs(self, key="p1"):
        return [p["job"] for round_ in self.reports for p in round_ if p["key"] == key and p["job"]]


def start_cmd(cmd_id=1, job_id=7, printer="p1"):
    return {"id": cmd_id, "printer": printer, "type": "start", "job_id": job_id,
            "payload": {"filename": "matplace-F26-000001.gcode", "slot": 0, "gcode_url": "https://x/api/agent/jobs/7/gcode"}}


class AgentTest(unittest.IsolatedAsyncioTestCase):
    def agent(self, **mock):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        options = {"print_seconds": 0.3, "heat_seconds": 0.05} | mock
        config = Config(server="https://x", token="t", work_dir=self.tmp.name,
                        printers=[PrinterConfig("p1", "mock", options), PrinterConfig("p2", "mock", dict(options))])
        self.server = FakeServer()
        return Agent(config, self.server)

    async def rounds(self, agent, n, pause=0.06):
        for _ in range(n):
            try:
                await agent.sync_once()
            except ServerError:
                pass
            await asyncio.sleep(pause)
            await asyncio.gather(*agent._tasks, return_exceptions=True)

    async def test_nothing_happens_without_a_start_command(self):
        agent = self.agent()
        await self.rounds(agent, 3)
        self.assertEqual({p["state"] for r in self.server.reports for p in r}, {"idle"})
        self.assertEqual(self.server.jobs(), [])

    async def test_start_print_progress_and_done_with_measured_values(self):
        agent = self.agent()
        self.server.queue.append(start_cmd())
        await self.rounds(agent, 12)

        self.assertEqual(self.server.results, [(1, True, "")])
        statuses = [j["status"] for j in self.server.jobs()]
        self.assertIn("printing", statuses)
        self.assertEqual(statuses[-1], "done")
        self.assertEqual(statuses.count("done"), 1, "a finished job is reported once, then forgotten")
        done = self.server.jobs()[-1]
        self.assertEqual((done["id"], done["progress"]), (7, 100.0))
        self.assertGreater(done["filament_used"], 3000)
        self.assertEqual(self.server.jobs("p2"), [], "the other printer of the same agent is untouched")
        self.assertIsNone(agent.workers["p1"].tracked)
        self.assertEqual(os.listdir(os.path.join(self.tmp.name, "p1")), [], "the G-code is removed after the print")

    async def test_pause_resume_cancel(self):
        agent = self.agent(print_seconds=5)
        self.server.queue.append(start_cmd())
        await self.rounds(agent, 3)
        self.server.queue.append({"id": 2, "printer": "p1", "type": "pause", "job_id": 7, "payload": {}})
        await self.rounds(agent, 2)
        self.assertEqual(self.server.jobs()[-1]["status"], "paused")
        self.server.queue.append({"id": 3, "printer": "p1", "type": "resume", "job_id": 7, "payload": {}})
        await self.rounds(agent, 2)
        self.assertEqual(self.server.jobs()[-1]["status"], "printing")
        self.server.queue.append({"id": 4, "printer": "p1", "type": "cancel", "job_id": 7, "payload": {}})
        await self.rounds(agent, 3)
        self.assertEqual(self.server.jobs()[-1]["status"], "cancelled")
        self.assertEqual([r[:2] for r in self.server.results], [(1, True), (2, True), (3, True), (4, True)])

    async def test_light_is_on_while_printing_and_switchable_by_command(self):
        agent = self.agent(print_seconds=5)
        driver = agent.workers["p1"].driver
        self.server.queue.append({"id": 1, "printer": "p1", "type": "light", "job_id": None, "payload": {"on": True}})
        await self.rounds(agent, 2)
        self.assertTrue(driver.light_on)
        self.server.queue.append({"id": 2, "printer": "p1", "type": "light", "job_id": None, "payload": {"on": False}})
        await self.rounds(agent, 2)
        self.assertFalse(driver.light_on)
        self.server.queue.append(start_cmd(cmd_id=3))
        await self.rounds(agent, 3)
        self.assertTrue(driver.light_on, "the camera needs light during the print")
        self.server.queue.append({"id": 4, "printer": "p1", "type": "cancel", "job_id": 7, "payload": {}})
        await self.rounds(agent, 3)
        self.assertFalse(driver.light_on, "switched off once the job left the printer")
        self.assertEqual([r[:2] for r in self.server.results], [(1, True), (2, True), (3, True), (4, True)])

    async def test_failed_print_is_reported_with_the_printers_message(self):
        agent = self.agent(fail_at=30)
        self.server.queue.append(start_cmd())
        await self.rounds(agent, 10)
        last = self.server.jobs()[-1]
        self.assertEqual(last["status"], "failed")
        self.assertIn("simulated failure", last["message"])

    async def test_a_busy_printer_refuses_a_second_start_and_says_why(self):
        agent = self.agent(print_seconds=5)
        self.server.queue.append(start_cmd())
        await self.rounds(agent, 2)
        self.server.queue.append(start_cmd(cmd_id=2, job_id=8))
        await self.rounds(agent, 2)
        self.assertEqual(self.server.results[1][:2], (2, False))
        self.assertIn("still running", self.server.results[1][2])
        self.assertEqual(agent.workers["p1"].tracked.job_id, 7)

    async def test_server_outage_loses_nothing_the_final_state_arrives_afterwards(self):
        agent = self.agent()
        self.server.queue.append(start_cmd())
        await self.rounds(agent, 2)
        self.server.down = True
        await self.rounds(agent, 10)               # the print finishes while matplace is unreachable
        self.assertNotIn("done", [j["status"] for j in self.server.jobs()])
        self.server.down = False
        await self.rounds(agent, 2)
        self.assertEqual(self.server.jobs()[-1]["status"], "done")

    async def test_restart_in_the_middle_of_a_print_keeps_reporting_the_job(self):
        agent = self.agent(print_seconds=5)
        self.server.queue.append(start_cmd())
        await self.rounds(agent, 3)

        again = Agent(agent.config, self.server)
        again._load_state()
        self.assertEqual(again.workers["p1"].tracked.job_id, 7)
        self.assertTrue(again.workers["p1"].tracked.seen_printing)

    async def test_a_print_the_printer_forgot_is_given_up_as_failed_not_reported_done(self):
        agent = self.agent(print_seconds=5)
        self.server.queue.append(start_cmd())
        await self.rounds(agent, 2)
        agent.workers["p1"].driver._file = "something-else.gcode"      # somebody started another file at the printer
        await self.rounds(agent, LOST_AFTER_POLLS + 1, pause=0.01)
        self.assertEqual(self.server.jobs()[-1]["status"], "failed")

    async def test_snapshot_is_a_jpeg(self):
        jpeg = await create("p", "mock", {}).snapshot()
        self.assertEqual(jpeg[:3], b"\xff\xd8\xff")

    async def test_mock_refuses_nonsense(self):
        d = create("p", "mock", {})
        with self.assertRaises(DriverError):
            await d.pause()
        with self.assertRaises(ValueError):
            create("p", "octoprint", {})


class ConfigTest(unittest.TestCase):
    def write(self, text):
        f = tempfile.NamedTemporaryFile("w", suffix=".yaml", delete=False, encoding="utf-8")
        f.write(text)
        f.close()
        self.addCleanup(os.remove, f.name)
        return f.name

    def test_loads_printers_and_driver_options(self):
        c = load(self.write("server: https://beta.matplace.com/\ntoken: mpa_x\nprinters:\n  - key: kobra-s1-01\n    driver: moonraker\n    url: http://192.168.8.21:7125\n"))
        self.assertEqual(c.server, "https://beta.matplace.com")
        self.assertEqual(c.printers[0].options, {"url": "http://192.168.8.21:7125"})

    def test_plain_http_to_the_internet_is_refused(self):
        with self.assertRaises(ValueError):
            load(self.write("server: http://beta.matplace.com\ntoken: x\nprinters: [{key: a, driver: mock}]\n"))

    def test_duplicate_printer_keys_are_refused(self):
        with self.assertRaises(ValueError):
            load(self.write("server: https://x\ntoken: x\nprinters: [{key: a, driver: mock}, {key: a, driver: mock}]\n"))


if __name__ == "__main__":
    unittest.main()


class MoonrakerDryerTelemetry(unittest.IsolatedAsyncioTestCase):
    """The ACE dryer line comes from Rinkhals' filament_hub object; the mmu_machine mirror (zeros while drying on a Kobra S1) is the fallback."""

    @staticmethod
    def _driver(status: dict):
        from farm_agent.drivers.moonraker import MoonrakerDriver

        d = MoonrakerDriver("s1", {"url": "http://printer:7125"})

        async def fake_get(path, **kw):
            return {"result": {"status": status}}

        d._get = fake_get  # type: ignore[method-assign]
        return d

    async def test_live_values_from_filament_hub_win_over_the_mirror(self):
        st = await self._driver({
            "print_stats": {"state": "standby"},
            "filament_hub": {"filament_hubs": [{"temp": 50, "humidity": 0, "dryer_status": {"status": "drying", "target_temp": 50, "duration": 360, "remain_time": 10405}}]},
            "mmu_machine": {"unit_0": {"dryer_status": "drying", "dryer_temp": 0, "dryer_target_temp": 50, "dryer_remaining": 0, "dryer_humidity": 0}},
        }).status()
        t = st.telemetry
        self.assertEqual(("drying", 50.0, 50.0, 173, 0), (t["dryer_state"], t["dryer_temp"], t["dryer_target"], t["dryer_remain_min"], t["dryer_rh"]))
        self.assertNotIn("dryer", t)

    async def test_mirror_alone_and_a_stopped_dryer(self):
        st = await self._driver({
            "print_stats": {"state": "standby"},
            "mmu_machine": {"unit_0": {"dryer_status": "stop", "dryer_temp": 27, "dryer_target_temp": 0, "dryer_remaining": 0, "dryer_humidity": 35}},
        }).status()
        t = st.telemetry
        self.assertEqual(("off", 27.0, 0, 0, 35.0), (t["dryer_state"], t["dryer_temp"], t["dryer_target"], t["dryer_remain_min"], t["dryer_rh"]))

    async def test_no_ace_no_dryer_keys(self):
        st = await self._driver({"print_stats": {"state": "standby"}, "filament_hub": {"filament_hubs": []}}).status()
        self.assertFalse([k for k in st.telemetry if k.startswith("dryer")])
