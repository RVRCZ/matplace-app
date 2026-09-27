#!/usr/bin/env python3
"""
Test objects for tuning a filament on a printer of the farm (manifold3d, exact solids, milliseconds).

  calib_tool.py <kind> <out.stl> <params-json>

kind:
  quick        one plate, about 35 minutes: a 15 mm cube (dimensions, corners, top surface, elephant foot), overhang
               fan 30-70 degrees, a 20 mm bridge, three pillars for stringing, a single two-line wall, an 8 mm hole
  detailed     one plate, about 70 minutes: a 20 mm cube, overhang fan 30-80 degrees, bridges of 15 and 25 mm,
               stringing pillars with 10 and 20 mm gaps (40 mm tall), walls of one / two / three lines,
               holes of 3 / 5 / 8 / 10 mm, a bar to snap by hand for the layer bond
  ironing      one plate, about 10 minutes: a 30 x 30 mm plateau on a base barely larger than itself, for the
               ironing settings alone (ironing is a setting of the whole print, so a big object would spend most of
               its time polishing its own base plate)
  temp_tower   floors of 10 mm (params: floors 3-10); every floor carries a 14 mm bridge and a 45 degree overhang.
               The temperature per floor is written into the G-code by the web layer (App\\Domain\\Farm\\TowerGcode),
               floor 1 is the bottom one

What a nozzle draws scales with it (param `nozzle`, 0.4 by default): the walls are counted in nozzle widths and the
bond bar gets thinner with it. The base plate follows the nozzle but never goes below 1 mm, or a fine-nozzle test
bends when it is prised off the plate. The stringing pillars stay 4 mm thick for every nozzle: the travel between
them is what pulls the strings, and at 2 mm on a 0.2 nozzle each of their layers took two seconds - the top never
set, the hot nozzle came back every three seconds and cooked it brown. The cube, the holes, the bridges and the
overhang angles stay as they are: they measure the machine, not the nozzle.

All lengths in millimetres, the object sits on Z = 0 at the origin. Prints one JSON object:
{ok, kind, bbox, volume_mm3, area_mm2, triangles, features}. `features` says where each thing is on the plate, so
the evaluation form and a later photo check know what to look at.
"""
import json
import math
import sys

NOZZLE = 0.4          # the nozzle the sizes below are written for
PLATE_MIN = 1.0       # thinnest base plate: below this a test bends when it is prised off the build plate
PILLAR_R = 2.0        # stringing pillars, the same for every nozzle (see above)


def plate_of(n):
    """Base plate thickness: three nozzle widths, never below PLATE_MIN."""
    return max(PLATE_MIN, round(3 * n, 2))


def nozzle_of(p):
    """The nozzle this test prints with; 0.1-1.0 mm, the default is the farm's standard 0.4."""
    n = float(p.get("nozzle") or NOZZLE)
    if not 0.1 <= n <= 1.0:
        raise ValueError("nozzle 0.1-1.0")
    return n


def out(obj):
    print(json.dumps(obj))
    sys.exit(0)


def box(M, x, y, z, w, d, h):
    return M.Manifold.cube([w, d, h]).translate([x, y, z])


def overhang_wedge(M, x, y, z, width, depth, height, angle_deg, along="y"):
    """A slab whose underside leans `angle_deg` from the vertical: 0 = a straight post, 70 = a nearly flat roof."""
    run = height * math.tan(math.radians(angle_deg))
    foot = box(M, x, y, z, width, depth, 0.4)
    top = box(M, x, y + run, z + height - 0.4, width, depth, 0.4) if along == "y" else box(M, x + run, y, z + height - 0.4, width, depth, 0.4)
    return (foot + top).hull()


def skeleton_plate(M, parts, t, tiles=(), ribs_y=(), rib=4.0, margin=1.5):
    """
    The base the features stand on: a pad under every feature's foot, ribs along the rows and one spine across
    them - no solid slab, and no frame around it.

    A slab of 110 x 70 mm is six solid layers of surface nobody ever looks at: on a 0.2 mm nozzle nearly an hour of
    printing, and with ironing on another hour of polishing it. A frame around the plate looked like a brim, stuck
    out as thin arms and bent while the test was prised off (26 Sep 2026). `tiles` are rectangles the object needs
    for itself (the holes are drilled through one); `ribs_y` put a bar under each row of features, and the spine
    through the middle ties the rows into one piece.
    """
    def rect(x, y, rw, rd):
        return M.CrossSection.square([rw, rd]).translate([x, y])

    # a pad under each foot, not under each shadow: an overhang wedge touches the plate with a 6 x 3 mm foot
    # but throws a 30 mm shadow, and padding the shadows made the plate half the print on a fine nozzle
    z0 = parts.bounding_box()[2]
    flat = parts.slice(z0 + 0.01).offset(margin, M.JoinType.Round, 2.0)
    for x, y, tw, td in tiles:
        flat += rect(x, y, tw, td)
    # ribs and spine stay inside what the features cover, so nothing sticks out of the object
    x0, _, x1, _ = flat.bounds()
    for y in ribs_y:
        flat += rect(x0, y, x1 - x0, rib)
    if len(ribs_y) > 1:
        lo, hi = min(ribs_y), max(ribs_y)
        flat += rect((x0 + x1 - rib) / 2, lo, rib, hi - lo + rib)

    # the whole plate is drawn flat and extruded once: unioning boxes in 3D leaves non-manifold edges wherever a
    # rounded pad touches a rib exactly tangentially, and the STL then reads as not watertight
    return flat.simplify(1e-3).extrude(t)


def quick(M, p):
    features = []
    n = nozzle_of(p)
    plate_t = plate_of(n)
    plate_w, plate_d = 80.0, 50.0
    solid = M.Manifold()
    z0 = plate_t

    # row A: cube, bridge, single wall, hole
    cube = 15.0
    solid += box(M, 3, 3, z0, cube, cube, cube)
    features.append({"name": "cube", "at": [3, 3], "size": [cube, cube, cube], "checks": ["dimensions", "corners", "top_surface", "elephant_foot"]})

    span, pw, ph = 20.0, 5.0, 10.0
    bx = 26.0
    solid += box(M, bx, 6, z0, pw, 8, ph) + box(M, bx + pw + span, 6, z0, pw, 8, ph)
    solid += box(M, bx, 6, z0 + ph, 2 * pw + span, 8, 2.0)
    features.append({"name": "bridge", "at": [bx, 6], "span": span, "checks": ["bridge"]})

    wall_t = round(2 * n, 2)
    solid += box(M, 62, 4, z0, wall_t, 15, 15)
    features.append({"name": "thin_wall", "at": [62, 4], "thickness": wall_t, "lines": 2, "checks": ["single_wall"]})

    hole_d = 8.0
    features.append({"name": "hole", "at": [72, 12], "diameter": hole_d, "checks": ["hole_size"]})

    # row B: overhang fan and stringing pillars
    angles = [30, 40, 50, 60, 70]
    for i, a in enumerate(angles):
        x = 3 + i * 8
        solid += overhang_wedge(M, x, 27, z0, 6, 3, 10, a)
    features.append({"name": "overhangs", "at": [3, 27], "angles": angles, "checks": ["overhang"]})

    pillars = [48, 62, 76]
    r = PILLAR_R
    for x in pillars:
        solid += M.Manifold.cylinder(25, r, -1.0, 48).translate([x, 38, z0])
    features.append({"name": "stringing", "at": [[x, 38] for x in pillars], "height": 25, "radius": r, "checks": ["stringing"]})

    solid += skeleton_plate(M, solid, plate_t, tiles=[(66, 5, 14, 16)], ribs_y=(10.0, 36.0))
    solid -= M.Manifold.cylinder(plate_t + 2, hole_d / 2, -1.0, 64).translate([72, 12, -1])

    return solid, features


def detailed(M, p):
    features = []
    n = nozzle_of(p)
    plate_t = plate_of(n)
    plate_w, plate_d = 110.0, 70.0
    solid = M.Manifold()
    z0 = plate_t

    # row A: cube, two bridges, walls, holes
    cube = 20.0
    solid += box(M, 3, 3, z0, cube, cube, cube)
    features.append({"name": "cube", "at": [3, 3], "size": [cube, cube, cube], "checks": ["dimensions", "corners", "top_surface", "elephant_foot"]})


    pw, ph = 4.0, 10.0
    for y, span in ((3, 15.0), (12, 25.0)):
        solid += box(M, 62, y, z0, pw, 6, ph) + box(M, 62 + pw + span, y, z0, pw, 6, ph)
        solid += box(M, 62, y, z0 + ph, 2 * pw + span, 6, 2.0)
        features.append({"name": "bridge", "at": [62, y], "span": span, "checks": ["bridge"]})

    walls = [round(k * n, 2) for k in (1, 2, 3)]
    for i, t in enumerate(walls):
        solid += box(M, 64 + i * 6, 22, z0, t, 14, 15)
    features.append({"name": "thin_walls", "at": [64, 22], "thicknesses": walls, "lines": [1, 2, 3], "checks": ["single_wall"]})

    holes = ((84, 30, 3.0), (91, 30, 5.0), (100, 30, 8.0), (100, 44, 10.0))
    features.append({"name": "holes", "at": [[x, y] for x, y, _ in holes], "diameters": [d for _, _, d in holes], "checks": ["hole_size"]})

    # row B: overhang fan, stringing pillars, bond bar
    angles = [30, 40, 50, 60, 70, 80]
    for i, a in enumerate(angles):
        x = 3 + i * 8
        solid += overhang_wedge(M, x, 38, z0, 6, 3, 10 if a < 80 else 5, a)
    features.append({"name": "overhangs", "at": [3, 38], "angles": angles, "checks": ["overhang"]})

    pillars = [58, 68, 88]
    r = PILLAR_R
    for x in pillars:
        solid += M.Manifold.cylinder(40, r, -1.0, 48).translate([x, 60, z0])
    features.append({"name": "stringing", "at": [[x, 60] for x in pillars], "gaps": [10, 20], "height": 40, "radius": r, "checks": ["stringing"]})

    # snapped by hand: a bar of the same number of lines whatever the nozzle, or the bond cannot be compared
    bar_t = round(max(0.8, 7.5 * n), 2)
    solid += box(M, 100, 52, z0, bar_t, 12, 35)
    features.append({"name": "bond_bar", "at": [100, 52], "size": [bar_t, 12, 35], "checks": ["layer_bond"]})

    solid += skeleton_plate(M, solid, plate_t, tiles=[(79, 22, 28, 29)], ribs_y=(5.0, 24.0, 38.0, 58.0))
    for x, y, dia in holes:
        solid -= M.Manifold.cylinder(plate_t + 2, dia / 2, -1.0, 64).translate([x, y, -1])

    return solid, features


def ironing(M, p):
    """
    Only the ironed plateau, on a base barely larger than itself.

    Ironing is a setting of the whole print: Orca smooths every horizontal top surface it finds, so asking the
    detailed test to iron its 30 x 30 plateau also irons its base plate - on a 0.2 mm nozzle three quarters of an
    hour of polishing something nobody reads. Its own small object answers the same question in ten minutes.
    """
    n = nozzle_of(p)
    plate_t = plate_of(n)
    side, plateau, wall = 34.0, 30.0, 3.0
    solid = box(M, 0, 0, 0, side, side, plate_t)
    solid += box(M, 2, 2, plate_t, plateau, plateau, wall)
    features = [{"name": "ironing", "at": [2, 2], "size": [plateau, plateau], "checks": ["ironing", "top_surface"]}]

    return solid, features


def temp_tower(M, p):
    floors = int(p.get("floors", 5))
    if floors < 3 or floors > 10:
        raise ValueError("floors 3-10")
    fh = 10.0
    features = []
    solid = M.Manifold()
    for i in range(floors):
        z = i * fh
        block = box(M, 0, 0, z, 20, 12, fh)
        pillar = box(M, 8, 26, z, 4, 4, fh)
        bridge = box(M, 8, 12, z + fh - 2, 4, 18, 2)
        wedge = overhang_wedge(M, 20, 0, z + 2, 5, 12, 6, 45, along="x")
        solid = solid + block + pillar + bridge + wedge
        features.append({"name": "floor", "index": i + 1, "z": [z, z + fh], "checks": ["bridge", "overhang", "surface", "layer_bond"]})
    return solid, features


def main(argv):
    if len(argv) < 3:
        out({"ok": False, "code": "usage", "error": "usage"})
    kind, dst = argv[1], argv[2]
    try:
        import manifold3d as M
        import numpy as np
        p = json.loads(argv[3] if len(argv) > 3 and argv[3] else "{}")
        if isinstance(p, list) and not p:
            p = {}                                   # PHP encodes an empty parameter array as []
        builders = {"quick": quick, "detailed": detailed, "ironing": ironing, "temp_tower": temp_tower}
        if kind not in builders or not isinstance(p, dict):
            raise ValueError("unknown_kind")
        solid, features = builders[kind](M, p)
        if solid.is_empty() or solid.status() != M.Error.NoError or solid.volume() <= 0:
            raise ValueError("empty_result")
        x0, y0, z0, x1, y1, z1 = solid.bounding_box()
        solid = solid.translate([-x0, -y0, -z0])
        mesh = solid.to_mesh()
        verts = np.asarray(mesh.vert_properties, dtype=np.float32)[:, :3]
        tris = np.asarray(mesh.tri_verts, dtype=np.int64)
        tri = verts[tris]
        normals = np.cross(tri[:, 1] - tri[:, 0], tri[:, 2] - tri[:, 0])
        lens = np.linalg.norm(normals, axis=1, keepdims=True)
        normals = np.divide(normals, lens, out=np.zeros_like(normals), where=lens > 0)
        rec = np.zeros(len(tris), dtype=[("n", "<f4", 3), ("v", "<f4", (3, 3)), ("a", "<u2")])
        rec["n"], rec["v"] = normals, tri
        with open(dst, "wb") as fh:
            fh.write(b"matplace calibration".ljust(80, b" "))
            fh.write(np.uint32(len(tris)).tobytes())
            fh.write(rec.tobytes())
        out({"ok": True, "kind": kind, "bbox": {"x": round(x1 - x0, 2), "y": round(y1 - y0, 2), "z": round(z1 - z0, 2)},
             "volume_mm3": round(solid.volume(), 1), "area_mm2": round(solid.surface_area(), 1), "triangles": int(len(tris)), "features": features})
    except SystemExit:
        raise
    except Exception as e:  # noqa: BLE001
        out({"ok": False, "code": "failed", "error": str(e)})


if __name__ == "__main__":
    main(sys.argv)
