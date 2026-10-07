"""
Things cut out of a picture or a name for param_tool.py: pendant, keychain, earrings, Christmas ornament, fridge magnet,
coaster.
One builder; the product says what is added to the shape (an eyelet, a pocket for a magnet, grooves underneath…).

The picture comes in the colours of the farm's filaments (shape2d.colors). Two ways to print them:

  raised (default)  every colour lies one step higher than the one under it. Each layer of the print is one single
                    filament, so the colours are changed by height: any printer does it with a filament swap, the farm
                    with a tool change at a layer. notes.color_changes says where.
  flush             the colours are inlaid level with the top. Several filaments share a layer: only a printer that
                    changes filament by itself prints that (notes.multi_material).

Parts: `body` (the plate, with the eyelet), `color_<n>` (n = the colour's number in the list shown to the visitor),
`rim` (a raised border in its own colour). Everything lies flat on the bed, face up.
"""
import math

import shape2d as S

PRODUCTS = ("charm", "keychain", "earrings", "ornament", "magnet", "coaster")

# the widest range any product allows; each product's own limits are ParametricGenerator::FIELDS
LIMITS = {
    "width": (10, 250), "thickness": (1.2, 10), "frame": (0, 4), "relief": (0.2, 1.2), "colors_n": (1, 8), "bg_strength": (0, 100), "smooth": (0, 1),
    "contrast": (50, 150), "brightness": (50, 150), "saturation": (0, 200), "eye_pos": (0, 100), "eye_hole": (1.5, 8), "eye_wall": (1.2, 4),
    "mag_d": (4, 30), "mag_h": (1, 6), "mag_gap": (0, 0.4),
}
BODIES = {"charm": ("image", "circle", "rect"), "keychain": ("rect", "image", "circle"), "earrings": ("image", "circle"), "ornament": ("image", "circle", "star"),
          "magnet": ("image", "circle", "rect"), "coaster": ("circle", "square", "hex")}
MOUNTS = ("glue", "press", "through", "none")
INLAY = 0.6                 # how deep inlaid colours go: three layers, nothing of the plate shows through
GAP = 6.0                   # between the two earrings on the bed


def _num(Invalid, p, key, default):
    lo, hi = LIMITS[key]
    try:
        v = float(p.get(key, default))
    except (TypeError, ValueError):
        raise Invalid("not_a_number", key)
    if math.isnan(v) or v < lo or v > hi:
        raise Invalid("out_of_range", "%s %s-%s" % (key, lo, hi))
    return v


def _code(p, part):
    """The visitor's own filament for a part: (code, hex) or None."""
    c = (p.get("part_colors") or {}).get(part)
    if isinstance(c, dict) and c.get("code"):
        return str(c["code"]), str(c.get("hex") or "")
    return None


def _light(hx):
    lab = S.hex_lab(hx)
    return float(lab[0]) if lab is not None else 50.0


def _star(M, R, r):
    pts = []
    for i in range(10):
        a = math.pi / 2 + i * math.pi / 5
        rad = R if i % 2 == 0 else r
        pts.append((rad * math.cos(a), rad * math.sin(a)))
    star = M.CrossSection([pts]).offset(-1.2, M.JoinType.Round, 2.0, 24).offset(1.2, M.JoinType.Round, 2.0, 24)      # tips a finger does not mind
    x0, _, x1, _ = star.bounds()
    return star.scale([2 * R / (x1 - x0)] * 2)               # as wide as asked, rounding included


def _shape(M, body, D):
    """A geometric body centred on the origin and the radius of the circle that fits inside it."""
    C = M.CrossSection
    if body == "circle":
        return C.circle(D / 2, 160), D / 2
    if body == "hex":
        return C.circle(D / 2, 6).rotate(30), D / 2 * math.cos(math.pi / 6)
    if body == "star":
        R, r = D / 2, 0.55 * D / 2
        return _star(M, R, r), R * r * math.sin(math.pi / 5) / math.sqrt(R * R + r * r - 2 * R * r * math.cos(math.pi / 5)) - 0.6
    return S.rounded_rect(M, D, D, min(8.0, D / 10)).translate([-D / 2, -D / 2]), D / 2     # square


def _filled(M, cs, smallest):
    """The outline without its small holes: a hole a nozzle cannot go round only weakens the plate."""
    import numpy as np
    keep = []
    for poly in cs.to_polygons():
        pts = np.asarray(poly, dtype=np.float64)
        area = 0.5 * float(np.sum(pts[:, 0] * np.roll(pts[:, 1], -1) - np.roll(pts[:, 0], -1) * pts[:, 1]))
        if area > 0 or -area >= smallest:
            keep.append(pts)
    return M.CrossSection(keep, M.FillRule.EvenOdd) if keep else cs


def _roomiest(M, cs, need):
    """Where a disc of the given radius has the most room inside an outline: (x, y, the room there)."""
    import numpy as np
    from PIL import Image, ImageDraw
    from scipy import ndimage
    x0, y0, x1, y1 = cs.bounds()
    cell = max(0.25, max(x1 - x0, y1 - y0) / 400)
    size = (int((x1 - x0) / cell) + 3, int((y1 - y0) / cell) + 3)
    img = Image.new("1", size, 0)
    draw = ImageDraw.Draw(img)
    for poly in cs.to_polygons():
        pts = [((float(x) - x0) / cell + 1, (float(y) - y0) / cell + 1) for x, y in poly]
        area = sum(pts[i][0] * pts[(i + 1) % len(pts)][1] - pts[(i + 1) % len(pts)][0] * pts[i][1] for i in range(len(pts)))
        draw.polygon(pts, fill=1 if area > 0 else 0)
    inside = ndimage.distance_transform_edt(np.asarray(img, dtype=bool)) * cell
    # the middle of the shape when it has the room: a magnet off-centre makes the picture hang askew
    cy, cx = [int(round(v)) for v in ndimage.center_of_mass(inside > 0)]
    if 0 <= cy < inside.shape[0] and 0 <= cx < inside.shape[1] and inside[cy, cx] >= need:
        iy, ix = cy, cx
    else:
        iy, ix = np.unravel_index(int(np.argmax(inside)), inside.shape)
    return x0 + (ix - 1) * cell, y0 + (iy - 1) * cell, float(inside[iy, ix])


def build(M, Invalid, p, product):
    C, J = M.CrossSection, M.JoinType.Round
    n = lambda key, d: _num(Invalid, p, key, d)       # noqa: E731
    width, t, frame, step = n("width", 50), n("thickness", 3), n("frame", 1.2), n("relief", 0.6)
    body_kind = p.get("body", BODIES[product][0])
    if body_kind not in BODIES[product]:
        raise Invalid("bad_choice", "body")
    flush, rim, bevel = (bool(p.get(f, False)) for f in ("flush", "rim", "bevel"))
    eyelet = bool(p.get("eyelet", False)) and product in ("charm", "keychain", "earrings", "ornament")
    warn = []
    lines = [str(x).strip() for x in (p.get("lines") or []) if str(x).strip()]
    art_path = p.get("artwork_path")
    if not art_path and not lines:
        raise Invalid("no_text")
    is_text = not art_path
    edge = max(frame, 1.0) if (rim or (is_text and body_kind == "image")) else frame      # a rim needs a body to stand on, letters a body to hold them
    geometric = body_kind not in ("image", "rect")
    if is_text and body_kind == "rect":
        edge += 2.0                                           # letters on a plate want air round them, a picture brings its own

    # ── the picture: its colours bottom to top, in the place they will have on the body ─────────────────────────
    shape2d, inner_r = (None, 0.0)
    if geometric:
        shape2d, inner_r = _shape(M, body_kind, width)
        inner_r -= edge
        if inner_r < 4:
            raise Invalid("shape_too_small")
    options = {
        "n": int(n("colors_n", 4)), "background": "auto" if p.get("remove_bg", True) else "keep", "background_strength": n("bg_strength", 30), "smooth": n("smooth", 0.3),
        "contrast": n("contrast", 100) / 100, "brightness": n("brightness", 100) / 100, "saturation": n("saturation", 100) / 100,
        "palette": p.get("palette") or [], "merge": p.get("merge") or [], "order": p.get("order") or [],
        "assign": {part[6:]: c["code"] for part, c in (p.get("part_colors") or {}).items() if part.startswith("color_") and isinstance(c, dict) and c.get("code")},
    }
    art_w = width - 2 * edge
    if art_w < 6:
        raise Invalid("shape_too_small")
    try:
        if is_text:
            art, tinfo = S.text(M, lines[:2], p.get("font"), 10, scales=[1.0, 0.7])
            w0, h0 = S.size(art)
            k = (2 * (inner_r - 1.0) / math.hypot(w0, h0)) if geometric else art_w / w0
            art = S.fit(art, width_mm=w0 * k)
            pal = options["palette"]
            first = options["assign"].get("1")
            code = first or (min(pal, key=lambda f: _light(f[1]))[0] if pal else "")
            hx = dict((c, h) for c, h in pal).get(code, "#222222")
            layers = [{"index": 1, "rgb": hx, "code": code, "hex": hx, "share": 1.0, "own": art, "stack": art}]
            info = {"source": "text", "silhouette": art, "size": list(S.size(art)), "found": 1, "wanted": 1, "background": "alpha", "missing_chars": tinfo.get("missing_chars", [])}
        else:
            if geometric:
                probe = dict(options, fit=("circle", 100.0), n=1)
                kept = S.colors(M, art_path, 100.0, probe)[1]["background"] == "kept"
                # a whole photo fills the body to its edge, a cut-out motif sits inside it
                options["fit"] = ("cover", 2 * inner_r, 2 * inner_r) if kept else ("circle", 2 * (inner_r - 1.0))
            layers, info = S.colors(M, art_path, art_w, options)
    except S.ArtworkError as e:
        raise Invalid(e.code, str(e).split(": ", 1)[1] if ": " in str(e) else "")
    aw, ah = info["size"]
    if max(aw, ah) > 400:
        raise Invalid("artwork_too_tall")
    sil = info["silhouette"]
    if geometric:
        # the body is centred on the picture; what reaches over its inner edge is cut off
        shape2d = shape2d.translate([aw / 2, ah / 2])
        clip = shape2d.offset(-edge, J, 2.0, 48) if edge > 0 else shape2d
        for layer in layers:
            layer["own"], layer["stack"] = layer["own"] ^ clip, layer["stack"] ^ clip
        layers = [layer for layer in layers if layer["own"].area() >= S.MIN_ISLAND_MM2]
        if not layers:
            raise Invalid("empty_result")
        body2d = shape2d
    elif body_kind == "rect":
        r = min(4.0, (min(aw, ah) + 2 * edge) / 6)
        body2d = S.rounded_rect(M, aw + 2 * edge, ah + 2 * edge, r).translate([-edge, -edge])
        if info["background"] == "kept" and edge < r:          # the corners of a whole photo follow the corners of the plate
            clip = S.rounded_rect(M, aw, ah, max(0.5, r - edge))
            for layer in layers:
                layer["own"], layer["stack"] = layer["own"] ^ clip, layer["stack"] ^ clip
    else:
        grown = sil.offset(edge, J, 2.0, 24) if edge > 0 else sil
        body2d = _filled(M, grown.simplify(0.02), 4.0)
        body2d, links = S.joined(M, body2d, max(edge, 0.6), max(2.0, min(aw, ah) * 0.06))
        if links:
            warn.append("pieces_tied")
    if is_text and info.get("missing_chars"):
        warn.append("missing_chars")
    if info.get("ignored_outlines"):
        warn.append("outlines_ignored")
    count = len(layers)

    # ── eyelet: a ring on the outline, wherever the visitor put it ───────────────────────────────────────────────
    notes = {}
    hole2d = None
    ring_out = None
    if eyelet:
        hole_d, wall = n("eye_hole", 3), n("eye_wall", 2)
        travel = S.dense(S.outer_ring(body2d))
        px, py, nx, ny = S.along(travel, n("eye_pos", 0) / 100.0)
        reach = hole_d / 2 + wall / 2                        # the hole stays outside the shape, half the wall bites into it
        cx, cy = px + nx * reach, py + ny * reach
        ring_out = C.circle(hole_d / 2 + wall, 64).translate([cx, cy])
        hole2d = C.circle(hole_d / 2, 48).translate([cx, cy])
        # a neck from the ring into the body: on a thin or hollow spot of the outline the ring would hang by a thread
        neck = (C.circle(max(1.0, wall * 0.6), 24).translate([cx - nx * hole_d / 2, cy - ny * hole_d / 2]) + C.circle(max(1.0, wall * 0.6), 24).translate([px - nx * wall, py - ny * wall])).hull()
        body2d = body2d + ring_out + (neck - hole2d)
        notes["eyelet"] = {"x": round(cx, 2), "y": round(cy, 2), "hole": hole_d}
    plate2d = body2d - hole2d if hole2d is not None else body2d
    if hole2d is not None:
        for layer in layers:
            layer["own"], layer["stack"] = layer["own"] - hole2d, layer["stack"] - hole2d

    # ── heights ─────────────────────────────────────────────────────────────────────────────────────────────────
    if product == "magnet" and p.get("mount", "glue") not in MOUNTS:
        raise Invalid("bad_choice", "mount")
    mount = p.get("mount", "glue") if product == "magnet" else "none"
    mag_d, mag_h, mag_gap = (n("mag_d", 10), n("mag_h", 2), n("mag_gap", 0.2)) if mount != "none" else (0, 0, 0)
    if mount in ("glue", "press") and t < mag_h + 0.8:
        t = round(mag_h + 0.8, 2)                             # a floor of four layers over the magnet
        notes["thickened"] = t
    if flush:
        top = t
        solids = [(layer, layer["own"].extrude(INLAY).translate([0, 0, t - INLAY])) for layer in layers]
    else:
        top = t + count * step
        solids = [(layer, layer["stack"].extrude(step).translate([0, 0, t + i * step])) for i, layer in enumerate(layers)]
    body = plate2d.extrude(t)
    if bevel and not rim and edge >= 0.4:
        c = min(0.8, edge, t * 0.3)
        body = plate2d.extrude(t - c)
        for i in range(4):
            body = body + plate2d.offset(-c * (i + 1) / 4, J, 2.0, 16).extrude(c / 4 + 0.01).translate([0, 0, t - c + c * i / 4 - 0.01])
    whole = body
    if flush:
        pocket = None
        for layer, _ in solids:
            pocket = layer["own"] if pocket is None else pocket + layer["own"]
        body = body - pocket.extrude(INLAY + 1).translate([0, 0, t - INLAY])
    rim_solid = None
    if rim:
        rim_top = (t + INLAY) if flush else top
        rim2d = plate2d - plate2d.offset(-edge, J, 2.0, 24)
        if ring_out is not None:
            rim2d = rim2d - ring_out.offset(0.01, J, 2.0, 24)
        if not rim2d.is_empty():
            rim_solid = rim2d.extrude(rim_top - t).translate([0, 0, t])
            top = max(top, rim_top)

    # ── what the product adds ──────────────────────────────────────────────────────────────────────────────────
    if mount != "none":
        fit = mag_gap if mount == "glue" else 0.05            # glued: room for the glue; pressed: the plastic gives
        need = mag_d / 2 + fit
        mx, my, room = _roomiest(M, plate2d if ring_out is None else body2d - ring_out, need + 1.2)
        if room < need + 1.0:
            warn.append("magnet_no_room")
        if mount == "through":
            cut = M.Manifold.cylinder(top + 2, mag_d / 2 + 0.05, mag_d / 2 + 0.05, 64).translate([mx, my, -1])
            body, whole = body - cut, whole - cut
            solids = [(layer, s - cut) for layer, s in solids]
        else:
            cut = M.Manifold.cylinder(mag_h + 0.2 + 1, need, need, 64).translate([mx, my, -1])
            body, whole = body - cut, whole - cut
        notes["magnet"] = {"x": round(mx, 2), "y": round(my, 2), "d": mag_d, "h": mag_h, "mount": mount}
    if product == "coaster" and p.get("grooves", False):
        # rings cut two layers deep into the underside: the coaster does not slide on a wet table
        cut2d = None
        for i in range(3):
            a = plate2d.offset(-(6.0 + 9.0 * i), J, 2.0, 32)
            b = plate2d.offset(-(7.6 + 9.0 * i), J, 2.0, 32)
            if a.is_empty() or b.is_empty() or b.area() < 60:
                break
            cut2d = (a - b) if cut2d is None else cut2d + (a - b)
        if cut2d is not None:
            cut = cut2d.extrude(0.4 + 1).translate([0, 0, -1])
            body, whole = body - cut, whole - cut

    # ── colours of the parts ───────────────────────────────────────────────────────────────────────────────────
    own = _code(p, "body")
    if is_text:
        pal = sorted(p.get("palette") or [], key=lambda f: -_light(f[1]))
        body_color = own or ((pal[0][0], pal[0][1]) if pal else ("", "#ede6d6"))       # letters dark, the plate light
    else:
        # the plate is the picture's lowest colour unless the visitor gives it its own: no filament is changed for it,
        # so a picture of two colours is one change, which is what the farm prints today
        body_color = own or (layers[0]["code"], layers[0]["hex"])
        spools = p.get("palette") or []
        if not own and body_kind != "image" and info["background"] != "kept" and spools:
            # a cut-out motif on a round or square plate must not vanish into it: the plate gets the filament that is
            # furthest from every colour of the motif (a snowman with a black hat ends up on blue, not on black or white)
            motif = [S.hex_lab(layer["hex"]) for layer in layers]
            pick = max(spools, key=lambda f: min(float(((S.hex_lab(f[1]) - m) ** 2).sum()) for m in motif))
            body_color = (pick[0], pick[1])
    rim_color = _code(p, "rim") or body_color

    # ── one earring becomes a pair ─────────────────────────────────────────────────────────────────────────────
    x0, y0, x1, y1 = body2d.bounds()
    span = x1 - x0

    def laid(solid):
        one = solid.translate([-x0, -y0, 0])
        if product != "earrings":
            return one
        two = one.mirror([1, 0, 0]).translate([span, 0, 0]) if p.get("mirror", False) else one
        return one + two.translate([span + GAP, 0, 0])

    parts = {"body": laid(body)}
    pieces = [("body", parts["body"])]
    paint = {"body": body_color[1]}
    listed = []
    for layer, solid in solids:
        if solid.is_empty():
            continue
        name = "color_%d" % layer["index"]
        parts[name] = laid(solid)
        pieces.append((name, parts[name]))
        paint[name] = layer["hex"]
        listed.append({"part": name, "index": layer["index"], "rgb": layer["rgb"], "code": layer["code"], "hex": layer["hex"], "share": layer["share"]})
    has_rim = rim_solid is not None
    if has_rim:
        parts["rim"] = laid(rim_solid)
        pieces.append(("rim", parts["rim"]))
        paint["rim"] = rim_color[1]
    # the one solid the price and a one-colour print are made from: everything fused, a hair of overlap between the layers
    fused = whole
    if not flush:
        for i, (layer, solid) in enumerate(solids):
            fused = fused + layer["stack"].extrude(step + 0.01).translate([0, 0, t + i * step - 0.01])
    if has_rim:
        fused = fused + rim_solid.translate([0, 0, -0.01])
    parts["all"] = laid(fused)
    parts["_pieces"] = {"all": pieces}

    # ── what the print needs ───────────────────────────────────────────────────────────────────────────────────
    # the print from the bed up, band by band: which filaments each band of layers holds. One filament in every band =
    # the colours can be changed by height; two in one band = only a printer that changes filament by itself
    bands = [(0.0, "body", [body_color] + ([(c["code"], c["hex"]) for c in listed] if flush else []))]
    if flush:
        if has_rim:
            bands.append((t, "rim", [rim_color]))
    else:
        for i, c in enumerate(listed):
            bands.append((round(t + i * step, 2), c["part"], [(c["code"], c["hex"])] + ([rim_color] if has_rim else [])))
    filaments = []
    for _, _, held in bands:
        for code, _ in held:
            if code not in filaments:
                filaments.append(code)
    multi = any(len({code for code, _ in held}) > 1 for _, _, held in bands)
    changes = []
    if not multi:
        last = body_color[0]
        for z, part, held in bands[1:]:
            if held[0][0] != last:
                changes.append({"z": z, "part": part, "code": held[0][0], "hex": held[0][1]})
                last = held[0][0]
    thin = max((S.printability(M, layer["own"], 0.45)["thin_pct"] for layer in layers), default=0)
    if thin > 35:
        warn.append("thin_lines")
    if mount == "through" and count:
        warn.append("magnet_shows")
    w_all, h_all = (2 * span + GAP if product == "earrings" else span), y1 - y0
    notes.update({
        "outer": [round(w_all, 1), round(h_all, 1), round(top, 1)], "each": [round(span, 1), round(h_all, 1), round(top, 1)], "copies": 2 if product == "earrings" else 1,
        "colors": listed, "body_color": {"code": body_color[0], "hex": body_color[1]}, "paint": paint, "parts": [name for name, _ in pieces],
        "filaments": len(filaments), "multi_material": bool(multi), "color_changes": changes, "found": info["found"], "wanted": info["wanted"],
        "background": info["background"], "source": info["source"], "warnings": warn, "thin_pct": thin, "missing_chars": info.get("missing_chars", []),
    })
    if has_rim:
        notes["rim_color"] = {"code": rim_color[0], "hex": rim_color[1]}
    if len(changes) == 1:
        notes["color_change_mm"] = changes[0]["z"]          # one change is what the farm and the slicer projects already know
    if eyelet:
        # the outline the eyelet travels on, for dragging it in the preview: 120 points evenly spread along it, the
        # first one where eye_pos is 0, so a point's place in the list is the share of the way round
        notes["outline"] = [[round(c - o, 1) for c, o in zip(S.along(travel, i / 120.0)[:2], (x0, y0))] for i in range(120)]
        notes["eyelet"]["x"], notes["eyelet"]["y"] = round(notes["eyelet"]["x"] - x0, 2), round(notes["eyelet"]["y"] - y0, 2)
        notes["eyelet"]["z"] = round(t, 2)
    if "magnet" in notes:
        notes["magnet"]["x"], notes["magnet"]["y"] = round(notes["magnet"]["x"] - x0, 2), round(notes["magnet"]["y"] - y0, 2)
    return parts, notes


BUILDERS = {product: (lambda M, Invalid, p, product=product: build(M, Invalid, p, product)) for product in PRODUCTS}
