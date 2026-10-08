#!/usr/bin/env python3
"""
The card pictures of the selling and planning tools (session 4), which have no model to render: simple drawings on
the same beige studio backdrop as the rendered cards, 3:2, written as <base>-800.jpg, -800.webp and -480.webp.

  sell_cards.py <public/img/tools> [cost profit plan vendors image listing photo]
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


def image(img):
    # a picture from a description: a black cat silhouette on a white card, a text line (the description) underneath
    card(img, (330, 110, 1270, 870))
    d = ImageDraw.Draw(img)
    cx, cy = 800, 470
    d.ellipse([cx - 190, cy - 110, cx + 190, cy + 230], fill=INK)                       # the body
    d.ellipse([cx - 120, cy - 290, cx + 120, cy - 60], fill=INK)                        # the head
    d.polygon([(cx - 115, cy - 230), (cx - 95, cy - 370), (cx - 20, cy - 270)], fill=INK)   # the ears
    d.polygon([(cx + 115, cy - 230), (cx + 95, cy - 370), (cx + 20, cy - 270)], fill=INK)
    d.line([(cx + 170, cy + 160), (cx + 330, cy + 60), (cx + 300, cy - 120)], fill=INK, width=44, joint="curve")   # the tail
    d.rounded_rectangle((430, 760, 1170, 820), radius=14, fill=(244, 241, 236), outline=(214, 208, 200), width=3)
    d.rounded_rectangle((460, 782, 1000, 798), radius=8, fill=MUTED)                   # the typed description
    d.rounded_rectangle((1090, 776, 1150, 804), radius=14, fill=ACTION)                 # the make button


def listing(img):
    # the texts of a listing: a title line, paragraphs and a row of tag chips
    card(img, (280, 110, 1320, 870))
    d = ImageDraw.Draw(img)
    d.rounded_rectangle((360, 190, 1050, 240), radius=12, fill=INK)                    # the title
    for i, w in enumerate([880, 840, 900, 620]):
        y = 300 + i * 48
        d.rounded_rectangle((360, y, 360 + w, y + 22), radius=11, fill=(205, 200, 192))
    x = 360
    for w, col in [(170, ACTION), (220, GREEN), (150, BLUE), (200, ACTION), (120, GREEN)]:
        d.rounded_rectangle((x, 540, x + w, 600), radius=30, fill=col)
        x += w + 22
    for i, w in enumerate([860, 700]):
        y = 660 + i * 48
        d.rounded_rectangle((360, y, 360 + w, y + 22), radius=11, fill=(205, 200, 192))
    d.ellipse([1150, 170, 1250, 270], fill=WHITE, outline=ACTION, width=6)             # the copy mark
    d.rounded_rectangle((1180, 200, 1222, 242), radius=6, outline=ACTION, width=6)


def photo(img):
    # a product photo on a backdrop: a vase cut out of a cluttered shot, set on a soft gradient with a shadow
    card(img, (160, 110, 760, 870), fill=(226, 221, 214))
    d = ImageDraw.Draw(img)
    for pts in [[(200, 300), (380, 260), (560, 320), (740, 280)], [(200, 700), (420, 660), (640, 720), (740, 690)]]:
        d.line(pts, fill=(198, 192, 184), width=18, joint="curve")                     # the clutter behind
    d.rectangle((560, 560, 720, 640), fill=(186, 180, 172))
    d.rectangle((190, 420, 330, 520), fill=(190, 184, 176))
    card(img, (840, 110, 1440, 870), fill=(246, 244, 240))
    glow = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    gd = ImageDraw.Draw(glow)
    gd.ellipse([930, 700, 1350, 800], fill=(0, 0, 0, 70))
    glow = glow.filter(ImageFilter.GaussianBlur(26))
    img.paste(glow, (0, 0), glow)
    for ox, col in [(460, (120, 118, 116)), (1140, ACTION)]:                           # the vase, grey in the shot, orange on the backdrop
        d = ImageDraw.Draw(img)
        d.polygon([(ox - 110, 720), (ox + 110, 720), (ox + 70, 420), (ox + 130, 300), (ox - 130, 300), (ox - 70, 420)], fill=col)
        d.ellipse([ox - 130, 270, ox + 130, 330], fill=col)
        d.rectangle((ox - 118, 700, ox + 118, 724), fill=col)
    d.polygon([(780, 470), (830, 490), (780, 510)], fill=INK)                           # the arrow between the two
    d.rectangle((740, 480, 790, 500), fill=INK)


DRAW = {"cost": cost, "profit": profit, "plan": plan, "vendors": vendors, "image": image, "listing": listing, "photo": photo}


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
