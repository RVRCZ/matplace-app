"""
Casting molds, round 2 (docs/AB.md), for mold_tool.py: vents from the pockets where air would stay, three more molds
for silicone (a shell that follows the model, an open tray, a two-part mold with a parting plate), a core for hollow
castings, a choice of keys and of the pouring opening, and the volumes a caster needs in millilitres. Round 1
(`mold_tool.build`) calls what it needs from here; nothing of round 1 changes when none of it is asked for.
"""
import math
import os
import sys

import numpy as np

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import mesh_tool as T  # noqa: E402

VENT_D = 2.5              # mm: a vent's bore
VENT_MOST = 6             # vents at most
VENT_NEAR = 6.0           # mm: pockets closer than this are one pocket
SLEEVE = 2.4              # printed wall of a silicone sleeve or tray
BASE = 3.0                # printed base under a master
GROOVE = 1.5              # how deep a sleeve sits in its base
PLATE = 3.0               # the parting plate of a two-part silicone mold
KEY_R = 3.0               # the cone keys on that plate
FOOT = 8.0                # the feet under the plate: the master's lower half hangs free
STEM = 12.0               # how far a core's stem reaches out of the opening
FLANGE = 3.0              # the plate a core stands on


def heading(deg):
    return (math.cos(math.radians(deg)), math.sin(math.radians(deg)), 0.0)


# ── where air stays ────────────────────────────────────────────────────────────────────────────────────────────────────

def pockets(surface, h, pour_up=-1):
    """
    Where air stays when the cavity is filled: with the opening at the foot (pour_up = -1) the mold stands foot up,
    the faces that look down in the model's own frame are then ceilings, and air collects at the lowest point of each
    of them that has no lower neighbour; with the opening at the top (pour_up = +1) the faces that look up and their
    highest points. Returns [(x, y, z)] from the worst pocket on, at most VENT_MOST, never the opening itself.
    """
    P, N = surface.points, surface.normals
    look = N[:, 2] * pour_up > 0.25
    if not look.any():
        return []
    pts = P[look]
    up = pts[:, 2] * pour_up                                   # "height" in the pouring frame: bigger = nearer the opening
    cell = max(2.0, float(np.ptp(P[:, :2], axis=0).max()) / 40.0)
    lo = pts[:, :2].min(axis=0)
    ij = np.floor((pts[:, :2] - lo) / cell).astype(np.int64)
    w = int(ij[:, 0].max()) + 1
    key = ij[:, 0] + ij[:, 1] * w
    order = np.argsort(up)                                     # lowest first (in the pouring frame)
    best = {}
    for i in order:
        k = int(key[i])
        if k not in best:
            best[k] = i
    cand = []
    for k, i in best.items():
        i0, j0 = k % w, k // w
        lower = False
        for di in range(-2, 3):
            for dj in range(-2, 3):
                j = best.get((i0 + di) + (j0 + dj) * w)
                if j is not None and up[j] < up[i] - 0.3:
                    lower = True
                    break
            if lower:
                break
        z = float(pts[i][2])
        at_opening = z < max(3.0, 0.04 * h) if pour_up < 0 else z > h - max(3.0, 0.04 * h)
        if not lower and not at_opening:
            cand.append(pts[i])
    cand.sort(key=lambda p: float(p[2]) * pour_up)
    kept = []
    for p in cand:
        if all(float(np.linalg.norm(p - q)) > VENT_NEAR for q in kept):
            kept.append(p)
        if len(kept) >= VENT_MOST:
            break
    return [tuple(float(v) for v in p) for p in kept]


def bore(M, a, b, r):
    """A round bore from a to b."""
    ax, ay, az = a
    bx, by, bz = b
    vx, vy, vz = bx - ax, by - ay, bz - az
    length = math.sqrt(vx * vx + vy * vy + vz * vz)
    if length < 0.1:
        return None
    tilt = math.degrees(math.acos(max(-1.0, min(1.0, vz / length))))
    turn = math.degrees(math.atan2(vy, vx))
    return M.Manifold.cylinder(length, r, r, 16).rotate([0.0, tilt, 0.0]).rotate([0.0, 0.0, turn]).translate([ax, ay, az])


def vents_rigid(M, points, split, h, wall, pour_up=-1):
    """
    The vents of a printed mold, to take away from it before it is cut into pieces: from each pocket to the wall the
    mold shows upwards when it is poured (the bottom wall for an opening at the foot, the top wall for one at the top).
    Two halves parted by an upright plane get them in the parting plane (a groove on each face, nothing to print over);
    wedges and level halves get straight bores.
    """
    r = VENT_D / 2
    end_z = -wall - 1.0 if pour_up < 0 else h + wall + 1.0
    cuts = []
    for (x, y, z) in points:
        start = (x, y, z - 1.0 * pour_up)                      # a millimetre into the cavity, so the two meet
        if split.parts == 2 and split.angle is not None:
            n = split.normal()
            d = n[0] * x + n[1] * y - split.c
            px, py = x - n[0] * d, y - n[1] * d
            if abs(d) > 1.0:
                cuts.append(bore(M, start, (px, py, z), r))
                start = (px, py, z)
            cuts.append(bore(M, start, (px, py, end_z), r))
        else:
            cuts.append(bore(M, start, (x, y, end_z), r))
    cuts = [c for c in cuts if c is not None]
    return M.Manifold.batch_boolean(cuts, M.OpType.Add) if cuts else None


def vent_pins(M, points):
    """Pins on a master, from each pocket down into the base: the silicone sets round them and keeps the channels."""
    r = VENT_D / 2
    pins = [bore(M, (x, y, z + 1.0), (x, y, -0.5), r) for (x, y, z) in points]
    pins = [p for p in pins if p is not None]
    return M.Manifold.batch_boolean(pins, M.OpType.Add) if pins else None


# ── solids at a distance from the model ───────────────────────────────────────────────────────────────────────────────

def offset_solid(M, mesh, grow, pitch=None):
    """
    The model grown (grow > 0) or shrunk (grow < 0) by that many millimetres on every side: the distance field of a
    voxel grid, marching cubes, one closed solid. Empty when nothing is left of a shrunk model.
    """
    import trimesh
    from scipy import ndimage
    from skimage import measure
    size = float(max(mesh.extents))
    pitch = pitch or max(0.4, size / 150.0)
    pad = int(math.ceil(abs(grow) / pitch)) + 3
    grid, origin = T.surface_grid(mesh, pitch, pad)
    if grow >= 0:
        field = ndimage.distance_transform_edt(~grid) * pitch
        mask = field <= grow
    else:
        field = ndimage.distance_transform_edt(grid) * pitch
        mask = field >= -grow
    if mask.sum() < 8:
        return M.Manifold()
    mask = np.pad(mask, 2)
    smooth = ndimage.gaussian_filter(mask.astype(np.float32), 1.0)            # the voxel steps blurred away, the surface stays within half a voxel
    verts, faces, _, _ = measure.marching_cubes(smooth, level=0.5)
    verts = (verts - 2.0) * pitch + origin
    m = trimesh.Trimesh(verts, faces, process=True)
    if not len(m.faces):
        return M.Manifold()
    man = T.as_manifold(m)
    if man.volume() < 0:
        man = T.as_manifold(trimesh.Trimesh(verts, faces[:, ::-1], process=True))
    return man.simplify(pitch * 0.25)


def outline(M, man, wall):
    """The outline of a silicone sleeve or tray seen from above, `wall` away from the model, its bays bridged."""
    J = M.JoinType.Round
    bridge = max(8.0, wall)
    return man.project().offset(wall + bridge, J, 2.0, 48).offset(-bridge, J, 2.0, 48).simplify(0.1)


def base_with_groove(M, inner, rim=4.0, fit=0.2):
    """A base for a sleeve to sit in: the plate and the groove round the sleeve's foot (as the round-1 sleeve has)."""
    J = M.JoinType.Round
    base = inner.offset(SLEEVE + rim, J, 2.0, 48).simplify(0.05).extrude(BASE).translate([0, 0, -BASE + 0.01])
    groove = (inner.offset(SLEEVE + fit, J, 2.0, 48) - inner.offset(-fit, J, 2.0, 48)).simplify(0.05).extrude(GROOVE + 1.0).translate([0, 0, -GROOVE])
    return base - groove


# ── the three new silicone molds ──────────────────────────────────────────────────────────────────────────────────────

def shell_mold(M, mesh, man, h, wall, pins, split):
    """
    A silicone mold whose printed sleeve follows the model `wall` away from it instead of standing round its outline:
    the model grown by `wall` is the silicone, grown by `wall` + SLEEVE the printed shell; both open at the top where the
    silicone is poured in, the shell parted into two halves (the best plane of a two-part mold) so it comes off the set
    silicone, standing in a groove of the base. Returns (pieces, report).
    """
    J = M.JoinType.Round
    top = h + wall - min(3.0, wall / 3.0)                           # the opening: where the grown shape nearly closes
    big = 1000.0
    floor = M.Manifold.cube([big, big, big]).translate([-big / 2, -big / 2, 0.0])
    lid = M.Manifold.cube([big, big, big]).translate([-big / 2, -big / 2, top])
    silicone = (offset_solid(M, mesh, wall) ^ floor) - lid
    grown = (offset_solid(M, mesh, wall + SLEEVE) ^ floor) - lid
    x0, y0, _, x1, y1, _ = man.bounding_box()
    chimney = M.Manifold.cylinder(wall + SLEEVE + 2.0, 6.0, 6.0, 32).translate([(x0 + x1) / 2, (y0 + y1) / 2, h - 1.0])
    shell = grown - silicone - chimney
    inner = silicone.slice(0.5).simplify(0.1)                        # the foot of the shell: the groove follows it
    base = base_with_groove(M, inner)
    master = base + man
    if pins is not None:
        master = master + pins
    n = split.normal() if split.angle is not None else (0.0, 1.0, 0.0)
    halves = [shell.trim_by_plane((-n[0], -n[1], 0.0), -split.c if split.angle is not None else 0.0),
              shell.trim_by_plane((n[0], n[1], 0.0), split.c if split.angle is not None else 0.0)]
    halves = [x for x in halves if not x.is_empty()]
    prism_ml = (float(outline(M, man, wall).area()) * (h + wall) - float(man.volume())) / 1000.0
    silicone_ml = (float(silicone.volume()) - float(man.volume())) / 1000.0
    report = {
        "variant": "shell",
        "box": [round(x1 - x0 + 2 * (wall + SLEEVE), 1), round(y1 - y0 + 2 * (wall + SLEEVE), 1), round(top + BASE, 1)],
        "silicone_ml": round(silicone_ml, 1),
        "silicone_box_ml": round(prism_ml, 1),
        "saving_pct": int(round(100.0 * max(0.0, 1.0 - silicone_ml / prism_ml))) if prism_ml > 0 else 0,
        "mold_cm3": round((float(master.volume()) + sum(float(x.volume()) for x in halves)) / 1000.0, 1),
        "keys": 0,
        "pieces": 1 + len(halves),
    }
    return [master] + halves, report


def tray_mold(M, man, h, wall, pins):
    """
    An open tray for a shape with a flat back (a relief, a sign, a coaster): the model lies on its back on the floor
    of a printed box, one piece with its walls; silicone is poured over it up to `wall` above the model, and the set
    mold comes out of the tray and casts through its open back. Returns (pieces, report).
    """
    J = M.JoinType.Round
    inner = outline(M, man, wall)
    floor = inner.offset(SLEEVE, J, 2.0, 48).simplify(0.05).extrude(BASE).translate([0, 0, -BASE + 0.01])
    walls = (inner.offset(SLEEVE, J, 2.0, 48) - inner).simplify(0.05).extrude(h + wall)
    tray = floor + walls + man
    if pins is not None:
        tray = tray + pins
    x0, y0, _, x1, y1, _ = tray.bounding_box()
    report = {
        "variant": "tray",
        "box": [round(x1 - x0, 1), round(y1 - y0, 1), round(h + wall + BASE, 1)],
        "silicone_ml": round((float(inner.area()) * (h + wall) - float(man.volume())) / 1000.0, 1),
        "mold_cm3": round((float(tray.volume()) - float(man.volume())) / 1000.0, 1),
        "keys": 0,
        "pieces": 1,
    }
    return [tray], report


def two_part_mold(M, man, split, h, wall, pocket_points):
    """
    A two-part silicone mold: the master lies in a parting plate (its silhouette cut out, cone keys on the plate) inside
    a sleeve; the first half is poured over it, the whole turned over, the plate taken away, the second half poured on.
    The plate stands on feet so the master's lower half hangs free. Vents lie in the parting plane as pins on the
    master. Returns (pieces [master, plate, sleeve], report); the plate and the sleeve are built in a frame where the
    parting plane is level, and turned back for the picture of the mold only in words (they print flat anyway).
    """
    J = M.JoinType.Round
    # the master in the frame of the plate: the parting plane becomes z = 0, the pull +z
    if split.angle is None:
        turned = man.translate([0, 0, -split.c])
        n = (0.0, 0.0, 1.0)
    else:
        turned = man.rotate([0.0, 0.0, 90.0 - split.angle]).rotate([90.0, 0.0, 0.0]).translate([0, 0, -split.c])
        n = split.normal()
    # vents: from each pocket, in the parting plane, towards the foot's end of the model (the sprue is there)
    pins = []
    r = VENT_D / 2
    for (x, y, z) in pocket_points:
        d = n[0] * x + n[1] * y + n[2] * z - split.c
        px, py, pz = x - n[0] * d, y - n[1] * d, z - n[2] * d
        pin = bore(M, (px, py, pz), (px, py, -wall - 1.0) if n[2] < 0.5 else (px - wall - 1.0, py, pz), r)
        if pin is not None:
            pins.append(pin)
    master = man if not pins else man + M.Manifold.batch_boolean(pins, M.OpType.Add)
    if split.angle is None:
        turned_all = master.translate([0, 0, -split.c])
    else:
        turned_all = master.rotate([0.0, 0.0, 90.0 - split.angle]).rotate([90.0, 0.0, 0.0]).translate([0, 0, -split.c])
    x0, y0, z0, x1, y1, z1 = turned_all.bounding_box()
    above, below = z1 + wall, -z0 + wall
    inner = outline(M, turned_all, wall)
    outer = inner.offset(SLEEVE, J, 2.0, 48).simplify(0.05)
    sleeve = (outer - inner).extrude(above + below + GROOVE).translate([0, 0, -GROOVE])
    # the plate: the base the sleeve sits in, the silhouette of the master cut out, feet, keys
    plate2d = inner.offset(SLEEVE + 4.0, J, 2.0, 48).simplify(0.05)
    hole = turned_all.project().offset(0.3, J, 2.0, 24).simplify(0.05)
    plate = base_with_groove(M, inner).translate([0, 0, 0.0]) - hole.extrude(PLATE + 4.0).translate([0, 0, -PLATE - 2.0])
    bx0, by0, bx1, by1 = plate2d.bounds()
    feet_h = below + 3.0
    mx, my = (bx0 + bx1) / 2, (by0 + by1) / 2
    for fx, fy in ((bx0 + 1.0, my - FOOT / 2), (bx1 - FOOT - 1.0, my - FOOT / 2), (mx - FOOT / 2, by0 + 1.0), (mx - FOOT / 2, by1 - FOOT - 1.0)):
        plate = plate + M.Manifold.cube([FOOT, FOOT, feet_h]).translate([fx, fy, -feet_h + 0.01])       # at the middle of each side: the rounded plate reaches its box there
    keys = 0
    # where a key fits: the band of the plate that the silicone covers, a key's radius away from the sleeve and the hole;
    # the four points of that band furthest out are the spots, a key's diameter apart at least
    band = (inner.offset(-KEY_R - 0.5, J, 2.0, 24) - hole.offset(KEY_R + 0.5, J, 2.0, 24)).offset(-0.3, J, 2.0, 24)
    if not band.is_empty():
        pts = np.vstack([np.asarray(poly, dtype=float) for poly in band.to_polygons()])
        spots = [pts[int(np.argmax(pts[:, 0]))], pts[int(np.argmin(pts[:, 0]))], pts[int(np.argmax(pts[:, 1]))], pts[int(np.argmin(pts[:, 1]))]]
        taken = []
        for kx, ky in spots:
            if all(math.hypot(kx - tx, ky - ty) > 2 * KEY_R + 2.0 for tx, ty in taken):
                plate = plate + M.Manifold.cylinder(KEY_R * 0.75, KEY_R, KEY_R * 0.3, 32).translate([float(kx), float(ky), -0.01])
                taken.append((kx, ky))
                keys += 1
    report = {
        "variant": "two_part",
        "box": [round(x1 - x0 + 2 * (wall + SLEEVE), 1), round(y1 - y0 + 2 * (wall + SLEEVE), 1), round(above + below + BASE, 1)],
        "silicone_ml": round((float(inner.area()) * (above + below) - float(man.volume())) / 1000.0, 1),
        "mold_cm3": round((float(plate.volume()) + float(sleeve.volume()) + float(master.volume()) - float(man.volume())) / 1000.0, 1),
        "keys": keys,
        "pieces": 3,
    }
    return [master, plate, sleeve], report


# ── a core for hollow castings ────────────────────────────────────────────────────────────────────────────────────────

def core_piece(M, mesh, man, core_wall):
    """
    The model shrunk by `core_wall` on every side, with a stem out through the opening at the foot and a flange to stand
    on: put into the silicone mold, the casting comes out as a shell that thick. Returns (piece, core_ml) or (None, 0).
    """
    J = M.JoinType.Round
    inner = offset_solid(M, mesh, -core_wall)
    if inner.is_empty() or inner.volume() < 500.0:
        return None, 0.0
    foot = man.slice(0.6).offset(-core_wall, J, 2.0, 24)
    if foot.is_empty() or foot.area() < 20.0:
        return None, 0.0
    stem = foot.simplify(0.1).extrude(STEM + core_wall + 2.0).translate([0, 0, -STEM])       # up into the shrunk body, which starts core_wall above the foot
    flange = foot.offset(FLANGE * 2 + 2.0, J, 2.0, 24).simplify(0.1).extrude(FLANGE).translate([0, 0, -STEM - FLANGE + 0.01])
    return inner + stem + flange, float(inner.volume()) / 1000.0


# ── keys and the pouring opening ──────────────────────────────────────────────────────────────────────────────────────

def cone_keys_two(M, halves, split, ex, ey, h, wall, clearance):
    """Two halves with low cones instead of balls (a cone lets go at a slant as well): at the spots the balls would take."""
    r = max(2.0, min(3.5, wall * 0.32))
    if r * 2 + 1.0 > wall:
        return halves, 0
    c = split.c
    if split.angle is None:
        spots, n = [(-ex / 2 - wall / 2, 0.0, c), (ex / 2 + wall / 2, 0.0, c), (0.0, ey / 2 + wall / 2, c)], (0.0, 0.0, 1.0)
    else:
        spots, n = [(-ex / 2 - wall / 2, c, h / 2), (ex / 2 + wall / 2, c, h / 2), (0.0, c, h + wall / 2)], split.normal()
    tall = (r - 0.3 * r) / math.tan(math.radians(50.0))
    tilt = math.degrees(math.acos(max(-1.0, min(1.0, n[2]))))
    turn = math.degrees(math.atan2(n[1], n[0]))

    def cone(spot, grow):
        body = M.Manifold.cylinder(tall + 0.5 + grow, r + grow, 0.3 * r + grow, 32).translate([0, 0, -0.5])
        return body.rotate([0.0, tilt, 0.0]).rotate([0.0, 0.0, turn]).translate(list(spot))

    first = halves[0] + M.Manifold.batch_boolean([cone(s, 0.0) for s in spots], M.OpType.Add)
    second = halves[1] - M.Manifold.batch_boolean([cone(s, clearance) for s in spots], M.OpType.Add)
    return [first, second], len(spots)


def side_opening(M, man, ex, h, wall):
    """A pouring opening through a side wall at three quarters of the height: a round neck and a flare outside."""
    x0, y0, _, x1, y1, _ = man.bounding_box()
    cy, z = (y0 + y1) / 2, 0.75 * h
    r = max(4.0, min(7.0, wall * 0.45))
    neck = M.Manifold.cylinder(wall + 3.0 + (x1 - x0) / 2, r, r, 32).rotate([0.0, 90.0, 0.0]).translate([-ex / 2 - wall - 0.5, cy, z])
    flare = M.Manifold.cylinder(min(4.0, wall * 0.45), r + min(4.0, wall * 0.45), r, 32).rotate([0.0, 90.0, 0.0]).translate([-ex / 2 - wall - 0.01, cy, z])
    return neck + flare, math.pi * r * r * (wall + 0.5)
