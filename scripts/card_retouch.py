#!/usr/bin/env python3
"""
Small repairs of a reviewed card picture in storage/app/card_images/ before it is published, where the picture is
otherwise right and another attempt would only risk the shape.

  python scripts/card_retouch.py <key> <x0> <y0> <x1> <y1>      paint over a mark: a made-up logo on the printer far behind
  python scripts/card_retouch.py crop <key> <x0> <y0> <x1>      a closer view: the object came out too small

All numbers are pixels of <key>-800.jpg. A painted box is filled from the colours left and right of it and its edge
is feathered (the background there is out of focus anyway). A crop keeps 3:2, its height follows from its width; it is
cut out of <key>-raw.png (the picture as it came) when that is there, so it stays sharp.
"""
import os
import sys

from PIL import Image, ImageDraw, ImageFilter

WORK = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "storage", "app", "card_images")


def write(im, key):
    """The three files of a card from one 3:2 picture; what was there before stays as <key>-undo.jpg (one step back)."""
    import shutil
    shutil.copyfile(os.path.join(WORK, key + "-800.jpg"), os.path.join(WORK, key + "-undo.jpg"))
    big = im if im.size == (800, 533) else im.resize((800, 533), Image.LANCZOS)
    big.save(os.path.join(WORK, key + "-800.jpg"), quality=90, optimize=True, progressive=True)
    big.save(os.path.join(WORK, key + "-800.webp"), quality=82, method=6)
    im.resize((480, 320), Image.LANCZOS).save(os.path.join(WORK, key + "-480.webp"), quality=80, method=6)


def paint(key, x0, y0, x1, y1):
    im = Image.open(os.path.join(WORK, key + "-800.jpg")).convert("RGB")
    pad = 14
    area = (max(0, x0 - pad), max(0, y0 - pad), min(im.width, x1 + pad), min(im.height, y1 + pad))
    patch = im.crop(area)
    box = (x0 - area[0], y0 - area[1], x1 - area[0], y1 - area[1])
    # every row of the box takes the colours left and right of it, blended across: a shade that runs on behind the mark
    px = patch.load()
    for y in range(box[1], box[3]):
        left = px[max(0, box[0] - 3), y]
        right = px[min(patch.width - 1, box[2] + 2), y]
        for x in range(box[0], box[2]):
            t = (x - box[0]) / max(1, box[2] - box[0] - 1)
            px[x, y] = tuple(round(left[c] * (1 - t) + right[c] * t) for c in range(3))
    soft = patch.filter(ImageFilter.GaussianBlur(3))
    mask = Image.new("L", patch.size, 0)
    ImageDraw.Draw(mask).rectangle((box[0] - 4, box[1] - 4, box[2] + 4, box[3] + 4), fill=255)
    patch.paste(soft, (0, 0), mask.filter(ImageFilter.GaussianBlur(4)))
    im.paste(patch, area[:2])
    write(im, key)
    print("painted over", key, (x0, y0, x1, y1))


def crop(key, x0, y0, x1):
    x0, x1 = max(0, x0), min(800, x1)
    high = round((x1 - x0) / 1.5)
    y0 = max(0, min(y0, 533 - high))            # the box never leaves the picture: it slides up instead
    y1 = y0 + high
    raw = os.path.join(WORK, key + "-raw.png")
    if os.path.isfile(raw):
        # the raw picture is cut to 3:2 from its middle the way the card was, then the box is scaled to its size
        im = Image.open(raw).convert("RGB")
        w, h = im.size
        if w / h > 1.5:
            nw = int(h * 1.5)
            im = im.crop(((w - nw) // 2, 0, (w - nw) // 2 + nw, h))
        else:
            nh = int(w / 1.5)
            im = im.crop((0, (h - nh) // 2, w, (h - nh) // 2 + nh))
        k = im.width / 800
        im = im.crop((round(x0 * k), round(y0 * k), round(x1 * k), round(y1 * k)))
    else:
        im = Image.open(os.path.join(WORK, key + "-800.jpg")).convert("RGB").crop((x0, y0, x1, y1))
    write(im.resize((1200, 800), Image.LANCZOS) if im.width < 1200 else im.resize((im.width, round(im.width / 1.5)), Image.LANCZOS), key)
    print("cropped", key, (x0, y0, x1, y1), "from the raw picture" if os.path.isfile(raw) else "from the 800 px card")


if __name__ == "__main__":
    if sys.argv[1] == "crop":
        crop(sys.argv[2], *(int(v) for v in sys.argv[3:6]))
    else:
        paint(sys.argv[1], *(int(v) for v in sys.argv[2:6]))
