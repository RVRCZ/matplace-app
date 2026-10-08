"""
A drawer insert made to measure from a photo, for param_tool.py: things laid on a sheet of A4 paper and photographed
from above become pockets of their own shape in a tray, like a foam insert of a tool case.

The sheet is the ruler. It is found in the photo as the biggest bright, colourless area, its four corners are pulled
straight to 210 × 297 mm, and whatever is darker or more colourful than the paper inside it is a thing. Every thing
gets a pocket of its outline with some play, as deep as asked, and a notch for a finger to lift it out.
"""
import math

import shape2d as S

A4 = (210.0, 297.0)
PX = 3.0                    # cells per millimetre of the straightened sheet
EDGE = 4.0                  # a band along the edge of the sheet that is not looked at: shadows of the paper, the table showing through
SMALLEST = 40.0             # mm²: what is smaller is a crumb or a shadow, not a thing

LIMITS = {"insert": {"depth": (4, 40), "gap": (0.3, 3), "floor": (1.2, 4), "margin": (2, 15)}}


def _num(Invalid, p, key, default):
    lo, hi = LIMITS["insert"][key]
    try:
        v = float(p.get(key, default))
    except (TypeError, ValueError):
        raise Invalid("not_a_number", key)
    if math.isnan(v) or v < lo or v > hi:
        raise Invalid("out_of_range", "%s %s-%s" % (key, lo, hi))
    return v


def things_on_sheet(path):
    """
    The photo → a grid of the straightened sheet (rows × cols, PX cells per mm, True where a thing lies) and its size in
    millimetres (width, height). Raises S.ArtworkError: image_unreadable, sheet_not_found, sheet_empty.
    """
    import numpy as np
    from PIL import Image, ImageOps
    from scipy import ndimage
    from skimage import transform
    try:
        img = ImageOps.exif_transpose(Image.open(path)).convert("RGB")
    except Exception:  # noqa: BLE001
        raise S.ArtworkError("image_unreadable")
    img.thumbnail((1100, 1100), Image.LANCZOS)
    a = np.asarray(img, dtype=np.float32) / 255.0
    light, colour = a.mean(axis=2), a.max(axis=2) - a.min(axis=2)
    # paper: bright (above the split between the light and the dark half of the photo) and nearly colourless
    hist, _ = np.histogram(light, bins=64, range=(0, 1))
    w = np.cumsum(hist).astype(np.float64)
    m = np.cumsum(hist * np.arange(64)).astype(np.float64)
    with np.errstate(divide="ignore", invalid="ignore"):
        between = (m[-1] * w - m * w[-1]) ** 2 / (w * (w[-1] - w))
    split = (float(np.nanargmax(between)) + 0.5) / 64 if not np.all(np.isnan(between)) else 0.6
    paper = ndimage.binary_opening((light > max(split, 0.45)) & (colour < 0.16), iterations=2)
    labels, count = ndimage.label(paper)
    if not count:
        raise S.ArtworkError("sheet_not_found")
    sizes = ndimage.sum(paper, labels, range(1, count + 1))
    sheet = ndimage.binary_fill_holes(labels == int(np.argmax(sizes)) + 1)          # the things on it are holes in it
    if sheet.mean() < 0.12:
        raise S.ArtworkError("sheet_not_found")
    ys, xs = np.nonzero(sheet)
    # its four corners: the points furthest towards the four corners of the photo
    tl, br = int(np.argmin(xs + ys)), int(np.argmax(xs + ys))
    tr, bl = int(np.argmax(xs - ys)), int(np.argmin(xs - ys))
    corners = np.array([[xs[i], ys[i]] for i in (tl, tr, br, bl)], dtype=np.float64)
    quad = 0.5 * abs(sum(corners[i][0] * corners[(i + 1) % 4][1] - corners[(i + 1) % 4][0] * corners[i][1] for i in range(4)))
    if quad < 1 or sheet.sum() / quad < 0.9 or sheet.sum() / quad > 1.1:
        raise S.ArtworkError("sheet_not_found")                 # not a four-cornered thing: a window, a table top, a sheet with a corner out of the photo
    top, left = np.linalg.norm(corners[1] - corners[0]), np.linalg.norm(corners[3] - corners[0])
    width, height = (A4[1], A4[0]) if top > left else A4          # lying or standing, as it lies in the photo
    cols, rows = int(round(width * PX)), int(round(height * PX))
    flat = np.array([[0, 0], [cols, 0], [cols, rows], [0, rows]], dtype=np.float64)
    if hasattr(transform.ProjectiveTransform, "from_estimate"):       # the newer name of the same thing
        to_photo = transform.ProjectiveTransform.from_estimate(flat, corners)
    else:
        to_photo = transform.ProjectiveTransform()
        to_photo.estimate(flat, corners)
    flat_light = transform.warp(light, to_photo, output_shape=(rows, cols), order=1, mode="edge")
    flat_colour = transform.warp(colour, to_photo, output_shape=(rows, cols), order=1, mode="edge")
    white = float(np.median(flat_light))
    thing = (flat_light < white - 0.14) | (flat_colour > 0.22)
    band = int(round(EDGE * PX))
    thing[:band, :] = thing[-band:, :] = False
    thing[:, :band] = thing[:, -band:] = False
    thing = ndimage.binary_fill_holes(ndimage.binary_closing(ndimage.binary_opening(thing, iterations=2), iterations=3))
    labels, count = ndimage.label(thing)
    if count:
        sizes = ndimage.sum(thing, labels, range(1, count + 1))
        for i in np.flatnonzero(sizes < SMALLEST * PX * PX):
            thing[labels == i + 1] = False
    if not thing.any():
        raise S.ArtworkError("sheet_empty")
    return thing, (width, height)


def insert(M, Invalid, p):
    """
    A tray with a pocket for every thing photographed on a sheet of A4: the outlines with `gap` of play all round, `depth`
    deep over a floor, the tray `margin` wider than the things together. A notch beside each pocket lets a finger under
    the thing. One piece, one colour, printed as it lies in the drawer.
    """
    C, J = M.CrossSection, M.JoinType.Round
    n = lambda key, d: _num(Invalid, p, key, d)       # noqa: E731
    depth, gap, floor, margin = n("depth", 15), n("gap", 1.5), n("floor", 1.6), n("margin", 5)
    notch = bool(p.get("notch", True))
    path = p.get("artwork_path")
    if not path:
        raise Invalid("no_photo")
    if str(path).lower().endswith(".svg"):
        raise Invalid("photo_needed")
    try:
        grid, sheet = things_on_sheet(path)
    except S.ArtworkError as e:
        raise Invalid(e.code)
    shapes = S.mask_outline(M, grid, 1.2).scale([1 / PX, 1 / PX]).simplify(0.15)
    things = sorted((piece for piece in shapes.decompose() if piece.area() >= SMALLEST), key=lambda c: (c.bounds()[0], c.bounds()[1]))
    if not things:
        raise Invalid("sheet_empty")
    pockets, sizes = C(), []
    for thing in things:
        # the soft edge of a thing in a photo is read as part of it: about half a millimetre all round, taken back here
        pocket = thing.offset(gap - 0.5, J, 2.0, 24)
        x0, y0, x1, y1 = pocket.bounds()
        sizes.append([round(x1 - x0 - 2 * gap, 1), round(y1 - y0 - 2 * gap, 1)])
        if notch:
            # a round bite out of the pocket's long side, level with its middle and on its very edge: room for a fingertip
            if (x1 - x0) >= (y1 - y0):
                cut = pocket ^ C.square([2.0, y1 - y0 + 2]).translate([(x0 + x1) / 2 - 1.0, y0 - 1])
                mid = ((x0 + x1) / 2, cut.bounds()[1] if not cut.is_empty() else y0)
            else:
                cut = pocket ^ C.square([x1 - x0 + 2, 2.0]).translate([x0 - 1, (y0 + y1) / 2 - 1.0])
                mid = (cut.bounds()[0] if not cut.is_empty() else x0, (y0 + y1) / 2)
            pocket = pocket + C.circle(9.0, 48).translate(list(mid))
        pockets = pockets + pocket
    x0, y0, x1, y1 = pockets.bounds()
    plan = S.rounded_rect(M, x1 - x0 + 2 * margin, y1 - y0 + 2 * margin, min(6.0, margin)).translate([x0 - margin, y0 - margin])
    tray = plan.extrude(floor + depth) - (pockets ^ plan.offset(-1.2, J, 2.0, 16)).extrude(depth + 1).translate([0, 0, floor])
    px0, py0, px1, py1 = plan.bounds()
    warn = []
    if max(px1 - px0, py1 - py0) > 250:
        warn.append("insert_big")
    notes = {"outer": [round(px1 - px0, 1), round(py1 - py0, 1), round(floor + depth, 1)], "things": sizes, "sheet": [round(sheet[0]), round(sheet[1])],
             "warnings": warn, "thin_pct": 0, "missing_chars": []}
    return {"all": tray.translate([-px0, -py0, 0])}, notes


BUILDERS = {"insert": insert}
