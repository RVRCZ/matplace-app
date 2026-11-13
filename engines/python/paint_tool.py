#!/usr/bin/env python3
"""
Model colouring (the "Vybarvit model" tool, docs/AS.md): a model's STL and a colouring record → a 3MF painted the way
Bambu Studio / OrcaSlicer / Anycubic Slicer Next save it (`paint_color` on every triangle), which is the very file the
farm already reads, prices and slices (docs/AL.md §14, colors_tool.py).

  paint_tool.py build <src.stl> <out.3mf> <json|@file> [added.bin]
  paint_tool.py sample <out.stl> <triangles>          a test body of about that many triangles (measurements, tests)

The record (JSON):
  triangles   how many triangles the source STL has (the record was made over exactly this file)
  palette     ["#rrggbb", …]; a triangle's state is an index into it, 0 = not painted (the model's own colour)
  states_file a file of one byte per source triangle in the STL's order: its state; 255 = this triangle was refined by
              the brush and is replaced by triangles of `added_file` (or `runs`: [[count, state], …], for tests)
  added_file  the refined triangles (float32 LE, nine numbers each), `added_states_file` one byte each (or `added_runs`)
  heights     [{"z": mm above the model's lowest point, "color": state}, …]: every triangle NOT painted takes the colour
              of the highest band that starts at or below it; the mesh is cut at every such height, so the border is exact
  speck_mm2   a patch of one colour smaller than this joins the colour around it (a 0.4 mm nozzle cannot draw it)

The brush refines the mesh in the browser by splitting edges (both triangles of an edge at once), so the refined mesh
has no T-junctions and covers the very same surface: checked here by the area (within 0.1 %) and the bounding box. The
cut at a height splits every edge that crosses it at one point computed the same way for both of its triangles.

Answers one JSON line: {ok, triangles, colors: [{hex, share, triangles, filament}], specks: {count, mm2}, cut, …}.
"""
import json
import os
import sys
import zipfile

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

REPLACED = 255
MAX_TRIANGLES = 1_900_000        # colors_tool.py reads a painted 3MF of up to 2 M triangles


class Invalid(Exception):
    def __init__(self, code, detail=""):
        super().__init__(code + (": " + detail if detail else ""))
        self.code = code


def out(obj):
    print(json.dumps(obj))
    sys.exit(0)


def read_stl(path):
    import numpy as np
    try:
        size = os.path.getsize(path)
        with open(path, "rb") as fh:
            fh.seek(80)
            n = int(np.frombuffer(fh.read(4), dtype="<u4")[0])
        if size != 84 + 50 * n or n < 1:
            raise Invalid("not_stl")
        rec = np.fromfile(path, dtype=[("n", "<f4", 3), ("v", "<f4", (3, 3)), ("a", "<u2")], offset=84, count=n)
        return np.ascontiguousarray(rec["v"])
    except (OSError, ValueError, IndexError):
        raise Invalid("not_stl")


def expand(runs, n, limit):
    """[[count, state], …] as one state per triangle; the counts must add up to n."""
    import numpy as np
    try:
        counts = np.asarray([int(r[0]) for r in runs], dtype=np.int64)
        states = np.asarray([int(r[1]) for r in runs], dtype=np.int64)
    except (TypeError, ValueError, IndexError):
        raise Invalid("bad_record", "runs")
    if len(counts) and (counts.min() < 1 or states.min() < 0):
        raise Invalid("bad_record", "runs")
    if int(counts.sum()) != n:
        raise Invalid("bad_record", "runs cover %d of %d" % (int(counts.sum()), n))
    if len(states) and int(states[(states != REPLACED)].max(initial=0)) >= limit:
        raise Invalid("bad_record", "state")
    return np.repeat(states, counts).astype(np.uint8)


def load_states(p, file_key, runs_key, n, limit):
    """One state per triangle: a file of bytes (what the page sends) or runs written in the record (tests, by hand)."""
    import numpy as np
    path = p.get(file_key)
    if not path:
        return expand(p.get(runs_key) or [], n, limit)
    try:
        states = np.fromfile(path, dtype=np.uint8)
    except OSError:
        raise Invalid("bad_record", file_key)
    if len(states) != n:
        raise Invalid("bad_record", "%s holds %d of %d" % (file_key, len(states), n))
    if len(states) and int(states[states != REPLACED].max(initial=0)) >= limit:
        raise Invalid("bad_record", "state")
    return states


def area(tri):
    import numpy as np
    return 0.5 * np.linalg.norm(np.cross(tri[:, 1] - tri[:, 0], tri[:, 2] - tri[:, 0]), axis=1)


def cut_at(tri, states, z):
    """
    Every triangle that reaches across the height z is cut along it; the pieces keep its state. An edge is cut at one
    point, computed from its lower end to its upper end, so the two triangles of an edge get the very same point.
    """
    import numpy as np
    eps = 1e-5
    d = tri[:, :, 2].astype(np.float64) - z
    side = np.where(d > eps, 1, np.where(d < -eps, -1, 0))
    crossing = (side.max(axis=1) == 1) & (side.min(axis=1) == -1)
    idx = np.flatnonzero(crossing)
    if not len(idx):
        return tri, states, 0
    T = tri[idx].astype(np.float64)
    S = side[idx]

    def meet(a, b):
        # from the lower end to the upper one (a tie cannot happen: the ends lie on opposite sides)
        swap = a[:, 2] > b[:, 2]
        lo = np.where(swap[:, None], b, a)
        hi = np.where(swap[:, None], a, b)
        t = (z - lo[:, 2]) / (hi[:, 2] - lo[:, 2])
        p = lo + (hi - lo) * t[:, None]
        p[:, 2] = z
        return p

    new_t, new_s = [], []
    st = states[idx]
    # one corner on the plane, the other two on opposite sides: two pieces
    on = (S == 0).sum(axis=1) == 1
    if on.any():
        k = np.argmax(S[on] == 0, axis=1)
        R = np.take_along_axis(T[on], ((k[:, None] + np.arange(3)) % 3)[:, :, None].repeat(3, axis=2), axis=1)
        V, B, C = R[:, 0], R[:, 1], R[:, 2]
        P = meet(B, C)
        new_t += [np.stack([V, B, P], axis=1), np.stack([V, P, C], axis=1)]
        new_s += [st[on], st[on]]
    # no corner on the plane: the corner alone on its side is cut off, the rest is a quad of two pieces
    off = ~on
    if off.any():
        So = S[off]
        alone = np.where((So.sum(axis=1) < 0)[:, None], So == 1, So == -1)
        k = np.argmax(alone, axis=1)
        R = np.take_along_axis(T[off], ((k[:, None] + np.arange(3)) % 3)[:, :, None].repeat(3, axis=2), axis=1)
        A, B, C = R[:, 0], R[:, 1], R[:, 2]
        P1, P2 = meet(A, B), meet(C, A)
        new_t += [np.stack([A, P1, P2], axis=1), np.stack([P1, B, C], axis=1), np.stack([P1, C, P2], axis=1)]
        new_s += [st[off], st[off], st[off]]
    keep = ~crossing
    tri = np.concatenate([tri[keep]] + [t.astype(np.float32) for t in new_t])
    states = np.concatenate([states[keep]] + new_s)
    return tri, states, int(len(idx))


def weld(tri):
    """The triangles as vertices and faces; corners at the very same coordinates are one vertex."""
    import numpy as np
    flat = np.ascontiguousarray(tri.reshape(-1, 3))
    view = flat.view([("", flat.dtype)] * 3).ravel()
    _, first, inverse = np.unique(view, return_index=True, return_inverse=True)
    return flat[first], inverse.reshape(-1, 3).astype(np.int64)


def merge_specks(V, F, states, limit):
    """
    Patches of one colour (triangles joined by edges) smaller than `limit` mm² take the colour they share the longest
    border with: a dot a 0.4 mm nozzle cannot draw would only cost a colour change. Returns how many and their area.
    """
    import numpy as np
    import trimesh
    if limit <= 0:
        return states, 0, 0.0
    m = trimesh.Trimesh(vertices=V, faces=F, process=False)
    adj = m.face_adjacency
    if not len(adj):
        return states, 0, 0.0
    edges = m.face_adjacency_edges
    lens = np.linalg.norm(V[edges[:, 0]] - V[edges[:, 1]], axis=1)
    areas = m.area_faces
    merged, merged_area = 0, 0.0
    for _ in range(3):
        same = states[adj[:, 0]] == states[adj[:, 1]]
        labels = trimesh.graph.connected_component_labels(adj[same], node_count=len(F))
        patch_area = np.bincount(labels, weights=areas)
        small = patch_area < limit
        if not small.any() or small.all():
            break
        # the borders of the small patches: (patch, colour beyond) → length
        la, lb = labels[adj[:, 0]], labels[adj[:, 1]]
        border = ~same
        best = {}
        for a, b, sb, ln in list(zip(la[border], lb[border], states[adj[border][:, 1]], lens[border])) + list(zip(lb[border], la[border], states[adj[border][:, 0]], lens[border])):
            if small[a] and not small[b]:
                by = best.setdefault(int(a), {})
                by[int(sb)] = by.get(int(sb), 0.0) + float(ln)
        if not best:
            break
        to = np.full(len(patch_area), -1, dtype=np.int64)
        for patch, by in best.items():
            to[patch] = max(by.items(), key=lambda kv: kv[1])[0]
            merged += 1
            merged_area += float(patch_area[patch])
        change = to[labels] >= 0
        states = states.copy()
        states[change] = to[labels][change]
    return states, merged, merged_area


def paint_code(filament):
    """`paint_color` of a whole triangle in a filament (1-based), as the slicers write it."""
    from colors_tool import _paint_string
    return _paint_string(("leaf", int(filament)))


def write_3mf(dst, V, F, filaments, hexes, own, name):
    """One object, its extruder `own` (1-based), every triangle of another filament painted."""
    import numpy as np
    codes = {f: paint_code(f) for f in set(int(x) for x in np.unique(filaments))}
    parts = ['<?xml version="1.0" encoding="UTF-8"?>\n<model unit="millimeter" xml:lang="en-US" xmlns="http://schemas.microsoft.com/3dmanufacturing/core/2015/02">\n<resources>\n<object id="1" type="model">\n<mesh>\n<vertices>\n']
    parts.append("".join('<vertex x="%.9g" y="%.9g" z="%.9g"/>\n' % (float(x), float(y), float(z)) for x, y, z in V))
    parts.append("</vertices>\n<triangles>\n")
    lines = []
    for (a, b, c), f in zip(F.tolist(), filaments.tolist()):
        lines.append('<triangle v1="%d" v2="%d" v3="%d"/>\n' % (a, b, c) if f == own else '<triangle v1="%d" v2="%d" v3="%d" paint_color="%s"/>\n' % (a, b, c, codes[f]))
    parts.append("".join(lines))
    parts.append('</triangles>\n</mesh>\n</object>\n</resources>\n<build>\n<item objectid="1"/>\n</build>\n</model>\n')
    safe = "".join(ch for ch in str(name) if ch.isalnum() or ch in " _-.")[:60] or "model"
    settings = ('<?xml version="1.0" encoding="UTF-8"?>\n<config>\n<object id="1">\n<metadata key="name" value="%s"/>\n<metadata key="extruder" value="%d"/>\n</object>\n</config>\n' % (safe, own))
    tmp = dst + ".tmp"
    with zipfile.ZipFile(tmp, "w", zipfile.ZIP_DEFLATED, compresslevel=4) as z:
        z.writestr("[Content_Types].xml", '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                   '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="model" ContentType="application/vnd.ms-package.3dmanufacturing-3dmodel+xml"/></Types>')
        z.writestr("_rels/.rels", '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                   '<Relationship Target="/3D/3dmodel.model" Id="rel0" Type="http://schemas.microsoft.com/3dmanufacturing/2013/01/3dmodel"/></Relationships>')
        z.writestr("3D/3dmodel.model", "".join(parts))
        z.writestr("Metadata/model_settings.config", settings)
        z.writestr("Metadata/project_settings.config", json.dumps({"filament_colour": hexes}))
    os.replace(tmp, dst)


def build(src, dst, p, added_path):
    import numpy as np
    import re
    palette = [str(h).upper() for h in (p.get("palette") or [])]
    if len(palette) < 2 or len(palette) > 16 or any(not re.match(r"^#[0-9A-F]{6}$", h) for h in palette):
        raise Invalid("bad_record", "palette")
    tri = read_stl(src)
    n = len(tri)
    if int(p.get("triangles", -1)) != n:
        raise Invalid("source_changed", "%d != %d" % (int(p.get("triangles", -1)), n))
    states = load_states(p, "states_file", "runs", n, len(palette))
    lo, hi = tri.reshape(-1, 3).min(axis=0), tri.reshape(-1, 3).max(axis=0)
    source_area = float(area(tri.astype(np.float64)).sum())
    replaced = states == REPLACED
    refined = 0
    added_path = p.get("added_file") or added_path
    if replaced.any() or added_path:
        if not added_path or not os.path.isfile(added_path):
            raise Invalid("bad_record", "added")
        raw = np.fromfile(added_path, dtype="<f4")
        if len(raw) % 9 or not len(raw):
            raise Invalid("bad_record", "added")
        add = raw.reshape(-1, 3, 3)
        if not np.isfinite(add).all():
            raise Invalid("bad_record", "added")
        add_states = load_states(p, "added_states_file", "added_runs", len(add), len(palette))
        if (add_states == REPLACED).any():
            raise Invalid("bad_record", "added state")
        # the refined mesh is the same surface: inside the model's box, the same area
        tol = 1e-3 + 1e-5 * float(np.abs(np.concatenate([lo, hi])).max())
        if (add.reshape(-1, 3).min(axis=0) < lo - tol).any() or (add.reshape(-1, 3).max(axis=0) > hi + tol).any():
            raise Invalid("mesh_changed", "box")
        tri = np.concatenate([tri[~replaced], add])
        states = np.concatenate([states[~replaced], add_states])
        new_area = float(area(tri.astype(np.float64)).sum())
        if abs(new_area - source_area) > max(1e-3 * source_area, 0.01):
            raise Invalid("mesh_changed", "area %.3f != %.3f" % (new_area, source_area))
        refined = int(len(add))
    if len(tri) > MAX_TRIANGLES:
        raise Invalid("too_heavy", str(len(tri)))

    # by height: the mesh cut at every band, what is not painted takes its band's colour
    bands = []
    for h in (p.get("heights") or [])[:16]:
        try:
            z, c = float(h["z"]), int(h["color"])
        except (KeyError, TypeError, ValueError):
            raise Invalid("bad_record", "heights")
        if c < 0 or c >= len(palette):
            raise Invalid("bad_record", "heights")
        if 0 < z < float(hi[2] - lo[2]):
            bands.append((float(lo[2]) + z, c))
    bands.sort()
    cut = 0
    for z, _ in bands:
        tri, states, k = cut_at(tri, states, z)
        cut += k
    if bands:
        cz = tri[:, :, 2].astype(np.float64).mean(axis=1)
        band = np.searchsorted(np.asarray([b[0] for b in bands]), cz, side="right") - 1
        colour = np.asarray([b[1] for b in bands], dtype=np.uint8)
        free = (states == 0) & (band >= 0)
        states = states.copy()
        states[free] = colour[band[free]]

    V, F = weld(tri)
    ok = (F[:, 0] != F[:, 1]) & (F[:, 1] != F[:, 2]) & (F[:, 0] != F[:, 2])
    F, states = F[ok], states[ok]
    states, specks, speck_area = merge_specks(V, F, states, float(p.get("speck_mm2", 0.5)))

    a = area(V[F].astype(np.float64))
    by = np.bincount(states, weights=a, minlength=len(palette))
    counts = np.bincount(states, minlength=len(palette))
    used = [i for i in range(len(palette)) if counts[i] > 0]
    if len(used) < 2:
        raise Invalid("one_color")
    # the filaments in the palette's order; the model's own extruder is the colour with the largest area (the fewest attributes)
    filament_of = {s: i + 1 for i, s in enumerate(used)}
    own = filament_of[max(used, key=lambda s: by[s])]
    filaments = np.asarray([filament_of.get(int(s), own) for s in range(len(palette))], dtype=np.int64)[states]
    write_3mf(dst, V, F, filaments, [palette[s] for s in used], own, p.get("name") or "model")
    total = float(by.sum()) or 1.0
    out({"ok": True, "out": dst, "triangles": int(len(F)), "vertices": int(len(V)), "source_triangles": n, "refined": refined, "cut": cut,
         "colors": [{"hex": palette[s], "state": s, "filament": filament_of[s], "triangles": int(counts[s]), "share": round(float(by[s]) / total, 4)} for s in sorted(used, key=lambda s: -by[s])],
         "specks": {"count": int(specks), "mm2": round(speck_area, 3)}, "height": round(float(hi[2] - lo[2]), 3)})


def sample(dst, triangles):
    """A bumpy ball of about `triangles` triangles: something to paint and to measure with."""
    import numpy as np
    from art_tool import write_stl
    n = max(8, int(round(triangles ** 0.5)))
    u = np.linspace(0, 2 * np.pi, n + 1)
    v = np.linspace(0, np.pi, n // 2 + 1)
    uu, vv = np.meshgrid(u, v)
    r = 30.0 * (1 + 0.08 * np.sin(5 * uu) * np.sin(4 * vv))
    P = np.stack([r * np.sin(vv) * np.cos(uu), r * np.sin(vv) * np.sin(uu), r * np.cos(vv)], axis=2)
    P[:, -1] = P[:, 0]
    P[0], P[-1] = P[0].mean(axis=0), P[-1].mean(axis=0)
    a, b, c, d = P[:-1, :-1], P[:-1, 1:], P[1:, 1:], P[1:, :-1]
    tri = np.concatenate([np.stack([a, d, c], axis=2).reshape(-1, 3, 3), np.stack([a, c, b], axis=2).reshape(-1, 3, 3)])
    keep = area(tri) > 1e-9
    tri = tri[keep]
    tri[:, :, 2] -= tri[:, :, 2].min()
    write_stl(dst, np.asarray(tri, dtype=np.float32))
    out({"ok": True, "out": dst, "triangles": int(len(tri))})


def main(argv):
    try:
        if len(argv) >= 5 and argv[1] == "build":
            raw = argv[4]
            if raw.startswith("@"):
                with open(raw[1:], encoding="utf-8") as fh:
                    raw = fh.read()
            build(argv[2], argv[3], json.loads(raw or "{}"), argv[5] if len(argv) > 5 else None)
        if len(argv) >= 4 and argv[1] == "sample":
            sample(argv[2], int(argv[3]))
        out({"ok": False, "code": "usage", "error": "usage"})
    except Invalid as e:
        out({"ok": False, "code": e.code, "error": str(e)})
    except SystemExit:
        raise
    except Exception as e:  # noqa: BLE001
        out({"ok": False, "code": "failed", "error": str(e)})


if __name__ == "__main__":
    main(sys.argv)
