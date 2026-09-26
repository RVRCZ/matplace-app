"""Print advice for a model: numbers the geometry can tell for sure, and pictures for the advisor. One JSON on stdout.

  advise_tool.py analyze <in.stl> <out_dir> [overhang_deg]

Numbers (all in the model's own orientation, Z up, millimetres): size, volume, surface, closedness, separate bodies,
the area touching the plate, the footprint, overhang area steeper than <overhang_deg> (default 45) from vertical
facing down, the slenderness, wall thickness measured by rays from the surface inwards, and the orientation with the
least overhang and a stable base (the same rating the print farm uses before it prints).

Pictures: view_1..4.png in <out_dir>, 768 px, overhangs painted orange and the plate contact blue.
"""
import json
import math
import os
import sys

import numpy as np

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

VIEWS = [  # (elevation, azimuth, label)
    (25, -60, "front left, from above"),
    (25, 120, "back right, from above"),
    (0, -90, "front, level"),
    (-35, -45, "from below"),
]


def out(obj):
    print(json.dumps(obj))
    sys.exit(0)


def clustered(m, cells=180):
    """Vertex clustering onto a grid: a light copy for pictures and rays, never used for numbers of area or volume."""
    import trimesh

    if len(m.faces) <= 30000:
        return m
    size = float(max(m.extents)) / cells
    keys = np.floor((m.vertices - m.bounds[0]) / size).astype(np.int64)
    uniq, inverse = np.unique(keys, axis=0, return_inverse=True)
    inverse = inverse.reshape(-1)
    sums = np.zeros((len(uniq), 3))
    np.add.at(sums, inverse, m.vertices)
    counts = np.bincount(inverse, minlength=len(uniq))[:, None]
    faces = inverse[m.faces]
    keep = (faces[:, 0] != faces[:, 1]) & (faces[:, 1] != faces[:, 2]) & (faces[:, 0] != faces[:, 2])
    light = trimesh.Trimesh(sums / counts, faces[keep], process=False)
    light.remove_unreferenced_vertices()
    return light


def side_words(down):
    """Which side of the model, as uploaded, would lie on the plate."""
    axis = int(np.argmax(np.abs(down)))
    sign = down[axis] > 0
    names = {(2, False): "bottom (as uploaded)", (2, True): "top (upside down)", (1, False): "front side", (1, True): "back side",
             (0, False): "left side", (0, True): "right side"}
    if abs(down[axis]) < 0.95:
        return "tilted, resting on a slanted face"
    return names[(axis, bool(sign))]


def first_hits(origins, dirs, tris, chunk=40):
    """Distance along each ray to the nearest triangle (Moller-Trumbore, vectorised; no rtree/embree needed)."""
    v0 = tris[:, 0]
    e1 = tris[:, 1] - v0
    e2 = tris[:, 2] - v0
    best = np.full(len(origins), np.inf)
    for s in range(0, len(origins), chunk):
        o = origins[s:s + chunk, None, :]
        d = dirs[s:s + chunk, None, :]
        p = np.cross(d, e2[None])
        det = np.einsum("rtk,tk->rt", p, e1)
        ok = np.abs(det) > 1e-12
        inv = np.where(ok, 1.0 / np.where(ok, det, 1.0), 0.0)
        tv = o - v0[None]
        u = np.einsum("rtk,rtk->rt", tv, p) * inv
        q = np.cross(tv, e1[None])
        v = np.einsum("rtk,rtk->rt", np.broadcast_to(d, q.shape), q) * inv
        t = np.einsum("tk,rtk->rt", e2, q) * inv
        hit = ok & (u >= 0) & (v >= 0) & (u + v <= 1) & (t > 1e-4)
        best[s:s + chunk] = np.where(hit, t, np.inf).min(axis=1)
    return best


def thickness(m, samples=500):
    """Distance from sampled surface points straight inwards to the opposite surface."""
    import trimesh

    try:
        pts, idx = trimesh.sample.sample_surface_even(m, samples)
    except Exception:  # noqa: BLE001 - very small or odd meshes
        pts, idx = trimesh.sample.sample_surface(m, samples)
    dirs = -m.face_normals[idx]
    d = first_hits(pts + dirs * 1e-3, dirs, m.vertices[m.faces])
    d = d[np.isfinite(d)]
    if not len(d):
        return None
    return {
        "min_mm": round(float(np.percentile(d, 1)), 2),
        "median_mm": round(float(np.median(d)), 2),
        "share_under_0_8mm": round(float((d < 0.8).mean()), 3),
        "share_under_1_2mm": round(float((d < 1.2).mean()), 3),
    }


def paint(m, cos_limit):
    normals = m.face_normals
    facing = -normals[:, 2]
    z0 = float(m.bounds[0][2])
    face_z = m.vertices[m.faces][:, :, 2].min(axis=1)
    on_bed = (facing > 0.9995) & (face_z - z0 < 0.05)
    overhang = (facing > cos_limit) & ~on_bed
    return on_bed, overhang


def render(m, cos_limit, folder, px=768):
    """Orthographic pictures drawn back to front with Pillow (painter's algorithm), 2x supersampled."""
    from PIL import Image, ImageDraw

    light = clustered(m)
    on_bed, overhang = paint(light, cos_limit)
    colors = np.tile(np.array([0.72, 0.74, 0.78]), (len(light.faces), 1))
    colors[overhang] = [0.95, 0.55, 0.15]
    colors[on_bed] = [0.25, 0.45, 0.95]
    centre = (light.bounds[0] + light.bounds[1]) / 2
    radius = float(np.linalg.norm(light.extents)) / 2 or 1.0
    big = px * 2
    files = []
    for i, (elev, azim, label) in enumerate(VIEWS, 1):
        el, az = math.radians(elev), math.radians(azim)
        eye = np.array([math.cos(el) * math.cos(az), math.cos(el) * math.sin(az), math.sin(el)])   # towards the viewer
        right = np.cross([0.0, 0.0, 1.0], eye)
        right = right / (np.linalg.norm(right) or 1.0) if np.linalg.norm(right) > 1e-6 else np.array([1.0, 0.0, 0.0])
        up = np.cross(eye, right)
        v = light.vertices - centre
        sx = v @ right
        sy = v @ up
        depth = v @ eye
        scale = big * 0.46 / radius
        pts = np.stack([big / 2 + sx * scale, big / 2 - sy * scale], axis=1)
        facing = light.face_normals @ eye
        order = np.argsort(depth[light.faces].mean(axis=1))          # far first
        order = order[facing[order] > -0.05]                          # faces turned away are hidden anyway
        light_dir = eye * 0.55 + up * 0.55 + right * 0.3
        light_dir /= np.linalg.norm(light_dir)
        shade = 0.38 + 0.62 * np.clip(light.face_normals @ light_dir, 0, 1)
        rgb = np.clip(colors * shade[:, None], 0, 1) * 255
        im = Image.new("RGB", (big, big), (255, 255, 255))
        draw = ImageDraw.Draw(im)
        tri = pts[light.faces]
        for f in order:
            c = tuple(int(x) for x in rgb[f])
            draw.polygon([tuple(tri[f, 0]), tuple(tri[f, 1]), tuple(tri[f, 2])], fill=c, outline=c)
        im = im.resize((px, px), Image.LANCZOS)
        path = os.path.join(folder, f"view_{i}.png")
        im.save(path, optimize=True)
        files.append({"file": path, "label": label})
    return files


def analyze(src, folder, overhang_deg):
    import trimesh
    from farm_tool import candidates, footprint, rate

    m = trimesh.load(src, force="mesh")
    if m.is_empty or len(m.faces) == 0:
        out({"ok": False, "error": "empty"})
    os.makedirs(folder, exist_ok=True)
    cos_limit = math.cos(math.radians(overhang_deg))
    down = np.array([0.0, 0.0, -1.0])
    total = float(m.area) or 1.0
    bed = (1e9, 1e9, 1e9)

    # the orientation is chosen on the light copy (a scanned figure has ~1M faces); its numbers come from the real mesh
    light = clustered(m)
    score0 = rate(light, down, cos_limit, bed)[0]
    best_score, best_down = score0, down
    for d in candidates(light):
        s = rate(light, d, cos_limit, bed)[0]
        if s < best_score - 1e-6:
            best_score, best_down = s, d
    _, over0, base0, height0 = rate(m, down, cos_limit, bed)
    foot0 = footprint(m.vertices, down)
    ext = m.extents
    best = (best_score, best_down) + tuple(rate(m, best_down, cos_limit, bed)[1:])
    better = (over0 - best[2]) > 0.15 * max(over0, 1.0) and float(np.dot(best_down, down)) < 0.999

    try:
        bodies = len(m.split(only_watertight=False))
    except Exception:  # noqa: BLE001
        bodies = None
    facts = {
        "size_mm": [round(float(v), 1) for v in ext],
        "volume_cm3": round(abs(float(m.volume)) / 1000, 2) if m.is_watertight else None,
        "surface_cm2": round(total / 100, 1),
        "watertight": bool(m.is_watertight),
        "bodies": bodies,
        "faces": int(len(m.faces)),
        "as_uploaded": {
            "plate_contact_mm2": round(base0, 1),
            "footprint_mm2": round(foot0, 1),
            "contact_share_of_footprint": round(base0 / foot0, 3) if foot0 else 0,
            "overhang_mm2": round(over0, 1),
            "overhang_share_of_surface": round(over0 / total, 3),
            "height_mm": round(height0, 1),
            "slenderness_height_to_narrowest_base": round(height0 / max(min(ext[0], ext[1]), 0.1), 2),
        },
        "best_orientation": {
            "same_as_uploaded": not better,
            "rest_on": "bottom (as uploaded)" if not better else side_words(best[1]),
            "overhang_mm2": round(best[2], 1),
            "plate_contact_mm2": round(best[3], 1),
            "height_mm": round(best[4], 1),
        },
        "wall_thickness": thickness(light),
        "overhang_limit_deg": overhang_deg,
    }
    views = render(m, cos_limit, folder)
    out({"ok": True, "facts": facts, "views": views})


def main(argv):
    if len(argv) >= 3 and argv[0] == "analyze":
        analyze(argv[1], argv[2], float(argv[3]) if len(argv) > 3 else 45.0)
    out({"ok": False, "error": "usage"})


if __name__ == "__main__":
    main(sys.argv[1:])
