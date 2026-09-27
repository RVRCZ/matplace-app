"""
Model repair for printing:  python repair_tool.py <in.stl> <out.stl>

What a downloaded STL usually suffers from, fixed one step at a time, and said plainly afterwards:
  doubled vertices and faces, degenerate faces, faces turned inside out, holes in the surface, dust (loose specks).
Every body of the file is kept and repaired on its own (a box and its lid stay two bodies). A repair that would change
the shape (the size of a body moves by more than 2 %) is thrown away and the body stays as it was.

Prints one JSON object: {ok, verdict, before, after, actions}.  verdict: clean | repaired | improved | unchanged
"""
import json
import sys


def out(obj):
    print(json.dumps(obj))
    sys.exit(0)


def edge_counts(m):
    import numpy as np
    if len(m.faces) == 0:
        return 0, 0
    edges = np.sort(m.edges, axis=1)
    _, counts = np.unique(edges, axis=0, return_counts=True)
    return int((counts == 1).sum()), int((counts > 2).sum())


def describe(m):
    import numpy as np
    open_e, bad_e = edge_counts(m)
    try:
        shells = len(m.split(only_watertight=False))
    except Exception:  # noqa: BLE001
        shells = 1
    watertight = bool(m.is_watertight)
    flipped = bool((watertight and not m.is_winding_consistent) or (watertight and float(m.volume) < 0))
    bb = m.extents if len(m.faces) else np.zeros(3)
    return {
        "watertight": watertight, "open_edges": open_e, "non_manifold_edges": bad_e, "shells": int(shells),
        "flipped_normals": flipped, "triangles": int(len(m.faces)), "vertices": int(len(m.vertices)),
        "bbox": {"x": round(float(bb[0]), 3), "y": round(float(bb[1]), 3), "z": round(float(bb[2]), 3)},
        "volume_mm3": round(abs(float(m.volume)), 2) if watertight else None,
    }


def fix_body(p):
    """One body: normals, holes, and pymeshfix when the simple means did not close it. Returns (mesh, used_meshfix)."""
    import numpy as np
    import trimesh
    before = p.extents.copy()
    q = p.copy()
    trimesh.repair.fix_normals(q)
    trimesh.repair.fill_holes(q)
    used = False
    if not q.is_watertight:
        try:
            import pymeshfix
            mf = pymeshfix.MeshFix(np.asarray(q.vertices, dtype=float), np.asarray(q.faces, dtype=np.int32))
            mf.repair(joincomp=False, remove_smallest_components=False)
            cand = trimesh.Trimesh(vertices=mf.v, faces=mf.f, process=True)
            same = len(cand.faces) > 0 and bool(np.all(np.abs(cand.extents - before) <= np.maximum(before * 0.02, 0.05)))
            if same and cand.is_watertight:
                q, used = cand, True
        except Exception:  # noqa: BLE001 - optional library; without it the simple repair is all there is
            pass
    if q.is_watertight and float(q.volume) < 0:
        q.invert()
    return q, used


def main(argv):
    if len(argv) < 3:
        out({"ok": False, "error": "usage"})
    try:
        import numpy as np
        import trimesh
        loaded = trimesh.load(argv[1], force="mesh", process=False)
        if loaded.is_empty or len(loaded.faces) == 0:
            out({"ok": False, "error": "empty"})
        m = trimesh.Trimesh(vertices=np.asarray(loaded.vertices), faces=np.asarray(loaded.faces), process=False)
        actions = {}

        # an STL repeats every corner per triangle: weld first, or every edge looks open
        welded = m.copy()
        welded.merge_vertices()
        before = describe(welded)

        work = welded
        keep = work.nondegenerate_faces()
        actions["removed_degenerate"] = int((~keep).sum())
        work.update_faces(keep)
        keep = work.unique_faces()
        actions["removed_duplicate"] = int((~keep).sum())
        work.update_faces(keep)
        work.remove_unreferenced_vertices()

        parts = list(work.split(only_watertight=False)) if len(work.faces) else []
        if not parts:
            parts = [work]
        biggest = max(float(max(p.extents)) for p in parts)
        bodies, dust, meshfix = [], 0, 0
        for p in parts:
            # specks a thousand times smaller than the model are scanner or export noise, not a part of it
            if len(parts) > 1 and (len(p.faces) < 4 or float(max(p.extents)) < biggest * 0.001):
                dust += 1
                continue
            q, used = fix_body(p)
            meshfix += 1 if used else 0
            bodies.append(q)
        actions["removed_dust"] = dust
        actions["rebuilt_bodies"] = meshfix
        result = bodies[0] if len(bodies) == 1 else trimesh.util.concatenate(bodies)
        after = describe(result)
        actions["closed_edges"] = max(0, before["open_edges"] - after["open_edges"])
        actions["fixed_normals"] = bool(before["flipped_normals"] and not after["flipped_normals"])
        actions["fixed_non_manifold"] = max(0, before["non_manifold_edges"] - after["non_manifold_edges"])

        changed = any(v for v in actions.values())
        if before["watertight"] and not before["flipped_normals"] and not changed:
            verdict = "clean"
        elif after["watertight"] and not after["flipped_normals"]:
            verdict = "repaired"
        elif after["open_edges"] + after["non_manifold_edges"] < before["open_edges"] + before["non_manifold_edges"] or changed:
            verdict = "improved"
        else:
            verdict = "unchanged"

        result.export(argv[2], file_type="stl")
        out({"ok": True, "verdict": verdict, "before": before, "after": after, "actions": actions})
    except SystemExit:
        raise
    except Exception as e:  # noqa: BLE001
        out({"ok": False, "error": str(e)[:300]})


if __name__ == "__main__":
    main(sys.argv)
