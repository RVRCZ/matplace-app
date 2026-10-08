"""
Things made of a name for param_tool.py. Each builder returns (parts, notes) like the ones in creative_kinds.py;
the text goes through shape2d.

name_cup   a pen holder in the shape of a name: the name itself is the cup, as tall as a pencil needs. Printed
           standing, one piece; with a base plate the plate can be a second colour (one filament change at its top).
"""
import math

import shape2d as S

LIMITS = {
    "name_cup": {"width": (80, 250), "height": (40, 120), "wall": (1.2, 3), "floor": (1.2, 4)},
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


BUILDERS = {"name_cup": name_cup}
