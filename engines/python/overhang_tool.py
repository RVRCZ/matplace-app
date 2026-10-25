"""Overhang share of a mesh, the way the calculator's browser measures it (resources/js/calc/geometry.ts stats()):
the share of the surface (by area) that faces down steeper than 45°, the bottom itself left out. For the estimate
range by shape (docs/M.md §6): `php artisan matplace:estimate-range --export --overhang` counts it for the files of
old calculations, so the range's overhang term can be calibrated on real slices.

    python overhang_tool.py model.stl        -> {"overhang_share": 0.31, "triangles": 1804554, "area_mm2": ...}
"""
import json
import sys


def overhang_share(path: str) -> dict:
    import numpy as np
    import trimesh

    mesh = trimesh.load(path, force='mesh', process=False)
    if not isinstance(mesh, trimesh.Trimesh) or len(mesh.faces) == 0:
        return {'overhang_share': None, 'triangles': 0, 'area_mm2': 0.0}
    normals = mesh.face_normals            # trimesh orients them by the winding; a mesh wound inside out is flipped below
    areas = mesh.area_faces
    if mesh.volume < 0:
        normals = -normals
    floor = float(mesh.bounds[0][2]) + 0.5
    z = mesh.vertices[mesh.faces][:, :, 2]  # the three corners' heights of every face
    off_floor = (z > floor).any(axis=1)
    down = (normals[:, 2] < -0.70710678) & off_floor
    area = float(areas.sum())
    return {
        'overhang_share': round(float(areas[down].sum()) / area, 4) if area > 0 else None,
        'triangles': int(len(mesh.faces)),
        'area_mm2': round(area, 1),
        'volume_mm3': round(abs(float(mesh.volume)), 1),
    }


if __name__ == '__main__':
    if len(sys.argv) < 2:
        print(__doc__)
        sys.exit(2)
    print(json.dumps(overhang_share(sys.argv[1])))
