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

The gingerbread is the same thing the other way round: the shape is ours (a man, a heart, a star, a tree), icing is
piped on it and the visitor's name goes across it. Dough and icing: two colours one on another, one filament change.

The cookie takes a picture or a silhouette as its dough and the strokes the visitor draws on it in the preview as
icing: one part `icing_<n>` for every filament drawn with, stacked like the colours of a picture.

The badge is a topper for a retractable badge reel: the picture, the wearer's name under it in one of the picture's
colours, and a shallow pocket in the back for the reel's sticky dot.

The medallion is a round or star plate with an eyelet and, next to it on the bed, the open links of its chain.

The photo_organizer is the same dish grown tall: walls along the outline of the picture, and inside them a grid of
dividers, a block with round holes, or one open pocket.

The tray is a little dish in the shape of the picture: a floor, a wall round it, and the picture cut into the floor
(one colour), inlaid in it in colours (a multi-material print) or left out.
"""
import math

import shape2d as S

PRODUCTS = ("charm", "keychain", "earrings", "ornament", "magnet", "coaster", "gingerbread", "name_letter", "cookie", "topper", "tray", "badge", "medallion", "photo_organizer")

# the widest range any product allows; each product's own limits are ParametricGenerator::FIELDS
LIMITS = {
    "width": (10, 250), "height": (6, 250), "wall": (1.2, 3), "thickness": (1.2, 15), "frame": (0, 10), "relief": (0.2, 2), "colors_n": (1, 8), "bg_strength": (0, 100), "smooth": (0, 1),
    "contrast": (50, 150), "brightness": (50, 150), "saturation": (0, 200), "eye_pos": (0, 100), "eye_hole": (1.5, 8), "eye_wall": (1.2, 4),
    "mag_d": (4, 30), "mag_h": (0.4, 6), "mag_gap": (0, 0.4), "text_size": (30, 150), "text_y": (-60, 60), "spike": (30, 100), "spikes": (1, 2), "links": (0, 40), "cell": (20, 80), "hole_d": (8, 40),
}
BODIES = {"charm": ("image", "circle", "rect"), "keychain": ("rect", "image", "circle"), "earrings": ("image", "circle"), "ornament": ("image", "circle", "star"),
          "magnet": ("image", "circle", "rect"), "coaster": ("circle", "square", "hex"), "gingerbread": ("image",), "name_letter": ("image",), "cookie": ("image", "circle"), "topper": ("image",), "tray": ("image", "circle"), "badge": ("image", "circle", "rect"), "medallion": ("circle", "star", "hex"), "photo_organizer": ("image", "circle")}
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


def _caption(M, p, layers, info, text, body_kind, fit_d, warn):
    """
    A name under the picture (badge). The letters join one of the picture's own colours, the one that reads best on the
    plate, so the name costs no filament and no change of its own. Returns the layers and the info with the name in them.
    """
    C, J = M.CrossSection, M.JoinType.Round
    aw, ah = info["size"]
    art, tinfo = S.text(M, [text], p.get("font"), 10)
    w0, h0 = S.size(art)
    k = min(aw / w0, 0.16 * ah / 10, 0.7)                      # as wide as the picture at most, and never the main thing: capitals of 7 mm or less
    if 10 * k < 3.0:
        warn.append("name_small")
    art = S.fit(art, width_mm=w0 * k)
    tx0, ty0, tx1, ty1 = art.bounds()
    tw, th, m = tx1 - tx0, ty1 - ty0, 1.2
    left = aw / 2 - tw / 2
    # the lowest point of the picture above the name: the name hangs there, not under the whole picture's box
    sil = info["silhouette"]
    over = sil ^ C.square([tw + 2 * m, ah + 2]).translate([left - m, -1])
    foot = 0.0 if over.is_empty() else over.bounds()[1]
    top = foot - 1.0
    art = art.translate([left - tx0, top - ty1])
    pad = S.rounded_rect(M, tw + 2 * m, th + m + 1.5, min(2.0, (th + 2 * m) / 3)).translate([left - m, top - th - m])
    # the name takes the colour furthest in lightness from the plate (the plate is the picture's lowest colour unless
    # the picture is a motif on a round or square plate, which gets a filament of its own: there the darkest reads best)
    if (body_kind == "image" or info["background"] == "kept") and len(layers) > 1:
        base = _light(layers[0]["hex"])
        at = max(range(1, len(layers)), key=lambda i: abs(_light(layers[i]["hex"]) - base))
    else:
        at = min(range(len(layers)), key=lambda i: _light(layers[i]["hex"]))
    for i, layer in enumerate(layers):
        if i <= at:
            layer["stack"] = layer["stack"] + art               # the colours under the name carry it
    layers[at]["own"] = layers[at]["own"] + art
    sil = sil + pad
    dx, dy = max(0.0, m - left), max(0.0, -(top - th - m))
    width, height = max(aw, left + tw + m) + dx, ah + dy
    scale = min(1.0, fit_d / math.hypot(width, height)) if fit_d else 1.0      # in a round body the two together must fit
    move = lambda cs: cs.translate([dx, dy]).scale([scale, scale])            # noqa: E731
    for layer in layers:
        layer["own"], layer["stack"] = move(layer["own"]), move(layer["stack"])
    info = dict(info, silhouette=move(sil), size=[width * scale, height * scale], missing_chars=tinfo.get("missing_chars", []),
                caption={"index": layers[at]["index"], "height": round(10 * k * scale, 1)})
    return layers, info


LINK = (30.0, 18.0, 4.0, 0.4)    # a chain link: long, wide, the bar, and how much narrower than the bar its gap is (it snaps in)
LINK_ROW = 7                     # links in a row on the bed


def _chain(M, count, tall, medal_w, medal_h):
    """
    Open links of a chain: an oval ring with a gap in the middle of one long side, where a chain under load does not
    pull. As tall as the medal's plate, so they are printed in its one filament. They are laid round a medal whose
    corner is at the origin: first beside it, as high as it reaches, then in rows above it, never wider than a row of
    seven, so the whole plate fits a bed of 250 mm. Returns (solid, width and depth of the plate with the medal) or None.
    """
    C, J = M.CrossSection, M.JoinType.Round
    long, wide, bar, snap = LINK
    gap = min(bar, tall) - snap                              # the neighbour goes through by its thinner side
    oval = (C.circle(wide / 2, 64).translate([wide / 2, wide / 2]) + C.circle(wide / 2, 64).translate([long - wide / 2, wide / 2])).hull()
    link = oval - oval.offset(-bar, J, 2.0, 32) - C.square([gap, bar + 2]).translate([long / 2 - gap / 2, wide - bar - 1])
    if count < 1:
        return None
    one = link.extrude(tall)
    step_x, step_y = long + 3.0, wide + 3.0
    widest = LINK_ROW * step_x - 3.0
    beside = (max(0, int((widest - medal_w - 4.0 + 3.0) // step_x)), max(0, int((medal_h + 3.0) // step_y)))      # columns, rows next to the medal
    spots = [(medal_w + 4.0 + c * step_x, r * step_y) for r in range(beside[1]) for c in range(beside[0])]
    spots += [((i % LINK_ROW) * step_x, medal_h + 4.0 + (i // LINK_ROW) * step_y) for i in range(max(0, count - len(spots)))]
    spots = spots[:count]
    return (M.Manifold.compose([one.translate([x, y, 0]) for x, y in spots]),
            max([medal_w] + [x + long for x, _ in spots]), max([medal_h] + [y + wide for _, y in spots]))


INSIDES = ("grid", "holes", "open")


def _inside(M, Invalid, p, inner, wall, floor, rise, cell, hole_d):
    """
    What divides a tall dish into an organizer: walls in a grid with cells of about `cell` (pencils, make-up), or a
    solid block with round holes of `hole_d` in a honeycomb (toothbrushes), or nothing (one pocket).
    Returns (the solid to add, or None; what it makes: {"kind", "count"}).
    """
    C, J = M.CrossSection, M.JoinType.Round
    kind = p.get("inside", INSIDES[0])
    if kind not in INSIDES:
        raise Invalid("bad_choice", "inside")
    if kind == "open":
        return None, {"kind": kind, "count": 1}
    x0, y0, x1, y1 = inner.bounds()
    if kind == "grid":
        bars = C()
        for lo, hi, across in ((x0, x1, False), (y0, y1, True)):
            cells = max(1, int(round((hi - lo) / cell)))
            for i in range(1, cells):
                at = lo + i * (hi - lo) / cells - wall / 2
                bars = bars + (C.square([x1 - x0 + 2, wall]).translate([x0 - 1, at]) if across else C.square([wall, y1 - y0 + 2]).translate([at, y0 - 1]))
        count = len([c for c in (inner - bars).decompose() if c.area() > 60.0])      # a sliver in a corner is no compartment
        if bars.is_empty():
            return None, {"kind": kind, "count": 1}
        # the dividers reach a little into the wall, so they are one body with it
        return (bars ^ inner.offset(0.3, J, 2.0, 16)).extrude(rise - floor).translate([0, 0, floor]), {"kind": kind, "count": count}
    pitch = hole_d + max(wall, 1.6)
    zone = inner.offset(-(hole_d / 2 + 0.4), J, 2.0, 16)
    cx, cy = (x0 + x1) / 2, (y0 + y1) / 2
    spots = []
    rows = int((y1 - y0) / (pitch * 0.866)) + 2
    cols = int((x1 - x0) / pitch) + 2
    for r in range(-rows, rows + 1):
        for c in range(-cols, cols + 1):
            x, y = cx + (c + (0.5 if r % 2 else 0.0)) * pitch, cy + r * pitch * 0.866
            if not zone.is_empty() and not (C.square([0.2, 0.2]).translate([x - 0.1, y - 0.1]) ^ zone).is_empty():
                spots.append((x, y))
    if not spots:
        raise Invalid("shape_too_small")
    drill = M.Manifold.cylinder(rise - floor + 1, hole_d / 2, hole_d / 2, 64)
    block = inner.offset(0.3, J, 2.0, 16).extrude(rise - floor).translate([0, 0, floor])
    return block - M.Manifold.compose([drill.translate([x, y, floor]) for x, y in spots]), {"kind": kind, "count": len(spots)}


def build(M, Invalid, p, product):
    C, J = M.CrossSection, M.JoinType.Round
    n = lambda key, d: _num(Invalid, p, key, d)       # noqa: E731
    width, t, frame, step = n("width", 50), n("thickness", 3), n("frame", 1.2), n("relief", 0.6)
    body_kind = p.get("body", BODIES[product][0])
    if body_kind not in BODIES[product]:
        raise Invalid("bad_choice", "body")
    flush, rim, bevel = (bool(p.get(f, False)) for f in ("flush", "rim", "bevel"))
    dish = product in ("tray", "photo_organizer")             # the shape is a little dish, the picture is in its floor
    if product == "photo_organizer":
        floor_mode, frame = "plain", 0.0                      # a tall dish: nobody sees its floor, the picture only gives the outline
    else:
        floor_mode = p.get("floor", "engraved") if dish else None
    if dish:
        if floor_mode not in ("engraved", "colors", "plain"):
            raise Invalid("bad_choice", "floor")
        flush, rim, bevel = floor_mode == "colors", False, False       # colours of a floor can only be inlaid: the walls stand in the same layers
    eyelet = (bool(p.get("eyelet", False)) and product in ("charm", "keychain", "earrings", "ornament", "gingerbread", "medallion")) or (bool(p.get("hang", False)) and product in ("cookie", "name_letter"))
    biscuit = product == "cookie"                             # a picture or a silhouette as dough, icing piped on it by hand
    cookie = product in ("gingerbread", "name_letter", "topper")      # the shape is ours, the visitor brings the name
    warn = []
    lines = [str(x).strip() for x in (p.get("lines") or []) if str(x).strip()]
    art_path = p.get("artwork_path")
    if not cookie and not art_path and not lines:
        raise Invalid("no_text")
    is_text = not art_path and not cookie
    edge = max(frame, 1.0) if (rim or (is_text and body_kind == "image")) else frame      # a rim needs a body to stand on, letters a body to hold them
    geometric = body_kind not in ("image", "rect")
    if dish:
        edge = frame + n("wall", 1.6)                         # the picture keeps off the wall by the frame
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
        if cookie:
            layers, info = {"gingerbread": _gingerbread, "name_letter": _name_letter, "topper": _topper}[product](M, Invalid, p, width, lines, warn)
        elif is_text:
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
            if product == "badge" and lines:
                if geometric and info["background"] == "kept":
                    warn.append("caption_photo")              # a whole photo fills the circle: no room under it
                else:
                    layers, info = _caption(M, p, layers, info, lines[0], body_kind, 2 * (inner_r - 1.0) if geometric else 0.0, warn)
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
    elif cookie:
        body2d = info["body"]
    else:
        grown = sil.offset(edge, J, 2.0, 24) if edge > 0 else sil
        body2d = _filled(M, grown.simplify(0.02), 4.0)
        body2d, links = S.joined(M, body2d, max(edge, 0.6), max(2.0, min(aw, ah) * 0.06))
        if links:
            warn.append("pieces_tied")
    if (is_text or cookie or info.get("caption")) and info.get("missing_chars"):
        warn.append("missing_chars")
    if info.get("ignored_outlines"):
        warn.append("outlines_ignored")
    carved, dish_color = None, None
    if dish and floor_mode != "colors":
        # one colour: the picture is cut two layers deep into the floor (everything but its ground colour), or left out
        # a picture in colours gives the dish its ground colour; the black of a silhouette is no wish for a black dish
        dish_color = (layers[0]["code"], layers[0]["hex"]) if layers and not is_text and info["found"] > 1 else None
        if floor_mode == "engraved":
            for layer in (layers if is_text else layers[1:]):
                carved = layer["own"] if carved is None else carved + layer["own"]
        layers = []
    dough = None
    if biscuit:
        spools = p.get("palette") or []
        dough = min(spools, key=lambda f: float(((S.hex_lab(f[1]) - S.hex_lab(DOUGH)) ** 2).sum())) if spools else ("", DOUGH)
        # a silhouette is only the shape of the biscuit; of a picture in colours the part that is dough anyway is left out
        if not is_text and (info["found"] == 1 or (layers and layers[0]["code"] == dough[0])):
            layers = layers[1:]
        clip = body2d.offset(-(ROUND + 0.3), J, 2.0, 24)       # icing stays off the rounded edge
        for layer in layers:
            layer["own"], layer["stack"] = layer["own"] ^ clip, layer["stack"] ^ clip
        layers = [layer for layer in layers if not layer["stack"].is_empty()]
        piped = _icing(M, p, aw, clip)
        if piped:
            # what is piped lies on everything: under each stroke the layers below are filled up to it, so every layer of
            # the print still holds one filament (see the picture's colours in shape2d.colors)
            above = None
            for layer in reversed(piped):
                layer["stack"] = layer["own"] if above is None else layer["own"] + above
                above = layer["stack"]
            for i, layer in enumerate(piped[:-1]):
                layer["own"] = layer["own"] - piped[i + 1]["stack"]
            for layer in layers:
                layer["own"], layer["stack"] = layer["own"] - above, layer["stack"] + above
            layers = layers + piped
    count = len(layers)

    # ── eyelet: a ring on the outline, wherever the visitor put it ───────────────────────────────────────────────
    notes = {}
    hole2d = None
    ring_out = None
    if eyelet:
        hole_d, wall = n("eye_hole", 3), n("eye_wall", 2)
        travel = S.dense(S.outer_ring(body2d))
        if cookie and info.get("top"):
            travel = S.started(travel, info["top"])
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
    pocketed = product in ("magnet", "badge")                # a pocket in the back: for a magnet, or for the sticky dot of a badge reel
    if pocketed and p.get("mount", "glue") not in MOUNTS:
        raise Invalid("bad_choice", "mount")
    mount = p.get("mount", "glue") if pocketed else "none"
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
    body = _rounded_top(M, plate2d, t, ROUND) if biscuit else plate2d.extrude(t)
    if bevel and not rim and edge >= 0.4 and not biscuit:
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
    if dish:
        rise = n("height", 15)
        if rise < t + 3:
            raise Invalid("dish_low")
        inner = body2d.offset(-n("wall", 1.6), J, 2.0, 24)
        if carved is not None:
            body = body - (carved ^ inner).extrude(0.6 + 1).translate([0, 0, t - 0.6])
            whole = body
        walls = (plate2d - inner).extrude(rise)
        body, whole, top = body + walls, whole + walls, rise
        if product == "photo_organizer":
            filling, made = _inside(M, Invalid, p, inner, n("wall", 1.6), t, rise, n("cell", 40), n("hole_d", 20))
            if filling is not None:
                body, whole = body + filling, whole + filling
            notes["pockets"] = dict(made, depth=round(rise - t, 1))
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
            deep = mag_h + (0.2 if product == "magnet" else 0.0)      # a magnet must not stand proud; a sticky dot is as deep as asked
            cut = M.Manifold.cylinder(deep + 1, need, need, 64).translate([mx, my, -1])
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

    chain = None
    if product == "medallion":
        # the links lie round the medal on the bed and belong to its plate: one part, one filament
        bx0, by0, bx1, by1 = body2d.bounds()
        chain = _chain(M, int(n("links", 20)), t, bx1 - bx0, by1 - by0)
        if chain is not None:
            links = chain[0].translate([bx0, by0, 0])
            body, whole = body + links, whole + links
            notes["chain"] = {"links": int(n("links", 20)), "length": round(int(n("links", 20)) * (LINK[0] - 2 * LINK[2]) / 10.0) * 10}

    # ── colours of the parts ───────────────────────────────────────────────────────────────────────────────────
    own = _code(p, "body")
    if cookie:
        body_color = own or info["dough"]
    elif biscuit:
        body_color = own or (dough[0], dough[1])
    elif dish and not layers:
        spools = sorted(p.get("palette") or [], key=lambda f: -_light(f[1]))
        body_color = own or dish_color or ((spools[0][0], spools[0][1]) if spools else ("", "#ede6d6"))
    elif is_text:
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
        name = layer.get("part") or "color_%d" % layer["index"]
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
    thin = max((S.printability(M, layer["own"], 0.45)["thin_pct"] for layer in layers if not layer["own"].is_empty()), default=0)
    if thin > 35:
        warn.append("thin_lines")
    if mount == "through" and count:
        warn.append("magnet_shows")
    w_all, h_all = (2 * span + GAP if product == "earrings" else span), y1 - y0
    each_h = h_all
    if chain is not None:
        w_all, h_all = chain[1], chain[2]
    notes.update({
        "outer": [round(w_all, 1), round(h_all, 1), round(top, 1)], "each": [round(span, 1), round(each_h, 1), round(top, 1)], "copies": 2 if product == "earrings" else 1,
        "colors": listed, "body_color": {"code": body_color[0], "hex": body_color[1]}, "paint": paint, "parts": [name for name, _ in pieces],
        "filaments": len(filaments), "multi_material": bool(multi), "color_changes": changes, "found": info["found"], "wanted": info["wanted"],
        "background": info["background"], "source": info["source"], "warnings": warn, "thin_pct": thin, "missing_chars": info.get("missing_chars", []),
    })
    if has_rim:
        notes["rim_color"] = {"code": rim_color[0], "hex": rim_color[1]}
    if info.get("caption"):
        notes["caption"] = info["caption"]
    if biscuit:
        # where the picture's own corner lies in the model and how wide it is: strokes are stored in shares of that width
        notes["frame"] = [round(-x0, 2), round(-y0, 2), round(aw, 2)]
        notes["draw_z"] = round(top, 2)
        notes["strokes"] = len([c for c in listed if c["part"].startswith("icing_")])
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


# ── gingerbread with a name ──────────────────────────────────────────────────

COOKIES = ("man", "heart", "star", "tree")
ICINGS = ("wavy", "plain", "none")
DOUGH = "#b0703c"           # baked gingerbread: the plate gets the filament nearest to it


def _piped(M, cs, inset, amp, wave, width):
    """A line of icing piped along the inside of an outline: wavy (amp > 0) or plain, `width` thick."""
    import numpy as np
    J = M.JoinType.Round
    ring = S.outer_ring(cs.offset(-inset, J, 2.0, 32))
    if ring is None:
        return M.CrossSection()
    pts = S.dense(ring, 0.8)
    step = np.linalg.norm(np.roll(pts, -1, axis=0) - pts, axis=1)
    run = np.concatenate([[0.0], np.cumsum(step)[:-1]])
    total = float(step.sum())
    waves = max(6, int(round(total / wave)))
    way = np.roll(pts, -3, axis=0) - np.roll(pts, 3, axis=0)
    way /= np.maximum(np.linalg.norm(way, axis=1, keepdims=True), 1e-9)
    out = np.stack([way[:, 1], -way[:, 0]], 1)               # the ring runs counter-clockwise: out of it is to the right
    line = M.CrossSection([pts + out * (amp * np.sin(2 * np.pi * waves * run / total))[:, None]])
    return (line.offset(width / 2, J, 2.0, 8) - line.offset(-width / 2, J, 2.0, 8)).simplify(0.4)       # in drawing units: a hundredth of a millimetre on the biscuit


def _garland(M, x0, x1, y, amp, width, humps):
    """A wavy (or straight) band from x0 to x1: icing across a branch or an ankle."""
    top, bottom = [], []
    for i in range(49):
        x = x0 + (x1 - x0) * i / 48
        dy = amp * math.sin(2 * math.pi * humps * i / 48)
        top.append((x, y + dy + width / 2))
        bottom.append((x, y + dy - width / 2))
    return M.CrossSection([bottom + top[::-1]])


def _gingerbread(M, Invalid, p, width, lines, warn):
    """
    The biscuit, what is piped on it and where its name goes. Drawn in units of a thousandth of the drawing and scaled
    to the width asked for. Returns (body, icing with the name, info).
    """
    C, J = M.CrossSection, M.JoinType.Round
    kind = p.get("cookie", COOKIES[0])
    icing = p.get("icing", ICINGS[0])
    if kind not in COOKIES:
        raise Invalid("bad_choice", "cookie")
    if icing not in ICINGS:
        raise Invalid("bad_choice", "icing")
    amp = 1.0 if icing == "wavy" else 0.0
    rr = lambda x, y, w, h, r, turn=0: S.rounded_rect(M, w, h, r).translate([-w / 2, -h / 2]).rotate(turn).translate([x + w / 2, y + h / 2])      # noqa: E731
    dot = lambda x, y, r: C.circle(r, 32).translate([x, y])       # noqa: E731
    extras, top = [], None
    if kind == "man":
        body = dot(500, 770, 190) + rr(330, 260, 340, 380, 90) + rr(110, 470, 780, 130, 65) + rr(330, 50, 140, 330, 70, -12) + rr(530, 50, 140, 330, 70, 12)
        body = body.offset(25, J, 2.0, 32).offset(-25, J, 2.0, 32)
        name = (500.0, 535.0, 640.0, 92.0)                   # centre, the widest it may be, the tallest its capitals may be
        arc = [(500 + 105 * math.cos(math.radians(a)), 770 + 105 * math.sin(math.radians(a))) for a in range(205, 336, 5)]
        arc += [(500 + 79 * math.cos(math.radians(a)), 770 + 79 * math.sin(math.radians(a))) for a in range(335, 204, -5)]
        extras = [dot(430, 800, 28), dot(570, 800, 28), C([arc]), dot(500, 400, 32), dot(500, 310, 32)]
        if icing != "none":
            extras += [_garland(M, 310, 480, 160, 16 * amp, 26, 1), _garland(M, 520, 690, 160, 16 * amp, 26, 1)]
    elif kind == "heart":
        pts = []
        for i in range(160):
            t = 2 * math.pi * i / 160
            pts.append((500 + 16 * math.sin(t) ** 3 * 30, 520 + (13 * math.cos(t) - 5 * math.cos(2 * t) - 2 * math.cos(3 * t) - math.cos(4 * t)) * 30))
        body = C([pts[::-1]])
        name = (500.0, 470.0, 560.0, 130.0)
        top = (500.0, 670.0)                                 # hung by the dip between the lobes, not by one of them
    elif kind == "star":
        R, r = 520.0, 290.0
        pts = [((R if i % 2 == 0 else r) * math.cos(math.pi / 2 + i * math.pi / 5) + 500, (R if i % 2 == 0 else r) * math.sin(math.pi / 2 + i * math.pi / 5) + 480) for i in range(10)]
        body = C([pts]).offset(-30, J, 2.0, 32).offset(30, J, 2.0, 32)
        name = (500.0, 470.0, 430.0, 120.0)
    else:
        body = C([[(500, 900), (240, 580), (760, 580)]]) + C([[(500, 720), (160, 360), (840, 360)]]) + C([[(500, 530), (80, 140), (920, 140)]]) + rr(430, 30, 140, 150, 12)
        body = body.offset(-14, J, 2.0, 32).offset(28, J, 2.0, 32).offset(-14, J, 2.0, 32)
        name = (500.0, 225.0, 560.0, 90.0)
        if icing != "none":
            extras = [_garland(M, 330, 670, 455, 18 * amp, 26, 2), _garland(M, 400, 600, 650, 16 * amp, 26, 1)]
    if kind in ("heart", "star") and icing != "none":
        extras.append(_piped(M, body, 62, 16 * amp, 110, 26))
    x0, y0, x1, _ = body.bounds()
    k = width / (x1 - x0)                                    # as wide as asked, rounded corners included
    place = lambda cs: cs.translate([-x0, -y0]).scale([k, k])       # noqa: E731
    body = place(body).simplify(0.02)
    piped = None
    for extra in extras:
        piped = extra if piped is None else piped + extra
    art = place(piped) if piped is not None else C()
    missing = []
    if lines:
        try:
            text, tinfo = S.text(M, lines[:1], p.get("font"), 10)
        except S.ArtworkError as e:
            raise Invalid(e.code)
        missing = tinfo.get("missing_chars", [])
        w0, h0 = S.size(text)
        fit = min(name[2] * k / w0, name[3] * k / 10.0)
        text = S.fit(text, width_mm=w0 * fit)
        tw, th = S.size(text)
        if fit * 10.0 < 3.0:
            warn.append("name_small")                        # capitals under 3 mm: a shorter name or a wider biscuit
        art = art + text.translate([(name[0] - x0) * k - tw / 2, (name[1] - y0) * k - th / 2])
    art = art ^ body.offset(-0.8, J, 2.0, 16)
    if art.is_empty():
        raise Invalid("no_text")
    pal = p.get("palette") or []
    own = ((p.get("part_colors") or {}).get("color_1") or {}).get("code") if isinstance((p.get("part_colors") or {}).get("color_1"), dict) else None
    by_code = dict((c, h) for c, h in pal)
    white = max(pal, key=lambda f: _light(f[1])) if pal else ("", "#f4f4f2")
    code = own if own in by_code else white[0]
    hx = by_code.get(code, white[1])
    dough = min(pal, key=lambda f: float(((S.hex_lab(f[1]) - S.hex_lab(DOUGH)) ** 2).sum())) if pal else ("", DOUGH)
    bx0, by0, bx1, by1 = body.bounds()
    layers = [{"index": 1, "rgb": hx, "code": code, "hex": hx, "share": round(art.area() / max(body.area(), 1e-9), 4), "own": art, "stack": art}]
    info = {"source": "cookie", "silhouette": body, "size": [bx1 - bx0, by1 - by0], "found": 1, "wanted": 1, "background": "alpha", "missing_chars": missing,
            "body": body, "dough": (dough[0], dough[1]), "top": ((top[0] - x0) * k, (top[1] - y0) * k) if top else None}
    return layers, info


# ── a big letter with the name on it ─────────────────────────────────────────

def _room(M, cs, aspect, margin):
    """
    The biggest rectangle of the given aspect (width / height) that lies inside an outline, `margin` away from its
    edge, lying flat or turned a quarter (up a stem): (cx, cy, width, height, turned), or None when there is no room.
    """
    import numpy as np
    from PIL import Image, ImageDraw
    from scipy import ndimage
    x0, y0, x1, y1 = cs.bounds()
    cell = max(0.3, max(x1 - x0, y1 - y0) / 260)
    size = (int((x1 - x0) / cell) + 3, int((y1 - y0) / cell) + 3)
    img = Image.new("1", size, 0)
    draw = ImageDraw.Draw(img)
    rings = []
    for poly in cs.to_polygons():
        pts = [((float(x) - x0) / cell + 1, (float(y) - y0) / cell + 1) for x, y in poly]
        rings.append((sum(pts[i][0] * pts[(i + 1) % len(pts)][1] - pts[(i + 1) % len(pts)][0] * pts[i][1] for i in range(len(pts))), pts))
    for area, pts in sorted(rings, key=lambda ring: -ring[0]):      # the outer rings first, then their holes
        draw.polygon(pts, fill=1 if area > 0 else 0)
    inside = ndimage.distance_transform_edt(np.asarray(img, dtype=bool)) * cell >= margin
    best = None
    for turned, mask in ((False, inside), (True, inside.T)):
        rows = mask.shape[0]
        # heights to try, tallest first; for each one the rows that are inside for the whole height and their longest run
        for h in sorted({int(round(v)) for v in np.geomspace(3, rows, 28)}, reverse=True):
            if best is not None and h * cell <= best[0]:
                break
            band = ndimage.minimum_filter1d(mask.astype(np.uint8), h, axis=0, mode="constant") > 0
            if not band.any():
                continue
            edge = np.zeros((rows, 1), dtype=np.int8)
            d = np.diff(np.concatenate([edge, band.astype(np.int8), edge], axis=1), axis=1)
            for r in np.flatnonzero(band.any(axis=1)):
                starts, ends = np.flatnonzero(d[r] == 1), np.flatnonzero(d[r] == -1)
                i = int(np.argmax(ends - starts))
                tall = min(h * cell, (ends[i] - starts[i]) * cell / aspect)       # its height limits the text, or its width
                if best is None or tall > best[0]:
                    best = (tall, (starts[i] + ends[i]) / 2, float(r), turned)
    if best is None:
        return None
    tall, cx, cy, turned = best
    if turned:
        cx, cy = cy, cx
    return x0 + (cx - 1) * cell, y0 + (cy - 1) * cell, tall * aspect, tall, turned


def _name_letter(M, Invalid, p, width, lines, warn):
    """
    The first letter of a name, big and thick, with the whole name written on it where the letter has the most room:
    across a bar or up a stem. Returns (layers, info) like _gingerbread.
    """
    C, J = M.CrossSection, M.JoinType.Round
    name = lines[0] if lines else ""
    initial = (str(p.get("initial") or "").strip() or name)[:1].upper()
    if not initial:
        raise Invalid("no_text")
    height = _num(Invalid, p, "height", 120)
    try:
        letter, linfo = S.text(M, [initial], p.get("letter_font") or p.get("font"), 100)
        letter = S.fit(letter, height_mm=height).simplify(0.02)
        missing = list(linfo.get("missing_chars", []))
        art = C()
        if name:
            text, tinfo = S.text(M, [name], p.get("font"), 10)
            missing += [c for c in tinfo.get("missing_chars", []) if c not in missing]
            tw, th = S.size(text)
            place = _room(M, letter, tw / th, 1.5)
            if place is None or place[3] < 2.0:
                warn.append("name_no_room")
            else:
                cx, cy, w, h, turned = place
                if h < 4.0:
                    warn.append("name_small")
                text = S.fit(text, width_mm=w).translate([-w / 2, -h / 2])
                art = (text.rotate(90) if turned else text).translate([cx, cy]) ^ letter.offset(-0.6, J, 2.0, 16)
    except S.ArtworkError as e:
        raise Invalid(e.code)
    if missing and letter.is_empty():
        raise Invalid("no_text")
    pal = p.get("palette") or []
    by_code = dict((c, h) for c, h in pal)
    chosen = (p.get("part_colors") or {}).get("color_1")
    own = chosen.get("code") if isinstance(chosen, dict) else None
    light = max(pal, key=lambda f: _light(f[1])) if pal else ("", "#f4f4f2")
    dark = min(pal, key=lambda f: _light(f[1])) if pal else ("", "#1b1b1d")
    code = own if own in by_code else light[0]
    hx = by_code.get(code, light[1])
    x0, y0, x1, y1 = letter.bounds()
    layers = [] if art.is_empty() else [{"index": 1, "rgb": hx, "code": code, "hex": hx, "share": round(art.area() / max(letter.area(), 1e-9), 4), "own": art, "stack": art}]
    info = {"source": "letter", "silhouette": letter, "size": [x1 - x0, y1 - y0], "found": 1, "wanted": 1, "background": "alpha", "missing_chars": missing,
            "body": letter, "dough": (dark[0], dark[1]), "top": None}
    return layers, info


# ── a biscuit with icing piped by hand ───────────────────────────────────────

ROUND = 1.5                 # the top edge of a biscuit is rounded by this much
MAX_STROKES, MAX_ICINGS = 60, 6


def _rounded_top(M, outline, t, r):
    """A plate whose top edge is a quarter round, in steps a layer high: a biscuit, not a slab."""
    steps = max(3, int(round(r / 0.25)))
    solid = outline.extrude(t - r)
    for i in range(steps):
        inset = r * (1 - math.sqrt(1 - ((i + 0.5) / steps) ** 2))
        ring = outline.offset(-inset, M.JoinType.Round, 2.0, 16) if inset > 0.01 else outline
        solid = solid + ring.extrude(r / steps + 0.01).translate([0, 0, t - r + r * i / steps - 0.01])
    return solid


def _stroke(M, pts, w, tip):
    """One stroke of the piping bag as an outline: a ribbon `w` wide along the points, round or flat at its ends, or a row of dots."""
    import numpy as np
    C = M.CrossSection
    kept = []
    for x, y in pts:                                         # a hand gives a point every pixel: one every 0.3 mm draws the same line
        if not kept or math.hypot(x - kept[-1][0], y - kept[-1][1]) >= 0.3:
            kept.append((x, y))
    if not kept:
        return None
    if len(kept) == 1:
        return C.circle(w / 2 if tip != "dots" else w * 0.6, 24).translate(list(kept[0]))
    a = np.array(kept, dtype=np.float64)
    if tip == "dots":
        seg = np.linalg.norm(np.diff(a, axis=0), axis=1)
        run = np.concatenate([[0.0], np.cumsum(seg)])
        out = None
        for s in np.arange(0.0, run[-1] + 1e-6, max(1.7 * w, 1.0)):
            i = int(min(np.searchsorted(run, s, side="right") - 1, len(seg) - 1))
            p = a[i] + (a[i + 1] - a[i]) * ((s - run[i]) / max(seg[i], 1e-9))
            dot = C.circle(w / 2, 20).translate([float(p[0]), float(p[1])])
            out = dot if out is None else out + dot
        return out
    way = np.gradient(a, axis=0)
    way /= np.maximum(np.linalg.norm(way, axis=1, keepdims=True), 1e-9)
    side = np.stack([-way[:, 1], way[:, 0]], 1) * (w / 2)
    left, right = a + side, a - side
    ring = [tuple(p) for p in left]
    if tip == "round":                                       # half a circle round the end, from the left edge to the right one
        base = math.atan2(side[-1][1], side[-1][0])
        ring += [(a[-1][0] + w / 2 * math.cos(base - math.pi * k / 8), a[-1][1] + w / 2 * math.sin(base - math.pi * k / 8)) for k in range(1, 8)]
    ring += [tuple(p) for p in right[::-1]]
    if tip == "round":
        base = math.atan2(-side[0][1], -side[0][0])
        ring += [(a[0][0] + w / 2 * math.cos(base - math.pi * k / 8), a[0][1] + w / 2 * math.sin(base - math.pi * k / 8)) for k in range(1, 8)]
    return C([np.array(ring, dtype=np.float64)], M.FillRule.NonZero)


def _icing(M, p, unit, clip):
    """
    The strokes the visitor drew, one layer for each filament they were drawn in, in the order the filaments first
    appear (a colour used later lies higher). Points are in shares of the picture's width: the drawing keeps its place
    when the biscuit is made bigger.
    """
    groups = []
    for s in (p.get("strokes") or [])[:MAX_STROKES]:
        if not isinstance(s, dict):
            continue
        try:
            pts = [(float(x) * unit, float(y) * unit) for x, y in (s.get("p") or [])[:300]]
            w = min(4.0, max(1.5, float(s.get("w", 2.5))))
        except (TypeError, ValueError):
            continue
        shape = _stroke(M, pts, w, str(s.get("t", "round")))
        if shape is None or shape.is_empty():
            continue
        code = str(s.get("c") or "")
        for group in groups:
            if group[0] == code:
                group[2] = group[2] + shape
                break
        else:
            if len(groups) < MAX_ICINGS:
                groups.append([code, str(s.get("h") or "#f4f4f2"), shape])
    layers = []
    whole = max(clip.area(), 1e-9)
    for n, (code, hx, cs) in enumerate(groups):
        cs = (cs ^ clip).simplify(0.02)
        if cs.is_empty():
            continue
        layers.append({"index": 10 + n + 1, "part": "icing_%d" % (n + 1), "rgb": hx, "code": code, "hex": hx, "share": round(cs.area() / whole, 4), "own": cs, "stack": cs})
    return layers


# ── cake topper ──────────────────────────────────────────────────────────────

TEMPLATES = ("number", "heart", "star", "circle", "none")
STICK = 4.0                 # how wide a stick of the topper is: printed flat, it must not snap when pushed into a cake


def _topper(M, Invalid, p, width, lines, warn):
    """
    A number or a shape with a name written across it and one or two sticks under it: everything one flat piece, the
    name raised in a second colour. Returns (layers, info) like _gingerbread.
    """
    C, J = M.CrossSection, M.JoinType.Round
    template = p.get("template", TEMPLATES[0])
    if template not in TEMPLATES:
        raise Invalid("bad_choice", "template")
    name = lines[0] if lines else ""
    number = str(p.get("number") or "").strip()[:3]
    missing, back = [], None
    try:
        if template == "number" and number:
            back, binfo = S.text(M, [number], p.get("letter_font") or p.get("font"), 100)
            missing += binfo.get("missing_chars", [])
        elif template == "heart":
            back = C([[(16 * math.sin(t) ** 3, 13 * math.cos(t) - 5 * math.cos(2 * t) - 2 * math.cos(3 * t) - math.cos(4 * t)) for t in (2 * math.pi * (160 - i) / 160 for i in range(160))]])
        elif template == "star":
            back = C([[((10 if i % 2 == 0 else 5.2) * math.cos(math.pi / 2 + i * math.pi / 5), (10 if i % 2 == 0 else 5.2) * math.sin(math.pi / 2 + i * math.pi / 5)) for i in range(10)]])
        elif template == "circle":
            back = C.circle(10, 128)
        text = None
        if name:
            text, tinfo = S.text(M, [name], p.get("font"), 10)
            missing += [c for c in tinfo.get("missing_chars", []) if c not in missing]
    except S.ArtworkError as e:
        raise Invalid(e.code)
    if back is None and text is None:
        raise Invalid("no_text")
    art = C()
    if back is not None:
        back = S.fit(back, width_mm=width)
        if template == "star":
            back = back.offset(-1.5, J, 2.0, 24).offset(1.5, J, 2.0, 24)
        bw, bh = S.size(back)
        body = back
    if text is not None:
        tw0, th0 = S.size(text)
        tw = width * (_num(Invalid, p, "text_size", 90) / 100.0 if back is not None else 1.0)
        text = S.fit(text, width_mm=tw)
        th = th0 * tw / tw0
        if th < 5.0:
            warn.append("name_small")
        cx, cy = (bw / 2, bh / 2 + bh * _num(Invalid, p, "text_y", -10) / 100.0) if back is not None else (tw / 2, th / 2)
        text = text.translate([cx - tw / 2, cy - th / 2])
        # under the name lies a fattened copy of it: letters that reach beyond the shape still have something to stand on
        fat = text.offset(max(1.2, th * 0.06), J, 2.0, 16)
        body = (back + fat) if back is not None else fat
        art = text
    body, links = S.joined(M, body.simplify(0.02), 1.0, 2.4)
    if links:
        warn.append("pieces_tied")
    # sticks: from inside the piece down to a common tip line, where the piece has material above them
    x0, y0, x1, y1 = body.bounds()
    length, count = _num(Invalid, p, "spike", 60), int(_num(Invalid, p, "spikes", 2))
    bottom = y0 - length
    for share in ((0.5,) if count == 1 else (0.3, 0.7)):
        x = x0 + (x1 - x0) * share
        for _ in range(12):                                  # a place with nothing above it (the gap of a heart, a space in a name): move towards the middle
            above = body ^ C.square([STICK, y1 - y0 + 2]).translate([x - STICK / 2, y0 - 1])
            if above.area() > STICK * 2:
                break
            x += (x0 + x1 - 2 * x) * 0.15
        top = above.bounds()[1] + 3.0 if not above.is_empty() else y0 + 3.0
        body = body + C([[(x - STICK / 2, top), (x - STICK / 2, bottom + 6), (x, bottom), (x + STICK / 2, bottom + 6), (x + STICK / 2, top)]])
    pal = p.get("palette") or []
    by_code = dict((c, h) for c, h in pal)
    chosen = (p.get("part_colors") or {}).get("color_1")
    own = chosen.get("code") if isinstance(chosen, dict) else None
    light = max(pal, key=lambda f: _light(f[1])) if pal else ("", "#f4f4f2")
    dark = min(pal, key=lambda f: _light(f[1])) if pal else ("", "#1b1b1d")
    code = own if own in by_code else light[0]
    hx = by_code.get(code, light[1])
    bx0, by0, bx1, by1 = body.bounds()
    move = [-bx0, -by0]
    body, art = body.translate(move), (art.translate(move) if not art.is_empty() else art)
    layers = [] if art.is_empty() or back is None else [{"index": 1, "rgb": hx, "code": code, "hex": hx, "share": round(art.area() / max(body.area(), 1e-9), 4), "own": art, "stack": art}]
    info = {"source": "topper", "silhouette": body, "size": [bx1 - bx0, by1 - by0], "found": 1, "wanted": 1, "background": "alpha", "missing_chars": missing,
            "body": body, "dough": (dark[0], dark[1]), "top": None}
    return layers, info


BUILDERS = {product: (lambda M, Invalid, p, product=product: build(M, Invalid, p, product)) for product in PRODUCTS}
