#!/usr/bin/env python3
"""
Mesh utility for matplace engines (trimesh, optional pymeshfix, optional cadquery/OCP for CAD formats).

  mesh_tool.py probe                                  -> {"ok": true, "trimesh": "x.y.z", "pymeshfix": bool, "cad": bool}
  mesh_tool.py check <in>                             -> geometry + topology report (mm)
  mesh_tool.py repair <in> <out.stl>                  -> repaired binary STL + report
  mesh_tool.py convert <in> <out.stl>                 -> any trimesh-readable mesh format to binary STL (scene merged)
  mesh_tool.py normalize <in> <out.stl> <max_mm> <yup 0|1> [clean,pedestal,solid] [extras-json]
                                                          extras: {"pedestal": round|square|hexagon|column|plaque|none, "name": "…", "dedication": "…", "font": path,
                                                                   "front": auto|keep|left|right|back, "source_out": path of the figure without a base,
                                                                   "strip_pedestal": true}
                                                       -> millimetres (largest side = max_mm), Z-up, on the bed;
                                                          clean = drop dust fragments, pedestal = flat round base,
                                                          solid = close holes and union all parts into one watertight body
  mesh_tool.py cad <in.step|iges> <out.stl> [lin] [ang] -> CAD B-rep to STL via OpenCascade (cadquery); lin=mm, ang=rad

Always prints exactly one JSON object on stdout.
"""
import json
import sys


def out(obj):
    print(json.dumps(obj))
    sys.exit(0)


def fail(msg):
    out({"ok": False, "error": str(msg)})


def load(path):
    import trimesh
    m = trimesh.load(path, force="mesh")
    if isinstance(m, trimesh.Scene):
        geoms = list(m.geometry.values())
        if not geoms:
            raise ValueError("empty scene")
        m = trimesh.util.concatenate(geoms)
    if m.is_empty or len(m.faces) == 0:
        raise ValueError("mesh has no faces")
    # Unit guess: printing meshes are in mm; values above 2000 are likely inches.
    if float(max(m.extents)) > 2000:
        m.apply_scale(25.4)
    return m


def report(m, extra=None):
    bb = m.extents
    shells = 1
    try:
        shells = len(m.split(only_watertight=False))
    except Exception:
        pass
    issues = []
    if not m.is_watertight:
        issues.append("not_watertight")
    if shells > 1:
        issues.append("multiple_shells")
    if m.is_watertight and not m.is_winding_consistent:
        issues.append("flipped_normals")
    if float(m.volume) < 0:
        issues.append("flipped_normals")
    r = {
        "ok": True,
        "watertight": bool(m.is_watertight),
        "volume_mm3": abs(float(m.volume)) if m.is_watertight else abs(float(m.convex_hull.volume)) * 0.6,
        "area_mm2": float(m.area),
        "bbox": {"x": float(bb[0]), "y": float(bb[1]), "z": float(bb[2])},
        "triangles": int(len(m.faces)),
        "shells": int(shells),
        "flipped_normals": "flipped_normals" in issues,
        "issues": sorted(set(issues)),
    }
    if extra:
        r.update(extra)
    return r


def solidify(m, target, extra=None):
    """
    Generated meshes have open edges and loose shells; slicers then guess. Close every real part (pymeshfix),
    drop dust, and union everything (manifold3d) into one watertight body. Every step falls back to the input.
    """
    import trimesh
    limit = target * 0.02
    parts = [p for p in m.split(only_watertight=False) if float(max(p.extents)) >= limit and len(p.faces) >= 4]
    if not parts:
        parts = [m]
    fixed = []
    for p in parts:
        q = p
        if not p.is_watertight:
            try:
                import pymeshfix
                fx = pymeshfix.MeshFix(p.vertices, p.faces)
                fx.repair()
                vv = getattr(fx, "points", None)
                ff = getattr(fx, "faces", None)
                if vv is None or ff is None:
                    vv, ff = fx.v, fx.f
                c = trimesh.Trimesh(vv, ff, process=True)
                # accept only a repair that kept the shape (pymeshfix may throw away a badly broken part)
                if len(c.faces) and c.is_watertight and abs(float(max(c.extents)) - float(max(p.extents))) <= 0.03 * float(max(p.extents)):
                    q = c
            except Exception:
                pass
        if q.is_watertight and q.volume < 0:
            q.invert()
        fixed.append(q)
    if extra is not None:
        fixed.append(extra)
    if len(fixed) > 1 and all(f.is_watertight for f in fixed):
        try:
            u = trimesh.boolean.union(fixed, engine="manifold")
            if len(u.faces) and u.is_watertight:
                return u
        except Exception:
            pass
    if len(fixed) == 1 and fixed[0].is_watertight:
        return fixed[0]
    # torn surfaces that cannot be closed exactly (hundreds of open pieces): rebuild the shape through a voxel grid
    try:
        body = rebuild_solid(trimesh.util.concatenate(fixed[:-1] if extra is not None else fixed), target)
        if extra is None:
            return body
        u = trimesh.boolean.union([body, extra], engine="manifold")
        if len(u.faces) and u.is_watertight:
            return u
    except Exception:
        pass
    return fixed[0] if len(fixed) == 1 else trimesh.util.concatenate(fixed)


def rebuild_solid(m, target):
    """
    One closed body from any soup of surfaces: voxelise the surface, close the gaps, fill the inside, mesh it again.
    Detail is limited by the grid (about 0.3 mm on an 80 mm figure), which is below what a nozzle prints.
    """
    import numpy as np
    import trimesh
    from scipy import ndimage
    from skimage import measure

    pad = 4
    pitch = max(0.3, float(max(m.extents)) / 220.0)
    vox = m.voxelized(pitch)
    grid = np.pad(vox.matrix, pad)
    closed = ndimage.binary_closing(grid, structure=ndimage.generate_binary_structure(3, 1), iterations=3)
    # inside = enclosed when looked at along at least two of the three axes; survives holes that a flood fill would leak through
    votes = np.zeros(closed.shape, dtype=np.uint8)
    for axis in range(3):
        moved = np.moveaxis(closed, axis, 0)
        votes += np.moveaxis(np.stack([ndimage.binary_fill_holes(layer) for layer in moved]), 0, axis)
    solid = (votes >= 2) | closed
    solid = ndimage.binary_opening(solid, iterations=1) | closed            # drops whiskers, keeps every real surface voxel
    field = ndimage.gaussian_filter(solid.astype(np.float32), 0.8)
    verts, faces, _, _ = measure.marching_cubes(field, level=0.5)
    verts = (verts - pad) * pitch + vox.transform[:3, 3]
    out_mesh = trimesh.Trimesh(verts, faces[:, ::-1], process=True)
    bodies = [b for b in out_mesh.split(only_watertight=True) if b.volume > 0]
    if not bodies:
        raise ValueError("rebuild produced no closed body")
    biggest = max(b.volume for b in bodies)
    keep = [b for b in bodies if b.volume >= biggest * 0.02]
    body = keep[0] if len(keep) == 1 else trimesh.boolean.union(keep, engine="manifold")
    return simplified(body, pitch * 0.12)


def simplified(mesh, tolerance):
    """Marching cubes gives hundreds of thousands of tiny triangles; merge the flat ones so the file stays small."""
    try:
        import numpy as np
        import trimesh
        import manifold3d as M
        man = M.Manifold(M.Mesh(vert_properties=np.asarray(mesh.vertices, dtype=np.float32), tri_verts=np.asarray(mesh.faces, dtype=np.uint32)))
        if man.is_empty():
            return mesh
        res = man.simplify(tolerance).to_mesh()
        slim = trimesh.Trimesh(vertices=np.asarray(res.vert_properties)[:, :3], faces=np.asarray(res.tri_verts), process=True)
        return slim if len(slim.faces) and slim.is_watertight else mesh
    except Exception:
        return mesh


def face_front(m):
    """
    Busts do not always arrive looking at -Y. Shoulders are the wide axis; the head sits in front of the chest.
    Returns the quarter turns about Z (0-3) that bring the face to -Y; 0 when the shape gives no clear answer.
    """
    import numpy as np
    c, a = m.triangles_center, m.area_faces
    z0, h = float(m.bounds[0][2]), float(m.extents[2])
    low = (c[:, 2] < z0 + 0.45 * h)
    top = (c[:, 2] > z0 + 0.62 * h)
    if low.sum() < 10 or top.sum() < 10:
        return 0
    spread = c[low][:, :2].max(0) - c[low][:, :2].min(0)
    if max(spread) < 1.25 * min(spread):
        return 0                                                          # no clear shoulder line
    depth_axis = 0 if spread[1] > spread[0] else 1
    chest = np.average(c[low][:, depth_axis], weights=a[low])
    head = np.average(c[top][:, depth_axis], weights=a[top])
    if abs(head - chest) < 0.03 * h:
        return 0
    forward = 1 if head > chest else -1
    # quarter turns counter-clockwise: +X -> 3 (to -Y), +Y -> 2, -X -> 1, -Y -> 0
    return {(0, 1): 3, (1, 1): 2, (0, -1): 1, (1, -1): 0}[(depth_axis, forward)]


def seat_of(m):
    """
    Where the base has to reach: the lowest level at which the figure is really there. A hanging hand or a strand
    of hair below the chest must not decide it, otherwise the body floats above the base.
    Returns (z_seat, cx, cy, r) from the slice of the body just above that level.
    """
    import numpy as np
    z0, h = float(m.bounds[0][2]), float(m.extents[2])
    z_seat = z0
    try:
        import manifold3d as M
        man = M.Manifold(M.Mesh(vert_properties=np.asarray(m.vertices, dtype=np.float32), tri_verts=np.asarray(m.faces, dtype=np.uint32)))
        levels = np.linspace(z0 + 0.003 * h, z0 + 0.35 * h, 48)
        areas = np.array([man.slice(float(z)).area() for z in levels])
        if areas.max() > 0:
            z_seat = float(levels[int(np.argmax(areas >= 0.5 * areas.max()))])
    except Exception:
        pass
    v = m.vertices
    band = v[(v[:, 2] >= z_seat) & (v[:, 2] <= z_seat + 0.10 * h)]
    if len(band) < 10:
        band = v[v[:, 2] <= z0 + 0.10 * h]
    cx, cy = float((band[:, 0].min() + band[:, 0].max()) / 2), float((band[:, 1].min() + band[:, 1].max()) / 2)
    r = float(np.percentile(np.hypot(band[:, 0] - cx, band[:, 1] - cy), 92)) * 1.08
    return z_seat, cx, cy, r


def without_pedestal(m):
    """Older generated files carry their base as a separate closed part standing on the bed; take it away again."""
    import trimesh
    parts = m.split(only_watertight=False)
    rest = [p for p in parts if not (p.is_watertight and abs(float(p.bounds[0][2]) - float(m.bounds[0][2])) < 0.05 and float(p.extents[2]) < 0.4 * float(m.extents[2]) and len(p.faces) < 20000)]
    if len(rest) == len(parts) or not rest:
        raise ValueError("no_separate_pedestal")
    return trimesh.util.concatenate(rest)


def pedestal_mesh(kind, cx, cy, r, ped_h, overlap, extras, z_top=None):
    """
    Base under a figure or bust, built as one exact solid (manifold3d) and handed back as a trimesh.
    "plaque" is a taller plinth with a flat front that carries a name and an optional dedication, raised 1 mm.
    The front of a generated model looks towards -Y.
    """
    import os
    import numpy as np
    import trimesh
    import manifold3d as M
    sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
    import shape2d as S

    def rounded(w, d, rad):
        return S.rounded_rect(M, w, d, rad).translate([-w / 2, -d / 2])

    note = {"pedestal": kind}
    if kind == "square":
        solid = rounded(2 * r, 2 * r, r * 0.12).extrude(ped_h + overlap)
    elif kind == "hexagon":
        a = np.linspace(0, 2 * np.pi, 6, endpoint=False) + np.pi / 6
        solid = M.CrossSection([np.stack([r * 1.12 * np.cos(a), r * 1.12 * np.sin(a)], 1)]).extrude(ped_h + overlap)
    elif kind == "column":
        ped_h = ped_h * 2.2
        step = ped_h * 0.35
        solid = M.Manifold.cylinder(step, r * 1.15, r * 1.15, 96) + M.Manifold.cylinder(ped_h + overlap - step, r * 0.92, r * 0.92, 96).translate([0, 0, step])
    elif kind == "plaque":
        ped_h = max(ped_h * 2.6, 12.0)
        w, d = 2 * r * 1.05, 2 * r * 0.9
        solid = rounded(w, d, 2.0).extrude(ped_h + overlap)
        lines = [t for t in (str(extras.get("name", "")).strip()[:24], str(extras.get("dedication", "")).strip()[:40]) if t]
        font = extras.get("font")
        if lines and font and os.path.isfile(font):
            try:
                y_top = ped_h * 0.86
                for i, line in enumerate(lines):
                    band = ped_h * (0.42 if i == 0 else 0.24)            # the name is the big line, the dedication the small one
                    cs, info = S.text(M, [line], font, band * 0.72)
                    tw, th = S.size(cs)
                    if tw > w * 0.86:
                        cs = S.fit(cs, width_mm=w * 0.86)
                        tw, th = S.size(cs)
                    x0, y0, _, _ = cs.bounds()
                    flat = cs.translate([-x0 - tw / 2, -y0]).extrude(1.2)
                    y_top -= band
                    # up stays up, the extrusion points at the viewer (-Y); 0.2 mm sits inside the plinth so the union is clean
                    solid = solid + flat.rotate([90, 0, 0]).translate([0, -d / 2 + 0.2, y_top + (band - th) / 2])
                    if info.get("missing_chars"):
                        note["missing_chars"] = info["missing_chars"]
                note["engraved_lines"] = len(lines)
            except Exception as e:  # noqa: BLE001 - a name that cannot be set must never lose the customer their model
                note["text_error"] = str(e)[:120]
    else:
        kind = "round"
        solid = M.Manifold.cylinder(ped_h + overlap, r, r, 96)
    # the top of the base ends `overlap` inside the figure
    mesh = solid.translate([cx, cy, (overlap if z_top is None else z_top) - (ped_h + overlap)]).to_mesh()
    note["pedestal"] = kind
    note["pedestal_height"] = round(float(ped_h), 1)
    return trimesh.Trimesh(vertices=np.asarray(mesh.vert_properties)[:, :3], faces=np.asarray(mesh.tri_verts), process=True), note


def cad_to_stl(src, dst, lin=0.05, ang=0.3):
    """STEP/IGES/BREP → STL through OpenCascade (same kernel FreeCAD uses)."""
    import cadquery as cq
    from cadquery import exporters
    ext = src.lower().rsplit(".", 1)[-1]
    if ext in ("step", "stp"):
        shape = cq.importers.importStep(src)
    elif ext in ("iges", "igs"):
        # cadquery has no IGES importer; use OCP directly
        from OCP.IGESControl import IGESControl_Reader
        from OCP.IFSelect import IFSelect_RetDone
        reader = IGESControl_Reader()
        if reader.ReadFile(src) != IFSelect_RetDone:
            raise ValueError("cannot read IGES")
        reader.TransferRoots()
        shape = cq.Workplane("XY").newObject([cq.Shape.cast(reader.OneShape())])
    elif ext == "brep":
        from OCP.BRepTools import BRepTools
        from OCP.TopoDS import TopoDS_Shape
        from OCP.BRep import BRep_Builder
        s = TopoDS_Shape()
        BRepTools.Read_s(s, src, BRep_Builder())
        shape = cq.Workplane("XY").newObject([cq.Shape.cast(s)])
    else:
        raise ValueError("unsupported CAD format " + ext)
    exporters.export(shape, dst, exportType="STL", tolerance=lin, angularTolerance=ang)


def main(argv):
    if len(argv) < 2:
        fail("usage")
    cmd = argv[1]
    try:
        if cmd == "probe":
            import trimesh
            try:
                import pymeshfix  # noqa: F401
                pf = True
            except Exception:
                pf = False
            try:
                import cadquery  # noqa: F401
                cad = True
            except Exception:
                cad = False
            out({"ok": True, "trimesh": trimesh.__version__, "pymeshfix": pf, "cad": cad})
        if cmd == "check":
            out(report(load(argv[2])))
        if cmd == "convert":
            m = load(argv[2])
            m.export(argv[3], file_type="stl")
            out(report(m, {"out": argv[3]}))
        if cmd == "normalize":
            import numpy as np
            import trimesh
            m = trimesh.load(argv[2], force="mesh")
            if isinstance(m, trimesh.Scene):
                m = trimesh.util.concatenate(list(m.geometry.values()))
            if m.is_empty or len(m.faces) == 0:
                raise ValueError("mesh has no faces")
            target = float(argv[4])
            if len(argv) > 5 and argv[5] == "1":
                # glTF is Y-up; printers are Z-up: rotate +90 degrees about X
                m.apply_transform(trimesh.transformations.rotation_matrix(np.pi / 2, [1, 0, 0]))
            ext = float(max(m.extents))
            if ext <= 0:
                raise ValueError("degenerate mesh")
            opts = set((argv[6] if len(argv) > 6 else "").split(","))
            try:
                extras = json.loads(argv[7]) if len(argv) > 7 and argv[7] else {}
            except ValueError:
                extras = {}
            ped_note = {}
            m.apply_scale(target / ext)
            removed = 0
            if "clean" in opts:
                # generated meshes carry tiny floating fragments; keep everything that is a real part (glasses, hair)
                parts = m.split(only_watertight=False)
                if len(parts) > 1:
                    limit = target * 0.02
                    keep = [p for p in parts if float(max(p.extents)) >= limit]
                    removed = len(parts) - len(keep)
                    if keep and removed:
                        m = trimesh.util.concatenate(keep)
            if extras.get("strip_pedestal"):
                m = without_pedestal(m)
            m.apply_translation([-m.bounds[0][0], -m.bounds[0][1], -m.bounds[0][2]])
            if "solid" in opts:
                m = solidify(m, target, None)
            front = str(extras.get("front", "keep"))
            turns = face_front(m) if front == "auto" else {"right": 3, "back": 2, "left": 1}.get(front, 0)      # where the face looks now, seen from the front
            if turns:
                m.apply_transform(trimesh.transformations.rotation_matrix(turns * np.pi / 2, [0, 0, 1]))
                ped_note["turned"] = turns * 90
            m.apply_translation([-m.bounds[0][0], -m.bounds[0][1], -m.bounds[0][2]])
            if extras.get("source_out"):
                # the figure alone, closed and facing the front: a different base later costs no new generation
                m.export(str(extras["source_out"]), file_type="stl")
            kind = str(extras.get("pedestal", "round"))
            if "pedestal" in opts and kind != "none":
                # base under the figure: flat first layer, no supports under a ragged cut, stands on a shelf
                z_seat, cx, cy, r = seat_of(m)
                r = max(r, target * 0.18)
                r = min(r, float(max(m.extents[0], m.extents[1])) * 0.55)   # never much wider than the figure itself
                ped_h = max(3.0, target * 0.05)
                overlap = max(2.0, target * 0.035)
                try:
                    ped, note = pedestal_mesh(kind, cx, cy, r, ped_h, overlap, extras, z_seat + overlap)
                except Exception as e:  # noqa: BLE001 - fall back to the plain round base
                    ped = trimesh.creation.cylinder(radius=r, height=ped_h + overlap, sections=96)
                    ped.apply_translation([cx, cy, z_seat + overlap - (ped_h + overlap) / 2.0])
                    note = {"pedestal": "round", "pedestal_error": str(e)[:120]}
                ped_note.update(note)
                floor = float(ped.bounds[0][2])
                if float(m.bounds[0][2]) < floor - 0.01:
                    # whatever hangs below the base (a hand, a strand of hair) would lift the print off the bed
                    try:
                        import manifold3d as M
                        cut = M.Manifold(M.Mesh(vert_properties=np.asarray(m.vertices, dtype=np.float32), tri_verts=np.asarray(m.faces, dtype=np.uint32))).trim_by_plane([0, 0, 1], floor).to_mesh()
                        if len(cut.tri_verts):
                            m = trimesh.Trimesh(vertices=np.asarray(cut.vert_properties)[:, :3], faces=np.asarray(cut.tri_verts), process=True)
                    except Exception:
                        pass
                joined = None
                if "solid" in opts and m.is_watertight:
                    try:
                        u = trimesh.boolean.union([m, ped], engine="manifold")
                        joined = u if len(u.faces) and u.is_watertight else None
                    except Exception:
                        joined = None
                m = joined if joined is not None else trimesh.util.concatenate([m, ped])
                m.apply_translation([-m.bounds[0][0], -m.bounds[0][1], -m.bounds[0][2]])
                m.apply_scale(target / float(max(m.extents)))
            m.export(argv[3], file_type="stl")
            out(report(m, dict({"out": argv[3], "removed_fragments": removed}, **ped_note)))
        if cmd == "cad":
            lin = float(argv[4]) if len(argv) > 4 else 0.05
            ang = float(argv[5]) if len(argv) > 5 else 0.3
            cad_to_stl(argv[2], argv[3], lin, ang)
            m = load(argv[3])
            m.export(argv[3], file_type="stl")  # normalise to binary STL
            out(report(m, {"out": argv[3]}))
        if cmd == "repair":
            m = load(argv[2])
            import trimesh
            trimesh.repair.fix_normals(m)
            trimesh.repair.fix_winding(m)
            trimesh.repair.fill_holes(m)
            if hasattr(m, "remove_degenerate_faces"):
                m.remove_degenerate_faces()
            if not m.is_watertight:
                try:
                    import pymeshfix
                    fx = pymeshfix.MeshFix(m.vertices, m.faces)
                    fx.repair()
                    m = trimesh.Trimesh(fx.v, fx.f)
                except Exception:
                    pass
            m.export(argv[3], file_type="stl")
            out(report(m, {"out": argv[3]}))
        fail("unknown command " + cmd)
    except SystemExit:
        raise
    except Exception as e:  # noqa: BLE001
        fail(e)


if __name__ == "__main__":
    main(sys.argv)
