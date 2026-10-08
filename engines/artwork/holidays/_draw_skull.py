"""
Draws holidays/sugar-skull.svg: a sugar skull (calavera) for the Day of the Dead, our own drawing made of circles and
boxes, so nothing is owed to anybody. One dark path, the eyes, the nose, the teeth and the flowers cut out of it as
holes, as every silhouette of the library. Run it again after the drawing is changed:

    python engines/artwork/holidays/_draw_skull.py
"""
import math
import os

import manifold3d as M

C = M.CrossSection
J = M.JoinType.Round
HERE = os.path.dirname(os.path.abspath(__file__))


def circle(x, y, r):
    return C.circle(r, 96).translate([x, y])


def box(x0, y0, x1, y1, r=0.0):
    cs = C.square([x1 - x0, y1 - y0]).translate([x0, y0])
    return cs.offset(-r, J, 2.0, 24).offset(r, J, 2.0, 24) if r else cs


def flower(x, y, core, petal, reach, count):
    cs = circle(x, y, core)
    for i in range(count):
        a = 2 * math.pi * i / count
        cs = cs + circle(x + reach * math.cos(a), y + reach * math.sin(a), petal)
    return cs


head = C.circle(1.0, 160).scale([330, 310]).translate([0, 120]) + box(-195, -340, 195, 0, 70)
head = head.offset(-40, J, 2.0, 32).offset(80, J, 2.0, 32).offset(-40, J, 2.0, 32)       # the cheeks run into the jaw
holes = flower(-140, 105, 78, 30, 88, 10) + flower(140, 105, 78, 30, 88, 10)             # eye sockets with a scalloped edge
holes = holes + (circle(-26, -62, 30) + circle(26, -62, 30) + C([[(-52, -50), (52, -50), (0, 22)]], M.FillRule.NonZero))      # the nose: a heart upside down
for x in (-112, -56, 0, 56, 112):
    holes = holes + box(x - 11, -300, x + 11, -190, 9)                                   # teeth
holes = holes + box(-160, -253, 160, -237, 7)
holes = holes + circle(0, 330, 24)                                                       # a flower on the forehead: petals round a dot
for i in range(6):
    a = math.pi / 2 + 2 * math.pi * i / 6
    holes = holes + circle(56 * math.cos(a), 330 + 56 * math.sin(a), 20)
for x in (-238, 238):
    holes = holes + circle(x, -40, 22) + circle(x * 0.86, -112, 15)                      # dots on the cheeks
skull = (head - holes).simplify(0.3)

x0, y0, x1, y1 = skull.bounds()
k = 1000.0 / (y1 - y0)
d = "".join("M" + "L".join("%s,%s" % (round((float(x) - x0) * k, 1), round((y1 - float(y)) * k, 1)) for x, y in ring) + "Z" for ring in skull.to_polygons())
with open(os.path.join(HERE, "sugar-skull.svg"), "w", encoding="utf-8", newline="\n") as f:
    f.write('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %s 1000">\n<path fill="#111" d="%s"/>\n</svg>\n' % (round((x1 - x0) * k, 1), d))
print("sugar-skull.svg", len(skull.to_polygons()), "rings,", (len(d) + 1023) // 1024, "kB")
