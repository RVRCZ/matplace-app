"""
Draws a picture of a model: the examples under a tool's page (php artisan matplace:tool-examples).

    python render_tool.py <model.stl> <picture.png> [width height] [#rrggbb]
    python render_tool.py <model.stl> <base> card <paint.json>

The second form draws the picture of a tool's card (php artisan matplace:tool-examples --card): 3:2, studio light on
the beige backdrop, seen a little more from above, every part in its filament colour. It writes <base>-800.jpg,
<base>-800.webp and <base>-480.webp. paint.json: {"color": "#rrggbb", "parts": [{"tris": [from, to], "color": "#rrggbb"}],
"regions": [{"x0", "y0", "x1", "y1", "z0", "color": "#rrggbb"}]}; regions (two-colour plates, bins of a set) win over
parts, as they do in the viewer; triangles nobody names get "color".

No GPU and no browser: the triangles are projected from a fixed three-quarter view, filled through a depth buffer
with numpy, lit by two lights, and laid on the page's background with a soft shadow under the model. The picture is
drawn at double size and scaled down (smooth edges), then stored as a small palette PNG.

Prints one JSON line: {"ok": true, "triangles": n} or {"ok": false, "error": "..."}.
"""
import json
import math
import sys

import numpy as np
import trimesh
from PIL import Image, ImageDraw, ImageFilter

BACKGROUND = (243, 238, 230)      # the beige of the product pictures (#F3EEE6)
FILAMENT = (217, 83, 30)          # the orange of the buttons, as printed plastic
MAX_TRIANGLES = 400_000


def rotation(yaw_deg: float, pitch_deg: float) -> np.ndarray:
    """Model (x right, y back, z up) → view (x right, y up, z towards the eye)."""
    yaw, pitch = math.radians(yaw_deg), math.radians(pitch_deg)
    rz = np.array([[math.cos(yaw), -math.sin(yaw), 0], [math.sin(yaw), math.cos(yaw), 0], [0, 0, 1]])
    # tilt the up axis towards the eye: after it, model z points up-and-forward
    rx = np.array([[1, 0, 0], [0, math.cos(pitch), -math.sin(pitch)], [0, math.sin(pitch), math.cos(pitch)]])
    to_view = np.array([[1, 0, 0], [0, 0, 1], [0, -1, 0]])   # y back → into the screen
    return rx @ to_view @ rz


def hex_rgb(value, default=FILAMENT):
    v = str(value or '')
    if len(v) == 7 and v[0] == '#':
        try:
            return tuple(int(v[i:i + 2], 16) for i in (1, 3, 5))
        except ValueError:
            pass
    return default


def face_colours(mesh: trimesh.Trimesh, paint: dict) -> np.ndarray:
    """One colour per triangle: the part it belongs to, or the region its centre lies in (first match wins)."""
    n = len(mesh.faces)
    out = np.empty((n, 3), dtype=np.float32)
    out[:] = hex_rgb(paint.get('color'))
    for part in paint.get('parts') or []:
        a, b = part['tris']
        out[max(0, a):min(n, b)] = hex_rgb(part.get('color'))
    regions = paint.get('regions') or []
    if regions:
        centres = np.asarray(mesh.vertices)[np.asarray(mesh.faces)].mean(axis=1)
        free = np.ones(n, dtype=bool)
        for g in regions:
            hit = free & (centres[:, 0] >= g['x0'] - 0.01) & (centres[:, 0] <= g['x1'] + 0.01) & (centres[:, 1] >= g['y0'] - 0.01) & (centres[:, 1] <= g['y1'] + 0.01) & (centres[:, 2] >= g['z0'])
            out[hit] = hex_rgb(g.get('color'))
            free &= ~hit
    return out


def render(mesh: trimesh.Trimesh, width: int, height: int, colour, card: bool = False) -> Image.Image:
    """colour: one (r, g, b) for the whole model, or an array with one per triangle. card: the look of a tool's card."""
    ss = 2
    w, h = width * ss, height * ss
    r = rotation(-34.0, 36.0) if card else rotation(-38.0, 28.0)
    v = np.asarray(mesh.vertices, dtype=np.float64)
    f = np.asarray(mesh.faces, dtype=np.int64)
    centre = (v.min(axis=0) + v.max(axis=0)) / 2
    p = (v - centre) @ r.T

    # fit: the model takes most of the frame, a little lower than the middle (room for the shadow)
    span = p[:, :2].max(axis=0) - p[:, :2].min(axis=0)
    scale = min(w * (0.7 if card else 0.78) / max(span[0], 1e-6), h * (0.7 if card else 0.74) / max(span[1], 1e-6))
    mid = (p[:, :2].max(axis=0) + p[:, :2].min(axis=0)) / 2
    sx = (p[:, 0] - mid[0]) * scale + w / 2
    sy = h * 0.47 - (p[:, 1] - mid[1]) * scale
    sz = p[:, 2]

    # the shadow: the model flattened onto the floor it stands on, seen from the same place
    floor = v.copy()
    floor[:, 2] = v[:, 2].min()
    q = (floor - centre) @ r.T
    qx = (q[:, 0] - mid[0]) * scale + w / 2 + 10 * ss
    qy = h * 0.47 - (q[:, 1] - mid[1]) * scale + 6 * ss
    shadow = Image.new('L', (w, h), 0)
    draw = ImageDraw.Draw(shadow)
    step = max(1, len(f) // 60_000)                    # a soft blob does not need every triangle
    for tri in f[::step]:
        draw.polygon([(qx[tri[0]], qy[tri[0]]), (qx[tri[1]], qy[tri[1]]), (qx[tri[2]], qy[tri[2]])], fill=255)
    shadow = shadow.filter(ImageFilter.GaussianBlur(9 * ss))

    # light: a key light from the upper left front, a weak fill from the right, some ambient
    n = np.cross(p[f[:, 1]] - p[f[:, 0]], p[f[:, 2]] - p[f[:, 0]])
    length = np.linalg.norm(n, axis=1)
    keep = length > 1e-12
    n[keep] /= length[keep][:, None]
    facing = n[:, 2]
    n = np.where(facing[:, None] < 0, -n, n)           # thin or badly wound faces are lit from the side we see
    key = np.array([-0.45, 0.65, 0.62]); key /= np.linalg.norm(key)
    fill = np.array([0.7, 0.15, 0.7]); fill /= np.linalg.norm(fill)
    shade = 0.34 + 0.62 * np.clip(n @ key, 0, 1) + 0.16 * np.clip(n @ fill, 0, 1)
    shade = np.clip(shade, 0.25, 1.12)

    depth = np.full((h, w), -np.inf)
    lit = np.zeros((h, w), dtype=np.float32)
    tint = np.zeros((h, w, 3), dtype=np.float32) if np.ndim(colour) == 2 else None
    x0 = np.floor(np.minimum.reduce([sx[f[:, 0]], sx[f[:, 1]], sx[f[:, 2]]])).astype(int).clip(0, w - 1)
    x1 = np.ceil(np.maximum.reduce([sx[f[:, 0]], sx[f[:, 1]], sx[f[:, 2]]])).astype(int).clip(0, w - 1)
    y0 = np.floor(np.minimum.reduce([sy[f[:, 0]], sy[f[:, 1]], sy[f[:, 2]]])).astype(int).clip(0, h - 1)
    y1 = np.ceil(np.maximum.reduce([sy[f[:, 0]], sy[f[:, 1]], sy[f[:, 2]]])).astype(int).clip(0, h - 1)
    for i in np.nonzero(keep)[0]:
        a, b, c = f[i]
        ax, ay, bx, by, cx, cy = sx[a], sy[a], sx[b], sy[b], sx[c], sy[c]
        area = (bx - ax) * (cy - ay) - (cx - ax) * (by - ay)
        if abs(area) < 1e-9:
            continue
        ys, xs = np.mgrid[y0[i]:y1[i] + 1, x0[i]:x1[i] + 1]
        px, py = xs + 0.5, ys + 0.5
        w0 = ((bx - px) * (cy - py) - (cx - px) * (by - py)) / area
        w1 = ((cx - px) * (ay - py) - (ax - px) * (cy - py)) / area
        w2 = 1.0 - w0 - w1
        inside = (w0 >= -1e-6) & (w1 >= -1e-6) & (w2 >= -1e-6)
        if not inside.any():
            continue
        z = w0 * sz[a] + w1 * sz[b] + w2 * sz[c]
        region = depth[y0[i]:y1[i] + 1, x0[i]:x1[i] + 1]
        nearer = inside & (z > region)
        region[nearer] = z[nearer]
        lit[y0[i]:y1[i] + 1, x0[i]:x1[i] + 1][nearer] = shade[i]
        if tint is not None:
            tint[y0[i]:y1[i] + 1, x0[i]:x1[i] + 1][nearer] = colour[i]

    covered = np.isfinite(depth)
    rgb = np.empty((h, w, 3), dtype=np.float32)
    rgb[:] = BACKGROUND
    dark = np.asarray(shadow, dtype=np.float32)[:, :, None] / 255.0
    rgb *= 1.0 - 0.26 * dark
    per_face = np.ndim(colour) == 2
    if per_face:
        body = np.clip(lit[:, :, None] * tint, 0, 255)
    else:
        body = np.clip(lit[:, :, None] * np.array(colour, dtype=np.float32)[None, None, :], 0, 255)
    if card:
        # a studio backdrop: lighter behind the model, a touch darker towards the corners
        yy, xx = np.mgrid[0:h, 0:w]
        fall = 1.03 - 0.1 * (((xx - w * 0.5) / (w * 0.75)) ** 2 + ((yy - h * 0.42) / (h * 0.8)) ** 2)
        rgb *= np.clip(fall, 0.9, 1.03)[:, :, None]
        rgb = np.clip(rgb, 0, 255)
    rgb[covered] = body[covered]

    picture = Image.fromarray(rgb.astype(np.uint8), 'RGB').resize((width, height), Image.LANCZOS)
    if card:
        return picture
    return picture.quantize(colors=96, method=Image.MEDIANCUT, dither=Image.NONE)


def card(argv):
    """<model.stl> <base> card <paint.json> → <base>-800.jpg, <base>-800.webp, <base>-480.webp"""
    try:
        mesh = trimesh.load(argv[1], force='mesh', process=False)
        if not isinstance(mesh, trimesh.Trimesh) or len(mesh.faces) == 0:
            raise ValueError('the file holds no triangles')
        if len(mesh.faces) > MAX_TRIANGLES:
            raise ValueError('too many triangles to draw: %d' % len(mesh.faces))
        with open(argv[4], encoding='utf-8') as fh:
            paint = json.load(fh)
        picture = render(mesh, 1600, 1066, face_colours(mesh, paint), card=True)
        big = picture.resize((800, 533), Image.LANCZOS)
        big.save(argv[2] + '-800.jpg', quality=84, optimize=True, progressive=True)
        big.save(argv[2] + '-800.webp', quality=80, method=6)
        picture.resize((480, 320), Image.LANCZOS).save(argv[2] + '-480.webp', quality=80, method=6)
    except Exception as e:  # noqa: BLE001 - the caller reads the reason
        print(json.dumps({'ok': False, 'error': str(e)}))
        return 1
    print(json.dumps({'ok': True, 'triangles': int(len(mesh.faces))}))
    return 0


def main(argv):
    if len(argv) < 3:
        print(json.dumps({'ok': False, 'error': 'usage: render_tool.py model.stl picture.png [width height] [#rrggbb]'}))
        return 2
    if len(argv) > 4 and argv[3] == 'card':
        return card(argv)
    width = int(argv[3]) if len(argv) > 4 else 800
    height = int(argv[4]) if len(argv) > 4 else 600
    colour = FILAMENT
    for arg in argv[3:]:
        if arg.startswith('#') and len(arg) == 7:
            colour = tuple(int(arg[i:i + 2], 16) for i in (1, 3, 5))
    try:
        mesh = trimesh.load(argv[1], force='mesh', process=False)
        if not isinstance(mesh, trimesh.Trimesh) or len(mesh.faces) == 0:
            raise ValueError('the file holds no triangles')
        if len(mesh.faces) > MAX_TRIANGLES:
            raise ValueError('too many triangles to draw: %d' % len(mesh.faces))
        render(mesh, width, height, colour).save(argv[2], optimize=True)
    except Exception as e:  # noqa: BLE001 - the caller reads the reason
        print(json.dumps({'ok': False, 'error': str(e)}))
        return 1
    print(json.dumps({'ok': True, 'triangles': int(len(mesh.faces))}))
    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv))
