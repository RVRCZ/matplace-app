"""
Draws the coloured pictures of the library (engines/artwork/colour/*.svg): our own work from plain geometry, CC0.
They are what the picture-in-colours tools open with, so each one is a handful of flat colours that have a filament:
white, black, red, green, brown, yellow, pink, orange.

    python engines/artwork/colour/_draw.py        writes the SVG files next to this script
"""
import math
import os

WHITE, BLACK, RED, GREEN, BROWN, YELLOW, PINK, ORANGE, DARK, CREAM = "#f4f4f2", "#1b1b1d", "#d1232a", "#2f9e44", "#b0703c", "#f2c230", "#f49ac1", "#f28c28", "#2b2540", "#f4e3c1"


def circle(cx, cy, r, fill):
    return '<circle cx="%g" cy="%g" r="%g" fill="%s"/>' % (cx, cy, r, fill)


def ellipse(cx, cy, rx, ry, fill, turn=0):
    t = ' transform="rotate(%g %g %g)"' % (turn, cx, cy) if turn else ""
    return '<ellipse cx="%g" cy="%g" rx="%g" ry="%g" fill="%s"%s/>' % (cx, cy, rx, ry, fill, t)


def rect(x, y, w, h, fill, r=0, turn=0):
    t = ' transform="rotate(%g %g %g)"' % (turn, x + w / 2, y + h / 2) if turn else ""
    return '<rect x="%g" y="%g" width="%g" height="%g" rx="%g" fill="%s"%s/>' % (x, y, w, h, r, fill, t)


def poly(points, fill):
    return '<polygon points="%s" fill="%s"/>' % (" ".join("%g,%g" % (round(x, 1), round(y, 1)) for x, y in points), fill)


def star(cx, cy, R, r, fill, points=5):
    pts = []
    for i in range(points * 2):
        a = -math.pi / 2 + i * math.pi / points
        rad = R if i % 2 == 0 else r
        pts.append((cx + rad * math.cos(a), cy + rad * math.sin(a)))
    return poly(pts, fill)


def heart(cx, cy, size, fill):
    pts = []
    for i in range(120):
        t = 2 * math.pi * i / 120
        x = 16 * math.sin(t) ** 3
        y = 13 * math.cos(t) - 5 * math.cos(2 * t) - 2 * math.cos(3 * t) - math.cos(4 * t)
        pts.append((cx + x * size / 32, cy - y * size / 32))
    return poly(pts, fill)


def smile(cx, cy, r, width, fill, a0=20, a1=160):
    """A curved line as a filled band: outlines only would not print."""
    outer = [(cx + r * math.cos(math.radians(a)), cy + r * math.sin(math.radians(a))) for a in range(a0, a1 + 1, 5)]
    inner = [(cx + (r - width) * math.cos(math.radians(a)), cy + (r - width) * math.sin(math.radians(a))) for a in range(a1, a0 - 1, -5)]
    return poly(outer + inner, fill)


def wave(x0, x1, y, amp, width, fill, humps=3):
    top, bottom = [], []
    for i in range(61):
        x = x0 + (x1 - x0) * i / 60
        dy = amp * math.sin(math.pi * humps * i / 60 * 2)
        top.append((x, y + dy - width / 2))
        bottom.append((x, y + dy + width / 2))
    return poly(top + bottom[::-1], fill)


def arc(cx, cy, rx, ry, a0, a1, width, fill, steps=24):
    """A band along a piece of an ellipse (degrees, 0 = right, 90 = down): a brow, a lash line, a strand of hair."""
    at = lambda r_x, r_y, a: (cx + r_x * math.cos(math.radians(a)), cy + r_y * math.sin(math.radians(a)))      # noqa: E731
    outer = [at(rx, ry, a0 + (a1 - a0) * i / steps) for i in range(steps + 1)]
    inner = [at(rx - width, ry - width, a1 - (a1 - a0) * i / steps) for i in range(steps + 1)]
    return poly(outer + inner, fill)


def blob(points, fill, steps=10):
    """A closed smooth outline through the given points (Catmull-Rom): a head of hair, a face, shoulders."""
    out, n = [], len(points)
    for i in range(n):
        p0, p1, p2, p3 = points[(i - 1) % n], points[i], points[(i + 1) % n], points[(i + 2) % n]
        for s in range(steps):
            t = s / steps
            out.append(tuple(0.5 * (2 * p1[k] + (-p0[k] + p2[k]) * t + (2 * p0[k] - 5 * p1[k] + 4 * p2[k] - p3[k]) * t * t + (-p0[k] + 3 * p1[k] - 3 * p2[k] + p3[k]) * t ** 3) for k in (0, 1)))
    return poly(out, fill)


PICTURES = {
    "happy-ghost": [
        circle(500, 400, 300, WHITE), rect(200, 400, 600, 380, WHITE),
        circle(300, 780, 100, WHITE), circle(500, 780, 100, WHITE), circle(700, 780, 100, WHITE),
        ellipse(160, 520, 130, 70, WHITE, -25), ellipse(840, 520, 130, 70, WHITE, 25),
        ellipse(410, 380, 45, 70, DARK), ellipse(590, 380, 45, 70, DARK), ellipse(500, 540, 50, 65, DARK),
        ellipse(310, 490, 55, 32, PINK), ellipse(690, 490, 55, 32, PINK),
    ],
    "red-heart": [heart(500, 470, 900, RED), ellipse(300, 290, 95, 55, WHITE, -40)],
    "gingerbread-man": [
        circle(500, 230, 190, BROWN), rect(330, 360, 340, 380, BROWN, 90),
        rect(110, 400, 780, 130, BROWN, 65), rect(330, 620, 140, 330, BROWN, 70, 12), rect(530, 620, 140, 330, BROWN, 70, -12),
        circle(430, 200, 28, WHITE), circle(570, 200, 28, WHITE), smile(500, 230, 105, 26, WHITE, 25, 155),
        circle(500, 460, 34, RED), circle(500, 580, 34, RED),
        wave(140, 260, 465, 18, 26, WHITE, 1), wave(740, 860, 465, 18, 26, WHITE, 1),
        wave(330, 450, 850, 16, 26, WHITE, 1), wave(550, 670, 850, 16, 26, WHITE, 1),
    ],
    "snowman": [
        circle(500, 720, 250, WHITE), circle(500, 370, 180, WHITE),
        rect(330, 190, 340, 50, BLACK, 10), rect(390, 30, 220, 180, BLACK, 16),
        rect(335, 500, 330, 70, RED, 30), rect(560, 520, 80, 210, RED, 24, -12),
        circle(440, 340, 24, BLACK), circle(560, 340, 24, BLACK),
        poly([(500, 370), (500, 420), (660, 400)], ORANGE),
        circle(500, 660, 26, BLACK), circle(500, 760, 26, BLACK), circle(500, 860, 26, BLACK),
    ],
    "paw-badge": [
        circle(500, 500, 480, CREAM),
        ellipse(500, 640, 190, 160, BROWN), ellipse(270, 430, 80, 105, BROWN, -20), ellipse(420, 290, 80, 110, BROWN, -6),
        ellipse(580, 290, 80, 110, BROWN, 6), ellipse(730, 430, 80, 105, BROWN, 20),
        ellipse(500, 660, 110, 85, PINK),
    ],
    "christmas-ball": [
        rect(440, 40, 120, 110, YELLOW, 20), circle(500, 560, 400, RED),
        poly([(118, 470), (882, 470), (896, 560), (882, 650), (118, 650), (104, 560)], WHITE),
        star(300, 560, 62, 26, RED, 6), star(500, 560, 62, 26, RED, 6), star(700, 560, 62, 26, RED, 6),
        circle(360, 300, 34, WHITE), circle(640, 300, 34, WHITE), circle(500, 830, 34, WHITE),
    ],
    "christmas-tree-colour": [
        rect(430, 820, 140, 150, BROWN, 12),
        poly([(500, 110), (760, 420), (240, 420)], GREEN), poly([(500, 290), (840, 640), (160, 640)], GREEN), poly([(500, 480), (920, 860), (80, 860)], GREEN),
        star(500, 110, 100, 42, YELLOW),
        circle(420, 370, 36, RED), circle(600, 560, 36, RED), circle(330, 600, 36, RED), circle(690, 790, 36, RED), circle(300, 800, 36, RED), circle(500, 730, 36, YELLOW),
    ],
    "smiling-star": [
        star(500, 520, 480, 250, YELLOW),
        circle(420, 470, 34, DARK), circle(580, 470, 34, DARK), smile(500, 500, 110, 28, DARK, 30, 150),
        ellipse(345, 560, 48, 30, PINK), ellipse(655, 560, 48, 30, PINK),
    ],
    # a face nobody owns: what the portrait mode of papel picado is shown with (docs/T.md)
    "portrait-woman": [
        # the hair behind the head, down to the shoulders
        blob([(500, 40), (700, 90), (810, 260), (830, 500), (850, 700), (800, 860), (640, 900), (500, 880), (360, 900), (200, 860), (150, 700), (170, 500), (190, 260), (300, 90)], DARK),
        # shoulders in a blouse, the neck, the collar, a string of beads
        blob([(500, 800), (700, 830), (900, 900), (960, 1000), (500, 1000), (40, 1000), (100, 900), (300, 830)], YELLOW, 6),
        poly([(425, 640), (575, 640), (590, 840), (500, 900), (410, 840)], CREAM),
        poly([(410, 820), (500, 910), (590, 820), (650, 850), (500, 1000), (350, 850)], WHITE),
        circle(432, 842, 15, RED), circle(462, 870, 15, RED), circle(500, 884, 15, RED), circle(538, 870, 15, RED), circle(568, 842, 15, RED),
        circle(200, 930, 16, RED), circle(300, 960, 16, RED), circle(120, 975, 16, RED), circle(800, 930, 16, RED), circle(700, 960, 16, RED), circle(880, 975, 16, RED),
        # the face
        blob([(500, 150), (640, 190), (700, 330), (705, 470), (660, 610), (580, 710), (500, 740), (420, 710), (340, 610), (295, 470), (300, 330), (360, 190)], CREAM),
        # the hair in front: a parting on the left, a sweep across the forehead
        blob([(300, 420), (290, 260), (360, 130), (500, 90), (640, 120), (720, 250), (715, 430), (680, 330), (600, 240), (470, 200), (380, 250), (330, 330)], DARK),
        # strands the light catches
        arc(520, 330, 190, 170, 200, 262, 14, ORANGE), arc(560, 400, 250, 250, 215, 262, 12, ORANGE), arc(600, 420, 300, 300, 222, 255, 10, ORANGE),
        arc(500, 520, 330, 360, 150, 178, 12, ORANGE), arc(500, 520, 330, 360, 2, 30, 12, ORANGE), arc(500, 540, 300, 330, 152, 172, 10, ORANGE), arc(500, 540, 300, 330, 8, 28, 10, ORANGE),
        # brows, eyes with their lashes
        arc(405, 452, 84, 62, 222, 318, 15, DARK), arc(595, 452, 84, 62, 222, 318, 15, DARK),
        ellipse(405, 468, 52, 28, WHITE), ellipse(595, 468, 52, 28, WHITE),
        arc(405, 486, 60, 46, 198, 342, 12, DARK), arc(595, 486, 60, 46, 198, 342, 12, DARK),
        circle(405, 468, 25, DARK), circle(595, 468, 25, DARK), circle(414, 460, 8, WHITE), circle(604, 460, 8, WHITE),
        # the nose, the mouth, earrings
        arc(500, 528, 40, 44, 35, 145, 11, BROWN), poly([(490, 452), (502, 448), (494, 548), (480, 556)], BROWN),
        blob([(424, 632), (470, 618), (500, 626), (530, 618), (576, 632), (540, 668), (500, 678), (460, 668)], RED, 6),
        circle(292, 566, 24, YELLOW), circle(708, 566, 24, YELLOW),
    ],
}

if __name__ == "__main__":
    here = os.path.dirname(os.path.abspath(__file__))
    for name, shapes in PICTURES.items():
        with open(os.path.join(here, name + ".svg"), "w", encoding="utf-8", newline="\n") as fh:
            fh.write('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000">\n' + "\n".join(shapes) + "\n</svg>\n")
        print(name)
