#!/usr/bin/env python3
"""
Print farm: make an uploaded STL ready for the plate (trimesh, numpy, optional pymeshfix).

  farm_tool.py prepare <in.stl> <out.stl> <unit_scale> <bed_x> <bed_y> <bed_z> [overhang_deg]

    1. load as it is (no unit guessing here: the customer confirmed the units, unit_scale turns them into mm)
    2. not closed -> repair: merge vertices, fix normals, fill holes, then pymeshfix; the repair is only accepted
       when the result is closed and still the same object (size within 2 %)
    3. orient: try the big faces of the convex hull and the six axis directions as "down" and keep the one with
       the least overhang area, a real flat base and a low height; a model that already lies well stays as it is
    4. spin about Z when only the other way round fits the plate, put on Z = 0, centre in XY, write binary STL

Always prints exactly one JSON object on stdout.
"""
import json
import math
import sys


def out(obj):
    print(json.dumps(obj))
    sys.exit(0)


def fail(msg):
    out({"ok": False, "error": str(msg)})


def open_edges(m):
    """Edges that belong to one face only (hole rims) and edges shared by more than two faces."""
    import numpy as np
    edges = np.sort(m.edges, axis=1)
    _, counts = np.unique(edges, axis=0, return_counts=True)
    return int((counts == 1).sum()), int((counts > 2).sum())


def repair(m):
    import numpy as np
    import trimesh
    before = m.extents.copy()
    fixed = m.copy()
    fixed.merge_vertices()
    fixed.update_faces(fixed.nondegenerate_faces())
    fixed.update_faces(fixed.unique_faces())
    trimesh.repair.fix_normals(fixed)
    trimesh.repair.fill_holes(fixed)
    method = "trimesh"
    if not fixed.is_watertight:
        try:
            import pymeshfix
            mf = pymeshfix.MeshFix(np.asarray(fixed.vertices, dtype=float), np.asarray(fixed.faces, dtype=np.int32))
            mf.repair(joincomp=True, remove_smallest_components=False)
            cand = trimesh.Trimesh(vertices=mf.v, faces=mf.f, process=True)
            if len(cand.faces) > 0:
                fixed, method = cand, "pymeshfix"
        except Exception:  # noqa: BLE001 - pymeshfix is optional; without it the simple repair is all we have
            pass
    same_object = bool(np.all(np.abs(fixed.extents - before) <= np.maximum(before * 0.02, 0.05)))
    if fixed.is_watertight and same_object:
        if fixed.volume < 0:
            fixed.invert()
        return fixed, method
    return None, method


def candidates(m, limit=24):
    """Directions that may point down: the largest convex-hull facets (a body rests on its hull) and the axes."""
    import numpy as np
    dirs = [np.array(v, dtype=float) for v in ([0, 0, -1], [0, 0, 1], [0, -1, 0], [0, 1, 0], [-1, 0, 0], [1, 0, 0])]
    try:
        hull = m.convex_hull
        order = np.argsort(hull.facets_area)[::-1][:limit]
        for i in order:
            dirs.append(np.asarray(hull.facets_normal[i], dtype=float))
    except Exception:  # noqa: BLE001 - degenerate hull (flat sheet): the axes are enough
        pass
    uniq = []
    for d in dirs:
        n = np.linalg.norm(d)
        if n < 1e-9:
            continue
        d = d / n
        if not any(float(np.dot(d, u)) > 0.9995 for u in uniq):
            uniq.append(d)
    return uniq


def rate(m, down, cos_limit, bed):
    """Lower is better. Areas are relative to the whole surface so small and large models score alike."""
    import numpy as np
    normals, areas = m.face_normals, m.area_faces
    total = float(areas.sum()) or 1.0
    facing = normals @ down                                  # 1 = looks straight down
    height_v = m.vertices @ (-down)
    z0, z1 = float(height_v.min()), float(height_v.max())
    height = z1 - z0
    face_z = height_v[m.faces].min(axis=1)
    on_bed = (facing > 0.9995) & (face_z - z0 < 0.05)
    base = float(areas[on_bed].sum())
    overhang = float(areas[(facing > cos_limit) & ~on_bed].sum())
    diag = float(np.linalg.norm(m.extents)) or 1.0
    score = overhang / total - 0.5 * min(base / total, 0.3) + 0.15 * height / diag
    if base / total < 0.002:
        score += 0.2                                         # nothing flat to stand on
    if height > bed[2]:
        score += 10.0
    return score, overhang, base, height


def prepare(src, dst, unit_scale, bed, overhang_deg):
    import numpy as np
    import trimesh
    m = trimesh.load(src, force="mesh", process=False)
    if isinstance(m, trimesh.Scene):
        geoms = list(m.geometry.values())
        if not geoms:
            raise ValueError("empty scene")
        m = trimesh.util.concatenate(geoms)
    if m.is_empty or len(m.faces) == 0:
        raise ValueError("mesh has no faces")
    if not np.isfinite(m.vertices).all():
        raise ValueError("mesh has invalid coordinates")
    m.merge_vertices()
    if unit_scale != 1.0:
        m.apply_scale(unit_scale)

    was_watertight = bool(m.is_watertight)
    holes, joints = open_edges(m)
    repaired, method = False, None
    if not was_watertight:
        fixed, method = repair(m)
        if fixed is not None:
            m, repaired = fixed, True
    elif m.volume < 0:
        m.invert()

    # orientation
    cos_limit = math.cos(math.radians(overhang_deg))
    start = np.array([0.0, 0.0, -1.0])
    s0, over0, base0, _ = rate(m, start, cos_limit, bed)
    best, best_score, best_stats = start, s0 - 0.02, (over0, base0)   # the uploaded pose wins ties
    for d in candidates(m):
        s, over, base, _ = rate(m, d, cos_limit, bed)
        if s < best_score - 1e-9:
            best, best_score, best_stats = d, s, (over, base)
    rot = np.eye(4)
    if float(np.dot(best, start)) < 0.9999:
        rot = trimesh.geometry.align_vectors(best, start)
        m.apply_transform(rot)
    # only the other way round fits the plate
    spun = False
    ex = m.extents
    if (ex[0] > bed[0] or ex[1] > bed[1]) and ex[1] <= bed[0] and ex[0] <= bed[1]:
        spin = trimesh.transformations.rotation_matrix(math.pi / 2, [0, 0, 1])
        m.apply_transform(spin)
        rot = spin @ rot
        spun = True
    lo, hi = m.bounds
    m.apply_translation([-(lo[0] + hi[0]) / 2, -(lo[1] + hi[1]) / 2, -lo[2]])
    m.export(dst, file_type="stl")

    shells = 1
    try:
        shells = len(m.split(only_watertight=False))
    except Exception:  # noqa: BLE001
        pass
    holes_after, joints_after = open_edges(m)
    ex = m.extents
    out({
        "ok": True,
        "was_watertight": was_watertight,
        "watertight": bool(m.is_watertight),
        "repaired": repaired,
        "repair_method": method,
        "open_edges_before": holes + joints,
        "open_edges": holes_after + joints_after,
        "bbox": {"x": float(ex[0]), "y": float(ex[1]), "z": float(ex[2])},
        "volume_mm3": abs(float(m.volume)) if m.is_watertight else abs(float(m.convex_hull.volume)) * 0.6,
        "area_mm2": float(m.area),
        "triangles": int(len(m.faces)),
        "shells": int(shells),
        "orientation": {
            "method": "hull-facets",
            "matrix": [[round(float(v), 6) for v in row[:3]] for row in rot[:3]],
            "changed": bool(float(np.dot(best, start)) < 0.9999),
            "spun_to_fit": spun,
            "overhang_deg": overhang_deg,
            "overhang_mm2_before": round(over0, 1),
            "overhang_mm2": round(best_stats[0], 1),
            "base_mm2": round(best_stats[1], 1),
        },
    })


def main(argv):
    if len(argv) < 2:
        fail("usage")
    try:
        if argv[1] == "prepare":
            bed = (float(argv[5]), float(argv[6]), float(argv[7]))
            prepare(argv[2], argv[3], float(argv[4]), bed, float(argv[8]) if len(argv) > 8 else 40.0)
        fail("unknown command " + argv[1])
    except SystemExit:
        raise
    except Exception as e:  # noqa: BLE001 - the caller needs one JSON object whatever happened
        fail(e)


if __name__ == "__main__":
    main(sys.argv)
