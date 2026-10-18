"""
The .gcode.3mf package the stock Anycubic firmware (Kobra X in LAN mode) wants a print to arrive as.

A bare .gcode uploads fine, but the firmware fails to set the task up from it (the "init" report carries a garbage
code, the start is refused with 10301 "device busy, state unknown"; 18 Oct 2026, docs/AM.md). What the Anycubic
Slicer Next 2.0.0.3 uploads was captured and is rebuilt here around our own G-code:

  [Content_Types].xml, _rels/.rels, 3D/3dmodel.model (empty model), Metadata/model_settings.config (+ its rels),
  Metadata/custom_gcode_per_layer.xml                              - fixed text, as the Slicer writes it
  Metadata/plate_1.gcode                                           - our G-code with the lines the firmware reads:
        "; paint_info = [...]" in the header and in a trailing "ams_info" block (the file's filaments),
        a trailing "statistics" block (model_size is where the printer takes the dimensions from)
  Metadata/plate_1.gcode.md5                                       - upper-case MD5 of that G-code
  Metadata/plate_1.gcode.metadata                                  - the G-code without the toolpath (head + tail)
  Metadata/plate_1.json, Metadata/slice_info.config                - bounding box, filaments, time, weight
  Metadata/plate_1.png, plate_1_small.png, plate_no_light_1.png, top_1.png, pick_1.png   - thumbnails (plain colour)

The spool is chosen by the start command's ams_box_mapping (paint -> position), the firmware's own mechanism, so the
G-code's T<n> (matplace's GcodeSlot writes the spool position there, docs/V.md) is turned into T0 = paint 0 and the
position it named goes into the mapping. Values the metadata needs come from Orca's own comment lines.
"""
from __future__ import annotations

import hashlib
import io
import json
import re
import struct
import zipfile
import zlib
from dataclasses import dataclass

CONTENT_TYPES = b"""<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
 <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
 <Default Extension="model" ContentType="application/vnd.ms-package.3dmanufacturing-3dmodel+xml"/>
 <Default Extension="png" ContentType="image/png"/>
 <Default Extension="gcode" ContentType="text/x.gcode"/>
</Types>
"""

RELS = b"""<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
 <Relationship Target="/3D/3dmodel.model" Id="rel-1" Type="http://schemas.microsoft.com/3dmanufacturing/2013/01/3dmodel"/>
 <Relationship Target="/Metadata/plate_1.png" Id="rel-2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail"/>
 <Relationship Target="/Metadata/plate_1.png" Id="rel-4" Type="http://schemas.bambulab.com/package/2021/cover-thumbnail-middle"/>
 <Relationship Target="/Metadata/plate_1_small.png" Id="rel-5" Type="http://schemas.bambulab.com/package/2021/cover-thumbnail-small"/>
</Relationships>
"""

MODEL = b"""<?xml version="1.0" encoding="UTF-8"?>
<model unit="millimeter" xml:lang="en-US" xmlns="http://schemas.microsoft.com/3dmanufacturing/core/2015/02" xmlns:BambuStudio="http://schemas.bambulab.com/package/2021" xmlns:p="http://schemas.microsoft.com/3dmanufacturing/production/2015/06" requiredextensions="p">
 <metadata name="Application">BambuStudio-2.0.0.3</metadata>
 <metadata name="BambuStudio:3mfVersion">1</metadata>
 <metadata name="Copyright"></metadata>
 <metadata name="CreationDate"></metadata>
 <metadata name="Description"></metadata>
 <metadata name="Designer"></metadata>
 <metadata name="DesignerCover"></metadata>
 <metadata name="DesignerUserId"></metadata>
 <metadata name="License"></metadata>
 <metadata name="ModificationDate"></metadata>
 <metadata name="Origin"></metadata>
 <metadata name="Title"></metadata>
 <resources>
 </resources>
 <build/>
</model>
"""

MODEL_SETTINGS = b"""<?xml version="1.0" encoding="UTF-8"?>
<config>
  <plate>
    <metadata key="plater_id" value="1"/>
    <metadata key="plater_name" value=""/>
    <metadata key="locked" value="false"/>
    <metadata key="filament_map_mode" value="Auto For Flush"/>
    <metadata key="filament_maps" value="1 1 1 1 1"/>
    <metadata key="gcode_file" value="Metadata/plate_1.gcode"/>
    <metadata key="thumbnail_file" value="Metadata/plate_1.png"/>
    <metadata key="thumbnail_no_light_file" value="Metadata/plate_no_light_1.png"/>
    <metadata key="top_file" value="Metadata/top_1.png"/>
    <metadata key="pick_file" value="Metadata/pick_1.png"/>
    <metadata key="pattern_bbox_file" value="Metadata/plate_1.json"/>
  </plate>
</config>
"""

MODEL_SETTINGS_RELS = b"""<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
 <Relationship Target="/Metadata/plate_1.gcode" Id="rel-1" Type="http://schemas.bambulab.com/package/2021/gcode"/>
</Relationships>
"""

CUSTOM_GCODE = b"""<?xml version="1.0" encoding="utf-8"?>
<custom_gcodes_per_layer>
<plate>
<plate_info id="1"/>
<mode value="MultiExtruder"/>
</plate>
</custom_gcodes_per_layer>
"""

# the slicer's plate names for Orca's curr_bed_type
BED_TYPES = {"Textured PEI Plate": "textured_plate", "Smooth PEI Plate / High Temp Plate": "hot_plate",
             "Smooth Cool Plate": "cool_plate", "Engineering Plate": "eng_plate", "Textured Cool Plate": "supertack_plate"}


@dataclass
class Facts:
    """What the metadata says about the print, read from Orca's comment lines (defaults where a line is missing)."""
    layers: int = 0
    seconds: int = 0
    time_text: str = ""
    grams: float = 0.0
    metres: float = 0.0
    bbox: tuple = (0.0, 0.0, 0.0, 0.0)       # x min, y min, x max, y max of the first layer
    height: float = 0.0
    layer_height: float = 0.2
    nozzle: float = 0.4
    printer_model: str = "Anycubic Kobra X"
    bed_type: str = "textured_plate"
    support: bool = False
    object_name: str = "model"
    filament_id: str = "GFPLA"
    tool: int = 0                              # the T<n> the G-code selects


def facts_of(raw: bytes) -> Facts:
    f = Facts()
    f.layers = int(_header(raw, b"total layer number") or config_value(raw, b"total layers count") or 0)
    f.time_text = config_value(raw, b"estimated printing time (normal mode)")
    f.seconds = _seconds(f.time_text)
    f.grams = _float(config_value(raw, b"total filament used [g]") or config_value(raw, b"filament used [g]"))
    f.metres = _float(config_value(raw, b"filament used [mm]")) / 1000.0
    lo, hi = _pair(config_value(raw, b"first_layer_print_min")), _pair(config_value(raw, b"first_layer_print_max"))
    f.bbox = (lo[0], lo[1], hi[0], hi[1])
    f.height = _float(_header(raw, b"max_z_height"))
    f.layer_height = _float(config_value(raw, b"layer_height")) or 0.2
    f.nozzle = _float(config_value(raw, b"nozzle_diameter")) or 0.4
    f.printer_model = config_value(raw, b"printer_model") or f.printer_model
    f.bed_type = BED_TYPES.get(config_value(raw, b"curr_bed_type"), "textured_plate")
    f.support = config_value(raw, b"enable_support") == "1"
    m = re.search(rb"^EXCLUDE_OBJECT_DEFINE NAME=(\S+)", raw, re.M)
    if m:
        f.object_name = re.sub(r"_id_\d+_copy_\d+$", "", m.group(1).decode("utf-8", "replace"))
    f.filament_id = config_value(raw, b"filament_ids") or f.filament_id
    f.tool = tool_of(raw)
    return f


def tool_of(raw: bytes) -> int:
    """The tool the G-code selects (GcodeSlot writes "T<n>" after the start macro); 0 when it selects none."""
    for i, line in enumerate(raw.split(b"\n", 400)):
        m = re.match(rb"\s*T(\d+)\b", line)
        if m:
            return int(m.group(1))
        if i >= 400:
            break
    return 0


def package(raw: bytes, colour: str, material: str) -> tuple[bytes, Facts]:
    """The .gcode.3mf bytes for this G-code, printed in `colour` (#rrggbb) and `material` (PLA...) as paint 0."""
    facts = facts_of(raw)
    gcode = prepared_gcode(raw, colour, material, facts)
    rgb = rgb_of(colour)
    big, small = _png(512, 512, rgb), _png(128, 128, rgb)
    md5 = hashlib.md5(gcode).hexdigest().upper().encode()  # noqa: S324 - the printer's checksum
    x0, y0, x1, y1 = facts.bbox
    plate = {"bbox_all": [x0, y0, x1, y1],
             "bbox_objects": [{"area": round((x1 - x0) * (y1 - y0), 1), "bbox": [x0, y0, x1, y1], "id": 1, "layer_height": facts.layer_height, "name": facts.object_name}],
             "bed_type": facts.bed_type, "filament_colors": [colour], "filament_ids": [0], "first_extruder": 0, "is_seq_print": False,
             "nozzle_diameter": facts.nozzle, "version": 2}
    slice_info = (
        '<?xml version="1.0" encoding="UTF-8"?>\n<config>\n  <header>\n'
        '    <header_item key="X-ACNext-Client-Type" value="slicer"/>\n'
        '    <header_item key="X-ACNext-Client-Version" value="2.0.0.3 20260904033713"/>\n  </header>\n  <plate>\n'
        '    <metadata key="index" value="1"/>\n'
        f'    <metadata key="printer_model_id" value="{_xml(facts.printer_model)}"/>\n'
        f'    <metadata key="nozzle_diameters" value="{facts.nozzle:g}"/>\n'
        '    <metadata key="timelapse_type" value="0"/>\n'
        f'    <metadata key="prediction" value="{facts.seconds}"/>\n'
        f'    <metadata key="weight" value="{facts.grams:.2f}"/>\n'
        '    <metadata key="outside" value="false"/>\n'
        f'    <metadata key="support_used" value="{"true" if facts.support else "false"}"/>\n'
        '    <metadata key="label_object_enabled" value="false"/>\n'
        '    <metadata key="filament_maps" value="1 1 1 1 1"/>\n'
        f'    <object identify_id="1" name="{_xml(facts.object_name)}" skipped="false" />\n'
        f'    <filament id="1" tray_info_idx="{_xml(facts.filament_id)}" type="{_xml(material)}" color="{colour}" used_m="{facts.metres:.2f}" used_g="{facts.grams:.2f}" />\n'
        '  </plate>\n</config>\n'
    ).encode()
    buf = io.BytesIO()
    with zipfile.ZipFile(buf, "w", zipfile.ZIP_DEFLATED) as z:
        z.writestr("[Content_Types].xml", CONTENT_TYPES)
        z.writestr("Metadata/plate_1.png", big)
        z.writestr("Metadata/plate_1_small.png", small)
        z.writestr("Metadata/plate_no_light_1.png", big)
        z.writestr("Metadata/top_1.png", big)
        z.writestr("Metadata/pick_1.png", big)
        z.writestr("Metadata/plate_1.json", json.dumps(plate, separators=(",", ":")))
        z.writestr("3D/3dmodel.model", MODEL)
        z.writestr("Metadata/custom_gcode_per_layer.xml", CUSTOM_GCODE)
        z.writestr("Metadata/plate_1.gcode.md5", md5)
        z.writestr("Metadata/plate_1.gcode.metadata", metadata_of(gcode))
        z.writestr("Metadata/plate_1.gcode", gcode)
        z.writestr("Metadata/_rels/model_settings.config.rels", MODEL_SETTINGS_RELS)
        z.writestr("Metadata/model_settings.config", MODEL_SETTINGS)
        z.writestr("Metadata/slice_info.config", slice_info)
        z.writestr("_rels/.rels", RELS)
    return buf.getvalue(), facts


def prepared_gcode(raw: bytes, colour: str, material: str, facts: Facts | None = None) -> bytes:
    """The G-code as it goes into the package: T<n> -> T0, the paint_info header line, the ams_info and statistics
    blocks at the end (each only when the file has none)."""
    facts = facts or facts_of(raw)
    out = raw
    if facts.tool:
        out = re.sub(rb"^(\s*)T\d+\b", rb"\1T0", out, count=1, flags=re.M)
    info = json.dumps([{"material_type": material.split()[0].upper(), "paint_color": rgb_of(colour), "paint_index": 0}], separators=(",", ":")).encode()
    if b"; paint_info" not in out:
        line = b"; paint_info = " + info + b"\n"
        end = out.find(b"; HEADER_BLOCK_END")
        out = out[:end] + line + out[end:] if end > 0 else b"; HEADER_BLOCK_START\n" + line + b"; HEADER_BLOCK_END\n" + out
    if b"; statistics = begin" not in out:
        x0, y0, x1, y1 = facts.bbox
        tail = (b"\n\n\n; ams_info = begin\n; paint_info = " + info + b"\n; ams_info = end\n\n; statistics = begin\n"
                + f"; used_filament = {facts.metres:.2f}\n; print_time = {facts.time_text}\n".encode()
                + f"; model_size = {x1 - x0:.2f},{y1 - y0:.2f},{facts.height:.2f}\n; total_layers = {facts.layers}\n; statistics = end\n".encode())
        out = out.rstrip(b"\r\n") + b"\n" + tail
    return out


def metadata_of(gcode: bytes) -> bytes:
    """The G-code without its toolpath: everything before the first layer, then from the filament summary to the end."""
    head_end = gcode.find(b";LAYER_CHANGE")
    tail_start = gcode.find(b"; filament used [mm]")
    if head_end < 0 or tail_start < 0 or tail_start < head_end:
        return gcode
    return gcode[:head_end] + b"\n" + gcode[tail_start:]


# -- Orca's comment lines ----------------------------------------------------------------------------------------
def config_value(raw: bytes, key: bytes) -> str:
    """The first value of "; <key> = a;b;c" (the first filament's), "" when there is no such line."""
    m = re.search(rb"^; " + re.escape(key) + rb" = ([^\r\n]*)", raw, re.M)
    if not m:
        return ""
    return m.group(1).split(b";")[0].strip().strip(b'"').decode("utf-8", "replace")


def _header(raw: bytes, key: bytes) -> str:
    """The value of a header line "; <key>: value"."""
    m = re.search(rb"^; " + re.escape(key) + rb": ([^\r\n]*)", raw[:4000], re.M)
    return m.group(1).strip().decode("utf-8", "replace") if m else ""


def _seconds(text: str) -> int:
    total = 0
    for value, unit in re.findall(r"(\d+)\s*([dhms])", text):
        total += int(value) * {"d": 86400, "h": 3600, "m": 60, "s": 1}[unit]
    return total


def _float(text: str) -> float:
    try:
        return float(text.split(",")[0]) if text else 0.0
    except ValueError:
        return 0.0


def _pair(text: str) -> tuple[float, float]:
    parts = text.split(",")
    return (_float(parts[0]), _float(parts[1])) if len(parts) >= 2 else (0.0, 0.0)


def rgb_of(colour: str) -> list[int]:
    try:
        return [int(colour.lstrip("#")[i:i + 2], 16) for i in (0, 2, 4)]
    except ValueError:
        return [0, 0, 0]


def _xml(text: str) -> str:
    return text.replace("&", "&amp;").replace('"', "&quot;").replace("<", "&lt;")


def _png(width: int, height: int, rgb: list[int]) -> bytes:
    """A plain RGBA picture of one colour with a darker rim (no image library on the farm PC is needed)."""
    inner = bytes(rgb) + b"\xff"
    rim = bytes(max(0, c - 60) for c in rgb) + b"\xff"
    edge = max(4, width // 24)
    rows = bytearray()
    for y in range(height):
        rows += b"\x00"
        if y < edge or y >= height - edge:
            rows += rim * width
        else:
            rows += rim * edge + inner * (width - 2 * edge) + rim * edge

    def chunk(kind: bytes, body: bytes) -> bytes:
        return struct.pack(">I", len(body)) + kind + body + struct.pack(">I", zlib.crc32(kind + body) & 0xFFFFFFFF)

    return (b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", struct.pack(">IIBBBBB", width, height, 8, 6, 0, 0, 0))
            + chunk(b"IDAT", zlib.compress(bytes(rows), 6)) + chunk(b"IEND", b""))
