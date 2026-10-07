#!/usr/bin/env python3
"""
Filament art: a picture in the colours of the farm's filaments, as a thing to hang on the wall (manifold3d, exact mm).

    art_tool.py <out.stl> <params-json | @file> [parts] [<parts-dir>]

The picture is read by shape2d.colors (the same way the pendants and coasters of shape_kinds.py read theirs): the
background goes, the colours are found, each one gets the nearest spool. Two ways to turn that into plastic:

  stack     ONE PRINT. A base plate (`base` mm) carries the colours as steps of `step` mm (two layers of 0.2 each),
            every colour one step above the one under it: the print changes filament by height, so any printer does it
            with a swap, the farm with a tool change (notes.color_changes). Parts: body, color_<n>.
  layered   A PICTURE OF PLATES. Every colour is a plate of its own (`plate` mm thick) holding its own area and all the
            colours in front of it, so the plates stack like a paper cut: the back plate is the whole silhouette, the
            front one only its colour (a black outline, say). Spacers (`gap` mm, posts of 6 mm on the plate behind) lift
            each plate off the one behind it for depth and shadow, or the plates lie flat. Every plate prints on its own
            in its colour, no multi-material needed, and the page gets a guide: the order, back to front.
            Parts: plate_<n> (n = the colour's number), frame (a board with a rim and a hanging slot, optional).

view: "use" (assembled, as it hangs) or "print" (every piece laid flat on the bed, side by side: what is downloaded).

Always prints one JSON object: {"ok": true, "bbox": {...}, "volume_mm3", "area_mm2", "triangles", "notes": {...},
"parts": [{"name", "tris": [from, to), "bbox": [x0,y0,z0,x1,y1,z1]}]} or {"ok": false, "code": "...", "error": "..."}.
With a parts directory every piece is also written there as <name>.stl.
"""
import json
import math
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

LIMITS = {
    "width": (50, 250), "height": (50, 250), "base": (0.8, 3), "step": (0.4, 1.2), "plate": (1.5, 3), "gap": (0, 5), "colors_n": (1, 8),
    "bg_strength": (0, 100), "smooth": (0, 1), "contrast": (50, 150), "brightness": (50, 150), "saturation": (0, 200), "frame_w": (6, 20),
    "margin": (0, 20),
}
MODES = ("stack", "layered")
SHAPES = ("image", "rect", "circle")
FRAMES = ("none", "round", "square")
POST_D = 6.0                 # spacer posts
POST_MIN_WALL = 1.5          # a post needs this much plate round it
FRAME_T = 2.0                # the board of the frame
FRAME_RIM = 1.6              # the wall of the frame round the picture
SLOT_W, SLOT_H = 10.0, 4.0   # the hanging slot in the back of the frame's board (a nail or a screw head)
LED_ROOM = 10.0              # with an LED strip the plates stand this much further from the board
BED = 240.0                  # pieces are laid out in rows no wider than this
MIN_LINE = 0.8               # a line of a plate narrower than this is given to the plate behind it


class Invalid(Exception):
    """A reason the page can show: code + detail."""

    def __init__(self, code, detail=""):
        super().__init__(code + (": " + detail if detail else ""))
        self.code = code


def out(obj):
    print(json.dumps(obj))
    sys.exit(0)


def _num(p, key, default):
    lo, hi = LIMITS[key]
    try:
        v = float(p.get(key, default))
    except (TypeError, ValueError):
        raise Invalid("not_a_number", key)
    if math.isnan(v) or v < lo or v > hi:
        raise Invalid("out_of_range", "%s %s-%s" % (key, lo, hi))
    return v


def _code(p, part):
    c = (p.get("part_colors") or {}).get(part)
    if isinstance(c, dict) and c.get("code"):
        return str(c["code"]), str(c.get("hex") or "")
    return None


def _light(S, hx):
    lab = S.hex_lab(hx)
    return float(lab[0]) if lab is not None else 50.0


def _spots(M, cs, radius, count, keep_off=None):
    """
    Up to `count` points inside an outline where a disc of `radius` has room, as far from each other as they can be:
    the places of the spacer posts. `keep_off`: points (x, y, r) nothing may come near.
    """
    import numpy as np
    from PIL import Image, ImageDraw
    from scipy import ndimage
    x0, y0, x1, y1 = cs.bounds()
    cell = max(0.3, max(x1 - x0, y1 - y0) / 300)
    size = (int((x1 - x0) / cell) + 3, int((y1 - y0) / cell) + 3)
    img = Image.new("1", size, 0)
    draw = ImageDraw.Draw(img)
    for poly in cs.to_polygons():
        pts = [((float(x) - x0) / cell + 1, (float(y) - y0) / cell + 1) for x, y in poly]
        area = sum(pts[i][0] * pts[(i + 1) % len(pts)][1] - pts[(i + 1) % len(pts)][0] * pts[i][1] for i in range(len(pts)))
        draw.polygon(pts, fill=1 if area > 0 else 0)
    inside = ndimage.distance_transform_edt(np.asarray(img, dtype=bool)) * cell
    room = inside >= radius
    for kx, ky, kr in keep_off or []:
        yy, xx = np.ogrid[:size[1], :size[0]]
        room &= ((xx - 1) * cell + x0 - kx) ** 2 + ((yy - 1) * cell + y0 - ky) ** 2 > (kr + radius) ** 2
    found = []
    far = np.full(inside.shape, np.inf)
    yy, xx = np.ogrid[:size[1], :size[0]]
    for _ in range(count):
        score = np.where(room, np.minimum(far, inside * 3.0), -1.0)      # far from the others, and with room to spare
        iy, ix = np.unravel_index(int(np.argmax(score)), score.shape)
        if score[iy, ix] <= 0:
            break
        px, py = x0 + (ix - 1) * cell, y0 + (iy - 1) * cell
        found.append((round(float(px), 2), round(float(py), 2)))
        far = np.minimum(far, np.sqrt(((xx - ix) * cell) ** 2 + ((yy - iy) * cell) ** 2))
        room &= far > 4 * radius
    return found


def _opened(M, cs, r):
    """The outline without its parts narrower than 2r (a hair of a plate breaks off, a line a nozzle cannot draw)."""
    J = M.JoinType.Round
    return cs.offset(-r, J, 2.0, 16).offset(r, J, 2.0, 16)


def _polys(cs, digits=1):
    """The outline as SVG path data, for the guide on the page."""
    parts = []
    for poly in cs.to_polygons():
        pts = ["%s %s" % (round(float(x), digits), round(float(-y), digits)) for x, y in poly]
        if len(pts) >= 3:
            parts.append("M" + "L".join(pts) + "Z")
    return "".join(parts)


def _rounded_square(M, side, r):
    import shape2d as S
    return S.rounded_rect(M, side, side, r).translate([-side / 2, -side / 2])


def _lay_out(pieces, gap=8.0):
    """Pieces side by side on the bed, rows no wider than BED, each standing on z = 0. Returns [(name, solid)]."""
    laid, x, y, row_h = [], 0.0, 0.0, 0.0
    for name, solid in pieces:
        x0, y0, z0, x1, y1, z1 = solid.bounding_box()
        w, d = x1 - x0, y1 - y0
        if x > 0 and x + w > BED:
            x, y, row_h = 0.0, y + row_h + gap, 0.0
        laid.append((name, solid.translate([x - x0, y - y0, -z0])))
        x += w + gap
        row_h = max(row_h, d)
    return laid


def build(M, p):
    import numpy as np
    import shape2d as S
    C, J = M.CrossSection, M.JoinType.Round
    n = lambda key, d: _num(p, key, d)       # noqa: E731
    mode = p.get("mode", "layered")
    if mode not in MODES:
        raise Invalid("bad_choice", "mode")
    shape = p.get("shape", "image")
    if shape not in SHAPES:
        raise Invalid("bad_choice", "shape")
    frame = p.get("frame", "none")
    if frame not in FRAMES:
        raise Invalid("bad_choice", "frame")
    view = p.get("view", "use")
    width, height = n("width", 150), n("height", 150)
    margin = n("margin", 0) if shape != "image" else 0.0
    base, step, plate_t, gap = n("base", 1.2), n("step", 0.4), n("plate", 2.0), n("gap", 3.0)
    frame_w = n("frame_w", 10)
    led = bool(p.get("led", False)) and frame != "none"
    flat = bool(p.get("flat", False))
    if flat:
        gap = 0.0
    warn = []
    art_path = p.get("artwork_path")
    if not art_path:
        raise Invalid("no_text")

    # ── the picture ─────────────────────────────────────────────────────────────────────────────────────────────
    options = {
        "n": int(n("colors_n", 5)), "background": "auto" if p.get("remove_bg", True) else "keep", "background_strength": n("bg_strength", 30), "smooth": n("smooth", 0.3),
        "contrast": n("contrast", 100) / 100, "brightness": n("brightness", 100) / 100, "saturation": n("saturation", 100) / 100,
        "palette": p.get("palette") or [], "merge": p.get("merge") or [], "order": p.get("order") or [],
        "assign": {part.split("_", 1)[1]: c["code"] for part, c in (p.get("part_colors") or {}).items() if (part.startswith("color_") or part.startswith("plate_")) and isinstance(c, dict) and c.get("code")},
    }
    # the frame's window: a round frame holds a disc, a square one a square; the picture is fitted into it
    window = width
    if frame == "round":
        shape = "circle"
        window = min(width, height)
        width = height = window
    elif frame == "square":
        shape = "rect" if shape == "image" else shape
        window = min(width, height) if shape == "circle" else None
    inner_w, inner_h = width - 2 * margin, height - 2 * margin
    if shape == "circle":
        d = (window or min(width, height)) - 2 * margin
        inner_w = inner_h = d
    if min(inner_w, inner_h) < 20:
        raise Invalid("shape_too_small")
    try:
        if shape == "image":
            layers, info = S.colors(M, art_path, inner_w, dict(options, fit=("box", inner_w, inner_h)))
        else:
            probe = dict(options, fit=("circle", 100.0), n=1)
            kept = S.colors(M, art_path, 100.0, probe)[1]["background"] == "kept"
            # a whole photo fills the body to its edge, a cut-out motif sits inside it with a little air
            if shape == "circle":
                fit = ("cover", inner_w, inner_h) if kept else ("circle", inner_w - 4.0)
            else:
                fit = ("cover", inner_w, inner_h) if kept else ("box", inner_w - 4.0, inner_h - 4.0)
            layers, info = S.colors(M, art_path, inner_w, dict(options, fit=fit))
    except S.ArtworkError as e:
        raise Invalid(e.code, str(e).split(": ", 1)[1] if ": " in str(e) else "")
    aw, ah = info["size"]
    if max(aw, ah) > 400:
        raise Invalid("artwork_too_tall")
    if info.get("ignored_outlines"):
        warn.append("outlines_ignored")

    # ── the body the picture lies on: its own outline, a rectangle or a disc ────────────────────────────────────
    if shape == "circle":
        body2d = C.circle(inner_w / 2 + margin, 180).translate([aw / 2, ah / 2])
        clip = C.circle(inner_w / 2, 180).translate([aw / 2, ah / 2])
    elif shape == "rect":
        # the plate is as big as asked, the picture sits in its middle
        r = min(6.0, min(width, height) / 12)
        body2d = S.rounded_rect(M, width, height, r).translate([aw / 2 - width / 2, ah / 2 - height / 2])
        clip = S.rounded_rect(M, inner_w, inner_h, max(0.5, r - margin)).translate([aw / 2 - inner_w / 2, ah / 2 - inner_h / 2])
    else:
        body2d, clip = info["silhouette"], None
    if clip is not None:
        for layer in layers:
            layer["own"], layer["stack"] = layer["own"] ^ clip, layer["stack"] ^ clip
        layers = [layer for layer in layers if layer["own"].area() >= S.MIN_ISLAND_MM2]
        if not layers:
            raise Invalid("empty_result")
    if shape == "image":
        # a silhouette of many pieces (a word, a flock of birds) must hold together: the back plate ties them
        body2d, links = S.joined(M, body2d, 0.6, max(2.0, min(aw, ah) * 0.06))
        if links:
            warn.append("pieces_tied")

    # ── lines too thin to print as plates: given to the plate behind (layered) ─────────────────────────────────
    merged_mm2 = 0.0
    if mode == "layered":
        count = len(layers)
        stacks = []
        above = None
        for i in range(count - 1, -1, -1):
            own = _opened(M, layers[i]["own"], MIN_LINE / 2) if i > 0 else layers[i]["own"]
            merged_mm2 += max(0.0, layers[i]["own"].area() - own.area())
            stack = own if above is None else own + above
            layers[i]["own"], layers[i]["stack"] = own, stack
            above = stack
            stacks.append(stack)
        for i in range(count - 1):
            layers[i]["own"] = layers[i]["stack"] - layers[i + 1]["stack"]
        layers[0]["stack"] = body2d if shape != "image" else layers[0]["stack"] + body2d
        layers[0]["own"] = layers[0]["stack"] - (layers[1]["stack"] if count > 1 else C())
        if merged_mm2 > 2.0:
            warn.append("thin_merged")
    count = len(layers)

    # ── colours of the parts ────────────────────────────────────────────────────────────────────────────────────
    spools = p.get("palette") or []
    parts, pieces, paint, listed = {}, [], {}, []
    notes = {"mode": mode, "shape": shape, "frame": frame, "warnings": warn, "found": info["found"], "wanted": info["wanted"], "background": info["background"], "source": info["source"]}
    x0, y0, x1, y1 = body2d.bounds()
    span_w, span_h = x1 - x0, y1 - y0

    def laid(solid):
        return solid.translate([-x0, -y0, 0])

    bands = []
    if mode == "stack":
        own = _code(p, "body")
        body_color = own or (layers[0]["code"], layers[0]["hex"])
        if not own and shape != "image" and info["background"] != "kept" and spools:
            motif = [S.hex_lab(layer["hex"]) for layer in layers]
            pick = max(spools, key=lambda f: min(float(((S.hex_lab(f[1]) - m) ** 2).sum()) for m in motif))
            body_color = (pick[0], pick[1])
        body = body2d.extrude(base)
        parts["body"] = laid(body)
        pieces.append(("body", parts["body"]))
        paint["body"] = body_color[1]
        fused = body
        top = base
        bands.append((0.0, "body", body_color))
        for i, layer in enumerate(layers):
            solid = layer["stack"].extrude(step).translate([0, 0, base + i * step])
            if solid.is_empty():
                continue
            name = "color_%d" % layer["index"]
            parts[name] = laid(solid)
            pieces.append((name, parts[name]))
            paint[name] = layer["hex"]
            listed.append({"part": name, "index": layer["index"], "rgb": layer["rgb"], "code": layer["code"], "hex": layer["hex"], "share": layer["share"], "area_mm2": round(layer["own"].area(), 1)})
            fused = fused + layer["stack"].extrude(step + 0.01).translate([0, 0, base + i * step - 0.01])
            top = base + (i + 1) * step
            bands.append((round(base + i * step, 2), name, (layer["code"], layer["hex"])))
        parts["all"] = laid(fused)
        notes["body_color"] = {"code": body_color[0], "hex": body_color[1]}
        changes, last = [], body_color[0]
        for z, part, held in bands[1:]:
            if held[0] != last:
                changes.append({"z": z, "part": part, "code": held[0], "hex": held[1]})
                last = held[0]
        filaments = []
        for _, _, held in bands:
            if held[0] not in filaments:
                filaments.append(held[0])
        notes.update({"color_changes": changes, "filaments": len(filaments), "multi_material": False, "outer": [round(span_w, 1), round(span_h, 1), round(top, 1)], "each": [[round(span_w, 1), round(span_h, 1), round(top, 1)]]})
        if len(changes) == 1:
            notes["color_change_mm"] = changes[0]["z"]
        thin = max((S.printability(M, layer["own"], 0.45)["thin_pct"] for layer in layers if not layer["own"].is_empty()), default=0)
        notes["thin_pct"] = thin
        if thin > 35:
            warn.append("thin_lines")
    else:
        # ── plates back to front, posts on the plate behind under the plate in front ──────────────────────────────
        depth = 0.0
        plates = []
        guide = []
        for i, layer in enumerate(layers):
            name = "plate_%d" % layer["index"]
            area2d = layer["stack"]
            if area2d.is_empty():
                continue
            own = _code(p, name)
            color = own or (layer["code"], layer["hex"])
            z = depth
            solid = area2d.extrude(plate_t).translate([0, 0, z])
            posts = []
            post_d = POST_D
            lifted = gap > 0 and i + 1 < count
            if lifted:
                ahead = layers[i + 1]["stack"]
                # posts stand where the plate in front has room for them, up to four, far apart; a small plate gets
                # thinner posts, and one with no room for any lies flat on this one (glued)
                want = 4 if ahead.area() > 2500 else 3 if ahead.area() > 600 else 2 if ahead.area() > 150 else 1
                for post_d, wall in ((POST_D, POST_MIN_WALL), (4.0, 1.0), (3.0, 0.8)):
                    spots = _spots(M, ahead, post_d / 2 + wall, want)
                    if spots:
                        break
                for px, py in spots:
                    solid = solid + M.Manifold.cylinder(gap + 0.01, post_d / 2, post_d / 2, 32).translate([px, py, z + plate_t - 0.01])
                    posts.append([round(px - x0, 1), round(py - y0, 1)])
                if not posts:
                    lifted = False
                    if "small_plate_flat" not in warn:
                        warn.append("small_plate_flat")
            plates.append((name, solid, color, layer))
            guide.append({"part": name, "index": layer["index"], "code": color[0], "hex": color[1], "rgb": layer["rgb"], "share": layer["share"], "area_mm2": round(layer["own"].area(), 1),
                          "z": round(z, 2), "posts": posts, "post_d": post_d if posts else 0, "svg": _polys(layer["stack"].translate([-x0, -y0]).simplify(0.15)), "own_svg": _polys(layer["own"].translate([-x0, -y0]).simplify(0.15))})
            depth = z + plate_t + (gap if lifted else 0.0)
        if not plates:
            raise Invalid("empty_result")
        stack_depth = depth
        # ── the frame: a board with a rim round the picture and a slot to hang it by ──────────────────────────────
        frame_solid = None
        frame_color = None
        if frame != "none":
            own = _code(p, "frame")
            if own:
                frame_color = own
            elif spools:
                dark = min(spools, key=lambda f: _light(S, f[1]))
                frame_color = (dark[0], dark[1])
            else:
                frame_color = ("", "#17171A")
            room = stack_depth + (LED_ROOM if led else 0.0) + 1.0
            cx, cy = x0 + span_w / 2, y0 + span_h / 2
            if frame == "round":
                outer2d = C.circle(span_w / 2 + frame_w, 240).translate([cx, cy])
                inner2d = C.circle(span_w / 2 + 0.6, 240).translate([cx, cy])
            else:
                side = max(span_w, span_h)
                outer2d = _rounded_square(M, side + 2 * frame_w, min(8.0, frame_w)).translate([cx, cy])
                inner2d = S.rounded_rect(M, span_w + 1.2, span_h + 1.2, 1.0).translate([x0 - 0.6, y0 - 0.6])
            board = outer2d.extrude(FRAME_T)
            rim = (outer2d - inner2d).extrude(room).translate([0, 0, FRAME_T])
            frame_solid = board + rim
            # a keyhole slot in the back of the board, above the middle: a nail head slides in from below
            sy = cy + span_h / 2 + frame_w * 0.45 if frame == "square" else cy + span_w / 2 + frame_w * 0.45
            slot = S.rounded_rect(M, SLOT_W, SLOT_H, SLOT_H / 2 - 0.01).translate([cx - SLOT_W / 2, sy - SLOT_H / 2]).extrude(1.2).translate([0, 0, -0.01])
            frame_solid = frame_solid - slot
            if led:
                # a way out for the strip's cable through the rim at the bottom
                hole = M.Manifold.cube([8.0, frame_w + 4.0, 5.0]).translate([cx - 4.0, cy - (span_h if frame == "square" else span_w) / 2 - frame_w - 2.0, FRAME_T])
                frame_solid = frame_solid - hole
            notes["frame_outer"] = [round(v, 1) for v in ((span_w + 2 * frame_w, span_h + 2 * frame_w) if frame == "round" else (max(span_w, span_h) + 2 * frame_w,) * 2)]
            notes["frame_depth"] = round(FRAME_T + room, 1)
        # ── assembled (use) or laid flat on the bed (print) ─────────────────────────────────────────────────────
        lift = FRAME_T + (LED_ROOM if led else 0.0) if frame_solid is not None else 0.0
        if view == "use":
            all_pieces = []
            if frame_solid is not None:
                all_pieces.append(("frame", laid(frame_solid)))
            for name, solid, color, layer in plates:
                all_pieces.append((name, laid(solid.translate([0, 0, lift]))))
        else:
            flat_pieces = [(name, solid) for name, solid, _, _ in plates]
            if frame_solid is not None:
                flat_pieces.append(("frame", frame_solid))
            all_pieces = _lay_out(flat_pieces)
        fused = None
        for name, solid in all_pieces:
            parts[name] = solid
            pieces.append((name, solid))
            fused = solid if fused is None else fused + solid
        parts["all"] = fused
        for name, solid, color, layer in plates:
            paint[name] = color[1]
            listed.append({"part": name, "index": layer["index"], "rgb": layer["rgb"], "code": color[0], "hex": color[1], "share": layer["share"], "area_mm2": round(layer["own"].area(), 1)})
        if frame_solid is not None:
            paint["frame"] = frame_color[1]
            notes["frame_color"] = {"code": frame_color[0], "hex": frame_color[1]}
        each = []
        for name, solid in pieces:
            bx0, by0, bz0, bx1, by1, bz1 = solid.bounding_box()
            each.append([round(bx1 - bx0, 1), round(by1 - by0, 1), round(bz1 - bz0, 1)])
        notes.update({"guide": guide, "plates": len(plates), "depth": round(stack_depth, 1), "filaments": len({c[0] for _, _, c, _ in plates} | ({frame_color[0]} if frame_color else set())),
                      "multi_material": False, "color_changes": [], "outer": [round(span_w, 1), round(span_h, 1), round(stack_depth + lift, 1)], "each": each, "merged_mm2": round(merged_mm2, 1)})
    parts["_pieces"] = {"all": pieces}
    notes.update({"colors": listed, "paint": paint, "parts": [name for name, _ in pieces], "picture": [round(aw, 1), round(ah, 1)]})
    return parts, notes


def write_stl(path, tri):
    import numpy as np
    normals = np.cross(tri[:, 1] - tri[:, 0], tri[:, 2] - tri[:, 0])
    lens = np.linalg.norm(normals, axis=1, keepdims=True)
    normals = np.divide(normals, lens, out=np.zeros_like(normals), where=lens > 0)
    rec = np.zeros(len(tri), dtype=[("n", "<f4", 3), ("v", "<f4", (3, 3)), ("a", "<u2")])
    rec["n"], rec["v"] = normals, tri
    with open(path, "wb") as fh:
        fh.write(b"matplace filament art".ljust(80, b" "))
        fh.write(np.uint32(len(tri)).tobytes())
        fh.write(rec.tobytes())


def main(argv):
    if len(argv) < 3:
        out({"ok": False, "error": "usage", "code": "usage"})
    dst = argv[1]
    with_parts = len(argv) > 3 and argv[3] == "parts"
    parts_dir = argv[4] if len(argv) > 4 else None
    try:
        import manifold3d as M
        import numpy as np
        raw = argv[2] or "{}"
        if raw.startswith("@"):
            with open(raw[1:], encoding="utf-8") as fh:
                raw = fh.read()
        p = json.loads(raw)
        if not isinstance(p, dict):
            raise Invalid("unknown_kind")
        parts, notes = build(M, p)
        named = parts.pop("_pieces")
        solid = parts["all"]
        if solid.is_empty() or solid.status() != M.Error.NoError or solid.volume() <= 0:
            raise Invalid("empty_result")
        x0, y0, z0, x1, y1, z1 = solid.bounding_box()
        solid = solid.translate([-x0, -y0, -z0])
        listed, chunks, at = [], [], 0
        if parts_dir:
            os.makedirs(parts_dir, exist_ok=True)
        for name, piece in [(nm, pc.translate([-x0, -y0, -z0])) for nm, pc in named["all"]]:
            m = piece.to_mesh()
            t = np.asarray(m.vert_properties, dtype=np.float32)[:, :3][np.asarray(m.tri_verts, dtype=np.int64)]
            if not len(t):
                continue
            lo, hi = t.reshape(-1, 3).min(axis=0), t.reshape(-1, 3).max(axis=0)
            listed.append({"name": name, "tris": [at, at + len(t)], "bbox": [round(float(c), 2) for c in (*lo, *hi)]})
            chunks.append(t)
            at += len(t)
            if parts_dir:
                # a piece of its own stands on the bed where it is: the slicer puts it in the middle
                write_stl(os.path.join(parts_dir, name + ".stl"), t - np.array([lo[0], lo[1], lo[2]], dtype=np.float32))
        tri = np.concatenate(chunks) if chunks else np.zeros((0, 3, 3), dtype=np.float32)
        write_stl(dst, tri)
        out({"ok": True, "kind": "filament_art", "part": "all", "bbox": {"x": round(x1 - x0, 2), "y": round(y1 - y0, 2), "z": round(z1 - z0, 2)},
             "volume_mm3": round(solid.volume(), 1), "area_mm2": round(solid.surface_area(), 1), "triangles": int(len(tri)), "notes": notes, **({"parts": listed} if with_parts else {})})
    except Invalid as e:
        out({"ok": False, "code": e.code, "error": str(e)})
    except SystemExit:
        raise
    except Exception as e:  # noqa: BLE001
        out({"ok": False, "code": "failed", "error": str(e)})


if __name__ == "__main__":
    main(sys.argv)
