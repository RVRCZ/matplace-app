"""
Phone stand, round 2 (docs/AA.md), for param_tool.phone_stand: a cut-out shape in the rest of the stands that have
one, and four more stands - a corrugated S band, a flat plate with a pattern and a slide-in leg, a rounded Z band
with a stripe in a second colour, and an animal silhouette with a seat for the phone. Nothing here touches the six
stands of round 1: they call `cut_rest` only when a shape is asked for.
"""
import math

import shape2d as S

MARGIN_MIN = 6.0        # mm of frame round a cut-out, at least (or twice the thickness)
CUT_SHARE = 0.70        # a cut-out is at most this share of the rest's height
CUT_LEAST = 8.0         # mm: a cut-out narrower or lower than this is not worth the hole
RIB_AMP = 1.2           # mm: the corrugation's amplitude, each face
PLAY = 0.25             # mm of play in a slot, as in stand_kinds
WEB = 2.4               # mm of plastic between the holes of a pattern
STRIPE = 0.30           # the stripe's share of the band's thickness
STRIPE_DEPTH = 1.6      # mm: how deep the stripe lies in the band
SLOT_D = 9.0            # mm: how deep the silhouette's seat holds the phone
LIP = 3.0               # mm: the wall in front of a seat


# ── the shape of a cut-out or a silhouette ────────────────────────────────────────────────────────────────────────────

def outline(M, Invalid, p, width_mm=None, height_mm=None):
    """
    The outer contour of the chosen artwork (a library silhouette or an upload), as one outline without holes (eyes
    would fall out of a cut-out), scaled to a width or a height, its lower-left corner at the origin.
    Returns (CrossSection, info) or (None, {}) when no artwork was given.
    """
    if not p.get("artwork_path"):
        return None, {}
    try:
        art, info = S.load(M, p, width_mm or height_mm or 50.0, None, None, fill_holes=True)
    except S.ArtworkError as e:
        raise Invalid(e.code, str(e).split(": ", 1)[1] if ": " in str(e) else "")
    ring = S.outer_ring(art)
    if ring is None or len(ring) < 3:
        raise Invalid("svg_empty")
    cs = M.CrossSection([[(float(x), float(y)) for x, y in ring]], M.FillRule.NonZero)
    cs = S.fit(cs, width_mm=width_mm, height_mm=height_mm)
    return cs, info


def _cutter(M, cs, cx, cy, depth):
    """The shape as a cutter through a slab `depth` thick that starts at z = 0: centred on (cx, cy), a millimetre over on both sides."""
    w, h = S.size(cs)
    x0, y0, _, _ = cs.bounds()
    return cs.translate([cx - w / 2 - x0, cy - h / 2 - y0]).extrude(depth + 2.0).translate([0, 0, -1.0])


def cut_rest(M, Invalid, num, p, note, style, geo):
    """
    The cut-out shape in the rest of a round-1 stand, as a solid to take away from the stand in its print frame
    (before it is moved or turned). `geo` carries what each style knows about its rest:
      plate: width, back, t, angle, y_foot              (the leaning plate, cut before the fin and the hooks join)
      wave:  width, back, t, angle, h, r1               (the band's rest, its centre line through (0, h))
      desk:  width, back, t, angle, fx, shelf_h, r      (the rest's inner face from (fx, shelf_h))
      wall:  plate_w, plate_h, pocket_h, t, screws, inner_w
    Returns the cutter or None (nothing to cut: no shape, or no room for one), and tells why in the notes.
    """
    t = geo["t"]
    margin = max(MARGIN_MIN, 2.0 * t)
    share = num(p, "phone_stand", "cutout_size", 55) / 100.0
    if style == "wall":
        rest_w, rest_h = geo["plate_w"], geo["plate_h"] - geo["pocket_h"]
        if geo["screws"]:
            rest_w = geo["inner_w"] - 2 * (8.0 + 2.2) - 2 * 3.0          # between the two columns of screw holes
        cx, cy = geo["plate_w"] / 2, (geo["pocket_h"] + geo["plate_h"]) / 2
    elif style == "wave":
        lo, hi = geo["r1"] + t, geo["back"] - t                          # the straight part of the rest, not its bend
        rest_w, rest_h = geo["width"], hi - lo
        cx, cy = (lo + hi) / 2, geo["width"] / 2                         # along the rest, across the width
    else:                                                                # plate, desk: the whole rest
        rest_w, rest_h = geo["width"], geo["back"]
        cx, cy = geo["width"] / 2, geo["back"] / 2
    w = min(rest_w * share, rest_w - 2 * margin)
    h = min(CUT_SHARE * rest_h, rest_h - 2 * margin)
    if w < CUT_LEAST or h < CUT_LEAST:
        note.setdefault("warnings", []).append("stand_cutout_small")
        return None
    cs, _ = outline(M, Invalid, p, width_mm=w)
    if cs is None:
        return None
    if S.size(cs)[1] > h:
        cs = S.fit(cs, height_mm=h)
        note.setdefault("warnings", []).append("stand_cutout_small")
    if S.printability(M, cs, 0.4)["thin_pct"] > 15:
        note.setdefault("warnings", []).append("stand_cutout_fine")
    note["cutout"] = [round(S.size(cs)[0], 1), round(S.size(cs)[1], 1)]
    if style == "plate":
        # the plate's own frame: x across, y up the plate, z through it; moved and leaned as the plate is
        return _cutter(M, cs, cx, cy, t).rotate([geo["angle"], 0, 0]).translate([0, geo["y_foot"], t - 0.01])
    if style == "wall":
        return _cutter(M, cs, cx, cy, t)
    # wave and desk: the shape lies in the rest's plane (x along the rest, y across the width) and goes through it along
    # the rest's normal n = (sin a, -cos a); built flat, turned up on its edge, leaned, and carried to the rest's front face
    a = geo["angle"]
    nx, ny = math.sin(math.radians(a)), -math.cos(math.radians(a))
    if style == "wave":
        bx, by = -nx * t / 2, geo["h"] - ny * t / 2
    else:
        bx, by = geo["fx"], geo["shelf_h"]
    return _cutter(M, cs, cx, cy, t).rotate([90, 0, 0]).rotate([0, 0, a]).translate([bx, by, 0])


# ── paths and bands ───────────────────────────────────────────────────────────────────────────────────────────────────

def _fillet(c, d_in, d_out, radius, n=14):
    """The arc that rounds a corner `c` between an incoming direction and an outgoing one: points from the arc's start to its end."""
    cos_phi = -(d_in[0] * d_out[0] + d_in[1] * d_out[1])
    phi = math.acos(max(-1.0, min(1.0, cos_phi)))              # the interior angle between the two edges
    if phi < 1e-3 or phi > math.pi - 1e-3:
        return [c]
    lt = radius / math.tan(phi / 2)                             # from the corner to where the arc touches each edge
    a = (c[0] - d_in[0] * lt, c[1] - d_in[1] * lt)
    b = (c[0] + d_out[0] * lt, c[1] + d_out[1] * lt)
    turn = d_in[0] * d_out[1] - d_in[1] * d_out[0]              # left turn > 0
    nin = (-d_in[1], d_in[0]) if turn > 0 else (d_in[1], -d_in[0])
    o = (a[0] + nin[0] * radius, a[1] + nin[1] * radius)
    a0, a1 = math.atan2(a[1] - o[1], a[0] - o[0]), math.atan2(b[1] - o[1], b[0] - o[0])
    while a1 - a0 > math.pi:
        a1 -= 2 * math.pi
    while a1 - a0 < -math.pi:
        a1 += 2 * math.pi
    return [(o[0] + radius * math.cos(a0 + (a1 - a0) * i / n), o[1] + radius * math.sin(a0 + (a1 - a0) * i / n)) for i in range(n + 1)]


def _resample(pts, step):
    """A polyline with a point every `step` millimetres, and the arc length at each."""
    out, dist = [pts[0]], [0.0]
    s = 0.0
    for (x0, y0), (x1, y1) in zip(pts, pts[1:]):
        length = math.hypot(x1 - x0, y1 - y0)
        n = max(1, int(math.ceil(length / step)))
        for i in range(1, n + 1):
            f = i / n
            out.append((x0 + (x1 - x0) * f, y0 + (y1 - y0) * f))
            dist.append(s + length * f)
        s += length
    return out, dist


def corrugated(M, pts, t, pitch, amp, step=0.6):
    """
    A band `t` thick along a polyline with ribs on both faces: a half-round ridge `amp` high every `pitch` millimetres,
    the same places on both faces, ridges running across the band. (A thick sheet cannot follow a sine of this pitch
    as a true corrugation would - its inner side would break into cusps - so the band stays straight and carries the
    ribs.) Returns (outline, number of ribs). Printed on its side the ribs lie in the plane of the bed: nothing hangs over.
    """
    C = M.CrossSection
    pts, dist = _resample(pts, step)
    n = len(pts)
    band = _band(M, pts, t)
    ribs = []
    at = pitch / 2
    for i in range(n):
        if dist[i] < at:
            continue
        (xa, ya), (xb, yb) = pts[max(0, i - 1)], pts[min(n - 1, i + 1)]
        tx, ty = xb - xa, yb - ya
        length = math.hypot(tx, ty) or 1.0
        nx, ny = -ty / length, tx / length
        for side in (1.0, -1.0):
            ribs.append(C.circle(amp, 16).translate([pts[i][0] + side * nx * t / 2, pts[i][1] + side * ny * t / 2]))
        at += pitch
    return M.CrossSection.batch_boolean([band] + ribs, M.OpType.Add), len(ribs) // 2


def _band(M, pts, t):
    """A plain band of thickness t along a polyline: hulls of circles at consecutive points, as param_tool._ribbon."""
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


def _corner_patch(M, i, d_a, d_b, radius):
    """
    The plastic that fills the inner corner between two faces meeting at `i` (unit directions d_a and d_b away from it),
    leaving a fillet of `radius`: the triangle between the faces less the circle that touches both.
    """
    C = M.CrossSection
    cos_phi = d_a[0] * d_b[0] + d_a[1] * d_b[1]
    phi = math.acos(max(-1.0, min(1.0, cos_phi)))
    lt = radius / math.tan(phi / 2)
    a = (i[0] + d_a[0] * lt, i[1] + d_a[1] * lt)
    b = (i[0] + d_b[0] * lt, i[1] + d_b[1] * lt)
    bis = (d_a[0] + d_b[0], d_a[1] + d_b[1])
    norm = math.hypot(*bis) or 1.0
    o = (i[0] + bis[0] / norm * radius / math.sin(phi / 2), i[1] + bis[1] / norm * radius / math.sin(phi / 2))
    return C([[i, a, b]], M.FillRule.NonZero) - C.circle(radius, 48).translate([o[0], o[1]])


# ── the four stands ───────────────────────────────────────────────────────────────────────────────────────────────────

def ribbed(M, num, p, note, width, device, t, angle, back, cable):
    """
    The S band of the wave stand (same rest, bend, ground, front roll and seat) drawn as a corrugated sheet: both faces
    follow small waves `rib_pitch` apart, 1.2 mm high. One profile across the width, printed lying on its side.
    """
    C = M.CrossSection
    pitch = num(p, "phone_stand", "rib_pitch", 6)
    amp = min(RIB_AMP, 0.3 * t)
    a = math.radians(angle)
    ux, uy = math.cos(a), math.sin(a)
    h = t / 2
    R2 = max(8.0, t * 1.6)                                   # front roll (centre line radius): room for a plug under the seat
    seat_y = h + 2 * R2 - 2.5                                # the roll's top stands 2.5 mm above the seat: the lip
    x_rest = (seat_y - h) / math.tan(a) - h / uy
    x_end = x_rest - 1.5
    D = device + 3.0 + R2 - x_end
    tail = x_end + 75.0 * ux + 8.0
    R1 = max(8.0, 1.6 * t)
    # the ground sheet: from the tail behind the rest, forward under it, up and over the front roll, back along the seat
    # ... over the top of the roll (the lip) and down a gentle step onto the seat, both corners rounded so the band
    # does not fold on its inner side
    top, s1 = (-D, h + 2 * R2), (-D + 6.0, seat_y)
    d1 = (6.0, seat_y - top[1])
    d1 = (d1[0] / math.hypot(*d1), d1[1] / math.hypot(*d1))
    ground = ([(tail, h), (-D, h)] + _arc(-D, h + R2, R2, -90, -270, 20)[1:-1] + _fillet(top, (1.0, 0.0), d1, 4.0)
              + _fillet(s1, d1, (1.0, 0.0), 4.0) + [(x_end + 4.0, seat_y)])          # the seat runs into the rest, no notch between them
    # the rest: from the ground up and back, a touch below the ground's centre line so the two sheets run into one another
    rest = [(-ux * 1.0, h - uy * 1.0), (back * ux, h + back * uy)]
    ground_sheet, n_ground = corrugated(M, ground, t, pitch, amp)
    rest_sheet, n_rest = corrugated(M, rest, t, pitch, amp)
    sheets = [ground_sheet, rest_sheet]
    # the inner corner between the rest's front face and the ground's top face, filled with a fillet R1 (a closing
    # offset would smooth the ribs away as well)
    nx, ny = math.sin(a), -math.cos(a)
    s = (t - h + ny * h) / uy                                 # where the rest's front face crosses the ground's top face
    i = (-nx * h + s * ux, t)
    patch = _corner_patch(M, i, (-1.0, 0.0), (ux, uy), R1)
    prof = M.CrossSection.batch_boolean(sheets + [patch], M.OpType.Add)
    pieces = [c for c in prof.decompose() if c.area() > 5.0]              # a sliver where the sheet folds at the lip is no part of it
    prof = M.CrossSection.batch_boolean(pieces, M.OpType.Add) if len(pieces) > 1 else pieces[0]
    solid = prof.extrude(width)
    if cable:
        slot = min(16.0, width * 0.3)
        sx0, sx1 = -D + R2 - h, x_end
        solid = solid - M.Manifold.cube([sx1 - sx0 + 1.0, t + 2 * amp + 1.0, slot]).translate([sx0, seat_y - h - amp - 0.5, width / 2 - slot / 2])
    x0, y0, x1, y1 = prof.bounds()
    use = _on_floor(solid.rotate([90, 0, 0]).rotate([0, 0, 180]))
    note["outer"] = [round(x1 - x0, 1), round(width, 1), round(y1 - y0, 1)]
    note["rib_pitch"] = pitch
    note["ribs"] = n_ground + n_rest
    return {"all": _on_floor(solid), "use": use}, note


def _tiles(M, pattern, x0, y0, x1, y1):
    """The holes of a pattern inside a rectangle: tiles on a grid, each wholly inside it with a web of plastic to spare."""
    C = M.CrossSection
    J = M.JoinType.Round
    tiles = []

    def heart(size):
        pts = []
        for k in range(40):
            u = 2 * math.pi * k / 40
            x = 16 * math.sin(u) ** 3
            y = 13 * math.cos(u) - 5 * math.cos(2 * u) - 2 * math.cos(3 * u) - math.cos(4 * u)
            pts.append((x * size / 34.0, y * size / 34.0))
        return C([pts], M.FillRule.NonZero)

    if pattern == "hearts":
        tile, dx, dy, stagger = heart(9.0), 13.0, 12.0, True
    elif pattern == "squares":
        tile, dx, dy, stagger = C.square([8.0, 8.0]).translate([-4.0, -4.0]).offset(-2.0, J, 2.0, 8).offset(2.0, J, 2.0, 8), 11.0, 11.0, False
    elif pattern == "honeycomb":
        tile, dx, dy, stagger = C.circle(5.2, 6).rotate(30), 11.4, 11.4 * 0.866, True
    else:
        tile, dx, dy, stagger = None, 12.5, 11.0, True
    sizes = (5.0, 3.4, 4.4, 2.8)
    row = 0
    y = y0 + WEB + dy / 2
    while y <= y1 - WEB - dy / 2 + 0.01:
        x = x0 + WEB + dx / 2 + (dx / 2 if stagger and row % 2 else 0.0)
        col = 0
        while x <= x1 - WEB - dx / 2 + 0.01:
            cs = (C.circle(sizes[(row + 2 * col) % 4], 40) if tile is None else tile).translate([x, y])
            bx0, by0, bx1, by1 = cs.bounds()
            if bx0 >= x0 + WEB and bx1 <= x1 - WEB and by0 >= y0 + WEB and by1 <= y1 - WEB:
                tiles.append(cs)
            x += dx
            col += 1
        y += dy
        row += 1
    return M.CrossSection.batch_boolean(tiles, M.OpType.Add) if tiles else C.square([0, 0])


def _yz(solid):
    """A solid built in the XY plane and pulled along Z, turned so that X → Y, Y → Z and its pull runs along X."""
    return solid.rotate([90, 0, 0]).rotate([0, 0, 90])


def flatpack(M, Invalid, p, note, width, device, t, angle, back):
    """
    Two flat parts: a plate (`body`) with a pattern of holes, a shelf with a lip for the phone along its bottom and a
    slot in its lower third; a triangular leg (`stand`) whose tongue slides into the slot from behind and holds the
    plate at the angle. Both printed lying flat, each its own colour.
    """
    C = M.CrossSection
    pattern = str(p.get("pattern", "circles"))
    m = max(6.0, 2.0 * t)
    W, B = width, back
    plate = S.rounded_rect(M, W, B, min(6.0, m)).extrude(t)
    # the shelf for the phone along the bottom edge, its lip chamfered underneath so it prints without a support
    shelf = C([[(0, t - 0.01), (4.0, t - 0.01), (4.0, t + device - 1.5), (6.5, t + device + 1.0), (9.0, t + device + 1.0),
                (9.0, t + device + 4.0), (0, t + device + 4.0)]], M.FillRule.NonZero).extrude(W - 2 * m)
    plate = plate + _yz(shelf).translate([m, 0, 0])
    # the slot the leg's tongue goes through, in the lower third
    y_s = max(0.3 * B, 9.0 + m + 8.0)
    slot_w, slot_h = t + 2 * PLAY, 12.0 + 2 * PLAY
    plate = plate - M.Manifold.cube([slot_w, slot_h, t + 2.0]).translate([W / 2 - slot_w / 2, y_s - slot_h / 2, -1.0])
    # the pattern: above the shelf and below the top edge, in two fields that leave a web round the slot
    fields = [(m, 9.0 + m, W - m, y_s - slot_h / 2 - 4.0), (m, y_s + slot_h / 2 + 4.0, W - m, B - m)]
    holes = [_tiles(M, pattern, *f) for f in fields if f[3] - f[1] > 12.0]
    for cs in holes:
        if not cs.is_empty():
            plate = plate - cs.extrude(t + 2.0).translate([0, 0, -1.0])
    # the leg: a triangle whose front edge lies along the plate's back, a tongue through the slot, a foot behind
    a = math.radians(angle)
    L = max(0.42 * B, y_s + 6.0 + m)
    e = (math.cos(a), math.sin(a))
    out = (-math.sin(a), math.cos(a))                            # from the leg towards the plate
    foot = L * e[0] + 22.0
    tongue_l = t + 2 * PLAY + 1.5
    leg2d = C([[(0.0, 0.0), (foot, 0.0), (L * e[0], L * e[1])]], M.FillRule.NonZero)
    tongue = C([[(0, -6.0), (tongue_l, -6.0), (tongue_l, 6.0), (0, 6.0)]], M.FillRule.NonZero)
    tongue = tongue.rotate(math.degrees(math.atan2(out[1], out[0]))).translate([y_s * e[0] - 0.5 * out[0], y_s * e[1] - 0.5 * out[1]])
    leg2d = (leg2d + tongue).offset(-1.5, M.JoinType.Round, 2.0, 12).offset(1.5, M.JoinType.Round, 2.0, 12)
    lx0, ly0, lx1, ly1 = leg2d.bounds()
    leg = leg2d.extrude(t)
    # put together: the plate leans back on the leg, its bottom edge on the table, the leg in its middle plane
    plate_use = plate.rotate([angle, 0, 0])
    leg_use = _yz(leg2d.extrude(t)).translate([W / 2 - t / 2, 0.12 * math.sin(a), -0.12 * math.cos(a)])
    pu = plate_use.bounding_box()
    shift = -pu[2] if pu[2] < 0 else 0.0
    use = M.Manifold.compose([plate_use.translate([0, 0, shift]), leg_use.translate([0, 0, shift])])
    all_ = M.Manifold.compose([plate, leg.translate([W + 8.0 - lx0, -ly0, 0])])
    ub = use.bounding_box()
    note["outer"] = [round(W, 1), round(ub[4] - ub[1], 1), round(ub[5] - ub[2], 1)]
    note["needs"] = []                                           # nothing from outside: the leg slides into the slot
    note["pattern"] = pattern
    note["leg_slot"] = [round(slot_w, 2), round(slot_h, 2)]       # (not "slot": the page reads that as the cable's)
    return {"body": plate, "stand": leg, "all": all_, "use": use}, note


def ribbon(M, num, p, note, width, device, angle, back):
    """
    A thick band, round-edged, bent into a Z: a base on the table with a lip at the front, the rest rising from it up
    and back. Along the band's side runs a groove with a thinner band in it (`stripe`), the second colour; printed on
    its side with the groove up, the stripe flat beside it, pressed in afterwards. One colour if the customer keeps one.
    """
    C = M.CrossSection
    T = num(p, "phone_stand", "band", 10)
    a = math.radians(angle)
    ux, uy = math.cos(a), math.sin(a)
    nx, ny = math.sin(a), -math.cos(a)
    lip = max(10.0, device)
    s = T * (1 + ny) / (2 * uy)                                          # along the rest's front face up to the base's top
    xr = T / 2 + device + 4.0 + nx * T / 2 - s * ux                       # the rest's centre line meets the base here
    tail = xr + 75.0 * ux + 8.0                                            # the base reaches behind the phone's weight
    base_pts = [(0.0, T / 2 + lip), (0.0, T / 2), (tail, T / 2)]
    rest_pts = [(xr, T / 2), (xr + back * ux, T / 2 + back * uy)]
    J = M.JoinType.Round
    prof = (_band(M, base_pts, T) + _band(M, rest_pts, T)).offset(3.0, J, 2.0, 24).offset(-3.0, J, 2.0, 24)
    stripe_w = STRIPE * T
    stripe2d = _band(M, base_pts, stripe_w) + _band(M, rest_pts, stripe_w)
    body = prof.extrude(width) - stripe2d.extrude(STRIPE_DEPTH + 0.02).translate([0, 0, width - STRIPE_DEPTH])
    stripe = stripe2d.offset(-PLAY, J, 2.0, 24).extrude(STRIPE_DEPTH - 0.1)
    sx0, sy0, sx1, sy1 = stripe2d.bounds()
    x0, y0, x1, y1 = prof.bounds()
    in_place = stripe.translate([0, 0, width - STRIPE_DEPTH + 0.05])
    turn = lambda m: m.rotate([90, 0, 0]).rotate([0, 0, 180])              # noqa: E731 - lying on its side → standing on the table
    use = M.Manifold.compose([turn(body), turn(in_place)])
    ub = use.bounding_box()
    use = use.translate([-ub[0], -ub[1], -ub[2]])
    all_ = M.Manifold.compose([body.translate([-x0, -y0, 0]), stripe.translate([x1 - x0 + 8.0 - sx0, -sy0, 0])])
    note["outer"] = [round(x1 - x0, 1), round(width, 1), round(y1 - y0, 1)]
    note["band"] = T
    note["needs"] = []                                           # the stripe is pressed in by hand, nothing from outside
    return {"body": body.translate([-x0, -y0, 0]), "stripe": stripe.translate([-sx0, -sy0, 0]), "all": all_, "use": use}, note


def silhouette(M, Invalid, num, p, note, device, angle, back, t):
    """
    The chosen silhouette as a thick outline standing on its edge, `back` tall, printed lying on its face: a flat base
    along its bottom (lengthened with a foot when the shape stands on too little), in front of it a pad with a leaning
    seat the phone's bottom edge drops into; the phone leans on the shape. Across the phone it is `across` wide.
    """
    C = M.CrossSection
    across = num(p, "phone_stand", "across", 25)
    th = max(4.0, t)
    art, info = outline(M, Invalid, p, height_mm=back)
    if art is None:
        raise Invalid("artwork_required")
    if S.printability(M, art, th / 2)["thin_pct"] > 30:
        note.setdefault("warnings", []).append("stand_silhouette_thin")
    if back < 60:
        note.setdefault("warnings", []).append("stand_silhouette_low")
    ring = art - art.offset(-th, M.JoinType.Round, 2.0, 16)
    x0, y0, x1, y1 = art.bounds()
    H, Wd = y1 - y0, x1 - x0
    # what the shape stands on: its lowest millimetre; widened to 45 % of the height round its middle when it is less
    band = art ^ C.square([Wd, 1.0]).translate([x0, y0])
    bx0, bx1 = (x0, x1) if band.is_empty() else (band.bounds()[0], band.bounds()[2])
    cx = (x0 + x1) / 2
    if bx1 - bx0 < 0.45 * H:
        bx0, bx1 = min(bx0, cx - 0.225 * H), max(bx1, cx + 0.225 * H)
    # the pad in front with the seat: a slot leaning back at the angle, a lip in front of it, the floor 2 mm up
    a = math.radians(angle)
    pad_len = LIP + device + 1.0 + SLOT_D * math.cos(a) + 2.0
    pad_h = SLOT_D * math.sin(a) + 2.0 + 2.0
    front = bx0 - pad_len
    base = C.square([bx1 - front, th]).translate([front, y0]) + C.square([pad_len + 2.0, pad_h]).translate([front, y0])
    base = base.offset(1.0, M.JoinType.Round, 2.0, 12).offset(-1.0, M.JoinType.Round, 2.0, 12)
    slot = C.square([device + 1.0, SLOT_D + 4.0]).rotate(-(90.0 - angle)).translate([front + LIP, y0 + 2.0 + (device + 1.0) * math.cos(a)])
    prof = (ring + base) - slot
    px0, py0, px1, py1 = prof.bounds()
    prof = prof.translate([-px0, -py0])
    solid = prof.extrude(across)
    use = _on_floor(solid.rotate([90, 0, 0]).rotate([0, 0, 180]))
    note["outer"] = [round(px1 - px0, 1), round(across, 1), round(py1 - py0, 1)]
    note["needs"] = []
    note["silhouette"] = [round(Wd, 1), round(H, 1)]
    note["ring"] = th
    if info.get("ignored_outlines"):
        note.setdefault("warnings", []).append("outlines_ignored")
    return {"all": solid, "use": use}, note


def _on_floor(solid):
    b = solid.bounding_box()
    return solid.translate([-b[0], -b[1], -b[2]])
