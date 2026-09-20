#!/usr/bin/env python3
"""
Mesh utility for matplace engines (trimesh, optional pymeshfix, optional cadquery/OCP for CAD formats).

  mesh_tool.py probe                                  -> {"ok": true, "trimesh": "x.y.z", "pymeshfix": bool, "cad": bool}
  mesh_tool.py check <in>                             -> geometry + topology report (mm)
  mesh_tool.py repair <in> <out.stl>                  -> repaired binary STL + report
  mesh_tool.py convert <in> <out.stl>                 -> any trimesh-readable mesh format to binary STL (scene merged)
  mesh_tool.py normalize <in> <out.stl> <max_mm> <yup 0|1> [clean,pedestal,solid] [extras-json]
                                                          extras: {"pedestal": round|square|hexagon|column|plaque, "name": "…", "dedication": "…", "font": path}
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
    return fixed[0] if len(fixed) == 1 else trimesh.util.concatenate(fixed)


def pedestal_mesh(kind, cx, cy, r, ped_h, overlap, extras):
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
    mesh = solid.translate([cx, cy, -ped_h]).to_mesh()
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
            m.apply_translation([-m.bounds[0][0], -m.bounds[0][1], -m.bounds[0][2]])
            if "pedestal" in opts:
                # round base under the figure: flat first layer, no supports under a ragged cut, stands on a shelf.
                # It overlaps the model by a few millimetres; slicers merge overlapping shells.
                h = float(m.extents[2])
                low = m.vertices[m.vertices[:, 2] <= h * 0.10]
                cx, cy = float(low[:, 0].mean()), float(low[:, 1].mean())
                r = float(np.percentile(np.hypot(low[:, 0] - cx, low[:, 1] - cy), 92)) * 1.08
                r = max(r, target * 0.18)
                r = min(r, float(max(m.extents[0], m.extents[1])) * 0.55)   # never much wider than the figure itself
                ped_h = max(3.0, target * 0.05)
                overlap = max(2.0, target * 0.035)
                try:
                    ped, ped_note = pedestal_mesh(str(extras.get("pedestal", "round")), cx, cy, r, ped_h, overlap, extras)
                except Exception as e:  # noqa: BLE001 - fall back to the plain round base
                    ped = trimesh.creation.cylinder(radius=r, height=ped_h + overlap, sections=96)
                    ped.apply_translation([cx, cy, (ped_h + overlap) / 2.0 - ped_h])
                    ped_note = {"pedestal": "round", "pedestal_error": str(e)[:120]}
                m = solidify(m, target, ped) if "solid" in opts else trimesh.util.concatenate([m, ped])
                m.apply_translation([-m.bounds[0][0], -m.bounds[0][1], -m.bounds[0][2]])
                m.apply_scale(target / float(max(m.extents)))
            elif "solid" in opts:
                m = solidify(m, target, None)
                m.apply_translation([-m.bounds[0][0], -m.bounds[0][1], -m.bounds[0][2]])
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
