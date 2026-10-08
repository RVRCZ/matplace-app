"""
A picture or a name that stands on something useful, for param_tool.py: a tray for sticky notes (more to come: a holder
for hair ties, a stand for a candle).

The figure is a silhouette printed lying flat, clean on both faces, with a foot under it. The foot is pushed into a slot
of the base, as the standing logo is (creative_kinds.logo): two parts, `body` and `stand`, each one colour, no supports.
`all` lays both on the bed, `use` shows them put together.
"""
import math

import shape2d as S

SINK, SHOW, PLAY = 6.0, 1.5, 0.25      # how deep the foot sits in the slot, how much of it stays in sight, the play round it

LIMITS = {
    "notes": {"width": (50, 150), "thickness": (2.4, 5), "pad": (50, 105), "depth": (8, 30)},
}


def _num(Invalid, p, kind, key, default):
    lo, hi = LIMITS[kind][key]
    try:
        v = float(p.get(key, default))
    except (TypeError, ValueError):
        raise Invalid("not_a_number", key)
    if math.isnan(v) or v < lo or v > hi:
        raise Invalid("out_of_range", "%s %s-%s" % (key, lo, hi))
    return v


def _figure(M, Invalid, p, width, t):
    """
    The picture or the name as a flat figure with a foot: (solid lying on the bed with its corner at the origin, where
    the foot begins and ends across it, the figure's width and height with the foot, the warnings).
    """
    try:
        art, info = S.load(M, p, width, p.get("font"), None, fill_holes=bool(p.get("artwork_path")) and not str(p.get("artwork_path")).lower().endswith(".svg"))
    except S.ArtworkError as e:
        raise Invalid(e.code, str(e).split(": ", 1)[1] if ": " in str(e) else "")
    art = S.fit(art, width_mm=width)
    w, h = S.size(art)
    if h > 250:
        raise Invalid("artwork_too_tall")
    warn = []
    if info.get("missing_chars"):
        warn.append("missing_chars")
    if info.get("ignored_outlines"):
        warn.append("outlines_ignored")
    x0, y0, x1, y1 = art.bounds()
    # what the figure stands on: the part of it within a few millimetres of its lowest point, at least 45 % of its width
    descent = float(info.get("descent_ratio", 0.0)) * width
    reach = descent + 2.0 if info.get("source") == "text" else min(8.0, max(3.0, h * 0.18))
    band = art ^ M.CrossSection.square([w, reach]).translate([x0, y0])
    bx0, bx1 = (x0, x1) if band.is_empty() else (band.bounds()[0], band.bounds()[2])
    if bx1 - bx0 < 0.45 * w:
        c = (bx0 + bx1) / 2
        bx0, bx1 = max(x0, c - 0.225 * w), min(x1, c + 0.225 * w)
    figure = art + M.CrossSection.square([bx1 - bx0, SINK + SHOW + reach]).translate([bx0, y0 - SINK - SHOW])
    figure, links = S.joined(M, figure, 0.6, max(2.0, 0.03 * w))      # a dot of an i, a star beside the moon: tied, or it stays on the bed
    if links:
        warn.append("pieces_tied")
    thin = S.printability(M, art)["thin_pct"]
    if thin > 20:
        warn.append("thin_lines")                              # a standing figure is more fragile than a relief
    fx0, fy0, fx1, fy1 = figure.bounds()
    flat = figure.translate([-fx0, -fy0]).extrude(t)
    return flat, (bx0 - fx0, bx1 - fx0), (fx1 - fx0, fy1 - fy0), {"warnings": warn, "thin_pct": thin, "missing_chars": info.get("missing_chars", [])}


def _slot(M, foot, t):
    """The slot a foot is pushed into, its corner at the origin: as long and as thick as the foot with play, a little deeper."""
    return M.Manifold.cube([foot[1] - foot[0] + 2 * PLAY, t + 2 * PLAY, SINK + 1.0])


def _together(M, flat, foot, size, t, base, slot_at, top):
    """
    The parts and what they look like put together. `slot_at` is the corner of the slot in the base, `top` the height of
    the base where the slot opens. In `use` the part of the foot that is in the slot is left out, so every face that
    shows belongs wholly to the figure or wholly to the base.
    """
    fw, fh = size
    upright = flat.rotate([90, 0, 0]).translate([slot_at[0] + PLAY - foot[0], slot_at[1] + PLAY + t, top - SINK])
    return {
        "body": flat, "stand": base,
        "all": M.Manifold.compose([flat, base.translate([fw + 8.0, 0, 0])]),
        "use": M.Manifold.compose([base, upright.trim_by_plane([0, 0, 1], top)]),
    }


def notes(M, Invalid, p):
    """
    A tray for a pad of sticky notes with a figure standing behind it. The tray is open on top, its front wall cut down
    in the middle for a thumb; behind it a block carries the slot, and, if asked, a groove for a pen in front of the figure.
    """
    k = "notes"
    C = M.CrossSection
    n = lambda key, d: _num(Invalid, p, k, key, d)       # noqa: E731
    width, t, pad, depth = n("width", 90), n("thickness", 3), n("pad", 76), n("depth", 12)
    pen = bool(p.get("pen", True))
    flat, foot, size, said = _figure(M, Invalid, p, width, t)
    wall, floor = 2.0, 2.0
    room = pad + 2.0                                           # a millimetre of air round the pad
    block = (12.0 if pen else 0.0) + t + 2 * PLAY + 8.0        # behind the tray: the pen's groove, the slot, 4 mm of plastic either side of it
    base_w = max(room + 2 * wall, foot[1] - foot[0] + 2 * PLAY + 8.0)
    base_d, base_h = wall + room + block, floor + depth
    base = S.rounded_rect(M, base_w, base_d, 3).extrude(base_h)
    cavity_x = (base_w - room) / 2
    base = base - M.Manifold.cube([room, room, depth + 1]).translate([cavity_x, wall, floor])
    base = base - M.Manifold.cube([room * 0.4, wall + 2, depth + 1]).translate([cavity_x + room * 0.3, -1, floor + 2.0])      # for the thumb
    back = wall + room                                         # where the block begins
    if pen:
        base = base - M.Manifold.cylinder(base_w + 2, 4.5, 4.5, 48).rotate([0, 90, 0]).translate([-1, back + 6.5, base_h + 0.5])
    slot_at = ((base_w - (foot[1] - foot[0])) / 2 - PLAY, base_d - 4.0 - t - 2 * PLAY)
    base = base - _slot(M, foot, t).translate([slot_at[0], slot_at[1], base_h - SINK])
    parts = _together(M, flat, foot, size, t, base, slot_at, base_h)
    notes_ = dict(said, outer=[round(max(base_w, size[0]), 1), round(base_d, 1), round(base_h - SINK + size[1], 1)], needs=["glue_optional"],
                  pad=[round(room, 1), round(depth, 1)],
                  # preview colours: what stands above the base behind the tray is the figure, the rest the base
                  regions=[{"x0": -1, "y0": round(slot_at[1] + PLAY - 0.05, 2), "x1": 9999, "y1": round(slot_at[1] + PLAY + t + 0.05, 2), "z0": round(base_h + 0.05, 2), "color": "orange"},
                           {"x0": -1, "y0": -1, "x1": 9999, "y1": 9999, "z0": -1, "color": "blue"}])
    return parts, notes_


BUILDERS = {"notes": notes}
