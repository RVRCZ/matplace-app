"""
Things made of a name for param_tool.py. Each builder returns (parts, notes) like the ones in creative_kinds.py;
the text goes through shape2d.

name_cup   a pen holder in the shape of a name: the name itself is the cup, as tall as a pencil needs. Printed
           standing, one piece; with a base plate the plate can be a second colour (one filament change at its top).
beads      a bead for every character of a text: a cube, a ball, a heart or a star with the letter on its top face
           (raised in a second colour, or sunk) and sunk mirrored into the face it lies on, a hole through its side.
"""
import math

import shape2d as S

LIMITS = {
    "name_cup": {"width": (80, 250), "height": (40, 120), "wall": (1.2, 3), "floor": (1.2, 4)},
    "beads": {"size": (8, 16), "hole": (1.5, 4), "relief": (0.4, 1.2)},
}
ROOM = 3.0                  # the pocket is the letters grown by this much: a stroke of handwriting is too narrow for a pencil
PLATE = 3.0                 # the base plate under the name: how thick and how far it reaches beyond the walls


def _num(Invalid, p, kind, key, default):
    lo, hi = LIMITS[kind][key]
    try:
        v = float(p.get(key, default))
    except (TypeError, ValueError):
        raise Invalid("not_a_number", key)
    if math.isnan(v) or v < lo or v > hi:
        raise Invalid("out_of_range", "%s %s-%s" % (key, lo, hi))
    return v


def _filled(M, cs, smallest):
    """The outline without the holes smaller than `smallest` mm²: the eye of an e is no place for a pencil."""
    import numpy as np
    keep = []
    for poly in cs.to_polygons():
        pts = np.asarray(poly, dtype=np.float64)
        area = 0.5 * float(np.sum(pts[:, 0] * np.roll(pts[:, 1], -1) - np.roll(pts[:, 0], -1) * pts[:, 1]))
        if area > 0 or -area >= smallest:
            keep.append(pts)
    return M.CrossSection(keep, M.FillRule.EvenOdd) if keep else cs


def _widest(M, cs):
    """The radius of the biggest circle that fits inside an outline: what decides whether a pencil goes in."""
    import numpy as np
    from PIL import Image, ImageDraw
    from scipy import ndimage
    x0, y0, x1, y1 = cs.bounds()
    cell = max(0.3, max(x1 - x0, y1 - y0) / 500)
    img = Image.new("1", (int((x1 - x0) / cell) + 3, int((y1 - y0) / cell) + 3), 0)
    draw = ImageDraw.Draw(img)
    rings = []
    for poly in cs.to_polygons():
        pts = [((float(x) - x0) / cell + 1, (float(y) - y0) / cell + 1) for x, y in poly]
        rings.append((sum(pts[i][0] * pts[(i + 1) % len(pts)][1] - pts[(i + 1) % len(pts)][0] * pts[i][1] for i in range(len(pts))), pts))
    for area, pts in sorted(rings, key=lambda ring: -ring[0]):
        draw.polygon(pts, fill=1 if area > 0 else 0)
    return float(ndimage.distance_transform_edt(np.asarray(img, dtype=bool)).max()) * cell


def name_cup(M, Invalid, p):
    k = "name_cup"
    n = lambda key, d: _num(Invalid, p, k, key, d)       # noqa: E731
    width, height, wall, floor = n("width", 160), n("height", 80), n("wall", 1.6), n("floor", 2)
    lines = [str(x).strip() for x in (p.get("lines") or []) if str(x).strip()]
    if not lines:
        raise Invalid("no_text")
    try:
        text, info = S.text(M, lines[:1], p.get("font"), 10)
    except S.ArtworkError as e:
        raise Invalid(e.code)
    J = M.JoinType.Round
    base = bool(p.get("base", False))
    # the name as wide as asked, walls (and the plate) included
    reach = ROOM + wall + (PLATE if base else 0)
    text = S.fit(text, width_mm=max(20.0, width - 2 * reach))
    warn = []
    if info.get("missing_chars"):
        warn.append("missing_chars")
    pocket = _filled(M, text.offset(ROOM, J, 2.0, 24).simplify(0.03), 150.0)
    outer = pocket.offset(wall, J, 2.0, 24)
    # letters that do not touch (a printed typeface, a space in the name) are tied by a bar as tall as the cup
    outer, links = S.joined(M, outer, wall, max(4.0, 2 * wall))
    if links:
        warn.append("pieces_tied")
    room = _widest(M, pocket)
    if room < 4.5:
        warn.append("cup_narrow")                            # a pencil is 7-8 mm: it needs a pocket 9 mm wide somewhere
    body = outer.extrude(height) - pocket.extrude(height).translate([0, 0, floor])
    notes = {"warnings": warn, "missing_chars": info.get("missing_chars", []), "pocket_mm": round(2 * room, 1), "links": links}
    if base:
        plate = outer.offset(PLATE, J, 2.0, 24)
        body = plate.extrude(PLATE) + body.translate([0, 0, PLATE - 0.01])
        # the plate in one colour, the name in another: everything above the plate is the name
        notes["color_change_mm"] = round(PLATE, 2)
        notes["regions"] = [{"x0": -9999, "y0": -9999, "x1": 9999, "y1": 9999, "z0": round(PLATE + 0.05, 2), "color": "orange", "exact": True},
                            {"x0": -9999, "y0": -9999, "x1": 9999, "y1": 9999, "z0": -1, "color": "white", "exact": True}]
    x0, y0, x1, y1 = (outer.offset(PLATE, J, 2.0, 8) if base else outer).bounds()
    notes["outer"] = [round(x1 - x0, 1), round(y1 - y0, 1), round(height + (PLATE if base else 0), 1)]
    return {"all": body}, notes


BEADS = ("cube", "ball", "heart", "star")
BEAD_GAP = 3.0              # between the beads on the bed
BEAD_ROW = 8                # beads in a row of the plate; a longer text goes on in the next row


def _bead(M, shape, size):
    """One bead standing on the bed, centred on the origin: (solid, its height, the width its letter may have)."""
    C = M.CrossSection
    if shape == "ball":
        r = size * 0.62                                      # a ball with its top and bottom cut flat: it lies on the bed and has a face for the letter
        box = M.Manifold.cube([2 * r, 2 * r, size]).translate([-r, -r, 0])
        return M.Manifold.sphere(r, 64).translate([0, 0, size / 2]) ^ box, size, 2 * math.sqrt(r * r - size * size / 4) * 0.8
    if shape == "heart":
        pts = [(16 * math.sin(t) ** 3, 13 * math.cos(t) - 5 * math.cos(2 * t) - 2 * math.cos(3 * t) - math.cos(4 * t)) for t in (2 * math.pi * (120 - i) / 120 for i in range(120))]
        outline = S.fit(C([pts]), width_mm=size * 1.25)
        w, h = S.size(outline)
        return outline.translate([-w / 2, -h / 2]).extrude(size * 0.6), size * 0.6, size * 0.6
    if shape == "star":
        pts = [((size * 0.78 if i % 2 == 0 else size * 0.44) * math.cos(math.pi / 2 + i * math.pi / 5), (size * 0.78 if i % 2 == 0 else size * 0.44) * math.sin(math.pi / 2 + i * math.pi / 5)) for i in range(10)]
        outline = C([pts]).offset(-0.6, M.JoinType.Round, 2.0, 16).offset(0.6, M.JoinType.Round, 2.0, 16)
        return outline.extrude(size * 0.6), size * 0.6, size * 0.6
    return S.rounded_rect(M, size, size, size * 0.2).translate([-size / 2, -size / 2]).extrude(size), size, size * 0.62


def beads(M, Invalid, p):
    k = "beads"
    n = lambda key, d: _num(Invalid, p, k, key, d)       # noqa: E731
    size, hole, relief = n("size", 10), n("hole", 2.5), n("relief", 0.6)
    shape = p.get("shape", BEADS[0])
    style = p.get("style", "raised")
    if shape not in BEADS:
        raise Invalid("bad_choice", "shape")
    if style not in ("raised", "engraved"):
        raise Invalid("bad_choice", "style")
    text = str((p.get("lines") or [""])[0])[:16]
    if not text.strip():
        raise Invalid("no_text")
    two_sides = bool(p.get("two_sides", True))
    blank, height, room = _bead(M, shape, size)
    if hole > height - 2.4:
        raise Invalid("bead_hole_big")                       # 1.2 mm of plastic above and below the hole, or the bead splits
    # the hole runs from side to side, half way up: a thread goes through a row of beads lying next to each other
    bore = M.Manifold.cylinder(4 * size, hole / 2, hole / 2, 24).translate([0, 0, -2 * size]).rotate([0, 90, 0]).translate([0, 0, height / 2])
    blank = blank - bore
    box = blank.bounding_box()
    pitch = (box[3] - box[0] + BEAD_GAP, box[4] - box[1] + BEAD_GAP)
    bodies, letters, missing, count = [], [], [], 0
    for i, ch in enumerate(text):
        at = [(i % BEAD_ROW) * pitch[0], -(i // BEAD_ROW) * pitch[1], 0]
        body = blank
        if not ch.isspace():
            try:
                glyph, info = S.text(M, [ch], p.get("font"), 10)
            except S.ArtworkError:
                glyph, info = None, {"missing_chars": [ch]}
            missing += [c for c in info.get("missing_chars", []) if c not in missing]
            if glyph is not None and not glyph.is_empty():
                gw, gh = S.size(glyph)
                scale = min(room / gw, room / gh)
                glyph = S.fit(glyph, width_mm=gw * scale)
                gw, gh = S.size(glyph)
                glyph = glyph.translate([-gw / 2, -gh / 2])
                if style == "raised":
                    letters.append(glyph.extrude(relief).translate([at[0], at[1], height]))
                else:
                    body = body - glyph.extrude(0.6 + 1).translate([0, 0, height - 0.6])
                if two_sides:                                # read from below once the bead is turned over: mirrored here
                    body = body - glyph.mirror([1, 0]).extrude(0.4 + 1).translate([0, 0, -1])
        bodies.append(body.translate(at))
        count += 1
    body = M.Manifold.compose(bodies)
    parts = {"body": body, "all": body}
    warn = ["missing_chars"] if missing else []
    notes = {"warnings": warn, "missing_chars": missing, "count": count, "parts": []}
    if letters:
        raised = M.Manifold.compose(letters)
        parts["text"] = raised.translate([0, 0, -height])     # the letters alone lie on the bed
        parts["all"] = body + raised.translate([0, 0, -0.01])
        notes["parts"] = ["body", "text"]
        notes["color_change_mm"] = round(height, 2)
        notes["regions"] = [{"x0": -9999, "y0": -9999, "x1": 9999, "y1": 9999, "z0": round(height + 0.02, 2), "color": "orange", "exact": True, "part": "text"},
                            {"x0": -9999, "y0": -9999, "x1": 9999, "y1": 9999, "z0": -1, "color": "white", "exact": True, "part": "body"}]
    x0, y0, z0, x1, y1, z1 = parts["all"].bounding_box()
    notes["outer"] = [round(x1 - x0, 1), round(y1 - y0, 1), round(z1 - z0, 1)]
    bx0, by0, _, bx1, by1, bz1 = blank.bounding_box()
    notes["each"] = [round(bx1 - bx0, 1), round(by1 - by0, 1), round(bz1 + (relief if letters else 0), 1)]
    return parts, notes


BUILDERS = {"name_cup": name_cup, "beads": beads}
