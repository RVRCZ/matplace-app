"""
A headband with a decoration on top (devil horns, cat ears, bunny ears, antlers, a crown, a halo, a unicorn), for
param_tool.py. The builder returns (parts, notes) like the ones in stand_kinds.py.

How it prints. The band lies on its side: its arc is drawn in the plane of the bed and pulled up by the band's width,
so every layer runs along the arc (the direction a headband bends in) and nothing overhangs. A decoration is a flat
silhouette in the same plane.

mount = slot   the decoration is a part of its own (`details`), printed flat beside the band in its own filament: no
               filament change anywhere. Under it hangs a dovetail tongue that is pushed from the band's front edge into
               a pocket of a small boss on the band; the dovetail holds it against being pulled off, the pocket's floor
               against being pushed through. `all` lays the parts on the bed (the decorations inside the arc when they
               fit), `use` shows them put together, standing as worn.
mount = fused  one piece in one colour: the silhouettes grow out of the band, flush with the face that lies on the bed.

A new decoration is one function that draws it (base on y = 0, growing up, for the right-hand side of the head) and one
line in DECOR that says where its instances sit.
"""
import math

import shape2d as S

LIMITS = {"headband": {"head": (44, 64), "band": (8, 25), "thickness": (2, 4), "deco": (60, 160), "spacing": (30, 140),
                       "tilt": (-30, 30), "deco_t": (3, 8), "fit": (0.1, 0.4)}}

BREADTH = 0.27              # the breadth of a head as a share of its circumference
GRIP = 0.83                 # the band's inner width as a share of that breadth: it has to open to sit
RISE, DROP = 1.22, 1.15     # the arc above its widest point is taller than a circle, the legs under it run in
PHI = math.radians(38)      # how far the legs go on under the widest point
TAPER, THIN = 28.0, 0.72    # the legs thin down over this length to this share of the thickness
TOOTH_H, TOOTH_W, TOOTH_PITCH = 1.0, 1.8, 3.2
NECK, ROOT_W, SINK, WALL, FLOOR = 5.0, 7.6, 3.6, 2.2, 0.8      # the dovetail: its neck, its root, how deep it sits, the boss round it
GAP = 0.25                  # between a decoration and the band it hugs
ROOT = 40.0                 # how far a silhouette is drawn on under its base before the band trims it
RECESS = 0.6                # how deep the inner ear, the jewels and the grooves of a horn are sunk
NATURAL = {"horns": 45.0, "cat": 40.0, "bunny": 80.0, "antlers": 75.0, "crown": 34.0, "halo": 50.0, "unicorn": 70.0}
# the filaments a headband is drawn in when nobody chose any (the band, the decoration): the costume it usually is
PAINT = {"horns": ("#1c1c1c", "#c8102e"), "cat": ("#1c1c1c", "#1c1c1c"), "bunny": ("#f2f2f2", "#f2f2f2"), "antlers": ("#c8102e", "#7a4a21"),
         "crown": ("#e0b020", "#e0b020"), "halo": ("#f2f2f2", "#e0b020"), "unicorn": ("#f2f2f2", "#e0b020"), "none": ("#1c1c1c", "#1c1c1c")}
STRAIN_WARN = 1.4           # % of stretch in the outer fibre while the band is put on: above it PLA may crack


def _num(Invalid, p, key, default):
    lo, hi = LIMITS["headband"][key]
    try:
        v = float(p.get(key, default))
    except (TypeError, ValueError):
        raise Invalid("not_a_number", key)
    if math.isnan(v) or v < lo or v > hi:
        raise Invalid("out_of_range", "%s %s-%s" % (key, lo, hi))
    return v


def _cs(M, pts):
    """A polygon as a cross-section, whichever way round it was drawn."""
    area = sum(pts[i][0] * pts[(i + 1) % len(pts)][1] - pts[(i + 1) % len(pts)][0] * pts[i][1] for i in range(len(pts)))
    return M.CrossSection([list(pts) if area > 0 else list(reversed(pts))])


def _opened(M, cs, r):
    """Convex corners rounded by `r`, and anything thinner than 2r gone."""
    J = M.JoinType.Round
    return cs.offset(-r, J, 2.0, 24).offset(r, J, 2.0, 24)


def _closed(M, cs, r):
    """Concave corners filled by `r`."""
    J = M.JoinType.Round
    return cs.offset(r, J, 2.0, 24).offset(-r, J, 2.0, 24)


def _biggest(cs):
    """The largest piece of a cross-section: what a trim leaves beside it is a crumb."""
    pieces = cs.decompose()
    return max(pieces, key=lambda c: c.area()) if pieces else cs


def _stroke(M, pts, r0, r1, steps=6):
    """A line drawn with a round pen that thins from r0 to r1."""
    C = M.CrossSection
    total = sum(math.dist(pts[i], pts[i + 1]) for i in range(len(pts) - 1)) or 1.0
    out, done = [], 0.0
    for i in range(len(pts) - 1):
        seg = math.dist(pts[i], pts[i + 1])
        for k in range(steps):
            u0, u1 = k / steps, (k + 1) / steps
            a = (pts[i][0] + (pts[i + 1][0] - pts[i][0]) * u0, pts[i][1] + (pts[i + 1][1] - pts[i][1]) * u0)
            b = (pts[i][0] + (pts[i + 1][0] - pts[i][0]) * u1, pts[i][1] + (pts[i + 1][1] - pts[i][1]) * u1)
            ra = r0 + (r1 - r0) * (done + seg * u0) / total
            rb = r0 + (r1 - r0) * (done + seg * u1) / total
            out.append(C.batch_hull([C.circle(ra, 24).translate(a), C.circle(rb, 24).translate(b)]))
        done += seg
    return C.batch_boolean(out, M.OpType.Add)


def _blade(M, centre, half, n=40):
    """A shape given by its middle line and its half-width along it, both functions of u = 0 (base) … 1 (tip)."""
    left, right = [], []
    for i in range(n + 1):
        u = i / n
        x, y = centre(u)
        x2, y2 = centre(min(1.0, u + 1e-3))
        x1, y1 = centre(max(0.0, u - 1e-3))
        dx, dy = x2 - x1, y2 - y1
        d = math.hypot(dx, dy) or 1.0
        nx, ny = dy / d, -dx / d
        w = half(u)
        right.append((x + nx * w, y + ny * w))
        left.append((x - nx * w, y - ny * w))
    return _cs(M, right + left[::-1])


# ── the decorations: base on y = 0, growing up, drawn for the right-hand side (the head's middle is towards −x) ──────

def _horn(M, h):
    """A devil's horn: wide at the base, curving in towards the middle of the head, ending in a blunt point."""
    sweep = math.radians(52)
    r = h / math.sin(sweep)
    cs = _blade(M, lambda u: (-r * (1 - math.cos(sweep * u)), r * math.sin(sweep * u)), lambda u: 0.21 * h * (1 - u) ** 0.75 + 0.7)
    return {"cs": _opened(M, cs, 0.5), "recess": None, "bw": 0.21 * h}


def _cat(M, h):
    """A cat's ear: a triangle with bellied sides and a soft tip, the inner ear sunk."""
    w = 1.0 * h
    pts = [(-w / 2, 0), (w / 2, 0), (0.34 * w, 0.5 * h), (0.04 * w, h), (-0.28 * w, 0.52 * h)]
    cs = _opened(M, _cs(M, pts), 0.07 * h)
    inner = cs.offset(-0.19 * h, M.JoinType.Round, 2.0, 24) ^ M.CrossSection.square([4 * h, 4 * h]).translate([-2 * h, 0.16 * h])
    return {"cs": cs, "recess": None if inner.is_empty() else inner, "bw": w / 2}


def _bunny(M, h):
    """A rabbit's ear: long, widest above its middle, leaning a little outwards, the inner ear sunk."""
    cs = _blade(M, lambda u: (0.05 * h * u * u, h * u), lambda u: 0.12 * h * (1 - u) ** 1.5 + 0.19 * h * math.sin(math.pi * u ** 0.8) ** 0.75 + 0.5, 60)
    cs = _opened(M, cs, 0.5)
    inner = cs.offset(-0.085 * h, M.JoinType.Round, 2.0, 24) ^ M.CrossSection.square([4 * h, 4 * h]).translate([-2 * h, 0.15 * h])
    return {"cs": cs, "recess": None if inner.is_empty() else inner, "bw": 0.12 * h}


def _antler(M, h):
    """A reindeer's antler: a beam that curves outwards and up, with three tines."""
    beam = _stroke(M, [(0, -0.02 * h), (0.04 * h, 0.3 * h), (0.16 * h, 0.62 * h), (0.2 * h, 0.96 * h)], 0.085 * h, 0.04 * h)
    tines = [
        _stroke(M, [(0.04 * h, 0.28 * h), (-0.1 * h, 0.4 * h), (-0.2 * h, 0.56 * h)], 0.06 * h, 0.035 * h),
        _stroke(M, [(0.15 * h, 0.58 * h), (0.02 * h, 0.7 * h), (-0.05 * h, 0.86 * h)], 0.055 * h, 0.032 * h),
        _stroke(M, [(0.09 * h, 0.44 * h), (0.26 * h, 0.5 * h), (0.36 * h, 0.64 * h)], 0.055 * h, 0.032 * h),
    ]
    cs = _closed(M, M.CrossSection.batch_boolean([beam, *tines], M.OpType.Add), 0.035 * h)
    return {"cs": cs, "recess": None, "bw": 0.085 * h}


def _crown(M, h):
    """A crown over the top of the head: a band with five points, a ball on each, three jewels sunk into the band."""
    C = M.CrossSection
    w = 2.0 * h
    rim = 0.4 * h
    bits = [C.square([w, rim]).translate([-w / 2, 0])]
    for k, top in enumerate([0.72, 0.86, 1.0, 0.86, 0.72]):
        x = -w / 2 + w * (k + 0.5) / 5
        tip = top * h - 0.07 * h
        bits.append(_cs(M, [(x - w / 10, rim - 0.1), (x + w / 10, rim - 0.1), (x, tip)]))
        bits.append(C.circle(0.07 * h, 32).translate([x, tip]))
    cs = _closed(M, _opened(M, C.batch_boolean(bits, M.OpType.Add), 0.3), 0.04 * h)
    jewels = C.batch_boolean([C.circle(0.085 * h, 32).translate([x, 0.2 * h]) for x in (-0.3 * w, 0, 0.3 * w)], M.OpType.Add)
    return {"cs": cs, "recess": jewels, "bw": w / 2}


def _halo(M, h):
    """An angel's halo: a flat ring above the head on two posts that stand on a bar hugging the band."""
    C = M.CrossSection
    rx, ry, pen = 0.75 * h, 0.2 * h, 0.11 * h
    ring_y = h - ry
    oval = lambda a, b: _cs(M, [(a * math.cos(2 * math.pi * i / 96), b * math.sin(2 * math.pi * i / 96)) for i in range(96)])     # noqa: E731
    ring = (oval(rx, ry) - oval(rx - pen, ry - pen)).translate([0, ring_y])
    post_x, post_w = 0.42 * rx, 0.1 * h
    posts = [C.square([post_w, ring_y]).translate([sx * post_x - post_w / 2, 0]) for sx in (-1, 1)]
    bar = C.square([2 * post_x + post_w, 0.12 * h]).translate([-post_x - post_w / 2, 0])
    cs = _closed(M, C.batch_boolean([ring, bar, *posts], M.OpType.Add), 0.03 * h)
    return {"cs": cs, "recess": None, "bw": post_x + post_w / 2}


def _unicorn_horn(M, h):
    """A unicorn's horn: straight, thinning to a blunt point, with slanted grooves sunk into its face."""
    C = M.CrossSection
    cs = _opened(M, _blade(M, lambda u: (0.0, h * u), lambda u: 0.115 * h * (1 - u) + 0.8), 0.5)
    stripes = []
    for k in range(1, 7):
        y = h * k / 7.2
        stripes.append(_cs(M, [(-0.3 * h, y - 0.07 * h), (0.3 * h, y + 0.07 * h), (0.3 * h, y + 0.07 * h + 1.2), (-0.3 * h, y - 0.07 * h + 1.2)]))
    grooves = C.batch_boolean(stripes, M.OpType.Add) ^ cs
    return {"cs": cs, "recess": grooves, "bw": 0.115 * h, "edge": True}


def _unicorn_ear(M, h):
    return _cat(M, 0.46 * h)


# decoration → its instances: (the function that draws one; where it sits: "pair" = two of them `spacing` apart and
# mirrored, "centre" = one on top; the places of its tongues along the arc, in shares of its own base width; how much of
# the band's slope it follows where it stands: 1 = it stands square on the band, 0 = it stands upright whatever the band does)
DECOR = {
    "horns": [(_horn, "pair", [0.0], 1.0)],
    "cat": [(_cat, "pair", [0.0], 0.6)],
    "bunny": [(_bunny, "pair", [0.0], 0.35)],
    "antlers": [(_antler, "pair", [0.0], 0.5)],
    "crown": [(_crown, "centre", [-0.28, 0.28], 1.0)],
    "halo": [(_halo, "centre", [0.0], 1.0)],
    "unicorn": [(_unicorn_horn, "centre", [0.0], 1.0), (_unicorn_ear, "pair", [0.0], 1.0)],
    "none": [],
}


def _band(M, np, wi, th):
    """The band's middle line from tip to tip (right tip first, over the top), with its normals, lengths and thickness."""
    n = 360
    a, b, c = wi / 2 + th / 2, RISE * wi / 2 + th / 2, DROP * wi / 2
    t = np.linspace(-PHI, math.pi + PHI, n + 1)
    s = np.sin(t)
    pts = np.stack([a * np.cos(t), np.where(s >= 0, b, c) * s], axis=1)
    tan = np.gradient(pts, axis=0)
    tan /= np.linalg.norm(tan, axis=1, keepdims=True)
    nrm = np.stack([tan[:, 1], -tan[:, 0]], axis=1)            # outwards
    seg = np.linalg.norm(np.diff(pts, axis=0), axis=1)
    run = np.concatenate([[0.0], np.cumsum(seg)])
    end = np.minimum(run, run[-1] - run)                        # how far along the band from its nearer tip
    thick = th * (THIN + (1 - THIN) * np.minimum(1.0, end / TAPER))
    return {"pts": pts, "tan": tan, "nrm": nrm, "run": run, "end": end, "thick": thick, "top": n // 2, "t": t, "a": a, "b": b}


def _frame(np, band, along):
    """Where a thing sits on the band's outer face `along` mm from the top towards the right: (point, angle in degrees)."""
    run, top = band["run"], band["top"]
    at = float(np.clip(run[top] - along, run[1], run[-2]))
    i = int(np.clip(np.searchsorted(run, at), 1, len(run) - 1))
    u = (at - run[i - 1]) / (run[i] - run[i - 1])
    mix = lambda v: v[i - 1] + (v[i] - v[i - 1]) * u       # noqa: E731
    nrm = mix(band["nrm"])
    nrm = nrm / np.linalg.norm(nrm)
    spot = mix(band["pts"]) + nrm * mix(band["thick"]) / 2
    return (float(spot[0]), float(spot[1])), math.degrees(math.atan2(nrm[1], nrm[0])) - 90.0


def _put(cs, frame, turn=0.0):
    (x, y), angle = frame
    return cs.rotate(angle + turn).translate([x, y])


def headband(M, Invalid, p):
    import numpy as np
    C = M.CrossSection
    n = lambda key, d: _num(Invalid, p, key, d)       # noqa: E731
    head, w, th, scale = n("head", 52), n("band", 12), n("thickness", 2.6), n("deco", 100) / 100
    spacing, tilt, deco_t, fit = n("spacing", 70), n("tilt", 0), n("deco_t", 4), n("fit", 0.2)
    kind = p.get("decoration", "horns")
    mount = p.get("mount", "slot")
    if kind not in DECOR:
        raise Invalid("bad_choice", "decoration")
    if mount not in ("slot", "fused"):
        raise Invalid("bad_choice", "mount")
    teeth = bool(p.get("teeth", True))
    slot = mount == "slot" and bool(DECOR[kind])
    warn = []

    breadth = BREADTH * head * 10
    wi = GRIP * breadth
    band = _band(M, np, wi, th)
    pts, nrm, thick, run, end = band["pts"], band["nrm"], band["thick"], band["run"], band["end"]
    outer = pts + nrm * thick[:, None] / 2
    inner = pts - nrm * thick[:, None] / 2
    strip = _cs(M, [tuple(q) for q in outer] + [tuple(q) for q in inner[::-1]])
    bulb = thick[0] / 2 + 0.5                                   # a round end that does not dig in behind the ear
    solid2d = [strip, C.circle(bulb, 32).translate([float(pts[0][0]), float(pts[0][1])]), C.circle(bulb, 32).translate([float(pts[-1][0]), float(pts[-1][1])])]
    filled = _cs(M, [tuple(q) for q in outer])                 # the band and everything inside its arc: what trims a decoration
    quarter = run[band["top"]] - run[int(np.searchsorted(band["t"], 0.0))]      # from the top down to the widest point
    deco_t = min(deco_t, w)
    through = w - deco_t < 1.0                                  # too little left for a floor: the pocket goes through
    if through:
        deco_t = w

    # ── where the decorations sit ──
    placed = []                                                  # (drawing, frame, turn, mirrored, frames of its tongues)
    for draw, where, tongues, lean in DECOR[kind]:
        height = NATURAL[kind] * scale
        art = draw(M, height)
        sides = [1, -1] if where == "pair" else [0]
        for side in sides:
            along = side * min(spacing / 2, 0.8 * quarter)
            frame = _frame(np, band, along)
            mounts = [_frame(np, band, along + k * 2 * art["bw"]) for k in tongues]
            placed.append({"art": art, "frame": frame, "turn": -side * tilt - (1 - lean) * frame[1], "mirror": side == -1, "mounts": mounts, "height": height})

    boss_w, boss_h = ROOT_W + 2 * WALL, FLOOR + SINK
    pocket = _cs(M, [(-ROOT_W / 2, FLOOR), (ROOT_W / 2, FLOOR), (NECK / 2, boss_h), (NECK / 2, boss_h + 3), (-NECK / 2, boss_h + 3), (-NECK / 2, boss_h)])
    pockets = []
    if slot:
        for item in placed:
            for frame in item["mounts"]:
                solid2d.append(_put(C.square([boss_w, boss_h + th]).translate([-boss_w / 2, -th]), frame))
                pockets.append(_put(pocket, frame))
        filled = C.batch_boolean([filled, *solid2d[3:]], M.OpType.Add)
    body2d = _closed(M, C.batch_boolean(solid2d, M.OpType.Add), 2.0)
    filled = _closed(M, filled + body2d, 2.0)
    if teeth:
        # small teeth on the inner face from above the tips up to a little over the widest point: they hold in hair
        bits = []
        for side in (0, 1):
            at = 10.0
            while True:
                i = int(np.searchsorted(run, at)) if side == 0 else int(np.searchsorted(run, run[-1] - at))
                if band["t"][i] > math.radians(25) and band["t"][i] < math.pi - math.radians(25):
                    break
                q, m, tg = inner[i], -nrm[i], band["tan"][i]
                bits.append(_cs(M, [tuple(q - m * 0.3 - tg * TOOTH_W / 2), tuple(q - m * 0.3 + tg * TOOTH_W / 2), tuple(q + m * TOOTH_H)]))
                at += TOOTH_PITCH
        body2d = body2d + C.batch_boolean(bits, M.OpType.Add)
    body2d = _opened(M, body2d, 0.2)

    # ── the decorations as outlines in the band's plane ──
    shapes, recesses = [], []
    keep_off = filled.offset(GAP, M.JoinType.Round, 2.0, 24) if slot else _cs(M, [tuple(q) for q in pts])     # fused: trimmed at the band's middle line
    for item in placed:
        art = item["art"]
        cs, recess = art["cs"], art.get("recess")
        cs = cs + C.square([2 * art["bw"], ROOT + 0.6]).translate([-art["bw"], -ROOT])
        if item["mirror"]:
            cs = cs.mirror([1, 0])
            recess = recess.mirror([1, 0]) if recess is not None else None
        cs = _put(cs, item["frame"], item["turn"])
        if slot:
            # a foot round every boss, so that the tongue always has the decoration above it, whatever its tilt
            foot = _opened(M, C.square([boss_w + 3.2, ROOT + boss_h + 2.5]).translate([-boss_w / 2 - 1.6, -ROOT]), 1.2)
            cs = C.batch_boolean([cs, *[_put(foot, frame) for frame in item["mounts"]]], M.OpType.Add)
        cs = _biggest(_opened(M, cs - keep_off, 0.4))
        if cs.is_empty():
            raise Invalid("empty_result")
        if slot:
            tongue = pocket.offset(-fit, M.JoinType.Miter, 2.0)
            cs = C.batch_boolean([cs, *[_put(tongue, frame) for frame in item["mounts"]]], M.OpType.Add)
        if recess is not None:
            recess = _put(recess, item["frame"], item["turn"]) ^ (cs if art.get("edge") else cs.offset(-1.2, M.JoinType.Round, 2.0, 16))
            if not slot:
                recess = recess - body2d.offset(0.6, M.JoinType.Round, 2.0, 16)      # never a groove in the band itself
        shapes.append(cs)
        recesses.append(None if recess is None or recess.is_empty() else recess)
    if slot:
        for i in range(len(shapes)):
            for j in range(i + 1, len(shapes)):
                if (shapes[i] ^ shapes[j]).area() > 0.5:
                    raise Invalid("headband_overlap")           # two parts cannot share a place: more spacing or a smaller decoration

    # ── solids ──
    inner_w = float(inner[:, 0].max() - inner[:, 0].min())
    inner_h = float(inner[:, 1].max() - inner[:, 1].min())
    # what putting it on asks of the plastic: a half ring pulled open at its ends bends most at the top, the outer fibre
    # stretches by t/2 × the change of curvature (2δ/πR²); δ = from the band's width to well over the head's breadth
    radius = (band["a"] + band["b"]) / 2
    strain = lambda opening: round(100 * (th / 2) * 2 * opening / (math.pi * radius * radius), 2)     # noqa: E731
    worn, put_on = strain(breadth - wi), strain(1.6 * (breadth - wi) + 20)
    if put_on > STRAIN_WARN:
        warn.append("headband_stiff")
    said = {"warnings": warn, "inner": [round(inner_w, 1), round(inner_h, 1)], "strain_pct": {"worn": worn, "put_on": put_on},
            "deco_mm": round(max([i["height"] for i in placed], default=0.0), 1), "mount": "slot" if slot else "fused"}
    # the colours the pictures of the tool are drawn in: the ones picked for the parts, else the costume's own
    picked = p.get("part_colors") if isinstance(p.get("part_colors"), dict) else {}
    hexes = [(picked.get(part) or {}).get("hex") if isinstance(picked.get(part), dict) else None for part in ("body", "details")]
    said["paint"] = {"body": hexes[0] or PAINT[kind][0], "details": hexes[1] or PAINT[kind][1]}

    if not slot:
        solid = body2d.extrude(w)
        if shapes:
            flat = C.batch_boolean(shapes, M.OpType.Add).extrude(deco_t)
            cut = [r for r in recesses if r is not None]
            if cut:
                # the face that shows lies on the bed: what is sunk into it is two layers deep, no more
                flat = flat - C.batch_boolean(cut, M.OpType.Add).extrude(1.4).translate([0, 0, -1.0])
            solid = solid + flat
        x0, y0, z0, x1, y1, z1 = solid.bounding_box()
        said.update(outer=[round(x1 - x0, 1), round(y1 - y0, 1), round(z1 - z0, 1)], parts=[])
        # as worn: the arc upright, the face that lay on the bed to the front
        return {"all": solid, "use": solid.rotate([90, 0, 0]).rotate([0, 0, 180])}, said

    floor = 0.0 if through else w - deco_t - 0.2
    body = body2d.extrude(w)
    if pockets:
        body = body - C.batch_boolean(pockets, M.OpType.Add).extrude(w - floor + 1.0).translate([0, 0, floor])
    pieces = []
    for cs, recess in zip(shapes, recesses):
        piece = cs.extrude(deco_t)
        if recess is not None:
            piece = piece - recess.extrude(RECESS + 1.0).translate([0, 0, deco_t - RECESS])
        pieces.append(piece)
    worn_at = [piece.translate([0, 0, w - deco_t]) for piece in pieces]

    # on the bed: each decoration upright again (or lying on its side), in a row or a column inside the arc when that
    # fits there, else beside the band or under it, whichever keeps the plate smaller
    flats = []
    for item, cs, piece in zip(placed, shapes, pieces):
        (px, py), angle = item["frame"]
        flats.append((cs.translate([-px, -py]).rotate(-angle), piece.translate([-px, -py, 0]).rotate([0, 0, -angle])))

    def arranged(turn, column):
        """The decorations turned by `turn`, side by side or one over another, the lot centred on the origin."""
        boxes = []
        for up, piece in flats:
            up, piece = up.rotate(turn), piece.rotate([0, 0, turn])
            x0, y0, x1, y1 = up.bounds()
            boxes.append((up.translate([-x0, -y0]), piece.translate([-x0, -y0, 0]), x1 - x0, y1 - y0))
        wide = max(f[2] for f in boxes) if column else sum(f[2] for f in boxes) + 3.0 * (len(boxes) - 1)
        tall = sum(f[3] for f in boxes) + 3.0 * (len(boxes) - 1) if column else max(f[3] for f in boxes)
        out, at = [], 0.0
        for up, piece, fw, fh in boxes:
            dx, dy = ((wide - fw) / 2, at) if column else (at, (tall - fh) / 2)
            out.append((up.translate([dx - wide / 2, dy - tall / 2]), piece.translate([dx - wide / 2, dy - tall / 2, 0])))
            at += (fh if column else fw) + 3.0
        return out, wide, tall

    room = _cs(M, [tuple(q) for q in inner]).offset(-(TOOTH_H + 2.0), M.JoinType.Round, 2.0, 16)
    bx0, by0, bz0, bx1, by1, bz1 = body.bounding_box()
    rows, nested, best = None, False, None
    for turn in (0, 90):
        for column in (False, True):
            group, wide, tall = arranged(turn, column)
            for y in (0.4, 0.25, 0.1, -0.05, -0.2):
                if (C.batch_boolean([g[0].translate([0, y * band["b"]]) for g in group], M.OpType.Add) - room).area() < 0.01:
                    rows, nested = [g[1].translate([0, y * band["b"], 0]) for g in group], True
                    break
            if rows:
                break
            # beside the band, or under it
            for move, size in (([bx1 + 4.0 + wide / 2, (by0 + by1) / 2, 0], max(bx1 - bx0 + 4.0 + wide, by1 - by0, tall)),
                               ([(bx0 + bx1) / 2, by0 - 4.0 - tall / 2, 0], max(by1 - by0 + 4.0 + tall, bx1 - bx0, wide))):
                if best is None or size < best[0]:
                    best = (size, [g[1].translate(move) for g in group])
        if rows:
            break
    if rows is None:
        rows = best[1]
    details = M.Manifold.compose(rows)
    laid = M.Manifold.compose([body, *rows])
    x0, y0, z0, x1, y1, z1 = laid.bounding_box()
    if max(x1 - x0, y1 - y0) > 240:
        warn.append("headband_plate")                           # the parts do not fit one bed together: they print one after another
    said.update(outer=[round(x1 - x0, 1), round(y1 - y0, 1), round(z1 - z0, 1)], parts=["body", "details"],
                nested=nested, pocket={"depth": round(w - floor, 1), "through": through, "play": fit})
    use = M.Manifold.compose([body, *worn_at]).rotate([90, 0, 0])
    return {"body": body, "details": details, "all": laid, "use": use, "_shared": True}, said


BUILDERS = {"headband": headband}
