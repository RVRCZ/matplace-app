"""
Draws the forty cookie blanks of the library (engines/artwork/cookies/cookie-<name>.svg): Christmas and Halloween
shapes for the cookie tool, our own drawings made of circles, ovals and a few corner points, so nothing is owed to
anybody. Every one is a plump silhouette a child's hand can hold: no thin necks, no inner detail (the icing is drawn
on it in the tool). Run it again after a shape is changed:

    python engines/artwork/cookies/_draw.py [directory to write into]
"""
import math
import os
import sys

import manifold3d as M

C = M.CrossSection
J = M.JoinType.Round
HERE = sys.argv[1] if len(sys.argv) > 1 else os.path.dirname(os.path.abspath(__file__))


def circle(x, y, r):
    return C.circle(r, 96).translate([x, y])


def oval(x, y, rx, ry, turn=0.0):
    return C.circle(1.0, 96).scale([rx, ry]).rotate(turn).translate([x, y])


def box(x0, y0, x1, y1):
    return C.square([x1 - x0, y1 - y0]).translate([x0, y0])


def poly(*pts):
    return C([list(pts)], M.FillRule.NonZero)


def link(a, b, w):
    """A bar with round ends from a to b."""
    return (circle(a[0], a[1], w / 2) + circle(b[0], b[1], w / 2)).hull()


def soft(cs, r):
    return cs.offset(-r, J, 2.0, 32).offset(2 * r, J, 2.0, 32).offset(-r, J, 2.0, 32)


def mirror(cs):
    return cs + cs.mirror([1, 0])


def star(n, R, r, turn=90.0):
    return poly(*[((R if i % 2 == 0 else r) * math.cos(math.radians(turn) + i * math.pi / n), (R if i % 2 == 0 else r) * math.sin(math.radians(turn) + i * math.pi / n)) for i in range(2 * n)])


def heart(s=1.0):
    return poly(*[(s * 16 * math.sin(a) ** 3, s * (13 * math.cos(a) - 5 * math.cos(2 * a) - 2 * math.cos(3 * a) - math.cos(4 * a))) for a in (2 * math.pi * i / 120 for i in range(120))])


# ── Christmas ────────────────────────────────────────────────────────────────

def gingerbread_man():
    return soft(circle(0, 62, 26) + link((0, 40), (0, -10), 44) + link((-52, 26), (52, 26), 26) + link((-12, -10), (-26, -70), 28) + link((12, -10), (26, -70), 28), 5)


def santa():
    face = circle(0, 0, 40)
    hat = poly((-42, 20), (42, 20), (58, 86)) + circle(60, 90, 15) + link((-44, 22), (44, 22), 24)
    beard = soft(circle(-26, -30, 26) + circle(26, -30, 26) + circle(0, -50, 30), 4)
    return soft(face + hat + beard, 4)


def santa_hat():
    return soft(poly((-55, -30), (55, -30), (40, 20), (70, 70), (10, 40)) + circle(74, 72, 16) + link((-58, -34), (58, -34), 30), 4)


def reindeer():
    head = oval(0, -20, 34, 44) + oval(0, -52, 22, 18)
    ears = oval(-40, 6, 20, 10, 25) + oval(40, 6, 20, 10, -25)
    antler = link((-16, 14), (-34, 74), 12) + link((-28, 46), (-56, 60), 11) + link((-32, 62), (-12, 84), 10)
    return soft(head + ears + mirror(antler), 3)


def sleigh():
    body = soft(poly((-70, -10), (60, -10), (72, 40), (40, 40), (30, 14), (-30, 14), (-44, 62), (-74, 62)), 8)
    runner = link((-80, -44), (70, -44), 12) + link((70, -44), (88, -24), 12) + link((-40, -12), (-40, -44), 10) + link((34, -12), (34, -44), 10)
    return soft(body + runner, 3)


def gift():
    bow = oval(-22, 52, 24, 14, 20) + oval(22, 52, 24, 14, -20) + circle(0, 44, 10)
    return soft(soft(box(-56, -60, 56, 30), 6) + soft(box(-62, 22, 62, 42), 4) + bow, 3)


def tree():
    return soft(poly((0, 96), (34, 44), (18, 44), (48, -6), (28, -6), (62, -58), (-62, -58), (-28, -6), (-48, -6), (-18, 44), (-34, 44)) + box(-14, -84, 14, -50), 5)


def snowman():
    return soft(circle(0, -44, 42) + circle(0, 12, 32) + circle(0, 56, 24) + box(-20, 72, 20, 100) + link((-30, 74), (30, 74), 10), 4)


def stocking():
    return soft(soft(box(-30, -10, 22, 84), 6) + link((-6, -24), (40, -54), 50) + link((-36, 76), (28, 76), 22), 4)


def candy_cane():
    arc = C()
    for i in range(13):
        a = math.radians(i * 15)
        arc = arc + circle(-18 + 34 * math.cos(a), 44 + 34 * math.sin(a), 15)
    return soft(arc + link((16, 44), (16, -84), 30) + link((-52, 44), (-52, 30), 30), 3)


def bauble():
    return soft(circle(0, -10, 62) + box(-14, 48, 14, 70) + circle(0, 76, 10), 3)


def snowflake():
    arms = C()
    for i in range(6):
        a = math.radians(60 * i)
        tip = (86 * math.cos(a), 86 * math.sin(a))
        arms = arms + link((0, 0), tip, 20)
        for side in (-1, 1):
            b = a + side * math.radians(50)
            mid = (52 * math.cos(a), 52 * math.sin(a))
            arms = arms + link(mid, (mid[0] + 26 * math.cos(b), mid[1] + 26 * math.sin(b)), 15)
    return soft(arms + circle(0, 0, 22), 3)


def bell():
    return soft(poly((-18, 70), (18, 70), (40, 20), (46, -30), (66, -52), (-66, -52), (-46, -30), (-40, 20)) + circle(0, 72, 16) + circle(0, -60, 15), 6)


def five_star():
    return soft(star(5, 92, 46), 7)


def mitten():
    return soft(soft(box(-36, -30, 30, 60), 22) + link((30, 10), (56, 30), 28) + soft(box(-40, -70, 34, -34), 5), 4)


def wreath():
    ring = circle(0, 0, 80) - circle(0, 0, 40)
    bumps = C()
    for i in range(12):
        a = math.radians(30 * i)
        bumps = bumps + circle(74 * math.cos(a), 74 * math.sin(a), 20)
    return soft(ring + bumps - circle(0, 0, 40), 3) + soft(oval(-20, -78, 22, 12, 20) + oval(20, -78, 22, 12, -20), 2)


def elf_hat():
    return soft(poly((-50, -30), (50, -30), (20, 30), (44, 60), (60, 96), (0, 60)) + circle(62, 98, 12) + mirror(poly((0, -30), (60, -30), (72, -52), (52, -44), (40, -60), (26, -44), (12, -60), (0, -44))), 4)


def penguin():
    return soft(oval(0, -10, 50, 68) + circle(0, 56, 34) + oval(-54, -6, 14, 38, 18) + oval(54, -6, 14, 38, -18) + oval(-24, -78, 22, 10) + oval(24, -78, 22, 10), 4)


def holly():
    def leaf(turn):
        pts = []
        for i in range(90):
            a = 2 * math.pi * i / 90
            r = 1 + 0.16 * math.cos(8 * a)
            pts.append((r * 62 * math.cos(a), r * 34 * math.sin(a)))
        return poly(*pts).translate([56, 0]).rotate(turn)
    return soft(leaf(150) + leaf(30) + circle(-16, -12, 20) + circle(16, -12, 20) + circle(0, 12, 20), 4)


def xmas_heart():
    return soft(heart(5.2), 4)


# ── Halloween ────────────────────────────────────────────────────────────────

def pumpkin():
    return soft(oval(-36, -8, 44, 56) + oval(36, -8, 44, 56) + oval(0, -8, 40, 60) + poly((-10, 44), (12, 44), (18, 80), (-2, 76)), 4)


def ghost():
    waves = circle(-44, -62, 20) + circle(0, -62, 22) + circle(44, -62, 20)
    return soft(circle(0, 30, 62) + box(-62, -60, 62, 30) + waves + oval(-70, 0, 20, 12, -30) + oval(70, 0, 20, 12, 30), 4)


def bat():
    wing = poly((0, 20), (40, 46), (96, 30), (84, 2), (66, -14), (52, 0), (38, -18), (22, -2), (0, -24))
    return soft(mirror(wing) + oval(0, 0, 22, 32) + mirror(poly((6, 26), (20, 54), (24, 22))), 4)


def cat():
    body = oval(0, -30, 46, 56)
    head = circle(0, 46, 34) + mirror(poly((8, 66), (34, 96), (38, 56)))
    tail = C()
    for i in range(10):
        a = math.radians(-60 + i * 16)
        tail = tail + circle(52 + 30 * math.cos(a), -52 + 30 * math.sin(a), 10)
    return soft(body + head + tail, 4)


def spider():
    legs = C()
    for k, (dy, reach) in enumerate(((36, 70), (14, 86), (-10, 86), (-34, 70))):
        knee = (52, dy + 26 - 8 * k)
        legs = legs + link((14, dy * 0.4), knee, 13) + link(knee, (reach, dy - 20 - 6 * k), 13)
    return soft(mirror(legs) + oval(0, -14, 36, 44) + circle(0, 38, 24), 3)


def skull():
    return soft(oval(0, 22, 62, 58) + soft(box(-36, -62, 36, -6), 12), 8)


def witch_hat():
    return soft(poly((-22, -20), (22, -20), (4, 60), (30, 92), (-6, 76)) + oval(0, -32, 84, 18), 4)


def cauldron():
    return soft(oval(0, -10, 66, 54) + link((-60, 34), (60, 34), 22) + link((-36, -56), (-44, -74), 16) + link((36, -56), (44, -74), 16) + mirror(oval(70, 20, 12, 18)), 4)


def bone():
    return soft(link((-56, 0), (56, 0), 30) + circle(-68, 18, 22) + circle(-68, -18, 22) + circle(68, 18, 22) + circle(68, -18, 22), 4)


def moon():
    return soft(circle(0, 0, 80) - circle(34, 14, 66), 6)


def coffin():
    return soft(poly((-28, 96), (28, 96), (52, 50), (30, -96), (-30, -96), (-52, 50)), 6)


def eye():
    return soft(circle(0, -52, 96) ^ circle(0, 52, 96), 4)


def hand():
    palm = soft(box(-40, -70, 36, 0), 16)
    fingers = link((-30, -6), (-36, 62), 20) + link((-10, -4), (-12, 82), 20) + link((10, -4), (12, 76), 20) + link((28, -8), (34, 56), 18) + link((34, -40), (68, -8), 22)
    return soft(palm + fingers, 3)


def tooth():
    return soft(soft(box(-46, -6, 46, 62), 22) + oval(-26, -34, 20, 46, 8) + oval(26, -34, 20, 46, -8) + box(-30, -10, 30, 20), 5)


def candy_corn():
    return soft(poly((0, 92), (62, -70), (-62, -70)), 22)


def tombstone():
    return soft(circle(0, 30, 52) + box(-52, -70, 52, 30) + soft(box(-70, -88, 70, -64), 5), 3)


def broom():
    return soft(link((0, 96), (0, -6), 14) + poly((-16, -4), (16, -4), (46, -92), (-46, -92)) + link((-20, -10), (20, -10), 14), 4)


def owl():
    return soft(oval(0, -14, 52, 66) + circle(0, 44, 46) + mirror(poly((14, 78), (44, 100), (44, 60))) + oval(-22, -82, 16, 9) + oval(22, -82, 16, 9), 4)


def mushroom():
    return soft((circle(0, 6, 80) ^ box(-90, 6, 90, 100)) + soft(box(-24, -84, 24, 12), 14), 5)


def potion():
    return soft(circle(0, -30, 56) + box(-18, 10, 18, 70) + soft(box(-26, 62, 26, 84), 4) + box(-14, 80, 14, 98), 4)


SHAPES = {
    "gingerbread-man": gingerbread_man, "santa": santa, "santa-hat": santa_hat, "reindeer": reindeer, "sleigh": sleigh, "gift": gift, "tree": tree,
    "snowman": snowman, "stocking": stocking, "candy-cane": candy_cane, "bauble": bauble, "snowflake": snowflake, "bell": bell, "star": five_star,
    "mitten": mitten, "wreath": wreath, "elf-hat": elf_hat, "penguin": penguin, "holly": holly, "heart": xmas_heart,
    "pumpkin": pumpkin, "ghost": ghost, "bat": bat, "cat": cat, "spider": spider, "skull": skull, "witch-hat": witch_hat, "cauldron": cauldron,
    "bone": bone, "moon": moon, "coffin": coffin, "eye": eye, "hand": hand, "tooth": tooth, "candy-corn": candy_corn, "tombstone": tombstone,
    "broom": broom, "owl": owl, "mushroom": mushroom, "potion": potion,
}

if __name__ == "__main__":
    os.makedirs(HERE, exist_ok=True)
    for name, draw in SHAPES.items():
        cs = draw().simplify(0.2)
        pieces = cs.decompose()
        if len(pieces) != 1:
            print("NOT ONE PIECE:", name, len(pieces))
        x0, y0, x1, y1 = cs.bounds()
        k = 1000.0 / max(x1 - x0, y1 - y0)
        d = "".join("M" + "L".join("%s,%s" % (round((float(x) - x0) * k, 1), round((y1 - float(y)) * k, 1)) for x, y in ring) + "Z" for ring in cs.to_polygons())
        with open(os.path.join(HERE, "cookie-" + name + ".svg"), "w", encoding="utf-8", newline="\n") as f:
            f.write('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %s %s">\n<path fill="#111" d="%s"/>\n</svg>\n' % (round((x1 - x0) * k, 1), round((y1 - y0) * k, 1), d))
    print(len(SHAPES), "blanks")
