"""
The picture of a typeface in the font picker of the tools: a few words set in it, as an SVG.
Drawn from the very outlines the models are made of (shape2d.text), so what the visitor picks is what gets printed.

    python font_preview.py <font.ttf> <out.svg> <text>

Answers one line of JSON: {"ok": true, "width": …, "height": …} or {"ok": false, "error": …}.
"""
import json
import sys

import manifold3d as M

import shape2d as S


def main(font, out, text):
    cs, info = S.text(M, [text], font, 10)
    x0, y0, x1, y1 = cs.bounds()
    pad = 0.6
    paths = []
    for poly in cs.simplify(0.02).to_polygons():
        pts = ["%s %s" % (round(float(x) - x0 + pad, 2), round(y1 - float(y) + pad, 2)) for x, y in poly]      # SVG counts y downwards
        paths.append("M" + "L".join(pts) + "Z")
    w, h = round(x1 - x0 + 2 * pad, 2), round(y1 - y0 + 2 * pad, 2)
    with open(out, "w", encoding="utf-8", newline="\n") as f:
        f.write('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %s %s" width="%s" height="%s"><path fill-rule="evenodd" d="%s"/></svg>\n' % (w, h, w, h, "".join(paths)))
    return {"ok": True, "width": w, "height": h, "missing": info.get("missing_chars", [])}


if __name__ == "__main__":
    try:
        print(json.dumps(main(sys.argv[1], sys.argv[2], sys.argv[3])))
    except Exception as e:  # noqa: BLE001 - the command reads the answer and says which typeface failed
        print(json.dumps({"ok": False, "error": str(e)[:200]}))
