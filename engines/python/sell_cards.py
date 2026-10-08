#!/usr/bin/env python3
"""
The card pictures of the selling and planning tools (session 4), which have no model to render: simple drawings on
the same beige studio backdrop as the rendered cards, 3:2, written as <base>-800.jpg, -800.webp and -480.webp.

  sell_cards.py <public/img/tools> [cost profit plan vendors]
"""
import math
import os
import sys

from PIL import Image, ImageDraw, ImageFilter

W, H = 1600, 1067                 # drawn at twice the size and scaled down: smooth edges
BG = (236, 231, 224)
BG_EDGE = (222, 216, 208)
INK = (24, 30, 44)
MUTED = (120, 126, 138)
ACTION = (233, 110, 44)
GREEN = (84, 150, 112)
BLUE = (92, 128, 184)
WHITE = (250, 249, 247)
SHADOW = (0, 0, 0, 60)


def backdrop():
    img = Image.new("RGB", (W, H), BG)
    # a touch darker towards the corners, like the studio
    mask = Image.new("L", (W, H), 0)
    d = ImageDraw.Draw(mask)
    d.ellipse([-W * 0.2, -H * 0.4, W * 1.2, H * 1.3], fill=255)
    mask = mask.filter(ImageFilter.GaussianBlur(220))
    edge = Image.new("RGB", (W, H), BG_EDGE)
    return Image.composite(img, edge, mask)


def shadow(img, box, radius=40, blur=30, offset=(0, 26)):
    layer = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    d = ImageDraw.Draw(layer)
    x0, y0, x1, y1 = box
    d.rounded_rectangle([x0 + offset[0], y0 + offset[1], x1 + offset[0], y1 + offset[1]], radius=radius, fill=SHADOW)
    layer = layer.filter(ImageFilter.GaussianBlur(blur))
    img.paste(layer, (0, 0), layer)


def card(img, box, radius=40, fill=WHITE):
    shadow(img, box, radius)
    ImageDraw.Draw(img).rounded_rectangle(box, radius=radius, fill=fill, outline=(214, 208, 200), width=3)


def bars(img, x0, y0, w, h, values, colors, gap=0.35):
    d = ImageDraw.Draw(img)
    n = len(values)
    slot = w / n
    bw = slot * (1 - gap)
    top = max(values) or 1
    for i, v in enumerate(values):
        bh = h * v / top
        x = x0 + i * slot + (slot - bw) / 2
        d.rounded_rectangle([x, y0 + h - bh, x + bw, y0 + h], radius=18, fill=colors[i % len(colors)])


def cost(img):
    # a receipt: lines for the costs, the price as the fat last line
    card(img, (360, 130, 1240, 940))
    d = ImageDraw.Draw(img)
    rows = [(0.42, MUTED), (0.18, MUTED), (0.12, MUTED), (0.08, MUTED), (0.5, MUTED), (0.05, MUTED)]
    y = 230
    for share, col in rows:
        d.rounded_rectangle([430, y, 430 + 260, y + 34], radius=17, fill=(205, 200, 192))
        d.rounded_rectangle([1170 - 540 * share, y, 1170, y + 34], radius=17, fill=col)
        y += 86
    d.line([(430, y + 10), (1170, y + 10)], fill=(214, 208, 200), width=4)
    d.rounded_rectangle([430, y + 50, 430 + 300, y + 102], radius=26, fill=INK)
    d.rounded_rectangle([1170 - 430, y + 50, 1170, y + 102], radius=26, fill=ACTION)
    # a spool at the corner
    d.ellipse([200, 700, 440, 940], fill=(80, 86, 98))
    d.ellipse([250, 750, 390, 890], fill=BG)
    d.ellipse([300, 800, 340, 840], fill=(80, 86, 98))


def profit(img):
    # three prices side by side: what the platform keeps (orange) at the bottom, the cost (grey), the profit (green) on top
    card(img, (300, 130, 1300, 940))
    d = ImageDraw.Draw(img)
    cols = [(0.78, 0.22, 0.56), (0.95, 0.20, 0.46), (1.0, 0.18, 0.40)]       # (height, fee share, cost share)
    x = 420
    for total, fee, cost in cols:
        hh = 600 * total
        y1 = 850
        y0 = y1 - hh
        d.rounded_rectangle([x, y0, x + 200, y1], radius=28, fill=GREEN)
        d.rectangle([x, y1 - hh * (fee + cost), x + 200, y1 - hh * fee], fill=(205, 200, 192))
        d.rounded_rectangle([x, y1 - hh * fee, x + 200, y1], radius=28, fill=ACTION)
        d.rectangle([x, y1 - hh * fee, x + 200, y1 - hh * fee + 28], fill=ACTION)
        x += 290
    # a price tag
    d.polygon([(1060, 180), (1240, 180), (1240, 360), (1150, 440), (1060, 360)], fill=INK)
    d.ellipse([1130, 220, 1170, 260], fill=WHITE)


def plan(img):
    # twelve months of revenue and a line of the cumulative profit crossing zero
    card(img, (200, 130, 1400, 940))
    d = ImageDraw.Draw(img)
    season = [0.6, 0.6, 0.8, 0.9, 1.0, 0.8, 0.7, 0.8, 1.0, 1.4, 2.0, 2.4]
    bars(img, 280, 300, 1040, 520, season, [(205, 200, 192)])
    d.line([(280, 640), (1320, 640)], fill=(180, 174, 166), width=4)
    cum, pts = -1.6, []
    for i, s in enumerate(season):
        cum += s * 0.5 - 0.3
        pts.append((280 + 1040 * (i + 0.5) / 12, 640 - cum * 110))
    d.line(pts, fill=GREEN, width=14, joint="curve")
    for x, y in pts:
        d.ellipse([x - 16, y - 16, x + 16, y + 16], fill=WHITE, outline=GREEN, width=8)
    d.ellipse([pts[-1][0] - 26, pts[-1][1] - 26, pts[-1][0] + 26, pts[-1][1] + 26], fill=ACTION)
    # a checklist at the top left
    for i in range(3):
        y = 190 + i * 30
        d.rounded_rectangle([290, y, 316, y + 22], radius=6, outline=INK, width=3, fill=GREEN if i < 2 else WHITE)
        d.rounded_rectangle([330, y + 4, 330 + 160 - i * 30, y + 18], radius=7, fill=MUTED)


def vendors(img):
    # a map with roads, a radius and pins
    card(img, (200, 130, 1400, 940), fill=(244, 241, 236))
    d = ImageDraw.Draw(img)
    for pts in [[(200, 520), (600, 480), (900, 560), (1400, 500)], [(700, 130), (760, 500), (720, 940)], [(200, 760), (1000, 700), (1400, 820)], [(1000, 130), (1100, 940)]]:
        d.line(pts, fill=(226, 221, 214), width=22, joint="curve")
        d.line(pts, fill=WHITE, width=10, joint="curve")
    cx, cy = 780, 540
    ring = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    rd = ImageDraw.Draw(ring)
    rd.ellipse([cx - 330, cy - 330, cx + 330, cy + 330], fill=(92, 128, 184, 40), outline=(92, 128, 184, 170), width=6)
    img.paste(ring, (0, 0), ring)
    for x, y, col in [(cx, cy, INK), (560, 400, ACTION), (980, 430, ACTION), (900, 760, ACTION), (430, 700, GREEN), (1200, 300, GREEN), (1250, 800, (205, 200, 192))]:
        r = 44 if col is INK else 38
        d.polygon([(x, y + r * 1.6), (x - r * 0.95, y + r * 0.3), (x + r * 0.95, y + r * 0.3)], fill=col)
        d.ellipse([x - r, y - r, x + r, y + r], fill=col)
        d.ellipse([x - r * 0.42, y - r * 0.42, x + r * 0.42, y + r * 0.42], fill=WHITE)


DRAW = {"cost": cost, "profit": profit, "plan": plan, "vendors": vendors}


def main(argv):
    out = argv[1]
    which = argv[2:] or list(DRAW)
    for key in which:
        img = backdrop()
        DRAW[key](img)
        small = img.resize((800, 533), Image.LANCZOS)
        base = os.path.join(out, key)
        small.save(base + "-800.jpg", "JPEG", quality=86, optimize=True)
        small.save(base + "-800.webp", "WEBP", quality=82, method=6)
        small.resize((480, 320), Image.LANCZOS).save(base + "-480.webp", "WEBP", quality=80, method=6)
        print(key, os.path.getsize(base + "-800.jpg") // 1024, "kB jpg", os.path.getsize(base + "-800.webp") // 1024, "kB webp")


if __name__ == "__main__":
    main(sys.argv)
