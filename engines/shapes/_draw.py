"""
Draws the plate shapes of the sign tool (engines/shapes/<name>.svg): our own drawings, made of circles, boxes and a few
corner points, so nothing is owed to anybody. Run it again after a shape is changed:

    python engines/shapes/_draw.py

A shape is one filled outline without holes, drawn with room for a text in its middle. The tool scales it so that the
text fits (shape2d.room finds the widest box of the text's proportions inside it), so the size here does not matter.
Another shape = another function below and its name in ParametricGenerator::CHOICES['sign']['shape'] and in the texts
(param.o.sign.<name>); an SVG drawn elsewhere works as well, as long as it is one filled shape.
"""
import math
import os

import manifold3d as M

C = M.CrossSection
J = M.JoinType.Round
HERE = os.path.dirname(os.path.abspath(__file__))


def circle(x, y, r):
    return C.circle(r, 128).translate([x, y])


def ellipse(x, y, rx, ry, turn=0.0):
    return C.circle(1.0, 128).scale([rx, ry]).rotate(turn).translate([x, y])


def box(x0, y0, x1, y1):
    return C.square([x1 - x0, y1 - y0]).translate([x0, y0])


def poly(*pts):
    return C([list(pts)], M.FillRule.NonZero)             # whichever way round the points go


def soft(cs, r):
    """Corners rounded by r: the ones that stick out and the ones that go in."""
    return cs.offset(-r, J, 2.0, 32).offset(2 * r, J, 2.0, 32).offset(-r, J, 2.0, 32)


def heart():
    pts = []
    for i in range(200):
        a = 2 * math.pi * i / 200
        pts.append((5 * 16 * math.sin(a) ** 3, 5 * (13 * math.cos(a) - 5 * math.cos(2 * a) - 2 * math.cos(3 * a) - math.cos(4 * a))))
    return soft(poly(*pts), 3)


def star():
    pts = []
    for i in range(10):
        a = math.pi / 2 + i * math.pi / 5
        r = 100 if i % 2 == 0 else 58
        pts.append((r * math.cos(a), r * math.sin(a)))
    return soft(poly(*pts), 6)


def cloud():
    return soft(circle(-62, -8, 36) + circle(-22, 22, 46) + circle(34, 14, 42) + circle(74, -10, 32) + box(-62, -44, 74, 0), 5)


def bone():
    return soft(box(-72, -22, 72, 22) + circle(-80, 22, 27) + circle(-80, -22, 27) + circle(80, 22, 27) + circle(80, -22, 27), 4)


def hexagon():
    return soft(poly((-105, 0), (-72, 52), (72, 52), (105, 0), (72, -52), (-72, -52)), 5)


def banner():
    return soft(poly((-115, -30), (115, -30), (95, 0), (115, 30), (-115, 30), (-95, 0)), 3)


def arrow():
    return soft(poly((-105, -30), (35, -30), (35, -58), (108, 0), (35, 58), (35, 30), (-105, 30)), 4)


def house():
    return soft(poly((-82, -60), (82, -60), (82, 8), (0, 72), (-82, 8)) + box(42, 20, 60, 62), 4)


def car():
    return soft(soft(box(-105, -22, 105, 22), 12) + poly((-62, 15), (-40, 58), (38, 58), (68, 15)) + circle(-60, -24, 21) + circle(60, -24, 21), 4)


def cat():
    return soft(ellipse(0, 0, 82, 62) + poly((-76, 22), (-66, 96), (-16, 54)) + poly((76, 22), (66, 96), (16, 54)), 5)


def candy():
    return soft(ellipse(0, 0, 72, 62) + ellipse(-80, 2, 25, 54, -14) + ellipse(80, 2, 25, 54, 14), 5)


def flower():
    petals = C()
    for i in range(8):
        a = 2 * math.pi * i / 8
        petals = petals + circle(64 * math.cos(a), 64 * math.sin(a), 36)
    return soft(petals + circle(0, 0, 70), 4)


def shield():
    pts = [(-82, 62), (82, 62), (82, 0)]
    for i in range(1, 60):
        a = math.pi * i / 60
        pts.append((82 * math.cos(a), -88 * math.sin(a) ** 0.85))
    pts.append((-82, 0))
    return soft(poly(*pts), 5)


def tag():
    return soft(poly((-108, -16), (-76, -46), (104, -46), (104, 46), (-76, 46), (-108, 16)), 5)


def bubble():
    return soft(soft(box(-102, -34, 102, 58), 22) + poly((-58, -30), (-78, -84), (-12, -30)), 3)


def disc():
    return circle(0, 0, 80)


def fish():
    return soft(ellipse(-14, 0, 88, 50) + poly((58, 0), (112, 48), (100, 0), (112, -48)), 5)


SHAPES = {"heart": heart, "star": star, "cloud": cloud, "bone": bone, "hexagon": hexagon, "banner": banner, "arrow": arrow, "house": house, "car": car,
          "cat": cat, "candy": candy, "flower": flower, "shield": shield, "tag": tag, "bubble": bubble, "circle": disc, "fish": fish}

# the three plates the tool makes itself: drawn here only for their tiles in the shape picker
PLAIN = {"rounded": lambda: soft(box(-100, -45, 100, 45), 14), "rect": lambda: box(-100, -45, 100, 45), "oval": lambda: ellipse(0, 0, 105, 55)}
PUBLIC = os.path.join(HERE, "..", "..", "public", "img", "shapes")

if __name__ == "__main__":
    os.makedirs(PUBLIC, exist_ok=True)
    for name, draw in PLAIN.items():
        cs = draw().simplify(0.05)
        x0, y0, x1, y1 = cs.bounds()
        d = "M" + "L".join("%s %s" % (round(float(x) - x0, 1), round(y1 - float(y), 1)) for x, y in cs.to_polygons()[0]) + "Z"
        with open(os.path.join(PUBLIC, name + ".svg"), "w", encoding="utf-8", newline="\n") as f:
            f.write('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %s %s"><path d="%s"/></svg>\n' % (round(x1 - x0, 1), round(y1 - y0, 1), d))
    for name, draw in SHAPES.items():
        cs = draw().simplify(0.05)
        x0, y0, x1, y1 = cs.bounds()
        rings = cs.to_polygons()
        assert len(rings) == 1, (name, len(rings))
        d = "M" + "L".join("%s %s" % (round(float(x) - x0, 2), round(y1 - float(y), 2)) for x, y in rings[0]) + "Z"
        w, h = round(x1 - x0, 2), round(y1 - y0, 2)
        with open(os.path.join(HERE, name + ".svg"), "w", encoding="utf-8", newline="\n") as f:
            f.write('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %s %s"><path d="%s"/></svg>\n' % (w, h, d))
        with open(os.path.join(PUBLIC, name + ".svg"), "w", encoding="utf-8", newline="\n") as f:
            f.write('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %s %s"><path d="%s"/></svg>\n' % (w, h, d))
        print("%-8s %6.1f × %5.1f  %4d points  %d kB" % (name, w, h, len(rings[0]), (len(d) + 1023) // 1024))
