#!/usr/bin/env python3
"""
Casting molds around a printable model (manifold3d, exact solids).

  mold_tool.py <in.stl> <out.stl> <params-json>              builds the mold
  mold_tool.py analyse <in.stl> <faces.bin> <params-json>    only measures, and marks every triangle of <in.stl>

  params: {"type": "rigid|silicone", "parts": 2|3|4, "fill": false, "cast": "<path of cast.stl>", "wall": 8,
           "axis": "auto|x|y|z", "split": "auto"|0..1, "clearance": 0.15, "keys": true, "funnel": true}

rigid     The model (mm, Z-up, standing on the bed) is wrapped in a box of `wall` millimetres and the box is cut into
          pieces. The model plus a pouring funnel through the bottom wall are taken out of them, keys on the parting
          faces keep them aligned. The pieces are laid on the plate the way they print.
            2 parts   one parting plane: twelve upright ones and a level one are tried, ball keys
            3, 4      wedges round an upright axis, every wedge is pulled off along its middle; low cone keys, which
                      let go in every direction a neighbour may leave
          A hard mold lets go only of what it can see along the direction it is pulled off: the tool measures how much
          of the surface hides behind something else (`undercut_pct`) and takes the division with the least. The
          verdict says what such a mold is good for:
            rigid     comes apart as printed
            flexible  small undercuts: print it from TPU, or accept marks on the casting
            silicone  the casting would stay locked in: fill the undercuts or make a silicone mold
          fill      the hollows a piece cannot leave are filled in the cavity (everything between the hidden surface
                    and what the piece sees further out), so the mold does come apart; the casting then differs from
                    the model there. The changed shape is written to `cast` (STL) with `cast`.bin beside it: one byte
                    per triangle, 1 = added material.
silicone  For shapes no hard mold lets go of. Two printed parts: a base with the model standing on it (the master) and
          a sleeve that sits in a groove of the base. Silicone is poured over the master up to the rim; when it has set,
          the sleeve is lifted off and the soft mold is peeled from the master, cut open along one side where needed.
          The opening the master's foot leaves is where the casting is poured in.

analyse   <faces.bin> gets one byte per triangle of <in.stl>, in the order of the file: the piece it belongs to (0..3),
          plus 8 when no piece sees it. The answer lists the hidden share for 2, 3 and 4 parts (`options`).

Always prints exactly one JSON object on stdout:
  {"ok": true, "type": "rigid", "parts": 2, "axis": "y", "angle_deg": 90, "split_mm": 0.0, "undercut_pct": 2.1,
   "verdict": "rigid", "box": [w, d, h], "resin_ml": 12.3, "mold_cm3": 90.1, "warnings": ["undercuts"], "triangles": 12345}
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
PASSES = 1                 # how many times the filling is repeated on its own result (one finds nearly all)
BED = 240.0                # pieces are laid out in rows no wider than this


def out(obj):
    print(json.dumps(obj))
    sys.exit(0)


def basis(d):
    """Two directions across the pull d; (u, v, d) is right-handed."""
    import numpy as np
    d = np.asarray(d, dtype=float)
    if abs(d[2]) > 0.9:
        return np.array([1.0, 0.0, 0.0]), np.array([0.0, 1.0 if d[2] > 0 else -1.0, 0.0])
    return np.array([-d[1], d[0], 0.0]), np.array([0.0, 0.0, 1.0])


def heading(deg):
    r = math.radians(deg)
    return (math.cos(r), math.sin(r), 0.0)


class Surface:
    """Points spread evenly over the model: what a mold piece sees of them decides whether it comes off."""

    def __init__(self, mesh):
        import numpy as np
        import trimesh
        size = float(max(mesh.extents))
        self.cell = min(1.0, max(0.3, size / 250.0))
        count = int(min(1_500_000, max(200_000, mesh.area / (self.cell * self.cell) * 5)))
        np.random.seed(7)                                   # the same model gets the same answer every time
        self.points, faces = trimesh.sample.sample_surface(mesh, count)
        self.points = np.asarray(self.points)
        self.normals = np.asarray(mesh.face_normals[faces])
        self.corners = np.asarray(mesh.vertices).copy()
        self.grids = {}

    def move(self, matrix):
        """The same turn and shift the model got."""
        import numpy as np
        m = np.asarray(matrix, dtype=float)
        self.points = self.points @ m[:3, :3].T + m[:3, 3]
        self.corners = self.corners @ m[:3, :3].T + m[:3, 3]
        self.normals = self.normals @ m[:3, :3].T
        self.grids = {}

    def grid(self, d):
        """Columns along d: how far out the model reaches in each of them."""
        import numpy as np
        key = tuple(round(float(x), 4) for x in d)
        if key not in self.grids:
            dd = np.asarray(d, dtype=float)
            u, v = basis(dd)
            every = np.vstack([self.points, self.corners])
            pu, pv, pa = every @ u, every @ v, every @ dd
            u0, v0 = float(pu.min()), float(pv.min())
            iu = np.floor((pu - u0) / self.cell).astype(np.int64)
            iv = np.floor((pv - v0) / self.cell).astype(np.int64)
            w, h = int(iu.max()) + 1, int(iv.max()) + 1
            hi = np.full(w * h, -np.inf)
            np.maximum.at(hi, iu + iv * w, pa)
            self.grids[key] = (dd, u, v, u0, v0, w, h, hi)
        return self.grids[key]

    def released(self, d, points=None, normals=None):
        """
        True for every point a piece pulled along d lets go of: the point looks towards the pull and nothing of the
        model lies further out in its column. Walls that run along the pull (no draft) count as let go.
        """
        import numpy as np
        dd, u, v, u0, v0, w, h, hi = self.grid(d)
        if points is None:
            points, normals = self.points, self.normals
        if not len(points):
            return np.zeros(0, dtype=bool)
        iu = np.clip(np.floor((points @ u - u0) / self.cell).astype(np.int64), 0, w - 1)
        iv = np.clip(np.floor((points @ v - v0) / self.cell).astype(np.int64), 0, h - 1)
        a = points @ dd
        f = normals @ dd
        # a sloping surface rises inside its own column: that much may lie above a point and it is still the outer one
        slack = self.cell * np.sqrt(np.clip(1.0 - f * f, 0.0, 1.0)) / np.maximum(np.abs(f), 0.05) + 0.05
        return ((f > 0) & (a >= hi[iu + iv * w] - slack)) | (np.abs(f) < 0.03)


class Split:
    """How the mold is divided: one plane (2 parts) or wedges round an upright axis (3 or 4)."""

    def __init__(self, parts, name="y", angle=90.0, c=0.0, theta=0.0, cx=0.0, cy=0.0, pct=0.0):
        self.parts, self.name, self.angle, self.c = parts, name, angle, c
        self.theta, self.cx, self.cy, self.pct = theta, cx, cy, pct

    def normal(self):
        return (0.0, 0.0, 1.0) if self.angle is None else heading(self.angle)

    def pulls(self):
        if self.parts == 2:
            n = self.normal()
            return [tuple(-x for x in n), n]
        step = 360.0 / self.parts
        return [heading(self.theta + (k + 0.5) * step) for k in range(self.parts)]

    def part_of(self, points, normals=None):
        import numpy as np
        if normals is not None:
            # the mold lies just outside the surface: a face on a parting plane belongs to the piece that touches it
            points = points + 0.05 * normals
        if self.parts == 2:
            return (points @ np.asarray(self.normal()) >= self.c).astype(np.int64)
        ang = (np.degrees(np.arctan2(points[:, 1] - self.cy, points[:, 0] - self.cx)) - self.theta) % 360.0
        return np.minimum((ang / (360.0 / self.parts)).astype(np.int64), self.parts - 1)

    def planes(self, k):
        """The piece k as half-spaces: [(normal, offset)], inside is normal·p >= offset."""
        if self.parts == 2:
            n = self.normal()
            return [(n, self.c)] if k == 1 else [(tuple(-x for x in n), -self.c)]
        step = 360.0 / self.parts
        a0, a1 = math.radians(self.theta + k * step), math.radians(self.theta + (k + 1) * step)
        na = (-math.sin(a0), math.cos(a0), 0.0)
        nb = (math.sin(a1), -math.cos(a1), 0.0)
        return [(na, na[0] * self.cx + na[1] * self.cy), (nb, nb[0] * self.cx + nb[1] * self.cy)]

    def seen(self, surface, points=None, normals=None):
        """For every point: does the piece it belongs to let go of it."""
        import numpy as np
        if points is None:
            points, normals = surface.points, surface.normals
        part = self.part_of(points, normals)
        seen = np.zeros(len(points), dtype=bool)
        for k, d in enumerate(self.pulls()):
            m = part == k
            if m.any():
                seen[m] = surface.released(d, points[m], normals[m])
        return part, seen

    def move(self, deg, shift):
        """The model was turned by deg about the upright axis through the origin and then shifted."""
        r = math.radians(deg)
        if self.parts == 2:
            if self.angle is None:
                self.c += shift[2]
            else:
                self.angle = (self.angle + deg) % 360.0
                n = self.normal()
                self.c += n[0] * shift[0] + n[1] * shift[1]
        else:
            x, y = self.cx, self.cy
            self.cx = x * math.cos(r) - y * math.sin(r) + shift[0]
            self.cy = x * math.sin(r) + y * math.cos(r) + shift[1]
            self.theta = (self.theta + deg) % 360.0


def choose(surface, parts, axis_pref="auto", fraction=None):
    """The division into `parts` pieces with the least hidden surface."""
    import numpy as np
    step = max(1, len(surface.points) // 300_000)           # the search looks at a part of the points, the result at all
    P, N = surface.points[::step], surface.normals[::step]
    best = None
    if parts == 2:
        upright = [fraction] if fraction is not None else list(np.linspace(0.2, 0.8, 25))
        level = [fraction] if fraction is not None else list(np.linspace(0.0, 0.8, 33))
        if axis_pref == "x":
            tries = [("x", 0.0)]
        elif axis_pref == "y":
            tries = [("y", 90.0)]
        elif axis_pref == "z":
            tries = [("z", None)]
        else:
            # the two plain ones first: with equal results a mold that follows the model's own sides is the one to take
            tries = [("y", 90.0), ("x", 0.0), ("z", None)] + [("angle", 15.0 * k) for k in range(1, 12) if k != 6]
        for name, angle in tries:
            d = (0.0, 0.0, 1.0) if angle is None else heading(angle)
            plus, minus = surface.released(d, P, N), surface.released(tuple(-x for x in d), P, N)
            a = P @ np.asarray(d)
            a0, a1 = float(a.min()), float(a.max())
            for fr in (level if angle is None else upright):
                c = a0 + (a1 - a0) * float(fr)
                pct = 100.0 * (1.0 - float(np.where(a >= c, plus, minus).mean()))
                key = (round(pct, 1), abs(float(fr) - 0.5))
                # a later direction has to be better by a tenth of a per cent, not just nearer the middle
                same = best is not None and (best[1].name, best[1].angle) == (name, angle)
                if best is None or key[0] < best[0][0] or (same and key < best[0]):
                    best = (key, Split(2, name, angle, c))
    else:
        wedge = 360.0 / parts
        lo, hi = surface.corners.min(axis=0), surface.corners.max(axis=0)
        mid, ext = (lo + hi) / 2, hi - lo
        let = {}
        for theta in np.arange(0.0, wedge - 1e-6, 15.0):
            for k in range(parts):
                deg = round(float(theta + (k + 0.5) * wedge), 3) % 360.0
                if deg not in let:
                    let[deg] = surface.released(heading(deg), P, N)
        for ox in (0.0, -0.15, 0.15):
            for oy in (0.0, -0.15, 0.15):
                cx, cy = float(mid[0] + ox * ext[0]), float(mid[1] + oy * ext[1])
                around = np.degrees(np.arctan2(P[:, 1] - cy, P[:, 0] - cx))
                for theta in np.arange(0.0, wedge - 1e-6, 15.0):
                    part = np.minimum((((around - theta) % 360.0) / wedge).astype(np.int64), parts - 1)
                    seen = np.zeros(len(P), dtype=bool)
                    for k in range(parts):
                        seen |= (part == k) & let[round(float(theta + (k + 0.5) * wedge), 3) % 360.0]
                    pct = 100.0 * (1.0 - float(seen.mean()))
                    key = (round(pct, 1), abs(ox) + abs(oy), float(theta))
                    if best is None or key < best[0]:
                        best = (key, Split(parts, "wedges", None, 0.0, float(theta), cx, cy))
    split = best[1]
    _, seen = split.seen(surface)
    split.pct = 100.0 * (1.0 - float(seen.mean()))
    return split


def verdict_of(pct):
    return "rigid" if pct <= FLEXIBLE_FROM else ("flexible" if pct <= SILICONE_FROM else "silicone")


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


def on_plate(piece):
    x0, y0, z0, _, _, _ = piece.bounding_box()
    return piece.translate([-x0, -y0, -z0])


def lay_open(piece, pull):
    """Lay a piece on the outer wall it is pulled off by: the cavity and the parting faces look up."""
    dx, dy, dz = pull
    if abs(dz) > 0.9:
        piece = piece if dz < 0 else piece.rotate([180.0, 0.0, 0.0])
    elif abs(dx) > abs(dy) + 1e-6:
        piece = piece.rotate([0.0, 90.0 if dx > 0 else -90.0, 0.0])
    else:
        piece = piece.rotate([-90.0 if dy > 0 else 90.0, 0.0, 0.0])
    return on_plate(piece)


def lay_out(M, pieces):
    """Rows no wider than a common bed, 10 mm between the pieces."""
    placed, x, y, row = [], 0.0, 0.0, 0.0
    for p in pieces:
        _, _, _, w, d, _ = p.bounding_box()
        if x > 0 and x + w > BED:
            x, y, row = 0.0, y + row + 10.0, 0.0
        placed.append(p.translate([x, y, 0.0]))
        x += w + 10.0
        row = max(row, d)
    return M.Manifold.compose(placed)


def trimmed(solid, planes):
    for n, off in planes:
        solid = solid.trim_by_plane(tuple(float(x) for x in n), float(off))
    return solid


def swept(M, tris, base):
    """
    Triangles (n, 3, 3) in (u, v, a), all looking towards +a, each pulled straight down to a = base: the union of the
    prisms is everything that lies under that surface. Exact at steps and edges, and never higher than the surface.
    """
    import numpy as np
    order = np.array([[0, 1, 2], [3, 5, 4], [0, 3, 4], [0, 4, 1], [1, 4, 5], [1, 5, 2], [2, 5, 3], [2, 3, 0]], dtype=np.uint32)
    low = tris.copy()
    low[:, :, 2] = base
    six = np.concatenate([tris, low], axis=1).astype(np.float32)               # (n, 6, 3)
    solids = [M.Manifold(M.Mesh(vert_properties=six[i], tri_verts=order)) for i in range(len(six))]
    solids = [x for x in solids if x.status() == M.Error.NoError and not x.is_empty()]
    # joined in groups first: the library joins a few thousand small bodies much faster than one long list
    while len(solids) > 1:
        solids = [M.Manifold.batch_boolean(solids[i:i + 2000], M.OpType.Add) for i in range(0, len(solids), 2000)]
    return solids[0] if solids else M.Manifold()


def fill_undercuts(M, man, surface, split):
    """
    The model plus everything a piece could not leave. For every piece: the columns (along its pull) that hold hidden
    surface are found, and every triangle of the piece over them that looks towards the pull is swept back to the
    parting faces. What is added lies under the outer surface the piece sees, so nothing of the model is covered that
    could be seen before. Returns (cast, number of pieces changed).
    """
    import numpy as np
    from scipy import ndimage
    part, seen = split.seen(surface)
    cast, changed = man, 0
    for k, d in enumerate(split.pulls()):
        mine = part == k
        if not mine.any() or seen[mine].all():
            continue
        dd = np.asarray(d, dtype=float)
        u, v = basis(dd)
        pts = surface.points[mine]
        pu, pv, pa = pts @ u, pts @ v, pts @ dd
        cell = surface.cell
        u0, v0 = float(pu.min()) - 2 * cell, float(pv.min()) - 2 * cell
        iu = np.floor((pu - u0) / cell).astype(np.int64)
        iv = np.floor((pv - v0) / cell).astype(np.int64)
        nu, nv = int(iu.max()) + 3, int(iv.max()) + 3
        hidden = ~seen[mine]
        locked = np.zeros(nu * nv, dtype=bool)
        locked[(iu + iv * nu)[hidden]] = True
        locked = ndimage.binary_dilation(locked.reshape(nv, nu), iterations=1)
        piece = trimmed(man, split.planes(k))
        if piece.is_empty():
            continue
        verts, faces = _arrays(piece)
        tri = verts[faces]
        normal = np.cross(tri[:, 1] - tri[:, 0], tri[:, 2] - tri[:, 0])
        size = np.linalg.norm(normal, axis=1)
        facing = (normal @ dd) / np.maximum(size, 1e-12)
        tri = tri[(facing > 0.03) & (size > 1e-6)]
        # a hair under the surface: where the model is seen, it is the model's own surface that stays outside
        tri = np.stack([tri @ u, tri @ v, tri @ dd - 0.03], axis=2)
        # only the triangles over a locked column: the box of the triangle on the grid touches one
        i0 = np.clip(np.floor((tri[:, :, 0].min(axis=1) - u0) / cell).astype(np.int64), 0, nu - 1)
        i1 = np.clip(np.floor((tri[:, :, 0].max(axis=1) - u0) / cell).astype(np.int64), 0, nu - 1)
        j0 = np.clip(np.floor((tri[:, :, 1].min(axis=1) - v0) / cell).astype(np.int64), 0, nv - 1)
        j1 = np.clip(np.floor((tri[:, :, 1].max(axis=1) - v0) / cell).astype(np.int64), 0, nv - 1)
        total = np.pad(np.cumsum(np.cumsum(locked.astype(np.int64), axis=0), axis=1), ((1, 0), (1, 0)))
        inside = total[j1 + 1, i1 + 1] - total[j0, i1 + 1] - total[j1 + 1, i0] + total[j0, i0]
        tri = tri[inside > 0]
        if not len(tri):
            continue
        base = float(pa.min()) - 2.0
        under = swept(M, tri, base)
        if os.environ.get("MOLD_DEBUG"):
            sys.stderr.write("piece %d: locked columns %d, triangles swept %d, %s volume %.0f\n" % (k, int(locked.sum()), len(tri), under.status(), under.volume()))
        if under.is_empty() or under.status() != M.Error.NoError:
            continue
        under = under.simplify(0.01)
        back = [[u[0], v[0], dd[0], 0.0], [u[1], v[1], dd[1], 0.0], [u[2], v[2], dd[2], 0.0]]
        # the fillings of neighbouring pieces meet on the parting face: each reaches 0.01 mm over it, so they join
        added = trimmed(under.transform(back), [(n, off - 0.01) for n, off in split.planes(k)])
        if added.is_empty() or added.volume() < 1.0:
            continue
        cast = cast + added
        changed += 1
    return cast, changed


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
    if (x1 - x0) + 10.0 + (sx1 - sx0) > BED and (y1 - y0) + 10.0 + (sy1 - sy0) < (x1 - x0) + 10.0 + (sx1 - sx0):
        # side by side they would not fit a common bed: the sleeve goes behind the base
        sleeve = sleeve.translate([-sx0 + rim, -sy0 + (y1 - y0) + 10.0, 0.0])
    else:
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


def ball_keys(M, halves, split, ex, ey, h, wall, clearance):
    """Two halves: three balls on the parting face inside the wall, none near the pour opening."""
    r = max(2.0, min(4.0, wall * 0.35))
    c = split.c
    if split.angle is None:
        spots = [(-ex / 2 - wall / 2, 0.0, c), (ex / 2 + wall / 2, 0.0, c), (0.0, ey / 2 + wall / 2, c)]
    else:
        spots = [(-ex / 2 - wall / 2, c, h / 2), (ex / 2 + wall / 2, c, h / 2), (0.0, c, h + wall / 2)]
    if r * 2 + 1.0 > wall:
        return halves, 0
    balls = M.Manifold.batch_boolean([M.Manifold.sphere(r, 32).translate(list(s)) for s in spots], M.OpType.Add)
    sockets = M.Manifold.batch_boolean([M.Manifold.sphere(r + clearance, 32).translate(list(s)) for s in spots], M.OpType.Add)
    # the half of each ball inside the first half is already that half; the other half stands out of the parting face
    return [halves[0] + balls, halves[1] - sockets], len(spots)


def cone_keys(M, pieces, split, ex, ey, h, wall, clearance):
    """
    Wedges leave at up to 45 degrees to their parting faces; a ball would hold them. A low cone whose side leans
    50 degrees from its axis lets go in all those directions. Two on every parting face, in the middle of the wall.
    """
    r = max(2.0, min(3.5, wall * 0.32))
    if r * 2 + 1.0 > wall:
        return pieces, 0
    tall = (r - 0.3 * r) / math.tan(math.radians(50.0))
    step = 360.0 / split.parts
    count = 0
    for k in range(split.parts):
        deg = split.theta + k * step                       # the face between wedge k-1 and wedge k
        ray = heading(deg)
        into = (math.sin(math.radians(deg)), -math.cos(math.radians(deg)), 0.0)      # out of wedge k, into wedge k-1
        # where the ray leaves the box, and the middle of the wall there
        reach = []
        for axis, half, centre in ((0, ex / 2 + wall, split.cx), (1, ey / 2 + wall, split.cy)):
            if abs(ray[axis]) > 1e-9:
                edge = half if ray[axis] > 0 else -half
                reach.append(((edge - centre) / ray[axis], abs(ray[axis])))
        t_out, lean = min(reach)
        t = t_out - (wall / 2) / max(lean, 0.5)
        for z in (h * 0.25, h * 0.75):
            spot = [split.cx + ray[0] * t, split.cy + ray[1] * t, z]
            turn = math.degrees(math.atan2(into[1], into[0]))

            def cone(grow):
                body = M.Manifold.cylinder(tall + 0.5 + grow, r + grow, 0.3 * r + grow, 32).translate([0, 0, -0.5])
                return body.rotate([0.0, 90.0, 0.0]).rotate([0.0, 0.0, turn]).translate(spot)

            pieces[k] = pieces[k] + cone(0.0)
            pieces[(k - 1) % split.parts] = pieces[(k - 1) % split.parts] - cone(clearance)
            count += 1
    return pieces, count


def read_params(raw):
    try:
        p = json.loads(raw) if raw else {}
    except ValueError:
        p = {}
    split = p.get("split", "auto")
    return {
        "kind": "silicone" if str(p.get("type", "rigid")) == "silicone" else "rigid",
        "parts": int(p.get("parts", 2)) if int(p.get("parts", 2) or 2) in (2, 3, 4) else 2,
        "fill": bool(p.get("fill", False)),
        "cast": str(p.get("cast", "") or ""),
        "wall": max(4.0, min(25.0, float(p.get("wall", 8)))),
        "clearance": max(0.05, min(0.5, float(p.get("clearance", 0.15)))),
        "axis": str(p.get("axis", "auto")),
        "fraction": None if split in ("auto", None, "") else max(0.1, min(0.9, float(split))),
        "keys": bool(p.get("keys", True)),
        "funnel": bool(p.get("funnel", True)),
    }


def prepared(src):
    """The model as a closed body, centred over the origin, standing on z = 0; (mesh, shift it got)."""
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
    lo, hi = mesh.bounds
    shift = [-(lo[0] + hi[0]) / 2, -(lo[1] + hi[1]) / 2, -lo[2]]
    mesh.apply_translation(shift)
    return mesh, shift


def described(split):
    return {
        "parts": split.parts,
        "axis": split.name,
        "angle_deg": None if split.parts == 2 and split.angle is None else round(split.angle if split.parts == 2 else split.theta),
        "split_mm": round(split.c, 2) if split.parts == 2 else 0,
        "undercut_pct": round(split.pct, 1) if split.pct >= 0.5 else 0,
        "verdict": verdict_of(split.pct),
    }


def analyse(src, dst, raw):
    import numpy as np
    import trimesh
    p = read_params(raw)
    mesh, shift = prepared(src)
    surface = Surface(mesh)
    options, chosen = {}, None
    for parts in (2, 3, 4):
        s = choose(surface, parts, p["axis"] if parts == 2 else "auto", p["fraction"] if parts == 2 else None)
        options[str(parts)] = round(s.pct, 1) if s.pct >= 0.5 else 0
        if parts == p["parts"]:
            chosen = s
    # the triangles as the file has them, in its order: the page paints the very mesh it shows
    plain = trimesh.load(src, force="mesh", process=False)
    centres = np.asarray(plain.triangles_center) + np.asarray(shift)
    normals = np.asarray(plain.face_normals)
    part, seen = chosen.seen(surface, centres, normals)
    flags = part.astype(np.uint8) | np.where(seen, 0, 8).astype(np.uint8)
    with open(dst, "wb") as fh:
        fh.write(flags.tobytes())
    answer = described(chosen)
    answer.update({"ok": True, "options": options, "faces": int(len(flags)), "hidden_faces": int((~seen).sum())})
    out(answer)


def build(src, dst, raw):
    import numpy as np
    import trimesh
    import manifold3d as M
    p = read_params(raw)
    kind, wall = p["kind"], p["wall"]
    mesh, _ = prepared(src)
    surface = Surface(mesh)

    # what a hard mold would do with this shape: measured for both types, the silicone one reports it too
    split = choose(surface, p["parts"] if kind == "rigid" else 2, p["axis"] if kind == "rigid" and p["parts"] == 2 else "auto",
                   p["fraction"] if kind == "rigid" and p["parts"] == 2 else None)
    answer = described(split)
    turned = 0.0
    if kind == "rigid":
        # the mold is built square to its division: a plane's pull becomes y, the first wedge starts along x
        turned = (90.0 - split.angle) if split.parts == 2 and split.angle is not None else (-split.theta if split.parts > 2 else 0.0)
        if abs(turned) > 1e-6:
            spin = trimesh.transformations.rotation_matrix(math.radians(turned), [0, 0, 1])
            mesh.apply_transform(spin)
            lo, hi = mesh.bounds
            shift = [-(lo[0] + hi[0]) / 2, -(lo[1] + hi[1]) / 2, 0.0]
            mesh.apply_translation(shift)
            spin[:3, 3] = shift
            surface.move(spin)
            split.move(turned, shift)
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
        cast, filled = man, False
        if p["fill"] and split.pct > 0.5:
            original = man.as_original()
            cast, left, look = original, split.pct, surface
            for _ in range(PASSES):
                # measured again on the shape that will really be cast; what the first pass did not find (hidden
                # surface too small for the points of the first look) is found by the next
                more, changed = fill_undercuts(M, cast, look, split)
                if not changed:
                    break
                look = Surface(trimesh.Trimesh(*_arrays(more), process=False))
                _, seen = split.seen(look)
                now = 100.0 * (1.0 - float(seen.mean()))
                cast, filled = more, True
                if now > left - 0.3 or now < 1.0:
                    left = min(left, now)
                    break
                left = now
            if filled:
                added = float(cast.volume()) - float(man.volume())
                answer["added_ml"] = round(added / 1000.0, 1)
                answer["undercut_before_pct"] = answer["undercut_pct"]
                answer["undercut_pct"] = round(left, 1) if left >= 0.5 else 0
                answer["verdict"] = verdict_of(left)
                if p["cast"]:
                    write_cast(cast, original.original_id(), p["cast"], -turned)
        answer["fill"] = filled

        box = M.Manifold.cube([ex + 2 * wall, ey + 2 * wall, h + 2 * wall]).translate([-ex / 2 - wall, -ey / 2 - wall, -wall])
        cavity = cast
        resin_mm3 = float(cast.volume())
        if p["funnel"]:
            funnel, extra = pour_opening(M, man, h, wall)
            cavity = cavity + funnel
            resin_mm3 += extra
        shell = box - cavity
        pieces = [trimmed(shell, split.planes(k)) for k in range(split.parts)]
        keys = 0
        if p["keys"]:
            pieces, keys = (ball_keys if split.parts == 2 else cone_keys)(M, pieces, split, ex, ey, h, wall, p["clearance"])
        volume = sum(float(x.volume()) for x in pieces)
        if split.parts == 2:
            # as before: the first half with its parting face (towards +pull) up, the second turned the other way
            n = split.normal()
            laid = [lay_open(pieces[0], tuple(-x for x in n)), lay_open(pieces[1], n)]
        else:
            laid = [lay_open(piece, pull) for piece, pull in zip(pieces, split.pulls())]
        laid = [x for x in laid if not x.is_empty()]
        plate = lay_out(M, laid)
        report = {
            "box": [round(ex + 2 * wall, 1), round(ey + 2 * wall, 1), round(h + 2 * wall, 1)],
            "resin_ml": round(resin_mm3 / 1000.0, 1),
            "mold_cm3": round(volume / 1000.0, 1),
            "keys": keys,
            "pieces": len(laid),
        }
        if answer["verdict"] != "rigid":
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
    answer.update(report)
    answer.update({
        "ok": True,
        "type": kind,
        "plate": [round(bx1 - bx0, 1), round(by1 - by0, 1), round(bz1 - bz0, 1)],
        "wall": wall,
        "warnings": warnings,
        "triangles": int(len(result.faces)),
    })
    out(answer)


def _arrays(man):
    import numpy as np
    m = man.to_mesh()
    return np.asarray(m.vert_properties, dtype=np.float64)[:, :3], np.asarray(m.tri_verts, dtype=np.int64)


def write_cast(cast, model_id, path, turn_back):
    """The shape that will be cast, turned back the way the model was uploaded, and which of its triangles are new."""
    import numpy as np
    import trimesh
    solid = cast.rotate([0.0, 0.0, turn_back]) if abs(turn_back) > 1e-6 else cast
    m = solid.to_mesh()
    verts = np.asarray(m.vert_properties, dtype=np.float64)[:, :3]
    tris = np.asarray(m.tri_verts, dtype=np.int64)
    new = np.ones(len(tris), dtype=np.uint8)
    ids, starts = list(m.run_original_id), list(m.run_index)
    for i, rid in enumerate(ids):
        if rid == model_id:
            new[starts[i] // 3:starts[i + 1] // 3] = 0
    mesh = trimesh.Trimesh(verts, tris, process=False)
    for attempt in range(4):
        # vertices that share a spot are moved a few microns apart (as mesh_tool.from_manifold does), the order stays
        _, inv, cnt = np.unique(np.round(mesh.vertices.astype(np.float32), 4), axis=0, return_inverse=True, return_counts=True)
        twin = cnt[inv.ravel()] > 1
        if not twin.any():
            break
        vv = mesh.vertices.copy()
        vv[twin] += mesh.vertex_normals[twin] * -0.004 + np.random.default_rng(attempt).normal(0, 0.002, (int(twin.sum()), 3))
        mesh = trimesh.Trimesh(vertices=vv, faces=tris, process=False)
    mesh.export(path, file_type="stl")
    with open(path + ".bin", "wb") as fh:
        fh.write(new.tobytes())


def main():
    if len(sys.argv) >= 5 and sys.argv[1] == "analyse":
        analyse(sys.argv[2], sys.argv[3], sys.argv[4])
    if len(sys.argv) < 4:
        out({"ok": False, "error": "usage: mold_tool.py <in.stl> <out.stl> <params-json>"})
    build(sys.argv[1], sys.argv[2], sys.argv[3])


if __name__ == "__main__":
    try:
        main()
    except SystemExit:
        raise
    except Exception as e:  # noqa: BLE001
        out({"ok": False, "error": str(e)})
