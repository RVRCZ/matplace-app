"""
Creative products for param_tool.py: vase / plant pot, logo or picture to 3D, stamp / embossing plate, QR sign.
Each builder returns (parts, notes) like the ones in param_tool.py. Text, SVG and raster input goes through shape2d.
"""
import math

import shape2d as S

LIMITS = {
    "vase": {"height": (40, 300), "top_d": (30, 250), "bottom_d": (30, 250), "wall": (0.8, 4), "floor": (0.8, 5), "ribs": (6, 48), "twist": (0, 180)},
    "logo": {"width": (20, 250), "thickness": (0.6, 10), "plate": (0.8, 6), "margin": (0, 20), "base_h": (8, 40)},
    "stamp": {"width": (15, 120), "relief": (0.8, 4), "plate": (2, 6), "text_height": (4, 40)},
    "qr": {"size": (30, 150), "plate": (1.6, 4), "relief": (0.6, 2)},
    "stencil": {"width": (30, 250), "thickness": (0.8, 3), "margin": (5, 40), "bridge": (0.8, 3)},
    "lightbox": {"width": (80, 300), "depth": (25, 80), "wall": (1.6, 4), "face": (0.8, 2), "margin": (6, 40), "bridge": (0.8, 3), "cable": (3, 10), "clearance": (0.1, 0.6)},
}
CHOICES = {
    "vase": {"profile": ("cone", "belly", "tulip"), "style": ("smooth", "ribs", "twist"), "purpose": ("vase", "pot")},
    "logo": {"mode": ("relief", "cutout", "standing"), "shape": ("rounded", "rect", "circle")},
    "stamp": {"mode": ("raised", "recessed"), "handle": ("knob", "none")},
    "qr": {},
    "stencil": {},
    "lightbox": {"led": ("strip8", "strip10", "module")},
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


# ── vase / plant pot ─────────────────────────────────────────────────────────

def vase(M, Invalid, p):
    import numpy as np
    k = "vase"
    n = lambda key, d: _num(Invalid, p, k, key, d)       # noqa: E731
    h, top, bottom = n("height", 140), n("top_d", 90) / 2, n("bottom_d", 70) / 2
    wall, floor = n("wall", 1.6), n("floor", 1.6)
    profile, style, purpose = _pick(Invalid, p, k, "profile"), _pick(Invalid, p, k, "style"), _pick(Invalid, p, k, "purpose")
    ribs = int(n("ribs", 16))
    twist = n("twist", 90) if style == "twist" else 0.0
    amp = 0.0 if style == "smooth" else 0.045

    def radius(t):                                        # t = 0 bottom … 1 top
        base = bottom + (top - bottom) * t
        if profile == "belly":
            return base + 0.22 * max(top, bottom) * np.sin(np.pi * t)
        if profile == "tulip":
            return bottom + (top - bottom) * t ** 2.4 + 0.10 * bottom * np.sin(np.pi * t) * (1 - t)
        return base

    ts = np.linspace(0, 1, 50)
    if float(np.min(radius(ts))) - wall * (1 + amp) < 6:
        raise Invalid("vase_too_narrow")
    r0 = bottom
    ang = np.linspace(0, 2 * np.pi, 120 if style == "smooth" else 144, endpoint=False)
    ring = np.stack([r0 * (1 + amp * np.cos(ribs * ang)) * np.cos(ang), r0 * (1 + amp * np.cos(ribs * ang)) * np.sin(ang)], 1)
    base = M.CrossSection([ring])
    div = int(max(20, min(90, h / 2.5)))            # enough for a smooth profile, light enough for a live preview

    def shaped(z0, z1, inset):
        solid = M.Manifold.extrude(base, z1 - z0, div, twist * (z1 - z0) / h).translate([0, 0, z0])
        if z0 > 0 and twist:
            solid = solid.rotate([0, 0, twist * z0 / h])

        def warp(v):
            v = np.array(v, dtype=np.float64)
            s = (radius(np.clip(v[:, 2] / h, 0, 1)) - inset) / r0
            v[:, 0] *= s
            v[:, 1] *= s
            return v
        return solid.warp_batch(warp)

    body = shaped(0, h, 0.0) - shaped(floor, h + 1.0, wall)
    notes = {"outer": [round(2 * float(np.max(radius(ts))) * (1 + amp), 1), round(2 * float(np.max(radius(ts))) * (1 + amp), 1), round(h, 1)], "purpose": purpose}
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
        parts["all"] = body + saucer.translate([float(np.max(radius(ts))) * (1 + amp) + sr + 3 + 8, 0, 0])
    else:
        parts["all"] = body
    return parts, notes


# ── logo / picture to 3D ─────────────────────────────────────────────────────

def logo(M, Invalid, p):
    k = "logo"
    n = lambda key, d: _num(Invalid, p, k, key, d)       # noqa: E731
    width, t, plate_t, margin = n("width", 80), n("thickness", 2), n("plate", 2), n("margin", 5)
    mode, shape = _pick(Invalid, p, k, "mode"), _pick(Invalid, p, k, "shape")
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
        sink, show = 6.0, 2.5                                           # how deep it sits in the slot / how much of the foot stays visible
        x0, y0, x1, y1 = art.bounds()
        reach = descent + 3.0 if info.get("source") == "text" else min(8.0, max(3.0, hgt * 0.18))   # up to the baseline and a bit: a J or g must not be the only letter caught
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
            "use": M.Manifold.compose([base.translate([shift, 0, 0]), upright.translate([shift, 0, 0])]),
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
        notes = {"outer": [round(pw, 1), round(ph, 1), round(plate_t + t, 1)]}
    notes.update({"warnings": warn, "thin_pct": thin, "missing_chars": info.get("missing_chars", [])})
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
    total_h = size + label_h + foot
    plate = S.rounded_rect(M, size, total_h, 4)
    if p.get("hole", False) and not stand:
        plate = plate - M.CrossSection.circle(2.2, 32).translate([size / 2, total_h - 3.2]) if total_h - size - label_h > 6 else plate
    raised = M.CrossSection.batch_boolean(parts_2d, M.OpType.Add) if len(parts_2d) > 1 else parts_2d[0]
    body = plate.extrude(plate_t) + raised.extrude(relief).translate([0, 0, plate_t - 0.01])
    parts = {"body": body}
    notes = {"outer": [round(size, 1), round(total_h, 1), round(plate_t + relief, 1)], "module_mm": round(module, 2), "modules": int(cells), "quiet_zone_mm": round(4 * module, 1), "verified": True, "needs": []}
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


# ── illuminated sign ────────────────────────────────────────────────────────

LED = {"strip8": (8.0, 3.0), "strip10": (10.0, 3.5), "module": (18.0, 8.0)}     # width and height the light source needs on the wall / back


def lightbox(M, Invalid, p):
    k = "lightbox"
    n = lambda key, d: _num(Invalid, p, k, key, d)       # noqa: E731
    width, depth, wall, face_t = n("width", 160), n("depth", 35), n("wall", 2.0), n("face", 1.2)
    margin, bridge, cable, clearance = n("margin", 12), n("bridge", 1.4), n("cable", 5), n("clearance", 0.25)
    led = _pick(Invalid, p, k, "led")
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


BUILDERS = {"vase": vase, "logo": logo, "stamp": stamp, "qr": qr, "stencil": stencil, "lightbox": lightbox}
