#!/usr/bin/env python3
"""
Parametric everyday products as exact watertight solids (manifold3d): no AI, no cost per piece, milliseconds per model.

  param_tool.py <kind> <out.stl> <params-json> [part] [view] [parts]

kind:  organizer | box | phone_stand | cable_holder | holder | cap | vase | logo | stamp | qr   (the last four live in creative_kinds.py)
part:  all (default) | body | lid | saucer | handle | stand | imprint   (separate exports of multi-part products)
view:  print (default, the orientation it should be printed in) | use (how it stands on the desk; for previews)

All lengths in millimetres. Limits are enforced here as well as in the web layer, so the tool can never be asked
for an impossible or absurdly heavy shape. Prints one JSON object: {ok, bbox, volume_mm3, area_mm2, triangles, outer, notes}.

With the sixth argument "parts" (the tool page's preview) the triangles of the file are grouped by the piece they
belong to and the answer carries `parts`: [{name, tris: [from, to), bbox: [x0, y0, z0, x1, y1, z1]}], one entry per
separate body, in the order of the file. The viewer selects, colours and spreads the pieces by it. Without the
argument the file is written exactly as before.
"""
import json
import math
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

LIMITS = {
    "organizer": {"width": (30, 400), "depth": (30, 400), "height": (10, 150), "rows": (1, 8), "cols": (1, 8), "wall": (0.8, 4), "floor": (0.8, 4), "radius": (0, 20)},
    "box": {"inner_w": (10, 300), "inner_d": (10, 300), "inner_h": (8, 200), "wall": (1.2, 5), "floor": (1.0, 5), "clearance": (0.1, 0.6), "radius": (0, 30), "cable_d": (3, 30)},
    "phone_stand": {"width": (50, 260), "device": (7, 20), "angle": (35, 80), "back": (60, 200), "thickness": (3, 8), "radius": (0, 4), "depth": (40, 120), "vent": (1, 4)},
    "cable_holder": {"count": (1, 8), "cable": (3, 14), "depth": (10, 80), "wall": (2, 12), "radius": (0, 6)},
    "holder": {"obj_w": (10, 300), "obj_d": (5, 150), "height": (15, 150), "wall": (2, 6), "clearance": (0.3, 2), "radius": (0, 12),
               "hook_h": (10, 150), "bend": (0, 40), "edge": (0, 2)},
    "cap": {"size_a": (5, 200), "size_b": (8, 200), "height": (4, 60), "wall": (1.2, 4), "top": (1.2, 5), "clearance": (0.1, 1), "pitch": (0.5, 6), "edge": (0, 3), "mouth": (4, 195), "outer": (0, 220)},
    "modular": {"inner_w": (60, 600), "inner_d": (60, 600), "height": (15, 120), "cols": (1, 12), "rows": (1, 12), "wall": (0.8, 3), "floor": (0.8, 3), "radius": (0, 15), "gap": (0.3, 1.5)},
}
MIN_CELL = 8.0
MAX_HOLES = 8
MAX_BINS = 24
MIN_UNIT = 15.0


class Invalid(Exception):
    """Carries a machine-readable code so the web layer can explain it in the visitor's language."""

    def __init__(self, code, detail=""):
        super().__init__(code + (": " + detail if detail else ""))
        self.code = code


def out(obj):
    print(json.dumps(obj))
    sys.exit(0)


def num(p, kind, key, default):
    lo, hi = LIMITS[kind][key]
    try:
        v = float(p.get(key, default))
    except (TypeError, ValueError):
        raise Invalid("not_a_number", key)
    if math.isnan(v) or v < lo or v > hi:
        raise Invalid("out_of_range", "%s %s-%s" % (key, lo, hi))
    return v


def rounded_rect(M, w, d, r):
    r = max(0.0, min(r, w / 2 - 0.01, d / 2 - 0.01))
    if r < 0.05:
        return M.CrossSection.square([w, d])
    return M.CrossSection.square([w - 2 * r, d - 2 * r]).translate([r, r]).offset(r, M.JoinType.Round, 2.0, 48)


def organizer(M, p):
    k = "organizer"
    w, d, h = num(p, k, "width", 200), num(p, k, "depth", 120), num(p, k, "height", 40)
    rows, cols = int(num(p, k, "rows", 2)), int(num(p, k, "cols", 3))
    wall, floor = num(p, k, "wall", 1.6), num(p, k, "floor", 1.2)
    cw, cd = (w - wall * (cols + 1)) / cols, (d - wall * (rows + 1)) / rows
    if cw < MIN_CELL or cd < MIN_CELL:
        raise Invalid("cells_too_small", "%.1f x %.1f" % (cw, cd))
    if floor >= h - 2:
        raise Invalid("floor_too_thick")
    radius = num(p, k, "radius", 4)
    body = rounded_rect(M, w, d, radius).extrude(h)
    cells = []
    for r in range(rows):
        for c in range(cols):
            x, y = wall + c * (cw + wall), wall + r * (cd + wall)
            cells.append(rounded_rect(M, cw, cd, 2.0).translate([x, y]))
    # only the corners that follow the outside get the big radius; partitions keep small ones, so no plastic is wasted inside
    inside = rounded_rect(M, w - 2 * wall, d - 2 * wall, max(1.0, radius - wall)).translate([wall, wall])
    cavity = (M.CrossSection.batch_boolean(cells, M.OpType.Add) ^ inside).extrude(h).translate([0, 0, floor])
    solid = body - cavity
    return {"all": solid}, {"outer": [w, d, h], "cell": [round(cw, 1), round(cd, 1), round(h - floor, 1)]}


def modular(M, p):
    """
    Loose bins on the customer's own grid that fill a drawer (or an optional tray) exactly.
    bins: [{x, y, w, h, color}] in grid cells, x/y from the front-left corner. Every bin is its own print in its own colour.
    """
    k = "modular"
    iw, idp, h = num(p, k, "inner_w", 300), num(p, k, "inner_d", 150), num(p, k, "height", 40)
    cols, rows = int(num(p, k, "cols", 6)), int(num(p, k, "rows", 3))
    wall, floor, radius, gap = num(p, k, "wall", 1.6), num(p, k, "floor", 1.2), num(p, k, "radius", 6), num(p, k, "gap", 0.6)
    tray = bool(p.get("tray", False))
    ux, uy = iw / cols, idp / rows
    if ux < MIN_UNIT or uy < MIN_UNIT:
        raise Invalid("grid_too_fine", "%.0f x %.0f" % (ux, uy))
    if floor >= h - 3:
        raise Invalid("floor_too_thick")
    bins = p.get("bins") or []
    if not isinstance(bins, list) or not bins:
        raise Invalid("no_bins")
    if len(bins) > MAX_BINS:
        raise Invalid("too_many_bins", str(MAX_BINS))
    taken = [[0] * cols for _ in range(rows)]
    clean = []
    for i, b in enumerate(bins):
        try:
            x, y, w, d = int(b.get("x", 0)), int(b.get("y", 0)), int(b.get("w", 1)), int(b.get("h", 1))
        except (TypeError, ValueError):
            raise Invalid("not_a_number", "bin %d" % (i + 1))
        if w < 1 or d < 1 or x < 0 or y < 0 or x + w > cols or y + d > rows:
            raise Invalid("bin_outside", str(i + 1))
        for yy in range(y, y + d):
            for xx in range(x, x + w):
                if taken[yy][xx]:
                    raise Invalid("bins_overlap", "%d+%d" % (taken[yy][xx], i + 1))
                taken[yy][xx] = i + 1
        clean.append((x, y, w, d, str(b.get("color", "white"))[:80]))   # a built-in name or the code of a spool

    def bin_solid(w, d):
        ow, od = w * ux - gap, d * uy - gap
        r = min(radius, ow / 2 - 0.5, od / 2 - 0.5)
        outer = rounded_rect(M, ow, od, r).extrude(h)
        # the same radius inside, less the wall: a constant wall all the way round the corner
        cavity = rounded_rect(M, ow - 2 * wall, od - 2 * wall, max(0.0, r - wall)).extrude(h).translate([wall, wall, floor])
        return outer - cavity, ow, od

    sizes, placed, regions, bom = {}, [], [], {}
    t_wall, t_floor = 2.0, 1.6
    off = (t_wall + gap / 2, t_floor) if tray else (0.0, 0.0)
    for (x, y, w, d, color) in clean:
        key = "%dx%d" % (w, d)
        if key not in sizes:
            sizes[key] = bin_solid(w, d)
        solid, ow, od = sizes[key]
        px, py = off[0] + x * ux + gap / 2, off[0] + y * uy + gap / 2
        placed.append(solid.translate([px, py, off[1]]))
        regions.append({"x0": round(px, 2), "y0": round(py, 2), "x1": round(px + ow, 2), "y1": round(py + od, 2), "z0": round(off[1], 2), "color": color})
        item = bom.setdefault((key, color), {"size": key, "w_mm": round(ow, 1), "d_mm": round(od, 1), "color": color, "count": 0})
        item["count"] += 1

    assembled = M.Manifold.compose(placed)
    parts = {"bin_" + key: v[0] for key, v in sizes.items()}
    notes = {"unit": [round(ux, 1), round(uy, 1)], "inner": [iw, idp, h], "bins": len(clean), "free_cells": sum(row.count(0) for row in taken),
             "bom": sorted(bom.values(), key=lambda b: (b["size"], b["color"])), "regions": regions, "tray": tray, "outer": [iw, idp, h]}
    if tray:
        tw, td, th = iw + 2 * t_wall + gap, idp + 2 * t_wall + gap, max(12.0, round(h * 0.7, 1)) + t_floor
        tr = radius + t_wall if radius > 0 else 0.0
        shell = rounded_rect(M, tw, td, tr).extrude(th) - rounded_rect(M, tw - 2 * t_wall, td - 2 * t_wall, max(0.0, tr - t_wall)).extrude(th).translate([t_wall, t_wall, t_floor])
        parts["tray"] = shell
        assembled = M.Manifold.compose([shell, assembled])
        notes["outer"] = [round(tw, 1), round(td, 1), round(max(th, h + t_floor), 1)]
        notes["tray_size"] = [round(tw, 1), round(td, 1), round(th, 1)]
    parts["use"] = assembled                         # how it sits in the drawer: the preview, with its colours
    # priced as a set. With a tray the bins lie beside it, not in it: touching bodies would be sliced as one piece.
    parts["all"] = M.Manifold.compose([parts["tray"], M.Manifold.compose(placed).translate([notes["outer"][0] + 8.0, 0, -off[1]])]) if tray else assembled
    return parts, notes


def wall_frame(wall_name, iw, idp, wall):
    """Origin (inner left-bottom corner seen from outside), horizontal axis and outward normal of a box wall."""
    ow, od = iw + 2 * wall, idp + 2 * wall
    return {
        "front": ((wall, 0.0), (1, 0), iw, "y"),
        "back": ((wall + iw, od), (-1, 0), iw, "y"),
        "left": ((0.0, wall + idp), (0, -1), idp, "x"),
        "right": ((ow, wall), (0, 1), idp, "x"),
    }[wall_name]


def box(M, p):
    k = "box"
    iw, idp, ih = num(p, k, "inner_w", 80), num(p, k, "inner_d", 50), num(p, k, "inner_h", 30)
    wall, floor = num(p, k, "wall", 2.0), num(p, k, "floor", 1.6)
    lid = bool(p.get("lid", False))
    clearance = num(p, k, "clearance", 0.25)
    ow, od, oh = iw + 2 * wall, idp + 2 * wall, ih + floor
    lip_h = min(6.0, max(3.0, ih * 0.2)) if lid else 0.0
    radius = min(num(p, k, "radius", 2.5), min(iw, idp) / 2 - 0.5 + wall)
    inner_r = max(0.0, radius - wall)                      # the same rounding inside: walls stay even all the way round
    body = rounded_rect(M, ow, od, radius).extrude(oh) - rounded_rect(M, iw, idp, inner_r).extrude(ih + 1).translate([wall, wall, floor])

    holes = p.get("holes") or []
    if not isinstance(holes, list) or len(holes) > MAX_HOLES:
        raise Invalid("too_many_holes", str(MAX_HOLES))
    placed = {}
    for i, hdef in enumerate(holes):
        wname = hdef.get("wall")
        if wname not in ("front", "back", "left", "right"):
            raise Invalid("hole_wall", str(i + 1))
        shape = hdef.get("shape", "circle")
        try:
            hw = float(hdef.get("w", 8))
            hh = hw if shape == "circle" else float(hdef.get("h", 8))
            hx, hz = float(hdef.get("x", 0)), float(hdef.get("z", 0))
        except (TypeError, ValueError):
            raise Invalid("not_a_number", "hole %d" % (i + 1))
        if shape not in ("circle", "rect") or hw < 2 or hh < 2 or hw > 200 or hh > 200:
            raise Invalid("hole_size", str(i + 1))
        (ox, oy), (ax, ay), span, _ = wall_frame(wname, iw, idp, wall)
        margin = 2.0
        side = margin + inner_r                             # sideways also clear of the rounded corners
        top_limit = ih - lip_h - (1.0 if lid else 0.0)
        if hx - hw / 2 < side or hx + hw / 2 > span - side or hz - hh / 2 < margin or hz + hh / 2 > top_limit - margin + 1.0:
            raise Invalid("hole_outside", str(i + 1))
        for (px, pz, pw, ph, pi) in placed.get(wname, []):
            if abs(px - hx) < (pw + hw) / 2 + 1.5 and abs(pz - hz) < (ph + hh) / 2 + 1.5:
                raise Invalid("holes_overlap", "%d+%d" % (pi, i + 1))
        placed.setdefault(wname, []).append((hx, hz, hw, hh, i + 1))
        depth = wall + 2.0
        if shape == "circle":
            cutter = M.Manifold.cylinder(depth, hw / 2, hw / 2, 64).translate([0, 0, -depth / 2])   # axis Z, centred
        else:
            cutter = M.Manifold.cube([hw, hh, depth], True)
        # cutter axis Z -> wall normal; local X -> along the wall, local Y -> up
        cutter = cutter.rotate([90, 0, 0])                                                         # axis now along Y, up is Z
        if ax == 0:
            cutter = cutter.rotate([0, 0, 90])
        cx, cy = ox + ax * hx, oy + ay * hx
        nx, ny = (0, 0)
        if wname == "front":
            ny = wall / 2
        elif wname == "back":
            ny = -wall / 2
        elif wname == "left":
            nx = wall / 2
        else:
            nx = -wall / 2
        body = body - cutter.translate([cx + nx, cy + ny, floor + hz])

    if bool(p.get("cable_slot", False)):
        # a slot open to the rim in the right wall: the cable is laid in with its plug on, nothing is threaded through;
        # it reaches below the lip of the lid, so the lid closes over the cable
        cd = num(p, k, "cable_d", 8)
        deep = lip_h + cd + (1.0 if lid else 0.0)
        if cd > idp - 2 * (inner_r + 2.0) or deep > ih - 3.0:
            raise Invalid("box_cable_too_big")
        round_end = M.Manifold.cylinder(wall + 2.0, cd / 2, cd / 2, 48).rotate([0, 90, 0]).translate([ow - wall - 1.0, od / 2, oh - deep + cd / 2])
        shaft = M.Manifold.cube([wall + 2.0, cd, deep]).translate([ow - wall - 1.0, od / 2 - cd / 2, oh - deep + cd / 2])
        body = body - round_end - shaft
        for (px, pz, pw, ph, pi) in placed.get("right", []):
            if abs(px - idp / 2) < (pw + cd) / 2 + 1.5 and floor + pz + ph / 2 > oh - deep - 1.5:
                raise Invalid("holes_overlap", "%d" % pi)

    parts = {"body": body}
    notes = {"outer": [round(ow, 2), round(od, 2), round(oh + (floor if lid else 0), 2)], "inner": [iw, idp, ih], "lid": lid}
    if lid:
        lip_wall = max(1.2, min(wall, 2.0))
        lw, ld = iw - 2 * clearance, idp - 2 * clearance
        if lw - 2 * lip_wall < 2 or ld - 2 * lip_wall < 2:
            raise Invalid("box_too_small_for_lid")
        plate = rounded_rect(M, ow, od, radius).extrude(floor)
        lip_r = max(0.0, inner_r - clearance)
        ring = rounded_rect(M, lw, ld, lip_r).extrude(lip_h) - rounded_rect(M, lw - 2 * lip_wall, ld - 2 * lip_wall, max(0.0, lip_r - lip_wall)).extrude(lip_h + 1).translate([lip_wall, lip_wall, 0])
        parts["lid"] = plate + ring.translate([wall + clearance, wall + clearance, floor])
        notes["lip_height"] = round(lip_h, 1)
        notes["clearance"] = clearance
        parts["all"] = parts["body"] + parts["lid"].translate([ow + 8.0, 0, 0])     # both on one plate, printed together
    else:
        parts["all"] = body
    return parts, notes


def _rnd(M, cs, rad):
    J = M.JoinType.Round
    return cs.offset(-rad, J, 2.0, 24).offset(rad, J, 2.0, 24) if rad > 0.05 else cs


def _soft(M, cs, rad):
    """Round every corner of an outline, inner and outer."""
    J = M.JoinType.Round
    return cs.offset(rad, J, 2.0, 24).offset(-2 * rad, J, 2.0, 24).offset(rad, J, 2.0, 24) if rad > 0.05 else cs


def _desk_profile(M, device, angle, back, t, r, window):
    """
    A-frame: the phone sits on a shelf above the table between a front lip and a leaning rest, the rest runs down to
    a rear foot. Hollowed from the inside: an arch under the shelf, open at the bottom (the cable runs out there),
    and a window in the rest.
    """
    C = M.CrossSection
    a = math.radians(angle)
    ux, uy = math.cos(a), math.sin(a)
    nx, ny = math.sin(a), -math.cos(a)
    shelf_h, lip_h = 16.0, 12.0
    fx = t + device + 1.0
    top_in = (fx + ux * back, shelf_h + uy * back)
    top_out = (top_in[0] + nx * t, top_in[1] + ny * t)
    rear_x = fx + ux * back * 0.58 + (shelf_h + uy * back * 0.58) * 0.5 + t
    outer = _soft(M, C([[(0, 0), (rear_x, 0), top_out, top_in, (fx, shelf_h), (t, shelf_h), (t, shelf_h + lip_h), (0, shelf_h + lip_h)]]), r)
    big = 1000.0
    below = C([[(-big, -big), (big, -big), (big, shelf_h - t), (-big, shelf_h - t)]])
    sunk = outer + C([[(0, -50), (rear_x, -50), (rear_x, 0.5), (0, 0.5)]])
    profile = outer - _rnd(M, sunk.offset(-t, M.JoinType.Round, 2.0, 24) ^ below, r * 1.5)
    if window:
        above = C([[(-big, shelf_h + t), (big, shelf_h + t), (big, big), (-big, big)]])
        win = _rnd(M, outer.offset(-t, M.JoinType.Round, 2.0, 24) ^ above, 4.0)
        if win.area() > 200:
            profile = profile - win
    return profile, fx, rear_x, top_out[1] + r, shelf_h, lip_h


def _wedge_profile(M, device, angle, depth, r):
    """
    Low block for watching: a slot leaning back at the chosen angle, 20 mm deep, its bottom 6 mm above the table
    so a cable can leave through the underside.
    """
    C = M.CrossSection
    a = math.radians(angle)
    slot_d, front, floor = 20.0, 10.0, 6.0
    lean = slot_d * math.cos(a)
    height = floor + slot_d * math.sin(a) + 4.0
    depth = max(depth, front + device + lean + 14.0)
    block = _soft(M, C([[(0, 0), (depth, 0), (depth, height * 0.5), (front + device + lean + 10.0, height), (0, height)]]), r)
    # the slot: a rectangle standing on the floor line, leaned back by (90 - angle) about its front bottom corner
    # its lowest corner (the rear bottom one, after leaning) stays on the floor line
    slot = C.square([device, slot_d + 6.0]).rotate(-(90.0 - angle)).translate([front, floor + device * math.cos(a)])
    return block - slot, front, height, depth


def _bowl(M, w, h, rad):
    """A rectangle whose two bottom corners are round."""
    C = M.CrossSection
    if rad < 0.05:
        return C.square([w, h])
    return rounded_rect(M, w, h + rad + 1.0, rad) ^ C.square([w, h])


def _edged(M, prof, height, e):
    """
    An outline pulled up from the bed with its edges softened: a 45 degree bevel on the bed (a round edge would start
    in the air), a round edge on top. Thin slabs, each the outline drawn a little smaller, laid over each other by
    0.01 mm; no two of them share a wall.
    """
    if e < 0.05 or height < 2 * e + 1.0:
        return prof.extrude(height)
    n = 10
    while n > 2 and e * (1 - math.cos(math.pi / (2 * n))) < 0.03:
        n -= 1
    lap = 0.01

    def slab(inset, z0, z1):
        cs = prof.offset(-inset, M.JoinType.Round, 2.0, 24).simplify(0.02)
        return cs.extrude(z1 - z0).translate([0, 0, z0])

    low = [e * i / n for i in range(n + 1)]
    deep = [e * (1 - math.cos(math.pi / 2 * j / n)) for j in range(n + 1)]
    parts = [prof.extrude(height - deep[n - 1] - low[n - 1]).translate([0, 0, low[n - 1]])]
    for i in range(n - 1):
        parts.append(slab(e - low[i + 1], low[i], low[i + 1] + lap))
        parts.append(slab(e * (1 - math.sin(math.pi / 2 * (i + 1) / n)), height - deep[i + 1] - lap, height - deep[i]))
    return M.Manifold.batch_boolean(parts, M.OpType.Add)


def _rounded_foot(M, cs, height, e):
    """
    An outline pulled up from the bed with its bottom edge round (the first layers are drawn a little smaller; a
    printer manages that up to a few millimetres). Thin slabs laid over each other by 0.01 mm, as in _edged.
    """
    if e < 0.05 or height < e + 1.0:
        return cs.extrude(height)
    n = 10
    while n > 2 and e * (1 - math.cos(math.pi / (2 * n))) < 0.03:
        n -= 1
    deep = [e * (1 - math.cos(math.pi / 2 * j / n)) for j in range(n + 1)]
    parts = [cs.extrude(height - deep[n - 1]).translate([0, 0, deep[n - 1]])]
    for i in range(n - 1):
        inset = e * (1 - math.sin(math.pi / 2 * (i + 1) / n))
        slab = cs.offset(-inset, M.JoinType.Round, 2.0, 24).simplify(0.02)
        parts.append(slab.extrude(deep[i + 1] - deep[i] + 0.01).translate([0, 0, deep[i]]))
    return M.Manifold.batch_boolean(parts, M.OpType.Add)


def _half(M, solid):
    """The solid with its front half taken away: a look inside, for previews."""
    x0, y0, z0, x1, y1, z1 = solid.bounding_box()
    return solid - M.Manifold.cube([x1 - x0 + 2.0, (y1 - y0) / 2 + 1.0, z1 - z0 + 2.0]).translate([x0 - 1.0, y0 - 1.0, z0 - 1.0])


def _on_floor(solid):
    """Move a solid so its bounding box starts at the origin."""
    b = solid.bounding_box()
    return solid.translate([-b[0], -b[1], -b[2]])


def _ribbon(M, pts, t):
    """A band of thickness t along a polyline: hulls of circles at consecutive points, so bends come out round."""
    C = M.CrossSection
    out = C.square([0, 0])
    prev = C.circle(t / 2, 32).translate(list(pts[0]))
    for q in pts[1:]:
        nxt = C.circle(t / 2, 32).translate(list(q))
        out = out + (prev + nxt).hull()
        prev = nxt
    return out


def _arc(cx, cy, rad, a0, a1, n=12):
    return [(cx + rad * math.cos(math.radians(a0 + (a1 - a0) * i / n)), cy + rad * math.sin(math.radians(a0 + (a1 - a0) * i / n))) for i in range(n + 1)]


def _wave_profile(M, device, angle, back, t):
    """
    One bent band, seen from the side: the rest leans back, meets the ground in a round bend, the base runs forward
    along the table, rolls up at the front and comes back as the seat. A short tail behind the bend keeps the
    phone's weight inside the footprint. Returns (profile, depth, height, seat_y, seat x-range).
    """
    a = math.radians(angle)
    ux, uy = math.cos(a), math.sin(a)
    h = t / 2
    R2 = max(8.0, t * 1.6)                                   # front roll (centre line radius): room for a plug under the seat
    seat_y = h + 2 * R2 - 2.5                                # the roll's top stands 2.5 mm above the seat: the lip
    # rest centre line passes through K = (0, h); the phone's bottom sits where the rest face meets the seat level
    x_rest = (seat_y - h) / math.tan(a) - h / uy             # front face of the rest at seat height
    x_end = x_rest - 1.5                                     # the seat ends just before the rest
    D = device + 3.0 + R2 - x_end                            # front roll centre at x = -D
    tail = x_end + 75.0 * ux + 8.0                            # ground behind K: a phone's weight (about 75 mm up) stays inside the footprint
    front = _arc(-D, h + R2, R2, -90, 90)                     # up and over at the front
    seat = [(-D + 3.0, seat_y), (x_end, seat_y)]
    J = M.JoinType.Round
    rest = _ribbon(M, [(back * ux, h + back * uy), (0.0, h)], t)
    ground = _ribbon(M, [(tail, h), (-D, h)], t)
    # the rest flows into the ground through a big round bend on both sides, like a bent band
    R1 = max(8.0, 1.6 * t)
    prof = (rest + ground).offset(R1, J, 2.0, 24).offset(-R1, J, 2.0, 24)
    prof = prof + _ribbon(M, [(-D - 0.01, h)] + front + seat, t)
    prof = prof.offset(1.5, J, 2.0, 24).offset(-1.5, J, 2.0, 24)              # small fillet where the seat meets the rest
    depth = tail + h + D + R2 + h
    return prof, depth, back * uy + t, seat_y + h, (-D + R2 - h, x_end)


def phone_stand(M, p):
    """
    Four stands from one tool: A-frame for the desk, low wedge for watching, wall pocket, and a clip for a car vent.
    Each is a side profile extruded across the width, printed lying on its side or on its back plate, no supports.
    """
    k = "phone_stand"
    style = str(p.get("style", "plate"))
    width, device = num(p, k, "width", 70), num(p, k, "device", 12)
    t = num(p, k, "thickness", 5)
    r = min(num(p, k, "radius", 2.0), t * 0.45)
    cable = bool(p.get("cable", True))
    C = M.CrossSection
    note = {}

    def lean(lo=None, hi=None):
        """The angle that was asked for, kept to what this shape stands firmly at; a changed angle is told (param.warn.stand_angle_*)."""
        asked = num(p, k, "angle", 65)
        used = asked if lo is None else max(asked, lo)
        used = used if hi is None else min(used, hi)
        if used != asked:
            note.setdefault("warnings", []).append("stand_angle_%d" % used)
        note["angle"] = used
        return used

    if style == "wave":
        angle, back = lean(lo=55.0), num(p, k, "back", 90)
        profile, depth, height, seat_top, (sx0, sx1) = _wave_profile(M, device, angle, back, t)
        solid = profile.extrude(width)
        if cable:
            slot = min(16.0, width * 0.3)
            # through the seat only: the plug drops into the roll and the cable leaves at the side
            solid = solid - M.Manifold.cube([sx1 - sx0 + 1.0, t + 1.0, slot]).translate([sx0, seat_top - t - 0.5, width / 2 - slot / 2])
        use = _on_floor(solid.rotate([90, 0, 0]).rotate([0, 0, 180]))
        solid = _on_floor(solid)
        note["outer"] = [round(depth, 1), round(width, 1), round(height, 1)]
        return {"all": solid, "use": use}, note

    if style == "plate":
        # flat base, a leaning back plate braced by a fin, two hook blocks with a seat and a lip; printed upright
        angle, back = lean(lo=55.0), num(p, k, "back", 100)
        a = math.radians(angle)
        seat_h, lip_h, lip_t, hook_w = 10.0, 6.0, 3.0, 12.0
        y_lip = 8.0
        y_foot = y_lip + lip_t + device + 2.0 - seat_h / math.tan(a)        # the plate's front face meets the base here
        fin_d = max(30.0, 0.55 * back * math.cos(a) + 10.0)                     # base behind the plate: keeps the phone's weight inside
        depth = y_foot + fin_d
        base = rounded_rect(M, width, depth, r).extrude(t)
        plate = rounded_rect(M, width, back, r).extrude(t).rotate([angle, 0, 0]).translate([0, y_foot, t - 0.01])
        fin_w = max(12.0, width * 0.25)
        apex = (y_foot + back * 0.6 * math.cos(a) - t * math.sin(a) * 0.5, t + back * 0.6 * math.sin(a))
        fin = C([[(y_foot - 1.0, t - 0.01), (depth - r, t - 0.01), apex]]).extrude(fin_w).rotate([90, 0, 90]).translate([(width - fin_w) / 2, 0, 0])
        solid = base + plate + fin
        # hooks: a block from the base up to the seat, with a lip in front; the phone's back leans on the plate
        seat_back = y_foot + seat_h / math.tan(a)
        hook = C([[(y_lip, t - 0.01), (y_foot, t - 0.01), (seat_back, t + seat_h), (y_lip + lip_t, t + seat_h), (y_lip + lip_t, t + seat_h + lip_h), (y_lip, t + seat_h + lip_h)]])
        for x in (width * 0.25 - hook_w / 2, width * 0.75 - hook_w / 2):
            solid = solid + hook.extrude(hook_w).rotate([90, 0, 90]).translate([x, 0, 0])
        use = solid
        note["outer"] = [round(width, 1), round(depth, 1), round(t + back * math.sin(a), 1)]
        note["prints_upright"] = True
        return {"all": solid, "use": use}, note

    if style == "wedge":
        angle = lean(hi=70.0)
        profile, mouth, height, depth = _wedge_profile(M, device, angle, num(p, k, "depth", 60), r)
        solid = profile.extrude(width)
        if cable:
            slot = min(14.0, width * 0.3)
            # from the slot bottom down through the underside, then a groove along the underside to the front edge
            solid = solid - M.Manifold.cube([device + 6.0, 10.0, slot]).translate([mouth - 3.0, -1.0, width / 2 - slot / 2])
            solid = solid - M.Manifold.cube([mouth + 8.0, 4.0, slot]).translate([-1.0, -1.0, width / 2 - slot / 2])
        use = solid.rotate([90, 0, 0]).rotate([0, 0, 180]).translate([depth, 0, 0])
        note["outer"] = [round(depth, 1), round(width, 1), round(height, 1)]
        return {"all": solid, "use": use}, note

    if style == "wall":
        # pocket on a back plate: the phone drops in from above, a slot in the pocket floor lets the cable through
        pocket_h, plate_h = 32.0, 70.0
        inner_w, inner_d = width + 2.0, device + 1.5
        plate = rounded_rect(M, inner_w + 2 * t, plate_h, r).extrude(t)
        pocket = rounded_rect(M, inner_w + 2 * t, pocket_h, r).extrude(inner_d + 2 * t) - M.Manifold.cube([inner_w, pocket_h, inner_d]).translate([t, t, t])
        solid = plate + pocket
        if cable:
            slot = min(16.0, inner_w * 0.4)
            solid = solid - M.Manifold.cube([slot, t + 2.0, inner_d + 2.0]).translate([t + (inner_w - slot) / 2, -1.0, t - 1.0])
        if p.get("screws", False):
            for x in (t + 8.0, inner_w + t - 8.0):
                solid = solid - M.Manifold.cylinder(t + 2.0, 2.2, 2.2, 32).translate([x, plate_h - 10.0, -1.0])
                solid = solid - M.Manifold.cylinder(t + 2.0, 2.2, 2.2, 32).translate([x, pocket_h + 8.0, -1.0])
            note["screws"] = 4
        # printed flat on the back plate; on the wall the plate stands upright and the pocket faces the room
        use = solid.rotate([90, 0, 0]).translate([0, inner_d + 2 * t, 0])
        note["outer"] = [round(inner_w + 2 * t, 1), round(inner_d + 2 * t, 1), round(plate_h, 1)]
        return {"all": solid, "use": use}, note

    if style == "car":
        # cradle: back plate with a bottom lip and two side arms; behind it two springy fingers grip a vent slat
        vent = num(p, k, "vent", 1.5)
        inner_w, inner_d = width + 1.0, device + 1.0
        plate_h, lip_h, arm_h = 55.0, 14.0, 40.0
        wall = max(2.4, t * 0.6)
        finger_l, finger_t = 26.0, 2.4
        jaw = vent + 0.3
        px = inner_d + wall                                                              # front face of the back plate
        prof = C.square([wall, plate_h]).translate([px, 0])
        prof = prof + C.square([inner_d + 2 * wall, wall]) + C.square([wall, lip_h])      # floor and front lip
        prof = _soft(M, prof, min(r, wall * 0.45))
        fingers = C.square([0, 0])
        for y in (18.0, 18.0 + jaw + finger_t):
            fingers = fingers + C.square([finger_l + wall, finger_t]).translate([px, y])   # two fingers behind the plate
        tip = px + wall + finger_l - 3.0
        fingers = fingers + C.square([3.0, 0.6]).translate([tip, 18.0 + finger_t])         # bumps: the clip snaps over the slat
        fingers = fingers + C.square([3.0, 0.6]).translate([tip, 18.0 + finger_t + jaw - 0.6])
        prof = prof + _soft(M, fingers, 0.3)                                              # rounded lightly: the jaw must stay open
        solid = prof.extrude(inner_w + 2 * wall)
        for z in (0.0, inner_w + wall):                                                  # side arms, open in the middle for the buttons
            solid = solid + M.Manifold.cube([inner_d + 2 * wall, arm_h, wall]).translate([0, 0, z])
        if cable:
            slot = min(14.0, inner_w * 0.35)
            solid = solid - M.Manifold.cube([inner_d + 1.0, wall + 2.0, slot]).translate([wall - 0.5, -1.0, wall + (inner_w - slot) / 2])
        depth = px + wall + finger_l
        use = solid.rotate([90, 0, 0]).rotate([0, 0, 180]).translate([depth, 0, 0])
        note["outer"] = [round(depth, 1), round(inner_w + 2 * wall, 1), round(plate_h, 1)]
        note["material_hint"] = "petg"
        return {"all": solid, "use": use}, note

    angle, back = lean(lo=45.0), num(p, k, "back", 100)
    profile, fx, rear_x, top_y, shelf_h, lip_h = _desk_profile(M, device, angle, back, t, r, bool(p.get("window", True)))
    solid = profile.extrude(width)
    if cable:
        slot = min(16.0, width * 0.3)
        solid = solid - M.Manifold.cube([fx + 1.0, lip_h + t + 2.0, slot]).translate([-1.0, shelf_h - t - 1.0, width / 2 - slot / 2])
    use = solid.rotate([90, 0, 0]).rotate([0, 0, 180]).translate([rear_x, 0, 0])
    note["outer"] = [round(rear_x, 1), round(width, 1), round(top_y, 1)]
    note["shelf_height"] = shelf_h
    return {"all": solid, "use": use}, note


def cable_holder(M, p):
    """A weighty desk block: channels run front to back, a cable clicks in from the top and stays in the round seat."""
    k = "cable_holder"
    count, cable = int(num(p, k, "count", 4)), num(p, k, "cable", 6)
    depth, wall, radius = num(p, k, "depth", 45), num(p, k, "wall", 7), num(p, k, "radius", 3)
    screws = bool(p.get("screws", False))
    hole = cable + 0.6                              # the cable should slide, not jam
    pitch = hole + wall
    ear = 14.0 if screws else 0.0
    length = wall + count * pitch + 2 * ear
    base = 4.0
    height = base + hole + max(6.0, hole * 0.9)     # fingers tall enough to guide the cable in
    C = M.CrossSection
    block = rounded_rect(M, length, height, radius)
    cuts = []
    for i in range(count):
        cx = ear + wall + hole / 2 + i * pitch
        cy = base + hole / 2
        cuts.append(C.circle(hole / 2, 64).translate([cx, cy]))
        slit = hole * 0.72                          # narrower than the cable: it clicks in and stays
        cuts.append(C.square([slit, height]).translate([cx - slit / 2, cy]))
    if ear:
        # ears are only as high as the base, so a screw head has room
        cuts.append(C.square([ear, height]).translate([0, base + 1.0]))
        cuts.append(C.square([ear, height]).translate([length - ear, base + 1.0]))
    profile = block - C.batch_boolean(cuts, M.OpType.Add)
    soft = min(0.8, wall * 0.12)                     # finger tops and slit mouths lose their sharp edge
    profile = profile.offset(-soft, M.JoinType.Round, 2.0, 16).offset(soft, M.JoinType.Round, 2.0, 16)
    solid = profile.extrude(depth)                  # profile on the bed: no supports, strong fingers (layers run along them)
    if screws:
        for x in (ear / 2, length - ear / 2):
            solid = solid - M.Manifold.cylinder(base + 4, 2.2, 2.2, 32).rotate([-90, 0, 0]).translate([x, -1, depth / 2])
    use = solid.rotate([90, 0, 0]).translate([0, depth, 0])
    return {"all": solid, "use": use}, {"outer": [round(length, 1), round(depth, 1), round(height, 1)], "slot": round(hole, 1)}


def _signed(pts):
    return sum(a[0] * b[1] - b[0] * a[1] for a, b in zip(pts, pts[1:] + pts[:1])) / 2


def holder(M, p):
    """
    A holder for whatever the customer measured: a remote control, a bottle, a tool, a broom.
      pocket  closed on four sides, open on top (things that may fall out sideways)
      cradle  like the pocket with the front cut down to a low lip: the thing is seen and grabbed from the front
      hook    a J for anything with a handle, a strap or a loop
      clip    a springy C for round handles (broom, tool): the handle snaps in from the front
    Pocket and cradle print standing the way they hang on the wall: every wall rises straight from the bed, the
    opening looks up, no supports (lying on a side or on the back plate a wall hangs over the cavity; 27 Sep 2026).
    Hook and clip are a side profile lying on the bed: no supports, and the layers run along the arms that carry
    the load. Screw holes are teardrops with the point up, so the slicer has nothing to support in them either.
    holes = keyhole: the head of a screw that is already in the wall goes through the wide hole, the holder slides
    down and hangs in the narrow slot above it.
    The hook has its own height (hook_h, measured inside) and bends of a chosen radius (bend) with the wall kept
    constant round them; hook and clip can have their long edges softened (edge).
    Solids are cut from one block or drawn as one 2D outline; nothing is glued onto a shared wall.
    """
    k = "holder"
    style = p.get("style", "cradle")
    if style not in ("cradle", "pocket", "hook", "clip"):
        raise Invalid("bad_choice", "style")
    obj_w, obj_d, height = num(p, k, "obj_w", 50), num(p, k, "obj_d", 25), num(p, k, "height", 60)
    t, gap, r = num(p, k, "wall", 3), num(p, k, "clearance", 0.8), num(p, k, "radius", 1.5)
    screws = bool(p.get("mount", True))
    holes = p.get("holes", "round")
    if holes not in ("round", "keyhole"):
        raise Invalid("bad_choice", "holes")
    keyhole = screws and holes == "keyhole"
    edge = min(num(p, k, "edge", 1), t * 0.4)                        # the wall must survive being drawn smaller
    C = M.CrossSection
    note = {"style": style, "screws": 0, "holes": holes if screws else "none", "warnings": []}
    hole_r = 2.3                                                     # a 4 mm wood screw with room to spare
    head_r = 4.8                                                     # its head goes through this one
    slide = 8.0                                                      # how far the holder slides down onto the screw

    def drop(rad, up):
        a = rad * 0.7071
        tip = [[-a, a], [a, a], [0.0, rad * 1.4142]] if up == "y" else [[-a, a], [-a, -a], [-rad * 1.4142, 0.0]]
        if _signed(tip) < 0:
            tip = tip[::-1]
        return C.circle(rad, 48) + C([tip])

    def bore_x(solid, y, z, length, up="z", hang="y"):
        # a teardrop along X; `up` is the axis that points away from the bed while printing, `hang` the one that
        # points up the wall. (y, z) is where the screw sits in the end. Drawn in 2D as (-Z, Y).
        cut = drop(hole_r, up)
        if keyhole:
            down = [0.0, -slide] if hang == "y" else [slide, 0.0]
            cut = (cut + drop(hole_r, up).translate(down)).hull() + drop(head_r, up).translate(down)
        return solid - cut.extrude(length + 2.0).rotate([0, 90, 0]).translate([-1.0, y, z])

    if style == "clip":
        d = obj_w
        if d > 60:
            raise Invalid("holder_clip_too_wide", "60")
        length = max(12.0, min(height, 60.0))                        # how much of the handle the clip holds
        if keyhole:
            length = max(length, 24.0)                               # room for the wide hole and the slot above it
        rin = d / 2 - 0.2                                            # a touch smaller: the handle is gripped
        rout = rin + t
        ear = 16.0 if keyhole else (14.0 if screws else 3.0)
        cx = t + rout - 1.0                                          # the ring sinks 1 mm into the plate
        plate = C.square([t, 2 * rout + 2 * ear]).translate([0, -rout - ear])
        ring = C.circle(rout, 96).translate([cx, 0]) - C.circle(rin, 96).translate([cx, 0])
        mouth = d * 0.72                                             # narrower than the handle: it snaps in and stays
        ring = ring - C.square([rout + 2.0, mouth]).translate([cx, -mouth / 2])
        profile = _rnd(M, plate + ring, min(r, t * 0.3))
        solid = _edged(M, profile, length, edge)
        if screws:
            for y in (-rout - ear / 2, rout + ear / 2):
                solid = bore_x(solid, y, length / 2 + (slide / 2 if keyhole else 0.0), t, hang="z")
            note["screws"] = 2
        solid = _on_floor(solid)
        note["outer"] = [round(cx + rout, 1), round(2 * rout + 2 * ear, 1), round(length, 1)]
        note["inner"] = [round(d, 1)]
        note["material_hint"] = "petg"
        return {"all": solid, "use": solid}, note

    inner_w, inner_d = obj_w + gap, obj_d + gap
    if style == "hook":
        hook_h = num(p, k, "hook_h", 30)                             # inside, from the floor of the hook to its tip
        tip = hook_h + t                                             # the upturned end that keeps the strap on
        bend = max(0.0, min(num(p, k, "bend", 6), inner_d / 2 - 0.01, hook_h))
        width = max(10.0, obj_w)
        if keyhole and width < 15:
            raise Invalid("holder_keyhole_narrow", "15")
        step = 30.0 if keyhole else 18.0                             # two holes above each other on a narrow hook
        plate_h = max(tip + (26.0 + step if keyhole and width < 40 else 32.0), 50.0)
        # one outline: a block with round bottom corners, the inside taken out with corners smaller by the wall
        prof = _bowl(M, inner_d + 2 * t, plate_h, bend + t if bend > 0.05 else 0.0)
        prof = prof - _bowl(M, inner_d, plate_h, bend).translate([t, t])
        prof = prof - C.square([t + 1.5, plate_h]).translate([t + inner_d - 0.5, tip])
        prof = _soft(M, prof, min(r, t * 0.45))
        solid = _edged(M, prof, width, edge)
        if screws:
            if width < 40:
                for y in (plate_h - 9.0, plate_h - 9.0 - step):
                    solid = bore_x(solid, y, width / 2, t)
            else:
                for z in (width * 0.25, width * 0.75):
                    solid = bore_x(solid, plate_h - 9.0, z, t)
            note["screws"] = 2
        use = _on_floor(solid.rotate([90, 0, 0]))
        note["outer"] = [round(inner_d + 2 * t, 1), round(width, 1), round(plate_h, 1)]
        note["inner"] = [round(width, 1), round(inner_d, 1), round(hook_h, 1)]
        note["bend"] = round(bend, 1)
        return {"all": _on_floor(solid), "use": use}, note

    # pocket and cradle: one block, the cavity taken out of it, the front cut down for the cradle
    total_w = inner_w + 2 * t
    plate_h = height + ((30.0 if keyhole else 22.0) if screws else 0.0)
    outline = C.square([t, plate_h]) + C.square([inner_d + 2 * t, height])
    rad = min(r, t * 0.3)
    # the bottom stands on the bed: its edges stay sharp, a rounded one would start in the air
    outline = _rnd(M, outline, rad) + C.square([inner_d + 2 * t, max(rad, 0.5)])
    solid = outline.extrude(total_w)
    # seen from above: the two front corners are round (upright edges, nothing hangs), the back stays flat on the wall;
    # the cavity follows with a radius smaller by the wall
    depth = inner_d + 2 * t
    corner = max(0.0, min(r, depth - t - 0.5, total_w / 2 - 0.5))
    if corner > 0.05:
        sliver = C.square([corner + 1.0, corner + 1.0]) - C.circle(corner, 96).translate([0.0, corner + 1.0])
        for z0, flip in ((-1.0, False), (total_w + 1.0, True)):
            cut = sliver.mirror([0, 1]) if flip else sliver
            cut = cut.translate([depth - corner, z0])
            solid = solid - cut.extrude(plate_h + 2.0).rotate([-90, 0, 0]).scale([1, 1, -1]).translate([0, -1.0, 0])
    inside = max(0.0, corner - t)
    hollow = C.square([inner_d, inner_w])
    if inside > 0.05:                                                # only the front corners, the back ones stay square
        hollow = rounded_rect(M, inner_d + inside + 1.0, inner_w, inside).translate([-inside - 1.0, 0]) ^ C.square([inner_d + 1.0, inner_w + 2.0]).translate([0, -1.0])
    solid = solid - hollow.extrude(height + 2.0).rotate([-90, 0, 0]).scale([1, 1, -1]).translate([t, t, t])
    note["corner"] = round(corner, 1)
    if style == "cradle":
        lip = max(8.0, min(height * 0.35, 25.0))
        if lip < height - 1:
            if inside <= 0.05:
                # a hair wider than the cavity: its sides and the sides of this cut never lie in one plane
                solid = solid - M.Manifold.cube([t + 2.0, height + 2.0, inner_w + 0.02]).translate([t + inner_d - 1.0, lip, t - 0.01])
            if corner > 0.05:
                # the round corners go with the front: the side walls end where the curve would begin
                solid = solid - M.Manifold.cube([corner + 1.1, height + 2.0, total_w + 2.0]).translate([depth - corner - 0.1, lip, -1.0])
        note["lip"] = round(lip, 1)
    if screws:
        zs = (total_w / 2,) if total_w < 40 else (total_w * 0.25, total_w * 0.75)
        for z in zs:
            solid = bore_x(solid, plate_h - 9.0, z, t, up="y")
        note["screws"] = len(zs)
    standing = _on_floor(solid.rotate([90, 0, 0]))
    note["outer"] = [round(inner_d + 2 * t, 1), round(total_w, 1), round(plate_h, 1)]
    note["inner"] = [round(inner_w, 1), round(inner_d, 1), round(height - t, 1)]
    return {"all": standing, "use": standing}, note


def cap(M, p):
    """
    The missing lid, plug or cover, from what the customer measured on the opening.
      push    goes OVER the rim: measure the outside of the neck or box
      plug    goes INTO the opening: measure the inside; a flange stops it, low ribs hold it
      thread  screws ONTO an outer thread: measure across the thread crests and the distance between two turns
    Round, rectangular or hexagonal (size_a is then the distance across the flats). The thread is always round (a PET
    bottle's is); a rectangular or hexagonal threaded cap has the round thread inside and the shape outside.
    `edge` rounds the top edge (the edge on the bed) of every cap; the cut view shows the inside, thread included.
    `outer` makes a push-on or threaded cap bigger outside than the neck asks for (0 = just the wall round the neck):
    the thread or the neck keeps its measured size, the wall grows. Metric threads (M6 to M30) are the same helix
    with a depth of half the pitch, which is close to the ISO form and forgiving when printed.
    A cap that is to hold liquid needs a seal (a printed thread alone does not, as the first PET cap showed):
      seal = lip    a thin ring under the top, 0.3 mm wider than the mouth of the neck (`mouth`): it is pressed into
                    the opening and seals on its inside, the way a bottle cap's plug seal does
      seal = liner  a shallow bed under the top for a disc of foam rubber or silicone, 2 mm thick
    Everything prints with its flat top on the bed, opening up: no supports, and the thread is cut as one smooth helix.
    The domed cap (round, push-on) prints the other way up, standing on its rim: the hollow under the dome is a cone
    of 45 degrees, which a printer builds in the air without help.
    """
    k = "cap"
    style = p.get("style", "push")
    shape = p.get("shape", "round")
    head = p.get("head", "flat")
    if style not in ("push", "plug", "thread") or shape not in ("round", "rect", "hex") or head not in ("flat", "dome"):
        raise Invalid("bad_choice", "style")
    if head == "dome" and (style != "push" or shape != "round"):
        raise Invalid("cap_dome_round")
    a, b = num(p, k, "size_a", 40), num(p, k, "size_b", 30)
    height, wall, top, gap = num(p, k, "height", 12), num(p, k, "wall", 2), num(p, k, "top", 2), num(p, k, "clearance", 0.3)
    pitch = num(p, k, "pitch", 3)
    edge = num(p, k, "edge", 1)
    outer = num(p, k, "outer", 0)
    seal = p.get("seal", "none")
    if seal not in ("none", "lip", "liner"):
        raise Invalid("bad_choice", "seal")
    grip = bool(p.get("grip", True))
    C = M.CrossSection
    note = {"style": style, "shape": shape, "warnings": []}

    def sealed(solid, narrowest, bed):
        """The seal under the top: `narrowest` is the cavity's smallest radius, `bed` the outline the liner lies in."""
        if seal == "lip":
            mouth = num(p, k, "mouth", 21.7)
            lip_out = mouth / 2 + 0.15                                   # 0.3 mm over the mouth: it has to be pressed in
            lip_in = lip_out - 0.9
            if lip_out > narrowest - 0.8:
                raise Invalid("cap_seal_wide", "%.1f" % (2 * (narrowest - 0.8)))
            if lip_in < 1.5:
                raise Invalid("cap_too_small")
            tall = min(2.5, height - 1.0)
            # the ring narrows a little towards its end: it finds the opening and tightens as it goes in
            ring = (C.circle(lip_out, 96) - C.circle(lip_in, 96)).extrude(tall + 0.01, 1, 0.0, [0.95, 0.95]).translate([0, 0, top - 0.01])
            note["seal"] = {"type": "lip", "mouth": round(mouth, 1), "ring": round(2 * lip_out, 1)}
            note["warnings"].append("seal_try")
            return solid + ring
        if seal == "liner":
            deep = min(0.6, max(0.3, top - 1.2))
            note["seal"] = {"type": "liner", "deep": round(deep, 1)}
            note["needs"] = ["liner"]
            return solid - bed.extrude(deep + 1.0).translate([0, 0, top - deep])
        return solid

    def views(solid, turned=True):
        """print pose, the pose in use, and the inside for the preview."""
        use = _on_floor(solid.rotate([180, 0, 0])) if turned else _on_floor(solid)
        return {"all": _on_floor(solid), "use": use, "cut": _on_floor(_half(M, use))}

    def outline(w, d, rad=None):
        if shape == "round":
            return C.circle(w / 2, 128)
        if shape == "hex":                                           # w is measured across the flats, as a spanner does
            return C.circle(w / math.sqrt(3), 6).rotate(30)
        return rounded_rect(M, w, d, min(3.0, min(w, d) * 0.15) if rad is None else rad).translate([-w / 2, -d / 2])

    def knurled(cs, radius):
        """Low round bumps all the way round: fingers hold the cap, the printer needs no supports for them."""
        count = max(12, int(round(2 * math.pi * radius / 6.0)))
        bumps = [C.circle(1.0, 16).translate([(radius + 0.2) * math.cos(2 * math.pi * i / count), (radius + 0.2) * math.sin(2 * math.pi * i / count)]) for i in range(count)]
        return C.batch_boolean(bumps, M.OpType.Add)

    def body_of(skin, radius, total, e):
        """The skin with a round top edge; the bumps start above the rounding, so it stays a clean curve."""
        solid = _rounded_foot(M, skin, total, e)
        if grip and shape == "round" and radius is not None:
            solid = solid + knurled(skin, radius).extrude(total - e).translate([0, 0, e])
        return solid

    if style == "plug":
        if min(a, b if shape == "rect" else a) - 2 * gap < 6:
            raise Invalid("cap_too_small")
        pw, pd = a - 2 * gap, (b if shape == "rect" else a) - 2 * gap
        flange = max(3.0, wall * 1.5)
        body = _rounded_foot(M, outline(pw + 2 * flange, pd + 2 * flange), top, min(edge, top - 0.4, flange - 0.5))
        # the plug narrows a little towards its end: it starts easily and tightens as it goes in
        core = outline(pw, pd).extrude(height + 0.01, 0, 0.0, [0.965, 0.965]).translate([0, 0, top - 0.01])
        solid = body + core
        if min(pw, pd) > 16:                                         # hollow from the open end: less plastic, a little give
            hollow = outline(pw - 2 * wall, pd - 2 * wall, 1.0).extrude(height + 1.0).translate([0, 0, top + 1.2])
            solid = solid - hollow
        ribs = 0
        for i in range(3):
            z = top + height * (0.3 + 0.22 * i)
            if z + 1.0 < top + height - 1.0:
                k_s = 1 - 0.035 * (z - top) / max(height, 1e-6)
                ring = (outline((pw + 0.5) * k_s, (pd + 0.5) * k_s) - outline((pw - 1.2) * k_s, (pd - 1.2) * k_s)).extrude(0.8)
                solid = solid + ring.translate([0, 0, z])
                ribs += 1
        note["outer"] = [round(pw + 2 * flange, 1), round(pd + 2 * flange, 1), round(top + height, 1)]
        note["fits"] = [round(a, 1)] if shape != "rect" else [round(a, 1), round(b, 1)]
        note["ribs"] = ribs
        note["material_hint"] = "petg"
        return views(solid), note

    if style == "thread":
        if a < 5:
            raise Invalid("cap_too_small")
        depth = max(0.5, min(1.5, pitch * 0.5))                      # how far the thread stands out of the neck (ISO: 0.54 of the pitch)
        if pitch > height:
            raise Invalid("cap_thread_short", "%d" % math.ceil(pitch))
        # one smooth helix: a circle set off the axis and turned once per pitch spans from the root to the crest
        rc = a / 2 - depth / 2 + gap
        turns = height / pitch
        cavity = C.circle(rc, 96).translate([depth / 2, 0]).extrude(height + 0.02, max(8, int(math.ceil(height / 0.25))), 360.0 * turns)
        outer_r = max(a / 2 + gap + wall, outer / 2)                   # a bigger cap round a small thread: the wall grows
        # the shape outside: round, hexagonal (the wall is measured at the flats) or rectangular (at least as deep as wide)
        ow, od = 2 * outer_r, (max(b, 2 * outer_r) if shape == "rect" else 2 * outer_r)
        solid = body_of(outline(ow, od), outer_r, top + height, min(edge, top + 0.6 * wall)) - cavity.translate([0, 0, top])
        solid = sealed(solid, a / 2 - depth + gap, C.circle(a / 2 + gap, 96))
        note["outer"] = [round(ow, 1), round(od, 1), round(top + height, 1)]
        note["fits"] = [round(a, 1)]
        note["thread"] = {"pitch": round(pitch, 2), "turns": round(turns, 1), "depth": round(depth, 2)}
        note["warnings"].append("thread_try")
        return views(solid), note

    # push-on cap
    iw, idp = a + 2 * gap, (b if shape == "rect" else a) + 2 * gap
    if head == "dome":
        ri, ro = iw / 2, iw / 2 + wall
        # half of the section, turned round the axis: the rim on the bed, a straight skirt, a half ball on top;
        # inside a straight bore and a cone of 45 degrees
        steps = 48
        arc = [(ro * math.cos(math.pi / 2 * i / steps), height + ro * math.sin(math.pi / 2 * i / steps)) for i in range(steps + 1)]
        profile = [(ri, 0.0), (ro, 0.0)] + arc + [(0.0, height + ri), (ri, height)]
        solid = M.Manifold.revolve(C([profile]), 128)
        note["outer"] = [round(2 * ro, 1), round(2 * ro, 1), round(height + ro, 1)]
        note["fits"] = [round(a, 1)]
        note["head"] = "dome"
        return views(solid, False), note
    ow, od = max(iw + 2 * wall, outer), (idp + 2 * wall if shape == "rect" else max(iw + 2 * wall, outer))
    solid = body_of(outline(ow, od), ow / 2, top + height, min(edge, top + 0.6 * wall)) - outline(iw, idp, 1.0 if shape == "rect" else None).extrude(height + 1.0).translate([0, 0, top])
    solid = sealed(solid, min(iw, idp) / 2, outline(iw, idp, 1.0 if shape == "rect" else None))
    note["outer"] = [round(ow, 1), round(od, 1), round(top + height, 1)]
    note["fits"] = [round(a, 1)] if shape != "rect" else [round(a, 1), round(b, 1)]
    return views(solid), note


NOT_PIECES = ("all", "use", "imprint", "cut")


def pieces_of(M, shown, parts):
    """
    The bodies of the shown solid, each named after the part it is: matched by volume and surface (they survive the
    moves and turns of a layout). A body no part answers for (pieces fused into one, a trimmed preview) is "body".
    Returns [(name, manifold)] with the pieces of one part next to each other.
    """
    known = []
    for name, solid in parts.items():
        if name in NOT_PIECES or solid.is_empty():
            continue
        for piece in solid.decompose():
            known.append((name, piece.volume(), piece.surface_area()))
    found = []
    for piece in shown.decompose():
        v, a = piece.volume(), piece.surface_area()
        if v <= 0:
            continue
        best, best_err = "body", 0.02                       # 2 %: meshing noise, never another part
        for name, kv, ka in known:
            err = max(abs(kv - v) / max(kv, 1e-9), abs(ka - a) / max(ka, 1e-9))
            if err < best_err:
                best, best_err = name, err
        found.append((best, piece))
    order = []
    for name, _ in found:
        if name not in order:
            order.append(name)
    return sorted(found, key=lambda f: order.index(f[0]))


def main(argv):
    if len(argv) < 4:
        out({"ok": False, "error": "usage", "code": "usage"})
    kind, dst = argv[1], argv[2]
    part = argv[4] if len(argv) > 4 else "all"
    view = argv[5] if len(argv) > 5 else "print"
    with_parts = len(argv) > 6 and argv[6] == "parts"
    try:
        import manifold3d as M
        import numpy as np
        p = json.loads(argv[3] or "{}")
        builders = {"organizer": organizer, "box": box, "phone_stand": phone_stand, "cable_holder": cable_holder, "modular": modular, "holder": holder, "cap": cap}
        if not isinstance(p, dict):
            raise Invalid("unknown_kind")
        if kind in builders:
            parts, notes = builders[kind](M, p)
        else:
            import creative_kinds
            if kind not in creative_kinds.BUILDERS:
                raise Invalid("unknown_kind")
            parts, notes = creative_kinds.BUILDERS[kind](M, Invalid, p)
        key = part if (part != "all" and part in parts) else ("use" if (view == "use" and "use" in parts) else "all")
        solid = parts[key]
        if solid.is_empty() or solid.status() != M.Error.NoError or solid.volume() <= 0:
            raise Invalid("empty_result")
        x0, y0, z0, x1, y1, z1 = solid.bounding_box()
        solid = solid.translate([-x0, -y0, -z0])
        listed = []
        if with_parts:
            chunks, at = [], 0
            for name, piece in pieces_of(M, solid, parts):
                m = piece.to_mesh()
                t = np.asarray(m.vert_properties, dtype=np.float32)[:, :3][np.asarray(m.tri_verts, dtype=np.int64)]
                if not len(t):
                    continue
                lo, hi = t.reshape(-1, 3).min(axis=0), t.reshape(-1, 3).max(axis=0)
                listed.append({"name": name, "tris": [at, at + len(t)], "bbox": [round(float(c), 2) for c in (*lo, *hi)]})
                chunks.append(t)
                at += len(t)
            tri = np.concatenate(chunks) if chunks else np.zeros((0, 3, 3), dtype=np.float32)
            tris = tri                                      # only its length is used below
        else:
            mesh = solid.to_mesh()
            verts = np.asarray(mesh.vert_properties, dtype=np.float32)[:, :3]
            tris = np.asarray(mesh.tri_verts, dtype=np.int64)
            tri = verts[tris]
        normals = np.cross(tri[:, 1] - tri[:, 0], tri[:, 2] - tri[:, 0])
        lens = np.linalg.norm(normals, axis=1, keepdims=True)
        normals = np.divide(normals, lens, out=np.zeros_like(normals), where=lens > 0)
        rec = np.zeros(len(tris), dtype=[("n", "<f4", 3), ("v", "<f4", (3, 3)), ("a", "<u2")])
        rec["n"], rec["v"] = normals, tri
        with open(dst, "wb") as fh:
            fh.write(b"matplace parametric".ljust(80, b" "))
            fh.write(np.uint32(len(tris)).tobytes())
            fh.write(rec.tobytes())
        out({"ok": True, "kind": kind, "part": key, "bbox": {"x": round(x1 - x0, 2), "y": round(y1 - y0, 2), "z": round(z1 - z0, 2)},
             "volume_mm3": round(solid.volume(), 1), "area_mm2": round(solid.surface_area(), 1), "triangles": int(len(tris)), "notes": notes, **({"parts": listed} if with_parts else {})})
    except Invalid as e:
        out({"ok": False, "code": e.code, "error": str(e)})
    except SystemExit:
        raise
    except Exception as e:  # noqa: BLE001
        out({"ok": False, "code": "failed", "error": str(e)})


if __name__ == "__main__":
    main(sys.argv)
