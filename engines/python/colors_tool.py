#!/usr/bin/env python3
"""
Colour Splitter: a coloured 3MF → one part per colour (the `colors` command of edit_tool.py lands here).

  colors_tool.py analyse <in.3mf>                                  what colours the file holds (edit_tool.py analyse hands over)
  colors_tool.py pack <out.3mf> <in.stl> <json|@file>               parts of one STL as a 3MF with a colour each (cards, tests);
                                                                    json: {"parts": [{"name", "tris": [from, to], "hex"}]}

Colours come from the 3MF core and material extensions (basematerials, m:colorgroup; on an object as pid/pindex, on a
triangle as pid/p1), from the slicers' extruder per object or volume (Bambu / Orca Metadata/model_settings.config,
Prusa Metadata/Slic3r_PE_model.config) and from painted triangles (Bambu / Orca paint_color, Prusa
slic3rpe:mmu_segmentation): a painted triangle the slicer subdivided takes the colour of most of its pieces. The
filaments' colours come from the project (filament_colour / extruder_colour) or from a fixed palette.

A colour that is a closed body of its own (an assembly) becomes a part as it is. A colour painted on a body becomes an
inlay: the painted faces pushed `depth` mm inward (1.2 by default) and closed with walls; the body loses that much (a
recess), so the parts print separately and fit together, or print in place as one multi-material job. The colour with
the largest area on a body is the body's own colour and stays its base. Parts are named color_1… by area; the answer
says which colour each one is.
"""
import json
import os
import re
import sys
import zipfile
import xml.etree.ElementTree as ET

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

CORE = "{http://schemas.microsoft.com/3dmanufacturing/core/2015/02}"
MAT = "{http://schemas.microsoft.com/3dmanufacturing/material/2015/02}"
PROD = "{http://schemas.microsoft.com/3dmanufacturing/production/2015/06}"
SLIC3R = "{http://schemas.slic3r.org/3mf/2017/06}"
# filaments 1… when the project names no colours (Bambu's default spools first)
PALETTE = ["#00AE42", "#FFFFFF", "#000000", "#F75403", "#1E88E5", "#FDD835", "#8E24AA", "#00ACC1", "#6D4C41", "#EC407A", "#7CB342", "#3949AB", "#FFB300", "#00897B", "#5E35B1", "#546E7A"]
BASE_HEX = "#BDBDBD"
PROUD = 0.05          # mm an inlay stands above the surface, so the recess boolean never meets a coincident face
MAX_COLORS = 16
MAX_TRIANGLES = 2_000_000


class Invalid(Exception):
    def __init__(self, code, detail=""):
        super().__init__(code + (": " + detail if detail else ""))
        self.code = code
        self.detail = detail


def out(obj):
    print(json.dumps(obj))
    sys.exit(0)


def _hex(s):
    """'#RRGGBBAA' / '#RRGGBB' → '#RRGGBB'; None when it is not a colour."""
    if not s:
        return None
    m = re.match(r"^#?([0-9a-fA-F]{6})([0-9a-fA-F]{2})?$", s.strip())
    return ("#" + m.group(1).upper()) if m else None


def _transform(s):
    if not s:
        return None
    p = s.split()
    try:
        return [float(v) for v in p[:12]] if len(p) >= 12 else None
    except ValueError:
        return None


def _mul(a, b):
    """Two 3 × 4 transforms (first a, then b), as the PHP converter composes them."""
    if not a:
        return b
    if not b:
        return a
    r = [0.0] * 12
    for i in range(3):
        for j in range(3):
            r[i * 3 + j] = sum(a[i * 3 + k] * b[k * 3 + j] for k in range(3))
    for j in range(3):
        r[9 + j] = sum(a[9 + k] * b[k * 3 + j] for k in range(3)) + b[9 + j]
    return r


def _apply(tf, V):
    import numpy as np
    if tf is None:
        return V
    R = np.array([[tf[0], tf[1], tf[2]], [tf[3], tf[4], tf[5]], [tf[6], tf[7], tf[8]]], dtype=np.float64)
    return V @ R + np.array(tf[9:12], dtype=np.float64)


def _paint_state(s):
    """
    The majority state of a painted triangle (the slicers' TriangleSelector: hex nibbles, the first one last in the
    string, bits low first; 2 bits of split sides, a leaf has 2 bits of state, 3 = 3 + 4 more bits; a split node has
    2 bits of its special side and then its children). 0 = the body's own colour. Also says whether it was split.
    """
    bits = []
    for ch in reversed(s.strip()):
        try:
            n = int(ch, 16)
        except ValueError:
            return 0, False
        bits.extend(((n >> i) & 1) for i in range(4))
    pos = [0]

    def read(k):
        v = 0
        for i in range(k):
            if pos[0] < len(bits):
                v |= bits[pos[0]] << i
            pos[0] += 1
        return v

    counts = {}
    split = [False]

    def node(weight, depth):
        if depth > 12 or pos[0] >= len(bits):
            return
        sides = read(2)
        if sides == 0:
            st = read(2)
            if st == 3:
                st = 3 + read(4)
            counts[st] = counts.get(st, 0.0) + weight
            return
        split[0] = True
        read(2)
        kids = sides + 1
        for _ in range(kids):
            node(weight / kids, depth + 1)

    node(1.0, 0)
    if not counts:
        return 0, split[0]
    return max(counts.items(), key=lambda kv: kv[1])[0], split[0]


class Model:
    def __init__(self):
        self.materials = {}     # (file, pid) -> [(name, hex)]
        self.objects = {}       # (file, id) -> {"mesh": (V, T, P), "components": [...], "pid", "pindex"}
        self.extruders = {}     # object id -> extruder from the slicer's settings
        self.volumes = {}       # object id -> [(first, last, extruder)]
        self.filaments = []     # hex per extruder (1-based)
        self.split = 0          # painted triangles the slicer subdivided
        self.build = []         # (file, object id, transform)


def _settings(z, names, model):
    # Bambu / Orca: the filaments' colours, the extruder of every object or part
    ps = names.get("metadata/project_settings.config")
    if ps:
        try:
            cfg = json.loads(z.read(ps).decode("utf-8", "replace"))
            model.filaments = [_hex(c) for c in (cfg.get("filament_colour") or [])]
        except ValueError:
            pass
    ms = names.get("metadata/model_settings.config")
    if ms:
        try:
            for el in ET.fromstring(z.read(ms)).iter():
                if el.tag in ("object", "part") and el.get("id"):
                    for md in el.findall("metadata"):
                        if md.get("key") == "extruder":
                            try:
                                model.extruders[el.get("id")] = int(md.get("value") or 0)
                            except ValueError:
                                pass
        except ET.ParseError:
            pass
    # Prusa: the extruders' colours in the ini, the extruder per object or volume in the model config
    ini = names.get("metadata/slic3r_pe.config")
    if ini and not any(model.filaments):
        for line in z.read(ini).decode("utf-8", "replace").splitlines():
            m = re.match(r"^;?\s*(extruder_colour|filament_colour)\s*=\s*(.+)$", line)
            if m:
                hexes = [_hex(c) for c in m.group(2).split(";")]
                if any(hexes):
                    model.filaments = hexes
    mc = names.get("metadata/slic3r_pe_model.config")
    if mc:
        try:
            for o in ET.fromstring(z.read(mc)).findall("object"):
                oid = o.get("id")
                for md in o.findall("metadata"):
                    if md.get("key") == "extruder":
                        try:
                            model.extruders[oid] = int(md.get("value") or 0)
                        except ValueError:
                            pass
                for v in o.findall("volume"):
                    e = 0
                    for md in v.findall("metadata"):
                        if md.get("key") == "extruder":
                            try:
                                e = int(md.get("value") or 0)
                            except ValueError:
                                pass
                    if e and v.get("firstid") is not None and v.get("lastid") is not None:
                        model.volumes.setdefault(oid, []).append((int(v.get("firstid")), int(v.get("lastid")), e))
        except ET.ParseError:
            pass


def _load_file(z, names, file, model, loaded):
    key = file.lstrip("/").lower()
    if key in loaded:
        return
    loaded.add(key)
    real = names.get(key)
    if not real:
        return
    root = ET.fromstring(z.read(real))
    res = root.find(CORE + "resources")
    if res is not None:
        for bm in res.findall(CORE + "basematerials"):
            model.materials[(key, bm.get("id"))] = [(b.get("name") or "", _hex(b.get("displaycolor"))) for b in bm.findall(CORE + "base")]
        for cg in res.findall(MAT + "colorgroup"):
            model.materials[(key, cg.get("id"))] = [("", _hex(c.get("color"))) for c in cg.findall(MAT + "color")]
        for o in res.findall(CORE + "object"):
            entry = {"pid": o.get("pid"), "pindex": o.get("pindex")}
            mesh = o.find(CORE + "mesh")
            if mesh is not None:
                vs = mesh.find(CORE + "vertices")
                ts = mesh.find(CORE + "triangles")
                V = [(float(v.get("x")), float(v.get("y")), float(v.get("z"))) for v in vs] if vs is not None else []
                T, P = [], []
                for t in (ts if ts is not None else []):
                    T.append((int(t.get("v1")), int(t.get("v2")), int(t.get("v3"))))
                    P.append((t.get("pid"), t.get("p1"), t.get("paint_color") or t.get(SLIC3R + "mmu_segmentation")))
                entry["mesh"] = (V, T, P)
            comps = o.find(CORE + "components")
            if comps is not None:
                entry["components"] = [((c.get(PROD + "path") or key).lstrip("/").lower(), c.get("objectid"), _transform(c.get("transform"))) for c in comps.findall(CORE + "component")]
                for pth, _, _ in entry["components"]:
                    _load_file(z, names, pth, model, loaded)
            model.objects[(key, o.get("id"))] = entry
    build = root.find(CORE + "build")
    if build is not None and key == "3d/3dmodel.model":
        for it in build.findall(CORE + "item"):
            pth = (it.get(PROD + "path") or key).lstrip("/").lower()
            model.build.append((pth, it.get("objectid"), _transform(it.get("transform"))))
            _load_file(z, names, pth, model, loaded)


def read_3mf(path):
    try:
        z = zipfile.ZipFile(path)
    except (zipfile.BadZipFile, OSError):
        raise Invalid("not_3mf")
    names = {n.lower(): n for n in z.namelist()}
    if "3d/3dmodel.model" not in names:
        raise Invalid("not_3mf")
    model = Model()
    _settings(z, names, model)
    try:
        _load_file(z, names, "3d/3dmodel.model", model, set())
    except ET.ParseError as e:
        raise Invalid("not_3mf", str(e))
    return model


def gather(model):
    """Every triangle of the build in world space with the index of its colour; the colours as (name, hex, extruder, sources)."""
    import numpy as np
    tris, keys = [], []
    colors, index, sources = [], {}, []

    def key_id(k, name, hexv, extruder, source):
        if k not in index:
            index[k] = len(colors)
            colors.append((name, hexv, extruder))
            sources.append(set())
        sources[index[k]].add(source)
        return index[k]

    def filament(n, source):
        hexv = (model.filaments[n - 1] if 0 < n <= len(model.filaments) else None) or PALETTE[(n - 1) % len(PALETTE)]
        return key_id(("extruder", n), "", hexv, n, source)

    def material(file, pid, idx, source):
        lst = model.materials.get((file, pid))
        try:
            idx = int(idx)
        except (TypeError, ValueError):
            return None
        if not lst or idx < 0 or idx >= len(lst):
            return None
        name, hexv = lst[idx]
        return key_id(("mat", file, pid, idx), name, hexv or BASE_HEX, None, "materials")

    def walk(file, oid, tf, depth):
        o = model.objects.get((file, oid))
        if o is None or depth > 8:
            return
        for pth, cid, ctf in o.get("components", []):
            walk(pth, cid, _mul(ctf, tf), depth + 1)
        if "mesh" not in o:
            return
        V, T, P = o["mesh"]
        if not T or not V:
            return
        Va = _apply(tf, np.asarray(V, dtype=np.float64))
        F = np.asarray(T, dtype=np.int64)
        ok = (F >= 0).all(axis=1) & (F < len(Va)).all(axis=1)
        e = model.extruders.get(oid, 0)
        obj_key = filament(e, "objects") if e > 0 else (material(file, o["pid"], o.get("pindex") or 0, "materials") if o.get("pid") is not None else None)
        vols = model.volumes.get(oid, [])
        ks = np.full(len(F), -1, dtype=np.int64)
        for i, (pid, p1, paint) in enumerate(P):
            k = None
            if paint:
                st, was_split = _paint_state(paint)
                if was_split:
                    model.split += 1
                if st > 0:
                    k = filament(st, "paint")
            if k is None and p1 is not None:
                k = material(file, pid if pid is not None else o.get("pid"), p1, "materials")
            if k is None:
                for a, b, ve in vols:
                    if a <= i <= b:
                        k = filament(ve, "objects")
                        break
            if k is None:
                k = obj_key if obj_key is not None else key_id(("base",), "", BASE_HEX, None, "none")
            ks[i] = k
        tris.append(Va[F[ok]])
        keys.append(ks[ok])

    for file, oid, tf in model.build:
        walk(file, oid, tf, 0)
    if not tris:
        raise Invalid("empty")
    tri = np.concatenate(tris)
    if len(tri) > MAX_TRIANGLES:
        raise Invalid("too_heavy", str(len(tri)))
    return tri, np.concatenate(keys), colors, [sorted(s) for s in sources]


def as_mesh(tri):
    import numpy as np
    import trimesh
    m = trimesh.Trimesh(vertices=tri.reshape(-1, 3), faces=np.arange(len(tri) * 3).reshape(-1, 3), process=False)
    m.merge_vertices()
    return m


def describe(m, keys, colors, sources):
    """The colours by area: name, hex, extruder, where they came from, triangles and share in per cent."""
    import numpy as np
    areas = np.bincount(keys, weights=m.area_faces, minlength=len(colors))
    counts = np.bincount(keys, minlength=len(colors))
    total = float(areas.sum()) or 1.0
    return [{"key": int(k), "name": colors[k][0], "hex": colors[k][1], "extruder": colors[k][2], "sources": sources[k], "triangles": int(counts[k]), "share": round(100.0 * float(areas[k]) / total, 1)}
            for k in np.argsort(-areas) if counts[k] > 0]


def analyse(src, p):
    import numpy as np
    if not str(src).lower().endswith(".3mf"):
        out({"ok": True, "colors": [], "has_colors": False, "not_3mf": True, "split_triangles": 0, "fits": True, "planes": None, "pieces": None, "too_many": False, "repaired": False})
    model = read_3mf(src)
    tri, keys, colors, sources = gather(model)
    m = as_mesh(tri)
    found = describe(m, keys, colors, sources)
    lo, hi = tri.reshape(-1, 3).min(axis=0), tri.reshape(-1, 3).max(axis=0)
    out({"ok": True, "bbox": {"x": round(float(hi[0] - lo[0]), 2), "y": round(float(hi[1] - lo[1]), 2), "z": round(float(hi[2] - lo[2]), 2)}, "triangles": int(len(tri)),
         "colors": found, "has_colors": len(found) > 1, "not_3mf": False, "split_triangles": int(model.split), "fits": True, "planes": None, "pieces": len(found), "too_many": False, "repaired": False})


def _closed(faces):
    import numpy as np
    edges = np.sort(faces[:, [[0, 1], [1, 2], [2, 0]]].reshape(-1, 2), axis=1)
    _, counts = np.unique(edges, axis=0, return_counts=True)
    return bool((counts == 2).all())


def _inlay(m, fidx, normals, depth):
    """The faces fidx of m as a solid: pushed `depth` inward along the vertex normals, closed with walls along the rim."""
    import numpy as np
    import trimesh
    import mesh_tool as T
    faces = m.faces[fidx]
    used, inv = np.unique(faces, return_inverse=True)
    # the normals of the painted faces alone: at a body's edge the body's own normal leans away and the inlay would come out shallow
    N = np.asarray(trimesh.Trimesh(vertices=m.vertices, faces=faces, process=False).vertex_normals, dtype=np.float64)[used]
    flat = np.linalg.norm(N, axis=1) < 1e-9
    if flat.any():
        N[flat] = normals[used][flat]
    top = m.vertices[used] + N * PROUD
    bottom = m.vertices[used] - N * depth
    F = inv.reshape(-1, 3)
    n = len(used)
    edges = F[:, [[0, 1], [1, 2], [2, 0]]].reshape(-1, 2)
    _, first, counts = np.unique(np.sort(edges, axis=1), axis=0, return_index=True, return_counts=True)
    rim = edges[first[counts == 1]]
    a, b = rim[:, 0], rim[:, 1]
    walls = np.concatenate([np.stack([b, a, a + n], 1), np.stack([b, a + n, b + n], 1)]) if len(rim) else np.zeros((0, 3), dtype=np.int64)
    inlay = trimesh.Trimesh(vertices=np.concatenate([top, bottom]), faces=np.concatenate([F, F[:, ::-1] + n, walls]), process=False)
    inlay.merge_vertices()
    if not inlay.is_watertight or not inlay.is_winding_consistent or abs(inlay.volume) < 1e-6:
        try:
            inlay = T.solidify(inlay, float(max(inlay.extents)))
        except Exception:  # noqa: BLE001
            return None
        if not inlay.is_watertight:
            return None
    if inlay.volume < 0:
        inlay.invert()
    return inlay


def _write(dst, parts, parts_dir):
    """Every part as its own STL and all of them in place as one; the list of parts with their triangle ranges."""
    import numpy as np
    from art_tool import write_stl
    listed, chunks, at = [], [], 0
    if parts_dir:
        os.makedirs(parts_dir, exist_ok=True)
    for name, mesh in parts:
        t = np.asarray(mesh.triangles, dtype=np.float32)
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


def colors(src, dst, p, parts_dir, stage=lambda d, n: None):
    import numpy as np
    import trimesh
    import manifold3d as M
    import mesh_tool as T
    depth = max(0.4, min(5.0, float(p.get("depth", 1.2) or 1.2)))
    stage(dst, "loading")
    if not str(src).lower().endswith(".3mf"):
        raise Invalid("no_colors", "not a 3mf")
    model = read_3mf(src)
    tri, keys, names, sources = gather(model)
    m = as_mesh(tri)
    found = describe(m, keys, names, sources)
    if len(found) < 2:
        raise Invalid("no_colors")
    notes = {"depth": depth, "split_triangles": int(model.split), "warnings": [], "shells": 0, "inlays": 0, "bases": 0, "triangles_in": int(len(tri))}
    if len(found) > MAX_COLORS:
        # a file with more colours than any printer has spools: the small ones join the largest
        keep = {c["key"] for c in found[:MAX_COLORS]}
        remap = np.arange(len(names))
        for c in found[MAX_COLORS:]:
            remap[c["key"]] = found[0]["key"]
        keys = remap[keys]
        found = [c for c in found if c["key"] in keep]
        notes["warnings"].append("many_colors")
    stage(dst, "cutting")
    labels = trimesh.graph.connected_component_labels(m.face_adjacency, node_count=len(m.faces))
    normals = np.asarray(m.vertex_normals, dtype=np.float64)
    areas = m.area_faces
    by_color = {c["key"]: [] for c in found}
    kinds = {c["key"]: set() for c in found}
    sub = lambda fi: m.submesh([fi], append=True, repair=False)
    for body in np.unique(labels):
        fi = np.flatnonzero(labels == body)
        bk = keys[fi]
        present = np.unique(bk)
        if len(present) == 1:
            k = int(present[0])
            by_color[k].append(sub(fi))
            kinds[k].add("shell")
            notes["shells"] += 1
            continue
        own = int(present[np.argmax([areas[fi[bk == k]].sum() for k in present])])
        closed = _closed(m.faces[fi])
        inlays = []
        for k in present:
            k = int(k)
            if k == own:
                continue
            inlay = _inlay(m, fi[bk == k], normals, depth)
            if inlay is None:
                if "inlay_failed" not in notes["warnings"]:
                    notes["warnings"].append("inlay_failed")
                continue
            by_color[k].append(inlay)
            kinds[k].add("inlay")
            inlays.append(inlay)
            notes["inlays"] += 1
        base = sub(fi)
        if inlays and closed:
            try:
                solid = T.as_manifold(base)
                cut = M.Manifold.batch_boolean([T.as_manifold(i) for i in inlays], M.OpType.Add)
                res = solid - cut
                if res.is_empty() or res.status() != M.Error.NoError:
                    raise ValueError("recess")
                base = T.from_manifold(res)
            except Exception:  # noqa: BLE001
                if "recess_failed" not in notes["warnings"]:
                    notes["warnings"].append("recess_failed")
        elif inlays and "body_open" not in notes["warnings"]:
            notes["warnings"].append("body_open")
        by_color[own].append(base)
        kinds[own].add("base")
        notes["bases"] += 1
    stage(dst, "layout")
    parts, report = [], []
    for i, c in enumerate(found):
        meshes = by_color[c["key"]]
        if not meshes:
            continue
        name = "color_%d" % (len(parts) + 1)
        whole = trimesh.util.concatenate(meshes) if len(meshes) > 1 else meshes[0]
        parts.append((name, whole))
        report.append({"part": name, "name": c["name"], "hex": c["hex"], "extruder": c["extruder"], "sources": c["sources"], "triangles": int(len(whole.faces)), "share": c["share"],
                       "kind": "mixed" if len(kinds[c["key"]]) > 1 else (next(iter(kinds[c["key"]])) if kinds[c["key"]] else "shell"), "bodies": len(meshes)})
    if not parts:
        raise Invalid("empty_result")
    listed, tri = _write(dst, parts, parts_dir)
    stage(dst, "done")
    notes["colors"] = report
    notes["parts"] = [name for name, _ in parts]
    notes["each"] = [[round(float(v), 1) for v in mesh.extents] for _, mesh in parts]
    lo, hi = tri.reshape(-1, 3).min(axis=0), tri.reshape(-1, 3).max(axis=0)
    volume = sum(float(abs(mesh.volume)) for _, mesh in parts if mesh.is_watertight)
    out({"ok": True, "kind": "colors", "part": "all", "bbox": {"x": round(float(hi[0] - lo[0]), 2), "y": round(float(hi[1] - lo[1]), 2), "z": round(float(hi[2] - lo[2]), 2)},
         "volume_mm3": round(volume, 1), "area_mm2": round(sum(float(mesh.area) for _, mesh in parts), 1), "triangles": int(len(tri)), "notes": notes, "parts": listed})


def pack(dst, stl, spec):
    """Parts of one STL (triangle ranges) as a 3MF with a base material each: what cards and tests feed the splitter."""
    import numpy as np
    import trimesh
    m = trimesh.load(stl, force="mesh", process=False)
    parts = [p for p in spec.get("parts", []) if p.get("tris")]
    if not parts:
        raise Invalid("empty")
    xml = ['<?xml version="1.0" encoding="UTF-8"?>',
           '<model unit="millimeter" xml:lang="en-US" xmlns="http://schemas.microsoft.com/3dmanufacturing/core/2015/02"><resources><basematerials id="1">']
    for p in parts:
        xml.append('<base name="%s" displaycolor="%sFF"/>' % (re.sub(r"[^A-Za-z0-9 _-]", "", str(p.get("name", "")))[:40], _hex(p.get("hex")) or BASE_HEX))
    xml.append("</basematerials>")
    for i, p in enumerate(parts):
        a, b = int(p["tris"][0]), int(p["tris"][1])
        piece = trimesh.Trimesh(vertices=m.vertices, faces=m.faces[a:b], process=False)
        piece.remove_unreferenced_vertices()
        xml.append('<object id="%d" type="model" pid="1" pindex="%d"><mesh><vertices>' % (i + 2, i))
        xml.extend('<vertex x="%.4f" y="%.4f" z="%.4f"/>' % tuple(v) for v in np.asarray(piece.vertices))
        xml.append("</vertices><triangles>")
        xml.extend('<triangle v1="%d" v2="%d" v3="%d"/>' % tuple(f) for f in np.asarray(piece.faces))
        xml.append("</triangles></mesh></object>")
    xml.append("</resources><build>")
    xml.extend('<item objectid="%d"/>' % (i + 2) for i in range(len(parts)))
    xml.append("</build></model>")
    with zipfile.ZipFile(dst, "w", zipfile.ZIP_DEFLATED) as z:
        z.writestr("[Content_Types].xml", '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                   '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="model" ContentType="application/vnd.ms-package.3dmanufacturing-3dmodel+xml"/></Types>')
        z.writestr("_rels/.rels", '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                   '<Relationship Target="/3D/3dmodel.model" Id="rel0" Type="http://schemas.microsoft.com/3dmanufacturing/2013/01/3dmodel"/></Relationships>')
        z.writestr("3D/3dmodel.model", "".join(xml))
    out({"ok": True, "out": dst, "parts": len(parts)})


def main(argv):
    try:
        if len(argv) >= 3 and argv[1] == "analyse":
            analyse(argv[2], {})
        if len(argv) >= 5 and argv[1] == "pack":
            raw = argv[4]
            if raw.startswith("@"):
                with open(raw[1:], encoding="utf-8") as fh:
                    raw = fh.read()
            pack(argv[2], argv[3], json.loads(raw or "{}"))
        out({"ok": False, "code": "usage", "error": "usage"})
    except Invalid as e:
        out({"ok": False, "code": e.code, "error": str(e)})
    except SystemExit:
        raise
    except Exception as e:  # noqa: BLE001
        out({"ok": False, "code": "failed", "error": str(e)})


if __name__ == "__main__":
    main(sys.argv)
