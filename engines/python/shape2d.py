"""
Shared 2D artwork for the creative tools: text, SVG and simple raster images → manifold3d CrossSection in millimetres.
Used by logo / stamp / QR sign (and later stencil, light sign, personalisation), so every tool reads the same inputs the
same way and says the same things about artwork it cannot use.

All functions return (cross_section, info) where info carries facts for the visitor ("lines thinner than the nozzle"…).
"""
import math
import re

MAX_POINTS = 60000          # flattened outline points per artwork: keeps booleans fast and files small
MAX_SVG_BYTES = 400 * 1024
MAX_IMAGE_PX = 360          # raster artwork is traced on a grid of at most this many cells on the longer side


class ArtworkError(Exception):
    def __init__(self, code, detail=""):
        super().__init__(code + (": " + detail if detail else ""))
        self.code = code


# ── text ─────────────────────────────────────────────────────────────────────

def _glyph_polys(font, glyph_set, name, steps=8):
    from fontTools.pens.basePen import BasePen

    class Flat(BasePen):
        def __init__(self, gs):
            super().__init__(gs)
            self.polys, self.cur = [], []

        def _moveTo(self, p):
            self._close()
            self.cur = [p]

        def _lineTo(self, p):
            self.cur.append(p)

        def _curveToOne(self, a, b, c):
            p0 = self.cur[-1]
            for i in range(1, steps + 1):
                t = i / steps
                mt = 1 - t
                self.cur.append((mt ** 3 * p0[0] + 3 * mt * mt * t * a[0] + 3 * mt * t * t * b[0] + t ** 3 * c[0],
                                 mt ** 3 * p0[1] + 3 * mt * mt * t * a[1] + 3 * mt * t * t * b[1] + t ** 3 * c[1]))

        def _qCurveToOne(self, a, b):
            p0 = self.cur[-1]
            for i in range(1, steps + 1):
                t = i / steps
                mt = 1 - t
                self.cur.append((mt * mt * p0[0] + 2 * mt * t * a[0] + t * t * b[0], mt * mt * p0[1] + 2 * mt * t * a[1] + t * t * b[1]))

        def _close(self):
            if len(self.cur) >= 3:
                self.polys.append(self.cur)
            self.cur = []

        _closePath = _close
        _endPath = _close

    pen = Flat(glyph_set)
    glyph_set[name].draw(pen)
    pen._close()
    return pen.polys


def _spare_fonts(font_path):
    """
    Where a character is looked for when the chosen typeface does not have it: DejaVu Sans (hearts, stars, notes,
    arrows) and Noto Emoji (faces, animals, cakes). A handwritten face has letters only; "Emma ♥" must still work.
    """
    import os
    here = os.path.dirname(os.path.abspath(__file__))
    found = []
    for path in (os.path.join(here, "..", "..", "vendor", "dompdf", "dompdf", "lib", "fonts", "DejaVuSans-Bold.ttf"),
                 os.path.join(here, "..", "fonts", "NotoEmoji.ttf")):
        if os.path.isfile(path) and os.path.abspath(path) != os.path.abspath(str(font_path)):
            found.append(path)
    return found


# joiners and modifiers of emoji: they draw nothing themselves
SILENT = {0xFE0E, 0xFE0F, 0x200D, 0x20E3} | set(range(0x1F3FB, 0x1F400))


def text(M, lines, font_path, cap_height_mm, line_gap=0.35, align="center", scales=None):
    """
    Lines of text → outline. cap_height_mm is the height of a capital letter, so "12 mm text" means what people expect.
    scales: how tall each line is against that height (None = all the same); a name with a smaller line under it is [1, 0.7].
    """
    import numpy as np
    from fontTools.ttLib import TTFont
    lines = [l for l in (str(x).strip() for x in lines) if l]
    if not lines:
        raise ArtworkError("no_text")
    font = TTFont(font_path)
    # a family that comes as one variable file is read at its boldest: thin strokes do not print
    heavy = {a.axisTag: a.maxValue for a in font["fvar"].axes if a.axisTag == "wght"} if "fvar" in font else None
    gs, cmap = (font.getGlyphSet(location=heavy) if heavy else font.getGlyphSet()), font.getBestCmap()
    units = font["head"].unitsPerEm
    cap = getattr(font["OS/2"], "sCapHeight", 0) or units * 0.72
    k = cap_height_mm / cap
    spare = []                                              # opened only when a character is missing

    def borrowed(code):
        """The glyph from a spare font, in the units of the main font and as tall as its capitals: (polys, advance)."""
        if not spare:
            for path in _spare_fonts(font_path):
                try:
                    f = TTFont(path)
                    spare.append((f, f.getGlyphSet(), f.getBestCmap(), f["hmtx"]))
                except Exception:  # noqa: BLE001 - a broken spare font only means the character stays missing
                    pass
            spare.append(None)                              # marks "already looked"
        for entry in spare:
            if entry is None:
                continue
            f, fgs, fcmap, fhmtx = entry
            name = fcmap.get(code)
            if name is None:
                continue
            polys = _glyph_polys(f, fgs, name)
            if not polys:
                continue
            ys = [py for poly in polys for _, py in poly]
            xs = [px for poly in polys for px, _ in poly]
            tall = max(ys) - min(ys)
            if tall <= 0:
                continue
            # a symbol is as tall as a capital letter and stands on the baseline, whatever its own font thinks
            s = cap * 1.05 / tall
            x0, y0 = min(xs), min(ys)
            pad = units * 0.06
            return [[((px - x0) * s + pad, (py - y0) * s - cap * 0.025) for px, py in poly] for poly in polys], (max(xs) - x0) * s + 2 * pad
        return None, 0
    polys, missing, widths, total = [], set(), [], 0
    rows = []
    for line in lines:
        x, row = 0.0, []
        for ch in line:
            if ord(ch) in SILENT:
                continue
            g = cmap.get(ord(ch))
            if g is None:
                got, advance = (None, 0) if ch.isspace() else borrowed(ord(ch))
                if got:
                    for poly in got:
                        row.append([(px + x, py) for px, py in poly])
                    x += advance
                    continue
                if not ch.isspace():
                    missing.add(ch)
                x += units * 0.3
                continue
            for poly in _glyph_polys(font, gs, g):
                row.append([(px + x, py) for px, py in poly])
            x += gs[g].width
        rows.append(row)
        widths.append(x)
    size = [float(scales[i]) if scales and i < len(scales) else 1.0 for i in range(len(rows))]
    widths = [w * s for w, s in zip(widths, size)]
    wmax = max(widths) if widths else 0
    # baselines: under a line comes the gap, what hangs below its baseline, and the capitals of the next line
    # (with every line the same size this is the one step there always was)
    base = [0.0]
    for i in range(1, len(rows)):
        base.append(base[-1] - (cap * size[i] + cap * line_gap * (size[i - 1] + size[i]) / 2 + units * 0.12 * size[i - 1]))
    for i, row in enumerate(rows):
        dx = {"left": 0, "right": wmax - widths[i]}.get(align, (wmax - widths[i]) / 2)
        s, dy = size[i], base[i]
        for poly in row:
            total += len(poly)
            polys.append(np.array([((px * s + dx) * k, (py * s + dy) * k) for px, py in poly], dtype=np.float64))
    if not polys:
        raise ArtworkError("no_text")
    if total > MAX_POINTS:
        raise ArtworkError("too_complex")
    cs = M.CrossSection(polys, M.FillRule.NonZero)
    bx0, by0, bx1, by1 = cs.bounds()
    # how far the ink hangs below the baseline of the last line, relative to the text width
    base_y = base[-1] * k
    hang = max(0.0, base_y - by0) / max(1e-6, bx1 - bx0)
    # where every line lies: from where to where, its baseline and the height of its capitals (a text that stands needs them)
    rows_at = []
    for i in range(len(rows)):
        dx = {"left": 0, "right": wmax - widths[i]}.get(align, (wmax - widths[i]) / 2)
        rows_at.append({"x0": dx * k, "x1": (dx + widths[i]) * k, "base": base[i] * k, "cap": cap_height_mm * size[i]})
    return cs, {"missing_chars": sorted(missing), "source": "text", "descent_ratio": hang, "rows": rows_at}


# ── SVG ──────────────────────────────────────────────────────────────────────

def svg(M, path, width_mm):
    import numpy as np
    raw = open(path, "rb").read()
    if len(raw) > MAX_SVG_BYTES:
        raise ArtworkError("svg_too_big")
    head = raw[:4096].lower()
    if b"<!entity" in raw.lower() or b"<!doctype" in head and b"[" in head:
        raise ArtworkError("svg_unsafe")
    import io
    from svgelements import SVG, Path, Shape
    try:
        doc = SVG.parse(io.BytesIO(raw), reify=True)
    except Exception as e:  # noqa: BLE001
        raise ArtworkError("svg_unreadable", str(e)[:80])
    polys, strokes_only, total = [], 0, 0
    for el in doc.elements():
        if not isinstance(el, Shape):
            continue
        fill = getattr(el, "fill", None)
        has_fill = fill is not None and getattr(fill, "value", None) is not None and getattr(fill, "alpha", 255) != 0
        if not has_fill:
            strokes_only += 1
            continue
        p = Path(el)
        for sub in p.as_subpaths():
            sp = Path(sub)
            try:
                length = sp.length(error=1e-3)
            except Exception:  # noqa: BLE001
                length = 0
            if not length:
                continue
            n = int(max(8, min(400, length / max(doc.viewbox.width if doc.viewbox else 100, 1) * 600)))
            pts = [sp.point(i / n) for i in range(n)]
            pts = [(pt.x, -pt.y) for pt in pts if pt is not None]          # SVG's Y axis points down
            if len(pts) >= 3:
                total += len(pts)
                polys.append(np.array(pts, dtype=np.float64))
    if not polys:
        raise ArtworkError("svg_outlines_only" if strokes_only else "svg_empty")
    if total > MAX_POINTS:
        raise ArtworkError("too_complex")
    cs = M.CrossSection(polys, M.FillRule.NonZero)
    return _fit_width(cs, width_mm), {"source": "svg", "ignored_outlines": strokes_only}


# ── raster ───────────────────────────────────────────────────────────────────

def raster(M, path, width_mm, threshold=None, invert=False, fill_holes=False):
    """
    Dark-on-light picture → outline. Works for logos and silhouettes, not for photographs (that is what the relief tool is for).
    fill_holes: everything enclosed by the drawing counts as inside (a line drawing of a leaf becomes the leaf itself).
    """
    import numpy as np
    from PIL import Image, ImageOps
    from scipy import ndimage
    try:
        img = Image.open(path)
        img = ImageOps.exif_transpose(img)
    except Exception:  # noqa: BLE001
        raise ArtworkError("image_unreadable")
    if img.mode in ("RGBA", "LA") or "transparency" in img.info:
        rgba = img.convert("RGBA")
        bg = Image.new("RGBA", rgba.size, (255, 255, 255, 255))
        img = Image.alpha_composite(bg, rgba)
    g = img.convert("L")
    scale = MAX_IMAGE_PX / max(g.size)
    if scale < 1:
        g = g.resize((max(8, round(g.size[0] * scale)), max(8, round(g.size[1] * scale))), Image.LANCZOS)
    a = np.asarray(g, dtype=np.float32)
    hist, _ = np.histogram(a, bins=256, range=(0, 255))
    if threshold is None:                                   # Otsu: the split that best separates dark from light
        w = np.cumsum(hist).astype(np.float64)               # floats: the squared term overflows 64-bit integers
        m = np.cumsum(hist * np.arange(256)).astype(np.float64)
        with np.errstate(divide="ignore", invalid="ignore"):
            between = (m[-1] * w - m * w[-1]) ** 2 / (w * (w[-1] - w))
        if np.all(np.isnan(between)):                        # one single shade: nothing to separate
            raise ArtworkError("image_blank", "0")
        threshold = int(np.nanargmax(between))
    dark = a <= threshold
    if invert:
        dark = ~dark
    mid = float(((a > threshold - 50) & (a < threshold + 50)).mean())   # photographs live in the middle greys
    fill = float(dark.mean())
    if fill < 0.01 or fill > 0.97:
        raise ArtworkError("image_blank", "%d" % round(fill * 100))
    if mid > 0.45:
        raise ArtworkError("image_is_photo")
    dark = ndimage.binary_opening(dark, iterations=1)                  # drops single-pixel noise
    labels, count = ndimage.label(dark)
    if count > 400:
        raise ArtworkError("image_too_noisy", str(count))
    if fill_holes:
        # close small gaps in the drawn line first, then fill what it encloses; keep only the biggest region
        closed = ndimage.binary_closing(dark, iterations=2)
        filled = ndimage.binary_fill_holes(closed)
        labels, count = ndimage.label(filled)
        if count > 1:
            sizes = ndimage.sum(filled, labels, range(1, count + 1))
            filled = labels == (int(np.argmax(sizes)) + 1)
        dark = filled
    rows, cols = dark.shape
    rects = []
    for r in range(rows):
        line = dark[r]
        edges = np.flatnonzero(np.diff(np.concatenate(([0], line.view(np.int8), [0]))))
        for s, e in zip(edges[::2], edges[1::2]):
            y = rows - 1 - r
            rects.append(np.array([(s, y), (e, y), (e, y + 1.02), (s, y + 1.02)], dtype=np.float64))
    if not rects:
        raise ArtworkError("image_blank", "0")
    cs = M.CrossSection(rects, M.FillRule.NonZero)
    cs = cs.offset(0.75, M.JoinType.Round, 2.0, 12).offset(-0.75, M.JoinType.Round, 2.0, 12).simplify(0.35)   # rounds the pixel staircase
    return _fit_width(cs, width_mm), {"source": "image", "threshold": int(threshold), "islands": int(count), "fill_pct": round(fill * 100)}


# ── common ───────────────────────────────────────────────────────────────────

def _fit_width(cs, width_mm):
    x0, y0, x1, y1 = cs.bounds()
    w = x1 - x0
    if w <= 0:
        raise ArtworkError("svg_empty")
    k = width_mm / w
    return cs.translate([-x0, -y0]).scale([k, k])


def fit(cs, width_mm=None, height_mm=None):
    """Scale to a width or a height (whichever is given) and move the lower-left corner to the origin."""
    x0, y0, x1, y1 = cs.bounds()
    k = (width_mm / (x1 - x0)) if width_mm else (height_mm / (y1 - y0))
    return cs.translate([-x0, -y0]).scale([k, k])


def size(cs):
    x0, y0, x1, y1 = cs.bounds()
    return x1 - x0, y1 - y0


def centre_on(cs, w, h):
    cw, ch = size(cs)
    x0, y0, _, _ = cs.bounds()
    return cs.translate([-x0 + (w - cw) / 2, -y0 + (h - ch) / 2])


def printability(M, cs, nozzle=0.4):
    """How much of the artwork survives a line two nozzles wide: thin strokes and tiny islands disappear in print."""
    area = cs.area()
    if area <= 0:
        return {"thin_pct": 100}
    kept = cs.offset(-nozzle, M.JoinType.Round, 2.0, 8).offset(nozzle, M.JoinType.Round, 2.0, 8).area()
    return {"thin_pct": int(round(max(0.0, 1 - kept / area) * 100))}


def load(M, p, width_mm, font_path=None, cap_height_mm=None, fill_holes=False):
    """One entry for every tool: params carry either text lines or an uploaded artwork file."""
    art = p.get("artwork_path")
    if art:
        ext = art.rsplit(".", 1)[-1].lower()
        if ext == "svg":
            return svg(M, art, width_mm)
        return raster(M, art, width_mm, invert=bool(p.get("invert", False)), fill_holes=fill_holes)
    lines = p.get("lines") or [p.get("text", "")]
    cs, info = text(M, lines, font_path, cap_height_mm or 10)
    if width_mm and not cap_height_mm:
        cs = fit(cs, width_mm=width_mm)
    return cs, info


def room(M, cs, aspect):
    """
    The biggest box of the given proportions (width / height) that lies inside an outline, its sides level:
    (cx, cy, width, height), or None. Read off a grid of 300 cells; numpy and PIL only, because a shaped sign asks
    for it on every preview and the import of anything heavier would cost more than the search.
    """
    import numpy as np
    from PIL import Image, ImageDraw
    x0, y0, x1, y1 = cs.bounds()
    cell = max(x1 - x0, y1 - y0) / 300
    img = Image.new("1", (int((x1 - x0) / cell) + 3, int((y1 - y0) / cell) + 3), 0)
    draw = ImageDraw.Draw(img)
    rings = []
    for poly in cs.to_polygons():
        pts = [((float(x) - x0) / cell + 1, (float(y) - y0) / cell + 1) for x, y in poly]
        rings.append((sum(pts[i][0] * pts[(i + 1) % len(pts)][1] - pts[(i + 1) % len(pts)][0] * pts[i][1] for i in range(len(pts))), pts))
    for area, pts in sorted(rings, key=lambda ring: -ring[0]):      # the outer rings first, then their holes
        draw.polygon(pts, fill=1 if area > 0 else 0)
    mask = np.asarray(img, dtype=np.int32)
    rows = mask.shape[0]
    down = np.vstack([np.zeros((1, mask.shape[1]), dtype=np.int32), np.cumsum(mask, axis=0)])

    def widest(h):
        """A box h rows tall: the longest run of columns that are inside for all its rows → (length, its last column, its top row)."""
        band = (down[h:] - down[:-h]) == h
        count = np.cumsum(band, axis=1)
        run = count - np.maximum.accumulate(np.where(band, 0, count), axis=1)
        r, c = np.unravel_index(int(np.argmax(run)), run.shape)
        return int(run[r, c]), int(c), int(r)

    # the taller the box, the shorter the run it finds: the best box is where the two meet
    lo, hi = 1, rows
    while lo < hi:
        mid = (lo + hi + 1) // 2
        if widest(mid)[0] >= aspect * mid:
            lo = mid
        else:
            hi = mid - 1
    best = None
    for h in (lo, min(rows, lo + 1)):
        length, c, r = widest(h)
        tall = min(h, length / aspect)
        if tall > 0 and (best is None or tall > best[0]):
            best = (tall, c - (length - 1) / 2.0, r + (h - 1) / 2.0)
    if best is None or best[0] < 2:
        return None
    tall = (best[0] - 1) * cell                                # a cell smaller: the box must lie inside, not on the line
    return x0 + (best[1] - 1) * cell, y0 + (best[2] - 1) * cell, tall * aspect, tall


def slugify(s):
    return re.sub(r"[^a-z0-9]+", "-", s.lower()).strip("-")[:40] or "model"


def rounded_rect(M, w, d, r):
    r = max(0.0, min(r, w / 2 - 0.01, d / 2 - 0.01))
    if r < 0.05:
        return M.CrossSection.square([w, d])
    return M.CrossSection.square([w - 2 * r, d - 2 * r]).translate([r, r]).offset(r, M.JoinType.Round, 2.0, 48)


def deg(a):
    return a * math.pi / 180.0


# ── a picture in the colours of filaments ───────────────────────────────────

MAX_COLORS = 8
COLOR_PX = 560            # a picture in colours is read on a finer grid than a silhouette: outlines of a drawing must survive
MIN_ISLAND_MM2 = 1.0        # a speck of colour smaller than this is not printed as its own piece


def _lab(rgb):
    """sRGB 0..1 (…, 3) → CIE Lab: distances in it are what the eye calls "a similar colour"."""
    import numpy as np
    c = np.where(rgb <= 0.04045, rgb / 12.92, ((rgb + 0.055) / 1.055) ** 2.4)
    m = np.array([[0.4124564, 0.3575761, 0.1804375], [0.2126729, 0.7151522, 0.0721750], [0.0193339, 0.1191920, 0.9503041]])
    xyz = c @ m.T / np.array([0.95047, 1.0, 1.08883])
    f = np.where(xyz > 0.008856, np.cbrt(xyz), 7.787 * xyz + 16 / 116)
    return np.stack([116 * f[..., 1] - 16, 500 * (f[..., 0] - f[..., 1]), 200 * (f[..., 1] - f[..., 2])], -1)


def hex_lab(value):
    import numpy as np
    v = str(value or "").lstrip("#")
    if len(v) != 6:
        return None
    try:
        return _lab(np.array([int(v[i:i + 2], 16) / 255.0 for i in (0, 2, 4)]))
    except ValueError:
        return None


def _svg_picture(path, px):
    """A coloured SVG drawn as a picture (fills only, in paint order): the same road as a PNG from then on."""
    import io
    from PIL import Image, ImageChops, ImageDraw
    from svgelements import SVG, Path, Shape
    raw = open(path, "rb").read()
    if len(raw) > MAX_SVG_BYTES:
        raise ArtworkError("svg_too_big")
    head = raw[:4096].lower()
    if b"<!entity" in raw.lower() or b"<!doctype" in head and b"[" in head:
        raise ArtworkError("svg_unsafe")
    try:
        doc = SVG.parse(io.BytesIO(raw), reify=True)
    except Exception as e:  # noqa: BLE001
        raise ArtworkError("svg_unreadable", str(e)[:80])
    shapes, strokes_only, total = [], 0, 0
    for el in doc.elements():
        if not isinstance(el, Shape):
            continue
        fill = getattr(el, "fill", None)
        if fill is None or getattr(fill, "value", None) is None or getattr(fill, "alpha", 255) == 0:
            strokes_only += 1
            continue
        rings = []
        for sub in Path(el).as_subpaths():
            sp = Path(sub)
            try:
                length = sp.length(error=1e-3)
            except Exception:  # noqa: BLE001
                length = 0
            if not length:
                continue
            n = int(max(8, min(400, length / max(doc.viewbox.width if doc.viewbox else 100, 1) * 600)))
            try:
                # all the points of a subpath in one call: asking for them one by one took a third of a preview's time
                import numpy as np
                pts = [(float(x), float(y)) for x, y in sp.npoint(np.linspace(0, 1, n, endpoint=False))]
            except Exception:  # noqa: BLE001 - an older svgelements has no npoint
                pts = [sp.point(i / n) for i in range(n)]
                pts = [(pt.x, pt.y) for pt in pts if pt is not None]
            if len(pts) >= 3:
                total += len(pts)
                rings.append(pts)
        if rings:
            shapes.append(((fill.red, fill.green, fill.blue), rings))
    if not shapes:
        raise ArtworkError("svg_outlines_only" if strokes_only else "svg_empty")
    if total > MAX_POINTS:
        raise ArtworkError("too_complex")
    xs = [x for _, rings in shapes for ring in rings for x, _ in ring]
    ys = [y for _, rings in shapes for ring in rings for _, y in ring]
    x0, y0, w, h = min(xs), min(ys), max(xs) - min(xs), max(ys) - min(ys)
    if w <= 0 or h <= 0:
        raise ArtworkError("svg_empty")
    k = 2 * px / max(w, h)                                   # drawn twice as big and scaled down: soft edges
    size = (max(8, int(round(w * k)) + 8), max(8, int(round(h * k)) + 8))
    out = Image.new("RGBA", size, (255, 255, 255, 0))
    for rgb, rings in shapes:
        mask = Image.new("1", size, 0)
        for ring in rings:                                  # even-odd: a ring inside a ring is a hole
            one = Image.new("1", size, 0)
            ImageDraw.Draw(one).polygon([((x - x0) * k + 4, (y - y0) * k + 4) for x, y in ring], fill=1)
            mask = ImageChops.logical_xor(mask, one)
        out.paste(Image.new("RGBA", size, tuple(int(c) for c in rgb) + (255,)), (0, 0), mask)
    return out.resize((max(8, size[0] // 2), max(8, size[1] // 2)), Image.LANCZOS), strokes_only


def _kmeans(np, pts, k, rounds=14):
    """Plain k-means with a fixed start (k-means++ from a seeded generator): the same picture gives the same colours."""
    rng = np.random.RandomState(7)
    centres = [pts[rng.randint(len(pts))]]
    d = ((pts - centres[0]) ** 2).sum(1)
    for _ in range(1, k):
        total = d.sum()
        if total <= 1e-9:
            break
        centres.append(pts[int(np.searchsorted(np.cumsum(d), rng.rand() * total).clip(0, len(pts) - 1))])
        d = np.minimum(d, ((pts - centres[-1]) ** 2).sum(1))
    c = np.array(centres, dtype=np.float64)
    for _ in range(rounds):
        lab = ((pts[:, None, :] - c[None, :, :]) ** 2).sum(2).argmin(1)
        moved = np.array([pts[lab == i].mean(0) if (lab == i).any() else c[i] for i in range(len(c))])
        if np.allclose(moved, c, atol=0.05):
            break
        c = moved
    return c


def mask_outline(M, mask, sigma=0.7):
    """
    A grid of on/off cells → outline in cells (Y up), its edge running between the cells smoothly: the mask is blurred a
    little and the outline is where it crosses one half. A mask inside another gives an outline inside the other's.
    """
    import numpy as np
    from scipy import ndimage
    from skimage import measure
    rows, cols = mask.shape
    sigma = max(0.3, sigma)
    pad = int(math.ceil(3 * sigma)) + 2                       # room for the blur to fade out: an outline that reaches the edge of the grid would stay open
    field = np.zeros((rows + 2 * pad, cols + 2 * pad), dtype=np.float32)
    field[pad:-pad, pad:-pad] = mask
    field = ndimage.gaussian_filter(field, sigma, mode="constant")
    polys = []
    for line in measure.find_contours(field, 0.5):
        if len(line) < 4:
            continue
        # (row, col) → (x, y) with Y up; the last point repeats the first
        polys.append(np.stack([line[:-1, 1] - pad + 0.5, rows - (line[:-1, 0] - pad + 0.5)], 1).astype(np.float64))
    if not polys:
        return M.CrossSection()
    return M.CrossSection(polys, M.FillRule.EvenOdd)


def _looks_painted(np, rgb, fg):
    """
    A photo or a painting, not a drawing: its cells spread over many colours. Counted on a coarse cube of colour
    (16 steps a channel): a drawing fills nine tenths of itself with a handful of them, a photo needs dozens.
    """
    cells = (rgb[fg] * 15.999).astype(np.int32)
    if len(cells) < 64:
        return False
    keys = cells[:, 0] * 256 + cells[:, 1] * 16 + cells[:, 2]
    counts = np.sort(np.bincount(keys, minlength=4096))[::-1]
    need = int(np.searchsorted(np.cumsum(counts), 0.9 * len(keys))) + 1
    return need > 24


def _opened_mask(np, ndimage, mask, r):
    """A mask without what is narrower than 2r cells (a round nib of radius r drawn inside it, and what it covers)."""
    if r < 0.75:
        return mask
    core = ndimage.distance_transform_edt(mask) > r
    if not core.any():
        return np.zeros(mask.shape, dtype=bool)
    return (ndimage.distance_transform_edt(~core) <= r) & mask


def _painted(np, ndimage, rgb, lab, fg, mm, want, detail, protect=True):
    """
    The colours of a painting or a photo, as areas a printer can make: (label map, centres, how many areas).

    1. The picture is cut into superpixels a little smaller than `detail` mm (SLIC follows the edges of the picture),
       each one the mean of its colour: brush strokes and noise go, edges stay where they are.
    2. More colours are found than asked for and the nearest pairs are joined until `want` are left. A colour that is
       saturated, small and lies in compact patches (a moon, a star, a red nose) is an accent: it is joined last, so
       seven shades of the sky do not push the one yellow out.
    3. Every colour is smoothed to millimetres: what is narrower than `detail` and every island smaller than
       2 x detail squared goes to the colour around it (an accent may be half that small).
    """
    from skimage.segmentation import slic
    LIGHT = np.array([0.7, 1.0, 1.0])                       # Lab: lightness counts for less than colour when two colours are compared
    rows, cols = fg.shape
    side = max(2.0, 0.7 * detail / mm)
    n_seg = int(max(60, min(9000, fg.sum() / (side * side))))
    # (cut over the whole grid: with a mask SLIC is several times slower, and a superpixel on the edge of the picture is
    # weighed by its cells inside the picture only)
    seg = slic(rgb.astype(np.float64), n_segments=int(n_seg * rows * cols / max(1, int(fg.sum()))), compactness=8.0, sigma=0, start_label=1, channel_axis=-1)
    seg = np.where(fg, seg, 0)
    ids = np.unique(seg[fg])
    area = ndimage.sum(fg.astype(np.float64), seg, ids)
    means = np.stack([ndimage.mean(lab[..., c], seg, ids) for c in range(3)], 1)
    lookup = np.zeros(int(seg.max()) + 1, dtype=np.int64)
    lookup[ids] = np.arange(len(ids))

    # 2 · colours: over-segmented, then the nearest joined
    k0 = int(min(len(ids), max(want + 3, 2 * want + 4)))
    weights = area / area.sum()
    repeat = np.repeat(np.arange(len(ids)), np.maximum(1, np.round(weights * 6000).astype(np.int64)))      # a superpixel counts by its area
    centres = _kmeans(np, means[repeat], k0, rounds=16)
    of = ((means[:, None, :] - centres[None, :, :]) ** 2).sum(2).argmin(1)
    r_px = 0.5 * detail / mm
    cell = np.where(fg, of[lookup[seg]], -1)
    cells = max(1, int(fg.sum()))

    half, r_half = cell[::2, ::2], r_px / 2.0                # the patches are weighed on every other cell: four times fewer, the same answer

    def weigh(i):
        own = cell == i
        count = int(own.sum())
        if not count:
            return 0.0, np.zeros(3), 0.0
        small = half == i
        # how much of the colour lies in patches a nozzle can fill: thin strokes scattered over the picture have none
        return count / cells, lab[own].mean(0), float(_opened_mask(np, ndimage, small, r_half).sum()) / max(1, int(small.sum()))

    k = len(centres)
    share, centre, solid = np.zeros(k), np.zeros((k, 3)), np.zeros(k)
    for i in range(k):
        share[i], centre[i], solid[i] = weigh(i)
    alive = [i for i in range(k) if share[i] > 0]
    min_px = 2.0 * detail * detail / (mm * mm)
    accent = {}
    while len(alive) > 1:
        chroma = {i: float(np.hypot(centre[i][1], centre[i][2])) for i in alive}
        big = max(alive, key=lambda i: share[i])
        # an accent: saturated, small, in compact patches big enough to print, and unlike every larger colour
        accent = {}
        for i in alive:
            others = [j for j in alive if j != i and share[j] >= share[i]]
            apart = min((float(np.sqrt(((centre[i] - centre[j]) ** 2).sum())) for j in others), default=0.0)
            accent[i] = bool(protect and i != big and chroma[i] >= 35.0 and share[i] <= 0.12 and solid[i] >= 0.35
                             and share[i] * solid[i] * cells >= 1.5 * min_px and apart >= 22.0)
        # a colour with nothing printable in it is noise: it goes first, whatever it looks like
        noise = [i for i in alive if share[i] * solid[i] * cells < 1.5 * min_px]
        if not noise and len(alive) <= want:
            break
        best = None
        for a in (noise or alive):
            for b in alive:
                if a == b:
                    continue
                # shades of one hue are joined before two hues are: lightness counts for less than colour does
                d = float(np.sqrt((((centre[a] - centre[b]) * LIGHT) ** 2).sum()))
                cost = d * (3.0 if (accent[a] or accent[b]) and not noise else 1.0) * (0.75 + min(0.5, 4.0 * min(share[a], share[b])))
                if best is None or cost < best[0]:
                    best = (cost, a, b)
        _, a, b = best
        into, gone = (a, b) if share[a] >= share[b] else (b, a)
        cell[cell == gone] = into
        share[gone], solid[gone] = 0.0, 0.0
        share[into], centre[into], solid[into] = weigh(into)
        alive = [i for i in alive if i != gone]
    accents = {i for i in alive if accent.get(i)} if len(alive) > 1 else set()

    # 3 · areas in millimetres
    label = np.full((rows, cols), -1, dtype=np.int32)
    for new, old in enumerate(alive):
        label[cell == old] = new
    k = len(alive)
    soft = np.stack([ndimage.gaussian_filter((label == i).astype(np.float32), max(0.6, r_px / 1.5)) * (1.3 if alive[i] in accents else 1.0) for i in range(k)], 0)
    label = np.where(fg, soft.argmax(0).astype(np.int32), -1)
    for _ in range(2):
        doubt = np.zeros((rows, cols), dtype=bool)
        for i in range(k):
            own = label == i
            if not own.any():
                continue
            small = alive[i] in accents
            keep = _opened_mask(np, ndimage, own, r_px * (0.6 if small else 0.9))
            doubt |= own & ~keep
            marks, count = ndimage.label(keep)
            if count:
                sizes = ndimage.sum(np.ones_like(marks), marks, range(1, count + 1))
                tiny = np.flatnonzero(sizes < (0.5 if small else 1.0) * min_px) + 1
                if len(tiny):
                    doubt |= np.isin(marks, tiny)
        sure = fg & ~doubt
        if not doubt.any() or not sure.any():
            break
        _, (iy, ix) = ndimage.distance_transform_edt(~sure, return_indices=True)
        label = np.where(doubt, label[iy, ix], label)
    regions = int(sum(ndimage.label(label == i)[1] for i in range(k)))
    centres = np.array([lab[label == i].mean(0) if (label == i).any() else centre[alive[i]] for i in range(k)])
    return label, centres, regions


def _flat_colors(np, ndimage, lab, fg, mm, want):
    """The colours of a drawing: k-means over its cells, specks and hair lines given to what surrounds them. Returns (label map, centres)."""
    rows, cols = fg.shape
    pts = lab[fg]
    sample = pts[:: max(1, len(pts) // 20000)]
    centres = _kmeans(np, sample.astype(np.float64), want)
    # two centres the eye takes for one colour (a soft shadow, an anti-aliased edge) are one colour
    keep = []
    for c in centres:
        if all(np.sqrt(((c - other) ** 2).sum()) >= 7.0 for other in keep):
            keep.append(c)
    centres = np.array(keep)
    label = np.full((rows, cols), -1, dtype=np.int32)
    label[fg] = ((pts[:, None, :] - centres[None, :, :]) ** 2).sum(2).argmin(1)
    # specks of a colour and lines a cell or two wide (the fringe every soft edge has, a hair no nozzle draws):
    # given to whatever surrounds them
    speck = max(3, int(round(MIN_ISLAND_MM2 / (mm * mm))))
    doubt = np.zeros((rows, cols), dtype=bool)
    for i in range(len(centres)):
        own = label == i
        solid = ndimage.binary_opening(own, iterations=1)
        doubt |= own & ~solid
        marks, count = ndimage.label(solid)
        if count:
            sizes = ndimage.sum(np.ones_like(marks), marks, range(1, count + 1))
            small = np.flatnonzero(sizes < speck) + 1
            if len(small):
                doubt |= np.isin(marks, small)
    sure = fg & ~doubt
    if doubt.any() and sure.any():
        _, (iy, ix) = ndimage.distance_transform_edt(~sure, return_indices=True)
        label = np.where(doubt, label[iy, ix], label)
    return label, centres


def _read_colors(path, width_mm, o):
    """
    The part of colors() that needs no geometry: the picture read, its background found, its colours chosen and every
    cell given one of them. Returns a dict: listed (cells: 0 outside, 1 = the colour with the largest area …), order
    (bottom to top), chosen {n: (code, hex, picture hex)}, near, mm (millimetres per cell), found, want, background,
    ignored, total (cells of the picture), look, regions (painting: how many areas the colours fall into), suggest.
    """
    import numpy as np
    from PIL import Image, ImageEnhance, ImageOps
    from scipy import ndimage
    o = o or {}
    want = int(max(1, min(MAX_COLORS, o.get("n", 4))))
    ignored = 0
    if str(path).lower().endswith(".svg"):
        img, ignored = _svg_picture(path, COLOR_PX)
    else:
        try:
            img = Image.open(path)
            if o.get("look") == "painting":
                # a photo from a phone is twenty times the cells that are read: a JPEG is decoded small straight away
                img.draft("RGB", (2 * COLOR_PX, 2 * COLOR_PX))
            img = ImageOps.exif_transpose(img)
            img.load()
        except Exception:  # noqa: BLE001
            raise ArtworkError("image_unreadable")
    img = img.convert("RGBA")
    scale = COLOR_PX / max(img.size)
    if scale < 1:
        img = img.resize((max(8, round(img.size[0] * scale)), max(8, round(img.size[1] * scale))), Image.LANCZOS)
    alpha = np.asarray(img.getchannel("A"), dtype=np.uint8)
    rgb_img = Image.alpha_composite(Image.new("RGBA", img.size, (255, 255, 255, 255)), img).convert("RGB")
    for tool, key in ((ImageEnhance.Contrast, "contrast"), (ImageEnhance.Brightness, "brightness"), (ImageEnhance.Color, "saturation")):
        v = float(o.get(key, 1.0))
        if abs(v - 1.0) > 0.01:
            rgb_img = tool(rgb_img).enhance(max(0.2, min(3.0, v)))
    rgb = np.asarray(rgb_img, dtype=np.float32) / 255.0
    rgb = np.stack([ndimage.median_filter(rgb[..., i], 3) for i in range(3)], -1)      # the fringe of anti-aliased edges
    lab = _lab(rgb)
    rows, cols = alpha.shape

    # 1 · what is the picture and what is its background
    background = "kept"
    fg = np.ones((rows, cols), dtype=bool)
    if o.get("background", "auto") != "keep":
        if float((alpha < 128).mean()) > 0.01:
            fg = alpha >= 128
            background = "alpha"
        else:
            edge = np.concatenate([lab[0], lab[-1], lab[:, 0], lab[:, -1]])
            paper = np.median(edge, axis=0)
            tol = 3.0 + 0.35 * float(max(0, min(100, o.get("background_strength", 30))))      # ΔE 3 … 38, 13 by default
            like = np.sqrt(((lab - paper) ** 2).sum(-1)) < tol
            marks, _ = ndimage.label(like)
            touching = np.unique(np.concatenate([marks[0], marks[-1], marks[:, 0], marks[:, -1]]))
            fg = ~np.isin(marks, touching[touching > 0])
            background = "removed"
        fg = ndimage.binary_opening(fg, iterations=1)
        share = float(fg.mean())
        if share < 0.01:
            raise ArtworkError("image_blank", "0")
        if share > 0.995:                                    # nothing to remove: the whole picture is the motif
            fg = np.ones((rows, cols), dtype=bool)
            background = "kept"
    ys, xs = np.nonzero(fg)
    r0, r1, c0, c1 = ys.min(), ys.max() + 1, xs.min(), xs.max() + 1
    fg, lab, rgb = fg[r0:r1, c0:c1], lab[r0:r1, c0:c1], rgb[r0:r1, c0:c1]
    rows, cols = fg.shape
    # millimetres per cell: as wide as asked, or fitted another way (o["fit"]): inside a box, inside a circle, covering a box
    how = o.get("fit") or ("width", width_mm)
    mm = {"box": lambda: min(how[1] / cols, how[2] / rows), "circle": lambda: how[1] / math.hypot(cols, rows),
          "cover": lambda: max(how[1] / cols, how[2] / rows)}.get(how[0], lambda: how[1] / cols)()
    if rows * mm > 400:
        raise ArtworkError("artwork_too_tall")

    suggest = "painting" if _looks_painted(np, rgb, fg) else "graphic"
    painting = o.get("look") == "painting"
    detail = float(max(1.0, min(5.0, o.get("detail", 2.0)))) if painting else 0.0
    regions = 0
    if painting:
        label, centres, regions = _painted(np, ndimage, rgb, lab, fg, mm, want, detail, bool(o.get("protect_accents", True)))
    else:
        label, centres = _flat_colors(np, ndimage, lab, fg, mm, want)
    counts = np.array([(label == i).sum() for i in range(len(centres))], dtype=np.float64)
    total = float(counts.sum())
    # a colour that covers next to nothing is noise; the rest go by size, biggest first: that is the order they are listed in
    # (the areas of a painting were already weighed one by one: a small moon is no noise)
    alive = [int(i) for i in np.argsort(-counts) if counts[i] / total >= (0.0 if painting else 0.003) and counts[i] > 0] or [int(np.argmax(counts))]
    if len(alive) < len(centres):
        gone = ~np.isin(label, alive) & fg
        sure = fg & ~gone
        _, (iy, ix) = ndimage.distance_transform_edt(~sure, return_indices=True)
        label = np.where(gone, label[iy, ix], label)
    listed = np.zeros((rows, cols), dtype=np.int32)          # 1 = the colour with the largest area
    for n, old in enumerate(alive):
        listed[label == old] = n + 1
    found = len(alive)
    for pair in o.get("merge") or []:
        try:
            a, b = int(pair[0]), int(pair[1])
        except (TypeError, ValueError, IndexError):
            continue
        if a != b and 1 <= a <= found and 1 <= b <= found:
            listed[listed == a] = b
    present = [n for n in range(1, found + 1) if (listed == n).any()]
    order = []
    for x in o.get("order") or []:
        try:
            n = int(x)
        except (TypeError, ValueError):
            continue
        if n in present and n not in order:
            order.append(n)
    rest = [n for n in present if n not in order]
    if painting and not order and len(rest) > 2:
        light = {n: float(lab[listed == n][:, 0].mean()) for n in rest}
        dark = min(rest, key=lambda n: light[n])
        if dark != rest[0] and light[dark] < 35.0:
            rest = [n for n in rest if n != dark] + [dark]
    order += rest

    # 3 · each colour a filament: the nearest one of the palette, a different one for each colour while there are enough
    palette = [(str(code), hex_lab(hx), str(hx)) for code, hx in (o.get("palette") or []) if hex_lab(hx) is not None]
    assign = {}
    for k, v in (o.get("assign") or {}).items():
        if str(k).isdigit() and isinstance(v, str):
            assign[int(k)] = v
    by_code = {code: hx for code, _, hx in palette}
    free = bool(o.get("free"))
    chosen, used, near = {}, set(), {}
    core = None
    if painting:
        # a painting's areas were smoothed to millimetres: an area is shown in the colour of the cells that really are
        # of it, not dulled by the strokes of other colours it swallowed
        middles = np.array([lab[listed == n].mean(0) for n in present])
        core = np.zeros((rows, cols), dtype=np.int32)
        core[fg] = np.array(present)[((lab[fg][:, None, :] - middles[None, :, :]) ** 2).sum(2).argmin(1)]
    for n in present:                                        # biggest area first
        cells_of = listed == n
        if core is not None and (cells_of & (core == n)).sum() >= 0.2 * cells_of.sum():
            cells_of = cells_of & (core == n)
            # … and of those the more saturated half: a filament is a pure colour, the paint round a moon is not
            chroma = np.hypot(lab[..., 1], lab[..., 2])
            vivid = cells_of & (chroma >= np.median(chroma[cells_of]))
            if vivid.sum() >= 8:
                cells_of = vivid
        mean = lab[cells_of].mean(0)
        own = rgb[cells_of].mean(0)
        picture_hex = "#%02x%02x%02x" % tuple(int(round(float(v) * 255)) for v in own)
        if free and palette:
            near[n] = min(palette, key=lambda f: float(((f[1] - mean) ** 2).sum()))[0]
        if n in assign and (assign[n] in by_code or not palette):
            chosen[n] = (assign[n], by_code.get(assign[n], picture_hex), picture_hex)
        elif free:
            chosen[n] = (picture_hex, picture_hex, picture_hex)
        elif palette:
            ranked = sorted(palette, key=lambda f: float(((f[1] - mean) ** 2).sum()))
            pick = next((f for f in ranked if f[0] not in used), ranked[0])
            # a filament already taken is still the right one when the next free one is far off
            if pick is not ranked[0] and float(np.sqrt(((pick[1] - mean) ** 2).sum())) > 2.5 * float(np.sqrt(((ranked[0][1] - mean) ** 2).sum())) + 12:
                pick = ranked[0]
            chosen[n] = (pick[0], pick[2], picture_hex)
        else:
            chosen[n] = ("", picture_hex, picture_hex)
        used.add(chosen[n][0])

    return {"listed": listed, "order": order, "chosen": chosen, "near": near, "mm": mm, "found": found, "want": want, "background": background, "ignored": ignored,
            "total": total, "look": "painting" if painting else "graphic", "regions": regions, "suggest": suggest, "detail": detail}


def colors(M, path, width_mm, o=None):
    """
    A picture → the areas of its colours, each one a filament of the farm.

    o: n (1–8 colours), background ("auto" removes it: the alpha channel when there is one, else what is connected to
    the edge and looks like it; "keep" leaves the picture whole), background_strength 0–100, smooth (mm),
    contrast / brightness / saturation (1 = as is), palette [[code, hex], …] the filaments to choose from,
    assign {index: code} the visitor's own choice, merge [[from, into], …], order [index, …] bottom to top,
    free (true: no colour is matched to the palette; each keeps the colour it has in the picture, its hex is its code,
    and the palette only says what the visitor's own choices look like. "near" then names the palette's nearest colour).

    Returns (layers, info). layers bottom to top: {"index", "rgb" (hex of the picture), "code", "hex", "near", "share",
    "own" (the area of this colour alone), "stack" (this colour and everything above it: what lies in its layer when the
    colours are printed one on another)}. All in millimetres, the silhouette's lower-left corner at the origin,
    width_mm wide, or fitted as o["fit"] says: ("box", w, h) inside a box, ("circle", d) inside a circle, ("cover", w, h)
    covering a box. info: silhouette, size, found (how many colours the picture really had), background.
    """
    import numpy as np
    o = o or {}
    read = _read_colors(path, width_mm, o)
    listed, order, chosen, near, mm, found, want, background, ignored, total = (read[k] for k in ("listed", "order", "chosen", "near", "mm", "found", "want", "background", "ignored", "total"))
    rows, cols = listed.shape
    painting = read["look"] == "painting"

    # 4 · outlines: bottom to top, every layer holds its colour and all the colours above it
    sigma = 0.7 + 0.5 * float(max(0.0, min(1.0, o.get("smooth", 0.3)))) / mm
    # a traced edge has a point per cell: a tenth of them draws the same line. The areas of a painting were already
    # simplified to millimetres: their edge is rounded over a quarter of the smallest detail and needs no more points
    # than a nozzle can follow
    exact = 0.03
    if painting:
        sigma, exact = max(sigma, 0.25 * read["detail"] / mm), 0.15
    above = np.zeros((rows, cols), dtype=bool)
    stacks = {}
    for n in reversed(order):
        above = above | (listed == n)
        stacks[n] = mask_outline(M, above, sigma).scale([mm, mm]).simplify(exact)
    silhouette = stacks[order[0]]
    if silhouette.is_empty():
        raise ArtworkError("image_blank", "0")
    layers = []
    for pos, n in enumerate(order):
        stack = stacks[n]
        own = stack - stacks[order[pos + 1]] if pos + 1 < len(order) else stack
        code, hx, picture_hex = chosen[n]
        layers.append({"index": n, "rgb": picture_hex, "code": code, "hex": hx, "near": near.get(n, code), "share": round(float((listed == n).sum()) / total, 4), "own": own, "stack": stack})
    x0, y0, x1, y1 = silhouette.bounds()
    move = [-x0, -y0]
    for layer in layers:
        layer["own"], layer["stack"] = layer["own"].translate(move), layer["stack"].translate(move)
    info = {"source": "svg" if str(path).lower().endswith(".svg") else "image", "silhouette": silhouette.translate(move), "size": [x1 - x0, y1 - y0], "found": found,
            "wanted": want, "background": background, "cell_mm": round(mm, 3), "ignored_outlines": ignored,
            "look": read["look"], "regions": read["regions"], "suggest": read["suggest"], "detail": read["detail"]}
    return layers, info


def looks_like(path):
    """
    A quick look at a picture: ("painting" | "graphic", whether it is cut out of its background by transparency,
    its height over its width).
    A drawing in an SVG is a graphic; a raster picture is judged on a small copy by how many colours it spreads over.
    """
    import numpy as np
    from PIL import Image, ImageOps
    if str(path).lower().endswith(".svg"):
        return "graphic", True, 1.0
    try:
        img = ImageOps.exif_transpose(Image.open(path))
        img.draft("RGB", (400, 400))
        img = img.convert("RGBA")
    except Exception:  # noqa: BLE001
        raise ArtworkError("image_unreadable")
    tall = img.size[1] / max(1, img.size[0])
    img.thumbnail((200, 200), Image.BILINEAR)
    alpha = np.asarray(img.getchannel("A"), dtype=np.uint8)
    cut_out = float((alpha < 128).mean()) > 0.01
    rgb = np.asarray(Image.alpha_composite(Image.new("RGBA", img.size, (255, 255, 255, 255)), img).convert("RGB"), dtype=np.float32) / 255.0
    fg = alpha >= 128 if cut_out else np.ones(alpha.shape, dtype=bool)
    return ("painting" if _looks_painted(np, rgb, fg) else "graphic"), cut_out, tall


def poster(path, width_mm, o=None):
    """
    The picture as the tool will make it, flat: every cell in the colour of its filament, the background clear. No
    geometry is traced, so it is there at once: what the page shows while the model is still being built.
    Returns (PIL image RGBA, info): colors bottom to top [{index, rgb, code, hex, share}], regions, look, suggest,
    found, wanted, background, cell_mm.
    """
    import numpy as np
    from PIL import Image
    o = o or {}
    read = _read_colors(path, width_mm, o)
    listed = read["listed"]
    rows, cols = listed.shape
    out = np.zeros((rows, cols, 4), dtype=np.uint8)
    listed_colors = []
    for n in read["order"]:
        code, hx, picture = read["chosen"][n]
        own = listed == n
        look = hx if hex_lab(hx) is not None else picture
        out[own] = [int(look[i:i + 2], 16) for i in (1, 3, 5)] + [255]
        listed_colors.append({"index": n, "rgb": picture, "code": code, "hex": look, "share": round(float(own.sum()) / read["total"], 4)})
    info = {"colors": listed_colors, "regions": read["regions"], "look": read["look"], "suggest": read["suggest"], "found": read["found"], "wanted": read["want"],
            "background": read["background"], "cell_mm": round(read["mm"], 3), "detail": read["detail"]}
    return Image.fromarray(out, "RGBA"), info


def thinned(sections, points, exact=0.15):
    """
    Outlines drawn with fewer points, until all of them together have at most `points`: a fine detail on a big picture
    is many areas, and a point of an outline is about four triangles of a plate. Returns the outlines in their order.
    """
    out = [cs.simplify(exact) for cs in sections]
    while exact < 0.6 and sum(len(poly) for cs in out for poly in cs.to_polygons()) > points:
        exact *= 1.5
        out = [cs.simplify(exact) for cs in out]
    return out


def joined(M, base, grow, link_w):
    """
    One piece out of many: what would fall apart (separate letters, the dot of an i, a star beside the moon) is tied to
    its nearest neighbour by a short link. Returns (outline, number of links).
    """
    import numpy as np
    C = M.CrossSection
    links = 0
    for _ in range(60):
        pieces = base.decompose()
        if len(pieces) <= 1:
            break
        pieces.sort(key=lambda c: c.area(), reverse=True)
        small = pieces[-1]
        a = np.vstack([np.asarray(poly) for poly in small.to_polygons()])
        best = None
        for other in pieces[:-1]:
            b = np.vstack([np.asarray(poly) for poly in other.to_polygons()])
            if len(b) > 1500:
                b = b[:: len(b) // 1500 + 1]
            d = np.linalg.norm(a[:, None, :] - b[None, :, :], axis=2)
            i, j = np.unravel_index(int(np.argmin(d)), d.shape)
            if best is None or d[i, j] < best[0]:
                best = (float(d[i, j]), a[i], b[j])
        _, pa, pb = best
        # the link reaches a little into both pieces, so it is a real joint and not a touching edge
        way = (pb - pa) / (np.linalg.norm(pb - pa) or 1.0)
        pa, pb = pa - way * grow, pb + way * grow
        dot = C.circle(link_w / 2, 24)
        base = base + (dot.translate([float(pa[0]), float(pa[1])]) + dot.translate([float(pb[0]), float(pb[1])])).hull()
        links += 1
    return base, links


def outer_ring(cs):
    """The biggest outer contour of an outline as an array of points, counter-clockwise."""
    import numpy as np
    best, best_area = None, 0.0
    for poly in cs.to_polygons():
        pts = np.asarray(poly, dtype=np.float64)
        if len(pts) < 3:
            continue
        area = 0.5 * float(np.sum(pts[:, 0] * np.roll(pts[:, 1], -1) - np.roll(pts[:, 0], -1) * pts[:, 1]))
        if area > best_area:
            best, best_area = pts, area
    return best


def dense(ring, step=1.0):
    """The same contour with a point at least every `step` millimetres: a long straight edge gets a middle to start from."""
    import numpy as np
    out = []
    for a, b in zip(ring, np.roll(ring, -1, axis=0)):
        n = max(1, int(math.ceil(float(np.linalg.norm(b - a)) / step)))
        for i in range(n):
            out.append(a + (b - a) * (i / n))
    return np.array(out, dtype=np.float64)


def started(ring, point):
    """The same contour with one more point where it comes nearest to `point`, marked as the place `along` counts from."""
    import numpy as np
    at = int(np.argmin(((ring - np.asarray(point, dtype=np.float64)) ** 2).sum(axis=1)))
    return np.concatenate([ring, [[float("nan"), float(at)]]])


def along(ring, share):
    """
    The point of a contour at a share of its length, counted clockwise from its topmost point, and the direction that
    points out of the shape there: (x, y, nx, ny). Where an eyelet goes when "12 % round the outline" is asked for.
    """
    import numpy as np
    start = None
    if len(ring) and np.isnan(ring[-1, 0]):                  # see started(): the last row names the point to count from
        start, ring = int(ring[-1, 1]), ring[:-1]
    pts = ring[::-1]                                         # clockwise
    top = len(ring) - 1 - start if start is not None else int(np.argmax(pts[:, 1] - 1e-6 * np.abs(pts[:, 0] - pts[:, 0].mean())))
    pts = np.roll(pts, -top, axis=0)
    seg = np.linalg.norm(np.roll(pts, -1, axis=0) - pts, axis=1)
    total = float(seg.sum())
    run = np.cumsum(seg)

    def point(s):
        s = s % total
        j = int(np.searchsorted(run, s, side="right").clip(0, len(pts) - 1))
        b = run[j - 1] if j else 0.0
        return pts[j] + (pts[(j + 1) % len(pts)] - pts[j]) * ((s - b) / max(seg[j], 1e-9))

    at = (share % 1.0) * total
    p = point(at)
    # the direction of travel averaged over a few millimetres: a traced outline wiggles from cell to cell
    reach = max(1.5, total * 0.01)
    d = point(at + reach) - point(at - reach)
    d = d / (np.linalg.norm(d) or 1.0)
    return float(p[0]), float(p[1]), float(-d[1]), float(d[0])     # clockwise travel: out of the shape is to the left
