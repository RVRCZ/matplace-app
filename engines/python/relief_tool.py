#!/usr/bin/env python3
"""
Photo -> printable relief or lithophane (numpy + Pillow, exact millimetres, no AI, runs locally).

  relief_tool.py <in-image> <out.stl> <json-params>

params: {
  "mode": "lithophane" | "relief",
  "width": 100,            longer side of the plate in mm
  "min_thickness": 0.8,    thinnest place (lithophane: brightest pixel; relief: background)
  "max_thickness": 3.0,    thickest place (lithophane: darkest pixel; relief: highest point)
  "frame": 2.0,            frame width in mm (0 = none), frame height = max_thickness
  "pitch": 0.4,            grid step in mm (roughly the nozzle width)
  "invert": false,
  "standing": true         stand the plate up (default for lithophane: prints much better standing)
}
lithophane: dark = thick (light shines through thin places) — print standing, view against light.
relief:     bright = high — decorative plaque, print lying.
Prints one JSON object.
"""
import json
import sys


def fail(msg):
    print(json.dumps({"ok": False, "error": str(msg)}))
    sys.exit(0)


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
        from PIL import Image, ImageFilter, ImageOps
    except Exception as e:  # noqa: BLE001
        fail("numpy/Pillow missing: %s" % e)

    try:
        mode = p.get("mode", "lithophane")
        width = max(30.0, min(300.0, float(p.get("width", 100))))
        tmin = max(0.4, min(5.0, float(p.get("min_thickness", 0.8))))
        tmax = max(tmin + 0.4, min(12.0, float(p.get("max_thickness", 3.0))))
        frame = max(0.0, min(15.0, float(p.get("frame", 2.0))))
        pitch = max(0.25, min(1.0, float(p.get("pitch", 0.4))))
        invert = bool(p.get("invert", False))
        standing = bool(p.get("standing", mode == "lithophane"))

        img = Image.open(src)
        img = ImageOps.exif_transpose(img).convert("L")
        w0, h0 = img.size
        long_px = int(round((width - 2 * frame) / pitch))
        long_px = max(40, min(450, long_px))
        if w0 >= h0:
            cols, rows = long_px, max(20, int(round(long_px * h0 / w0)))
        else:
            rows, cols = long_px, max(20, int(round(long_px * w0 / h0)))
        img = ImageOps.autocontrast(img, cutoff=1).resize((cols, rows), Image.LANCZOS).filter(ImageFilter.GaussianBlur(0.6))
        g = np.asarray(img, dtype=np.float32) / 255.0          # 0 = black, 1 = white
        if mode == "lithophane":
            level = 1.0 - g                                     # dark -> thick
        else:
            level = g                                           # bright -> high
        if invert:
            level = 1.0 - level
        z = tmin + level * (tmax - tmin)

        # frame: pad with full height
        fpx = int(round(frame / pitch))
        if fpx > 0:
            z = np.pad(z, fpx, mode="constant", constant_values=tmax)
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

        # flat back: boundary loop (counter-clockwise seen from +Z) -> walls + a triangle fan, half the triangles of a full grid
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

        if standing:
            # stand the plate up: picture top -> +Z, relief side -> -Y (faces the viewer), flat back -> +Y
            verts = np.stack([verts[:, 0], -verts[:, 2], verts[:, 1]], axis=1)
            verts[:, 1] -= verts[:, 1].min()

        import trimesh
        m = trimesh.Trimesh(vertices=verts, faces=faces, process=False)
        if m.volume < 0:
            m.invert()
        m.export(out, file_type="stl")
        print(json.dumps({
            "ok": True, "out": out, "mode": mode, "standing": standing,
            "width": round(float(cols - 1) * pitch, 1), "height": round(float(rows - 1) * pitch, 1), "thickness": tmax,
            "triangles": int(len(faces)), "watertight": bool(m.is_watertight),
        }))
    except SystemExit:
        raise
    except Exception as e:  # noqa: BLE001
        fail(e)


if __name__ == "__main__":
    main()
