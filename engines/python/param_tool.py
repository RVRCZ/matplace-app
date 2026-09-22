#!/usr/bin/env python3
"""
Parametric everyday products as exact watertight solids (manifold3d): no AI, no cost per piece, milliseconds per model.

  param_tool.py <kind> <out.stl> <params-json> [part] [view]

kind:  organizer | box | phone_stand | cable_holder | vase | logo | stamp | qr   (the last four live in creative_kinds.py)
part:  all (default) | body | lid | saucer | handle | stand | imprint   (separate exports of multi-part products)
view:  print (default, the orientation it should be printed in) | use (how it stands on the desk; for previews)

All lengths in millimetres. Limits are enforced here as well as in the web layer, so the tool can never be asked
for an impossible or absurdly heavy shape. Prints one JSON object: {ok, bbox, volume_mm3, area_mm2, triangles, outer, notes}.
"""
import json
import math
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

LIMITS = {
    "organizer": {"width": (30, 400), "depth": (30, 400), "height": (10, 150), "rows": (1, 8), "cols": (1, 8), "wall": (0.8, 4), "floor": (0.8, 4), "radius": (0, 20)},
    "box": {"inner_w": (10, 300), "inner_d": (10, 300), "inner_h": (8, 200), "wall": (1.2, 5), "floor": (1.0, 5), "clearance": (0.1, 0.6)},
    "phone_stand": {"width": (50, 140), "device": (7, 20), "angle": (50, 80), "back": (60, 150), "thickness": (3, 8), "radius": (0, 4)},
    "cable_holder": {"count": (1, 8), "cable": (3, 14), "depth": (10, 80), "wall": (2, 12), "radius": (0, 6)},
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
        clean.append((x, y, w, d, str(b.get("color", "white"))[:12]))

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
    body = rounded_rect(M, ow, od, 2.5).extrude(oh) - M.Manifold.cube([iw, idp, ih + 1]).translate([wall, wall, floor])

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
        top_limit = ih - lip_h - (1.0 if lid else 0.0)
        if hx - hw / 2 < margin or hx + hw / 2 > span - margin or hz - hh / 2 < margin or hz + hh / 2 > top_limit - margin + 1.0:
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

    parts = {"body": body}
    notes = {"outer": [round(ow, 2), round(od, 2), round(oh + (floor if lid else 0), 2)], "inner": [iw, idp, ih], "lid": lid}
    if lid:
        lip_wall = max(1.2, min(wall, 2.0))
        lw, ld = iw - 2 * clearance, idp - 2 * clearance
        if lw - 2 * lip_wall < 2 or ld - 2 * lip_wall < 2:
            raise Invalid("box_too_small_for_lid")
        plate = rounded_rect(M, ow, od, 2.5).extrude(floor)
        ring = M.Manifold.cube([lw, ld, lip_h]) - M.Manifold.cube([lw - 2 * lip_wall, ld - 2 * lip_wall, lip_h + 1]).translate([lip_wall, lip_wall, 0])
        parts["lid"] = plate + ring.translate([wall + clearance, wall + clearance, floor])
        notes["lip_height"] = round(lip_h, 1)
        notes["clearance"] = clearance
        parts["all"] = parts["body"] + parts["lid"].translate([ow + 8.0, 0, 0])     # both on one plate, printed together
    else:
        parts["all"] = body
    return parts, notes


def phone_stand(M, p):
    """
    Desk stand drawn as one side profile: the phone sits on a shelf above the table between a front lip and a leaning
    back rest; the rest continues down to a rear foot. The profile is one closed outline with rounded corners, hollowed
    from the inside: an arch under the shelf, open at the bottom, so the charging cable drops through the slot in the
    shelf and runs out underneath; and a window in the rest that saves material. Printed lying on its side, no supports.
    """
    k = "phone_stand"
    width, device, angle = num(p, k, "width", 70), num(p, k, "device", 12), num(p, k, "angle", 65)
    back, t = num(p, k, "back", 100), num(p, k, "thickness", 5)
    r = min(num(p, k, "radius", 2.0), t * 0.45)
    a = math.radians(angle)
    ux, uy = math.cos(a), math.sin(a)                # along the rest
    nx, ny = math.sin(a), -math.cos(a)               # outward normal of the rest: backwards and down
    shelf_h, lip_h = 16.0, 12.0                      # room for a plug under the phone; how far the lip reaches up
    C, J = M.CrossSection, M.JoinType.Round

    def rnd(cs, rad):
        return cs.offset(-rad, J, 2.0, 24).offset(rad, J, 2.0, 24) if rad > 0.05 else cs

    fx = t + device + 1.0                            # foot of the rest: the phone leans between lip and rest with 1 mm play
    top_in = (fx + ux * back, shelf_h + uy * back)
    top_out = (top_in[0] + nx * t, top_in[1] + ny * t)
    rear_x = fx + ux * back * 0.58 + (shelf_h + uy * back * 0.58) * 0.5 + t   # the phone's weight stays well inside the footprint
    outer = C([[(0, 0), (rear_x, 0), top_out, top_in, (fx, shelf_h), (t, shelf_h), (t, shelf_h + lip_h), (0, shelf_h + lip_h)]])
    if r > 0.05:
        outer = outer.offset(r, J, 2.0, 24).offset(-2 * r, J, 2.0, 24).offset(r, J, 2.0, 24)   # every corner rounded
    big = 1000.0
    # everything hollow keeps a wall of t to the outside. The arch is inset from an outline that reaches below the
    # floor, so it stays open at the bottom.
    below = C([[(-big, -big), (big, -big), (big, shelf_h - t), (-big, shelf_h - t)]])
    sunk = outer + C([[(0, -50), (rear_x, -50), (rear_x, 0.5), (0, 0.5)]])
    profile = outer - rnd(sunk.offset(-t, J, 2.0, 24) ^ below, r * 1.5)
    if p.get("window", True):
        above = C([[(-big, shelf_h + t), (big, shelf_h + t), (big, big), (-big, big)]])
        window = rnd(outer.offset(-t, J, 2.0, 24) ^ above, 4.0)
        if window.area() > 200:
            profile = profile - window
    solid = profile.extrude(width)                   # the profile lies on the bed: prints without supports
    if p.get("cable", True):
        slot = min(16.0, width * 0.3)
        # through the lip and the shelf into the arch; the front leg below stays whole
        solid = solid - M.Manifold.cube([fx + 1.0, lip_h + t + 2.0, slot]).translate([-1.0, shelf_h - t - 1.0, width / 2 - slot / 2])
    # on the desk, turned so the viewer's default camera looks at the phone side, not at the back
    use = solid.rotate([90, 0, 0]).rotate([0, 0, 180]).translate([rear_x, 0, 0])
    return {"all": solid, "use": use}, {"outer": [round(rear_x, 1), round(width, 1), round(top_out[1] + r, 1)], "shelf_height": shelf_h}


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


def main(argv):
    if len(argv) < 4:
        out({"ok": False, "error": "usage", "code": "usage"})
    kind, dst = argv[1], argv[2]
    part = argv[4] if len(argv) > 4 else "all"
    view = argv[5] if len(argv) > 5 else "print"
    try:
        import manifold3d as M
        import numpy as np
        p = json.loads(argv[3] or "{}")
        builders = {"organizer": organizer, "box": box, "phone_stand": phone_stand, "cable_holder": cable_holder, "modular": modular}
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
             "volume_mm3": round(solid.volume(), 1), "area_mm2": round(solid.surface_area(), 1), "triangles": int(len(tris)), "notes": notes})
    except Invalid as e:
        out({"ok": False, "code": e.code, "error": str(e)})
    except SystemExit:
        raise
    except Exception as e:  # noqa: BLE001
        out({"ok": False, "code": "failed", "error": str(e)})


if __name__ == "__main__":
    main(sys.argv)
