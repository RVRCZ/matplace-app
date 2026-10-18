"""
The Anycubic LAN driver (Kobra X in LAN mode) against a fake link and a fake upload address - no printer, no network
beyond 127.0.0.1.   Run:  python -m pytest tests   (from agent/)

The fake link answers the `info` query from a dict the test edits (shaped like the Kobra X 2.0.2.2 reports, docs/AM.md)
and acts on the commands the driver publishes: start begins a project, pause and stop change it.
"""
import asyncio
import copy
import os
import sys
import tempfile
import hashlib
import io
import zipfile
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


# multiColorBox/getInfo of the Kobra X on 18 Oct 2026 with an ACE attached: the rack (id -1) lists 4 slots, its index 3
# being the ACE's feed (edit_status 3, 0 %), and the ACE (id 0) its 4
BOXES = [
    {"id": -1, "status": 1, "loaded_slot": -1, "slots": [
        {"index": 0, "type": "PLA Silk", "color": [0, 43, 73], "status": 5, "edit_status": 1}, {"index": 1, "type": "PLA Silk", "color": [255, 127, 50], "status": 5, "edit_status": 1},
        {"index": 2, "type": "PLA Silk", "color": [33, 39, 33], "status": 5, "edit_status": 1}, {"index": 3, "type": "PLA+", "color": [255, 255, 255], "status": 5, "edit_status": 3, "consumables_percent": 0}]},
    {"id": 0, "status": 1, "loaded_slot": -1, "temp": 28, "humidity": 38, "slots": [
        {"index": 0, "type": "PLA Silk", "color": [255, 127, 50], "status": 5}, {"index": 1, "type": "PLA Metal", "color": [0, 53, 148], "status": 5},
        {"index": 2, "type": "PLA Metal", "color": [101, 137, 70], "status": 4}, {"index": 3, "type": "PLA Metal", "color": [242, 169, 0], "status": 5}]},
]


class FakeLink:
    """Stands for KobraLink. `begins`: a start command turns into a running project (False: the printer ignores it)."""

    def __init__(self, printer: "FakePrinter", on_report):
        self.p = printer
        self.on_report = on_report
        self.open = False
        self.reports, self.history, self.seq = {}, {}, {}

    def connect(self):
        self.p.opened += 1
        if self.p.refuse:
            raise LinkError(self.p.refuse)
        self.open = True

    def close(self):
        self.open = False

    def report(self, kind, data):
        self.reports[kind] = data
        self.history.setdefault(kind, []).append(data)
        self.seq[kind] = self.seq.get(kind, 0) + 1
        if self.on_report:
            self.on_report(kind, data)

    def since(self, kind, n):
        return self.history.get(kind, [])[n:]

    def last(self, kind):
        return self.reports.get(kind)

    def count(self, kind):
        return self.seq.get(kind, 0)

    @property
    def connected(self):
        return self.open and not self.p.dropped

    def ask(self, kind, action="query", data=None, *, web=True, timeout=8.0):
        if not self.connected:
            raise LinkError("not connected")
        if (kind, action) == ("multiColorBox", "getInfo"):
            return {"type": kind, "state": "done", "code": 200, "data": {"multi_color_box": copy.deepcopy(self.p.boxes)}}
        return {"type": kind, "state": "done", "code": 200, "data": dict(self.p.info)}

    def publish(self, kind, action, data, *, web=False):
        if not self.connected:
            raise LinkError("not connected")
        self.p.sent.append((kind, action, data))
        info = self.p.info
        if (kind, action) == ("print", "start") and self.p.busy_starts > 0:
            # still reading the uploaded file: refused like on 18 Oct 2026
            self.p.busy_starts -= 1
            self.report("print", {"action": "start", "state": "failed", "code": 10301, "msg": "设备忙,情况未知"})
            return "msg"
        if (kind, action) == ("print", "start"):
            self.report("print", {"action": "start", "state": "checking", "code": 200, "msg": "done"})
        incomplete = not (data.get("ams_settings") or {}).get("ams_box_mapping") or not data.get("url") or not data.get("md5") or not data.get("filesize")
        if (kind, action) == ("print", "start") and incomplete:
            # what the Kobra X did on 18 Oct 2026 to a start without the mapping, or without url/md5/filesize: the
            # task is noted ("init"), nothing heats, the printer stays free, and print/report says 10301
            self.report("print", {"action": "start", "state": "failed", "code": 10301, "msg": "设备忙,情况未知"})
            info.update(state="free", project={"state": "init", "filename": data["filename"], "progress": 0, "pause": 0})
        elif (kind, action) == ("print", "start") and self.p.begins:
            info.update(state="busy", project={"state": "printing", "filename": data["filename"], "progress": 0, "pause": 0,
                                               "curr_layer": 0, "total_layers": 50, "remain_time": 6, "print_time": 0})
        elif (kind, action) == ("print", "pause") and info.get("project"):
            info["project"] = dict(info["project"], pause=1, state="paused")
        elif (kind, action) == ("print", "resume") and info.get("project"):
            info["project"] = dict(info["project"], pause=0, state="printing")
        elif (kind, action) == ("print", "stop") and info.get("project"):
            # as the Kobra X does (18 Oct 2026): the task is gone, last_project stays the earlier finished print,
            # only the stop report names the cancelled file
            gone = info["project"]
            info.update(state="free", project=None)
            self.report("print", {"action": "stop", "state": "stoped", "code": 10601, "msg": "用户发起",
                                  "data": {"taskid": "-1", "filename": gone["filename"], "progress": gone.get("progress", 0), "print_time": 0}})
        return "msg"


class FakePrinter:
    def __init__(self):
        self.info = {k: (dict(v) if isinstance(v, dict) else v) for k, v in FREE.items()}
        self.refuse = None        # LinkError text connect() raises (CLOUD_MODE, "timed out"...)
        self.dropped = False      # the link went down after connecting
        self.begins = True
        self.busy_starts = 0      # how many starts the printer refuses with 10301 (busy reading the file)
        self.boxes = copy.deepcopy(BOXES)
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
        """Like the Kobra X: the name as a plain field first, then the file in "gcode", the size in X-File-Length;
        any other form is refused the way the printer refused it on 18 Oct 2026."""
        form = await request.post()
        names = list(form.keys())
        f = form.get("gcode")
        if names[:2] != ["filename", "gcode"] or not hasattr(f, "file") or not request.headers.get("X-File-Length"):
            return web.json_response({"code": 19005, "message": "Parsing form fail"})
        body = f.file.read()
        if int(request.headers["X-File-Length"]) != len(body) or form["filename"] != f.filename:
            return web.json_response({"code": 19005, "message": "Parsing form fail"})
        self.uploads.append((request.query.get("s"), f.filename, body))
        # the printer reads the file by itself and shows it as a task in "init" (still free)
        self.info.update(project={"state": "init", "filename": f.filename, "progress": 0, "pause": 0})
        return web.json_response({"code": 200, "message": "success"})


class AnycubicLanTest(unittest.IsolatedAsyncioTestCase):
    async def driver(self, printer: FakePrinter, **options):
        d = create("kobra-x-01", "anycubic_lan", {"ip": "192.168.8.44", "_link": printer.link, "start_poll": 0.01,
                                                  "start_wait": 0.5, "retry_seconds": 60, "answer_poll": 0.01,
                                                  "answer_wait": 0.1, "busy_retry": 0.01, **options})
        await d.connect()
        self.addAsyncCleanup(d.close)
        return d

    def gcode(self, tool: int = 0) -> str:
        fd, path = tempfile.mkstemp(suffix=".gcode")
        with os.fdopen(fd, "wb") as fh:
            # the start of a farm G-code after GcodeSlot (kx-cube10.gcode, 18 Oct 2026)
            fh.write((f"; HEADER_BLOCK_START\n; generated by OrcaSlicer 2.4.0-beta\n; total layer number: 50\n; HEADER_BLOCK_END\n\n"
                      f"G9111 bedTemp=55 extruderTemp=225\nM109 S225\nT{tool} ; slot chosen by matplace farm\nG1 X10 Y10\n"
                      f"; CONFIG_BLOCK_START\n; filament_colour = #F2754E\n; filament_type = PLA\n; CONFIG_BLOCK_END\n").encode())
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
            await d.start(self.gcode(tool=1), "mp7-cube.gcode", 1)
            token, name, body = p.uploads[0]
            self.assertEqual(("tok", "mp7-cube.gcode.3mf"), (token, name))
            # the package carries the G-code as paint 0 with the rack spool's colour and material in the lines the
            # firmware reads (anycubic_pkg; the spool itself is chosen by the start's mapping)
            with zipfile.ZipFile(io.BytesIO(body)) as z:
                gcode = z.read("Metadata/plate_1.gcode")
            self.assertIn(b"\nT0 ; slot chosen by matplace farm\n", gcode)
            self.assertIn(b'; paint_info = [{"material_type":"PLA","paint_color":[255,127,50],"paint_index":0}]\n; HEADER_BLOCK_END', gcode)
            kind, action, data = p.sent[-1]
            self.assertEqual(("print", "start", "mp7-cube.gcode", "-1", 1, 1), (kind, action, data["filename"], data["taskid"], data["filetype"], data["project_type"]))
            # the start as the Slicer Next sends it: url, the upload's md5 and size, the mapping T1 -> rack position 1
            self.assertEqual((hashlib.md5(body).hexdigest(), len(body)), (data["md5"], data["filesize"]))
            self.assertTrue(data["url"].startswith("https://"))
            self.assertEqual([{"paint_index": 0, "ams_index": 1, "paint_color": [255, 127, 50, 255], "ams_color": [255, 127, 50, 255], "material_type": "PLA"}],
                             data["ams_settings"]["ams_box_mapping"])
            self.assertTrue(data["ams_settings"]["use_ams"])
            self.assertEqual((1, []), (data["task_settings"]["auto_leveling"], data["task_settings"]["model_objects_skip_parts"]))
            await d.cancel()
            self.assertEqual(("print", "stop"), p.sent[-1][:2])
            s = await d.status()
            # the printer's last_project still names the earlier finished print; the cancelled one is told from the stop report
            self.assertEqual((IDLE, JOB_CANCELLED, "mp7-cube.gcode"), (s.state, s.job.status, s.job.filename))
            self.assertEqual("old part_PLA_0.2_5h51m15s.gcode", p.info["last_project"]["filename"])
            # until the next start, which forgets it
            await d.start(self.gcode(tool=1), "mp8-cube.gcode", 1)
            self.assertEqual((PRINTING, "mp8-cube.gcode"), ((s := await d.status()).state, s.job.filename))

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

    async def test_a_start_refused_while_the_printer_reads_the_file_is_sent_again(self):
        async with FakePrinter() as p:
            p.busy_starts = 2
            d = await self.driver(p)
            await d.start(self.gcode(tool=1), "mp10-cube.gcode", 1)
            self.assertEqual(3, sum(1 for k, a, _ in p.sent if (k, a) == ("print", "start")))
            self.assertEqual(PRINTING, (await d.status()).state)

    async def test_a_start_refused_for_good_is_told_with_the_printers_code(self):
        async with FakePrinter() as p:
            p.busy_starts = 5
            d = await self.driver(p, start_attempts=2)
            with self.assertRaisesRegex(DriverError, "10301"):
                await d.start(self.gcode(), "mp11-cube.gcode", 0)

    async def test_the_spool_positions_run_across_the_rack_and_the_ace_as_the_printer_lists_them(self):
        async with FakePrinter() as p:
            d = await self.driver(p)
            s = await d.spools()
            self.assertEqual(2, s.boxes)
            self.assertEqual([("Rack 1", -1, 0, 0), ("Rack 2", -1, 1, 1), ("Rack 3", -1, 2, 2), ("ACE 1", 0, 0, 4), ("ACE 2", 0, 1, 5), ("ACE 3", 0, 2, 6), ("ACE 4", 0, 3, 7)],
                             [(q.name, q.box, q.index, q.ams_index) for q in s.positions])
            self.assertEqual([0, 1, 2, 3, 4, 5, 6], [q.slot for q in s.positions])
            self.assertEqual(("#003594", "PLA Metal", 5), ((q := s.at(4)).colour, q.material, q.status))
            self.assertIsNone(s.loaded)
            # the rack alone: four positions, no ACE
            p.boxes = [p.boxes[0]]
            s = await d.spools()
            self.assertEqual(["Rack 1", "Rack 2", "Rack 3", "Rack 4"], [q.name for q in s.positions])
            self.assertEqual((0, 3), ((q := s.at(3)).ordinal, q.ams_index))
            with self.assertRaisesRegex(DriverError, r"slot 5: the printer offers 4 spool positions \(Rack 1, Rack 2, Rack 3, Rack 4\)"):
                await d.spool_at(4, "#000000", "PLA")

    async def test_a_start_from_the_ace_maps_the_paint_to_the_ace_position(self):
        async with FakePrinter() as p:
            d = await self.driver(p)
            for slot, name, ams_index, colour, material in ((2, "Rack 3", 2, [33, 39, 33, 255], "PLA"), (3, "ACE 1", 4, [255, 127, 50, 255], "PLA"), (6, "ACE 4", 7, [242, 169, 0, 255], "PLA")):
                await d.start(self.gcode(tool=slot), f"mp{slot}-cube.gcode", slot)
                data = p.sent[-1][2]
                self.assertEqual([{"paint_index": 0, "ams_index": ams_index, "paint_color": colour, "ams_color": colour, "material_type": material}],
                                 data["ams_settings"]["ams_box_mapping"], name)
                with zipfile.ZipFile(io.BytesIO(p.uploads[-1][2])) as z:
                    self.assertIn(b"\nT0 ; slot chosen by matplace farm\n", z.read("Metadata/plate_1.gcode"))
                await d.cancel()
            # the ACE's spool in the head is told as the screen names it and as matplace's slot
            await d.start(self.gcode(tool=3), "mp13-cube.gcode", 3)
            p.boxes[1]["loaded_slot"] = 0
            s = await d.status()
            self.assertEqual((PRINTING, "ACE 1", 3), (s.state, s.telemetry["spool"], s.telemetry["slot"]))
            # a slot the printer has not: no upload, a clear refusal
            p.boxes = [p.boxes[0]]
            await d.cancel()
            uploads = len(p.uploads)
            with self.assertRaisesRegex(DriverError, "slot 5: the printer offers 4 spool positions"):
                await d.start(self.gcode(tool=4), "mp14-cube.gcode", 4)
            self.assertEqual(uploads, len(p.uploads))

    async def test_a_stale_task_the_printer_only_noted_is_stopped_before_the_start(self):
        async with FakePrinter() as p:
            p.info.update(state="free", project={"state": "init", "filename": "refused-earlier.gcode", "progress": 0, "pause": 0})
            d = await self.driver(p)
            self.assertEqual(IDLE, (await d.status()).state, "a noted task on a free printer is no print")
            await d.start(self.gcode(tool=1), "mp12-cube.gcode", 1)
            self.assertEqual(("print", "stop"), p.sent[0][:2])
            self.assertEqual(PRINTING, (await d.status()).state)

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
