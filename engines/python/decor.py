"""
Decorations laid on a face of a product, for param_tool.py: the one place where a text, a relief from a photo or a logo
is made and joined with the shape, whatever the shape is (docs/AQ.md).

A decoration is one entry of the list `decor` in the product's parameters:

    kind    text | photo | logo
    face    front | back
    x, y    where its middle lies, in % of the face's width from the face's middle (right and up are positive, as the
            face is looked at)
    size    its width in % of the face's width
    turn    degrees, counter-clockwise as the face is looked at
    mode    raised (stands out of the face) | engraved (sunk into it) | flush (inlaid level with it in its own filament)
    depth   how far it stands out or goes in, mm
    color   {code, hex} of its own filament, or nothing: the colour of the plate
    text:   lines (1 to 3), font (the file), typeface (its key)
    photo:  image_path, frame (circle | rect), contrast (%), smooth (0 to 1), invert
    logo:   image_path, invert

The builder of the product says what a face is (`_face` in its parts): the plate's outline as it is printed, its
thickness, the middle and the width decorations are measured from, the part that is the plate, and whether the piece
is printed with its front on the bed.

What a printer can do decides the rest:
  - the face that lies on the bed is seen from below: what is on it is mirrored, and nothing can stand out of it. A
    text or a logo asked to be raised there is sunk instead (or inlaid, when it has a filament of its own), and sunk
    no deeper than two layers: the slicer bridges those, a third one made it ask for supports (control slices, AQ.md);
  - a relief from a photo needs its face up. On the front it turns a piece that would print face down face up (the
    builder asks needs_front_up() first); on the back of a piece printed face up it cannot be made;
  - a decoration in a filament of its own is a part `decor_<n>`; the others are a part of the plate.
"""
import math

import shape2d as S

KINDS = ("text", "photo", "logo")
FACES = ("front", "back")
MODES = ("raised", "engraved", "flush")
FRAMES = ("circle", "rect")
MAX_ITEMS = 6
LAYER = 0.2
DEPTH = {"text": (0.4, 1.2), "logo": (0.4, 1.2), "photo": (0.6, 3.0)}
MIN_CAP = 3.0               # the smallest capital letter a 0.4 mm nozzle still writes legibly (raised or inlaid)
MIN_CAP_SUNK = 3.5          # a sunk letter closes sooner: its strokes are bridged or filled by the neighbouring lines
MARGIN = 0.8                # decorations keep this far from the edge of the face
BED_SUNK = 0.4              # on the face that lies on the bed nothing is sunk deeper: two layers are bridged, three call for supports
RELIEF_FLOOR = 0.2          # the lowest point of a relief: one layer, so the picture has an edge and no hole
RELIEF_GRID = 300           # a relief is read on at most this many cells across


def needs_front_up(p):
    """A relief from a photo on the front: the piece cannot be printed with its front on the bed."""
    return any(isinstance(i, dict) and i.get("kind") == "photo" and i.get("face", "front") == "front" for i in (p.get("decor") or []))


def wanted(p):
    return any(isinstance(i, dict) for i in (p.get("decor") or []))


def _num(Invalid, item, key, default, lo, hi, n):
    try:
        v = float(item.get(key, default))
    except (TypeError, ValueError):
        raise Invalid("not_a_number", "decor.%d.%s" % (n, key))
    if math.isnan(v) or v < lo - 1e-9 or v > hi + 1e-9:
        raise Invalid("out_of_range", "decor.%d.%s %s-%s" % (n, key, lo, hi))
    return v


def _centred(cs):
    x0, y0, x1, y1 = cs.bounds()
    return cs.translate([-(x0 + x1) / 2, -(y0 + y1) / 2])


def _relief(M, Invalid, item, n, w, h, depth, mirrored, outline):
    """A photo as a height field w × h mm, light is high, its middle on the origin; cut to the outline of its frame."""
    import numpy as np
    from PIL import Image, ImageOps
    from scipy import ndimage
    from relief_tool import heightmap, plate_mesh
    try:
        img = ImageOps.exif_transpose(Image.open(item.get("image_path")))
        if img.mode in ("RGBA", "LA", "P"):
            img = img.convert("RGBA")
            back = Image.new("RGBA", img.size, (255, 255, 255, 255))
            img = Image.alpha_composite(back, img)
        img = img.convert("L")
    except Exception:  # noqa: BLE001
        raise Invalid("artwork_unreadable")
    pitch = max(0.2, w / RELIEF_GRID)
    cols, rows = max(8, int(round(w / pitch))) + 1, max(8, int(round(h / pitch))) + 1
    # the photo covers its frame: cut to the frame's proportions round its middle
    iw, ih = img.size
    want = (cols - 1) / float(rows - 1)
    if iw / float(ih) > want:
        cut = int(round(ih * want))
        img = img.crop(((iw - cut) // 2, 0, (iw - cut) // 2 + cut, ih))
    else:
        cut = int(round(iw / want))
        img = img.crop((0, (ih - cut) // 2, iw, (ih - cut) // 2 + cut))
    contrast = _num(Invalid, item, "contrast", 100, 50, 150, n)
    level = heightmap(img, cols, rows, "relief", bool(item.get("invert", False)), 0.0, (contrast / 100.0 - 1.0) * 50.0, 1.0)
    # what a nozzle of 0.4 mm cannot draw only makes noise on the surface: detail under about half a millimetre goes,
    # more of it with the slider
    sigma = (0.25 + 0.9 * _num(Invalid, item, "smooth", 0.35, 0, 1, n)) / pitch
    level = ndimage.gaussian_filter(level, sigma)
    lo, hi = float(level.min()), float(level.max())
    level = (level - lo) / (hi - lo) if hi - lo > 1e-6 else np.zeros_like(level)
    if mirrored:
        level = level[:, ::-1]
    z = (RELIEF_FLOOR + level * (depth - RELIEF_FLOOR)).astype(np.float32)
    verts, faces = plate_mesh(z, pitch, False)
    solid = M.Manifold(M.Mesh(vert_properties=np.asarray(verts, dtype=np.float32), tri_verts=np.asarray(faces, dtype=np.uint32)))
    if solid.status() != M.Error.NoError or solid.volume() <= 0:
        raise Invalid("empty_result")
    solid = solid.translate([-(cols - 1) * pitch / 2, -(rows - 1) * pitch / 2, 0])
    return solid ^ outline.extrude(depth + 1)


def _zspan(solid):
    box = solid.bounding_box()
    return round(box[2], 2), round(box[5], 2)


def apply(M, Invalid, p, parts, notes, face):
    """Lays the decorations of `p` on the parts a builder made; changes parts and notes in place."""
    C, J = M.CrossSection, M.JoinType.Round
    items = [i for i in (p.get("decor") or []) if isinstance(i, dict)]
    if not items:
        return
    if face is None:
        raise Invalid("decor_unsupported")
    if len(items) > MAX_ITEMS:
        raise Invalid("decor_too_many")
    plate, t, plate_part = face["plate"], float(face["t"]), face["part"]
    cx, cy, W = face["centre"][0], face["centre"][1], float(face["width"])
    face_down = bool(face.get("face_down"))
    region = plate.offset(-float(face.get("inset", MARGIN)), J, 2.0, 24)
    if region.is_empty():
        raise Invalid("shape_too_small")
    named = parts.get("_pieces", {}).get("all")
    order = [name for name, _ in named] if named else [k for k in parts if not k.startswith("_") and k not in ("all", "use")]
    solids = {name: parts[name] for name in order}
    whole = parts["all"]
    warn = notes.setdefault("warnings", [])
    plate_code = face.get("code") or ""
    placed, colored = [], []
    top = max(parts["all"].bounding_box()[5], t)

    def note(code):
        if code not in warn:
            warn.append(code)

    for n, item in enumerate(items, 1):
        kind, side, mode = item.get("kind"), item.get("face", "front"), item.get("mode", "raised")
        if kind not in KINDS:
            raise Invalid("bad_choice", "decor.%d.kind" % n)
        if side not in FACES:
            raise Invalid("bad_choice", "decor.%d.face" % n)
        if mode not in MODES:
            raise Invalid("bad_choice", "decor.%d.mode" % n)
        lo, hi = DEPTH[kind]
        depth = _num(Invalid, item, "depth", 1.2 if kind == "photo" else 0.6, lo, hi, n)
        depth = max(LAYER, round(depth / LAYER) * LAYER)              # whole layers: a decoration ends where a layer ends
        x, y = _num(Invalid, item, "x", 0, -50, 50, n) * W / 100, _num(Invalid, item, "y", 0, -50, 50, n) * W / 100
        width = _num(Invalid, item, "size", 60, 10, 100, n) * W / 100
        turn = _num(Invalid, item, "turn", 0, -180, 180, n)
        on_bed = (side == "front") == face_down
        color = item.get("color") if isinstance(item.get("color"), dict) and item["color"].get("code") else None
        if color and str(color["code"]) == plate_code:
            color = None                                              # the plate's own filament: no part of its own
        if kind == "photo":
            if on_bed:
                raise Invalid("decor_photo_bed")
            color = None                                              # a relief is one filament: the plate's
            if mode == "flush":
                mode = "raised"
        elif on_bed and mode == "raised":
            mode = "flush" if color else "engraved"                    # nothing stands out of the face on the bed
            note("decor_bed_face")
        if mode == "flush" and not color:
            mode = "engraved"                                         # inlaid in the plate's own filament shows nothing
        if mode == "engraved":
            color = None
            if on_bed:
                depth = min(depth, BED_SUNK)
        if on_bed:
            x, turn = -x, -turn
        entry = {"index": n, "kind": kind, "face": side, "mode": mode, "depth": round(depth, 2), "on_bed": on_bed}

        # ── the outline of the decoration, where it lies in the print ─────────────────────────────────────────
        try:
            if kind == "text":
                lines = [str(s).strip() for s in (item.get("lines") or []) if str(s).strip()][:3]
                if not lines:
                    raise Invalid("decor_no_text")
                art, tinfo = S.text(M, lines, item.get("font"), 10)
                w0 = S.size(art)[0]
                k = width / w0
                cap = 10 * k
                entry["cap"] = round(cap, 1)
                if cap < (MIN_CAP_SUNK if mode == "engraved" else MIN_CAP):
                    note("decor_text_small")
                if tinfo.get("missing_chars"):
                    note("missing_chars")
                    notes["missing_chars"] = sorted(set(list(notes.get("missing_chars") or []) + list(tinfo["missing_chars"])))
                art = S.fit(art, width_mm=width)
            elif kind == "logo":
                if not item.get("image_path"):
                    raise Invalid("decor_no_image")
                art = S.load(M, {"artwork_path": item.get("image_path"), "invert": bool(item.get("invert", False))}, width)[0]
                art = S.fit(art, width_mm=width)
            else:
                if not item.get("image_path"):
                    raise Invalid("decor_no_image")
                frame = item.get("frame", "circle")
                if frame not in FRAMES:
                    raise Invalid("bad_choice", "decor.%d.frame" % n)
                art = C.circle(width / 2, 128) if frame == "circle" else S.rounded_rect(M, width, width * 0.75, min(3.0, width / 12))
        except S.ArtworkError as e:
            raise Invalid(e.code, str(e).split(": ", 1)[1] if ": " in str(e) else "")
        art = _centred(art)
        flat = art                                                    # as it is drawn, before it is turned and laid in its place
        aw, ah = S.size(art)
        art = art.rotate(turn)
        if on_bed:
            art = art.scale([-1.0, 1.0])
        art = art.translate([cx + x, cy + y])
        inside = art ^ region
        if inside.is_empty():
            raise Invalid("decor_off_face", str(n))
        if inside.area() < art.area() * 0.98:
            note("decor_clipped")
        if kind != "photo" and S.printability(M, inside, 0.45)["thin_pct"] > 35:
            note("decor_thin")
        bx0, by0, bx1, by1 = inside.bounds()
        entry["box"] = [round(v, 1) for v in (bx0, by0, bx1, by1)]

        # ── its solid, and what it does to the parts ───────────────────────────────────────────────────────────
        if kind == "photo":
            sunk = mode == "engraved"
            relief = _relief(M, Invalid, item, n, aw, ah, depth, on_bed, flat)
            base = t - depth if sunk else t
            if sunk and t - depth < 3 * LAYER:
                raise Invalid("decor_too_deep", str(n))
            relief = relief.rotate([0, 0, turn]).translate([cx + x, cy + y, base]) ^ region.extrude(top + depth + 5)
            if sunk:
                pocket = inside.extrude(top + 5).translate([0, 0, base])
                solids = {name: s - pocket for name, s in solids.items()}
                whole = whole - pocket
            else:
                # the relief stands on the plate; what already stands there (a raised colour) keeps its place
                for name, s in solids.items():
                    if name != plate_part:
                        relief = relief - s
            solids[plate_part] = solids[plate_part] + relief
            whole = whole + relief
            entry["z"] = list(_zspan(relief))
        elif mode == "engraved":
            if t - depth < 2 * LAYER:
                raise Invalid("decor_too_deep", str(n))
            cut = inside.extrude(depth + 1).translate([0, 0, -1]) if on_bed else inside.extrude(top + 5).translate([0, 0, t - depth])
            solids = {name: s - cut for name, s in solids.items()}
            whole = whole - cut
            entry["z"] = [0.0, round(depth, 2)] if on_bed else [round(t - depth, 2), round(t, 2)]
        else:
            if mode == "flush":
                if t - depth < 2 * LAYER:
                    raise Invalid("decor_too_deep", str(n))
                solid = inside.extrude(depth).translate([0, 0, 0 if on_bed else t - depth])
                solids = {name: s - solid for name, s in solids.items()}
            else:
                solid = inside.extrude(depth).translate([0, 0, t])
                for name, s in solids.items():
                    if name != plate_part:
                        solid = solid - s
            if solid.is_empty():
                raise Invalid("decor_off_face", str(n))
            if color:
                name = "decor_%d" % n
                solids[name] = solid
                order.append(name)
                entry.update({"part": name, "code": str(color["code"]), "hex": str(color.get("hex") or "#888888")})
                colored.append(entry)
                whole = whole + solid.translate([0, 0, -0.01 if not on_bed and mode == "raised" else 0])
            else:
                solids[plate_part] = solids[plate_part] + solid
                whole = whole + solid
            entry["z"] = list(_zspan(solid))
        placed.append(entry)

    if whole.is_empty() or any(solids[name].is_empty() for name in order if not name.startswith("decor_")):
        raise Invalid("empty_result")
    order = [name for name in order if not solids[name].is_empty()]
    for name in list(parts):
        if not name.startswith("_") and name not in ("all", "use") and name not in order:
            del parts[name]
    for name in order:
        parts[name] = solids[name]
    parts["all"] = whole
    pieces = [(name, solids[name]) for name in order]
    parts.setdefault("_pieces", {})["all"] = pieces
    if "use" in parts:
        parts["use"] = whole.rotate([0, 180, 0])
        parts["_pieces"]["use"] = [(name, s.rotate([0, 180, 0])) for name, s in pieces]

    # ── notes: what was laid where, the parts and their filaments, how it prints ───────────────────────────────
    notes["decor"] = placed
    notes["parts"] = order
    paint = notes.setdefault("paint", {})
    for e in colored:
        paint[e["part"]] = e["hex"]
    high = round(whole.bounding_box()[5], 1)
    for key in ("outer", "each"):
        if isinstance(notes.get(key), list) and len(notes[key]) == 3:
            notes[key][2] = max(notes[key][2], high)
    # a decoration shares layers with whatever stands beside it: how the piece prints is read again from the parts as they
    # are now. Parts that share a layer print together in one plate, each where the whole has it
    if _filaments(notes, solids, order, colored, face):
        parts["_shared"] = True


def _filaments(notes, solids, order, colored, face):
    """Which filaments every band of layers holds now, and so how the piece prints; True when two share a band."""
    codes = {face["part"]: (face.get("code") or "", face.get("hex") or "")}
    if notes.get("rim_color"):
        codes["rim"] = (notes["rim_color"]["code"], notes["rim_color"]["hex"])
    for c in list(notes.get("colors") or []) + list(notes.get("layers") or []):
        codes[c["part"]] = (c.get("code") or "", c.get("hex") or "")
    for e in colored:
        codes[e["part"]] = (e["code"], e["hex"])
    spans = []
    for name in order:
        if name in codes:
            z0, z1 = _zspan(solids[name])
            spans.append((z0, z1, name))
    cuts = sorted({z for z0, z1, _ in spans for z in (z0, z1)})
    bands = []
    for a, b in zip(cuts, cuts[1:]):
        if b - a < 0.05:
            continue
        held = [name for z0, z1, name in spans if z0 < b - 0.02 and z1 > a + 0.02]
        if held:
            bands.append((a, held))
    multi = any(len({codes[name][0] for name in held}) > 1 for _, held in bands)
    changes = []
    if not multi and bands:
        last = codes[bands[0][1][0]][0]
        for z, held in bands[1:]:
            code, hx = codes[held[0]]
            if code != last:
                # the part that starts here is the one the change is for
                changes.append({"z": round(z, 2), "part": next((nm for nm in held if abs(_zspan(solids[nm])[0] - z) < 0.02), held[0]), "code": code, "hex": hx})
                last = code
    notes["filaments"] = len({codes[name][0] for _, held in bands for name in held})
    notes["multi_material"] = bool(multi)
    notes["one_plate_required"] = bool(multi)
    notes["color_changes"] = changes
    notes.pop("color_change_mm", None)
    if len(changes) == 1:
        notes["color_change_mm"] = changes[0]["z"]
    return bool(multi)
