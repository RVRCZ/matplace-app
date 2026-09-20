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


def text(M, lines, font_path, cap_height_mm, line_gap=0.35, align="center"):
    """Lines of text → outline. cap_height_mm is the height of a capital letter, so "12 mm text" means what people expect."""
    import numpy as np
    from fontTools.ttLib import TTFont
    lines = [l for l in (str(x).strip() for x in lines) if l]
    if not lines:
        raise ArtworkError("no_text")
    font = TTFont(font_path)
    gs, cmap, hmtx = font.getGlyphSet(), font.getBestCmap(), font["hmtx"]
    units = font["head"].unitsPerEm
    cap = getattr(font["OS/2"], "sCapHeight", 0) or units * 0.72
    k = cap_height_mm / cap
    polys, missing, widths, total = [], set(), [], 0
    rows = []
    for line in lines:
        x, row = 0.0, []
        for ch in line:
            g = cmap.get(ord(ch))
            if g is None:
                if not ch.isspace():
                    missing.add(ch)
                x += units * 0.3
                continue
            for poly in _glyph_polys(font, gs, g):
                row.append([(px + x, py) for px, py in poly])
            x += hmtx[g][0]
        rows.append(row)
        widths.append(x)
    wmax = max(widths) if widths else 0
    step = cap * (1 + line_gap) + units * 0.12
    for i, row in enumerate(rows):
        dx = {"left": 0, "right": wmax - widths[i]}.get(align, (wmax - widths[i]) / 2)
        dy = -i * step
        for poly in row:
            total += len(poly)
            polys.append(np.array([((px + dx) * k, (py + dy) * k) for px, py in poly], dtype=np.float64))
    if not polys:
        raise ArtworkError("no_text")
    if total > MAX_POINTS:
        raise ArtworkError("too_complex")
    cs = M.CrossSection(polys, M.FillRule.NonZero)
    return cs, {"missing_chars": sorted(missing), "source": "text"}


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

def raster(M, path, width_mm, threshold=None, invert=False):
    """Dark-on-light picture → outline. Works for logos and silhouettes, not for photographs (that is what the relief tool is for)."""
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


def load(M, p, width_mm, font_path=None, cap_height_mm=None):
    """One entry for every tool: params carry either text lines or an uploaded artwork file."""
    art = p.get("artwork_path")
    if art:
        ext = art.rsplit(".", 1)[-1].lower()
        if ext == "svg":
            return svg(M, art, width_mm)
        return raster(M, art, width_mm, invert=bool(p.get("invert", False)))
    lines = p.get("lines") or [p.get("text", "")]
    cs, info = text(M, lines, font_path, cap_height_mm or 10)
    if width_mm and not cap_height_mm:
        cs = fit(cs, width_mm=width_mm)
    return cs, info


def slugify(s):
    return re.sub(r"[^a-z0-9]+", "-", s.lower()).strip("-")[:40] or "model"


def rounded_rect(M, w, d, r):
    r = max(0.0, min(r, w / 2 - 0.01, d / 2 - 0.01))
    if r < 0.05:
        return M.CrossSection.square([w, d])
    return M.CrossSection.square([w - 2 * r, d - 2 * r]).translate([r, r]).offset(r, M.JoinType.Round, 2.0, 48)


def deg(a):
    return a * math.pi / 180.0
