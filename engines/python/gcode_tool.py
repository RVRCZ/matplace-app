#!/usr/bin/env python3
"""
A model out of a G-code file (manifold3d, exact mm): the cast of a print, layer by layer, for people who have only the
G-code of a thing and want to print it again, price it or order it.

    gcode_tool.py gcode  <in.gcode> <out.stl> <params-json | @file> [<parts-dir>]
    gcode_tool.py header <in.gcode>

How the model is made
  Every layer's shape is read from the closed loops of the *outermost* walls (Orca/Bambu/Anycubic "Outer wall" with
  its "Overhang wall" parts, Prusa "External perimeter"/"Overhang perimeter", Cura "WALL-OUTER"): the slicer draws
  one such loop round every contour of the slice, the outline of the thing and the outline of every hole alike, half
  a line width inside the true surface. The loops of a layer are nested into a tree (which loop lies in which); a loop
  with no parent is material, a loop inside material of the same tool is a hole, a loop inside a hole is material
  again (an island), and a loop inside material of *another* tool is material of its own (a letter inlaid on a plate).
  Each tool's region is the union of its material loops less what lies directly inside them, grown by half the line
  width (the outline out, the holes in), and where two tools overlap at an inlay's edge the later tool wins. The
  regions are extruded over the layer's height, equal consecutive layers as one slab, and the slabs of a tool are
  united into one solid. Infill, inner walls, top and bottom skins do not shape the model (they lie inside the outer
  loops); supports, skirt, brim, prime/wipe towers, purge lines and travels are left out.

Colours
  Every loop carries the tool (T<n>) it was printed with; the colours come from the slicer's `filament_colour`
  (Orca, Bambu, Anycubic) or `extruder_colour` (Prusa). One tool: one part `body`. Several tools in one layer: one
  part per tool (`color_1`, `color_2`…), with `part_colors` for the farm's print by parts and a plain 3MF beside the
  parts (one object per colour, colors.3mf). Several tools but never two in one layer: one part `body` with
  `color_changes` by height, the way a one-nozzle printer swaps spools.

What the header gives: the slicer and its version, the printer and the profile, the layer height and the line width,
the printing time, the filament in g and m (in total and per tool).

Limits: a file is read up to `max_lines` lines (1 500 000); a longer one is cut there and the answer says so. Vase
mode (one spiral, no closed loops) comes out as the loop of every layer closed where the spiral passes the height.
Details finer than a line width and the steps of the layers are what the print had; the original design is gone.

One JSON object on stdout; with a parts directory every part is written there as <name>.stl. The file <out>.stage
holds one word while the tool works (parsing, layers, building, done).
"""
import json
import math
import os
import sys
import zipfile

import numpy as np

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from edit_tool import Invalid, finish, out, stage  # noqa: E402

MAX_LINES = 1_500_000
DEFAULT_WIDTH = 0.42
ARC_STEP_DEG = 5.0
# the paths a model's shape is read from (the outermost loop of every contour) and the other walls that continue them
OUTER = {"Outer wall", "External perimeter", "WALL-OUTER"}
WALLISH = OUTER | {"Overhang wall", "Overhang perimeter", "Inner wall", "Perimeter", "WALL-INNER", "Internal perimeter"}
# a palette for a tool whose colour the file does not name
FALLBACK_COLORS = ["#2B2B2B", "#D9D9D9", "#C94714", "#24407A", "#2E7D4F", "#C9A227", "#7B3FA0", "#7F8C8D"]


# ── reading ──────────────────────────────────────────────────────────────────────────────────────────────────────

def _num(word):
    try:
        return float(word[1:])
    except ValueError:
        return None


def _time_s(text):
    """'1d 2h 3m 4s', '2h 3m', '45m 10s' → seconds; None when nothing is a time."""
    total, found = 0, False
    for part in text.replace(",", " ").split():
        if part[-1:] in "dhms" and part[:-1].isdigit():
            total += int(part[:-1]) * {"d": 86400, "h": 3600, "m": 60, "s": 1}[part[-1]]
            found = True
    return total if found else None


def _list(value):
    """A slicer's per-filament setting: values split by ';' (Orca, Prusa) or ',' (Anycubic's filament used)."""
    if value is None:
        return []
    sep = ";" if ";" in value else ","
    return [v.strip().strip('"') for v in value.split(sep)]


def header_of(hdr, flavour_line):
    """What the file says about itself, from the key = value lines of its head and its config block."""
    flavour, slicer = "unknown", ""
    line = (flavour_line or "").strip("; ").strip()
    low = line.lower()
    if "anycubic" in low:
        flavour = "anycubic"
    elif "bambu" in low:
        flavour = "bambu"
    elif "orca" in low:
        flavour = "orca"
    elif "prusa" in low:
        flavour = "prusa"
    elif "cura" in low:
        flavour = "cura"
    elif "superslicer" in low:
        flavour = "prusa"
    if line.lower().startswith("generated by "):
        line = line[13:]
    slicer = line.split(" on ")[0].strip()[:60]
    colours = _list(hdr.get("filament_colour") or hdr.get("extruder_colour") or hdr.get("filament_color"))
    colours = [c if c.startswith("#") and len(c) in (7, 9) else "" for c in colours]
    names = _list(hdr.get("filament_settings_id"))
    types = _list(hdr.get("filament_type"))
    used_g = [float(v) for v in _list(hdr.get("filament used [g]")) if v.replace(".", "", 1).isdigit()]
    used_mm = [float(v) for v in _list(hdr.get("filament used [mm]")) if v.replace(".", "", 1).isdigit()]
    total_g = hdr.get("total filament used [g]")
    try:
        total_g = float(total_g) if total_g is not None else (sum(used_g) if used_g else None)
    except ValueError:
        total_g = sum(used_g) if used_g else None
    time_s = None
    for key in ("estimated printing time (normal mode)", "estimated printing time", "total estimated time", "model printing time", "TIME"):
        if hdr.get(key) is not None:
            time_s = _time_s(hdr[key]) if not hdr[key].strip().isdigit() else int(hdr[key].strip())
            if time_s is not None:
                break

    def num(*keys):
        for k in keys:
            v = hdr.get(k)
            if v is None:
                continue
            try:
                return float(_list(v)[0])
            except (ValueError, IndexError):
                continue
        return None

    return {
        "flavour": flavour, "slicer": slicer,
        "printer": (hdr.get("printer_model") or hdr.get("printer_settings_id") or hdr.get("machine_name") or "").strip('"')[:80],
        "profile": (hdr.get("print_settings_id") or hdr.get("print_profile") or "").strip('"')[:80],
        "layer_height": num("layer_height"),
        "line_width": num("outer_wall_line_width", "external_perimeter_extrusion_width", "line_width", "extrusion_width"),
        "nozzle": num("nozzle_diameter"),
        "colours": colours, "names": names, "types": types, "used_g": used_g, "used_mm": used_mm,
        "filament_g": round(total_g, 2) if total_g is not None else None,
        "filament_m": round(sum(used_mm) / 1000, 2) if used_mm else None,
        "time_s": time_s,
    }


def read(path, max_lines=MAX_LINES, progress=None):
    """The outer-wall loops of every layer, the tools they were printed with, and the header. Pure Python over the
    lines: a million lines in a few seconds."""
    hdr, flavour_line = {}, None
    loops = {}                      # z → [(tool, width, [(x, y)…])]
    heights = {}                    # z → layer height said by ;HEIGHT:
    tools_seen = set()
    x = y = z = 0.0
    e = 0.0
    abs_xyz, abs_e = True, True
    tool, typ, width = 0, "", None
    cur, cur_z, cur_tool, cur_width, cur_outer = [], None, 0, None, False
    n, truncated = 0, False
    extrusions, outer_moves = 0, 0

    def end_poly():
        nonlocal cur, cur_outer
        if cur_outer and len(cur) >= 3:
            loops.setdefault(round(cur_z, 3), []).append((cur_tool, cur_width or DEFAULT_WIDTH, cur))
        cur, cur_outer = [], False

    with open(path, "r", encoding="latin-1", newline="") as fh:
        for line in fh:
            n += 1
            if n > max_lines:
                truncated = True
                break
            if progress and n % 200000 == 0:
                progress(n)
            if not line:
                continue
            c = line[0]
            if c == ";":
                if line.startswith(";TYPE:"):
                    typ = line[6:].strip()
                    if typ not in WALLISH:
                        end_poly()
                elif line.startswith(";WIDTH:"):
                    try:
                        width = float(line[7:].strip())
                    except ValueError:
                        width = None
                elif line.startswith(";LAYER_CHANGE") or line.startswith(";LAYER:"):
                    end_poly()
                elif line.startswith(";HEIGHT:"):
                    try:
                        heights[round(z, 3)] = float(line[8:].strip())
                    except ValueError:
                        pass
                elif line.startswith(";TIME:") and "TIME" not in hdr:
                    hdr["TIME"] = line[6:].strip()
                elif line.startswith("; ") or line.startswith(";Layer height:") or line.startswith(";Filament used:"):
                    body = line[1:].strip()
                    if flavour_line is None and (body.lower().startswith("generated by") or "slicer" in body.lower()[:30] or "bambustudio" in body.lower()[:20] or "cura" in body.lower()[:20]):
                        flavour_line = body
                    if " = " in body:
                        key, _, value = body.partition(" = ")
                        key = key.strip()
                        if key not in hdr:
                            hdr[key] = value.strip()
                    elif "printing time:" in body or "estimated time:" in body:
                        # Bambu: "; model printing time: 1h 32m 12s; total estimated time: 1h 40m 2s"
                        for piece in body.split(";"):
                            key, _, value = piece.partition(":")
                            if value.strip() and key.strip() not in hdr:
                                hdr[key.strip()] = value.strip()
                    elif ": " in body and body.split(": ", 1)[0].strip() in ("Layer height", "Filament used", "filament_density", "filament_diameter"):
                        key, value = body.split(": ", 1)
                        hdr.setdefault({"Layer height": "layer_height", "Filament used": "filament used [m]"}.get(key.strip(), key.strip()), value.strip())
                continue
            if c == "G" or c == "g":
                words = line.split()
                cmd = words[0].upper()
                if cmd in ("G1", "G0", "G01", "G00"):
                    nx = ny = nz = ne = None
                    for w in words[1:]:
                        k = w[0]
                        if k == ";":
                            break
                        v = _num(w)
                        if v is None:
                            continue
                        if k == "X":
                            nx = v
                        elif k == "Y":
                            ny = v
                        elif k == "Z":
                            nz = v
                        elif k == "E":
                            ne = v
                    if nx is None and ny is None and nz is None and ne is None:
                        continue                    # a feed rate alone (G1 F9000) moves nothing and breaks no loop
                    if abs_xyz:
                        tx = nx if nx is not None else x
                        ty = ny if ny is not None else y
                        tz = nz if nz is not None else z
                    else:
                        tx = x + (nx or 0.0)
                        ty = y + (ny or 0.0)
                        tz = z + (nz or 0.0)
                    de = 0.0
                    if ne is not None:
                        de = (ne - e) if abs_e else ne
                        e = ne if abs_e else e + ne
                    if de > 1e-7 and (tx != x or ty != y) and tz == z:
                        extrusions += 1
                        if cur and cur_z == z and cur_tool == tool and cur[-1][0] == x and cur[-1][1] == y:
                            cur.append((tx, ty))
                        else:
                            end_poly()
                            cur = [(x, y), (tx, ty)]
                            cur_z, cur_tool, cur_width = z, tool, width
                        if typ in OUTER:
                            cur_outer = True
                            outer_moves += 1
                    else:
                        end_poly()
                    x, y, z = tx, ty, tz
                elif cmd in ("G2", "G3", "G02", "G03"):
                    nx = ny = ne = None
                    ci = cj = cr = None
                    for w in words[1:]:
                        k = w[0]
                        if k == ";":
                            break
                        v = _num(w)
                        if v is None:
                            continue
                        if k == "X":
                            nx = v
                        elif k == "Y":
                            ny = v
                        elif k == "E":
                            ne = v
                        elif k == "I":
                            ci = v
                        elif k == "J":
                            cj = v
                        elif k == "R":
                            cr = v
                    tx = (nx if nx is not None else x) if abs_xyz else x + (nx or 0.0)
                    ty = (ny if ny is not None else y) if abs_xyz else y + (ny or 0.0)
                    de = 0.0
                    if ne is not None:
                        de = (ne - e) if abs_e else ne
                        e = ne if abs_e else e + ne
                    pts = arc_points(x, y, tx, ty, ci, cj, cr, cmd in ("G2", "G02"))
                    if de > 1e-7 and pts:
                        extrusions += 1
                        if not (cur and cur_z == z and cur_tool == tool and cur[-1][0] == x and cur[-1][1] == y):
                            end_poly()
                            cur = [(x, y)]
                            cur_z, cur_tool, cur_width = z, tool, width
                        cur.extend(pts)
                        if typ in OUTER:
                            cur_outer = True
                            outer_moves += 1
                    else:
                        end_poly()
                    x, y = tx, ty
                elif cmd == "G90":
                    abs_xyz, abs_e = True, True
                elif cmd == "G91":
                    abs_xyz, abs_e = False, False
                elif cmd == "G92":
                    end_poly()
                    for w in words[1:]:
                        v = _num(w)
                        if v is None:
                            continue
                        if w[0] == "E":
                            e = v
                        elif w[0] == "X":
                            x = v
                        elif w[0] == "Y":
                            y = v
                        elif w[0] == "Z":
                            z = v
                elif cmd == "G28":
                    end_poly()
                    x = y = z = 0.0
                continue
            if c == "M":
                if line.startswith("M82"):
                    abs_e = True
                elif line.startswith("M83"):
                    abs_e = False
                elif line.startswith("M620 S"):
                    t = _num(line[5:].split()[0].rstrip("A"))
                    if t is not None:
                        end_poly()
                        tool = int(t)
                continue
            if c == "T":
                word = line.split()[0]
                if len(word) > 1 and word[1:].isdigit():
                    end_poly()
                    tool = int(word[1:])
                    tools_seen.add(tool)
    end_poly()
    return {"loops": loops, "heights": heights, "header": header_of(hdr, flavour_line), "lines": n - (1 if truncated else 0), "truncated": truncated,
            "extrusions": extrusions, "outer_moves": outer_moves, "tools_seen": sorted(tools_seen)}


def arc_points(x0, y0, x1, y1, ci, cj, r, clockwise):
    """The points along a G2/G3 arc (centre by I/J or radius R), every ARC_STEP_DEG; [] when it is not an arc."""
    if ci is None and cj is None:
        if r is None:
            return [(x1, y1)]
        dx, dy = x1 - x0, y1 - y0
        d = math.hypot(dx, dy)
        if d < 1e-9 or abs(r) < d / 2:
            return [(x1, y1)]
        h = math.sqrt(max(0.0, r * r - (d / 2) ** 2))
        mx, my = (x0 + x1) / 2, (y0 + y1) / 2
        sign = -1.0 if (clockwise) == (r > 0) else 1.0
        cx, cy = mx + sign * h * (-dy) / d, my + sign * h * dx / d
    else:
        cx, cy = x0 + (ci or 0.0), y0 + (cj or 0.0)
    radius = math.hypot(x0 - cx, y0 - cy)
    if radius < 1e-9:
        return [(x1, y1)]
    a0 = math.atan2(y0 - cy, x0 - cx)
    a1 = math.atan2(y1 - cy, x1 - cx)
    span = a1 - a0
    if clockwise:
        if span >= 0:
            span -= 2 * math.pi
    elif span <= 0:
        span += 2 * math.pi
    if abs(x1 - x0) < 1e-9 and abs(y1 - y0) < 1e-9:
        span = -2 * math.pi if clockwise else 2 * math.pi
    steps = max(2, int(math.ceil(abs(span) / math.radians(ARC_STEP_DEG))))
    pts = [(cx + radius * math.cos(a0 + span * k / steps), cy + radius * math.sin(a0 + span * k / steps)) for k in range(1, steps)]
    pts.append((x1, y1))
    return pts


# ── the layers ────────────────────────────────────────────────────────────────────────────────────────────────────

def close_loops(entries, counts):
    """Closed polygons out of the outer-wall polylines of one layer: a polyline whose ends meet is closed; open ones
    are chained end to end when they meet, and closed when the chain's ends do; what stays open is left out."""
    import numpy as np
    closed, open_ = [], []
    for tool, w, pts in entries:
        a = np.asarray(pts, dtype=np.float64)
        keep = np.ones(len(a), dtype=bool)
        keep[1:] = np.any(np.abs(a[1:] - a[:-1]) > 1e-6, axis=1)
        a = a[keep]
        if len(a) < 3:
            continue
        tol = max(1.5 * w, 0.8)
        if np.hypot(*(a[0] - a[-1])) <= tol:
            closed.append((tool, w, a[:-1] if np.hypot(*(a[0] - a[-1])) < 1e-6 else a))
        else:
            open_.append((tool, w, a))
    # chaining of open pieces of one loop (a loop the slicer split by a travel), by tool
    rest = open_
    while rest:
        tool, w, a = rest.pop(0)
        tol = max(1.5 * w, 0.8)
        chain = a
        grew = True
        while grew:
            grew = False
            for i, (t2, w2, b) in enumerate(rest):
                if t2 != tool:
                    continue
                if np.hypot(*(chain[-1] - b[0])) <= tol:
                    chain = np.vstack([chain, b[1:]])
                elif np.hypot(*(chain[-1] - b[-1])) <= tol:
                    chain = np.vstack([chain, b[::-1][1:]])
                elif np.hypot(*(chain[0] - b[-1])) <= tol:
                    chain = np.vstack([b, chain[1:]])
                elif np.hypot(*(chain[0] - b[0])) <= tol:
                    chain = np.vstack([b[::-1], chain[1:]])
                else:
                    continue
                rest.pop(i)
                grew = True
                break
        if np.hypot(*(chain[0] - chain[-1])) <= max(3 * w, 2.0) and len(chain) >= 3:
            closed.append((tool, w, chain))
        else:
            counts["open"] += 1
    return closed


def _area(a):
    x, y = a[:, 0], a[:, 1]
    return 0.5 * float(abs(np.dot(x, np.roll(y, -1)) - np.dot(y, np.roll(x, -1))))


def _inside(point, poly):
    """Ray casting of one point against a polygon (numpy)."""
    px, py = point
    x, y = poly[:, 0], poly[:, 1]
    x2, y2 = np.roll(x, -1), np.roll(y, -1)
    cross = (y > py) != (y2 > py)
    with np.errstate(divide="ignore", invalid="ignore"):
        xs = x + (py - y) * (x2 - x) / (y2 - y)
    return bool(np.count_nonzero(cross & (px < xs)) % 2)


def layer_regions(M, closed, counts):
    """The region of every tool in one layer (CrossSection, grown by half the line width), from the nesting of the
    closed loops; {} when the layer has none."""
    polys = []
    for tool, w, a in closed:
        area = _area(a)
        if area < w * w:
            counts["tiny"] += 1
            continue
        polys.append({"tool": tool, "w": w, "pts": a, "area": area, "bbox": (a[:, 0].min(), a[:, 1].min(), a[:, 0].max(), a[:, 1].max())})
    if not polys:
        return {}
    polys.sort(key=lambda p: -p["area"])
    # the direct parent of each loop: the smallest bigger loop it lies in
    for i, p in enumerate(polys):
        p["parent"] = None
        px, py = p["pts"][0]
        for j in range(i - 1, -1, -1):
            q = polys[j]
            bx0, by0, bx1, by1 = q["bbox"]
            if px < bx0 or px > bx1 or py < by0 or py > by1:
                continue
            if _inside((px, py), q["pts"]):
                p["parent"] = j
                break
    for i, p in enumerate(polys):
        par = polys[p["parent"]] if p["parent"] is not None else None
        p["material"] = par is None or (not par["material"]) or par["tool"] != p["tool"]
        p["children"] = []
        if par is not None:
            par["children"].append(i)
    regions = {}
    widths = {}
    for p in polys:
        if not p["material"]:
            continue
        fill = M.CrossSection([p["pts"]], M.FillRule.NonZero)
        inner = [M.CrossSection([polys[c]["pts"]], M.FillRule.NonZero) for c in p["children"]]
        if inner:
            fill = fill - M.CrossSection.batch_boolean(inner, M.OpType.Add)
        if fill.is_empty():
            continue
        regions.setdefault(p["tool"], []).append(fill)
        widths.setdefault(p["tool"], []).append(p["w"])
    grown = {}
    for tool, fills in regions.items():
        region = fills[0] if len(fills) == 1 else M.CrossSection.batch_boolean(fills, M.OpType.Add)
        w = sorted(widths[tool])[len(widths[tool]) // 2]
        grown[tool] = region.offset(w / 2, M.JoinType.Round, 2.0, 8).simplify(0.01)
    # where an inlay's grown edge overlaps the plate's, the later tool keeps it
    tools = sorted(grown)
    for i, tool in enumerate(tools):
        later = [grown[t] for t in tools[i + 1:]]
        if later:
            grown[tool] = grown[tool] - M.CrossSection.batch_boolean(later, M.OpType.Add) if len(later) > 1 else grown[tool] - later[0]
            if grown[tool].is_empty():
                del grown[tool]
    return grown


def _same(a, b):
    pa, pb = a.to_polygons(), b.to_polygons()
    if len(pa) != len(pb):
        return False
    return all(len(x) == len(y) and np.allclose(x, y, atol=1e-6) for x, y in zip(pa, pb))


def build(src, dst, p, parts_dir):
    import manifold3d as M
    stage(dst, "parsing")
    max_lines = int(p.get("max_lines") or MAX_LINES)
    data = read(src, max_lines, progress=lambda n: stage(dst, "parsing"))
    hdr = data["header"]
    loops = data["loops"]
    if data["extrusions"] == 0:
        raise Invalid("not_gcode" if data["lines"] < 5 or hdr["flavour"] == "unknown" and not loops else "no_walls")
    if not loops:
        raise Invalid("no_walls")
    stage(dst, "layers")
    zs = sorted(loops)
    layer_h = hdr["layer_height"]
    if layer_h is None:
        dz = sorted(round(b - a, 3) for a, b in zip(zs, zs[1:]) if b - a > 0.01)
        layer_h = dz[len(dz) // 2] if dz else (zs[0] if zs else 0.2)
    # a spiral (vase mode) changes z all the time: its loops are gathered per layer of the layer height
    if len(zs) > 3 * max(1, int(round(zs[-1] / max(layer_h, 0.01)))) + 10:
        binned = {}
        for z in zs:
            k = round(math.ceil(z / layer_h - 1e-6) * layer_h, 3)
            binned.setdefault(k, []).extend(loops[z])
        loops, zs = binned, sorted(binned)
    counts = {"open": 0, "tiny": 0}
    slabs = {}              # tool → [(z0, z1, region)]
    per_layer_tools = []
    last = {}               # tool → index of its last slab, to merge equal layers
    prev_z = None
    for z in zs:
        closed = close_loops(loops[z], counts)
        regions = layer_regions(M, closed, counts)
        h = data["heights"].get(z) or (z - prev_z if prev_z is not None and 0 < z - prev_z <= 3 * layer_h else layer_h)
        z0 = round(z - h, 4)
        per_layer_tools.append(sorted(regions))
        for tool, region in regions.items():
            lst = slabs.setdefault(tool, [])
            if lst and abs(lst[-1][1] - z0) < 1e-4 and _same(lst[-1][2], region):
                lst[-1] = (lst[-1][0], round(z, 4), lst[-1][2])
            else:
                lst.append((z0, round(z, 4), region))
        prev_z = z
    if not slabs:
        raise Invalid("no_walls")
    stage(dst, "building")
    solids = {}
    for tool, lst in slabs.items():
        pieces = [M.Manifold.extrude(region, z1 - z0).translate([0.0, 0.0, z0]) for z0, z1, region in lst if z1 - z0 > 1e-5]
        if not pieces:
            continue
        solid = pieces[0] if len(pieces) == 1 else union_many(M, pieces)
        if solid.is_empty() or solid.status() != M.Error.NoError:
            raise Invalid("empty_result")
        solids[tool] = solid
    if not solids:
        raise Invalid("empty_result")
    tools = sorted(solids)
    mixed = any(len(t) > 1 for t in per_layer_tools)
    mode = "one" if len(tools) == 1 else ("parts" if mixed else "height")
    # a file that names one filament names it for whatever tool number the print used (a farm print from slot T3)
    def pick(lst, t, default=None):
        return lst[t] if t < len(lst) else (lst[0] if len(lst) == 1 else default)
    hex_of = {t: (pick(hdr["colours"], t) or FALLBACK_COLORS[i % len(FALLBACK_COLORS)]).upper()[:7] for i, t in enumerate(tools)}
    name_of = {t: pick(hdr["names"], t, "") for t in tools}
    type_of = {t: pick(hdr["types"], t, "") for t in tools}
    used_of = {t: pick(hdr["used_g"], t) for t in tools}

    # the whole thing stands on the bed in the middle of the plate
    x0, y0, z0, x1, y1, z1 = union_many(M, list(solids.values())).bounding_box() if len(solids) > 1 else solids[tools[0]].bounding_box()
    shift = [-(x0 + x1) / 2, -(y0 + y1) / 2, -z0]
    solids = {t: s.translate(shift) for t, s in solids.items()}
    notes = {
        "flavour": hdr["flavour"], "slicer": hdr["slicer"], "printer": hdr["printer"], "profile": hdr["profile"],
        "layer_height": round(layer_h, 3), "line_width": hdr["line_width"] or DEFAULT_WIDTH, "nozzle": hdr["nozzle"],
        "layers": len(zs), "lines": data["lines"], "truncated": data["truncated"], "loops": sum(len(v) for v in loops.values()),
        "open_loops": counts["open"], "time_s": hdr["time_s"], "filament_g": hdr["filament_g"], "filament_m": hdr["filament_m"],
        "colors_mode": mode, "tools": tools, "warnings": [],
    }
    if data["truncated"]:
        notes["warnings"].append("truncated")
    if counts["open"]:
        notes["warnings"].append("open_loops")
    total_vol = sum(s.volume() for s in solids.values()) or 1.0
    if mode == "parts":
        laid = [("color_%d" % (i + 1), solids[t]) for i, t in enumerate(tools)]
        notes["colors"] = [{"part": "color_%d" % (i + 1), "hex": hex_of[t], "name": name_of[t], "type": type_of[t], "extruder": t + 1, "grams": used_of[t],
                            "share": round(100 * solids[t].volume() / total_vol, 1), "triangles": int(solids[t].num_tri())} for i, t in enumerate(tools)]
        notes["part_colors"] = {"color_%d" % (i + 1): {"code": hex_of[t], "hex": hex_of[t]} for i, t in enumerate(tools)}
        notes["multi_material"] = True
        if parts_dir:
            os.makedirs(parts_dir, exist_ok=True)
            write_3mf(os.path.join(parts_dir, "colors.3mf"), laid, [hex_of[t] for t in tools])
    else:
        body = solids[tools[0]] if len(solids) == 1 else union_many(M, list(solids.values()))
        laid = [("body", body)]
        notes["colors"] = [{"part": "body" if mode == "one" else None, "hex": hex_of[t], "name": name_of[t], "type": type_of[t], "extruder": t + 1, "grams": used_of[t],
                            "share": round(100 * solids[t].volume() / total_vol, 1), "triangles": int(solids[t].num_tri())} for t in tools]
        notes["part_colors"] = {"body": {"code": hex_of[tools[0]], "hex": hex_of[tools[0]]}}
        notes["multi_material"] = False
        if mode == "height":
            # where the print changes its spool: the bottom of the first layer of every other tool
            changes, current = [], None
            for z, tl in zip(zs, per_layer_tools):
                if not tl:
                    continue
                if current is not None and tl[0] != current:
                    changes.append({"z": round(slab_bottom(slabs[tl[0]], z) + shift[2], 3), "hex": hex_of[tl[0]], "code": None})
                current = tl[0]
            notes["color_changes"] = changes
            if len(changes) == 1:
                notes["color_change_mm"] = changes[0]["z"]
    finish(M, "gcode", dst, laid, notes, parts_dir)


def slab_bottom(lst, z):
    for z0, z1, _ in lst:
        if z0 - 1e-4 <= z <= z1 + 1e-4:
            return z0
    return z


def union_many(M, pieces):
    """The union of many solids, in batches: hundreds of slabs stacked on one another."""
    while len(pieces) > 1:
        merged = []
        for i in range(0, len(pieces), 32):
            chunk = pieces[i:i + 32]
            merged.append(chunk[0] if len(chunk) == 1 else M.Manifold.batch_boolean(chunk, M.OpType.Add))
        pieces = merged
    return pieces[0]


def write_3mf(path, laid, hexes):
    """A plain 3MF of one object per colour, each with its colour as a base material (what every slicer reads)."""
    parts = []
    objects = []
    for i, ((name, solid), hx) in enumerate(zip(laid, hexes)):
        mesh = solid.to_mesh()
        verts = np.asarray(mesh.vert_properties)[:, :3]
        tris = np.asarray(mesh.tri_verts)
        vs = "".join('<vertex x="%.4f" y="%.4f" z="%.4f"/>' % (v[0], v[1], v[2]) for v in verts)
        ts = "".join('<triangle v1="%d" v2="%d" v3="%d"/>' % (t[0], t[1], t[2]) for t in tris)
        objects.append('<object id="%d" name="%s" type="model" pid="1" pindex="%d"><mesh><vertices>%s</vertices><triangles>%s</triangles></mesh></object>' % (i + 2, name, i, vs, ts))
        parts.append('<base name="%s %s" displaycolor="%sFF"/>' % (name, hx, hx))
    model = ('<?xml version="1.0" encoding="UTF-8"?><model unit="millimeter" xml:lang="en-US" xmlns="http://schemas.microsoft.com/3dmanufacturing/core/2015/02">'
             '<metadata name="Application">matplace gcode_tool</metadata><resources><basematerials id="1">%s</basematerials>%s</resources><build>%s</build></model>'
             % ("".join(parts), "".join(objects), "".join('<item objectid="%d"/>' % (i + 2) for i in range(len(laid)))))
    with zipfile.ZipFile(path, "w", zipfile.ZIP_DEFLATED) as zf:
        zf.writestr("[Content_Types].xml", '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                    '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="model" ContentType="application/vnd.ms-package.3dmanufacturing-3dmodel+xml"/></Types>')
        zf.writestr("_rels/.rels", '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                    '<Relationship Target="/3D/3dmodel.model" Id="rel0" Type="http://schemas.microsoft.com/3dmanufacturing/2013/01/3dmodel"/></Relationships>')
        zf.writestr("3D/3dmodel.model", model)


def header(src):
    data = read(src, 20000)
    out({"ok": True} | data["header"] | {"lines": data["lines"], "tools": data["tools_seen"]})


def main(argv):
    if len(argv) < 3:
        out({"ok": False, "error": "usage", "code": "usage"})
    op = argv[1]
    try:
        if op == "header":
            header(argv[2])
        if op != "gcode" or len(argv) < 4:
            raise Invalid("unknown_kind", op)
        src, dst, raw = argv[2], argv[3], argv[4] if len(argv) > 4 else "{}"
        parts_dir = argv[5] if len(argv) > 5 else None
        if raw.startswith("@"):
            with open(raw[1:], encoding="utf-8") as fh:
                raw = fh.read()
        p = json.loads(raw or "{}")
        if not isinstance(p, dict):
            raise Invalid("unknown_kind")
        build(src, dst, p, parts_dir)
    except Invalid as e:
        out({"ok": False, "code": e.code, "error": str(e)})
    except SystemExit:
        raise
    except Exception as e:  # noqa: BLE001
        out({"ok": False, "code": "failed", "error": str(e)})


if __name__ == "__main__":
    main(sys.argv)
