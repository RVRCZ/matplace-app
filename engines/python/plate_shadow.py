"""The shadow of a model file on the plate as it lies: the convex hull of its vertices seen from above, the same
shape farm_tool.py keeps for a prepared piece (plate_shadow). For the calculator's "N pieces fit on one plate".

    python plate_shadow.py <model.stl|obj|3mf|...>
    → {"ok": true, "footprint": [[x, y], ...], "bbox": {"x", "y", "z"}, "triangles": n}
"""
import sys

from farm_tool import fail, out, plate_shadow


def main(argv):
    if len(argv) < 2:
        fail("usage: plate_shadow.py <model>")
    try:
        import numpy as np
        import trimesh
        m = trimesh.load(argv[1], force="mesh", process=False)
        if isinstance(m, trimesh.Scene):
            geoms = list(m.geometry.values())
            if not geoms:
                raise ValueError("empty scene")
            m = trimesh.util.concatenate(geoms)
        if m.is_empty or len(m.faces) == 0:
            raise ValueError("mesh has no faces")
        if not np.isfinite(m.vertices).all():
            raise ValueError("mesh has invalid coordinates")
        ex = m.extents
        out({
            "ok": True,
            "footprint": plate_shadow(m.vertices),
            "bbox": {"x": float(ex[0]), "y": float(ex[1]), "z": float(ex[2])},
            "triangles": int(len(m.faces)),
        })
    except SystemExit:
        raise
    except Exception as e:  # noqa: BLE001 - the caller needs one JSON object whatever happened
        fail(e)


if __name__ == "__main__":
    main(sys.argv)
