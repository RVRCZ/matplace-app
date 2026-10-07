#!/usr/bin/env python3
"""
Editing a ready model (manifold3d, exact mm): the "I have a file" tools of matplace.

    edit_tool.py analyse <in.stl> <params-json | @file>
    edit_tool.py split   <in.stl> <out.stl> <params-json | @file> [<parts-dir>]
    edit_tool.py scale   <in.stl> <out.stl> <params-json | @file> [<parts-dir>]

The model is loaded (any unit guess as in mesh_tool.load), closed when it is not (mesh_tool.solidify: pymeshfix, union,
or a rebuild through a grid, the way the farm does it) and turned into an exact solid. Very heavy models are thinned
first (fast_simplification, when it is installed; above 2 M triangles without it the tool refuses).

split   Cuts the model with planes across X, Y and Z so that every piece fits the bed, with as few cuts as it takes.
        params: {"bed": [x, y, z] usable mm, "planes": {"x": [mm…], "y": [...], "z": [...]} (the visitor's own planes,
        else automatic), "joint": "none" | "pins" | "dovetail", "numbers": true, "lay": true, "font": path}
        pins      Ø 6 × 12 mm dowels (part `pins`, standing on the bed) in holes with 0.2 mm of play, on both faces
        dovetail  a loose double-dovetail key (part `keys`) in a channel cut into both faces; the pieces slide together
        numbers   the piece's number engraved 0.6 mm into its cut face
        lay       every piece turned so its largest cut face lies on the bed (prints without supports there)
        Answers with the pieces (piece_<n>), where each one came from (`map`), the planes, and warnings.
analyse Only the plan: size, whether the model is closed, how many cuts the bed needs and where (no booleans).
scale   Uniform scale to `height` mm (or by `factor`), standing on the bed.

One JSON object on stdout; with a parts directory every piece is written there as <name>.stl. The file <out>.stage
holds one word while the tool works (loading, repairing, cutting, joints, layout), for the page that waits.
"""
import json
import math
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import mesh_tool as T  # noqa: E402

PIN_D, PIN_L, PLAY = 6.0, 12.0, 0.2
KEY_W, KEY_WAIST, KEY_T = 8.0, 5.0, 3.0      # the double-dovetail key: 8 mm wide at the ends, 5 at the waist, 3 mm into each face
NUMBER_DEPTH = 0.6
MAX_K = 6                                    # cuts per axis the plan tries
MAX_TRIANGLES = 2_000_000
THIN_TO = 1_200_000
GAP = 8.0                                    # between pieces on the bed
BED_ROW = 240.0
MIN_PIECE = 2.0                              # mm³: slivers below it are dust of the cut, not pieces


class Invalid(Exception):
    def __init__(self, code, detail=""):
        super().__init__(code + (": " + detail if detail else ""))
        self.code = code


def out(obj):
    print(json.dumps(obj))
    sys.exit(0)


def stage(dst, name):
    if not dst:
        return
    try:
        with open(dst + ".stage", "w", encoding="utf-8") as f:
            f.write(name)
    except OSError:
        pass


def load_solid(src, dst, notes):
    """The model as an exact solid standing on the bed with its corner at the origin, and its trimesh for sizes."""
    import numpy as np
    import trimesh
    stage(dst, "loading")
    m = T.load(src)
    notes["triangles_in"] = int(len(m.faces))
    if len(m.faces) > MAX_TRIANGLES:
        try:
            import fast_simplification  # noqa: F401
        except ImportError:
            raise Invalid("too_heavy", str(len(m.faces)))
        stage(dst, "thinning")
        m = m.simplify_quadric_decimation(face_count=THIN_TO)
        notes["thinned_to"] = int(len(m.faces))
    target = float(max(m.extents))
    if target < 5:
        raise Invalid("too_small")
    if target > 1000:
        raise Invalid("too_big")
    repaired = False
    if not m.is_watertight or not m.is_winding_consistent:
        stage(dst, "repairing")
        m = T.solidify(m, target)
        repaired = True
    if not m.is_watertight:
        raise Invalid("not_watertight")
    if m.volume < 0:
        m.invert()
    notes["repaired"] = repaired
    m.apply_translation(-m.bounds[0])
    import manifold3d as M
    man = T.as_manifold(m)
    if man.is_empty() or man.status() != M.Error.NoError:
        # a mesh trimesh calls closed may still hold self-intersections manifold refuses: the grid rebuild gives a clean one
        stage(dst, "repairing")
        m = T.rebuild_solid(m, target)
        m.apply_translation(-m.bounds[0])
        man = T.as_manifold(m)
        notes["repaired"] = True
        if man.is_empty() or man.status() != M.Error.NoError:
            raise Invalid("not_watertight")
    return man, m


def plan(ext, bed, forced=None):
    """
    How many cuts across each axis the bed asks for, and where. Every piece is judged as it will be printed: laid on
    its largest cut face, so the cut axis becomes its height. Fewest cuts first, then fewest pieces, then the biggest
    pieces. `forced`: {"x": [mm…]} the visitor's own planes on some axes (their count is kept, only the others are planned).
    """
    ux, uy, uz = bed
    ex, ey, ez = ext
    forced = forced or {}

    def fits(w, d, h):
        return h <= uz + 1e-6 and ((w <= ux + 1e-6 and d <= uy + 1e-6) or (w <= uy + 1e-6 and d <= ux + 1e-6))

    def judge(k):
        cell = [ex / k[0], ey / k[1], ez / k[2]]
        cut = [i for i in range(3) if k[i] > 1]
        if not cut:
            return fits(cell[0], cell[1], cell[2])
        # the largest cut face goes down; the other two sizes are the footprint
        down = max(cut, key=lambda i: cell[(i + 1) % 3] * cell[(i + 2) % 3])
        others = [cell[j] for j in range(3) if j != down]
        return fits(others[0], others[1], cell[down])

    best = None
    for kx in range(1, MAX_K + 1):
        for ky in range(1, MAX_K + 1):
            for kz in range(1, MAX_K + 1):
                k = (kx, ky, kz)
                if any(len(forced.get(a, [])) and k[i] != len(forced[a]) + 1 for i, a in enumerate("xyz")):
                    continue
                if not judge(k):
                    continue
                cuts = sum(v - 1 for v in k)
                pieces = kx * ky * kz
                smallest = min(ex / kx, ey / ky, ez / kz)
                key = (cuts, pieces, -smallest)
                if best is None or key < best[0]:
                    best = (key, k)
    if best is None:
        return None
    k = best[1]
    planes = {}
    for i, a in enumerate("xyz"):
        planes[a] = [round(float(v), 2) for v in forced[a]] if forced.get(a) else [round(ext[i] * j / k[i], 2) for j in range(1, k[i])]
    return planes


def clean_planes(raw, ext):
    """The visitor's planes: numbers inside the model, sorted, no two closer than 3 mm."""
    forced = {}
    if not isinstance(raw, dict):
        return forced
    for i, a in enumerate("xyz"):
        vals = []
        for v in raw.get(a) or []:
            try:
                v = float(v)
            except (TypeError, ValueError):
                continue
            if 1.0 < v < ext[i] - 1.0 and all(abs(v - o) >= 3.0 for o in vals):
                vals.append(v)
        if vals:
            forced[a] = sorted(vals)
    return forced


def turned(M, solid, axis):
    """The solid turned so that `axis` becomes z: a slice at a height is then a section across that axis."""
    import numpy as np
    if axis == "z":
        return solid
    if axis == "x":
        mat = np.array([[0, 1, 0, 0], [0, 0, 1, 0], [1, 0, 0, 0]], dtype=np.float64)      # (x, y, z) → (y, z, x)
    else:
        mat = np.array([[0, 0, 1, 0], [1, 0, 0, 0], [0, 1, 0, 0]], dtype=np.float64)      # (x, y, z) → (z, x, y)
    return solid.transform(mat)


def to3d(axis, at, u, v):
    """A point of a section across `axis` back in the model's space (see turned)."""
    if axis == "z":
        return (u, v, at)
    if axis == "x":
        return (at, u, v)
    return (v, at, u)


def box2d(M, axis, lo, hi):
    """The cell's extent across `axis` as a rectangle in section coordinates (u, v)."""
    if axis == "z":
        u0, u1, v0, v1 = lo[0], hi[0], lo[1], hi[1]
    elif axis == "x":
        u0, u1, v0, v1 = lo[1], hi[1], lo[2], hi[2]
    else:
        u0, u1, v0, v1 = lo[2], hi[2], lo[0], hi[0]
    return M.CrossSection([[(u0, v0), (u1, v0), (u1, v1), (u0, v1)]])


def spots(M, cs, radius, count, keep_off=None):
    from art_tool import _spots
    return _spots(M, cs, radius, count, keep_off)


def room_at(cs, x, y):
    """How far a point lies inside an outline (0 outside): the room a number or a key has there."""
    import numpy as np
    from PIL import Image, ImageDraw
    from scipy import ndimage
    x0, y0, x1, y1 = cs.bounds()
    cell = max(0.3, max(x1 - x0, y1 - y0) / 300)
    size = (int((x1 - x0) / cell) + 3, int((y1 - y0) / cell) + 3)
    img = Image.new("1", size, 0)
    draw = ImageDraw.Draw(img)
    for poly in cs.to_polygons():
        pts = [((float(px) - x0) / cell + 1, (float(py) - y0) / cell + 1) for px, py in poly]
        area = sum(pts[i][0] * pts[(i + 1) % len(pts)][1] - pts[(i + 1) % len(pts)][0] * pts[i][1] for i in range(len(pts)))
        draw.polygon(pts, fill=1 if area > 0 else 0)
    inside = ndimage.distance_transform_edt(np.asarray(img, dtype=bool)) * cell
    ix, iy = int(round((x - x0) / cell + 1)), int(round((y - y0) / cell + 1))
    if 0 <= iy < inside.shape[0] and 0 <= ix < inside.shape[1]:
        return float(inside[iy, ix])
    return 0.0


def cylinder_along(M, axis, at, u, v, length, radius):
    """A cylinder of `length` centred on the plane across `axis` at (u, v), along the axis."""
    c = M.Manifold.cylinder(length, radius, radius, 48).translate([0, 0, -length / 2])
    x, y, z = to3d(axis, at, u, v)
    if axis == "x":
        c = c.rotate([0, 90, 0])
    elif axis == "y":
        c = c.rotate([-90, 0, 0])
    return c.translate([x, y, z])


def key_solid(M, axis, at, u, v, length, play):
    """The double-dovetail key across the plane at (u, v), running along v; grown by `play` it is the channel for it."""
    import numpy as np
    w, waist, t = KEY_W / 2 + play, KEY_WAIST / 2 + play, KEY_T + play
    # the hourglass in (u, n): n runs along the axis, t into each piece; extruded along v, centred
    profile = M.CrossSection([[(-w, -t), (w, -t), (waist, 0), (w, t), (-w, t), (-waist, 0)]])      # counter-clockwise, or manifold reads it as a hole
    solid = profile.extrude(length).translate([0, 0, -length / 2]).rotate([90, 0, 0])      # (u, n, v) → (u, -v, n); the key is symmetric along v
    if axis == "x":
        solid = solid.transform(np.array([[0, 0, 1, 0], [1, 0, 0, 0], [0, 1, 0, 0]], dtype=np.float64))     # → (n, u, -v)
    elif axis == "y":
        solid = solid.transform(np.array([[0, 1, 0, 0], [0, 0, 1, 0], [1, 0, 0, 0]], dtype=np.float64))     # → (-v, n, u)
    x, y, z = to3d(axis, at, u, v)
    return solid.translate([x, y, z])


def lay_down(M, piece, axis, side):
    """The piece turned so the face across `axis` on its `side` (-1 low, +1 high) lies on the bed."""
    if axis == "z":
        p = piece if side < 0 else piece.rotate([180, 0, 0])
    elif axis == "x":
        p = piece.rotate([0, -90, 0]) if side < 0 else piece.rotate([0, 90, 0])
    else:
        p = piece.rotate([90, 0, 0]) if side < 0 else piece.rotate([-90, 0, 0])
    x0, y0, z0, _, _, _ = p.bounding_box()
    return p.translate([-x0, -y0, -z0])


def lay_out(pieces):
    laid, x, y, row_h = [], 0.0, 0.0, 0.0
    for name, solid in pieces:
        x0, y0, z0, x1, y1, z1 = solid.bounding_box()
        w, d = x1 - x0, y1 - y0
        if x > 0 and x + w > BED_ROW:
            x, y, row_h = 0.0, y + row_h + GAP, 0.0
        laid.append((name, solid.translate([x - x0, y - y0, -z0])))
        x += w + GAP
        row_h = max(row_h, d)
    return laid


def write_pieces(M, dst, pieces, parts_dir):
    import numpy as np
    listed, chunks, at = [], [], 0
    if parts_dir:
        os.makedirs(parts_dir, exist_ok=True)
    for name, piece in pieces:
        m = piece.to_mesh()
        t = np.asarray(m.vert_properties, dtype=np.float32)[:, :3][np.asarray(m.tri_verts, dtype=np.int64)]
        if not len(t):
            continue
        lo, hi = t.reshape(-1, 3).min(axis=0), t.reshape(-1, 3).max(axis=0)
        listed.append({"name": name, "tris": [at, at + len(t)], "bbox": [round(float(c), 2) for c in (*lo, *hi)]})
        chunks.append(t)
        at += len(t)
        if parts_dir:
            from art_tool import write_stl
            write_stl(os.path.join(parts_dir, name + ".stl"), t - np.array([lo[0], lo[1], lo[2]], dtype=np.float32))
    tri = np.concatenate(chunks) if chunks else np.zeros((0, 3, 3), dtype=np.float32)
    from art_tool import write_stl
    write_stl(dst, tri)
    return listed, tri


def bed_of(p):
    bed = p.get("bed") or [240, 240, 250]
    try:
        bed = [float(bed[0]), float(bed[1]), float(bed[2])]
    except (TypeError, ValueError, IndexError):
        raise Invalid("not_a_number", "bed")
    if min(bed) < 30 or max(bed) > 1000:
        raise Invalid("out_of_range", "bed 30-1000")
    return bed


def analyse(src, p):
    notes = {}
    man, m = load_solid(src, None, notes)
    ext = [float(v) for v in m.extents]
    bed = bed_of(p)
    forced = clean_planes(p.get("planes"), ext)
    planes = plan(ext, bed, forced)
    x0, y0, z0, x1, y1, z1 = man.bounding_box()
    out({"ok": True, "bbox": {"x": round(ext[0], 2), "y": round(ext[1], 2), "z": round(ext[2], 2)}, "volume_mm3": round(man.volume(), 1), "triangles": int(man.num_tri()),
         "repaired": notes.get("repaired", False), "fits": planes is not None and not any(planes.values()), "planes": planes, "too_many": planes is None,
         "pieces": (len(planes["x"]) + 1) * (len(planes["y"]) + 1) * (len(planes["z"]) + 1) if planes else None, "bed": bed})


def split(src, dst, p, parts_dir):
    import manifold3d as M
    import numpy as np
    import shape2d as S
    notes = {"warnings": []}
    warn = notes["warnings"]
    man, m = load_solid(src, dst, notes)
    ext = [float(v) for v in m.extents]
    bed = bed_of(p)
    joint = p.get("joint", "pins")
    if joint not in ("none", "pins", "dovetail"):
        raise Invalid("bad_choice", "joint")
    numbers = bool(p.get("numbers", True))
    lay = bool(p.get("lay", True))
    forced = clean_planes(p.get("planes"), ext)
    planes = plan(ext, bed, forced) if not forced or len(forced) < 3 else forced
    if planes is None:
        # the visitor's planes (or a model nothing fits) cannot be planned round: cut where asked, and say what does not fit
        planes = {a: forced.get(a, []) for a in "xyz"}
        warn.append("does_not_fit")
    cuts = sum(len(v) for v in planes.values())
    if cuts == 0:
        raise Invalid("fits_already")
    bounds = {a: [0.0] + planes[a] + [ext[i]] for i, a in enumerate("xyz")}

    # ── cutting ───────────────────────────────────────────────────────────────────────────────────────────────────
    stage(dst, "cutting")
    pieces = []                       # {n, cell, lo, hi, solid}
    n = 0
    eps = 0.01
    for i in range(len(bounds["x"]) - 1):
        for j in range(len(bounds["y"]) - 1):
            for k in range(len(bounds["z"]) - 1):
                lo = (bounds["x"][i], bounds["y"][j], bounds["z"][k])
                hi = (bounds["x"][i + 1], bounds["y"][j + 1], bounds["z"][k + 1])
                box = M.Manifold.cube([hi[0] - lo[0] + 2 * eps, hi[1] - lo[1] + 2 * eps, hi[2] - lo[2] + 2 * eps]).translate([lo[0] - eps, lo[1] - eps, lo[2] - eps])
                piece = man ^ box
                if piece.is_empty() or piece.volume() < MIN_PIECE:
                    continue
                n += 1
                pieces.append({"n": n, "cell": [i, j, k], "lo": lo, "hi": hi, "solid": piece, "faces": {}})
    if len(pieces) < 2:
        raise Invalid("fits_already")
    if len(pieces) > 20:
        warn.append("many_pieces")
    by_cell = {tuple(pc["cell"]): pc for pc in pieces}

    # ── the faces the pieces share, and the joints on them ─────────────────────────────────────────────────────────
    stage(dst, "joints")
    joints = []                       # {axis, at, a, b, pins: [...], key: ...}
    pin_count = 0
    key_count = 0
    sections = {}
    for pc in pieces:
        i, j, k = pc["cell"]
        for ai, a in enumerate("xyz"):
            nxt = [i, j, k]
            nxt[ai] += 1
            other = by_cell.get(tuple(nxt))
            if other is None:
                continue
            at = pc["hi"][ai]
            if (a, at) not in sections:
                sections[(a, at)] = turned(M, man, a).slice(at)
            region = sections[(a, at)] ^ box2d(M, a, pc["lo"], pc["hi"])
            # the pieces of this cell pair that really touch: the section inside both cells across the plane
            area = region.area()
            if area < 1.0:
                continue
            u0, v0, u1, v1 = region.bounds()
            pc["faces"][(a, +1)] = area
            other["faces"][(a, -1)] = area
            joint_here = {"axis": a, "at": round(at, 2), "a": pc["n"], "b": other["n"], "area_mm2": round(area, 1), "pins": [], "key": None}
            if joint == "pins":
                want = 1 if area < 400 else 2 if area < 2500 else 3 if area < 8000 else 4
                for (u, v) in spots(M, region, PIN_D / 2 + 2.5, want):
                    hole = cylinder_along(M, a, at, u, v, PIN_L + 2 * PLAY, PIN_D / 2 + PLAY / 2)
                    pc["solid"] = pc["solid"] - hole
                    other["solid"] = other["solid"] - hole
                    joint_here["pins"].append([round(u, 1), round(v, 1)])
                    pin_count += 1
                if not joint_here["pins"]:
                    warn.append("no_room_for_pins") if "no_room_for_pins" not in warn else None
            elif joint == "dovetail":
                uc, vc = (u0 + u1) / 2, (v0 + v1) / 2
                length = 0.6 * (v1 - v0)
                # the channel needs the face to be at least the key's width wide all along its run
                ok = length >= 12 and all(room_at(region, uc, vc + s * length / 2) >= KEY_W / 2 + 2.0 for s in (-1, 0, 1))
                if ok:
                    channel = key_solid(M, a, at, uc, vc, length + 2.0, PLAY / 2)
                    pc["solid"] = pc["solid"] - channel
                    other["solid"] = other["solid"] - channel
                    joint_here["key"] = {"u": round(uc, 1), "v": round(vc, 1), "length": round(length, 1)}
                    key_count += 1
                else:
                    want = 1 if area < 400 else 2
                    for (u, v) in spots(M, region, PIN_D / 2 + 2.5, want):
                        hole = cylinder_along(M, a, at, u, v, PIN_L + 2 * PLAY, PIN_D / 2 + PLAY / 2)
                        pc["solid"] = pc["solid"] - hole
                        other["solid"] = other["solid"] - hole
                        joint_here["pins"].append([round(u, 1), round(v, 1)])
                        pin_count += 1
                    if "key_as_pins" not in warn:
                        warn.append("key_as_pins")
            joints.append(joint_here)
            pc.setdefault("regions", {})[(a, +1)] = region
            other.setdefault("regions", {})[(a, -1)] = region

    # ── which face goes down, the number on it ─────────────────────────────────────────────────────────────────────
    stage(dst, "numbers")
    font = p.get("font")
    for pc in pieces:
        faces = pc["faces"]
        if faces:
            down = max(faces, key=lambda f: faces[f])
        else:
            down = None
        pc["down"] = down
        if numbers and down and font:
            a, side = down
            region = pc["regions"][down]
            at = pc["hi"]["xyz".index(a)] if side > 0 else pc["lo"]["xyz".index(a)]
            here = [j_ for j_ in joints if j_["axis"] == a and abs(j_["at"] - at) < 0.05 and pc["n"] in (j_["a"], j_["b"])]
            busy = [(u, v, PIN_D / 2 + PLAY) for j_ in here for (u, v) in j_["pins"]]
            for j_ in here:
                if j_["key"]:                                 # the channel of a key runs along v: a row of discs keeps the number off it
                    half = j_["key"]["length"] / 2 + 1.0
                    busy += [(j_["key"]["u"], j_["key"]["v"] + s, KEY_W / 2) for s in [x * 4.0 - half for x in range(int(2 * half / 4.0) + 1)]]
            where = spots(M, region, 4.0, 1, busy)
            if where:
                u, v = where[0]
                room = room_at(region, u, v)
                cap = max(3.0, min(8.0, room * 0.9))
                try:
                    text, _ = S.text(M, [str(pc["n"])], font, cap)
                    tw, th = S.size(text)
                    text = text.translate([u - tw / 2, v - th / 2])
                    pc["solid"] = pc["solid"] - number_cutter(M, text, a, at, side, NUMBER_DEPTH)
                    pc["number_at"] = [round(u, 1), round(v, 1)]
                except Exception:  # noqa: BLE001
                    pass

    # ── laying the pieces on the bed ───────────────────────────────────────────────────────────────────────────────
    stage(dst, "layout")
    named = []
    for pc in pieces:
        solid = pc["solid"]
        if lay and pc["down"]:
            a, side = pc["down"]
            solid = lay_down(M, solid, a, side)
        else:
            x0, y0, z0, _, _, _ = solid.bounding_box()
            solid = solid.translate([-x0, -y0, -z0])
        named.append(("piece_%d" % pc["n"], solid))
    extras = []
    if pin_count:
        pins = None
        cols = max(1, int(math.sqrt(pin_count)))
        for q in range(pin_count):
            one = M.Manifold.cylinder(PIN_L, PIN_D / 2, PIN_D / 2, 48).translate([(q % cols) * (PIN_D + 4.0), (q // cols) * (PIN_D + 4.0), 0])
            pins = one if pins is None else pins + one
        extras.append(("pins", pins))
    if key_count:
        keys = None
        for q, j_ in enumerate([j_ for j_ in joints if j_["key"]]):
            one = key_solid(M, "z", KEY_T, 0, 0, j_["key"]["length"], 0.0)
            kx0, ky0, kz0, kx1, ky1, kz1 = one.bounding_box()
            one = one.translate([-kx0, -ky0 + q * (KEY_W + 4.0), -kz0])
            keys = one if keys is None else keys + one
        extras.append(("keys", keys))
    laid = lay_out(named + extras)
    fused = None
    for _, solid in laid:
        fused = solid if fused is None else fused + solid
    if fused is None or fused.is_empty() or fused.status() != M.Error.NoError:
        raise Invalid("empty_result")
    listed, tri = write_pieces(M, dst, laid, parts_dir)
    stage(dst, "done")

    # ── what the page shows: the map of the pieces, the planes, the sizes ──────────────────────────────────────────
    each = []
    too_big = []
    for (name, solid), pc in zip(laid, pieces + [None] * len(extras)):
        x0, y0, z0, x1, y1, z1 = solid.bounding_box()
        size = [round(x1 - x0, 1), round(y1 - y0, 1), round(z1 - z0, 1)]
        each.append(size)
        w, d, h = sorted(size[:2], reverse=True) + [size[2]]
        if not (h <= bed[2] + 0.05 and ((w <= bed[0] + 0.05 and d <= bed[1] + 0.05) or (w <= bed[1] + 0.05 and d <= bed[0] + 0.05))):
            too_big.append(name)
    if too_big and "does_not_fit" not in warn:
        warn.append("does_not_fit")
    x0, y0, z0, x1, y1, z1 = fused.bounding_box()
    notes.update({
        "planes": planes, "bed": bed, "joint": joint, "pins": pin_count, "keys": key_count, "pieces": len(pieces), "cells": [len(bounds[a]) - 1 for a in "xyz"],
        "model": [round(v, 1) for v in ext], "volume_in_mm3": round(man.volume(), 1), "each": each, "too_big": too_big,
        "map": [{"n": pc["n"], "part": "piece_%d" % pc["n"], "cell": pc["cell"], "lo": [round(v, 1) for v in pc["lo"]], "hi": [round(v, 1) for v in pc["hi"]], "volume_mm3": round(pc["solid"].volume(), 1),
                 "down": list(pc["down"]) if pc["down"] else None, "number_at": pc.get("number_at")} for pc in pieces],
        "joints": joints, "parts": [name for name, _ in laid],
    })
    out({"ok": True, "kind": "split", "part": "all", "bbox": {"x": round(x1 - x0, 2), "y": round(y1 - y0, 2), "z": round(z1 - z0, 2)},
         "volume_mm3": round(fused.volume(), 1), "area_mm2": round(fused.surface_area(), 1), "triangles": int(len(tri)), "notes": notes, "parts": listed})


def number_cutter(M, text, axis, at, side, depth):
    """The text as a slab `depth` deep on the piece's side of the plane across `axis`, ready to be taken out of the piece."""
    slab = text.extrude(depth + 0.02)                        # in (u, v, n) with n = 0 … depth
    # into the piece: for the high side (+1) the piece lies below the plane, for the low side above it
    if side > 0:
        slab = slab.translate([0, 0, -(depth + 0.01)])        # n = -depth … +0.01
    else:
        slab = slab.translate([0, 0, -0.01])                  # n = -0.01 … depth
    if axis == "z":
        return slab.translate([0, 0, at])
    if axis == "x":
        # (u, v, n) → (n, u, v)
        import numpy as np
        mat = np.array([[0, 0, 1, at], [1, 0, 0, 0], [0, 1, 0, 0]], dtype=np.float64)
        return slab.transform(mat)
    import numpy as np
    # (u, v, n) → (v, n, u)
    mat = np.array([[0, 1, 0, 0], [0, 0, 1, at], [1, 0, 0, 0]], dtype=np.float64)
    return slab.transform(mat)


def scale(src, dst, p, parts_dir):
    import manifold3d as M
    notes = {"warnings": []}
    man, m = load_solid(src, dst, notes)
    ext = [float(v) for v in m.extents]
    factor = None
    if p.get("height"):
        factor = float(p["height"]) / ext[2]
    elif p.get("factor"):
        factor = float(p["factor"])
    if not factor or factor <= 0 or factor > 50:
        raise Invalid("out_of_range", "factor")
    solid = man.scale([factor, factor, factor])
    x0, y0, z0, x1, y1, z1 = solid.bounding_box()
    solid = solid.translate([-x0, -y0, -z0])
    listed, tri = write_pieces(M, dst, [("body", solid)], parts_dir)
    notes.update({"factor": round(factor, 4), "model": [round(v, 1) for v in ext], "parts": ["body"]})
    out({"ok": True, "kind": "scale", "part": "all", "bbox": {"x": round(x1 - x0, 2), "y": round(y1 - y0, 2), "z": round(z1 - z0, 2)},
         "volume_mm3": round(solid.volume(), 1), "area_mm2": round(solid.surface_area(), 1), "triangles": int(len(tri)), "notes": notes, "parts": listed})


def main(argv):
    if len(argv) < 4:
        out({"ok": False, "error": "usage", "code": "usage"})
    op = argv[1]
    try:
        if op == "analyse":
            raw = argv[3]
            if raw.startswith("@"):
                with open(raw[1:], encoding="utf-8") as fh:
                    raw = fh.read()
            analyse(argv[2], json.loads(raw or "{}"))
        src, dst, raw = argv[2], argv[3], argv[4] if len(argv) > 4 else "{}"
        parts_dir = argv[5] if len(argv) > 5 else None
        if raw.startswith("@"):
            with open(raw[1:], encoding="utf-8") as fh:
                raw = fh.read()
        p = json.loads(raw or "{}")
        if not isinstance(p, dict):
            raise Invalid("unknown_kind")
        if op == "split":
            split(src, dst, p, parts_dir)
        elif op == "scale":
            scale(src, dst, p, parts_dir)
        raise Invalid("unknown_kind", op)
    except Invalid as e:
        out({"ok": False, "code": e.code, "error": str(e)})
    except SystemExit:
        raise
    except Exception as e:  # noqa: BLE001
        out({"ok": False, "code": "failed", "error": str(e)})


if __name__ == "__main__":
    main(sys.argv)
