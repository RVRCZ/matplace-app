#!/usr/bin/env python3
"""
Casting molds around a printable model (manifold3d, exact solids).

  mold_tool.py <in.stl> <out.stl> <params-json>

  params: {"type": "rigid|silicone", "wall": 8, "axis": "auto|x|y|z", "split": "auto"|0..1, "clearance": 0.15,
           "keys": true, "funnel": true}

rigid     The model (mm, Z-up, standing on the bed) is wrapped in a box of `wall` millimetres and the box is cut in two
          by a parting plane. The model plus a pouring funnel through the bottom wall are taken out of both halves,
          three ball keys on the parting face keep them aligned. Both halves are laid side by side, parting face up.
          A hard mold lets go only of what it can see along the direction it is pulled off: the tool measures how much
          of the surface hides behind something else (`undercut_pct`), for twelve upright parting planes and a level
          one, and takes the best. The verdict says what such a mold is good for:
            rigid     comes apart as printed
            flexible  small undercuts: print it from TPU, or accept marks on the casting
            silicone  the casting would stay locked in: make a silicone mold instead
silicone  For shapes no hard mold lets go of. Two printed parts: a base with the model standing on it (the master) and
          a sleeve that sits in a groove of the base. Silicone is poured over the master up to the rim; when it has set,
          the sleeve is lifted off and the soft mold is peeled from the master, cut open along one side where needed.
          The opening the master's foot leaves is where the casting is poured in.

Always prints exactly one JSON object on stdout:
  {"ok": true, "type": "rigid", "axis": "y", "angle_deg": 90, "split_mm": 0.0, "undercut_pct": 2.1, "verdict": "rigid",
   "box": [w, d, h], "resin_ml": 12.3, "mold_cm3": 90.1, "warnings": ["undercuts"], "triangles": 12345}
"""
import json
import math
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import mesh_tool as T  # noqa: E402  (load, solidify, as_manifold, from_manifold)

FLEXIBLE_FROM = 3.0        # per cent of the surface hidden from the pull: below it a hard mold comes apart
SILICONE_FROM = 10.0       # above it not even a flexible printed mold lets go
SLEEVE = 2.4               # printed wall of the silicone sleeve
BASE = 3.0                 # printed base under the master
GROOVE = 1.5               # how deep the sleeve sits in the base


def out(obj):
    print(json.dumps(obj))
    sys.exit(0)


class Surface:
    """Points spread evenly over the model: what a mold half sees of them decides whether it comes off."""

    def __init__(self, mesh):
        import numpy as np
        import trimesh
        size = float(max(mesh.extents))
        self.cell = min(1.0, max(0.3, size / 250.0))
        count = int(min(1_500_000, max(200_000, mesh.area / (self.cell * self.cell) * 5)))
        np.random.seed(7)                                   # the same model gets the same answer every time
        self.points, faces = trimesh.sample.sample_surface(mesh, count)
        self.normals = mesh.face_normals[faces]
        self.corners = np.asarray(mesh.vertices)

    def hidden(self, d, fractions):
        """
        Pull direction d (both halves go apart along it). Returns (hidden per cent of the surface, plane position) for
        the best of the given plane positions. A point is seen by its half when it looks towards the pull and nothing
        of the model lies further out in its column; walls that run along the pull (no draft) count as seen.
        """
        import numpy as np
        d = np.asarray(d, dtype=float)
        if abs(d[2]) > 0.9:
            u, v = np.array([1.0, 0.0, 0.0]), np.array([0.0, 1.0, 0.0])
        else:
            u, v = np.array([-d[1], d[0], 0.0]), np.array([0.0, 0.0, 1.0])
        every = np.vstack([self.points, self.corners])
        pu, pv, pa = every @ u, every @ v, every @ d
        iu = np.floor((pu - pu.min()) / self.cell).astype(np.int64)
        iv = np.floor((pv - pv.min()) / self.cell).astype(np.int64)
        col = iu + iv * (int(iu.max()) + 1)
        hi = np.full(int(col.max()) + 1, -np.inf)
        lo = np.full(int(col.max()) + 1, np.inf)
        np.maximum.at(hi, col, pa)
        np.minimum.at(lo, col, pa)
        k = len(self.points)
        a, col = pa[:k], col[:k]
        f = self.normals @ d
        # a sloping surface rises inside its own column: that much may lie above a point and it is still the outer one
        slack = self.cell * np.sqrt(np.clip(1.0 - f * f, 0.0, 1.0)) / np.maximum(np.abs(f), 0.05) + 0.05
        top = (f > 0) & (a >= hi[col] - slack)
        bottom = (f < 0) & (a <= lo[col] + slack)
        upright = np.abs(f) < 0.03
        a0, a1 = float(pa.min()), float(pa.max())
        best = None
        for fr in fractions:
            c = a0 + (a1 - a0) * float(fr)
            seen = (top & (a >= c)) | (bottom & (a <= c)) | upright
            pct = 100.0 * (1.0 - float(seen.mean()))
            key = (round(pct, 1), abs(float(fr) - 0.5))      # equal → the cut nearest the middle
            if best is None or key < best[0]:
                best = (key, pct, c)
        return best[1], best[2]


def choose(mesh, axis_pref, fraction):
    """The pull direction and plane with the least hidden surface: (name, angle in degrees or None, plane, per cent)."""
    import numpy as np
    surface = Surface(mesh)
    upright = [fraction] if fraction is not None else list(np.linspace(0.2, 0.8, 25))
    level = [fraction] if fraction is not None else list(np.linspace(0.0, 0.8, 33))
    tries = []
    if axis_pref == "x":
        tries = [("x", 0.0)]
    elif axis_pref == "y":
        tries = [("y", 90.0)]
    elif axis_pref == "z":
        tries = [("z", None)]
    else:
        # the two plain ones first: with equal results a mold that follows the model's own sides is the one to take
        tries = [("y", 90.0), ("x", 0.0), ("z", None)] + [("angle", 15.0 * k) for k in range(1, 12) if k != 6]
    best = None
    for name, angle in tries:
        if angle is None:
            pct, c = surface.hidden((0.0, 0.0, 1.0), level)
        else:
            r = math.radians(angle)
            pct, c = surface.hidden((math.cos(r), math.sin(r), 0.0), upright)
        if best is None or round(pct, 1) < round(best[3], 1):
            best = (name, angle, c, pct)
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
    elif axis == "z":
        if sign < 0:
            half = half.rotate([180.0, 0.0, 0.0])
    else:
        half = half.rotate([0.0, -90.0 * sign, 0.0])     # +x → +z for sign 1
    x0, y0, z0, _, _, _ = half.bounding_box()
    return half.translate([-x0, -y0, -z0])


def silicone(M, man, ex, ey, h, wall):
    """The base with the master on it and the sleeve beside it; (plate, report)."""
    J = M.JoinType.Round
    # the sleeve follows the outline of the model seen from above, `wall` away from it; hollows of the outline are
    # bridged (silicone costs more than the print, but a narrow bay would be hard to fill and to peel)
    bridge = max(8.0, wall)
    inner = man.project().offset(wall + bridge, J, 2.0, 48).offset(-bridge, J, 2.0, 48).simplify(0.1)
    ih = h + wall                                                              # silicone stands this high
    rim, fit = 4.0, 0.2
    outer = inner.offset(SLEEVE, J, 2.0, 48).simplify(0.05)
    base = inner.offset(SLEEVE + rim, J, 2.0, 48).simplify(0.05).extrude(BASE).translate([0, 0, -BASE + 0.01])
    groove = (inner.offset(SLEEVE + fit, J, 2.0, 48) - inner.offset(-fit, J, 2.0, 48)).simplify(0.05).extrude(GROOVE + 1.0).translate([0, 0, -GROOVE])
    master = (base - groove) + man                                             # the master's foot sinks 0.01 mm into the base
    foot = man.slice(0.6)
    if foot.is_empty() or foot.area() < 30:
        # a ball touches the base in one point: a short neck holds it and leaves an opening to pour through
        x0, y0, _, x1, y1, _ = man.bounding_box()
        master = master + M.Manifold.cylinder(h * 0.25 + 0.01, 5.0, 5.0, 48).translate([(x0 + x1) / 2, (y0 + y1) / 2, -0.01])
    sleeve = (outer - inner).extrude(ih + GROOVE)
    x0, y0, z0, x1, y1, _ = master.bounding_box()
    master = master.translate([-x0, -y0, -z0])
    sx0, sy0, _, sx1, sy1, _ = sleeve.bounding_box()
    sleeve = sleeve.translate([-sx0 + (x1 - x0) + 10.0, -sy0 + rim, 0.0])
    report = {
        "box": [round(x1 - x0, 1), round(y1 - y0, 1), round(ih + BASE, 1)],
        "sleeve": [round(sx1 - sx0, 1), round(sy1 - sy0, 1), round(ih + GROOVE, 1)],
        "silicone_ml": round((float(inner.area()) * ih - float(man.volume())) / 1000.0, 1),
        "resin_ml": round(float(man.volume()) / 1000.0, 1),
        "mold_cm3": round((float(master.volume()) + float(sleeve.volume())) / 1000.0, 1),
        "keys": 0,
    }
    return M.Manifold.compose([master, sleeve]), report


def main():
    if len(sys.argv) < 4:
        out({"ok": False, "error": "usage: mold_tool.py <in.stl> <out.stl> <params-json>"})
    src, dst = sys.argv[1], sys.argv[2]
    try:
        p = json.loads(sys.argv[3]) if sys.argv[3] else {}
    except ValueError:
        p = {}
    kind = "silicone" if str(p.get("type", "rigid")) == "silicone" else "rigid"
    wall = max(4.0, min(25.0, float(p.get("wall", 8))))
    clearance = max(0.05, min(0.5, float(p.get("clearance", 0.15))))
    axis_pref = str(p.get("axis", "auto"))
    split = p.get("split", "auto")
    fraction = None if split in ("auto", None, "") else max(0.1, min(0.9, float(split)))
    with_keys = bool(p.get("keys", True))
    with_funnel = bool(p.get("funnel", True))

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

    # what a hard two-part mold would do with this shape: measured for both types, the silicone one reports it too
    name, angle, c, hidden = choose(mesh, axis_pref if kind == "rigid" else "auto", fraction if kind == "rigid" else None)
    verdict = "rigid" if hidden <= FLEXIBLE_FROM else ("flexible" if hidden <= SILICONE_FROM else "silicone")
    axis = "z" if name == "z" else "y"
    if kind == "rigid" and angle is not None and abs(angle - 90.0) > 1e-6:
        # the mold is built with the pull along y: the model is turned about the origin so that the chosen direction
        # becomes y (the plane keeps its distance from the origin), then centred again
        mesh.apply_transform(trimesh.transformations.rotation_matrix(math.radians(90.0 - angle), [0, 0, 1]))
        lo, hi = mesh.bounds
        shift = [-(lo[0] + hi[0]) / 2, -(lo[1] + hi[1]) / 2, 0.0]
        mesh.apply_translation(shift)
        c += shift[1]
    ex, ey, h = (float(v) for v in mesh.extents)

    try:
        man = T.as_manifold(mesh)
        if man.is_empty() or man.volume() <= 0:
            raise ValueError("empty solid")
        if man.num_tri() > 400000:
            man = man.simplify(0.03)
    except Exception as e:  # noqa: BLE001
        out({"ok": False, "error": "solid: %s" % e})

    warnings = []
    if kind == "silicone":
        plate, report = silicone(M, man, ex, ey, h, wall)
    else:
        box = M.Manifold.cube([ex + 2 * wall, ey + 2 * wall, h + 2 * wall]).translate([-ex / 2 - wall, -ey / 2 - wall, -wall])
        cavity = man
        resin_mm3 = float(man.volume())
        if with_funnel:
            funnel, extra = pour_opening(M, man, h, wall)
            cavity = cavity + funnel
            resin_mm3 += extra

        n = (0.0, 0.0, 1.0) if axis == "z" else (0.0, 1.0, 0.0)
        neg = tuple(-v for v in n)
        half_a = box.trim_by_plane(neg, -c) - cavity        # the side axis·p ≤ c, parting face normal +axis
        half_b = box.trim_by_plane(n, c) - cavity           # the side axis·p ≥ c, parting face normal −axis

        keys = 0
        if with_keys:
            r = max(2.0, min(4.0, wall * 0.35))
            # three balls on the parting face inside the wall, none near the pour opening
            if axis == "y":
                spots = [(-ex / 2 - wall / 2, c, h / 2), (ex / 2 + wall / 2, c, h / 2), (0.0, c, h + wall / 2)]
            else:
                spots = [(-ex / 2 - wall / 2, 0.0, c), (ex / 2 + wall / 2, 0.0, c), (0.0, ey / 2 + wall / 2, c)]
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
        report = {
            "box": [round(ex + 2 * wall, 1), round(ey + 2 * wall, 1), round(h + 2 * wall, 1)],
            "resin_ml": round(resin_mm3 / 1000.0, 1),
            "mold_cm3": round((float(half_a.volume()) + float(half_b.volume())) / 1000.0, 1),
            "keys": keys,
        }
        if verdict != "rigid":
            warnings.append("undercuts")

    if plate.is_empty():
        out({"ok": False, "error": "empty_result"})
    result = T.from_manifold(plate)
    if not len(result.faces):
        out({"ok": False, "error": "empty_result"})
    result.export(dst, file_type="stl")

    if h > 150 or max(ex, ey) > 150:
        warnings.append("large_mold")
    bx0, by0, bz0, bx1, by1, bz1 = plate.bounding_box()
    report.update({
        "ok": True,
        "type": kind,
        "axis": name if name != "angle" else "angle",
        "angle_deg": None if angle is None else round(angle),
        "split_mm": round(c, 2),
        "undercut_pct": round(hidden, 1) if hidden >= 0.5 else 0,
        "verdict": verdict,
        "plate": [round(bx1 - bx0, 1), round(by1 - by0, 1), round(bz1 - bz0, 1)],
        "wall": wall,
        "warnings": warnings,
        "triangles": int(len(result.faces)),
    })
    out(report)


if __name__ == "__main__":
    try:
        main()
    except SystemExit:
        raise
    except Exception as e:  # noqa: BLE001
        out({"ok": False, "error": str(e)})
