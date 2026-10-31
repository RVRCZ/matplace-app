"""
Traces our own logo (public/img/logo.png, the only big source we have) into engines/artwork/colour/matplace.svg: three
flat colours in paint order, the dark outline under the turquoise and the copper filament. Our logo, not CC0 (SOURCES.md).

    pip install potracer pillow numpy scipy
    python engines/artwork/colour/_trace_logo.py

The picture is enlarged four times before tracing, so the edges of the letters come out as curves, not as steps.
"""
import os

import numpy as np
import potrace
from PIL import Image
from scipy import ndimage

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(os.path.dirname(HERE)))
TEAL, DARK, COPPER = (45, 168, 153), (0, 68, 57), (194, 89, 65)
SCALE = 4


def trace(mask):
    """A boolean picture → the `d` of one path in the units of the original picture (even-odd: a ring in a ring is a hole)."""
    d = []
    mask = ndimage.gaussian_filter(mask.astype(np.float32), SCALE * 0.8) > 0.5      # the steps of the source's pixels go
    # turdsize: specks the size of a few original pixels, what is left of the anti-aliased edge between two colours
    for curve in potrace.Bitmap(~mask).trace(  # the library takes True for paper
       turdsize=30 * SCALE, alphamax=1.1, opticurve=True, opttolerance=0.6):
        at = lambda p: "%.1f %.1f" % (p.x / SCALE, p.y / SCALE)     # noqa: E731
        d.append("M" + at(curve.start_point))
        for s in curve.segments:
            d.append("L%s L%s" % (at(s.c), at(s.end_point)) if s.is_corner else "C%s %s %s" % (at(s.c1), at(s.c2), at(s.end_point)))
        d.append("Z")
    return "".join(d)


def main():
    src = Image.open(os.path.join(ROOT, "public", "img", "logo.png")).convert("RGBA")
    w, h = src.size
    big = np.asarray(src.resize((w * SCALE, h * SCALE), Image.LANCZOS), dtype=np.float32)
    logo = big[..., 3] >= 128
    rgb = big[..., :3]
    colours = np.array([DARK, TEAL, COPPER], dtype=np.float32)
    nearest = ((rgb[:, :, None, :] - colours[None, None, :, :]) ** 2).sum(-1).argmin(-1)
    # the dark outline fading out at the edge passes through a colour nearest to turquoise: turquoise never touches the
    # edge of the logo, so a ring of it there is dropped
    inside = ndimage.binary_erosion(logo, iterations=3 * SCALE)
    layers = [
        (DARK, logo),                                         # the whole logo: the two colours above lie on it without a gap
        (TEAL, (nearest == 1) & inside),
        (COPPER, (nearest == 2) & logo),
    ]
    paths = ['<path fill-rule="evenodd" fill="#%02x%02x%02x" d="%s"/>' % (*c, trace(mask)) for c, mask in layers]
    with open(os.path.join(HERE, "matplace.svg"), "w", encoding="utf-8", newline="\n") as f:
        f.write('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d">\n%s\n</svg>\n' % (w, h, "\n".join(paths)))


if __name__ == "__main__":
    main()
