#!/usr/bin/env python3
"""
Mesh utility for matplace engines (trimesh, optional pymeshfix, optional cadquery/OCP for CAD formats).

  mesh_tool.py probe                                  -> {"ok": true, "trimesh": "x.y.z", "pymeshfix": bool, "cad": bool}
  mesh_tool.py check <in>                             -> geometry + topology report (mm)
  mesh_tool.py repair <in> <out.stl>                  -> repaired binary STL + report
  mesh_tool.py convert <in> <out.stl>                 -> any trimesh-readable mesh format to binary STL (scene merged)
  mesh_tool.py normalize <in> <out.stl> <max_mm> <yup 0|1>  -> millimetres (largest side = max_mm), Z-up, on the bed
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
            m.apply_scale(target / ext)
            m.apply_translation([-m.bounds[0][0], -m.bounds[0][1], -m.bounds[0][2]])
            m.export(argv[3], file_type="stl")
            out(report(m, {"out": argv[3]}))
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
