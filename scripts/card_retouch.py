#!/usr/bin/env python3
"""
Paints over a small mark in a reviewed card picture (a made-up logo on the printer far in the background), where the
picture is otherwise right and another attempt would only risk the shape. Works on the three files of a card in
storage/app/card_images/ before they are published.

  python scripts/card_retouch.py <key> <x0> <y0> <x1> <y1>      the box in the pixels of <key>-800.jpg

The box is filled from the colours around it and its edge is feathered; the background there is out of focus anyway.
"""
import os
import sys

from PIL import Image, ImageDraw, ImageFilter

WORK = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "storage", "app", "card_images")


def main(argv):
    key = argv[1]
    x0, y0, x1, y1 = (int(v) for v in argv[2:6])
    path = os.path.join(WORK, key + "-800.jpg")
    im = Image.open(path).convert("RGB")
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
    im.save(path, quality=90, optimize=True, progressive=True)
    im.save(os.path.join(WORK, key + "-800.webp"), quality=82, method=6)
    im.resize((480, 320), Image.LANCZOS).save(os.path.join(WORK, key + "-480.webp"), quality=80, method=6)
    print("retouched", key, (x0, y0, x1, y1))


if __name__ == "__main__":
    main(sys.argv)
