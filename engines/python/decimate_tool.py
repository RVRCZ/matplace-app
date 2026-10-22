"""Thin a very fine mesh before it is sliced: fewer triangles, the same shape within a set deviation.

    python decimate_tool.py <in.stl> <out.stl> <target_faces> <max_deviation_mm> <max_peak_mm> [samples]

A mesh of millions of triangles (a scanned dragon, 1.8 M in 49 bodies, 22 Oct 2026) keeps OrcaSlicer busy for
minutes; quadric edge collapse (fast_simplification through trimesh) brings it down in seconds. The collapse is done
body by body – on the whole mesh it welds bodies together and leaves hundreds of non-manifold edges, and the mending
(pymeshfix) throws all but the largest body away – and every thinned body is mended on its own: fins (edges with three
or more faces) off, small holes filled, pymeshfix as the last resort; a body that does not come out watertight, in one
piece and with its volume within a percent stays as it was. The whole is then checked, not assumed: watertight, the
same bodies, the volume within half a percent, and the distance between the surfaces both ways (points sampled on
each against the other) – the 99th percentile within `max_deviation_mm` and the worst sample within `max_peak_mm`.
When the first target fails that, twice the target is tried once. Anything else is answered with ok: false and a
reason, and the caller slices the original.

The distance search needs no rtree: each mesh is subdivided (for the search alone) so no search triangle is longer
than a millimetre, the nearest search triangles always hold the real triangle under the point, the distances are
exact on the real triangles, and only a point still above the limit gets a small ball search.

Answers one JSON line: {ok, reason?, before, after, deviation_mm (p99), peak_mm, watertight, seconds, …}.
"""
import json
import sys
import time


def answer(**data):
    print(json.dumps(data))
    sys.exit(0)


class SurfaceIndex:
    """Exact point-to-surface distances through a search mesh of small triangles (see the module doc)."""

    def __init__(self, mesh, max_edge=1.0):
        import numpy as np
        import trimesh
        from scipy.spatial import cKDTree
        self.mesh = mesh
        v, f, idx = trimesh.remesh.subdivide_to_size(mesh.vertices, mesh.faces, max_edge=max_edge, max_iter=8, return_index=True)
        self.parent = np.asarray(idx)
        self.tree = cKDTree(v[f].mean(axis=1))
        self.reach = max_edge * 0.6

    def distance(self, points, limit, k=32):
        import numpy as np
        _, near = self.tree.query(points, k=k)
        out = np.full(len(points), np.inf)
        self._min(points, np.repeat(np.arange(len(points)), k), near.reshape(-1), out)
        over = np.where(out > limit)[0]
        if len(over):
            balls = self.tree.query_ball_point(points[over], r=out[over] + self.reach)
            lens = np.array([len(b) for b in balls])
            if lens.sum():
                self._min(points, np.repeat(over, lens), np.concatenate([np.asarray(b, dtype=np.int64) for b in balls]), out)
        return out

    def _min(self, points, pi, fi, out):
        import numpy as np
        from trimesh.triangles import closest_point
        pairs = np.unique(np.stack([pi, self.parent[fi]], axis=1), axis=0)
        d = np.linalg.norm(closest_point(self.mesh.triangles[pairs[:, 1]], points[pairs[:, 0]]) - points[pairs[:, 0]], axis=1)
        np.minimum.at(out, pairs[:, 0], d)


def cleanup(x):
    x.update_faces(x.nondegenerate_faces())
    x.update_faces(x.unique_faces())
    x.merge_vertices()
    x.remove_unreferenced_vertices()
    return x


def mend(x):
    """Fins off, small holes filled; pymeshfix when that is not enough (one body: nothing of it is thrown away)."""
    import numpy as np
    import trimesh
    from trimesh.grouping import group_rows
    if x.is_watertight:
        return x, "clean"
    fins = {ei // 3 for g in group_rows(x.edges_sorted, require_count=None) if len(g) >= 3 for ei in g}
    if fins:
        keep = np.ones(len(x.faces), bool)
        keep[list(fins)] = False
        x.update_faces(keep)
        x.remove_unreferenced_vertices()
    trimesh.repair.fill_holes(x)
    if x.is_watertight:
        return x, "fins"
    import pymeshfix
    mf = pymeshfix.MeshFix(x.vertices, x.faces)
    mf.repair(joincomp=False, remove_smallest_components=False)
    v, f = (mf.points, mf.faces) if hasattr(mf, "points") else (mf.v, mf.f)
    y = trimesh.Trimesh(v, f, process=True)
    return (y, "pymeshfix") if y.is_watertight else (x, "broken")


def thin_bodies(bodies, total_faces, target):
    """Every body thinned to its share of the target and mended; the ways it went, for the record."""
    import trimesh
    out, ways = [], {"kept": 0, "clean": 0, "fins": 0, "pymeshfix": 0, "failed": 0}
    for b in bodies:
        share = max(16, int(round(target * len(b.faces) / total_faces)))
        if len(b.faces) <= max(2000, int(share * 1.2)):
            out.append(b)
            ways["kept"] += 1
            continue
        chosen = None
        for aggression in (7, 5):
            tb, how = mend(cleanup(b.simplify_quadric_decimation(face_count=share, aggression=aggression)))
            if tb.is_watertight and tb.body_count == 1 and abs(float(tb.volume) / float(b.volume) - 1) <= 0.01:
                chosen, way = tb, how
                break
        if chosen is None:
            out.append(b)
            ways["failed"] += 1
        else:
            out.append(chosen)
            ways[way] += 1
    return trimesh.util.concatenate(out), ways


def main():
    if len(sys.argv) < 6:
        answer(ok=False, reason="usage")
    src, dst = sys.argv[1], sys.argv[2]
    target = max(1000, int(float(sys.argv[3])))
    limit, peak = float(sys.argv[4]), float(sys.argv[5])
    samples = int(sys.argv[6]) if len(sys.argv) > 6 else 10000
    t0 = time.time()
    import numpy as np
    import trimesh
    try:
        import fast_simplification  # noqa: F401  (trimesh's quadric decimation needs it)
        import pymeshfix  # noqa: F401
    except ImportError as e:
        answer(ok=False, reason="missing_" + (e.name or "module"), seconds=round(time.time() - t0, 2))

    m = trimesh.load(src, force="mesh")
    before = int(len(m.faces))
    if before <= target:
        answer(ok=False, reason="small_enough", before=before, seconds=round(time.time() - t0, 2))
    if not m.is_watertight:
        answer(ok=False, reason="not_watertight", before=before, seconds=round(time.time() - t0, 2))
    bodies_n, volume = int(m.body_count), float(m.volume)
    load_s = round(time.time() - t0, 2)
    t1 = time.time()
    bodies = sorted(m.split(only_watertight=False), key=lambda b: -len(b.faces))
    index = SurfaceIndex(m)
    split_s = round(time.time() - t1, 2)

    tried = []
    for goal in (target, target * 2):
        if goal >= before:
            break
        t2 = time.time()
        whole, ways = thin_bodies(bodies, before, goal)
        thin_s = round(time.time() - t2, 2)
        record = dict(target=goal, after=int(len(whole.faces)), ways=ways, watertight=bool(whole.is_watertight), bodies=int(whole.body_count),
                      volume_pct=round((float(whole.volume) / volume - 1) * 100, 3) if whole.is_watertight else None, thin_s=thin_s)
        tried.append(record)
        if not whole.is_watertight or int(whole.body_count) != bodies_n or abs(record["volume_pct"]) > 0.5 or len(whole.faces) >= before * 0.9:
            continue
        t3 = time.time()
        pts, _ = trimesh.sample.sample_surface(whole, samples)
        d1 = index.distance(pts, limit)
        pts2, _ = trimesh.sample.sample_surface(m, samples)
        d2 = SurfaceIndex(whole).distance(pts2, limit)
        d = np.concatenate([d1, d2])
        record.update(deviation_mm=round(float(np.percentile(d, 99)), 4), peak_mm=round(float(d.max()), 4), check_s=round(time.time() - t3, 2))
        if record["deviation_mm"] <= limit and record["peak_mm"] <= peak:
            whole.export(dst)
            answer(ok=True, before=before, after=record["after"], deviation_mm=record["deviation_mm"], peak_mm=record["peak_mm"], watertight=True,
                   bodies=bodies_n, volume_pct=record["volume_pct"], ways=ways, target=goal, load_s=load_s, split_s=split_s, thin_s=thin_s,
                   check_s=record["check_s"], seconds=round(time.time() - t0, 2))
    reason = "deviation" if any("deviation_mm" in r for r in tried) else ("shape" if tried else "small_enough")
    answer(ok=False, reason=reason, before=before, tried=tried, seconds=round(time.time() - t0, 2))


if __name__ == "__main__":
    main()
