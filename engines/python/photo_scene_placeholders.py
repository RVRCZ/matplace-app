#!/usr/bin/env python3
"""
Placeholder scenes for photo_compose.py (print photos on a shelf / a coffee table): a plain drawn outline of the
furniture, 2048x2048, with the JSON beside it (marked "placeholder": true). The real photos of empty interiors
replace these files under the same names; see the JSON format in app/Domain/Farm/PrintPhotos.php (SCENE FORMAT).

  photo_scene_placeholders.py <out dir>   writes shelf-demo.jpg/json and table-demo.jpg/json
"""
import json
import os
import sys

from PIL import Image, ImageDraw, ImageFilter

S = 2048


def wall():
    im = Image.new("RGB", (S, S), (226, 219, 208))
    d = ImageDraw.Draw(im)
    for y in range(S):   # a soft light falloff from the left window
        v = int(14 * y / S)
        d.line([(0, y), (S, y)], fill=(226 - v, 219 - v, 208 - v))
    return im


def shelf():
    im = wall()
    d = ImageDraw.Draw(im)
    top = int(S * 0.80)
    d.rectangle([int(S * 0.08), top, int(S * 0.92), top + 70], fill=(150, 108, 70))   # the board
    d.rectangle([int(S * 0.08), top, int(S * 0.92), top + 12], fill=(176, 133, 92))   # its lit top edge
    for bx in (0.18, 0.82):   # two brackets
        x = int(S * bx)
        d.polygon([(x - 20, top + 70), (x + 20, top + 70), (x + 20, top + 330), (x - 20, top + 330)], fill=(90, 90, 96))
    d.rectangle([int(S * 0.62), int(S * 0.12), int(S * 0.9), int(S * 0.42)], outline=(120, 112, 104), width=10)   # a frame on the wall
    return im.filter(ImageFilter.GaussianBlur(1.2))


def table():
    im = wall()
    d = ImageDraw.Draw(im)
    d.rectangle([0, int(S * 0.90), S, S], fill=(158, 150, 140))   # the floor
    top = int(S * 0.78)
    d.rounded_rectangle([int(S * 0.05), top, int(S * 0.95), top + 90], radius=24, fill=(120, 84, 56))   # the top
    d.rectangle([int(S * 0.05), top, int(S * 0.95), top + 14], fill=(152, 112, 78))
    for lx in (0.10, 0.88):
        x = int(S * lx)
        d.rectangle([x, top + 90, x + 46, int(S * 0.93)], fill=(96, 66, 44))
    d.ellipse([int(S * 0.70), int(S * 0.20), int(S * 0.95), int(S * 0.34)], outline=(150, 140, 128), width=8)
    return im.filter(ImageFilter.GaussianBlur(1.2))


SCENES = {
    "shelf-demo": (shelf, {"kind": "shelf", "surface_y": 0.80, "x": 0.45, "piece_h": 0.40, "light": "left"}),
    "table-demo": (table, {"kind": "table", "surface_y": 0.78, "x": 0.50, "piece_h": 0.36, "light": "right"}),
}


def main(out_dir):
    os.makedirs(out_dir, exist_ok=True)
    for name, (draw, meta) in SCENES.items():
        draw().save(os.path.join(out_dir, name + ".jpg"), "JPEG", quality=85)
        with open(os.path.join(out_dir, name + ".json"), "w", encoding="utf-8") as f:
            json.dump({"placeholder": True, **meta}, f, indent=2)
            f.write("\n")


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else "public/img/scenes")
