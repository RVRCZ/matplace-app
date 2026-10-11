"""
A pet figurine (the tool /tools/pet-figurine): what is done to the generated animal before it is printed.

    thinnest(m)                  the thinnest carrying place of the figure (a leg, the tail), in millimetres
    thicken(m, target, level)    the "tabletop miniature" look: everything a little fatter and smoother, like modelled clay
    stand(m, kind, extras, target)   the base under the paws, with the pet's name on it

All sizes are millimetres of the print. The animal comes from the generator standing on its feet. Its front (-Y) is
the side the photo was taken from, which is not always where the head points: a dog photographed from the side shows
its flank there, a cat looking into the camera its face. So the side the name stands on can be chosen (`name_side`).

Why this is its own module: an animal stands on four thin legs. The bases of a bust (mesh_tool.pedestal_mesh) are sized
by a circle round the chest and would be a slab as wide as the dog is long; and the legs and the tail are exactly what
breaks off a print, so their thickness is measured and, for the miniature, made up.
"""
import os
import sys

import numpy as np

# under this a leg or a tail snaps when the print is taken off the bed or handled
SAFE_MM = 2.5

# the miniature: how much is added all round (mm), by the three steps of the slider "roughness"
GROW_MM = {1: 0.8, 2: 1.0, 3: 1.2}
GROW_MOST = 1.6

# where the name can stand, and by how many degrees the text set at the front (-Y) is turned to get there
SIDES = {"front": 0, "right": 90, "back": 180, "left": 270}


def _grid(m, pitch, pad):
    """Occupied voxels of a closed mesh (see mesh_tool.surface_grid): (grid, origin)."""
    sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
    from mesh_tool import surface_grid
    return surface_grid(m, pitch, pad)


def thinnest(m, limit=6.0):
    """
    The thinnest place that carries something: the diameter of the thinnest leg or of the thin end of the tail.

    Tips do not count (an ear, the last millimetres of a tail and every toe taper to nothing), and neither does a thin
    ridge on a thick leg. What counts is a rod: the figure is "opened" with balls of growing size, and the first size
    at which a long piece of full section goes missing (a tenth of the figure long, at least 6 mm) is the answer.
    Returns that diameter in millimetres, or `limit` when nothing thinner than `limit` is found.
    """
    from scipy import ndimage
    size = float(max(m.extents))
    pitch = max(0.3, size / 230.0)
    solid, _ = _grid(m, pitch, 3)
    inside = ndimage.distance_transform_edt(solid) * pitch          # how deep inside the body every voxel lies
    full = np.ones((3, 3, 3), dtype=bool)
    long_enough = max(6.0, 0.1 * size)
    step = max(0.25, pitch * 0.6)
    radius = max(0.5, pitch)
    while 2 * radius <= limit:
        core = inside > radius
        if not core.any():
            return round(2 * radius, 1)
        # what a ball of this radius cannot reach: everything further than the radius from the core
        lost = solid & (ndimage.distance_transform_edt(~core) * pitch > radius + 0.6 * pitch)
        if lost.any():
            labels, count = ndimage.label(lost, structure=full)
            for k, box in enumerate(ndimage.find_objects(labels), start=1):
                if max((s.stop - s.start) for s in box) * pitch < long_enough:
                    continue
                pts = np.argwhere(labels[box] == k) * pitch
                # the piece along its own axes: a rod is long one way and about as thick as the ball both other ways
                spans = np.sqrt(np.maximum(np.linalg.eigvalsh(np.cov((pts - pts.mean(0)).T)), 0.0)) * 3.46
                if spans[2] >= long_enough and spans[0] >= radius and spans[1] <= 4.4 * radius:      # an ear is a plate, not a rod
                    return round(2 * radius, 1)
        radius += step
    return float(limit)


def thicken(m, target, level=2, grow=None):
    """
    The tabletop miniature: the figure grown by `grow` millimetres all round and smoothed, as if modelled in clay.
    Legs, tail and ears get thicker by twice that; whiskers, a collar's buckle and the fur's noise melt away.
    Returns (mesh, note). The mesh is closed; note says how much was added and the thinnest place before and after.
    """
    import trimesh
    from scipy import ndimage
    from skimage import measure
    grow = float(grow if grow is not None else GROW_MM.get(int(level), GROW_MM[2]))
    before = thinnest(m)
    note = {"thinnest_before_mm": before}
    size = float(max(m.extents))
    made = None
    for _ in range(4):
        pitch = max(0.35, size / 240.0)
        pad = int(np.ceil((grow + 2.0) / pitch)) + 3
        solid, origin = _grid(m, pitch, pad)
        # signed distance to the surface (negative inside), then the surface `grow` outside it
        field = (ndimage.distance_transform_edt(~solid) - ndimage.distance_transform_edt(solid)) * pitch
        field = ndimage.gaussian_filter(field.astype(np.float32), 0.9)
        verts, faces, _, _ = measure.marching_cubes(field, level=grow)
        made = trimesh.Trimesh(verts * pitch + origin, faces, process=True)
        if made.volume < 0:
            made.invert()
        # only the animal itself: the smoothing may leave a crumb beside it
        parts = made.split(only_watertight=False)
        if len(parts) > 1:
            made = max(parts, key=lambda p: abs(float(p.volume)) if p.is_watertight else 0.0)
        trimesh.smoothing.filter_taubin(made, lamb=0.5, nu=-0.53, iterations=12)
        after = thinnest(made)
        if after >= SAFE_MM or grow >= GROW_MOST - 1e-6:
            break
        grow = min(GROW_MOST, grow + 0.2)                            # still too thin: a little more clay
    if len(made.faces) > 150000:
        try:
            made = made.simplify_quadric_decimation(face_count=150000)
            trimesh.repair.fix_normals(made)
        except Exception:  # noqa: BLE001 - a finer mesh is no fault
            pass
    if not made.is_watertight:
        raise ValueError("miniature_open")
    # the size the customer asked for is the size of the figure, grown or not
    made.apply_scale(float(max(m.extents)) / float(max(made.extents)))
    made.apply_translation(m.bounds[0] - made.bounds[0])
    note.update({"miniature": True, "grown_mm": round(grow, 2), "thinnest_mm": thinnest(made)})
    return made, note


def _text(M, S, extras, cap, width):
    """The pet's name as a flat outline `cap` tall and at most `width` wide, its lower-left corner at the origin; or None."""
    name = str(extras.get("name", "")).strip()[:24]
    font = extras.get("font")
    if not name or not font or not os.path.isfile(font):
        return None, {}
    cs, info = S.text(M, [name], font, cap)
    tw, th = S.size(cs)
    if tw > width:
        cs = S.fit(cs, width_mm=width)
    x0, y0, _, _ = cs.bounds()
    return cs.translate([-x0, -y0]), ({"missing_chars": info["missing_chars"]} if info.get("missing_chars") else {})


def stand(m, kind, extras, target):
    """
    The base under the paws: one body with the figure. Returns (mesh, note).

    kind: "oval" follows the footprint of the paws (the least material), "round" is a disc (the tabletop miniature),
    "plaque" a taller plinth with the name on one of its sides. The low bases are 4 mm high with a chamfered top edge
    and carry the name raised on their top, on a strip the base is widened by. extras["name_side"] says where the name
    stands: "front" (-Y), "right" (+X), "back" (+Y) or "left" (-X); without it a long footprint gets it along the +X
    flank and a compact one in front. The paws sink a millimetre into the base, so an uneven sole never shows.
    """
    import manifold3d as M
    import trimesh
    sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
    import shape2d as S
    from mesh_tool import as_manifold, from_manifold

    kind = kind if kind in ("oval", "round", "plaque") else "oval"
    note = {"pedestal": kind}
    z0, h = float(m.bounds[0][2]), float(m.extents[2])
    v = m.vertices
    low = v[v[:, 2] <= z0 + 0.12 * h]                                    # the paws, or whatever the animal sits on
    x0, y0 = float(low[:, 0].min()), float(low[:, 1].min())
    x1, y1 = float(low[:, 0].max()), float(low[:, 1].max())
    margin = max(2.5, 0.04 * target)
    sink = max(1.0, 0.015 * target)
    tall = kind == "plaque"
    ped_h = max(12.0, 0.13 * target) if tall else max(4.0, 0.05 * target)
    chamfer = min(1.0, 0.25 * ped_h)

    # the name: where it was asked for, else beside a long animal along its flank (+X) and under a compact one in front (-Y)
    side = str(extras.get("name_side") or "")
    if side not in SIDES:
        side = "right" if (y1 - y0) >= 1.25 * (x1 - x0) else "front"
    along = side in ("right", "left")                                  # the name runs along Y
    cap = min(8.0, max(4.0, 0.075 * target))
    room = ((y1 - y0) if along else (x1 - x0)) + 2 * margin - 2 * chamfer - 2.0
    label, said = (None, {}) if tall else _text(M, S, extras, cap, max(10.0, room))
    note.update(said)
    strip = 0.0
    if label is not None:
        tw, th = S.size(label)
        strip = th + 2.0
        if side == "right":
            x1 += strip
        elif side == "left":
            x0 -= strip
        elif side == "back":
            y1 += strip
        else:
            y0 -= strip
    cx, cy = (x0 + x1) / 2, (y0 + y1) / 2
    w, d = (x1 - x0) + 2 * margin, (y1 - y0) + 2 * margin
    if kind == "round":
        # a disc that holds the footprint; never a coin under a big figure
        r = max(0.5 * float(np.hypot(x1 - x0, y1 - y0)) + 0.5 * margin, min(20.0, 0.5 * target))
        outline = M.CrossSection.circle(r, 128)
        w = d = 2 * r
    elif kind == "plaque":
        outline = S.rounded_rect(M, w, d, 2.0).translate([-w / 2, -d / 2])
    else:
        outline = S.rounded_rect(M, w, d, min(w, d) / 2 - 0.02).translate([-w / 2, -d / 2])

    body = outline.extrude(ped_h - chamfer)
    steps = 4
    for i in range(steps):
        # the chamfer as four steps of a quarter millimetre: each is a layer of the print
        body = body + outline.offset(-chamfer * (i + 1) / steps, M.JoinType.Round, 2.0, 32).extrude(chamfer / steps + 0.01).translate([0, 0, ped_h - chamfer + chamfer * i / steps - 0.01])
    if label is not None:
        tw, th = S.size(label)
        # set at the front edge (the feet of the letters outwards, read from that side), then turned to its side
        reach = (w if along else d) / 2 - margin
        raised = label.extrude(0.6 + 0.2).translate([-tw / 2, -reach, ped_h - 0.2]).rotate([0, 0, SIDES[side]])
        body = body + raised
        note["engraved_lines"] = 1
        if th < 3.0:
            note["small_text_mm"] = round(float(th), 1)
    if tall:
        # the plinth carries the name (and a dedication) on the chosen side, raised 1.2 mm
        lines = [t for t in (str(extras.get("name", "")).strip()[:24], str(extras.get("dedication", "")).strip()[:40]) if t]
        font = extras.get("font")
        if lines and font and os.path.isfile(font):
            try:
                face, y_top = (d if along else w), ped_h * 0.88
                for i, line in enumerate(lines):
                    band = ped_h * (0.44 if i == 0 else 0.26)
                    cs, info = S.text(M, [line], font, band * 0.72)
                    tw, th = S.size(cs)
                    if tw > face * 0.86:
                        cs = S.fit(cs, width_mm=face * 0.86)
                        tw, th = S.size(cs)
                    bx, by, _, _ = cs.bounds()
                    y_top -= band
                    flat = cs.translate([-bx - tw / 2, -by]).extrude(1.2).rotate([90, 0, 0]).translate([0, 0.2, y_top + (band - th) / 2])
                    # built on the front (-Y) face, then turned to its side
                    body = body + flat.translate([0, -(w if along else d) / 2, 0]).rotate([0, 0, SIDES[side]])
                    if info.get("missing_chars"):
                        note["missing_chars"] = info["missing_chars"]
                note["engraved_lines"] = len(lines)
            except Exception as e:  # noqa: BLE001 - a name that cannot be set must never lose the customer their model
                note["text_error"] = str(e)[:120]

    # the top of the base lies `sink` above the lowest point of the paws
    z_top = z0 + sink
    base = body.translate([cx, cy, z_top - ped_h])
    figure = as_manifold(m).trim_by_plane([0, 0, 1], z_top - ped_h + 0.05)   # nothing may hang below the base
    joined = from_manifold(figure + base)
    if not len(joined.faces) or not joined.is_watertight:
        raise ValueError("stand_not_joined")
    note.update({"pedestal_height": round(float(ped_h), 1), "base_mm": [round(float(w), 1), round(float(d), 1)], "name_side": side})
    return joined, note
