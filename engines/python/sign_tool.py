#!/usr/bin/env python3
"""
Parametric sign / name tag / keychain generator (CadQuery, exact millimetres, no AI).

  sign_tool.py <out.stl> <json-params>

params: {
  "lines": ["Roman", "3D tisk"],      1-2 lines of text
  "font": "/path/to/font.ttf",
  "text_height": 12,                  capital height of the first line in mm (second line is 70 %)
  "shape": "rounded" | "oval" | "rect",
  "thickness": 3,                     plate thickness in mm
  "relief": 1.2,                      text height above (emboss) or depth into (engrave) the plate
  "style": "emboss" | "engrave",
  "hole": true,                       keychain hole with a tab on the left
  "margin": 5,
  "border": true                      raised rim (emboss style only)
}
Prints one JSON object: {"ok": true, "width": .., "height": .., "out": ..}
"""
import json
import sys


def fail(msg):
    print(json.dumps({"ok": False, "error": str(msg)}))
    sys.exit(0)


def main():
    if len(sys.argv) < 3:
        fail("usage")
    out = sys.argv[1]
    try:
        p = json.loads(sys.argv[2])
    except Exception as e:  # noqa: BLE001
        fail("bad params: %s" % e)

    try:
        import cadquery as cq
    except Exception as e:  # noqa: BLE001
        fail("cadquery missing: %s" % e)

    lines = [str(x).strip() for x in (p.get("lines") or []) if str(x).strip()][:2]
    if not lines:
        fail("no text")
    font = p.get("font")
    th = max(4.0, min(80.0, float(p.get("text_height", 12))))
    thickness = max(1.2, min(10.0, float(p.get("thickness", 3))))
    relief = max(0.4, min(5.0, float(p.get("relief", 1.2))))
    style = p.get("style", "emboss")
    shape = p.get("shape", "rounded")
    margin = max(2.0, min(30.0, float(p.get("margin", th * 0.45))))
    hole = bool(p.get("hole", False))
    border = bool(p.get("border", True)) and style == "emboss"
    if style == "engrave":
        relief = min(relief, thickness - 0.6)

    try:
        def text_solid(txt, size, depth):
            kw = {"halign": "center", "valign": "center", "combine": False}
            if font:
                kw["fontPath"] = font
            return cq.Workplane("XY").text(txt, size, depth, **kw)

        depth = relief if style == "emboss" else relief + 0.2
        solids = []
        sizes = [th, th * 0.7]
        gap = th * 0.35
        boxes = []
        for i, ln in enumerate(lines):
            t = text_solid(ln, sizes[i] * 1.35, depth)  # font size ~ 1.35 x capital height
            bb = t.val().BoundingBox() if hasattr(t.val(), "BoundingBox") else t.combine().val().BoundingBox()
            solids.append(t)
            boxes.append(bb)

        heights = [b.ymax - b.ymin for b in boxes]
        widths = [b.xmax - b.xmin for b in boxes]
        total_h = sum(heights) + (gap if len(lines) == 2 else 0)
        text_w = max(widths)

        # vertical placement of lines (centred block)
        y = total_h / 2.0
        placed = []
        for i, t in enumerate(solids):
            cy = y - heights[i] / 2.0
            b = boxes[i]
            dx = -(b.xmin + b.xmax) / 2.0
            dy = cy - (b.ymin + b.ymax) / 2.0
            placed.append(t.translate((dx, dy, 0)))
            y -= heights[i] + gap

        rim = 1.6 if border else 0.0
        w = text_w + 2 * margin + 2 * rim
        h = total_h + 2 * margin + 2 * rim

        if shape == "oval":
            plate = cq.Workplane("XY").ellipse(w / 2.0 * 1.12, h / 2.0 * 1.25).extrude(thickness)
            w, h = w * 1.12, h * 1.25
        else:
            plate = cq.Workplane("XY").rect(w, h).extrude(thickness)
            if shape == "rounded":
                plate = plate.edges("|Z").fillet(min(h, w) * 0.18)

        if hole:
            r_out = max(5.0, h * 0.28)
            r_in = max(2.0, r_out * 0.45)
            cx = -w / 2.0 - r_out * 0.35
            tab = cq.Workplane("XY").center(cx, 0).circle(r_out).extrude(thickness)
            plate = plate.union(tab).faces(">Z").workplane(origin=(cx, 0, thickness)).circle(r_in).cutThruAll()

        if style == "emboss":
            result = plate
            for t in placed:
                result = result.union(t.translate((0, 0, thickness)))
            if border and shape != "oval":
                outer = cq.Workplane("XY").rect(w, h).extrude(relief)
                if shape == "rounded":
                    outer = outer.edges("|Z").fillet(min(h, w) * 0.18)
                inner = cq.Workplane("XY").rect(w - 2 * rim, h - 2 * rim).extrude(relief)
                if shape == "rounded":
                    inner = inner.edges("|Z").fillet(max(0.5, min(h, w) * 0.18 - rim))
                result = result.union(outer.cut(inner).translate((0, 0, thickness)))
        else:
            result = plate
            for t in placed:
                result = result.cut(t.translate((0, 0, thickness - relief)))

        cq.exporters.export(result, out, exportType="STL", tolerance=0.03, angularTolerance=0.2)
        print(json.dumps({"ok": True, "width": round(w, 1), "height": round(h, 1), "thickness": thickness, "out": out}))
    except SystemExit:
        raise
    except Exception as e:  # noqa: BLE001
        fail(e)


if __name__ == "__main__":
    main()
