#!/usr/bin/env python3
"""
Mesh utility for matplace engines (trimesh, optional pymeshfix).

  mesh_tool.py probe                      -> {"ok": true, "trimesh": "x.y.z", "pymeshfix": bool}
  mesh_tool.py check <in>                 -> geometry + topology report (mm)
  mesh_tool.py repair <in> <out.stl>      -> repaired binary STL + report
  mesh_tool.py convert <in> <out.stl>     -> any trimesh-readable format to binary STL (scene merged)

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
    import trimesh
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
            out({"ok": True, "trimesh": trimesh.__version__, "pymeshfix": pf})
        if cmd == "check":
            out(report(load(argv[2])))
        if cmd == "convert":
            m = load(argv[2])
            m.export(argv[3], file_type="stl")
            out(report(m, {"out": argv[3]}))
        if cmd == "repair":
            m = load(argv[2])
            import trimesh
            trimesh.repair.fix_normals(m)
            trimesh.repair.fix_winding(m)
            trimesh.repair.fill_holes(m)
            m.remove_degenerate_faces() if hasattr(m, "remove_degenerate_faces") else None
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
