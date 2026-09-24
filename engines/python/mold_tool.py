#!/usr/bin/env python3
"""
Two-part casting mold around a printable model (manifold3d, exact solids, seconds even for a million faces).

  mold_tool.py <in.stl> <out.stl> <params-json>

  params: {"wall": 8, "axis": "auto|x|y", "split": "auto"|0..1, "clearance": 0.15, "keys": true, "funnel": true}

The model (mm, Z-up, standing on the bed) is wrapped in a box of `wall` millimetres, the box is cut in two by a vertical
parting plane (normal along `axis`, position `split` as a fraction of that side, auto = the position with the least
undercut), the model plus a pouring funnel through the bottom wall are subtracted from both halves, and three ball keys
on the parting face keep the halves aligned. Both halves are laid side by side, parting face up, ready to print.

Always prints exactly one JSON object on stdout:
  {"ok": true, "axis": "y", "split_mm": 0.0, "undercut_pct": 2.1, "box": [w, d, h], "resin_ml": 12.3, "mold_cm3": 90.1,
   "warnings": ["undercuts"], "triangles": 12345}
"""
import json
import math
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import mesh_tool as T  # noqa: E402  (load, solidify, as_manifold, from_manifold)

AXES = {"x": (1.0, 0.0, 0.0), "y": (0.0, 1.0, 0.0)}


def out(obj):
    print(json.dumps(obj))
    sys.exit(0)


def undercut_area(mesh, axis, c):
    """Surface (mm²) that faces away from its own half's pull direction: the mold cannot be taken off there."""
    import numpy as np
    a = np.asarray(AXES[axis])
    centres = mesh.triangles_center @ a
    facing = mesh.face_normals @ a
    plus = centres > c
    hidden = (plus & (facing < -0.08)) | (~plus & (facing > 0.08))
    return float(mesh.area_faces[hidden].sum())


def best_split(mesh, axis, fraction):
    """Parting plane position along `axis`: the given fraction of the extent, or the least-undercut one of 25 samples."""
    import numpy as np
    lo, hi = float(mesh.bounds[0]["xy".index(axis)]), float(mesh.bounds[1]["xy".index(axis)])
    if fraction is not None:
        c = lo + (hi - lo) * fraction
        return c, undercut_area(mesh, axis, c)
    best, best_key = None, None
    for f in np.linspace(0.2, 0.8, 25):
        c = lo + (hi - lo) * float(f)
        u = undercut_area(mesh, axis, c)
        key = (round(u, 3), abs(float(f) - 0.5))            # equal undercut → the cut nearest the middle
        if best_key is None or key < best_key:
            best, best_key = (c, u), key
    return best


def pour_opening(M, man, z_top, wall):
    """
    Cross-section of the model just above the bed, grown a little, extruded down through the bottom wall and widened
    into a funnel outside. A model that barely touches the bed (a ball) gets a round opening instead.
    """
    cs = None
    for z in (0.6, 1.5, 3.0):
        s = man.slice(z)
        if not s.is_empty() and s.area() > 30:
            cs = s
            break
    if cs is None:
        x0, y0, _, x1, y1, _ = man.bounding_box()
        cs = M.CrossSection.circle(5.0, 48).translate([(x0 + x1) / 2, (y0 + y1) / 2])
    cs = cs.offset(0.8, M.JoinType.Round, 2.0, 16)
    x0, y0, x1, y1 = cs.bounds()
    size = max(x1 - x0, y1 - y0, 1.0)
    flare = min(4.0, wall * 0.45)
    scale = (size + 2 * flare) / size
    cx, cy = (x0 + x1) / 2, (y0 + y1) / 2
    # straight through the wall and a bit into the model, so the two cuts overlap
    neck = cs.extrude(wall + 3.5).translate([0, 0, -wall - 0.5])
    # the flare: wide at the outside face (z = -wall), narrowing to the neck at z = -wall + flare
    cone = M.Manifold.extrude(cs.translate([-cx, -cy]), flare, 1, 0.0, [scale, scale]).scale([1, 1, -1]).translate([cx, cy, -wall + flare])
    return neck + cone, float(cs.area()) * (wall + 0.5)


def print_orientation(half, axis, sign):
    """Rotate so the parting face (normal = sign * axis) points up, then stand on z = 0 at x = 0, y = 0."""
    if axis == "y":
        half = half.rotate([90.0 * sign, 0.0, 0.0])      # +y → +z for sign 1, −y → +z for sign −1
    else:
        half = half.rotate([0.0, -90.0 * sign, 0.0])     # +x → +z for sign 1
    x0, y0, z0, _, _, _ = half.bounding_box()
    return half.translate([-x0, -y0, -z0])


def main():
    if len(sys.argv) < 4:
        out({"ok": False, "error": "usage: mold_tool.py <in.stl> <out.stl> <params-json>"})
    src, dst = sys.argv[1], sys.argv[2]
    try:
        p = json.loads(sys.argv[3]) if sys.argv[3] else {}
    except ValueError:
        p = {}
    wall = max(4.0, min(25.0, float(p.get("wall", 8))))
    clearance = max(0.05, min(0.5, float(p.get("clearance", 0.15))))
    axis_pref = str(p.get("axis", "auto"))
    split = p.get("split", "auto")
    fraction = None if split in ("auto", None, "") else max(0.1, min(0.9, float(split)))
    with_keys = bool(p.get("keys", True))
    with_funnel = bool(p.get("funnel", True))

    import numpy as np
    import trimesh
    import manifold3d as M

    try:
        mesh = T.load(src)
    except Exception as e:  # noqa: BLE001
        out({"ok": False, "error": "unreadable: %s" % e})
    size = float(max(mesh.extents))
    if size < 5:
        out({"ok": False, "error": "too_small"})
    if size > 400:
        out({"ok": False, "error": "too_big"})
    if not mesh.is_watertight or mesh.volume <= 0:
        mesh = T.solidify(mesh, size)
    if not mesh.is_watertight:
        out({"ok": False, "error": "not_watertight"})
    # centred on the bed: the parting plane and the keys are placed around the origin
    lo, hi = mesh.bounds
    mesh.apply_translation([-(lo[0] + hi[0]) / 2, -(lo[1] + hi[1]) / 2, -lo[2]])
    ex, ey, h = (float(v) for v in mesh.extents)

    # parting plane: the axis and position with the least hidden surface
    candidates = ["x", "y"] if axis_pref not in AXES else [axis_pref]
    axis, c, hidden = None, 0.0, None
    for a in candidates:
        cc, u = best_split(mesh, a, fraction)
        if hidden is None or u < hidden:
            axis, c, hidden = a, cc, u
    undercut_pct = 100.0 * hidden / max(float(mesh.area), 1e-6)

    try:
        man = T.as_manifold(mesh)
        if man.is_empty() or man.volume() <= 0:
            raise ValueError("empty solid")
        if man.num_tri() > 400000:
            man = man.simplify(0.03)
    except Exception as e:  # noqa: BLE001
        out({"ok": False, "error": "solid: %s" % e})

    box = M.Manifold.cube([ex + 2 * wall, ey + 2 * wall, h + 2 * wall]).translate([-ex / 2 - wall, -ey / 2 - wall, -wall])
    cavity = man
    resin_mm3 = float(man.volume())
    if with_funnel:
        funnel, extra = pour_opening(M, man, h, wall)
        cavity = cavity + funnel
        resin_mm3 += extra

    n = AXES[axis]
    neg = tuple(-v for v in n)
    half_a = box.trim_by_plane(neg, -c) - cavity        # the side axis·p ≤ c, parting face normal +axis
    half_b = box.trim_by_plane(n, c) - cavity           # the side axis·p ≥ c, parting face normal −axis

    keys = 0
    if with_keys:
        r = max(2.0, min(4.0, wall * 0.35))
        # three balls on the parting face inside the wall: left, right and top of the model; none near the pour opening
        if axis == "y":
            spots = [(-ex / 2 - wall / 2, c, h / 2), (ex / 2 + wall / 2, c, h / 2), (0.0, c, h + wall / 2)]
        else:
            spots = [(c, -ey / 2 - wall / 2, h / 2), (c, ey / 2 + wall / 2, h / 2), (c, 0.0, h + wall / 2)]
        if r * 2 + 1.0 <= wall:
            balls = M.Manifold.batch_boolean([M.Manifold.sphere(r, 32).translate(list(s)) for s in spots], M.OpType.Add)
            sockets = M.Manifold.batch_boolean([M.Manifold.sphere(r + clearance, 32).translate(list(s)) for s in spots], M.OpType.Add)
            # the half of each ball inside A is already A; the other half stands out of the parting face
            half_a = half_a + balls
            half_b = half_b - sockets
            keys = len(spots)

    a_print = print_orientation(half_a, axis, 1)
    b_print = print_orientation(half_b, axis, -1)
    ax1 = a_print.bounding_box()[3]
    plate = M.Manifold.compose([a_print, b_print.translate([ax1 + 10.0, 0.0, 0.0])])
    if plate.is_empty():
        out({"ok": False, "error": "empty_result"})
    result = T.from_manifold(plate)
    if not len(result.faces):
        out({"ok": False, "error": "empty_result"})
    result.export(dst, file_type="stl")

    warnings = []
    if undercut_pct > 3.0:
        warnings.append("undercuts")
    if h > 150 or max(ex, ey) > 150:
        warnings.append("large_mold")
    bx0, by0, bz0, bx1, by1, bz1 = plate.bounding_box()
    out({
        "ok": True,
        "axis": axis,
        "split_mm": round(c, 2),
        "undercut_pct": round(undercut_pct, 1),
        "box": [round(ex + 2 * wall, 1), round(ey + 2 * wall, 1), round(h + 2 * wall, 1)],
        "plate": [round(bx1 - bx0, 1), round(by1 - by0, 1), round(bz1 - bz0, 1)],
        "resin_ml": round(resin_mm3 / 1000.0, 1),
        "mold_cm3": round((float(half_a.volume()) + float(half_b.volume())) / 1000.0, 1),
        "keys": keys,
        "wall": wall,
        "warnings": warnings,
        "triangles": int(len(result.faces)),
    })


if __name__ == "__main__":
    try:
        main()
    except SystemExit:
        raise
    except Exception as e:  # noqa: BLE001
        out({"ok": False, "error": str(e)})
