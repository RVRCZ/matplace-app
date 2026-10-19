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
        self.p.links.append(self)
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
        if (kind, action) == ("multiColorBox", "setDry"):
            # as chrisfore captured it: the box named by id takes drying_status; the report says success
            self.p.sent.append((kind, action, data))
            for wanted in (data or {}).get("multi_color_box") or []:
                box = next((b for b in self.p.boxes if b.get("id") == wanted.get("id")), None)
                if box is None:
                    return {"type": kind, "state": "failed", "code": 10112, "msg": "参数设定失败", "data": None}
                status = dict(wanted.get("drying_status") or {})
                box["drying_status"] = {"status": status.get("status", 0), "target_temp": status.get("target_temp", 0),
                                        "duration": status.get("duration", 0), "remain_time": status.get("duration", 0)}
            return {"type": kind, "action": action, "state": "success", "code": 200, "msg": "done", "data": {"multi_color_box": copy.deepcopy(self.p.boxes)}}
        if (kind, action) == ("light", "query"):
            return {"type": kind, "state": "done", "code": 200, "data": {"lights": [{"type": 3, "status": self.p.light, "brightness": 100 if self.p.light else 0}]}}
        return {"type": kind, "state": "done", "code": 200, "data": dict(self.p.info)}

    def publish(self, kind, action, data, *, web=False):
        if not self.connected:
            raise LinkError("not connected")
        self.p.sent.append((kind, action, data))
        info = self.p.info
        if (kind, action) == ("light", "control"):
            self.p.light = int(data.get("status") or 0)
            self.report("light", {"action": "control", "state": "done", "code": 200, "data": {"type": data.get("type"), "status": self.p.light, "brightness": data.get("brightness")}})
            return "msg"
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
        self.light = 0
        self.links = []
        self.opened = 0
        self.sent = []
        self.uploads = []
        self.runner = None

    def report(self, kind, data):
        """A report the printer sends by itself, to every connection."""
        for link in self.links:
            link.report(kind, data)

    def finish(self, print_time: int, supplies_usage: float):
        """The print ran to its end as the Kobra X tells it (18 Oct 2026): progress reports with the minutes and the
        millimetres, cooling reports without a file, a bare {filename, progress 0} a second before "finished", then
        "finished"; the task is gone and last_project still names the earlier print."""
        gone = self.info["project"]
        self.info.update(state="free", project=None)
        for link in self.links:
            link.report("print", {"action": "start", "state": "printing", "code": 200, "data": {"taskid": "-1", "filename": gone["filename"], "progress": 99,
                                                                                               "curr_layer": 50, "total_layers": 50, "print_time": print_time, "supplies_usage": supplies_usage}})
            link.report("print", {"action": "start", "state": "hotbed_cooling_off", "code": 200, "data": {"taskid": "-1"}})
            link.report("print", {"action": "start", "state": "printing", "code": 200, "data": {"taskid": "-1", "filename": gone["filename"], "progress": 0}})
            link.report("print", {"action": "start", "state": "finished", "code": 200, "data": {"taskid": "-1", "filename": gone["filename"], "progress": 100, "remain_time": 0}})

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

    def gcode(self, tool: int = 0, park: bool = False, dwell: int = 1000, sync: str = "", filaments: str = "", tools: tuple = ()) -> str:
        fd, path = tempfile.mkstemp(suffix=".gcode")
        with os.fdopen(fd, "wb") as fh:
            # the start of a farm G-code after GcodeSlot (kx-cube10.gcode, 18 Oct 2026); with the time-lapse the server
            # puts its park line first (kx-cube10-park.gcode); a plate of several filaments carries the farm's line
            # "; matplace filaments T0=<slot> T1=<slot>" and selects the spools as T<slot> in the middle of the print
            # (matplace-two-filaments-T0slot2-T1slot5.gcode, 19 Oct 2026; `tools` overrides the slots it selects)
            if park:
                fh.write(f"; matplace timelapse park_x=250 park_y=250 dwell={dwell} travel=200 frames=49{' sync=' + sync if sync else ''}\n".encode())
            fh.write(f"; HEADER_BLOCK_START\n; generated by OrcaSlicer 2.4.0-beta\n; total layer number: 50\n".encode())
            if filaments:
                slots = tools or tuple(int(v.split("=")[1]) for v in filaments.split())
                a, b = slots[0], slots[1] if len(slots) > 1 else slots[0]
                fh.write(f"; matplace filaments {filaments}\n; HEADER_BLOCK_END\n\nG9111 bedTemp=55 extruderTemp=225\nM109 S225\nT{a} ; matplace slot for filament 0\nG1 X10 Y10\n"
                         f"T{b} ; matplace slot for filament 1\nG1 X20 Y20\nT{a}\nG1 X30 Y30\n; filament used [mm] = 212.48, 30.00\n; filament used [g] = 0.63, 0.09\n"
                         f"; total filament used [g] = 0.72\n; CONFIG_BLOCK_START\n; filament_colour = #F2754E;#003594\n; filament_ids = GFPLA;GFPLA\n"
                         f"; filament_type = PLA;PLA\n; CONFIG_BLOCK_END\n".encode())
            else:
                fh.write((f"; HEADER_BLOCK_END\n\nG9111 bedTemp=55 extruderTemp=225\nM109 S225\nT{tool} ; slot chosen by matplace farm\nG1 X10 Y10\n"
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

    async def test_a_plate_of_several_filaments_maps_every_paint_to_its_spool_and_keeps_the_tool_changes(self):
        async with FakePrinter() as p:
            d = await self.driver(p)
            # T0 from slot 2 (Rack 3), T1 from slot 5 (ACE 3): the file's T2/T5 become T0/T1, each paint maps to its
            # position; the paints keep the file's colours (as the Slicer Next sends them), the spools' colours are the ams_color
            await d.start(self.gcode(filaments="T0=2 T1=5"), "mp30-badge.gcode", 2)
            data = p.sent[-1][2]
            self.assertEqual([{"paint_index": 0, "ams_index": 2, "paint_color": [242, 117, 78, 255], "ams_color": [33, 39, 33, 255], "material_type": "PLA"},
                              {"paint_index": 1, "ams_index": 6, "paint_color": [0, 53, 148, 255], "ams_color": [101, 137, 70, 255], "material_type": "PLA"}],
                             data["ams_settings"]["ams_box_mapping"])
            with zipfile.ZipFile(io.BytesIO(p.uploads[-1][2])) as z:
                gcode = z.read("Metadata/plate_1.gcode")
                info = z.read("Metadata/slice_info.config").decode()
            self.assertIn(b"\nT0 ; matplace slot for filament 0\nG1 X10 Y10\nT1 ; matplace slot for filament 1\nG1 X20 Y20\nT0\nG1 X30 Y30\n", gcode)
            self.assertNotIn(b"\nT2", gcode)
            self.assertNotIn(b"\nT5", gcode)
            self.assertIn(b'; paint_info = [{"material_type":"PLA","paint_color":[242,117,78],"paint_index":0},{"material_type":"PLA","paint_color":[0,53,148],"paint_index":1}]\n; HEADER_BLOCK_END', gcode)
            self.assertIn('<filament id="1" tray_info_idx="GFPLA" type="PLA Silk" color="#F2754E" used_m="0.21" used_g="0.63" />', info)
            self.assertIn('<filament id="2" tray_info_idx="GFPLA" type="PLA Metal" color="#003594" used_m="0.03" used_g="0.09" />', info)
            # the spool in the head changes in the middle of the print: told as before, and the feeding state as spool_change
            p.boxes[1]["loaded_slot"] = 2
            p.info["project"]["state"] = "ace_filament_feeding"
            s = await d.status()
            self.assertEqual((PRINTING, "ACE 3", 5, "ace_filament_feeding"), (s.state, s.telemetry["spool"], s.telemetry["slot"], s.telemetry["spool_change"]))
            await d.cancel()
            # refused before anything goes up: a gap in the paints, two paints from one slot, a slot the file selects
            # but the line does not map, a slot the printer lacks
            for line, tools, why in (("T0=2 T2=5", (), r"the filament line must name T0\.\.T1, it names T0 T2"),
                                     ("T0=2 T1=2", (), r"gives two paints the same slot \(T0=2 T1=2\)"),
                                     ("T0=2", (2, 5), r"selects T5, which the filament line does not map to a paint \(it maps slots 2\)"),
                                     ("T0=2 T1=9", (), r"T1: slot 10: the printer offers 7 spool positions")):
                uploads = len(p.uploads)
                with self.assertRaisesRegex(DriverError, why, msg=line):
                    await d.start(self.gcode(filaments=line, tools=tools), "mp31-badge.gcode", 2)
                self.assertEqual(uploads, len(p.uploads), line)

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

    async def test_a_finished_print_is_told_from_the_printers_report_not_from_last_project(self):
        async with FakePrinter() as p:
            d = await self.driver(p)
            await d.start(self.gcode(tool=1), "mp15-cube.gcode", 1)
            p.finish(print_time=5, supplies_usage=403)
            s = await d.status()
            self.assertEqual((IDLE, JOB_DONE, "mp15-cube.gcode", 100.0, 300, 403.0),
                             (s.state, s.job.status, s.job.filename, s.job.progress, s.job.print_duration, s.job.filament_used))
            self.assertEqual("old part_PLA_0.2_5h51m15s.gcode", p.info["last_project"]["filename"], "the printer's last_project never learns of it")
            self.assertEqual(s.job.status, (await d.status()).job.status, "and it stays told until the next start")

    async def test_the_light_is_switched_as_the_printer_itself_does_and_its_state_is_told(self):
        async with FakePrinter() as p:
            d = await self.driver(p)
            self.assertEqual("off", (await d.status()).telemetry["light"])
            await d.light(True)
            self.assertEqual(("light", "control", {"type": 3, "status": 1, "brightness": 100}), p.sent[-1])
            self.assertEqual("on", (await d.status()).telemetry["light"])
            await d.light(False)
            self.assertEqual({"type": 3, "status": 0, "brightness": 0}, p.sent[-1][2])
            self.assertEqual("off", (await d.status()).telemetry["light"])
            d2 = await self.driver(p, light=False)
            await d2.light(True)
            self.assertEqual("control", p.sent[-1][1], "light: false sends nothing")
            self.assertEqual(0, p.light)

    async def test_the_ace_dryer_is_started_and_stopped_and_shown_with_the_dashboards_keys(self):
        async with FakePrinter() as p:
            d = await self.driver(p)
            t = (await d.status()).telemetry
            self.assertEqual(("off", 28.0, 0, 0, 38.0), (t["dryer_state"], t["dryer_temp"], t["dryer_target"], t["dryer_remain_min"], t["dryer_rh"]))
            await d.dry(True, 45, 10)
            self.assertEqual(("multiColorBox", "setDry", {"multi_color_box": [{"id": 0, "drying_status": {"status": 1, "target_temp": 45, "duration": 10}}]}), p.sent[-1])
            t = (await d.status()).telemetry
            self.assertEqual(("drying", 45, 10), (t["dryer_state"], t["dryer_target"], t["dryer_remain_min"]))
            await d.dry(False)
            self.assertEqual({"multi_color_box": [{"id": 0, "drying_status": {"status": 0}}]}, p.sent[-1][2])
            self.assertEqual("off", (await d.status()).telemetry["dryer_state"])
            # the rack alone has no dryer
            p.boxes = [p.boxes[0]]
            self.assertNotIn("dryer_state", (await d.status()).telemetry)
            with self.assertRaisesRegex(DriverError, "no ACE"):
                await d.dry(True)

    async def test_the_head_is_reported_at_the_park_in_the_sure_window_after_a_reported_layer(self):
        # the measured timing, scaled down ten times: the park is reached 0.22-0.40 s after the report
        async with FakePrinter() as p:
            d = await self.driver(p, park_lead_min=0.22, park_lead=0.40, park_window_min=0.05)
            await d.start(self.gcode(tool=1), "mp16-cube.gcode", 1)
            self.assertIsNone(await d.head(), "no park line in the G-code: nothing to say")
            await d.cancel()
            await d.start(self.gcode(tool=1, park=True, dwell=3000), "mp17-cube.gcode", 1)
            d._park = (d._park[0], d._park[1], 0.3, d._park[3], "")      # dwell 3 s, scaled like the lead
            self.assertIsNone(await d.head(), "no layer reported yet")
            # the printer's progress reports carry the layer; the first one only says where the print is
            p.report("print", {"action": "start", "state": "printing", "code": 200, "data": {"filename": "mp17-cube.gcode", "progress": 2, "curr_layer": 1}})
            self.assertIsNone(await d.head())
            p.report("print", {"action": "start", "state": "printing", "code": 200, "data": {"filename": "mp17-cube.gcode", "progress": 4, "curr_layer": 2}})
            self.assertIsNone(await d.head(), "a new layer was reported, but the head is still printing the last one")
            await asyncio.sleep(0.45)
            self.assertEqual((250.0, 250.0, 0.0), await d.head(), "in the sure window the park is a real, standing position")
            await asyncio.sleep(0.2)
            self.assertIsNone(await d.head(), "the window is over: the agent sees the head leave")
            # the info poll notes the layer too
            p.info["project"].update(curr_layer=3)
            await d.status()
            await asyncio.sleep(0.45)
            self.assertEqual((250.0, 250.0, 0.0), await d.head())

    def test_the_park_line_may_say_the_server_synced_the_layer_marker(self):
        from farm_agent.drivers.anycubic_lan import _park_of
        self.assertEqual((0.0, 130.0, 3.0, 200.0, "m400"), _park_of(b"; matplace timelapse park_x=0 park_y=130 dwell=3000 travel=200 frames=349 sync=m400\n; HEADER"))
        self.assertEqual((250.0, 250.0, 1.0, 200.0, ""), _park_of(b"; matplace timelapse park_x=250 park_y=250 dwell=1000 travel=200 frames=49\n"))
        self.assertIsNone(_park_of(b"; HEADER_BLOCK_START\n"))

    async def test_with_the_synced_marker_the_park_may_begin_before_the_report_and_the_sure_window_follows(self):
        async with FakePrinter() as p:
            d = await self.driver(p, park_window_min=0.05)
            await d.start(self.gcode(tool=1, park=True, dwell=3000, sync="m400"), "mp19-cube.gcode", 1)
            # with sync=m400 the marker is read with the head standing, the park move takes 1-1.5 s and the report
            # follows the read by 0-2 s: the park begins -1.0 to 1.5 s after the report and ends dwell later. Scaled
            # ten times here (-0.1 / 0.15, dwell 0.4): the sure window is 0.15-0.3 s after the report
            d._park = (d._park[0], d._park[1], 0.4, d._park[3], "m400")
            d.park_lead_min, d.park_lead = -0.1, 0.15
            p.report("print", {"action": "start", "state": "printing", "code": 200, "data": {"filename": "mp19-cube.gcode", "progress": 4, "curr_layer": 2}})
            self.assertIsNone(await d.head())
            await asyncio.sleep(0.2)
            self.assertEqual((250.0, 250.0, 0.0), await d.head())
            await asyncio.sleep(0.15)
            self.assertIsNone(await d.head())

    async def test_the_synced_defaults_put_the_picture_one_and_a_half_to_two_seconds_after_the_report(self):
        async with FakePrinter() as p:
            d = await self.driver(p)
            await d.start(self.gcode(tool=1, park=True, dwell=3000, sync="m400"), "mp19-cube.gcode", 1)
            p.report("print", {"action": "start", "state": "printing", "code": 200, "data": {"filename": "mp19-cube.gcode", "progress": 4, "curr_layer": 2}})
            await asyncio.sleep(1.3)
            self.assertIsNone(await d.head(), "the report may follow the read by 2 s: the head may still be on its way")
            await asyncio.sleep(0.3)
            self.assertEqual((250.0, 250.0, 0.0), await d.head(), "1.5-2.0 s after the report the head stands in the park whatever the delay was")
            await asyncio.sleep(0.6)
            self.assertIsNone(await d.head(), "by 2 s after a report that came late the dwell is over")

    async def test_with_a_short_dwell_the_park_is_a_planned_position_for_a_burst_of_pictures(self):
        async with FakePrinter() as p:
            d = await self.driver(p, park_lead_min=0.22, park_lead=0.40, park_window_min=0.05)
            await d.start(self.gcode(tool=1, park=True), "mp18-cube.gcode", 1)      # dwell 1000 in the park line
            d._park = (d._park[0], d._park[1], 0.1, d._park[3], "")
            p.report("print", {"action": "start", "state": "printing", "code": 200, "data": {"filename": "mp18-cube.gcode", "progress": 4, "curr_layer": 2}})
            self.assertIsNone(await d.head())
            await asyncio.sleep(0.25)
            self.assertEqual((250.0, 250.0, None), await d.head(), "no sure window with 1 s: planned position, the agent takes a burst")
            await asyncio.sleep(0.3)
            self.assertIsNone(await d.head())

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
