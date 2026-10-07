#!/usr/bin/env python3
"""
Editing a ready model (manifold3d, exact mm): the "I have a file" tools of matplace.

    edit_tool.py analyse   <in.stl> <params-json | @file>
    edit_tool.py split     <in.stl> <out.stl> <params-json | @file> [<parts-dir>]
    edit_tool.py hollow    <in.stl> <out.stl> <params-json | @file> [<parts-dir>]
    edit_tool.py scale     <in.stl> <out.stl> <params-json | @file> [<parts-dir>]
    edit_tool.py life_size <in.stl> <out.stl> <params-json | @file> [<parts-dir>]
    edit_tool.py puzzle    <in.stl> <out.stl> <params-json | @file> [<parts-dir>]
    edit_tool.py holder    <in.stl> <out.stl> <params-json | @file> [<parts-dir>]
    edit_tool.py potion    <in.stl> <out.stl> <params-json | @file> [<parts-dir>]
    edit_tool.py flexi_cut <in.stl> <out.stl> <params-json | @file> [<parts-dir>]

The model is loaded (any unit guess as in mesh_tool.load), closed when it is not (mesh_tool.solidify: pymeshfix, union,
or a rebuild through a grid, the way the farm does it) and turned into an exact solid. Very heavy models are thinned
first (fast_simplification, when it is installed; above 2 M triangles without it the tool refuses).

split     Cuts the model with planes across X, Y and Z so that every piece fits the bed, with as few cuts as it takes.
          params: {"bed": [x, y, z] usable mm, "planes": {"x": [mm…], "y": [...], "z": [...]} (the visitor's own planes,
          else automatic), "joint": "none" | "pins" | "dovetail", "numbers": true, "lay": true, "font": path}
          pins      Ø 6 × 12 mm dowels (part `pins`, standing on the bed) in holes with 0.2 mm of play, on both faces
          dovetail  a loose double-dovetail key (part `keys`) in a channel cut into both faces; the pieces slide together
          numbers   the piece's number engraved 0.6 mm into its cut face
          lay       every piece turned so its largest cut face lies on the bed (prints without supports there)
          Answers with the pieces (piece_<n>), where each one came from (`map`), the planes, and warnings.
hollow    Takes the inside out, leaving a wall of `wall` mm (1.5–6): the distance to the surface on a grid of at most
          0.6 mm and 320³ cells (a bigger model gets a coarser grid, and says so), the inner surface by marching cubes,
          subtracted from the model. Drain holes of `drain_d` mm (3–8, `drains` 0–4, 0 = as many as the bottom has room
          for) through the lowest wall. Answers with the material saved (hollow.cavity_mm3, saved_g).
          view "cut" draws the model with a quarter taken out (the card of the tool), not for printing.
scale     Uniform scale to `height` mm (or by `factor`), standing on the bed.
life_size Scale to `height` mm, hollow it when it is bigger than 200 cm³ (wall 2–5 mm by its size), split it for the bed
          with pins: one tool for the "make it life size" page. Answers like split, with the hollowing in `hollow`.
puzzle    A flat model (a relief, a logo, a lithophane) as a jigsaw: a grid of `rows` × `cols` (2–8) pieces cut
          through the height, with classic knobs (`lock` "tabs": a head of `knob` % of the piece's side, 0.2 mm of play)
          or hidden pins (`lock` "pins": straight cuts, Ø 3 × 6 pins in the sides, the top stays clean); the number of
          every piece engraved underneath; an optional tray (part `frame`). A model taller than 25 mm gets pins.
holder    A holder out of a model (a koozie, an ice-cream pint sleeve, a soap dish): the model scaled to `height` mm,
          stood on the bed, and a cavity taken out of its top: `cavity` can330 | slim330 | can500 | pint | soap | candle
          | custom (`cav_d`, `cav_d2` for a cone, `cav_depth`, `cav_w`/`cav_l` for a box), `clearance` 0.3–1.5, `cav_x`/`cav_y`
          offset of the cavity's middle in mm, `cav_depth` for the preset too. The wall round the cavity is measured on
          five heights; under 2 mm the tool says how much bigger the model has to be. The soap dish gets a push-out hole
          in its floor. One part, `body`.
potion    A potion bottle out of a model: the model scaled to `height` mm, its bottom cut flat (`cut` % of the height),
          hollowed with `wall` mm, a neck of `neck_d` × `neck_h` on top opening into the cavity, a tapered cork (part `cork`)
          and, with `label`, a rounded label plate with `text` raised on it (part `label`, glued on). Parts: body, cork, label.
flexi_cut A model cut into `segments` (3–20, 0 = by the ball) across its longest axis (or `axis` x | y | z), with a
          ball joint in every cut: a ball of `ball_d` (6–10) on a neck on one segment, a socket with `clearance`
          (0.35–0.5) in the other, the segments `gap` apart. Printed in one go, assembled (print in place); the answer
          keeps the segments as pieces in their places, so the page can paint them. A cut too thin for the joint
          is said so, and that joint is left out (the segments then only touch).
analyse   Only the plan: size, whether the model is closed, how many cuts the bed needs and where (no booleans); with
          `height` also what the scaled model would come to.

One JSON object on stdout; with a parts directory every piece is written there as <name>.stl. The file <out>.stage
holds one word while the tool works (loading, repairing, cutting, joints, layout…), for the page that waits.
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
GRID_MM = 0.6                                # the finest grid of the hollowing
GRID_CELLS = 320                             # and its most cells along one axis
HOLLOW_FROM_CM3 = 200.0                      # life size: a model bigger than this is hollowed
PLA_G_CM3 = 1.24
PUZZLE_PIN_D, PUZZLE_PIN_L = 3.0, 6.0        # hidden pins of a puzzle
PUZZLE_TALL = 25.0                           # mm: above it a puzzle gets pins, not knobs (a knob through 40 mm of plastic does not slide)
# the things a holder is made for: diameter at the bottom, diameter at the top (a cone), depth; a box has width × length
CAVITIES = {
    "can330": {"d": 66.3, "d2": 66.3, "depth": 90.0},        # a 330 ml can (115 mm tall; the sleeve covers most of it)
    "slim330": {"d": 58.0, "d2": 58.0, "depth": 110.0},      # a slim 330 ml can (146 mm tall)
    "can500": {"d": 66.3, "d2": 66.3, "depth": 130.0},       # a 500 ml / energy can (168 mm tall)
    "pint": {"d": 80.0, "d2": 95.0, "depth": 100.0},         # a 473 ml ice-cream pint, tapered
    "soap": {"w": 90.0, "l": 60.0, "depth": 30.0, "r": 12.0},   # a bar of soap: a rounded box, a push-out hole underneath
    "candle": {"d": 80.0, "d2": 80.0, "depth": 25.0},        # a candle in a glass, three wicks are 103 mm
    "custom": {"d": 60.0, "d2": 60.0, "depth": 80.0},
}
MIN_WALL = 2.0
FLEXI_GAP = 0.45                             # mm between the segments of a flexi (two layers of play)
SURFACE_OFFSET = 1.1                         # cells: how far inside the true surface the centre of a surface cell lies (calibrated on a cube and a sphere)
KEEP_AT_CUT = 6.0                            # mm: a life-size model stays solid this far on each side of a cutting plane, so pins have something to sit in
COLLAR_DEEP = 13.0                           # mm: and that collar reaches this deep under the surface (a pin of Ø 6 with 2.5 mm of wall round it)


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


def extents_of(solid):
    x0, y0, z0, x1, y1, z1 = solid.bounding_box()
    return [x1 - x0, y1 - y0, z1 - z0]


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


def number_cutter(M, text, axis, at, side, depth):
    """The text as a slab `depth` deep on the piece's side of the plane across `axis`, ready to be taken out of the piece."""
    import numpy as np
    slab = text.extrude(depth + 0.02)                        # in (u, v, n) with n = 0 … depth
    # into the piece: for the high side (+1) the piece lies below the plane, for the low side above it
    if side > 0:
        slab = slab.translate([0, 0, -(depth + 0.01)])        # n = -depth … +0.01
    else:
        slab = slab.translate([0, 0, -0.01])                  # n = -0.01 … depth
    if axis == "z":
        return slab.translate([0, 0, at])
    if axis == "x":
        return slab.transform(np.array([[0, 0, 1, at], [1, 0, 0, 0], [0, 1, 0, 0]], dtype=np.float64))     # (u, v, n) → (n, u, v)
    return slab.transform(np.array([[0, 1, 0, 0], [0, 0, 1, at], [1, 0, 0, 0]], dtype=np.float64))         # (u, v, n) → (v, n, u)


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
    from art_tool import write_stl
    listed, chunks, at = [], [], 0
    if parts_dir:
        os.makedirs(parts_dir, exist_ok=True)
    for name, piece in pieces:
        # exact solids may hold vertices that share a spot (a pinched tunnel): every program welds them and calls the
        # file open, so they are moved a few microns apart first (mesh_tool.from_manifold, as the mold does)
        tm = T.from_manifold(piece)
        t = np.asarray(tm.triangles, dtype=np.float32)
        if not len(t):
            continue
        lo, hi = t.reshape(-1, 3).min(axis=0), t.reshape(-1, 3).max(axis=0)
        listed.append({"name": name, "tris": [at, at + len(t)], "bbox": [round(float(c), 2) for c in (*lo, *hi)]})
        chunks.append(t)
        at += len(t)
        if parts_dir:
            write_stl(os.path.join(parts_dir, name + ".stl"), t - np.array([lo[0], lo[1], lo[2]], dtype=np.float32))
    tri = np.concatenate(chunks) if chunks else np.zeros((0, 3, 3), dtype=np.float32)
    write_stl(dst, tri)
    return listed, tri


def finish(M, kind, dst, laid, notes, parts_dir):
    """The laid pieces as one file (and one each), measured; the one answer every command gives."""
    fused = None
    for _, solid in laid:
        fused = solid if fused is None else fused + solid
    if fused is None or fused.is_empty() or fused.status() != M.Error.NoError:
        raise Invalid("empty_result")
    listed, tri = write_pieces(M, dst, laid, parts_dir)
    stage(dst, "done")
    notes["parts"] = [name for name, _ in laid]
    notes.setdefault("each", [[round(v, 1) for v in extents_of(solid)] for _, solid in laid])
    x0, y0, z0, x1, y1, z1 = fused.bounding_box()
    out({"ok": True, "kind": kind, "part": "all", "bbox": {"x": round(x1 - x0, 2), "y": round(y1 - y0, 2), "z": round(z1 - z0, 2)},
         "volume_mm3": round(fused.volume(), 1), "area_mm2": round(fused.surface_area(), 1), "triangles": int(len(tri)), "notes": notes, "parts": listed})


def bed_of(p):
    bed = p.get("bed") or [240, 240, 250]
    try:
        bed = [float(bed[0]), float(bed[1]), float(bed[2])]
    except (TypeError, ValueError, IndexError):
        raise Invalid("not_a_number", "bed")
    if min(bed) < 30 or max(bed) > 1000:
        raise Invalid("out_of_range", "bed 30-1000")
    return bed


def wall_for(height_mm):
    """The wall of a life-size hollow: 2 mm at 200 mm, 5 mm at a metre."""
    return max(2.0, min(5.0, 2.0 + 3.0 * (height_mm - 200.0) / 800.0))


def analyse(src, p):
    notes = {}
    man, m = load_solid(src, None, notes)
    ext = [float(v) for v in m.extents]
    bed = bed_of(p)
    forced = clean_planes(p.get("planes"), ext)
    planes = plan(ext, bed, forced)
    answer = {"ok": True, "bbox": {"x": round(ext[0], 2), "y": round(ext[1], 2), "z": round(ext[2], 2)}, "volume_mm3": round(man.volume(), 1), "triangles": int(man.num_tri()),
              "repaired": notes.get("repaired", False), "fits": planes is not None and not any(planes.values()), "planes": planes, "too_many": planes is None,
              "pieces": (len(planes["x"]) + 1) * (len(planes["y"]) + 1) * (len(planes["z"]) + 1) if planes else None, "bed": bed}
    if p.get("op") == "wearable":
        # wearable: the scale the measure asks for, the size that gives, whether it gets hollowed, how many pieces
        factor, z, outer, inner, already, target = wearable_factor(p, man, ext)
        if not 0.2 <= factor <= 20:
            raise Invalid("out_of_range", "factor")
        big = [v * factor for v in ext]
        planes = plan(big, bed, {}) if p.get("split", True) else {"x": [], "y": [], "z": []}
        wall = float(p.get("wall", 3.0))
        answer.update({"factor": round(factor, 4), "scaled": [round(v, 1) for v in big], "hollow": bool(p.get("hollow", True)) and not already, "already_hollow": already, "wall": wall,
                       "target": round(target, 1), "girth": round((inner if already else max(outer - 2 * math.pi * wall, 0.0)) * factor, 1),
                       "pieces": (len(planes["x"]) + 1) * (len(planes["y"]) + 1) * (len(planes["z"]) + 1) if planes else None, "planes": planes, "too_many": planes is None, "fits": planes is not None and not any(planes.values())})
        out(answer)
    if p.get("height"):
        # life size: what the scaled model would come to (hollow or not, how many pieces)
        factor = float(p["height"]) / ext[2]
        big = [v * factor for v in ext]
        planes = plan(big, bed, {})
        answer.update({"factor": round(factor, 4), "scaled": [round(v, 1) for v in big], "scaled_volume_mm3": round(man.volume() * factor ** 3, 1),
                       "hollow": man.volume() * factor ** 3 > HOLLOW_FROM_CM3 * 1000, "wall": round(wall_for(big[2]), 1),
                       "pieces": (len(planes["x"]) + 1) * (len(planes["y"]) + 1) * (len(planes["z"]) + 1) if planes else None, "planes": planes, "too_many": planes is None, "fits": planes is not None and not any(planes.values())})
    out(answer)


# ── split ──────────────────────────────────────────────────────────────────────────────────────────────────────────

def split_solid(M, man, ext, p, dst, notes):
    """Cuts `man` for the bed and lays the pieces out; returns [(name, solid)] and fills `notes`."""
    import shape2d as S
    warn = notes.setdefault("warnings", [])
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
                # neighbouring cells meet exactly on the plane; only the outside of the model gets a hair of room
                pad_lo = [eps if idx == 0 else 0.0 for idx in (i, j, k)]
                pad_hi = [eps if idx == last else 0.0 for idx, last in ((i, len(bounds["x"]) - 2), (j, len(bounds["y"]) - 2), (k, len(bounds["z"]) - 2))]
                box = M.Manifold.cube([hi[0] - lo[0] + pad_lo[0] + pad_hi[0], hi[1] - lo[1] + pad_lo[1] + pad_hi[1], hi[2] - lo[2] + pad_lo[2] + pad_hi[2]]).translate([lo[0] - pad_lo[0], lo[1] - pad_lo[1], lo[2] - pad_lo[2]])
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
                if not joint_here["pins"] and "no_room_for_pins" not in warn:
                    warn.append("no_room_for_pins")
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
        down = max(faces, key=lambda f: faces[f]) if faces else None
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

    # ── what the page shows: the map of the pieces, the planes, the sizes ──────────────────────────────────────────
    each = []
    too_big = []
    for name, solid in laid:
        size = [round(v, 1) for v in extents_of(solid)]
        each.append(size)
        w, d, h = sorted(size[:2], reverse=True) + [size[2]]
        if not (h <= bed[2] + 0.05 and ((w <= bed[0] + 0.05 and d <= bed[1] + 0.05) or (w <= bed[1] + 0.05 and d <= bed[0] + 0.05))):
            too_big.append(name)
    if too_big and "does_not_fit" not in warn:
        warn.append("does_not_fit")
    notes.update({
        "planes": planes, "bed": bed, "joint": joint, "pins": pin_count, "keys": key_count, "pieces": len(pieces), "cells": [len(bounds[a]) - 1 for a in "xyz"],
        "model": [round(v, 1) for v in ext], "volume_in_mm3": round(man.volume(), 1), "each": each, "too_big": too_big,
        "map": [{"n": pc["n"], "part": "piece_%d" % pc["n"], "cell": pc["cell"], "lo": [round(v, 1) for v in pc["lo"]], "hi": [round(v, 1) for v in pc["hi"]], "volume_mm3": round(pc["solid"].volume(), 1),
                 "down": list(pc["down"]) if pc["down"] else None, "number_at": pc.get("number_at")} for pc in pieces],
        "joints": joints,
    })
    return laid


def split(src, dst, p, parts_dir):
    import manifold3d as M
    notes = {"warnings": []}
    man, m = load_solid(src, dst, notes)
    laid = split_solid(M, man, [float(v) for v in m.extents], p, dst, notes)
    finish(M, "split", dst, laid, notes, parts_dir)


# ── hollow ─────────────────────────────────────────────────────────────────────────────────────────────────────────

def filled_grid(M, man, m, pitch):
    """
    The cells of a grid of `pitch` the solid touches (their centres within half a cell of it), as trimesh's
    voxelized(pitch).fill() gives them, but drawn from slices of the exact solid: a coarse mesh with big triangles
    costs nothing extra (the voxelizer subdivides every triangle down to the pitch, 50 s on a 190 mm dome).
    Returns (grid[x, y, z], origin): cell (0, 0, 0) is centred on the model's lowest corner.
    """
    import numpy as np
    from PIL import Image, ImageDraw
    lo, hi = m.bounds
    n = [int(math.ceil((hi[k] - lo[k]) / pitch)) + 1 for k in range(3)]
    grid = np.zeros((n[0], n[1], n[2]), dtype=bool)
    half = pitch / 2
    eps = min(0.02 * pitch, 0.01)
    for k in range(n[2]):
        z = min(max(lo[2] + k * pitch, lo[2] + eps), hi[2] - eps)
        cs = man.slice(z)
        if cs.is_empty():
            continue
        cs = cs.offset(half, M.JoinType.Miter, 2.0, 8)
        polys = []
        for poly in cs.to_polygons():
            if len(poly) < 3:
                continue
            area = sum(poly[i][0] * poly[(i + 1) % len(poly)][1] - poly[(i + 1) % len(poly)][0] * poly[i][1] for i in range(len(poly)))
            polys.append((area, poly))
        polys.sort(key=lambda ap: -ap[0])                       # outer loops first, holes (clockwise, negative) after
        img = Image.new("1", (n[0], n[1]), 0)
        draw = ImageDraw.Draw(img)
        for area, poly in polys:
            draw.polygon([((x - lo[0]) / pitch + 0.5, (y - lo[1]) / pitch + 0.5) for x, y in poly], fill=1 if area > 0 else 0)
        grid[:, :, k] = np.asarray(img, dtype=bool).T
    return grid, np.array(lo, dtype=np.float64)


def hollow_solid(M, man, m, p, dst, notes, keep_planes=None):
    """
    The inside taken out of `man`, a wall of `wall` mm left. The distance to the surface is measured on a grid (the
    filled voxels of the model, a distance transform), the inner surface is where it reaches the wall (marching cubes
    on the smoothed field), and that body is subtracted. Drain holes go through the lowest wall. Returns the hollow
    solid, or `man` itself when nothing fits inside the wall (notes say so). `keep_planes`: {"x": [mm…]} planes the
    model will be cut at: a slab of KEEP_AT_CUT on each side of them stays solid (the joints need the material).
    """
    import numpy as np
    import trimesh
    from scipy import ndimage
    from skimage import measure
    warn = notes.setdefault("warnings", [])
    wall = float(p.get("wall", 2.5))
    if not 1.5 <= wall <= 6.0:
        raise Invalid("out_of_range", "wall 1.5-6")
    drain_d = float(p.get("drain_d", 5.0))
    if not 3.0 <= drain_d <= 8.0:
        raise Invalid("out_of_range", "drain_d 3-8")
    drains = int(p.get("drains", 0))
    if not 0 <= drains <= 4:
        raise Invalid("out_of_range", "drains 0-4")
    no_drain = bool(p.get("no_drain", False))
    stage(dst, "measuring")
    ext = [float(v) for v in m.extents]
    pitch = max(GRID_MM, max(ext) / GRID_CELLS)
    if pitch > GRID_MM + 1e-6:
        warn.append("coarse_grid")
    notes["hollow"] = {"wall": wall, "pitch": round(pitch, 2), "cavity_mm3": 0.0, "saved_g": 0.0, "drains": 0, "drain_at": [], "drain_d": drain_d}
    grid, origin = filled_grid(M, man, m, pitch)
    grid = np.pad(grid, 2)
    origin = origin - 2 * pitch
    inside = ndimage.distance_transform_edt(grid).astype(np.float32) * pitch       # mm to the nearest outside cell, from the cell centre
    # the surface cells themselves lie about half a cell inside the true surface
    field = ndimage.gaussian_filter(inside, 0.8) - (wall + SURFACE_OFFSET * pitch)
    for axis, ats in (keep_planes or {}).items():
        # a ring of material stays round every cut: KEEP_AT_CUT on each side of the plane, COLLAR_DEEP under the surface
        k = "xyz".index(axis)
        deep = inside > wall + COLLAR_DEEP
        for at in ats:
            idx = int(round((at - origin[k]) / pitch))
            reach = int(math.ceil(KEEP_AT_CUT / pitch))
            lo, hi = max(0, idx - reach), min(field.shape[k], idx + reach + 1)
            sl = [slice(None)] * 3
            sl[k] = slice(lo, hi)
            band = field[tuple(sl)]
            band[~deep[tuple(sl)]] = -1.0
    if not (field > 0).any():
        warn.append("nothing_to_hollow")
        return man
    stage(dst, "hollowing")
    verts, faces, _, _ = measure.marching_cubes(field, level=0.0)
    inner = trimesh.Trimesh(verts * pitch + origin, faces, process=True)
    if inner.volume < 0:
        inner.invert()
    bodies = [b for b in inner.split(only_watertight=True) if b.volume > 50.0]
    cavity = None
    for b in bodies:
        one = T.as_manifold(T.simplified(b, 0.05))
        if one.is_empty() or one.status() != M.Error.NoError:
            continue
        cavity = one if cavity is None else cavity + one
    if cavity is None:
        warn.append("nothing_to_hollow")
        return man
    hollow = man - cavity
    # ── drain holes through the lowest wall, where the cavity has room for them ──────────────────────────────────
    holes = []
    if not no_drain:
        cx0, cy0, cz0, cx1, cy1, cz1 = cavity.bounding_box()
        floor = cavity.slice(cz0 + max(1.0, 0.6 * pitch))
        want = drains or (1 if floor.area() < 600 else 2 if floor.area() < 6000 else 3)
        if not floor.is_empty():
            for (u, v) in spots(M, floor, drain_d / 2 + 1.0, want):
                hole = M.Manifold.cylinder(cz0 + 3.0 + 1.0, drain_d / 2, drain_d / 2, 48).translate([u, v, -1.0])
                hollow = hollow - hole
                holes.append([round(u, 1), round(v, 1)])
        if not holes:
            warn.append("no_room_for_drain")
    notes["hollow"].update({"cavity_mm3": round(cavity.volume(), 1), "saved_g": round(cavity.volume() / 1000 * PLA_G_CM3, 1), "drains": len(holes), "drain_at": holes, "cells": [int(v) for v in grid.shape]})
    return hollow


def hollow(src, dst, p, parts_dir):
    import manifold3d as M
    notes = {"warnings": []}
    man, m = load_solid(src, dst, notes)
    solid = hollow_solid(M, man, m, p, dst, notes)
    notes["model"] = [round(float(v), 1) for v in m.extents]
    notes["volume_in_mm3"] = round(man.volume(), 1)
    if p.get("view") == "cut":
        # the card: a quarter taken out, so the wall and the cavity can be seen
        x0, y0, z0, x1, y1, z1 = solid.bounding_box()
        solid = solid - M.Manifold.cube([x1 - x0, y1 - y0, z1 - z0 + 2]).translate([(x0 + x1) / 2, (y0 + y1) / 2, z0 - 1])
    finish(M, "hollow", dst, [("body", solid)], notes, parts_dir)


# ── scale, life size ───────────────────────────────────────────────────────────────────────────────────────────────

def factor_of(p, ext):
    factor = None
    if p.get("height"):
        factor = float(p["height"]) / ext[2]
    elif p.get("factor"):
        factor = float(p["factor"])
    if not factor or factor <= 0 or factor > 50:
        raise Invalid("out_of_range", "factor")
    return factor


def scaled(M, man, m, factor):
    solid = man.scale([factor, factor, factor])
    x0, y0, z0, _, _, _ = solid.bounding_box()
    solid = solid.translate([-x0, -y0, -z0])
    big = m.copy()
    big.apply_scale(factor)
    big.apply_translation(-big.bounds[0])
    return solid, big


def scale(src, dst, p, parts_dir):
    import manifold3d as M
    notes = {"warnings": []}
    man, m = load_solid(src, dst, notes)
    factor = factor_of(p, [float(v) for v in m.extents])
    solid, big = scaled(M, man, m, factor)
    notes.update({"factor": round(factor, 4), "model": [round(float(v), 1) for v in m.extents]})
    finish(M, "scale", dst, [("body", solid)], notes, parts_dir)


def life_size(src, dst, p, parts_dir):
    import manifold3d as M
    notes = {"warnings": []}
    man, m = load_solid(src, dst, notes)
    factor = factor_of(p, [float(v) for v in m.extents])
    solid, big = scaled(M, man, m, factor)
    ext = [float(v) for v in big.extents]
    if max(ext) > 1000:
        raise Invalid("too_big")
    notes.update({"factor": round(factor, 4), "model": [round(float(v), 1) for v in m.extents], "scaled": [round(v, 1) for v in ext], "scaled_volume_mm3": round(solid.volume(), 1)})
    bed = bed_of(p)
    planes = plan(ext, bed, {})
    hollowed = bool(p.get("hollow", True)) and solid.volume() > HOLLOW_FROM_CM3 * 1000
    if hollowed:
        q = dict(p)
        q.setdefault("wall", round(wall_for(ext[2]), 1))
        solid = hollow_solid(M, solid, big, q, dst, notes, planes if planes else None)
    else:
        notes["hollow"] = None
    notes["grams"] = round(solid.volume() / 1000 * PLA_G_CM3, 1)
    if planes is not None and not any(planes.values()):
        notes.update({"pieces": 1, "pins": 0, "keys": 0, "planes": planes, "bed": bed, "cells": [1, 1, 1], "map": [], "joints": []})
        finish(M, "life_size", dst, [("piece_1", solid)], notes, parts_dir)
    q = dict(p)
    q.setdefault("joint", "pins")
    q.pop("planes", None)
    laid = split_solid(M, solid, ext, q, dst, notes)
    finish(M, "life_size", dst, laid, notes, parts_dir)


# ── puzzle ─────────────────────────────────────────────────────────────────────────────────────────────────────────

def knob2d(M, length, along, at, into, share, play=0.0):
    """
    A jigsaw knob on the grid line across `along` ('x': the line runs along x at y = at; 'y': along y at x = at),
    centred at `share` of the line's length, reaching `into` (+1 / -1) across the line: a neck and a round head whose
    diameter is `knob` of the piece's side. Grown by `play` it is the hole the knob sits in.
    """
    C, J = M.CrossSection, M.JoinType.Round
    # the head is `share` wide; the whole knob reaches 0.375 of the piece's side, so knobs from opposite sides leave a web
    head_r = share * 0.5 + play
    neck_w = share * 0.55 + 2 * play
    neck_l = share * 0.2
    neck = C([[(-neck_w / 2, -0.5), (neck_w / 2, -0.5), (neck_w / 2, neck_l + head_r * 0.2), (-neck_w / 2, neck_l + head_r * 0.2)]])
    head = C.circle(head_r, 48).translate([0, neck_l + head_r * 0.75])
    k = (neck + head).offset(0.01, J, 2.0, 16)
    if into < 0:
        k = k.rotate(180)                                     # the knob is symmetric about its axis: turned round, it reaches the other way (a mirror would flip the winding)
    if along == "x":
        return k.translate([at, 0.0])
    return k.rotate(-90).translate([at, 0.0])                # +y → +x: the knob reaches across the vertical line


def puzzle_solid(M, man, ext, p, dst, notes):
    """A flat model as a jigsaw: returns the laid pieces and fills `notes`."""
    import random
    import shape2d as S
    C, J = M.CrossSection, M.JoinType.Round
    warn = notes.setdefault("warnings", [])
    rows = int(p.get("rows", 3))
    cols = int(p.get("cols", 4))
    if not (2 <= rows <= 8 and 2 <= cols <= 8):
        raise Invalid("out_of_range", "rows/cols 2-8")
    knob = float(p.get("knob", 35)) / 100.0
    if not 0.2 <= knob <= 0.5:
        raise Invalid("out_of_range", "knob 20-50")
    play = float(p.get("clearance", 0.2))
    if not 0.05 <= play <= 0.5:
        raise Invalid("out_of_range", "clearance")
    lock = p.get("lock", "tabs")
    if lock not in ("tabs", "pins"):
        raise Invalid("bad_choice", "lock")
    numbers = bool(p.get("numbers", True))
    frame = bool(p.get("frame", False))
    font = p.get("font")
    W, D, H = ext
    if H > PUZZLE_TALL and lock == "tabs":
        lock = "pins"
        warn.append("tall_gets_pins")
    if H > 80:
        raise Invalid("too_tall")
    cw, ch = W / cols, D / rows
    if min(cw, ch) < 12:
        raise Invalid("pieces_too_small", "%d x %d" % (cols, rows))
    stage(dst, "cutting")
    foot = man.project()                                      # the model's footprint: the pieces at the rim follow it
    rnd = random.Random(int(p.get("seed", 7)))
    # which way every inner edge's knob points: vertical lines (between columns) and horizontal lines (between rows)
    knobs = {}
    if lock == "tabs":
        for i in range(1, cols):
            for j in range(rows):
                knobs[("v", i, j)] = rnd.choice((-1, 1))
        for j in range(1, rows):
            for i in range(cols):
                knobs[("h", i, j)] = rnd.choice((-1, 1))
    pieces2d = []
    for j in range(rows):
        for i in range(cols):
            cell = C([[(i * cw, j * ch), ((i + 1) * cw, j * ch), ((i + 1) * cw, (j + 1) * ch), (i * cw, (j + 1) * ch)]])
            if lock == "tabs":
                # a knob of mine reaches into the neighbour; the neighbour's knob is a hole (grown by the play) in me
                for edge in ("left", "right", "bottom", "top"):
                    if edge == "left" and i > 0:
                        d = knobs[("v", i, j)]                 # +1: the knob of the left line points right, into me
                        k = knob2d(M, ch, "y", i * cw, +1, knob * ch, play if d > 0 else 0.0).translate([0, (j + 0.5) * ch])
                        cell = (cell - k) if d > 0 else (cell + knob2d(M, ch, "y", i * cw, -1, knob * ch).translate([0, (j + 0.5) * ch]))
                    if edge == "right" and i < cols - 1:
                        d = knobs[("v", i + 1, j)]
                        cell = (cell + knob2d(M, ch, "y", (i + 1) * cw, +1, knob * ch).translate([0, (j + 0.5) * ch])) if d > 0 else (cell - knob2d(M, ch, "y", (i + 1) * cw, -1, knob * ch, play).translate([0, (j + 0.5) * ch]))
                    if edge == "bottom" and j > 0:
                        d = knobs[("h", i, j)]                 # +1: the knob of the line below points up, into me
                        cell = (cell - knob2d(M, cw, "x", (i + 0.5) * cw, +1, knob * cw, play).translate([0, j * ch])) if d > 0 else (cell + knob2d(M, cw, "x", (i + 0.5) * cw, -1, knob * cw).translate([0, j * ch]))
                    if edge == "top" and j < rows - 1:
                        d = knobs[("h", i, j + 1)]
                        cell = (cell + knob2d(M, cw, "x", (i + 0.5) * cw, +1, knob * cw).translate([0, (j + 1) * ch])) if d > 0 else (cell - knob2d(M, cw, "x", (i + 0.5) * cw, -1, knob * cw, play).translate([0, (j + 1) * ch]))
            pieces2d.append((i, j, cell))
    pieces = []
    n = 0
    for i, j, cell in pieces2d:
        solid = man ^ cell.extrude(H + 2.0).translate([0, 0, -1.0])
        if solid.is_empty() or solid.volume() < MIN_PIECE:
            continue
        # a piece that fell apart (the footprint has a hole there) keeps its biggest body
        bodies = solid.decompose()
        if len(bodies) > 1:
            solid = max(bodies, key=lambda b: b.volume())
            warn.append("piece_split") if "piece_split" not in warn else None
        n += 1
        pieces.append({"n": n, "cell": [i, j], "solid": solid, "cell2d": cell})
    if len(pieces) < 2:
        raise Invalid("empty_result")
    # ── hidden pins: in the sides of the pieces, at half height ──────────────────────────────────────────────────
    stage(dst, "joints")
    pin_count = 0
    joints = []
    if lock == "pins":
        if H < PUZZLE_PIN_D + 2.0:
            warn.append("too_thin_for_pins")
        else:
            by_cell = {tuple(pc["cell"]): pc for pc in pieces}
            for pc in pieces:
                i, j = pc["cell"]
                for (di, dj, axis) in ((1, 0, "x"), (0, 1, "y")):
                    other = by_cell.get((i + di, j + dj))
                    if other is None:
                        continue
                    at = (i + 1) * cw if axis == "x" else (j + 1) * ch
                    region = turned(M, man, axis).slice(at) ^ box2d(M, axis, (i * cw, j * ch, 0.0), ((i + 1) * cw, (j + 1) * ch, H))
                    if region.area() < 20:
                        continue
                    want = 1 if (ch if axis == "x" else cw) < 40 else 2
                    placed = []
                    for (u, v) in spots(M, region, PUZZLE_PIN_D / 2 + 1.5, want):
                        hole = cylinder_along(M, axis, at, u, v, PUZZLE_PIN_L + 2 * PLAY, PUZZLE_PIN_D / 2 + PLAY / 2)
                        pc["solid"] = pc["solid"] - hole
                        other["solid"] = other["solid"] - hole
                        placed.append([round(u, 1), round(v, 1)])
                        pin_count += 1
                    joints.append({"axis": axis, "at": round(at, 2), "a": pc["n"], "b": other["n"], "pins": placed})
    # ── the number of every piece, engraved underneath ───────────────────────────────────────────────────────────
    stage(dst, "numbers")
    if numbers and font:
        for pc in pieces:
            bottom = pc["solid"].slice(0.3)
            where = spots(M, bottom, 3.5, 1)
            if not where:
                continue
            u, v = where[0]
            cap = max(3.0, min(7.0, room_at(bottom, u, v) * 0.9))
            try:
                text, _ = S.text(M, [str(pc["n"])], font, cap)
                tw, th = S.size(text)
                pc["solid"] = pc["solid"] - number_cutter(M, text.translate([u - tw / 2, v - th / 2]), "z", 0.0, -1, NUMBER_DEPTH)
                pc["number_at"] = [round(u, 1), round(v, 1)]
            except Exception:  # noqa: BLE001
                pass
    # ── the tray ──────────────────────────────────────────────────────────────────────────────────────────────────
    extras = []
    if frame:
        rim_w, base_t = 6.0, 1.5
        outer = foot.offset(rim_w + play, J, 2.0, 32)
        inner = foot.offset(play, J, 2.0, 32)
        tray = outer.extrude(min(H, 10.0) + base_t) - inner.extrude(min(H, 10.0) + 1.0).translate([0, 0, base_t])
        extras.append(("frame", tray))
        notes["frame"] = {"rim": rim_w, "base": base_t, "height": round(min(H, 10.0) + base_t, 1)}
    if pin_count:
        pins = None
        cols_p = max(1, int(math.sqrt(pin_count)))
        for q in range(pin_count):
            one = M.Manifold.cylinder(PUZZLE_PIN_L, PUZZLE_PIN_D / 2, PUZZLE_PIN_D / 2, 32).translate([(q % cols_p) * (PUZZLE_PIN_D + 3.0), (q // cols_p) * (PUZZLE_PIN_D + 3.0), 0])
            pins = one if pins is None else pins + one
        extras.append(("pins", pins))
    stage(dst, "layout")
    named = []
    for pc in pieces:
        x0, y0, z0, _, _, _ = pc["solid"].bounding_box()
        named.append(("piece_%d" % pc["n"], pc["solid"].translate([-x0, -y0, -z0])))
    laid = lay_out(named + extras)
    notes.update({
        "rows": rows, "cols": cols, "lock": lock, "knob": round(knob * 100), "clearance": play, "pieces": len(pieces), "pins": pin_count, "keys": 0, "cells": [cols, rows, 1],
        "model": [round(v, 1) for v in ext], "volume_in_mm3": round(man.volume(), 1), "planes": {"x": [round(i * cw, 2) for i in range(1, cols)], "y": [round(j * ch, 2) for j in range(1, rows)], "z": []},
        "map": [{"n": pc["n"], "part": "piece_%d" % pc["n"], "cell": pc["cell"] + [0], "lo": [round(pc["cell"][0] * cw, 1), round(pc["cell"][1] * ch, 1), 0.0], "hi": [round((pc["cell"][0] + 1) * cw, 1), round((pc["cell"][1] + 1) * ch, 1), round(H, 1)],
                 "volume_mm3": round(pc["solid"].volume(), 1), "down": None, "number_at": pc.get("number_at")} for pc in pieces],
        "joints": joints,
    })
    return laid


def puzzle(src, dst, p, parts_dir):
    import manifold3d as M
    notes = {"warnings": []}
    man, m = load_solid(src, dst, notes)
    laid = puzzle_solid(M, man, [float(v) for v in m.extents], p, dst, notes)
    finish(M, "puzzle", dst, laid, notes, parts_dir)


# ── holder ─────────────────────────────────────────────────────────────────────────────────────────────────────────

def cavity_2d(M, kind, spec, play, at_depth):
    """The cavity's section at `at_depth` below its mouth (0 = the mouth), as a CrossSection centred on the origin."""
    import shape2d as S
    C, J = M.CrossSection, M.JoinType.Round
    if kind == "soap":
        w, l, r = spec["w"] + 2 * play, spec["l"] + 2 * play, spec["r"]
        return S.rounded_rect(M, w, l, min(r, w / 2 - 0.1, l / 2 - 0.1)).translate([-w / 2, -l / 2])
    depth = spec["depth"]
    share = min(1.0, max(0.0, at_depth / depth)) if depth > 0 else 0.0
    d = spec["d2"] + (spec["d"] - spec["d2"]) * share + 2 * play     # d2 at the mouth, d at the bottom
    return C.circle(d / 2, 96)


def holder(src, dst, p, parts_dir):
    import manifold3d as M
    notes = {"warnings": []}
    warn = notes["warnings"]
    man, m = load_solid(src, dst, notes)
    ext0 = [float(v) for v in m.extents]
    factor = 1.0
    if p.get("height"):
        factor = factor_of(p, ext0)
    solid, big = scaled(M, man, m, factor)
    ext = [float(v) for v in big.extents]
    W, D, H = ext
    kind = p.get("cavity", "can330")
    if kind not in CAVITIES:
        raise Invalid("bad_choice", "cavity")
    spec = dict(CAVITIES[kind])
    for key in ("d", "d2", "depth", "w", "l"):
        if p.get("cav_" + key) is not None:
            try:
                spec[key] = float(p["cav_" + key])
            except (TypeError, ValueError):
                raise Invalid("not_a_number", "cav_" + key)
    if kind == "custom" and p.get("cav_d2") is None:
        spec["d2"] = spec["d"]
    play = float(p.get("clearance", 0.6))
    if not 0.3 <= play <= 1.5:
        raise Invalid("out_of_range", "clearance 0.3-1.5")
    depth = float(spec["depth"])
    if not 10.0 <= depth <= 200.0:
        raise Invalid("out_of_range", "depth 10-200")
    floor = H - depth
    if floor < MIN_WALL:
        raise Invalid("too_short", "%.0f" % (depth + MIN_WALL))
    cx = W / 2 + float(p.get("cav_x", 0) or 0)
    cy = D / 2 + float(p.get("cav_y", 0) or 0)
    stage(dst, "cutting")
    # the cavity: a cone or cylinder (or a rounded box) from the mouth down, built from its sections
    top = cavity_2d(M, kind, spec, play, 0.0)
    if kind == "soap" or abs(spec["d"] - spec["d2"]) < 0.01:
        cavity = top.extrude(depth + 1.0).translate([0, 0, H - depth])
    else:
        r_top = (spec["d2"] + 2 * play) / 2
        r_bottom = (spec["d"] + 2 * play) / 2
        cavity = M.Manifold.cylinder(depth + 1.0, r_bottom, r_top, 96).translate([0, 0, H - depth])
    cavity = cavity.translate([cx, cy, 0])
    # the wall round the cavity at five heights: how far the cavity could grow before it broke out of the model
    walls = []
    for i in range(5):
        at = depth * (i + 0.5) / 5
        z = H - at
        section = solid.slice(z)
        cav = cavity_2d(M, kind, spec, play, at).translate([cx, cy])
        lo, hi = 0.0, 8.0
        if not (cav.offset(lo, M.JoinType.Round, 2.0, 24) - section).is_empty():
            walls.append(0.0)
            continue
        for _ in range(10):
            mid = (lo + hi) / 2
            if (cav.offset(mid, M.JoinType.Round, 2.0, 24) - section).is_empty():
                lo = mid
            else:
                hi = mid
        walls.append(round(lo, 2))
    min_wall = min(walls) if walls else 0.0
    holder_solid = solid - cavity
    if kind == "soap":
        # a hole in the floor to push the bar out, and water out
        holder_solid = holder_solid - M.Manifold.cylinder(floor + 2.0, 10.0, 10.0, 48).translate([cx, cy, -1.0])
    if min_wall < MIN_WALL:
        # how much bigger the model would have to be for a 2 mm wall everywhere round the cavity
        warn.append("wall_thin")
        grow = (MIN_WALL - min_wall) * 2 + 1.0
        notes["grow_to"] = round(H * (1 + grow / max(spec.get("d2", spec.get("w", 60.0)), 20.0)), 0)
    if holder_solid.is_empty() or holder_solid.volume() < MIN_PIECE:
        raise Invalid("empty_result")
    notes.update({"factor": round(factor, 4), "model": [round(v, 1) for v in ext0], "scaled": [round(v, 1) for v in ext], "cavity": kind, "cavity_mm": {k: round(float(v), 1) for k, v in spec.items()},
                  "clearance": play, "at": [round(cx - W / 2, 1), round(cy - D / 2, 1)], "floor": round(floor, 1), "walls": walls, "min_wall": round(min_wall, 2), "volume_in_mm3": round(solid.volume(), 1)})
    finish(M, "holder", dst, [("body", holder_solid)], notes, parts_dir)


# ── soap dish by the footprint of a model ──────────────────────────────────────────────────────────────────────────

DRAIN_SLOT = 3.0                              # mm: the width of a drain slot through the floor
DRAIN_PITCH = 10.0                            # mm between slots or ribs
RIB_W, RIB_H = 2.0, 2.0                       # mm: ribs the bar lies on, water runs between them
FLOOR_MARGIN = 3.0                            # mm of floor kept along the wall, so the drains never meet it


def footprint_of(M, man, ext, kind):
    """The model's outline on the bed: its whole projection, or the slice just above its bottom; outer loops only."""
    C, J = M.CrossSection, M.JoinType.Round
    if kind == "bottom":
        foot = man.slice(min(0.6, ext[2] / 2))
    else:
        try:
            foot = man.project()
        except AttributeError:
            foot = C()
            for i in range(12):
                foot = foot + man.slice(ext[2] * (i + 0.5) / 12)
    if foot.is_empty():
        raise Invalid("empty_result")
    # one outline without holes, lightly rounded: the bar's silhouette, not its engraving
    foot = foot.offset(1.0, J, 2.0, 24).offset(-1.0, J, 2.0, 24).simplify(0.05)
    loops = [poly for poly in foot.to_polygons() if sum(poly[i][0] * poly[(i + 1) % len(poly)][1] - poly[(i + 1) % len(poly)][0] * poly[i][1] for i in range(len(poly))) > 0]
    return C(loops) if loops else foot


def soap(src, dst, p, parts_dir):
    """A soap dish round a model's footprint: a pocket the outline plus play, a wall, a floor with drains."""
    import functools
    import manifold3d as M
    C, J = M.CrossSection, M.JoinType.Round
    notes = {"warnings": []}
    man, m = load_solid(src, dst, notes)
    ext = [float(v) for v in m.extents]
    height = float(p.get("height", 20.0))
    play = float(p.get("clearance", 2.0))
    wall = float(p.get("wall", 2.4))
    floor = float(p.get("floor", 2.0))
    drain = p.get("drain", "grooves")
    foot_kind = p.get("foot", "widest")
    if drain not in ("grooves", "grid", "ribs", "none"):
        raise Invalid("bad_choice", "drain")
    if foot_kind not in ("widest", "bottom"):
        raise Invalid("bad_choice", "foot")
    for name, v, lo, hi in (("height", height, 8.0, 60.0), ("clearance", play, 0.5, 6.0), ("wall", wall, 1.2, 6.0), ("floor", floor, 1.2, 6.0)):
        if not lo <= v <= hi:
            raise Invalid("out_of_range", "%s %g-%g" % (name, lo, hi))
    stage(dst, "measuring")
    foot = footprint_of(M, man, ext, foot_kind)
    fx0, fy0, fx1, fy1 = foot.bounds()
    if max(fx1 - fx0, fy1 - fy0) + 2 * (play + wall) > 300:
        raise Invalid("too_big")
    if min(fx1 - fx0, fy1 - fy0) < 15:
        raise Invalid("too_small")
    pocket = foot.offset(play, J, 2.0, 32)
    outer = pocket.offset(wall, J, 2.0, 32)
    stage(dst, "cutting")
    dish = outer.extrude(floor + height) - pocket.extrude(height + 1.0).translate([0, 0, floor])
    px0, py0, px1, py1 = pocket.bounds()
    cx, cy = (px0 + px1) / 2, (py0 + py1) / 2
    inner = pocket.offset(-FLOOR_MARGIN, J, 2.0, 24)
    union = lambda shapes: functools.reduce(lambda a, b: a + b, shapes)
    across = lambda w, n_of: [C.square([w, py1 - py0 + 2.0]).translate([cx + i * DRAIN_PITCH - w / 2, py0 - 1.0]) for i in range(-(n_of // 2), n_of // 2 + 1)]
    along = lambda w, n_of: [C.square([px1 - px0 + 2.0, w]).translate([px0 - 1.0, cy + i * DRAIN_PITCH - w / 2]) for i in range(-(n_of // 2), n_of // 2 + 1)]
    drains = 0
    if drain in ("grooves", "grid") and not inner.is_empty():
        slots = across(DRAIN_SLOT, int((px1 - px0 - 2 * FLOOR_MARGIN) / DRAIN_PITCH))
        if drain == "grid":
            slots += along(DRAIN_SLOT, int((py1 - py0 - 2 * FLOOR_MARGIN) / DRAIN_PITCH))
        cut2d = union(slots) ^ inner
        if not cut2d.is_empty():
            dish = dish - cut2d.extrude(floor + 2.0).translate([0, 0, -1.0])
            drains = len(slots)
    elif drain == "ribs":
        # ribs the bar lies on, water runs between them to holes in the four corners of the floor
        ribs = across(RIB_W, int((px1 - px0 - 2.0) / DRAIN_PITCH))
        rib2d = union(ribs) ^ pocket.offset(-0.3, J, 2.0, 24)
        if not rib2d.is_empty():
            dish = dish + rib2d.extrude(RIB_H + 0.5).translate([0, 0, floor - 0.5])
            drains = len(ribs)
        ix0, iy0, ix1, iy1 = inner.bounds()
        for x, y in ((ix0 + 4.0, iy0 + 4.0), (ix1 - 4.0, iy0 + 4.0), (ix0 + 4.0, iy1 - 4.0), (ix1 - 4.0, iy1 - 4.0)):
            hole = C.circle(3.0, 32).translate([x, y])
            if (hole - inner).is_empty():
                dish = dish - hole.extrude(floor + 2.0).translate([0, 0, -1.0])
    if dish.is_empty() or dish.status() != M.Error.NoError:
        raise Invalid("empty_result")
    ox0, oy0, ox1, oy1 = outer.bounds()
    notes.update({"model": [round(v, 1) for v in ext], "foot": foot_kind, "footprint": [round(fx1 - fx0, 1), round(fy1 - fy0, 1)], "pocket": [round(px1 - px0, 1), round(py1 - py0, 1)],
                  "dish": [round(ox1 - ox0, 1), round(oy1 - oy0, 1), round(floor + height, 1)], "clearance": play, "wall": wall, "floor": floor, "height": height, "drain": drain, "drains": drains})
    finish(M, "soap", dst, [("body", dish)], notes, parts_dir)


# ── wearable: a helmet or armour piece scaled to a body girth ─────────────────────────────────────────────────────

STRAP_W, STRAP_T = 25.0, 4.0                  # mm: a slot for a 25 mm strap through a side wall
EYE_LINE = 0.6                                # a window sits at this share of the height unless moved
SIDES = {"front": ("y", 0), "back": ("y", 1), "left": ("x", 0), "right": ("x", 1), "top": ("z", 1)}


def loops_of(cs):
    """A section's outer loops and its holes as [(perimeter, polygon)] each (manifold winds holes clockwise)."""
    outer, holes = [], []
    for poly in cs.to_polygons():
        n = len(poly)
        if n < 3:
            continue
        area = sum(poly[i][0] * poly[(i + 1) % n][1] - poly[(i + 1) % n][0] * poly[i][1] for i in range(n))
        per = sum(math.hypot(poly[(i + 1) % n][0] - poly[i][0], poly[(i + 1) % n][1] - poly[i][1]) for i in range(n))
        (outer if area > 0 else holes).append((per, poly))
    return outer, holes


def girth_level(man, ext, samples=24):
    """The level where the model is widest (a helmet's brow) and that section's outer and inner girths in mm."""
    best = None
    for i in range(samples):
        z = ext[2] * (i + 0.5) / samples
        cs = man.slice(z)
        if cs.is_empty():
            continue
        x0, y0, x1, y1 = cs.bounds()
        width = (x1 - x0) + (y1 - y0)
        if best is None or width > best[0] + 1e-6:
            outer, holes = loops_of(cs)
            best = (width, z, max((p for p, _ in outer), default=0.0), max((p for p, _ in holes), default=0.0))
    if best is None:
        raise Invalid("empty_result")
    return best[1], best[2], best[3]


def wearable_factor(p, man, ext):
    """How much the model grows so its inner girth at the widest level meets the measure plus play."""
    wall = float(p.get("wall", 3.0))
    target = float(p.get("circumference", 560.0)) + float(p.get("play", 10.0))
    z, outer, inner = girth_level(man, ext)
    hollow_already = inner > 0.5 * outer
    inner_now = inner if hollow_already else max(outer - 2 * math.pi * wall, 1.0)
    factor = 1.0 if p.get("measure") == "none" else target / inner_now
    return factor, z, outer, inner, hollow_already, target


def window_cutter(M, w, side, W, D, H):
    """A window's prism: from outside the model to its middle along the side's axis, so only the near wall opens."""
    C = M.CrossSection
    ww, wh = w["w"], w["h"]
    shape = C.square([ww, wh]).translate([-ww / 2, -wh / 2]) if w["shape"] == "rect" else C.circle(0.5, 96).scale([ww, wh])
    axis, end = SIDES[side]
    k = "xyz".index(axis)
    depth = [W, D, H][k] / 2 + 1.0
    cv = H * EYE_LINE + w["dy"]
    if axis == "y":
        prism = shape.extrude(depth).rotate([90, 0, 0]) if end == 0 else shape.extrude(depth).rotate([-90, 0, 0])
        return prism.translate([W / 2 + w["dx"], D / 2, cv])
    if axis == "x":
        prism = shape.rotate(90).extrude(depth).rotate([0, -90, 0]) if end == 0 else shape.rotate(90).extrude(depth).rotate([0, 90, 0])
        return prism.translate([W / 2, D / 2 + w["dx"], cv])
    return shape.extrude(depth).translate([W / 2 + w["dx"], D / 2 + w["dy"], H / 2])


def windows_of(p):
    raw = p.get("windows") or []
    if isinstance(raw, str):
        try:
            raw = json.loads(raw)
        except ValueError:
            raw = []
    out = []
    for w in list(raw)[:4]:
        if not isinstance(w, dict):
            continue
        side = w.get("side", "front")
        shape = w.get("shape", "rect")
        if side not in SIDES or shape not in ("rect", "ellipse"):
            raise Invalid("bad_choice", "window")
        num = lambda key, d, lo, hi: max(lo, min(hi, float(w.get(key, d) if w.get(key) is not None else d)))
        out.append({"side": side, "shape": shape, "w": num("w", 60.0, 5.0, 300.0), "h": num("h", 30.0, 5.0, 300.0), "dx": num("dx", 0.0, -150.0, 150.0), "dy": num("dy", 0.0, -150.0, 150.0)})
    return out


def wearable(src, dst, p, parts_dir):
    import manifold3d as M
    C = M.CrossSection
    notes = {"warnings": []}
    warn = notes["warnings"]
    man, m = load_solid(src, dst, notes)
    ext0 = [float(v) for v in m.extents]
    wall = float(p.get("wall", 3.0))
    if not 2.0 <= wall <= 4.0:
        raise Invalid("out_of_range", "wall 2-4")
    windows = windows_of(p)
    factor, z_girth, outer0, inner0, hollow_already, target = wearable_factor(p, man, ext0)
    if not 0.2 <= factor <= 20:
        raise Invalid("out_of_range", "factor")
    solid, big = scaled(M, man, m, factor)
    ext = [float(v) for v in big.extents]
    if max(ext) > 1000:
        raise Invalid("too_big")
    W, D, H = ext
    notes.update({"factor": round(factor, 4), "model": [round(v, 1) for v in ext0], "scaled": [round(v, 1) for v in ext], "measure": p.get("measure", "head"), "target": round(target, 1), "wall": wall,
                  "girth_at": round(z_girth * factor, 1), "girth_outer": round(outer0 * factor, 1), "already_hollow": hollow_already})
    bed = bed_of(p)
    want_split = bool(p.get("split", True))
    planes = plan(ext, bed, {}) if want_split else {"x": [], "y": [], "z": []}
    # ── the hollow, and the opening underneath so the head goes in ─────────────────────────────────────────────
    hollowed = opened = False
    if bool(p.get("hollow", True)) and not hollow_already:
        before = solid.volume()
        solid = hollow_solid(M, solid, big, {"wall": wall, "no_drain": True}, dst, notes, planes if planes else None)
        hollowed = solid.volume() < before - 1.0
        if hollowed:
            _, holes = loops_of(solid.slice(wall + 1.0))
            if holes:
                # the cavity's outline just above the floor, turned round into outer loops, cut down through the floor
                opening = C([poly[::-1] for _, poly in holes])
                solid = solid - opening.extrude(wall + 2.0).translate([0, 0, -1.0])
                opened = True
            else:
                warn.append("no_opening")
    notes.update({"hollowed": hollowed, "opened": opened})
    # the inner girth as it came out (measured before the windows, which would open the section)
    _, holes = loops_of(solid.slice(min(max(z_girth * factor, 1.0), H - 1.0)))
    notes["girth_inner"] = round(max((pp for pp, _ in holes), default=0.0), 1)
    # ── windows through the near wall, strap slots through both side walls ─────────────────────────────────────
    stage(dst, "cutting")
    for w in windows:
        solid = solid - window_cutter(M, w, w["side"], W, D, H)
    notes["windows"] = windows
    straps = 0
    if bool(p.get("straps", False)):
        zc = H * max(0.1, min(0.9, float(p.get("strap_h", 35.0)) / 100.0))
        for x0 in (-1.0, W / 2):
            solid = solid - M.Manifold.cube([W / 2 + 1.0, STRAP_W, STRAP_T]).translate([x0, D / 2 - STRAP_W / 2, zc - STRAP_T / 2])
        straps = 2
    notes["straps"] = straps
    if solid.is_empty() or solid.status() != M.Error.NoError or solid.volume() < MIN_PIECE:
        raise Invalid("empty_result")
    notes["grams"] = round(solid.volume() / 1000 * PLA_G_CM3, 1)
    if not want_split or (planes is not None and not any(planes.values())):
        notes.update({"pieces": 1, "pins": 0, "keys": 0, "planes": planes or {"x": [], "y": [], "z": []}, "bed": bed, "cells": [1, 1, 1], "map": [], "joints": []})
        finish(M, "wearable", dst, [("body", solid)], notes, parts_dir)
    q = dict(p)
    q.setdefault("joint", "pins")
    q.pop("planes", None)
    laid = split_solid(M, solid, ext, q, dst, notes)
    finish(M, "wearable", dst, laid, notes, parts_dir)


# ── potion ─────────────────────────────────────────────────────────────────────────────────────────────────────────

def potion(src, dst, p, parts_dir):
    import numpy as np
    import shape2d as S
    import manifold3d as M
    notes = {"warnings": []}
    warn = notes["warnings"]
    man, m = load_solid(src, dst, notes)
    ext0 = [float(v) for v in m.extents]
    factor = factor_of(p, ext0) if p.get("height") else 1.0
    solid, big = scaled(M, man, m, factor)
    W, D, H = [float(v) for v in big.extents]
    wall = float(p.get("wall", 2.0))
    if not 1.5 <= wall <= 4.0:
        raise Invalid("out_of_range", "wall 1.5-4")
    neck_d = float(p.get("neck_d", 26.0))
    neck_h = float(p.get("neck_h", 30.0))
    if not 12.0 <= neck_d <= 60.0 or not 10.0 <= neck_h <= 80.0:
        raise Invalid("out_of_range", "neck")
    cut = float(p.get("cut", 4.0)) / 100.0
    if not 0.0 <= cut <= 0.3:
        raise Invalid("out_of_range", "cut 0-30")
    if H < 40:
        raise Invalid("too_small")
    # ── a flat bottom: the lowest `cut` of the height goes ───────────────────────────────────────────────────────
    stage(dst, "cutting")
    z_cut = H * cut
    if z_cut > 0:
        solid = solid.trim_by_plane([0, 0, 1], z_cut).translate([0, 0, -z_cut])
        H -= z_cut
    foot = solid.slice(0.3)
    if foot.area() < 100:
        warn.append("small_foot")
    # ── the neck on top: where the model is highest, the neck's ring sits on it ─────────────────────────────────
    x0, y0, z0, x1, y1, z1 = solid.bounding_box()
    top = solid.slice(z1 - min(3.0, H * 0.05))
    if top.is_empty():
        top = solid.slice(z1 - min(8.0, H * 0.15))
    tx0, ty0, tx1, ty1 = top.bounds() if not top.is_empty() else (x0, y0, x1, y1)
    cx, cy = (tx0 + tx1) / 2, (ty0 + ty1) / 2
    r_in, r_out = neck_d / 2, neck_d / 2 + wall
    # a seat the neck grows out of: a cone from the model's top down into it, so a pointed or narrow top still holds the neck
    seat_h = min(12.0, H * 0.12)
    seat = M.Manifold.cylinder(seat_h + neck_h, r_out + 3.0, r_out, 96).translate([cx, cy, z1 - seat_h])
    neck = M.Manifold.cylinder(neck_h + seat_h, r_out, r_out, 96).translate([cx, cy, z1 - seat_h])
    body = solid + seat + neck
    # ── hollow: the cavity, and the bore of the neck opening into it ────────────────────────────────────────────
    big_body = big
    try:
        import trimesh
        mesh = body.to_mesh()
        big_body = trimesh.Trimesh(np.asarray(mesh.vert_properties)[:, :3], np.asarray(mesh.tri_verts), process=True)
    except Exception:  # noqa: BLE001
        pass
    q = {"wall": wall, "no_drain": True}
    hollow = hollow_solid(M, body, big_body, q, dst, notes)
    bore = M.Manifold.cylinder(neck_h + seat_h + wall + 2.0, r_in, r_in, 96).translate([cx, cy, z1 - seat_h - wall - 1.0])
    hollow = hollow - bore
    if notes.get("hollow", {}).get("cavity_mm3", 0) <= 0:
        warn.append("solid_bottle")
    # ── the cork: a tapered plug with a knob, 0.4 mm of play at the top of the neck ─────────────────────────────
    plug_h = max(8.0, neck_h * 0.7)
    cork = M.Manifold.cylinder(plug_h, r_in - 0.9, r_in - 0.25, 64) + M.Manifold.cylinder(6.0, r_in + 3.0, r_in + 2.0, 64).translate([0, 0, plug_h])
    parts = [("body", hollow), ("cork", cork)]
    # ── a label with the name of the brew: a rounded plate with the text raised on it, glued to the bottle ─────
    text = str(p.get("text") or "").strip()
    if bool(p.get("label", True)) and text and p.get("font"):
        try:
            glyphs, _ = S.text(M, [text[:20]], p["font"], 7.0)
            tw, th = S.size(glyphs)
            lw, lh = min(tw + 8.0, max(30.0, W * 0.7)), th + 8.0
            if tw + 8.0 > lw:
                k = (lw - 8.0) / tw
                glyphs = S.fit(glyphs, width_mm=tw * k)
                tw, th = S.size(glyphs)
                lh = th + 8.0
            plate = S.rounded_rect(M, lw, lh, 3.0).extrude(1.2)
            raised = glyphs.translate([(lw - tw) / 2, (lh - th) / 2]).extrude(0.8).translate([0, 0, 1.2 - 0.01])
            parts.append(("label", plate + raised))
            notes["label"] = {"w": round(lw, 1), "h": round(lh, 1), "text": text[:20]}
        except Exception:  # noqa: BLE001
            warn.append("label_failed")
    laid = lay_out(parts)
    notes.update({"factor": round(factor, 4), "model": [round(v, 1) for v in ext0], "bottle": [round(v, 1) for v in extents_of(hollow)], "neck": {"d": neck_d, "h": neck_h, "x": round(cx - x0, 1), "y": round(cy - y0, 1)},
                  "cut_mm": round(z_cut, 1), "wall": wall, "volume_in_mm3": round(solid.volume(), 1), "cork": {"h": round(plug_h + 6.0, 1)}})
    finish(M, "potion", dst, laid, notes, parts_dir)


# ── flexi cut ──────────────────────────────────────────────────────────────────────────────────────────────────────

def sphere_at(M, r, x, y, z, n=48):
    return M.Manifold.sphere(r, n).translate([x, y, z])


def flexi_cut(src, dst, p, parts_dir):
    import manifold3d as M
    notes = {"warnings": []}
    warn = notes["warnings"]
    man, m = load_solid(src, dst, notes)
    ext = [float(v) for v in m.extents]
    factor = factor_of(p, ext) if p.get("height") else 1.0
    solid, big = scaled(M, man, m, factor)
    ext = [float(v) for v in big.extents]
    axis = p.get("axis") or "auto"
    if axis not in ("auto", "x", "y", "z"):
        raise Invalid("bad_choice", "axis")
    if axis == "auto":
        axis = "xyz"[int(max(range(3), key=lambda i: ext[i]))]
    ai = "xyz".index(axis)
    length = ext[ai]
    ball_d = float(p.get("ball_d", 8.0))
    if not 6.0 <= ball_d <= 10.0:
        raise Invalid("out_of_range", "ball_d 6-10")
    clr = float(p.get("clearance", 0.4))
    if not 0.35 <= clr <= 0.5:
        raise Invalid("out_of_range", "clearance 0.35-0.5")
    gap = float(p.get("gap", FLEXI_GAP))
    segments = int(p.get("segments", 0))
    if segments == 0:
        segments = int(max(3, min(20, round(length / (ball_d * 2.5)))))
    if not 3 <= segments <= 20:
        raise Invalid("out_of_range", "segments 3-20")
    seg_len = length / segments
    r = ball_d / 2
    if seg_len < 2 * r + 2.0:
        raise Invalid("segments_too_short", "%.0f" % (segments * (2 * r + 2.0)))
    cuts = [seg_len * i for i in range(1, segments)]
    stage(dst, "cutting")
    # ── the segments: slabs across the axis, a gap wide apart ────────────────────────────────────────────────────
    def slab(lo, hi):
        size = [ext[0] + 2, ext[1] + 2, ext[2] + 2]
        size[ai] = hi - lo
        at = [-1.0, -1.0, -1.0]
        at[ai] = lo
        return M.Manifold.cube(size).translate(at)
    bounds = [0.0] + cuts + [length]
    segs = []
    for i in range(segments):
        lo = bounds[i] + (gap / 2 if i > 0 else -1.0)
        hi = bounds[i + 1] - (gap / 2 if i < segments - 1 else -1.0)
        piece = solid ^ slab(lo, hi)
        if piece.is_empty() or piece.volume() < MIN_PIECE:
            raise Invalid("empty_result")
        bodies = piece.decompose()
        if len(bodies) > 1:
            piece = max(bodies, key=lambda b: b.volume())
            if "segment_split" not in warn:
                warn.append("segment_split")
        segs.append(piece)
    # ── the joints: a ball on a neck out of one segment, a socket in the next ─────────────────────────────────────
    stage(dst, "joints")
    joints = []
    for i, at in enumerate(cuts):
        section = turned(M, solid, axis).slice(at)
        need = r + clr + 1.2                                  # the socket and a wall round it
        where = spots(M, section, need, 1)
        if not where:
            joints.append({"at": round(at, 2), "ok": False})
            if "joint_no_room" not in warn:
                warn.append("joint_no_room")
            continue
        u, v = where[0]
        # the ball's centre lies 0.7 r beyond the plane, inside the next segment: the socket's opening is narrower than the ball
        depth = 0.7 * r
        cx, cy, cz = to3d(axis, at + gap / 2 + depth, u, v)
        socket = sphere_at(M, r + clr, cx, cy, cz)
        segs[i + 1] = segs[i + 1] - socket
        ball = sphere_at(M, r, cx, cy, cz)
        neck_len = gap + depth + 1.0
        neck = cylinder_along(M, axis, at - gap / 2 + neck_len / 2 - 1.0, u, v, neck_len, 0.5 * r)
        # the neck must not touch the socket's rim: it is thinner than the opening
        segs[i] = segs[i] + ball + neck
        joints.append({"at": round(at, 2), "ok": True, "u": round(u, 1), "v": round(v, 1), "room": round(room_at(section, u, v), 1)})
    pieces = [("segment_%d" % (i + 1), s) for i, s in enumerate(segs)]
    notes.update({"axis": axis, "segments": segments, "ball_d": ball_d, "clearance": clr, "gap": gap, "cuts": [round(c, 2) for c in cuts], "joints": joints,
                  "joined": sum(1 for j in joints if j["ok"]), "factor": round(factor, 4), "model": [round(v, 1) for v in ext], "volume_in_mm3": round(solid.volume(), 1),
                  "cells": [segments if axis == "x" else 1, segments if axis == "y" else 1, segments if axis == "z" else 1]})
    finish(M, "flexi_cut", dst, pieces, notes, parts_dir)


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
            p = json.loads(raw or "{}")
            if p.get("op") == "colors":
                import colors_tool
                try:
                    colors_tool.analyse(argv[2], p)
                except colors_tool.Invalid as e:
                    raise Invalid(e.code, e.detail)
            analyse(argv[2], p)
        src, dst, raw = argv[2], argv[3], argv[4] if len(argv) > 4 else "{}"
        parts_dir = argv[5] if len(argv) > 5 else None
        if raw.startswith("@"):
            with open(raw[1:], encoding="utf-8") as fh:
                raw = fh.read()
        p = json.loads(raw or "{}")
        if not isinstance(p, dict):
            raise Invalid("unknown_kind")
        commands = {"split": split, "hollow": hollow, "scale": scale, "life_size": life_size, "puzzle": puzzle, "holder": holder, "potion": potion, "flexi_cut": flexi_cut, "soap": soap, "wearable": wearable}
        if op == "colors":
            # a coloured 3MF into a part per colour: its own module, the source is the 3MF itself
            import colors_tool
            try:
                colors_tool.colors(src, dst, p, parts_dir, stage)
            except colors_tool.Invalid as e:
                raise Invalid(e.code, e.detail)
        if op not in commands:
            raise Invalid("unknown_kind", op)
        commands[op](src, dst, p, parts_dir)
    except Invalid as e:
        out({"ok": False, "code": e.code, "error": str(e)})
    except SystemExit:
        raise
    except Exception as e:  # noqa: BLE001
        out({"ok": False, "code": "failed", "error": str(e)})


if __name__ == "__main__":
    main(sys.argv)
