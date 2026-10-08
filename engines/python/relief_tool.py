#!/usr/bin/env python3
"""
Photo -> printable relief or lithophane (numpy + Pillow + manifold3d, exact millimetres, no AI, runs locally).

  relief_tool.py <in-image> <out.stl> <json-params>

params: {
  "mode": "lithophane" | "relief",
  "width": 100,            the plate's width in mm (the longer side when "height" is 0)
  "height": 0,             the plate's height in mm; 0 = by the photo's own ratio
  "shape": "rect",         rect | circle | oval | heart | arch | tree | custom | cylinder
  "silhouette": path,      the outline of a "custom" shape: an SVG or a dark-on-light picture
  "min_thickness": 0.8,    thinnest place (lithophane: brightest pixel; relief: background)
  "max_thickness": 3.0,    thickest place (lithophane: darkest pixel; relief: highest point)
  "frame": 2.0,            frame width in mm (0 = none), inside the plate's size, frame height = max_thickness
  "points": 560,           grid points on the longer side (detail of the picture; the grid step follows from it)
  "invert": false,
  "brightness": 0,         -50 … 50
  "contrast": 0,           -50 … 50
  "gamma": 1.0,            midtones 0.5 … 2
  "standing": true,        stand the plate up (default for lithophane: prints much better standing)
  "stand": false,          add a separate desk stand with a slot for the plate (printed beside it)
  "hang": "none",          none | hole (Ø 4 through the frame) | eyelet (a ring on top)
  "socket": "e27"          cylinder lamp only: e14 (Ø 28) | e27 (Ø 40) | led (open floor ring) | none (closed floor)
}
Thicknesses are rounded to whole 0.2 mm layers; the answer says how many shades that gives (`shades`).
lithophane: dark = thick (light shines through thin places) — print standing, view against light.
relief:     bright = high — decorative plaque, print lying.
cylinder:   the picture wrapped round a tube (a lamp shade): the relief on the outside, a floor with a hole for the socket.
Prints one JSON object.
"""
import json
import math
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

SHAPES = ("rect", "circle", "oval", "heart", "arch", "tree", "custom", "cylinder")
LAYER = 0.2
SOCKETS = {"e14": 28.0, "e27": 40.0, "led": 0.0, "none": None}


def fail(msg):
    print(json.dumps({"ok": False, "error": str(msg)}))
    sys.exit(0)


def ccw(pts):
    """A polygon wound counter-clockwise: manifold reads a clockwise one as a hole."""
    area = sum(pts[i][0] * pts[(i + 1) % len(pts)][1] - pts[(i + 1) % len(pts)][0] * pts[i][1] for i in range(len(pts)))
    return pts if area > 0 else pts[::-1]


def shape_2d(M, shape, w, h, silhouette=None):
    """The plate's outline, w × h mm, its lower-left corner at the origin."""
    import shape2d as S
    C = M.CrossSection
    if shape == "circle" or shape == "oval":
        return C.circle(0.5, 180).scale([w, h]).translate([w / 2, h / 2])
    if shape == "heart":
        pts = []
        for i in range(200):
            t = 2 * math.pi * i / 200
            x = 16 * math.sin(t) ** 3
            y = 13 * math.cos(t) - 5 * math.cos(2 * t) - 2 * math.cos(3 * t) - math.cos(4 * t)
            pts.append((x, y))
        cs = C([ccw(pts)])
        x0, y0, x1, y1 = cs.bounds()
        return cs.translate([-x0, -y0]).scale([w / (x1 - x0), h / (y1 - y0)])
    if shape == "arch":
        r = w / 2
        body = C.square([w, max(h - r, 1.0)])
        top = C.circle(r, 120).translate([r, max(h - r, 1.0)])
        return body + top
    if shape == "tree":
        # three tiers and a trunk, as wide as the plate
        pts = [(0.5, 1.0), (0.78, 0.68), (0.66, 0.68), (0.92, 0.38), (0.78, 0.38), (1.0, 0.1), (0.6, 0.1), (0.6, 0.0), (0.4, 0.0), (0.4, 0.1), (0.0, 0.1), (0.22, 0.38), (0.08, 0.38), (0.34, 0.68), (0.22, 0.68)]
        return C([ccw([(x * w, y * h) for x, y in pts])])
    if shape == "custom" and silhouette:
        cs = S.svg(M, silhouette, w) if str(silhouette).lower().endswith(".svg") else S.raster(M, silhouette, w, fill_holes=True)
        if isinstance(cs, tuple):
            cs = cs[0]
        x0, y0, x1, y1 = cs.bounds()
        cs = cs.translate([-x0, -y0])
        sx, sy = w / max(x1 - x0, 1e-6), h / max(y1 - y0, 1e-6)
        k = min(sx, sy)
        cs = cs.scale([k, k])
        bx0, by0, bx1, by1 = cs.bounds()
        return cs.translate([(w - (bx1 - bx0)) / 2, (h - (by1 - by0)) / 2])
    return C.square([w, h])


def heightmap(img, cols, rows, mode, invert, brightness, contrast, gamma):
    import numpy as np
    from PIL import Image, ImageEnhance, ImageFilter, ImageOps
    img = ImageOps.autocontrast(img, cutoff=1)
    if abs(contrast) > 0.5:
        img = ImageEnhance.Contrast(img).enhance(1.0 + contrast / 50.0)
    if abs(brightness) > 0.5:
        img = ImageEnhance.Brightness(img).enhance(1.0 + brightness / 50.0)
    img = img.resize((cols, rows), Image.LANCZOS).filter(ImageFilter.GaussianBlur(0.5))
    g = np.asarray(img, dtype=np.float32) / 255.0          # 0 = black, 1 = white
    if abs(gamma - 1.0) > 0.01:
        g = np.power(np.clip(g, 0.0, 1.0), 1.0 / gamma)
    level = 1.0 - g if mode == "lithophane" else g
    if invert:
        level = 1.0 - level
    return level


def plate_mesh(z, pitch, standing):
    """The flat plate out of a height map (mm): a top surface, walls and a fan for the back; (vertices, faces)."""
    import numpy as np
    rows, cols = z.shape
    z = z[::-1, :]                                          # image row 0 is the top -> +Y
    xs = np.arange(cols, dtype=np.float32) * pitch
    ys = np.arange(rows, dtype=np.float32) * pitch
    X, Y = np.meshgrid(xs, ys)
    top = np.stack([X.ravel(), Y.ravel(), z.ravel()], axis=1)
    n = rows * cols
    idx = np.arange(n).reshape(rows, cols)
    a, b, c, d = idx[:-1, :-1].ravel(), idx[:-1, 1:].ravel(), idx[1:, 1:].ravel(), idx[1:, :-1].ravel()
    top_f = np.concatenate([np.stack([a, b, c], 1), np.stack([a, c, d], 1)])
    loop = np.concatenate([idx[0, :-1], idx[:-1, -1], idx[-1, :0:-1], idx[:0:-1, 0]])
    m_ = len(loop)
    ring = top[loop].copy()
    ring[:, 2] = 0.0
    centre = np.array([[xs[-1] / 2.0, ys[-1] / 2.0, 0.0]], dtype=np.float32)
    verts = np.concatenate([top, ring, centre]).astype(np.float32)
    k0 = np.arange(m_)
    k1 = (k0 + 1) % m_
    t0, t1, b0, b1 = loop[k0], loop[k1], n + k0, n + k1
    walls = np.concatenate([np.stack([t0, b0, b1], 1), np.stack([t0, b1, t1], 1)])
    ci = np.full(m_, n + m_)
    back = np.stack([ci, b1, b0], 1)
    faces = np.concatenate([top_f, walls, back]).astype(np.int64)
    return verts, faces


def cylinder_mesh(z, pitch, radius, floor_t, hole_d):
    """
    The height map wrapped round a tube: the relief outside (radius + z), a smooth inside (radius), a top ring and a
    floor with a hole of hole_d (None: closed floor; 0: an open ring with nothing to it). Z up, the floor at 0.
    """
    import numpy as np
    rows, cols = z.shape
    z = z[::-1, :]
    theta = np.arange(cols, dtype=np.float64) * (2 * math.pi / cols)       # the seam closes: the last column meets the first
    ys = np.arange(rows, dtype=np.float64) * pitch + floor_t
    cos, sin = np.cos(theta), np.sin(theta)
    outer = np.stack([((radius + z) * cos[None, :]).ravel(), ((radius + z) * sin[None, :]).ravel(), np.repeat(ys, cols)], 1)
    inner = np.stack([(radius * cos[None, :] * np.ones((2, 1))).ravel(), (radius * sin[None, :] * np.ones((2, 1))).ravel(), np.repeat(ys[[0, -1]], cols)], 1)
    n = rows * cols
    io = np.arange(n).reshape(rows, cols)
    ii = np.arange(2 * cols).reshape(2, cols) + n
    faces = []

    def quads(grid, flip=False):
        a, b = grid[:-1, :], np.roll(grid[:-1, :], -1, axis=1)
        c, d = np.roll(grid[1:, :], -1, axis=1), grid[1:, :]
        a, b, c, d = a.ravel(), b.ravel(), c.ravel(), d.ravel()
        if flip:
            return np.concatenate([np.stack([a, c, b], 1), np.stack([a, d, c], 1)])
        return np.concatenate([np.stack([a, b, c], 1), np.stack([a, c, d], 1)])
    faces.append(quads(io))                                   # outside, normals out
    faces.append(quads(ii, flip=True))                        # inside, normals in
    # the top ring: outer top row to inner top row
    ot, it = io[-1, :], ii[-1, :]
    ot2, it2 = np.roll(ot, -1), np.roll(it, -1)
    faces.append(np.concatenate([np.stack([ot, it2, ot2], 1), np.stack([ot, it, it2], 1)]))
    verts = [outer, inner]
    base = n + 2 * cols
    ob, ib = io[0, :], ii[0, :]
    ob2, ib2 = np.roll(ob, -1), np.roll(ib, -1)
    if floor_t > 0 and hole_d is not None:
        # the floor: an annulus between the inner wall and the hole (or a disc), floor_t thick
        r_hole = hole_d / 2
        fo_top = np.stack([radius * cos, radius * sin, np.full(cols, floor_t)], 1)     # = inner bottom row, reused through ib
        fo_bot = np.stack([radius * cos, radius * sin, np.zeros(cols)], 1)
        fo = np.arange(cols) + base
        verts.append(fo_bot)
        base += cols
        # outer wall bottom row down to the floor's bottom outer rim
        faces.append(np.concatenate([np.stack([fo, np.roll(fo, -1), ob2], 1), np.stack([fo, ob2, ob], 1)]))
        if r_hole > 0:
            fh_top = np.stack([r_hole * cos, r_hole * sin, np.full(cols, floor_t)], 1)
            fh_bot = np.stack([r_hole * cos, r_hole * sin, np.zeros(cols)], 1)
            ht, hb = np.arange(cols) + base, np.arange(cols) + base + cols
            verts += [fh_top, fh_bot]
            base += 2 * cols
            ht2, hb2 = np.roll(ht, -1), np.roll(hb, -1)
            faces.append(np.concatenate([np.stack([ib, ib2, ht2], 1), np.stack([ib, ht2, ht], 1)]))          # floor top (annulus), normal up
            faces.append(np.concatenate([np.stack([fo, hb2, np.roll(fo, -1)], 1), np.stack([fo, hb, hb2], 1)]))   # floor bottom, normal down
            faces.append(np.concatenate([np.stack([ht, hb2, hb], 1), np.stack([ht, ht2, hb2], 1)]))         # the hole's wall, normal into the hole
        else:
            ct, cb = base, base + 1
            verts += [np.array([[0.0, 0.0, floor_t]]), np.array([[0.0, 0.0, 0.0]])]
            base += 2
            faces.append(np.stack([np.full(cols, ct), ib, ib2], 1))                                          # floor top disc
            faces.append(np.stack([np.full(cols, cb), np.roll(fo, -1), fo], 1))                              # floor bottom disc
    else:
        # no floor: the bottom ring closes the tube like the top one
        faces.append(np.concatenate([np.stack([ob, ob2, ib2], 1), np.stack([ob, ib2, ib], 1)]))
    return np.concatenate(verts).astype(np.float32), np.concatenate(faces).astype(np.int64)


def main():
    if len(sys.argv) < 4:
        fail("usage")
    src, out = sys.argv[1], sys.argv[2]
    try:
        p = json.loads(sys.argv[3])
    except Exception as e:  # noqa: BLE001
        fail("bad params: %s" % e)
    try:
        import numpy as np
        from PIL import Image, ImageOps
        import trimesh
    except Exception as e:  # noqa: BLE001
        fail("numpy/Pillow missing: %s" % e)

    try:
        mode = p.get("mode", "lithophane")
        shape = p.get("shape", "rect")
        if shape not in SHAPES:
            fail("bad shape")
        width = max(30.0, min(400.0 if shape == "cylinder" else 300.0, float(p.get("width", 100))))   # a lamp: the circumference
        height = float(p.get("height", 0) or 0)
        # whole layers: a thickness that ends inside a layer is printed as the next one anyway
        tmin = round(round(max(0.4, min(5.0, float(p.get("min_thickness", 0.8)))) / LAYER) * LAYER, 2)
        tmax = round(round(max(tmin + 2 * LAYER, min(12.0, float(p.get("max_thickness", 3.0)))) / LAYER) * LAYER, 2)
        frame = max(0.0, min(15.0, float(p.get("frame", 2.0))))
        points = int(p.get("points", 560))
        invert = bool(p.get("invert", False))
        brightness = max(-50.0, min(50.0, float(p.get("brightness", 0))))
        contrast = max(-50.0, min(50.0, float(p.get("contrast", 0))))
        gamma = max(0.5, min(2.0, float(p.get("gamma", 1.0))))
        standing = bool(p.get("standing", mode == "lithophane")) and shape != "cylinder"
        hang = p.get("hang", "none")
        socket = p.get("socket", "e27")
        if socket not in SOCKETS:
            fail("bad socket")

        img = ImageOps.exif_transpose(Image.open(src)).convert("L")
        w0, h0 = img.size
        if shape == "cylinder":
            # the picture round a tube: the width is the circumference, the height the tube's height
            circ = width
            if height <= 0:
                height = round(circ * h0 / w0, 1)
            height = max(30.0, min(300.0, height))
            pitch = max(0.12, min(0.4, circ / max(100, points)))
            cols = max(60, int(round(circ / pitch)))                   # steps round the tube (it closes on itself)
            rows = max(20, int(round(height / pitch))) + 1             # n steps of height need n + 1 rows
            level = heightmap(img, cols, rows, mode, invert, brightness, contrast, gamma)
            z = tmin + level * (tmax - tmin)
            radius = circ / (2 * math.pi)
            # e14 / e27: a floor with a hole for the socket; none: a closed floor; led: no floor, an open ring for a strip inside
            floor_t = 0.0 if socket == "led" else 2.0
            hole = None if socket == "led" else (0.0 if socket == "none" else SOCKETS[socket])
            if hole and hole / 2 > radius - 3:
                fail("socket_too_big")
            verts, faces = cylinder_mesh(z, pitch, radius, floor_t, hole)
            m = trimesh.Trimesh(vertices=verts, faces=faces, process=False)
            if m.volume < 0:
                m.invert()
            m.export(out, file_type="stl")
            print(json.dumps({"ok": True, "out": out, "mode": mode, "shape": shape, "standing": True, "stand": False, "width": round(2 * (radius + tmax), 1), "height": round(height + floor_t, 1),
                              "thickness": tmax, "min_thickness": tmin, "shades": int(round((tmax - tmin) / LAYER)) + 1, "circumference": round(circ, 1), "diameter": round(2 * radius, 1), "socket": socket,
                              "triangles": int(len(faces)), "watertight": bool(m.is_watertight)}))
            return
        if height <= 0:
            height = round(width * h0 / w0, 1) if w0 >= h0 else width
            if w0 < h0:
                width, height = round(height * w0 / h0, 1), height
        if shape == "circle":
            height = width
        height = max(30.0, min(300.0, height))
        inner_w, inner_h = width - 2 * frame, height - 2 * frame
        if min(inner_w, inner_h) < 20:
            fail("frame_too_wide")
        pitch = max(0.12, min(0.4, max(inner_w, inner_h) / max(100, points)))
        cols = max(40, min(1200, int(round(inner_w / pitch)))) + 1   # n steps need n + 1 points: the plate spans the whole size
        rows = max(40, min(1200, int(round(inner_h / pitch)))) + 1
        level = heightmap(img, cols, rows, mode, invert, brightness, contrast, gamma)
        z = tmin + level * (tmax - tmin)
        fpx = int(round(frame / pitch))
        if fpx > 0:
            z = np.pad(z, fpx, mode="constant", constant_values=tmax)
        verts, faces = plate_mesh(z, pitch, False)
        m = trimesh.Trimesh(vertices=verts, faces=faces, process=False)
        if m.volume < 0:
            m.invert()
        plate_w, plate_h = float(z.shape[1] - 1) * pitch, float(z.shape[0] - 1) * pitch
        if shape != "rect" or hang != "none":
            import manifold3d as M
            import mesh_tool as T
            solid = T.as_manifold(m)
            if shape != "rect":
                outline = shape_2d(M, shape, plate_w, plate_h, p.get("silhouette"))
                solid = solid ^ outline.extrude(tmax + 2.0).translate([0, 0, -1.0])
                if frame > 0:
                    rim = outline - outline.offset(-frame, M.JoinType.Round, 2.0, 32)
                    solid = solid + rim.extrude(tmax)
            if hang != "none":
                # where the outline's top is at the centre column: a heart has its cleft there, a tree its tip
                outline = outline if shape != "rect" else M.CrossSection.square([plate_w, plate_h])
                strip = outline ^ M.CrossSection.square([2.0, plate_h + 20.0]).translate([plate_w / 2 - 1.0, -10.0])
                top = strip.bounds()[3] if not strip.is_empty() else plate_h
            if hang == "hole":
                # a hole through the plate at the top, inside the frame (or 4 mm under the edge without one)
                d = 4.0 if frame >= 6 else 3.0
                solid = solid - M.Manifold.cylinder(tmax + 2.0, d / 2, d / 2, 48).translate([plate_w / 2, top - max(frame / 2, 4.0), -1.0])
            elif hang == "eyelet":
                # a ring above the plate's highest point, its neck down to the outline's top at the centre (fills a heart's cleft)
                ring = (M.CrossSection.circle(5.0, 64) - M.CrossSection.circle(2.5, 48)).extrude(tmax).translate([plate_w / 2, plate_h + 2.0, 0])
                neck = M.CrossSection.square([8.0, plate_h + 4.0 - top]).translate([plate_w / 2 - 4.0, top - 2.0]).extrude(tmax)
                solid = solid + ring + neck
            m = T.from_manifold(solid)
            if m.volume < 0:
                m.invert()
        if standing:
            # stand the plate up: picture top -> +Z, relief side -> -Y (faces the viewer), flat back -> +Y
            v = np.asarray(m.vertices)
            v = np.stack([v[:, 0], -v[:, 2], v[:, 1]], axis=1)
            v[:, 1] -= v[:, 1].min()
            m = trimesh.Trimesh(vertices=v, faces=m.faces, process=False)
            if m.volume < 0:
                m.invert()
        has_stand = False
        if bool(p.get("stand", False)):
            # a low pillow-shaped block: rounded corners in plan, rounded top edges, the slot leans back 8 degrees and
            # its mouth is rounded so the plate slides in; lies beside the plate so both print in one go
            import manifold3d as M
            C, J = M.CrossSection, M.JoinType.Round
            sw, sd, sh, slot = max(40.0, plate_w * 0.6), 30.0, 12.0, tmax + 0.5
            side = C.square([sd - 10.0, sh - 5.0]).translate([5.0, 0.0]).offset(5.0, J, 2.0, 32) ^ C.square([sd, sh])     # rounded top edges, flat bottom
            side = side + C.square([sd, 5.0])
            cut = C.square([slot, sh]).rotate(-8.0).translate([(sd - slot) / 2 + 1.0, 4.0])                                 # leans back, 4 mm floor under the plate
            side = side - cut
            side = side.offset(-1.5, J, 2.0, 24).offset(1.5, J, 2.0, 24)                                                     # rounded slot mouth
            block = side.extrude(sw).rotate([90, 0, 90])                                                                     # profile in (Y, Z), width along X
            plan = C.square([sw - 12.0, sd - 12.0]).translate([6.0, 6.0]).offset(6.0, J, 2.0, 32).extrude(sh + 1.0)       # rounded corners in plan
            block = block ^ plan
            sm = block.to_mesh()
            stand = trimesh.Trimesh(vertices=np.asarray(sm.vert_properties)[:, :3], faces=np.asarray(sm.tri_verts), process=False)
            stand.apply_translation([m.bounds[1][0] + 8.0, 0.0, 0.0])
            m = trimesh.util.concatenate([m, stand])
            has_stand = True
        m.export(out, file_type="stl")
        print(json.dumps({
            "ok": True, "out": out, "mode": mode, "shape": shape, "standing": standing, "stand": has_stand, "hang": hang,
            "width": round(plate_w, 1), "height": round(plate_h, 1), "thickness": tmax, "min_thickness": tmin, "shades": int(round((tmax - tmin) / LAYER)) + 1, "frame": frame,
            "triangles": int(len(m.faces)), "watertight": bool(m.is_watertight),
        }))
    except SystemExit:
        raise
    except Exception as e:  # noqa: BLE001
        fail(e)


if __name__ == "__main__":
    main()
