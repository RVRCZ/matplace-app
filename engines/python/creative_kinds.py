"""
Creative products for param_tool.py: vase / plant pot, logo or picture to 3D, stamp / embossing plate, QR sign.
Each builder returns (parts, notes) like the ones in param_tool.py. Text, SVG and raster input goes through shape2d.
"""
import math

import shape2d as S

LIMITS = {
    "vase": {"height": (40, 300), "top_d": (30, 250), "bottom_d": (30, 250), "wall": (0.8, 4), "floor": (0.8, 5), "ribs": (6, 48), "twist": (0, 360), "flute": (0, 45)},
    "logo": {"width": (20, 250), "thickness": (0.6, 50), "plate": (0.8, 6), "margin": (0, 20), "base_h": (8, 40)},
    "sign": {"text_height": (4, 80), "thickness": (1.2, 30), "relief": (0.4, 5), "margin": (2, 30), "radius": (0, 30)},
    "stamp": {"width": (15, 120), "relief": (0.8, 4), "plate": (2, 6), "text_height": (4, 40)},
    "qr": {"size": (30, 150), "plate": (1.6, 4), "relief": (0.6, 2)},
    "stencil": {"width": (30, 250), "thickness": (0.8, 3), "margin": (5, 40), "bridge": (0.8, 3)},
    "papel": {"width": (80, 250), "height": (80, 250), "thickness": (0.8, 2), "bridge": (0.8, 2.4), "darkness": (10, 90), "soften": (0, 3)},
    "lightbox": {"width": (80, 300), "depth": (25, 80), "wall": (1.6, 4), "face": (0.8, 2), "margin": (6, 40), "bridge": (0.8, 3), "cable": (3, 10), "clearance": (0.1, 0.6)},
    "cutter": {"width": (30, 150), "height": (10, 30), "wall": (0.8, 1.6), "flange": (3, 10), "flange_t": (1, 2.5)},
}
CHOICES = {
    "vase": {"profile": ("neck", "belly", "cone", "tulip"), "style": ("twist", "ribs", "smooth"), "purpose": ("vase", "pot")},
    "logo": {"mode": ("relief", "height", "cutout", "standing"), "shape": ("rounded", "rect", "circle")},
    "sign": {"shape": ("rounded", "rect", "oval", "heart", "star", "cloud", "bone", "hexagon", "banner", "arrow", "house", "car", "cat", "candy", "flower", "shield", "tag", "bubble", "circle", "fish"),
             "motif_at": ("left", "right", "above"), "ring_at": ("left", "right", "top"), "style": ("emboss", "engrave", "outline", "name", "stand"), "typeface": ("sans", "serif", "mono", "script")},
    "stamp": {"mode": ("raised", "recessed"), "handle": ("knob", "none")},
    "qr": {"plate_color": ("white", "yellow", "grey", "brown", "orange", "red", "green", "blue", "black"),
           "code_color": ("black", "blue", "green", "red", "brown", "orange", "grey", "yellow", "white")},
    "stencil": {},
    "papel": {"border": ("flowers", "diamonds", "dots", "hearts", "leaves", "stars", "none")},
    "lightbox": {"led": ("strip8", "strip10", "module"), "shape": ("rect", "round")},
    "cutter": {"edge": ("sharp", "straight"), "typeface": ("sans", "serif", "mono", "script")},
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


def _pick(Invalid, p, kind, key):
    options = CHOICES[kind][key]
    v = p.get(key, options[0])
    if v not in options:
        raise Invalid("bad_choice", key)
    return v


def _art(M, Invalid, p, width, cap=None):
    try:
        return S.load(M, p, width, p.get("font"), cap)
    except S.ArtworkError as e:
        raise Invalid(e.code, str(e).split(": ", 1)[1] if ": " in str(e) else "")


# ── vase / plant pot ───────────────────────────────────────────────────

def _through(np, ts, keys, values):
    """Catmull-Rom through the control points: a smooth silhouette that hits every one of them."""
    ts = np.asarray(ts, dtype=float)
    keys, values = np.asarray(keys, dtype=float), np.asarray(values, dtype=float)
    out = np.empty_like(ts)
    seg = np.clip(np.searchsorted(keys, ts) - 1, 0, len(keys) - 2)
    for i in range(len(keys) - 1):
        m = seg == i
        if not m.any():
            continue
        u = (ts[m] - keys[i]) / (keys[i + 1] - keys[i])
        p0, p1, p2, p3 = values[max(i - 1, 0)], values[i], values[i + 1], values[min(i + 2, len(values) - 1)]
        out[m] = 0.5 * (2 * p1 + (-p0 + p2) * u + (2 * p0 - 5 * p1 + 4 * p2 - p3) * u ** 2 + (-p0 + 3 * p1 - 3 * p2 + p3) * u ** 3)
    return out


def vase(M, Invalid, p):
    import numpy as np
    k = "vase"
    n = lambda key, d: _num(Invalid, p, k, key, d)       # noqa: E731
    h, top, bottom = n("height", 180), n("top_d", 62) / 2, n("bottom_d", 54) / 2
    wall, floor = n("wall", 1.6), n("floor", 1.6)
    profile, style, purpose = _pick(Invalid, p, k, "profile"), _pick(Invalid, p, k, "style"), _pick(Invalid, p, k, "purpose")
    ribs = int(n("ribs", 20))
    twist = n("twist", 200) if style == "twist" else 0.0
    amp = 0.0 if style == "smooth" else n("flute", 20) / 100.0
    belly = max(top, bottom) * 1.38                       # the widest point of the necked shape

    def radius(t):                                        # t = 0 bottom … 1 top
        base = bottom + (top - bottom) * t
        if profile == "neck":                             # low belly, a waist under the rim, a flared mouth
            return _through(np, t, [0, .10, .33, .62, .84, .93, 1.0],
                            [bottom, bottom * 1.25, belly, belly * .80, top * .84, top * .82, top])
        if profile == "belly":
            return base + 0.22 * max(top, bottom) * np.sin(np.pi * t)
        if profile == "tulip":
            return bottom + (top - bottom) * t ** 2.4 + 0.10 * bottom * np.sin(np.pi * t) * (1 - t)
        return base

    ts = np.linspace(0, 1, 50)
    r_min = float(np.min(radius(ts)))
    if r_min - wall < 6:
        raise Invalid("vase_too_narrow")
    seg = int(min(216, max(96, 6 * ribs))) if amp else 120
    ang = np.linspace(0, 2 * np.pi, seg, endpoint=False)
    wave = 1 + amp * (0.5 * (1 + np.cos(ribs * ang))) ** 1.8      # sharp ridge, wide valley: the flutes of a spun vase
    # across a flute the wall is thinner than its radial thickness; too steep a flank and the slicer has nothing to print
    slope = np.max(np.abs(np.gradient(wave, ang)) / wave) if amp else 0.0
    thin = wall * math.cos(math.atan(slope))
    if thin < 0.42:
        raise Invalid("vase_flutes_too_deep")
    ring = np.stack([wave * np.cos(ang), wave * np.sin(ang)], 1)
    base = M.CrossSection([ring])
    div = int(max(24, min(110, h / 2.0)))           # enough for a smooth profile, light enough for a live preview

    def shaped(z0, z1, inset):
        solid = M.Manifold.extrude(base, z1 - z0, div, twist * (z1 - z0) / h).translate([0, 0, z0])
        if z0 > 0 and twist:
            solid = solid.rotate([0, 0, twist * z0 / h])

        def warp(v):                                # the straight tube becomes the silhouette; the inside follows it, wall thick
            v = np.array(v, dtype=np.float64)
            s = radius(np.clip(v[:, 2] / h, 0, 1))
            v[:, 0] *= s
            v[:, 1] *= s
            if inset:
                rad = np.hypot(v[:, 0], v[:, 1])
                keep = np.maximum(rad - inset, 0.5) / np.maximum(rad, 1e-9)
                v[:, 0] *= keep
                v[:, 1] *= keep
            return v
        return solid.warp_batch(warp)

    body = shaped(0, h, 0.0) - shaped(floor, h + 1.0, wall)
    widest = float(np.max(radius(ts))) * (1 + amp)
    lean = math.degrees(math.atan(widest * math.radians(twist) / h))      # how far the flutes lean off vertical
    notes = {"outer": [round(2 * widest, 1), round(2 * widest, 1), round(h, 1)], "purpose": purpose, "wall_min": round(thin, 2)}
    warn = ([("thin_flutes")] if amp and thin < 0.8 else []) + (["steep_twist"] if lean > 55 else [])
    if warn:
        notes["warnings"] = warn
    parts = {"body": body}
    if purpose == "pot" and p.get("drainage", True):
        holes = [M.Manifold.cylinder(floor + 2, 4, 4, 32).translate([0, 0, -1])]
        if bottom > 28:
            for i in range(4):
                a = math.pi / 4 + i * math.pi / 2
                holes.append(M.Manifold.cylinder(floor + 2, 3.5, 3.5, 32).translate([bottom * 0.55 * math.cos(a), bottom * 0.55 * math.sin(a), -1]))
        parts["body"] = body = body - M.Manifold.batch_boolean(holes, M.OpType.Add)
        notes["drainage_holes"] = len(holes)
    if purpose == "pot" and p.get("saucer", True):
        sr = bottom * (1 + amp) + 10.0
        sw = max(wall, 1.6)
        saucer = M.Manifold.cylinder(14, sr, sr + 3, 96) - M.Manifold.cylinder(14, sr - sw, sr + 3 - sw, 96).translate([0, 0, max(floor, 1.6)])
        parts["saucer"] = saucer
        notes["saucer_d"] = round(2 * (sr + 3), 1)
        parts["all"] = body + saucer.translate([widest + sr + 3 + 8, 0, 0])
    else:
        parts["all"] = body
    return parts, notes


# ── logo / picture to 3D ─────────────────────────────────────────────────────

def logo(M, Invalid, p):
    k = "logo"
    n = lambda key, d: _num(Invalid, p, k, key, d)       # noqa: E731
    width, t, plate_t, margin = n("width", 80), n("thickness", 2), n("plate", 2), n("margin", 5)
    mode, shape = _pick(Invalid, p, k, "mode"), _pick(Invalid, p, k, "shape")
    if mode == "height":
        # every shade of the picture becomes a height: a photo, a drawing or a logo with gradients comes out as a
        # plastic relief on the plate. Text and SVG have no shades, they fall back to the plain relief.
        art_path = p.get("artwork_path")
        if art_path and not art_path.lower().endswith(".svg"):
            return _logo_height(M, Invalid, p, width, t, plate_t, margin, shape, art_path)
        mode = "relief"
    art, info = _art(M, Invalid, p, width, None)
    art = S.fit(art, width_mm=width)
    w, hgt = S.size(art)
    descent = float(info.get("descent_ratio", 0.0)) * width          # how far letters like J or g hang below the baseline, in millimetres
    if hgt > 300:
        raise Invalid("artwork_too_tall")
    warn = []
    thin = S.printability(M, art)["thin_pct"]
    if thin > 35:
        warn.append("thin_lines")
    if info.get("ignored_outlines"):
        warn.append("outlines_ignored")
    if info.get("missing_chars"):
        warn.append("missing_chars")
    if mode == "standing":
        # The logo stands in a slotted base. It prints lying flat (clean on both faces, its own colour) and is pushed in.
        sink, show = 6.0, 1.5                                           # how deep it sits in the slot / how much of the foot stays visible
        x0, y0, x1, y1 = art.bounds()
        reach = descent + 2.0 if info.get("source") == "text" else min(8.0, max(3.0, hgt * 0.18))   # up to the baseline and a bit: a J or g must not be the only letter caught
        band = art ^ M.CrossSection.square([w, reach]).translate([x0, y0])
        if band.is_empty():
            bx0, bx1 = x0, x1
        else:
            bx0, _, bx1, _ = band.bounds()
        if bx1 - bx0 < 0.45 * w:                                        # a round logo touches the ground in one point: give it a proper foot
            c = (bx0 + bx1) / 2
            bx0, bx1 = max(x0, c - 0.225 * w), min(x1, c + 0.225 * w)
        foot = M.CrossSection.square([bx1 - bx0, sink + show + reach]).translate([bx0, y0 - sink - show])
        figure = art + foot
        pieces = len(figure.decompose())
        if pieces > 1:
            warn.append("floating_pieces")
        if thin > 20 and "thin_lines" not in warn:
            warn.append("thin_lines")                                  # a standing shape is more fragile than a relief
        t = max(t, 2.4)                                                  # a standing shape needs some body
        logo_flat = S.fit(figure, width_mm=S.size(figure)[0]).extrude(t)
        fw, fh = S.size(figure)
        clearance = 0.25
        base_w, base_d, base_h = max(40.0, (bx1 - bx0) + 24.0), max(32.0, t + 26.0), max(sink + 2.0, n("base_h", 11))
        base = S.rounded_rect(M, base_w, base_d, 4).extrude(base_h)
        slot = M.Manifold.cube([(bx1 - bx0) + 2 * clearance, t + 2 * clearance, sink + 1.0]).translate([(base_w - (bx1 - bx0)) / 2 - clearance, (base_d - t) / 2 - clearance, base_h - sink])
        base = base - slot
        upright = logo_flat.rotate([90, 0, 0]).translate([0, t, 0])
        ux0, uy0, uz0, _, _, _ = upright.bounding_box()
        fx0 = figure.bounds()[0]
        upright = upright.translate([-ux0 + (base_w - (bx1 - bx0)) / 2 - (bx0 - fx0), -uy0 + (base_d - t) / 2, -uz0 + base_h - sink])
        lx0, ly0, lz0, lx1, ly1, lz1 = upright.bounding_box()
        shift = max(0.0, -lx0)
        parts = {
            "body": logo_flat, "stand": base,
            "all": M.Manifold.compose([logo_flat, base.translate([fw + 8.0, 0, 0])]),
            # the assembled view shows what you see on the shelf: the part of the logo that is hidden in the slot is left out,
            # so every visible face belongs wholly to the logo or wholly to the base (clean colours, no slivers across the joint)
            "use": M.Manifold.compose([S.rounded_rect(M, base_w, base_d, 4).extrude(base_h).translate([shift, 0, 0]),
                                       upright.translate([shift, 0, 0]).trim_by_plane([0, 0, 1], base_h)]),
        }
        notes = {"outer": [round(max(base_w, fw), 1), round(base_d, 1), round(base_h - sink + fh, 1)], "pieces": pieces, "needs": ["glue_optional"],
                 # preview colours: only what stands in the slot is the logo; the top face of the base belongs to the base
                 "regions": [{"x0": -1, "y0": round((base_d - t) / 2 - 0.05, 2), "x1": 9999, "y1": round((base_d + t) / 2 + 0.05, 2), "z0": round(base_h + 0.05, 2), "color": "orange"},
                             {"x0": -1, "y0": -1, "x1": 9999, "y1": 9999, "z0": -1, "color": "blue"}]}
        notes.update({"warnings": warn, "thin_pct": thin, "missing_chars": info.get("missing_chars", [])})
        return parts, notes
    if mode == "cutout":
        pieces = len(art.decompose())
        if pieces > 1:
            warn.append("separate_pieces")
        solid = art.extrude(t)
        if bool(p.get("bevel", False)) and t >= 1.2:
            # the top edge in four steps a layer high, as on the sign: printed in 0.2 mm layers that is a chamfer
            c = min(0.8, t * 0.3)
            solid = art.extrude(t - c)
            for i in range(4):
                ring = art.offset(-c * (i + 1) / 4, M.JoinType.Round, 2.0, 16)
                if not ring.is_empty():
                    solid = solid + ring.extrude(c / 4 + 0.01).translate([0, 0, t - c + c * i / 4 - 0.01])
        notes = {"outer": [round(w, 1), round(hgt, 1), round(t, 1)], "pieces": pieces}
    else:
        pw, ph = w + 2 * margin, hgt + 2 * margin
        if shape == "circle":
            d = math.hypot(w, hgt) + 2 * margin
            plate = M.CrossSection.circle(d / 2, 128).translate([d / 2, d / 2])
            pw = ph = d
        else:
            plate = S.rounded_rect(M, pw, ph, 0 if shape == "rect" else min(6, pw / 8))
        solid = plate.extrude(plate_t) + S.centre_on(art, pw, ph).extrude(t).translate([0, 0, plate_t - 0.01])
        notes = {"outer": [round(pw, 1), round(ph, 1), round(plate_t + t, 1)], "color_change_mm": round(plate_t, 2)}
    notes.update({"warnings": warn, "thin_pct": thin, "missing_chars": info.get("missing_chars", [])})
    return {"all": solid}, notes


# ── sign / name tag / keychain ───────────────────────────────────────────────

SIGN_SECOND_LINE = 0.7     # the height of a sign's second line against the first
# plates drawn as outlines (engines/shapes/<name>.svg, see _draw.py there): the text is fitted into the shape
TEMPLATES = ("heart", "star", "cloud", "bone", "hexagon", "banner", "arrow", "house", "car", "cat", "candy", "flower", "shield", "tag", "bubble", "circle", "fish")


def _beside(M, Invalid, p, art, cap, at):
    """The picture of a sign next to its text: as tall as the text (left, right) or a line and a half (above), a third of a letter away."""
    try:
        pic, pinfo = S.load(M, {"artwork_path": p["artwork_path"], "invert": p.get("invert", False)}, 100.0)
    except S.ArtworkError as e:
        raise Invalid(e.code, str(e).split(": ", 1)[1] if ": " in str(e) else "")
    x0, y0, x1, y1 = art.bounds()
    w, h = x1 - x0, y1 - y0
    gap = cap * 0.35
    tall = max(cap * 1.5, 6.0) if at == "above" else max(h, cap * 1.2)
    pic = S.fit(pic, height_mm=tall)
    if S.size(pic)[0] > 2 * tall:
        pic = S.fit(pic, width_mm=2 * tall)                  # a long flat picture must not push the text off the plate
    pw, ph = S.size(pic)
    px0, py0 = pic.bounds()[:2]
    if at == "above":
        return pic.translate([x0 + (w - pw) / 2 - px0, y1 + gap - py0]), pinfo
    return pic.translate([(x0 - gap - pw if at == "left" else x1 + gap) - px0, y0 + (h - ph) / 2 - py0]), pinfo


def _shaped_plate(M, Invalid, shape, pw, ph):
    """
    A plate in one of the drawn shapes, as big as it takes for a box of pw × ph (the text with its margins) to lie inside
    it; the box has its corner at the origin, as on a plain plate.
    """
    import os
    import re
    path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "shapes", shape + ".svg")
    try:
        # the shapes we draw ourselves are one path of straight lines: read it here, without the SVG reader and its import
        plain = re.search(r'<path d="M([-0-9. L]+)Z"/>', open(path, encoding="utf-8").read())
        if plain:
            outline = M.CrossSection([[(float(x), -float(y)) for x, y in (pt.split() for pt in plain.group(1).split("L"))]], M.FillRule.NonZero)
        else:
            outline = S.svg(M, path, 100.0)[0]
    except (S.ArtworkError, OSError, ValueError):
        raise Invalid("bad_choice", "shape")
    room = S.room(M, outline, pw / ph)
    if room is None:
        raise Invalid("shape_too_small")
    cx, cy, rw = room[:3]
    k = pw / rw
    return outline.translate([-cx, -cy]).scale([k, k]).translate([pw / 2, ph / 2])


def _ring_spot(M, cs, at):
    """Where an eyelet meets an outline on the asked side, level with its middle: (x, y, the way out x, y)."""
    C = M.CrossSection
    x0, y0, x1, y1 = cs.bounds()
    mx, my = (x0 + x1) / 2, (y0 + y1) / 2
    if at == "top":
        return mx, (cs ^ C.square([2.0, y1 - y0 + 2]).translate([mx - 1.0, y0 - 1])).bounds()[3], 0.0, 1.0
    sx0, _, sx1, _ = (cs ^ C.square([x1 - x0 + 2, 2.0]).translate([x0 - 1, my - 1.0])).bounds()
    return (sx1, my, 1.0, 0.0) if at == "right" else (sx0, my, -1.0, 0.0)


def sign(M, Invalid, p):
    """
    Text on a plate: raised (emboss), sunk (engrave) or raised as an outline. Keyring tab, raised rim, a bevelled top
    edge, and the plate and the text as separate parts for a two-colour print. Exact solids, milliseconds per preview.
    The plate is a box, an oval or one of the drawn shapes (a heart, a cloud, a bone…) grown round the text; a picture
    of the library or the visitor's own may stand next to the text and is treated as part of it.
    Two styles have no plate: `name` (the letters fattened are the body, a pendant) and `stand` (thick letters on a foot).
    """
    k = "sign"
    n = lambda key, d: _num(Invalid, p, k, key, d)       # noqa: E731
    cap, t, relief = n("text_height", 12), n("thickness", 3), n("relief", 1.2)
    margin, radius = n("margin", 5), n("radius", 6)
    shape, style = _pick(Invalid, p, k, "shape"), _pick(Invalid, p, k, "style")
    keyring, border, bevel, two = (bool(p.get(f, False)) for f in ("keyring", "border", "bevel", "two_color"))
    border = border and style != "engrave"
    if style == "engrave":
        relief = min(relief, t - 0.6)
    lines = [str(x).strip() for x in (p.get("lines") or []) if str(x).strip()]
    if not lines:
        raise Invalid("no_text")
    try:
        # the form calls the second line "smaller": a name and a line under it, not two headlines
        art, info = S.text(M, lines[:3], p.get("font"), cap, scales=[1.0, SIGN_SECOND_LINE, SIGN_SECOND_LINE])
    except S.ArtworkError as e:
        raise Invalid(e.code)
    warn = []
    if info.get("missing_chars"):
        warn.append("missing_chars")
    ring_at = _pick(Invalid, p, k, "ring_at")
    picture = None
    if p.get("artwork_path"):
        picture, pinfo = _beside(M, Invalid, p, art, cap, _pick(Invalid, p, k, "motif_at"))
        if pinfo.get("ignored_outlines"):
            warn.append("outlines_ignored")
    if style == "stand":
        return _sign_stand(M, art, picture, info, cap, t, warn)
    if style == "name":
        return _sign_name(M, p, art if picture is None else art + picture, info, cap, t, relief, keyring, two, warn, ring_at)
    if style == "outline":
        line = max(0.6, min(1.2, cap * 0.07))
        inner = art.offset(-line, M.JoinType.Round, 2.0, 16)
        art = art - inner if not inner.is_empty() else art
    if picture is not None:
        art = art + picture                                  # from here on the picture is a letter like any other
    w, hgt = S.size(art)
    thin = S.printability(M, art, 0.45)["thin_pct"]
    if thin > 35:
        warn.append("thin_lines")
    rim = 1.6 if border else 0.0
    pw, ph = w + 2 * margin + 2 * rim, hgt + 2 * margin + 2 * rim
    C = M.CrossSection
    if shape in TEMPLATES:
        plate2d = _shaped_plate(M, Invalid, shape, pw, ph)
        inner2d = plate2d.offset(-rim, M.JoinType.Round, 2.0, 24) if rim else None
    elif shape == "oval":
        pw, ph = pw * 1.12, ph * 1.25
        plate2d = C.circle(1.0, 128).scale([pw / 2, ph / 2]).translate([pw / 2, ph / 2])
        inner2d = C.circle(1.0, 128).scale([pw / 2 - rim, ph / 2 - rim]).translate([pw / 2, ph / 2]) if rim else None
    else:
        r = 0.0 if shape == "rect" else min(radius, min(pw, ph) / 2 - 0.5)
        plate2d = S.rounded_rect(M, pw, ph, r)
        inner2d = S.rounded_rect(M, pw - 2 * rim, ph - 2 * rim, max(0.0, r - rim)).translate([rim, rim]) if rim else None
    rim2d = plate2d - inner2d if inner2d is not None else None
    motif = S.centre_on(art, pw, ph)
    plate = plate2d.extrude(t)
    if rim2d is not None:
        # the rim is the plate's own outline carried up and a pocket taken out of the top: never a second body glued onto the
        # same outer wall (that union left slivers along the edge and the STL was not watertight). The rim is the edge treatment,
        # so a bevel is not applied together with it.
        body = plate2d.extrude(t + relief) - inner2d.extrude(relief + 1).translate([0, 0, t])
    elif bevel:
        # a soft top edge in four 0.2 mm steps: printed in 0.2 mm layers that IS a chamfer
        c = min(0.8, t * 0.3)
        plate = plate2d.extrude(t - c)
        for i in range(4):
            plate = plate + plate2d.offset(-c * (i + 1) / 4, M.JoinType.Round, 2.0, 16).extrude(c / 4 + 0.01).translate([0, 0, t - c + c * i / 4])
        body = plate
    else:
        body = plate
    whole2d = plate2d
    if keyring:
        # a round tab that bites a third into the plate, on the side asked for, level with the plate's middle
        r_out = max(5.0, min(ph * 0.28, 9.0)) if shape in TEMPLATES else max(5.0, ph * 0.28)
        r_in = max(2.0, r_out * 0.45)
        ex, ey, nx, ny = _ring_spot(M, plate2d, ring_at)
        cx, cy = ex + nx * r_out * 0.35, ey + ny * r_out * 0.35
        tab2d = C.circle(r_out, 64).translate([cx, cy])
        tab = tab2d.extrude(t)
        hole = M.Manifold.cylinder(t + 2, r_in, r_in, 48).translate([cx, cy, -1])
        plate = plate + tab - hole
        body = body + tab - hole
        whole2d = plate2d + tab2d
    if style == "engrave":
        body = body - motif.extrude(relief + 1).translate([0, 0, t - relief])
        parts = {"all": body}
    else:
        raised = motif.extrude(relief).translate([0, 0, t - 0.01])
        parts = {"all": body + raised}
        if two:
            if rim2d is not None:
                raised = raised + rim2d.extrude(relief).translate([0, 0, t - 0.01])
            parts["plate"] = plate
            parts["text"] = raised.translate([0, 0, -(t - 0.01)])
    ox0, oy0, ox1, oy1 = whole2d.bounds()
    notes = {"outer": [round(ox1 - ox0, 1), round(oy1 - oy0, 1), round(t + (0 if style == "engrave" else relief), 1)], "warnings": warn, "thin_pct": thin,
             "missing_chars": info.get("missing_chars", []), "two_color": two and style != "engrave"}
    if style != "engrave":
        notes["color_change_mm"] = round(t, 2)             # above the plate everything is the text (and the rim)
    if two and style != "engrave":
        notes["regions"] = [{"x0": -9999, "y0": -9999, "x1": 9999, "y1": 9999, "z0": round(t + 0.05, 2), "color": "orange"},
                            {"x0": -9999, "y0": -9999, "x1": 9999, "y1": 9999, "z0": -1, "color": "white"}]
        notes["color_change_mm"] = round(t, 1)
    return parts, notes


def _sign_stand(M, text, picture, info, cap, depth, warn):
    """
    The text stands on a shelf by itself: the letters as deep as asked, a foot under the last line, and under every
    line above it a rail that takes in what hangs below its baseline and reaches the capitals of the line beneath.
    All of it is one flat outline pulled up: printed lying on its back, without supports; "use" shows it standing.
    """
    C = M.CrossSection
    rows = info["rows"]
    left, bottom, right, _ = text.bounds()
    flat = text
    for upper, lower in zip(rows, rows[1:]):
        top, low = upper["base"] + 0.8, lower["base"] + lower["cap"] - 0.8
        flat = flat + C.square([upper["x1"] - upper["x0"] + 2.0, top - low]).translate([upper["x0"] - 1.0, low])
    ground = rows[-1]["base"]
    if picture is not None:
        px0, py0, px1, py1 = picture.bounds()
        if py0 < rows[0]["base"] + rows[0]["cap"]:           # beside the text: it stands on the foot like the letters
            # a picture that ends in a point (a heart) sinks into the foot until it holds by a width worth the name
            hold, sink = min(6.0, 0.25 * (px1 - px0)), 0.8
            while sink < 3.6:
                cut = picture ^ C.square([px1 - px0 + 2, 0.2]).translate([px0 - 1, py0 + sink])
                if not cut.is_empty() and cut.bounds()[2] - cut.bounds()[0] >= hold:
                    break
                sink += 0.4
            picture = picture.translate([0, ground - sink - py0])
        flat = flat + picture
        left, right = min(left, px0), max(right, px1)
    low = min(bottom, ground) - 3.0
    flat = flat + S.rounded_rect(M, right - left + 4.0, ground + 0.8 - low, 1.5).translate([left - 2.0, low])
    flat = flat.simplify(0.02)
    loose = sorted((piece.area() for piece in flat.decompose()), reverse=True)[1:]
    flat, links = S.joined(M, flat, 0.8, max(2.0, cap * 0.15))
    if any(area > (0.35 * cap) ** 2 for area in loose):
        warn.append("letters_tied")                         # a whole letter or the picture; accents and dots are tied without a word
    x0, y0, x1, y1 = flat.bounds()
    flat = flat.translate([-x0, -y0])
    tall = y1 - y0
    if depth < 0.18 * tall:
        warn.append("stand_tippy")
    lying = flat.extrude(depth)
    thin = S.printability(M, text, 0.45)["thin_pct"]
    if thin > 35:
        warn.append("thin_lines")
    notes = {"outer": [round(x1 - x0, 1), round(depth, 1), round(tall, 1)], "warnings": warn, "thin_pct": thin, "links": links,
             "missing_chars": info.get("missing_chars", []), "two_color": False, "stands": True}
    return {"all": lying, "use": lying.rotate([90, 0, 0]).translate([0, depth, 0])}, notes


def _sign_name(M, p, art, info, cap, t, relief, keyring, two, warn, ring_at="left"):
    """
    The name itself is the pendant: no plate, the letters a little fattened make the body and the letters as written
    stand raised on it. What would fall apart (separate letters, a heart after a space, the dot of an i) is tied to
    its nearest neighbour by a short link, so the piece is always one.
    """
    import numpy as np
    C = M.CrossSection
    J = M.JoinType.Round
    grow = max(1.0, cap * 0.1)
    link_w = max(2.0, cap * 0.2)
    base, links = S.joined(M, art.offset(grow, J, 2.0, 24).simplify(0.02), grow, link_w)
    tab_note = {}
    if keyring:
        r_out = max(4.0, cap * 0.36)
        r_in = max(1.8, r_out * 0.5)
        pts = np.vstack([np.asarray(poly) for poly in base.to_polygons()])
        x0, y0, x1, y1 = base.bounds()
        # the eyelet sits on the outermost part of the name on the asked side, at the height (or the place) where the name really is
        edge = max(1.0, cap * 0.15)
        if ring_at == "top":
            cx, cy = float(pts[pts[:, 1] > y1 - edge][:, 0].mean()), y1 + r_in + 0.4
            hold = (cx, y1 - grow)
        elif ring_at == "right":
            cx, cy = x1 + r_in + 0.4, float(pts[pts[:, 0] > x1 - edge][:, 1].mean())
            hold = (x1 - grow, cy)
        else:
            cx, cy = x0 - r_in - 0.4, float(pts[pts[:, 0] < x0 + edge][:, 1].mean())
            hold = (x0 + grow, cy)
        base = base + C.circle(r_out, 64).translate([cx, cy]) + (C.circle(link_w / 2, 24).translate([cx, cy]) + C.circle(link_w / 2, 24).translate(list(hold))).hull()
        base = base - C.circle(r_in, 48).translate([cx, cy])
        tab_note = {"eyelet_mm": round(2 * r_in, 1)}
    x0, y0, x1, y1 = base.bounds()
    base, art = base.translate([-x0, -y0]), art.translate([-x0, -y0])
    plate = base.extrude(t)
    raised = (art ^ base).extrude(relief).translate([0, 0, t - 0.01])
    parts = {"all": plate + raised}
    if two:
        parts["plate"] = plate
        parts["text"] = raised.translate([0, 0, -(t - 0.01)])
    thin = S.printability(M, art, 0.45)["thin_pct"]
    if thin > 35:
        warn.append("thin_lines")
    notes = {"outer": [round(x1 - x0, 1), round(y1 - y0, 1), round(t + relief, 1)], "warnings": warn, "thin_pct": thin, "links": links,
             "missing_chars": info.get("missing_chars", []), "two_color": two, "color_change_mm": round(t, 2)}
    notes.update(tab_note)
    if two:
        notes["regions"] = [{"x0": -9999, "y0": -9999, "x1": 9999, "y1": 9999, "z0": round(t + 0.05, 2), "color": "orange"},
                            {"x0": -9999, "y0": -9999, "x1": 9999, "y1": 9999, "z0": -1, "color": "white"}]
        notes["color_change_mm"] = round(t, 1)
    return parts, notes


def _logo_height(M, Invalid, p, width, t, plate_t, margin, shape, path):
    """Heightmap relief: dark = high (or light = high with invert), smoothed a little so the surface prints clean."""
    import numpy as np
    from PIL import Image, ImageOps
    from scipy import ndimage
    try:
        img = ImageOps.exif_transpose(Image.open(path))
    except Exception:  # noqa: BLE001
        raise Invalid("image_unreadable")
    if img.mode in ("RGBA", "LA") or "transparency" in img.info:
        rgba = img.convert("RGBA")
        img = Image.alpha_composite(Image.new("RGBA", rgba.size, (255, 255, 255, 255)), rgba)
    g = img.convert("L")
    step = 0.3                                                       # millimetres per sample: below what a nozzle shows
    cols = max(16, min(600, int(round(width / step))))
    rows = max(16, int(round(cols * g.size[1] / g.size[0])))
    if rows > 1000:
        raise Invalid("artwork_too_tall")
    a = np.asarray(g.resize((cols, rows), Image.LANCZOS), dtype=np.float32) / 255.0
    if bool(p.get("invert", False)):
        a = 1.0 - a
    hm = ndimage.gaussian_filter(1.0 - a, 0.7)                       # dark = high
    hm = (hm - hm.min()) / max(1e-6, hm.max() - hm.min())
    if float(hm.std()) < 0.02:
        raise Invalid("image_blank", "0")
    w, hgt = cols * step, rows * step
    z = plate_t - 0.01 + hm[::-1] * t                                 # image rows run top-down, Y runs up
    xs, ys = np.arange(cols) * step, np.arange(rows) * step
    X, Y = np.meshgrid(xs, ys)
    top = np.stack([X.ravel(), Y.ravel(), z.ravel()], 1)
    bottom = np.stack([X.ravel(), Y.ravel(), np.full(top.shape[0], plate_t - 0.6)], 1)
    verts = np.concatenate([top, bottom]).astype(np.float32)
    idx = np.arange(rows * cols).reshape(rows, cols)
    a0, b0, c0, d0 = idx[:-1, :-1].ravel(), idx[:-1, 1:].ravel(), idx[1:, 1:].ravel(), idx[1:, :-1].ravel()
    n = rows * cols
    faces = [np.stack([a0, b0, c0], 1), np.stack([a0, c0, d0], 1),                       # top, counter-clockwise seen from above
             np.stack([a0 + n, c0 + n, b0 + n], 1), np.stack([a0 + n, d0 + n, c0 + n], 1)]  # bottom, the other way round
    # walls along the four edges
    def wall(line):
        q = [np.stack([line[:-1], line[:-1] + n, line[1:] + n], 1), np.stack([line[:-1], line[1:] + n, line[1:]], 1)]
        return q
    for line, flip in ((idx[0, :], False), (idx[:, -1], False), (idx[-1, ::-1], False), (idx[::-1, 0], False)):
        faces += wall(line)
    tri = np.concatenate(faces).astype(np.uint32)
    relief = M.Manifold(M.Mesh(vert_properties=verts, tri_verts=tri))
    if relief.is_empty():
        raise Invalid("image_blank", "0")
    if relief.volume() < 0:
        relief = M.Manifold(M.Mesh(vert_properties=verts, tri_verts=tri[:, ::-1].copy()))
    pw, ph = w + 2 * margin, hgt + 2 * margin
    if shape == "circle":
        d = math.hypot(w, hgt) + 2 * margin
        plate2d = M.CrossSection.circle(d / 2, 128).translate([d / 2, d / 2])
        pw = ph = d
    else:
        plate2d = S.rounded_rect(M, pw, ph, 0 if shape == "rect" else min(6, pw / 8))
    relief = relief.translate([(pw - w) / 2, (ph - hgt) / 2, 0])
    solid = plate2d.extrude(plate_t) + relief
    notes = {"outer": [round(pw, 1), round(ph, 1), round(plate_t + t, 1)], "warnings": [], "thin_pct": 0, "missing_chars": [], "samples": [cols, rows]}
    return {"all": solid}, notes


# ── stamp / embossing plate ──────────────────────────────────────────────────

def stamp(M, Invalid, p):
    k = "stamp"
    n = lambda key, d: _num(Invalid, p, k, key, d)       # noqa: E731
    width, relief, plate_t = n("width", 50), n("relief", 1.6), n("plate", 3)
    mode, handle = _pick(Invalid, p, k, "mode"), _pick(Invalid, p, k, "handle")
    art, info = _art(M, Invalid, p, width, None)
    art = S.fit(art, width_mm=width)
    w, hgt = S.size(art)
    if hgt > 150:
        raise Invalid("artwork_too_tall")
    margin = 3.0
    pw, ph = w + 2 * margin, hgt + 2 * margin
    placed = S.centre_on(art, pw, ph)
    mirrored = placed.mirror([1, 0]).translate([pw, 0])                 # what is pressed reads the right way round
    plate = S.rounded_rect(M, pw, ph, 3)
    if mode == "raised":
        body = plate.extrude(plate_t) + mirrored.extrude(relief).translate([0, 0, plate_t - 0.01])
    else:
        body = plate.extrude(plate_t + relief) - mirrored.extrude(relief + 1).translate([0, 0, plate_t])
    imprint = plate.extrude(0.6) + placed.extrude(0.8).translate([0, 0, 0.59])
    warn = []
    thin = S.printability(M, art, 0.45)["thin_pct"]
    if thin > 30:
        warn.append("thin_lines")
    if info.get("missing_chars"):
        warn.append("missing_chars")
    parts = {"body": body, "imprint": imprint}
    notes = {"outer": [round(pw, 1), round(ph, 1), round(plate_t + relief, 1)], "warnings": warn, "thin_pct": thin, "missing_chars": info.get("missing_chars", []), "needs": []}
    if handle == "knob":
        r = max(8.0, min(15.0, min(pw, ph) * 0.3))
        knob = M.Manifold.cylinder(3, r + 5, r + 5, 64) + M.Manifold.cylinder(24, r * 0.75, r, 64).translate([0, 0, 3]) + M.Manifold.sphere(r, 48).scale([1, 1, 0.55]).translate([0, 0, 27])
        parts["handle"] = knob
        parts["all"] = body + knob.translate([pw + r + 5 + 8, ph / 2, 0])
        notes["needs"] = ["glue"]
    else:
        parts["all"] = body
    return parts, notes


# ── QR sign ──────────────────────────────────────────────────────────────────

# the filament colours of the preview (FILAMENT in resources/js/calc/viewer.ts), as r, g, b
_FILAMENT = {"white": (0.93, 0.9, 0.84), "black": (0.09, 0.09, 0.1), "grey": (0.55, 0.57, 0.6), "brown": (0.76, 0.6, 0.42), "red": (0.72, 0.13, 0.12),
             "blue": (0.13, 0.24, 0.47), "green": (0.16, 0.45, 0.27), "yellow": (0.92, 0.74, 0.16), "orange": (0.82, 0.32, 0.12)}


def _hex_rgb(value):
    """'#rrggbb' → (r, g, b) 0..1, None for anything else."""
    v = str(value or "")
    if len(v) != 7 or v[0] != "#":
        return None
    try:
        return tuple(int(v[i:i + 2], 16) / 255.0 for i in (1, 3, 5))
    except ValueError:
        return None


def _color(Invalid, p, kind, key):
    """A filament colour: one of the built-in names, or the code of a spool of the farm sent with its hex (<key>_hex)."""
    v = p.get(key, CHOICES[kind][key][0])
    if v in CHOICES[kind][key] and v in _FILAMENT:
        return str(v), _FILAMENT[v]
    rgb = _hex_rgb(p.get(key + "_hex"))
    if rgb is None or not isinstance(v, str) or not v or len(v) > 80:
        raise Invalid("bad_choice", key)
    return v, rgb


def _lightness(rgb):
    """Relative luminance of a filament colour, 0 black … 1 white (the sRGB formula contrast is measured with)."""
    lin = [c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4 for c in rgb]
    return 0.2126 * lin[0] + 0.7152 * lin[1] + 0.0722 * lin[2]


def qr(M, Invalid, p):
    import numpy as np
    import segno
    from PIL import Image, ImageDraw
    k = "qr"
    n = lambda key, d: _num(Invalid, p, k, key, d)       # noqa: E731
    size, plate_t, relief = n("size", 70), n("plate", 2.4), n("relief", 1.0)
    url = str(p.get("url", "")).strip()
    if len(url) < 4 or len(url) > 300:
        raise Invalid("qr_bad_text")
    try:
        code = segno.make(url, error="m", micro=False)
    except Exception:  # noqa: BLE001
        raise Invalid("qr_bad_text")
    matrix = np.array([[bool(v) for v in row] for row in code.matrix_iter(border=4)], dtype=bool)   # 4 modules of quiet zone, as the standard asks
    cells = matrix.shape[0]
    module = size / cells
    if module < 0.9:
        raise Invalid("qr_too_dense", "%d" % math.ceil(cells * 0.9))
    rects = []
    for r in range(cells):
        edges = np.flatnonzero(np.diff(np.concatenate(([0], matrix[r].view(np.int8), [0]))))
        for s, e in zip(edges[::2], edges[1::2]):
            y = (cells - 1 - r) * module
            rects.append(np.array([(s * module, y), (e * module, y), (e * module, y + module * 1.001), (s * module, y + module * 1.001)], dtype=np.float64))
    modules = M.CrossSection(rects, M.FillRule.NonZero)

    # the exported drawing is read back at the target size: every module centre must come out as it went in
    px = 6
    img = Image.new("1", (cells * px, cells * px), 0)
    draw = ImageDraw.Draw(img)
    for poly in modules.to_polygons():
        pts = [(float(x) / module * px, (cells - float(y) / module) * px) for x, y in poly]
        area = sum(pts[i][0] * pts[(i + 1) % len(pts)][1] - pts[(i + 1) % len(pts)][0] * pts[i][1] for i in range(len(pts)))
        draw.polygon(pts, fill=1 if area < 0 else 0)          # image Y points down: outer rings come out clockwise
    back = np.asarray(img, dtype=bool)[px // 2::px, px // 2::px]
    if back.shape != matrix.shape or not np.array_equal(back, matrix):
        raise Invalid("qr_unreadable")

    label = str(p.get("label", "")).strip()[:40]
    stand = bool(p.get("stand", False))
    foot = 10.0 if stand else 0.0                                       # a blank strip that sits in the stand
    label_h = 0.0
    parts_2d = [modules.translate([0, foot])]
    if label:
        cap = max(4.0, size * 0.075)
        txt, info = _art(M, Invalid, {"lines": [label], "font": p.get("font")}, None, cap)
        tw, th = S.size(txt)
        if tw > size - 8:
            txt = S.fit(txt, width_mm=size - 8)
            tw, th = S.size(txt)
        label_h = th + 6.0
        x0, y0, _, _ = txt.bounds()
        parts_2d = [modules.translate([0, foot + label_h]), txt.translate([-x0 + (size - tw) / 2, -y0 + foot + 3.0])]
    # the hanging hole sits on a strip of its own above the code, so the quiet zone stays blank; a sign in a stand has no hole
    hole = bool(p.get("hole", False)) and not stand
    head = 9.0 if hole else 0.0
    total_h = size + label_h + foot + head
    plate = S.rounded_rect(M, size, total_h, 4)
    if hole:
        plate = plate - M.CrossSection.circle(2.2, 32).translate([size / 2, total_h - head / 2])
    raised = M.CrossSection.batch_boolean(parts_2d, M.OpType.Add) if len(parts_2d) > 1 else parts_2d[0]
    body = plate.extrude(plate_t) + raised.extrude(relief).translate([0, 0, plate_t - 0.01])
    parts = {"body": body}
    notes = {"outer": [round(size, 1), round(total_h, 1), round(plate_t + relief, 1)], "module_mm": round(module, 2), "modules": int(cells), "quiet_zone_mm": round(4 * module, 1), "verified": True, "needs": []}
    # the code is only readable in a second colour; the stand on the same plate gets a light foot and a dark body, which
    # looks meant (before 30 Sep 2026 a sign with a stand stayed one colour, and a one-colour code is useless)
    notes["color_change_mm"] = round(plate_t, 2)
    # the two colours the customer picked: the preview paints them, the order of a print starts from them
    (plate_color, plate_rgb), (code_color, code_rgb) = _color(Invalid, p, k, "plate_color"), _color(Invalid, p, k, "code_color")
    notes["colors"] = {"plate": plate_color, "code": code_color}
    # "exact": the preview cuts the model at that height and paints the colours as their swatches show them
    notes["regions"] = [{"x0": -9999, "y0": -9999, "x1": 9999, "y1": 9999, "z0": round(plate_t + 0.05, 2), "color": code_color, "exact": True},
                        {"x0": -9999, "y0": -9999, "x1": 9999, "y1": 9999, "z0": -1, "color": plate_color, "exact": True}]
    light, dark = _lightness(plate_rgb), _lightness(code_rgb)
    contrast = (max(light, dark) + 0.05) / (min(light, dark) + 0.05)
    notes["contrast"] = round(contrast, 1)
    notes["warnings"] = []
    if plate_color == code_color:
        notes["warnings"].append("qr_one_color")
    elif contrast < 3:
        notes["warnings"].append("qr_low_contrast")
    elif dark > light:
        notes["warnings"].append("qr_inverted")                          # a light code on a dark plate: not every reader takes it
    if stand:
        slot = plate_t + 0.5
        sw, sd, sh = size * 0.7, 34.0, 12.0
        block = S.rounded_rect(M, sw, sd, 3).extrude(sh)
        cut = M.Manifold.cube([sw + 2, slot, sh]).translate([-1, 0, 0]).rotate([-12, 0, 0]).translate([0, sd * 0.42, 3.0])
        parts["stand"] = block - cut
        parts["all"] = body + parts["stand"].translate([size + 8, 0, 0])
        notes["lean_deg"] = 12
    else:
        parts["all"] = body
    return parts, notes


def _bridged_mask(M, plate, motif, bridge):
    """
    plate − motif, with every loose island (the inside of O, A, closed shapes) tied back to the frame by a thin bridge.
    Bridges run vertically through the island's middle: the shortest way that always reaches the frame.
    Returns (mask, number_of_bridges).
    """
    import numpy as np
    mask = plate - motif
    x0, y0, x1, y1 = plate.bounds()
    bridges = 0
    for _ in range(3):                                   # a bridge can free nothing new, but nested shapes need a second look
        pieces = mask.decompose()
        if len(pieces) <= 1:
            break
        frame = max(pieces, key=lambda c: c.area())
        bars = []
        for piece in pieces:
            if piece is frame or piece.area() < 0.3:
                continue
            px0, py0, px1, py1 = piece.bounds()
            cx = (px0 + px1) / 2
            bars.append(M.CrossSection.square([bridge, y1 - y0]).translate([cx - bridge / 2, y0]))
            bridges += 1
        if not bars:
            break
        mask = mask + (M.CrossSection.batch_boolean(bars, M.OpType.Add) ^ plate)
    dust = [c for c in mask.decompose() if c.area() < 0.3]
    return mask, bridges, len(dust)


# ── painting stencil ────────────────────────────────────────────────────────

def stencil(M, Invalid, p):
    k = "stencil"
    n = lambda key, d: _num(Invalid, p, k, key, d)       # noqa: E731
    width, t, margin, bridge = n("width", 120), n("thickness", 1.2), n("margin", 12), n("bridge", 1.2)
    art, info = _art(M, Invalid, p, width, None)
    art = S.fit(art, width_mm=width)
    w, hgt = S.size(art)
    if hgt > 300:
        raise Invalid("artwork_too_tall")
    pw, ph = w + 2 * margin, hgt + 2 * margin
    plate = S.rounded_rect(M, pw, ph, 4)
    mask, bridges, _ = _bridged_mask(M, plate, S.centre_on(art, pw, ph), bridge)
    warn = []
    thin = S.printability(M, art, 0.6)["thin_pct"]
    if thin > 30:
        warn.append("thin_lines")
    if info.get("missing_chars"):
        warn.append("missing_chars")
    if info.get("ignored_outlines"):
        warn.append("outlines_ignored")
    notes = {"outer": [round(pw, 1), round(ph, 1), round(t, 1)], "bridges": bridges, "warnings": warn, "thin_pct": thin, "missing_chars": info.get("missing_chars", [])}
    return {"all": mask.extrude(t)}, notes


# ── papel picado: a picture as a cut-out panel ──────────────────────────────

PAPEL_PX = 280              # a photo is read on a grid of this many cells on the longer side of the window
BORDERS = ("flowers", "diamonds", "dots", "hearts", "leaves", "stars", "none")


def _papel_photo(M, path, win_w, win_h, darkness, soften, invert):
    """
    A photo or a drawing → what stays as paper inside a window of win_w × win_h (corner at the origin): its dark parts.
    The picture covers the whole window (cut to its proportions, not squeezed), is softened, split into dark and light
    at the level asked for, and cleaned of what a nozzle cannot print.
    """
    import numpy as np
    from PIL import Image, ImageFilter, ImageOps
    from scipy import ndimage
    try:
        img = ImageOps.exif_transpose(Image.open(path))
    except Exception:  # noqa: BLE001
        raise S.ArtworkError("image_unreadable")
    if img.mode in ("RGBA", "LA", "P") or "transparency" in img.info:
        rgba = img.convert("RGBA")
        img = Image.alpha_composite(Image.new("RGBA", rgba.size, (255, 255, 255, 255)), rgba)
    cell = max(win_w, win_h) / PAPEL_PX
    cols, rows = max(8, int(round(win_w / cell))), max(8, int(round(win_h / cell)))
    g = ImageOps.autocontrast(ImageOps.fit(img.convert("L"), (cols, rows), Image.LANCZOS), cutoff=1)
    if soften > 0:
        g = g.filter(ImageFilter.GaussianBlur(soften * 0.9))
    a = np.asarray(g, dtype=np.float32)
    hist, _ = np.histogram(a, bins=256, range=(0, 255))
    w = np.cumsum(hist).astype(np.float64)
    m = np.cumsum(hist * np.arange(256)).astype(np.float64)
    with np.errstate(divide="ignore", invalid="ignore"):
        between = (m[-1] * w - m * w[-1]) ** 2 / (w * (w[-1] - w))
    if np.all(np.isnan(between)):
        raise S.ArtworkError("image_blank", "0")
    level = float(np.nanargmax(between)) + (darkness - 50.0) * 2.0       # the middle of the slider is the split the picture asks for itself
    paper = a <= level
    if invert:
        paper = ~paper
    share = float(paper.mean())
    if share < 0.02 or share > 0.98:
        raise S.ArtworkError("image_blank", "%d" % round(share * 100))
    # what a nozzle cannot print goes: specks of paper, pin holes, hairlines (an opening and a closing of one cell)
    small = max(4, int(round((1.6 / cell) ** 2)))
    # (done on the picture with its edge repeated outwards: otherwise the closing eats the paper along the picture's edge)
    paper = ndimage.binary_closing(ndimage.binary_opening(np.pad(paper, 3, mode="edge"), iterations=1), iterations=1)[3:-3, 3:-3].copy()
    for keep in (True, False):
        labels, count = ndimage.label(paper == keep)
        if count:
            sizes = ndimage.sum(paper == keep, labels, range(1, count + 1))
            for i in np.flatnonzero(sizes < small):
                paper[labels == i + 1] = not keep
    rects = []
    for r in range(rows):
        edges = np.flatnonzero(np.diff(np.concatenate(([0], paper[r].view(np.int8), [0]))))
        for s0, e0 in zip(edges[::2], edges[1::2]):
            y = rows - 1 - r
            rects.append(np.array([(s0, y), (e0, y), (e0, y + 1.02), (s0, y + 1.02)], dtype=np.float64))
    if not rects:
        raise S.ArtworkError("image_blank", "0")
    cs = M.CrossSection(rects, M.FillRule.NonZero)
    cs = cs.offset(0.75, M.JoinType.Round, 2.0, 12).offset(-0.75, M.JoinType.Round, 2.0, 12).simplify(0.35)
    return cs.scale([win_w / cols, win_h / rows])


def _papel_unit(M, kind, u):
    """One cut-out of a border, centred on the origin, about u across."""
    C = M.CrossSection
    if kind == "dots":
        return C.circle(0.3 * u, 32)
    if kind == "diamonds":
        return C.square([0.54 * u, 0.54 * u]).translate([-0.27 * u, -0.27 * u]).rotate(45)
    if kind == "hearts":
        pts = [(0.024 * u * 16 * math.sin(a) ** 3, 0.024 * u * (13 * math.cos(a) - 5 * math.cos(2 * a) - 2 * math.cos(3 * a) - math.cos(4 * a))) for a in (2 * math.pi * i / 48 for i in range(48))]
        return C([pts], M.FillRule.NonZero)
    if kind == "leaves":
        return (C.circle(0.42 * u, 48).translate([0.27 * u, 0]) ^ C.circle(0.42 * u, 48).translate([-0.27 * u, 0])).rotate(45)
    if kind == "stars":
        return C([[((0.42 if i % 2 == 0 else 0.19) * u * math.cos(math.pi / 2 + i * math.pi / 5), (0.42 if i % 2 == 0 else 0.19) * u * math.sin(math.pi / 2 + i * math.pi / 5)) for i in range(10)]], M.FillRule.NonZero)
    flower = C.circle(0.13 * u, 24)                          # flowers: petals round a dot, each a hole of its own
    for i in range(5):
        a = math.pi / 2 + 2 * math.pi * i / 5
        flower = flower + C.circle(0.12 * u, 24).translate([0.29 * u * math.cos(a), 0.29 * u * math.sin(a)])
    return flower


def _papel_ties(M, sheet, holes, plate, bridge):
    """
    Every piece of paper that hangs in the air (a face in a window cut out round it, the pupil of an eye) is tied to the
    rest by thin strips across the hole it lies in, and only across that hole: one up and down through its middle, and
    for a piece of some size one left and right as well. The strips stop at the piece's own outline, so they do not
    run through the holes cut in it. Returns (sheet, ties, pieces that could not be tied and were left out).
    """
    C, J = M.CrossSection, M.JoinType.Round
    x0, y0, x1, y1 = plate.bounds()

    def filled(cs):
        """A shape without its holes: its biggest outline alone."""
        import numpy as np
        rings = [np.asarray(r, dtype=np.float64) for r in cs.to_polygons()]
        size = lambda r: abs(float(np.sum(r[:, 0] * np.roll(r[:, 1], -1) - np.roll(r[:, 0], -1) * r[:, 1])))      # noqa: E731
        return C([max(rings, key=size)], M.FillRule.NonZero)

    ties = 0
    for _ in range(4):                                       # a tie frees nothing new, but nested pieces take another look
        pieces = sheet.decompose()
        if len(pieces) <= 1:
            break
        frame = max(pieces, key=lambda c: c.area())
        rims = [filled(c) for c in holes.decompose()]
        strips = []
        for piece in pieces:
            if piece is frame or piece.area() < 2.0:
                continue
            px0, py0, px1, py1 = piece.bounds()
            bars = C.square([bridge, y1 - y0]).translate([(px0 + px1) / 2 - bridge / 2, y0])
            if min(px1 - px0, py1 - py0) > 25.0:
                bars = bars + C.square([x1 - x0, bridge]).translate([x0, (py0 + py1) / 2 - bridge / 2])
            around = [rim for rim in rims if (piece - rim).area() < 0.05 * piece.area()]
            reach = min(around, key=lambda c: c.area()) if around else plate
            strips.append((bars ^ reach) - filled(piece).offset(-0.6, J, 2.0, 12))
            ties += 1
        if not strips:
            break
        sheet = sheet + M.CrossSection.batch_boolean(strips, M.OpType.Add)
    pieces = sheet.decompose()
    frame = max(pieces, key=lambda c: c.area())
    return frame, ties, len([c for c in pieces if c is not frame and c.area() >= 2.0])


def papel(M, Invalid, p):
    """
    Papel picado, the cut-paper banner of Mexican feasts, as a thin printed panel: a picture cut out in a window (its dark
    parts stay as paper, the light ones are holes), a border pierced with flowers, a scalloped lower edge and two holes
    for the string. One flat outline pulled up to the thickness asked for.
    """
    k = "papel"
    C = M.CrossSection
    n = lambda key, d: _num(Invalid, p, k, key, d)       # noqa: E731
    W, H, t, bridge = n("width", 150), n("height", 200), n("thickness", 1.2), n("bridge", 1.2)
    darkness, soften = n("darkness", 50), n("soften", 1)
    border = _pick(Invalid, p, k, "border")
    invert, scallop, string = bool(p.get("invert", False)), bool(p.get("scallop", True)), bool(p.get("string_holes", True))
    path = p.get("artwork_path")
    if not path:
        raise Invalid("no_text")
    band = max(10.0, min(26.0, 0.13 * min(W, H))) if border != "none" else 6.0
    win_w, win_h = W - 2 * band, H - 2 * band
    if win_w < 30 or win_h < 30:
        raise Invalid("shape_too_small")
    try:
        if path.lower().endswith(".svg"):
            # a drawing: its filled shapes are the paper, set whole into the window
            art = S.svg(M, path, 100.0)[0]
            aw, ah = S.size(art)
            art = S.fit(art, width_mm=aw * min((win_w - 4) / aw, (win_h - 4) / ah))
            paper = S.centre_on(art, win_w, win_h)
            if invert:
                paper = C.square([win_w, win_h]) - paper
        else:
            paper = _papel_photo(M, path, win_w, win_h, darkness, soften, invert)
    except S.ArtworkError as e:
        raise Invalid(e.code, str(e).split(": ", 1)[1] if ": " in str(e) else "")
    # the window is a hair smaller than the picture, so paper that reaches the picture's edge is one with the border
    window = C.square([win_w - 1.0, win_h - 1.0]).translate([band + 0.5, band + 0.5])
    holes = window - paper.translate([band, band])
    plate = S.rounded_rect(M, W, H, 3)
    if scallop:
        count = max(3, int(round(W / 26.0)))
        r = W / (2.0 * count)
        for i in range(count):
            plate = plate + C.circle(r, 48).translate([r + 2 * r * i, 0])
            holes = holes + C.circle(r * 0.3, 24).translate([r + 2 * r * i, -r * 0.35])
    if border != "none":
        unit = _papel_unit(M, border, band * 0.72)
        pitch = band * 0.95
        nx, ny = max(2, int((W - band) / pitch)), max(2, int((H - band) / pitch))
        spots = [(band / 2 + i * (W - band) / nx, y) for i in range(nx + 1) for y in (band / 2, H - band / 2)]
        spots += [(x, band / 2 + j * (H - band) / ny) for j in range(1, ny) for x in (band / 2, W - band / 2)]
        if string:
            spots = [s for s in spots if not (s[1] > H - band and (s[0] < band or s[0] > W - band))]      # the top corners are for the string
        holes = holes + M.CrossSection.batch_boolean([unit.translate(list(s)) for s in spots], M.OpType.Add)
    if string:
        for x in (band / 2, W - band / 2):
            holes = holes + C.circle(2.0, 32).translate([x, H - band / 2])
    sheet, ties, lost = _papel_ties(M, plate - holes, holes, plate, bridge)
    warn = []
    if lost:
        warn.append("papel_lost")
    thin = S.printability(M, sheet, 0.45)["thin_pct"]
    if thin > 30:
        warn.append("thin_lines")
    x0, y0, x1, y1 = sheet.bounds()
    open_pct = int(round(100 * (1 - sheet.area() / plate.area())))
    if open_pct > 70:
        warn.append("papel_airy")
    notes = {"outer": [round(x1 - x0, 1), round(y1 - y0, 1), round(t, 1)], "ties": ties, "open_pct": open_pct, "warnings": warn, "thin_pct": thin, "missing_chars": []}
    return {"all": sheet.translate([-x0, -y0]).extrude(t)}, notes


# ── illuminated sign ────────────────────────────────────────────────────────

LED = {"strip8": (8.0, 3.0), "strip10": (10.0, 3.5), "module": (18.0, 8.0)}     # width and height the light source needs on the wall / back


def lightbox(M, Invalid, p):
    k = "lightbox"
    n = lambda key, d: _num(Invalid, p, k, key, d)       # noqa: E731
    width, depth, wall, face_t = n("width", 160), n("depth", 35), n("wall", 2.0), n("face", 1.2)
    margin, bridge, cable, clearance = n("margin", 12), n("bridge", 1.4), n("cable", 5), n("clearance", 0.25)
    led = _pick(Invalid, p, k, "led")
    if _pick(Invalid, p, k, "shape") == "round":
        return _lightbox_round(M, Invalid, p, width, depth, wall, face_t, margin, bridge, cable, clearance, led)
    art, info = _art(M, Invalid, p, width - 2 * margin, None)
    art = S.fit(art, width_mm=width - 2 * margin)
    w, hgt = S.size(art)
    height = hgt + 2 * margin
    if height > 300:
        raise Invalid("artwork_too_tall")
    led_w, led_h = LED[led]
    if depth < led_w + 12:
        raise Invalid("lightbox_too_shallow", "%d" % math.ceil(led_w + 12))

    outer = S.rounded_rect(M, width, height, 5)
    inner = S.rounded_rect(M, width - 2 * wall, height - 2 * wall, 3.5).translate([wall, wall])
    # body: an open frame; a ledge 1.6 mm wide inside the front holds the diffuser and the face
    ledge = S.rounded_rect(M, width - 2 * wall - 3.2, height - 2 * wall - 3.2, 2.5).translate([wall + 1.6, wall + 1.6])
    body = (outer - inner).extrude(depth) + (inner - ledge).extrude(1.6).translate([0, 0, depth - face_t - 1.0 - 1.6])
    hole = M.Manifold.cylinder(wall + 2, cable / 2, cable / 2, 32).rotate([90, 0, 0]).translate([width / 2, wall + 1, 6 + cable / 2])
    body = body - hole

    seat_w, seat_h = width - 2 * wall - 2 * clearance, height - 2 * wall - 2 * clearance
    seat = S.rounded_rect(M, seat_w, seat_h, 3.3)
    mask, bridges, _ = _bridged_mask(M, seat, S.centre_on(art, seat_w, seat_h), bridge)
    face = mask.extrude(face_t)                                        # dark: the light comes only through the motif
    diffuser = seat.extrude(1.0)                                       # white or natural: spreads the light evenly
    lip_wall = 1.6
    back = outer.extrude(wall) + (seat - S.rounded_rect(M, seat_w - 2 * lip_wall, seat_h - 2 * lip_wall, 2).translate([lip_wall, lip_wall])).extrude(5).translate([wall + clearance, wall + clearance, wall])

    gap = 8.0
    parts = {
        "body": body, "face": face, "diffuser": diffuser, "back": back,
        "all": body + face.translate([width + gap, 0, 0]) + diffuser.translate([width + gap, height + gap, 0]) + back.translate([0, height + gap, 0]),
        # how it goes together, pulled apart along the depth so every layer is visible
        "use": (back + body.translate([0, 0, wall + 6]) + diffuser.translate([wall + clearance, wall + clearance, wall + depth + 14]) + face.translate([wall + clearance, wall + clearance, wall + depth + 24])).rotate([90, 0, 0]),
    }
    strip = 2 * (width + height - 4 * wall) / 1000.0
    warn = []
    thin = S.printability(M, art, 0.6)["thin_pct"]
    if thin > 30:
        warn.append("thin_lines")
    if info.get("missing_chars"):
        warn.append("missing_chars")
    if info.get("ignored_outlines"):
        warn.append("outlines_ignored")
    notes = {"outer": [round(width, 1), round(height, 1), round(depth + wall, 1)], "bridges": bridges, "warnings": warn, "thin_pct": thin,
             "missing_chars": info.get("missing_chars", []), "needs": ["led_" + led, "usb_power", "tape"], "led_m": round(strip, 2), "cable_mm": cable,
             "colors": {"body": "dark", "face": "dark", "diffuser": "white", "back": "dark"}}
    return parts, notes


def _lightbox_round(M, Invalid, p, width, depth, wall, face_t, margin, bridge, cable, clearance, led):
    """
    The round light box: a circle with its bottom cut flat, so it stands on a shelf by itself. Same four parts and the
    same fits as the rectangular one; every outline is the outer one moved inwards, so the walls are even all round.
    """
    C = M.CrossSection
    R = width / 2
    foot = 0.2 * R                                         # how much of the circle is cut away: a foot 1.2 R wide
    cy = R - foot
    height = 2 * R - foot
    led_w, led_h = LED[led]
    if depth < led_w + 12:
        raise Invalid("lightbox_too_shallow", "%d" % math.ceil(led_w + 12))

    def outline(inset):
        return C.circle(R - inset, 160).translate([R, cy]) ^ C.square([2 * R, 2 * R]).translate([0, inset])

    art, info = _art(M, Invalid, p, width - 2 * margin, None)
    aw, ah = S.size(art)
    room = R - wall - 1.6 - margin                         # the motif stays clear of the ledge that holds the face
    low = wall + 1.6 + margin
    k = 2 * room / math.hypot(aw, ah)
    for _ in range(40):
        w, h = aw * k, ah * k
        ay = max(cy, low + h / 2)                          # a tall motif moves up, away from the flat foot
        if math.hypot(w / 2, ay - cy + h / 2) <= room + 1e-6:
            break
        k *= 0.97
    art = S.fit(art, width_mm=aw * k)
    w, h = S.size(art)
    art = art.translate([R - w / 2, ay - h / 2])

    outer, inner = outline(0), outline(wall)
    body = (outer - inner).extrude(depth) + (inner - outline(wall + 1.6)).extrude(1.6).translate([0, 0, depth - face_t - 1.0 - 1.6])
    # the cable leaves low at the back of the side, above the foot, so the box still stands flat
    angle = -40.0
    hole = M.Manifold.cylinder(wall + 6, cable / 2, cable / 2, 32).translate([0, 0, -(wall + 6) / 2]).rotate([0, 90, 0]).translate([R - wall / 2, 0, 0]).rotate([0, 0, angle])
    body = body - hole.translate([R, cy, 6 + cable / 2])

    seat = outline(wall + clearance)
    mask, bridges, _ = _bridged_mask(M, seat, art, bridge)
    face = mask.extrude(face_t)
    diffuser = seat.extrude(1.0)
    back = outer.extrude(wall) + (seat - outline(wall + clearance + 1.6)).extrude(5).translate([0, 0, wall])

    gap = 8.0
    parts = {
        "body": body, "face": face, "diffuser": diffuser, "back": back,
        "all": body + face.translate([width + gap, 0, 0]) + diffuser.translate([width + gap, height + gap, 0]) + back.translate([0, height + gap, 0]),
        "use": (back + body.translate([0, 0, wall + 6]) + diffuser.translate([0, 0, wall + depth + 14]) + face.translate([0, 0, wall + depth + 24])).rotate([90, 0, 0]),
    }
    warn = []
    thin = S.printability(M, art, 0.6)["thin_pct"]
    if thin > 30:
        warn.append("thin_lines")
    if info.get("missing_chars"):
        warn.append("missing_chars")
    if info.get("ignored_outlines"):
        warn.append("outlines_ignored")
    strip = (2 * math.pi * (R - wall)) / 1000.0
    notes = {"outer": [round(width, 1), round(height, 1), round(depth + wall, 1)], "bridges": bridges, "warnings": warn, "thin_pct": thin,
             "missing_chars": info.get("missing_chars", []), "needs": ["led_" + led, "usb_power", "tape"], "led_m": round(strip, 2), "cable_mm": cable,
             "foot_mm": round(2 * math.sqrt(R * R - cy * cy), 1),
             "colors": {"body": "dark", "face": "dark", "diffuser": "white", "back": "dark"}}
    return parts, notes


# ── cookie cutter ───────────────────────────────────────────────────────────

def cutter(M, Invalid, p):
    """
    Cookie / dough cutter from an outline: a thin wall standing on the plate with a flange at its foot (the pressing side
    when the cutter is turned over), the cutting edge thinned at the top. The outline is mirrored, so the biscuit reads the
    right way round once the cutter is flipped for use. Holes in the outline (the counter of an "O") get their own wall,
    tied to the rest by flat bars at flange level. A line drawing (a leaf with its veins) also yields a separate stamp plate
    with the inner lines raised, pressed into the biscuit after cutting.
    """
    k = "cutter"
    n = lambda key, d: _num(Invalid, p, k, key, d)       # noqa: E731
    width, height, wall = n("width", 70), n("height", 18), n("wall", 1.0)
    flange, flange_t = n("flange", 5), n("flange_t", 1.6)
    edge = _pick(Invalid, p, k, "edge")
    raster_in = bool(p.get("artwork_path")) and not str(p.get("artwork_path")).lower().endswith(".svg")
    try:
        sil, info = S.load(M, p, width, p.get("font"), None, fill_holes=raster_in)
        art = S.load(M, p, width, p.get("font"), None)[0] if raster_in else sil
    except S.ArtworkError as e:
        raise Invalid(e.code, str(e).split(": ", 1)[1] if ": " in str(e) else "")
    sil = S.fit(sil, width_mm=width)
    if raster_in:
        art = S.fit(art, width_mm=width)
    w, hgt = S.size(sil)
    if hgt > 200:
        raise Invalid("artwork_too_tall")
    # narrow passages would print as a double wall and the dough would stick: open them up to at least two walls
    sil = sil.offset(-wall, M.JoinType.Round, 2.0, 8).offset(wall, M.JoinType.Round, 2.0, 8)
    if sil.is_empty() or sil.area() < 25:
        raise Invalid("empty_result")
    margin = wall + flange
    pw, ph = w + 2 * margin, hgt + 2 * margin
    place = lambda cs: S.centre_on(cs.mirror([1, 0]), pw, ph)       # noqa: E731  mirrored: the biscuit reads right once the cutter is turned over
    sil_p = place(sil).simplify(0.03)                                # a traced picture carries thousands of near-collinear points: fewer, cleaner offsets
    art_p = place(art) if raster_in else None

    off = lambda cs, d: cs.offset(d, M.JoinType.Round, 2.0, 8).simplify(0.02)       # noqa: E731  round offsets of a wavy outline breed near-coincident points
    rim = off(sil_p, wall)
    foot = off(sil_p, wall + flange)
    taper = 2.0 if edge == "sharp" else 0.0
    eps = 0.05                                       # no two stacked layers may share a vertical face: the union would leave slivers (see sign)

    # inner islands (the counter of an "O"): a flat bar through each hole at flange level ties its wall to the rest
    holes = [poly for poly in sil_p.to_polygons() if _signed_area(poly) < 0]
    bars = None
    for poly in holes:
        cy = sum(y for _, y in poly) / len(poly)
        bar = M.CrossSection.square([pw + 2, 2.5]).translate([-1, cy - 1.25]) ^ foot
        if not bar.is_empty():
            bars = bar if bars is None else bars + bar
    bridges = 0 if bars is None else len([h for h in holes])

    # three layers, each one flat 2D profile: flange with bars, the wall, the thinned cutting edge; 0.01 mm overlaps
    cavity_low = off(sil_p, eps) - bars if bars is not None else off(sil_p, eps)
    body = (foot - cavity_low).extrude(flange_t)
    body = body + (rim - sil_p).extrude(height - taper - flange_t + 0.01).translate([0, 0, flange_t - 0.01])
    if taper:
        body = body + (off(sil_p, min(wall, 0.6)) - off(sil_p, -0.02)).extrude(taper + 0.01).translate([0, 0, height - taper - 0.01])
    warn = []
    if info.get("missing_chars"):
        warn.append("missing_chars")
    if info.get("ignored_outlines"):
        warn.append("outlines_ignored")
    parts = {"body": body, "all": body}
    notes = {"outer": [round(pw, 1), round(ph, 1), round(height, 1)], "bridges": bridges, "warnings": warn, "missing_chars": info.get("missing_chars", []), "parts": []}

    # the drawing's inner lines (veins, eyes, a smile) → a stamp that marks the biscuit; only when there is enough of them
    if raster_in and bool(p.get("stamp", True)):
        inner = sil_p.offset(-(wall + 1.5), M.JoinType.Round, 2.0, 8)
        details = art_p ^ inner
        # a drawing filled with colour (a black bear with white eyes): the marks are what was left white
        if details.area() > 0.6 * inner.area():
            details = (inner - art_p).offset(-0.2, M.JoinType.Round, 2.0, 8).offset(0.2, M.JoinType.Round, 2.0, 8)
            notes["stamp_from"] = "white"
        if not details.is_empty() and details.area() > 0.015 * sil_p.area():
            plate2d = sil_p.offset(-(wall + 0.6), M.JoinType.Round, 2.0, 8)
            stamp = plate2d.extrude(1.6) + details.extrude(1.2).translate([0, 0, 1.59])
            parts["stamp"] = stamp
            parts["all"] = body + stamp.translate([pw + 8, 0, 0])
            parts["body"] = body
            notes["parts"] = ["body", "stamp"]
            notes["outer"] = [round(pw * 2 + 8, 1), round(ph, 1), round(height, 1)]
    # booleans of stacked profiles leave vertices nanometres apart; welded in the float32 STL they would open the mesh.
    # 5 µm is nothing on a cutter, and it makes every part one closed body.
    parts = {key: _welded(M, solid) for key, solid in parts.items()}
    # in use the cutter is turned over (about its Y axis, like flipping a page): the flange on top, the edge on the dough,
    # the outline reading as typed; the stamp stays face up beside it
    use = parts["body"].rotate([0, 180, 0]).translate([pw, 0, height])
    if "stamp" in parts:
        use = use + parts["stamp"].translate([pw + 8, 0, 0])
    parts["use"] = use
    return parts, notes


def _welded(M, solid, tolerance=0.005):
    try:
        slim = solid.simplify(tolerance)
    except AttributeError:                       # older manifold3d
        return solid
    return slim if not slim.is_empty() and slim.status() == M.Error.NoError else solid


def _signed_area(poly):
    return 0.5 * sum(poly[i][0] * poly[(i + 1) % len(poly)][1] - poly[(i + 1) % len(poly)][0] * poly[i][1] for i in range(len(poly)))


BUILDERS = {"vase": vase, "logo": logo, "sign": sign, "stamp": stamp, "qr": qr, "stencil": stencil, "papel": papel, "lightbox": lightbox, "cutter": cutter}
