#!/usr/bin/env python3
"""
A print's photo on a backdrop (the photo box, TODO #48): the cut-out from photo_cut.py (an RGBA PNG) is put on a drawn
or textured background, standing on a soft shadow, at the size the place wants (the web 3:2, a Short 9:16, a square
for the end of the square Short). Without a cut-out (rembg not installed) the photo itself is fitted and cropped.

  photo_compose.py <cutout.png|photo.jpg> <out.jpg> --bg studio --size 1200x800 [--cut 1|0] [--webp out.webp]
                   [--backgrounds <dir with wood.jpg, paper.jpg …>]

Backdrops: studio (a light grey vignette), white, gradient (a cool light gradient), or a texture by name from the
backgrounds folder (wood, paper, marble, concrete, linen; photo_backgrounds.py draws them). One JSON object on stdout.
"""
import json
import os
import sys

from PIL import Image, ImageDraw, ImageFilter, ImageOps


def out(obj):
    print(json.dumps(obj))
    sys.exit(0)


def arg(argv, name, default=None):
    return argv[argv.index(name) + 1] if name in argv else default


def radial(size, inner, outer):
    """A vignette: `inner` in the middle, `outer` at the corners."""
    w, h = size
    small = Image.new("RGB", (64, 64 * h // w if w >= h else 64), outer)
    draw = ImageDraw.Draw(small)
    sw, sh = small.size
    steps = 48
    for i in range(steps):
        k = i / steps
        color = tuple(int(outer[c] + (inner[c] - outer[c]) * (1 - k)) for c in range(3))
        rx, ry = sw * 0.75 * (1 - k) + sw * 0.05, sh * 0.75 * (1 - k) + sh * 0.05
        draw.ellipse([sw / 2 - rx, sh / 2 - ry, sw / 2 + rx, sh / 2 + ry], fill=color)
    return small.resize(size, Image.BICUBIC).filter(ImageFilter.GaussianBlur(min(size) * 0.02))


def linear(size, top, bottom):
    w, h = size
    strip = Image.new("RGB", (1, h))
    px = strip.load()
    for y in range(h):
        k = y / max(1, h - 1)
        px[0, y] = tuple(int(top[c] + (bottom[c] - top[c]) * k) for c in range(3))
    return strip.resize(size, Image.BICUBIC)


def backdrop(name, size, folder):
    if name == "white":
        return Image.new("RGB", size, (255, 255, 255))
    if name == "gradient":
        return linear(size, (226, 236, 250), (247, 249, 252))
    if name != "studio" and folder:
        path = os.path.join(folder, name + ".jpg")
        if os.path.isfile(path):
            return ImageOps.fit(Image.open(path).convert("RGB"), size, Image.LANCZOS)
    return radial(size, (246, 247, 249), (206, 211, 218))


def solid_box(piece):
    """The box of the solid mask (alpha over 128): where the piece really is. rembg leaves the photo's own shadow
    under a piece as a faint fringe (alpha 10-60), which would put the standing line and the contact shadow well
    below the feet (the dog of F26-000079 floated 40 px, 27 Oct 2026)."""
    box = piece.getchannel("A").point(lambda a: 255 if a > 128 else 0).getbbox()
    return box if box else (0, 0, piece.width, piece.height)


def ground_band(piece, sy1):
    """Where the piece touches the ground: the lowest solid row of every column; the columns whose bottom lies
    within 8 % of the height above the lowest one are the parts that stand on it (in a photo taken at an angle the
    far paw sits higher in the picture than the near one: the dog of F26-000079 floated 50 px over a shadow drawn
    through the lowest point alone). Returns the band's top row and its left and right column, in piece pixels."""
    solid = piece.getchannel("A").point(lambda v: 255 if v > 128 else 0)
    near = {}
    for col in range(piece.width):
        box = solid.crop((col, 0, col + 1, piece.height)).getbbox()
        if box and box[3] >= sy1 - piece.height * 0.08:
            near[col] = box[3]
    if not near:
        return sy1, 0, piece.width
    rows = sorted(near.values())
    cols = sorted(near)
    return rows[int(len(rows) * 0.05)], cols[0], cols[-1]


def place(cut, size, portrait):
    """Where the piece stands: at most this share of the width and height, the solid bottom of its mask at the
    standing line; whatever faint fringe hangs under that bottom is cut away (our shadow takes its place)."""
    w, h = size
    max_w = w * (0.80 if portrait else 0.78)
    max_h = h * (0.46 if portrait else 0.64)
    k = min(max_w / cut.width, max_h / cut.height)
    piece = cut.resize((max(1, round(cut.width * k)), max(1, round(cut.height * k))), Image.LANCZOS)
    sy1 = solid_box(piece)[3]
    piece = piece.crop((0, 0, piece.width, min(piece.height, sy1 + 1)))   # one soft row of the edge stays
    x = (w - piece.width) // 2
    y = round(h * (0.58 if portrait else 0.78)) - sy1
    return piece, x, max(0, y), sy1


def shadow(canvas, piece, x, y, sy1):
    """A soft drop shadow from the piece's own alpha and a darker contact shadow: an ellipse over the ground band
    (its top overlaps the far feet, its bottom lies under the near ones), as wide as the feet and a little more,
    never a fixed offset from the frame."""
    alpha = piece.getchannel("A")
    blur = max(3, round(piece.width * 0.035))
    drop = Image.new("RGBA", canvas.size, (0, 0, 0, 0))
    mask = Image.new("L", canvas.size, 0)
    mask.paste(alpha, (x, y + round(piece.height * 0.02)))
    mask = mask.filter(ImageFilter.GaussianBlur(blur)).point(lambda a: int(a * 0.32))
    drop.paste((20, 24, 30, 255), (0, 0), mask)
    canvas.alpha_composite(drop)
    contact = Image.new("L", canvas.size, 0)
    d = ImageDraw.Draw(contact)
    top, left, right = ground_band(piece, sy1)
    depth = max(0, sy1 - top)
    ry = depth / 2 + max(4, piece.height * 0.025)
    cy = y + top + depth / 2 + 0.2 * ry
    cx = x + (left + right) / 2
    rx = max((right - left) * 0.6, piece.width * 0.3, 8)
    d.ellipse([cx - rx, cy - ry, cx + rx, cy + ry], fill=120)
    contact = contact.filter(ImageFilter.GaussianBlur(max(2, round(piece.width * 0.02))))
    dark = Image.new("RGBA", canvas.size, (0, 0, 0, 0))
    dark.paste((10, 12, 16, 255), (0, 0), contact)
    canvas.alpha_composite(dark)


def main(argv):
    if len(argv) < 2:
        out({"ok": False, "error": "usage"})
    src, dst = argv[0], argv[1]
    bg = arg(argv, "--bg", "studio")
    try:
        w, h = (int(v) for v in arg(argv, "--size", "1200x800").lower().split("x"))
    except ValueError:
        out({"ok": False, "error": "bad_size"})
    cut = arg(argv, "--cut", "1") != "0"
    webp = arg(argv, "--webp")
    folder = arg(argv, "--backgrounds", os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "..", "public", "img", "backgrounds"))
    try:
        im = ImageOps.exif_transpose(Image.open(src))
    except Exception as e:  # noqa: BLE001
        out({"ok": False, "error": "not_a_picture: " + str(e)[:120]})
    size = (w, h)
    if cut:
        piece = im.convert("RGBA")
        bbox = piece.getchannel("A").point(lambda a: 255 if a > 8 else 0).getbbox()
        if not bbox:
            out({"ok": False, "error": "nothing_found"})
        piece = piece.crop(bbox)
        canvas = backdrop(bg, size, folder).convert("RGBA")
        piece, x, y, sy1 = place(piece, size, portrait=h > w)
        shadow(canvas, piece, x, y, sy1)
        canvas.alpha_composite(piece, (x, y))
        result = canvas.convert("RGB")
    else:
        # no cut-out: the photo as it is, filled and cropped to the size (the middle kept)
        result = ImageOps.fit(im.convert("RGB"), size, Image.LANCZOS, centering=(0.5, 0.5))
    result.save(dst, "JPEG", quality=88, optimize=True, progressive=True)
    if webp:
        result.save(webp, "WEBP", quality=82, method=4)
    out({"ok": True, "width": w, "height": h, "cut": cut, "bg": bg})


if __name__ == "__main__":
    main(sys.argv[1:])
