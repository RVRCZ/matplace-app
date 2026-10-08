"""
Things put together from layers, for param_tool.py: texts, pictures of the library and plain shapes, each in a filament
of its own, laid one on another wherever the visitor puts them (a cake topper "2 · Olivia", a name on a cloud with a
star beside it).

A layer is {kind: text | art | shape, what it shows, x, y (where its middle lies, mm), w (its width, mm), turn (degrees),
code and hex (its filament)}. The list runs from the bottom up. Layer n lies a step higher than layer n−1, and under
every layer the layers below are filled up to it (as the colours of a picture are in shape_kinds): so each band of
the print holds one filament and the colours are changed by height, a filament swap on any printer.

Parts: `layer_<n>`, one for every band, numbered from the bottom. What would fall apart is tied in the lowest band.
A base may be added to that band: an eyelet on top to hang the thing by, or the sticks of a cake topper.
"""
import math
import os
import re

import shape2d as S

MAX_LAYERS = 12
LIMITS = {"thickness": (1.2, 8), "step": (0.4, 1.6), "eye_hole": (2, 8), "spike": (30, 100)}
BASES = ("none", "eyelet", "sticks")
PLAIN = ("rounded", "rect", "circle")           # shapes drawn here; the others are the plates of engines/shapes
STICK = 4.0


def _num(Invalid, p, key, default):
    lo, hi = LIMITS[key]
    try:
        v = float(p.get(key, default))
    except (TypeError, ValueError):
        raise Invalid("not_a_number", key)
    if math.isnan(v) or v < lo or v > hi:
        raise Invalid("out_of_range", "%s %s-%s" % (key, lo, hi))
    return v


def _shape(M, name, path):
    """A plain shape by its name, or a plate of engines/shapes read from its file; about 100 wide."""
    C = M.CrossSection
    if name == "circle":
        return C.circle(50, 128)
    if name in PLAIN:
        return S.rounded_rect(M, 100, 60, 10 if name == "rounded" else 0)
    plain = re.search(r'<path d="M([-0-9. L]+)Z"/>', open(path, encoding="utf-8").read())
    if plain:
        return C([[(float(x), -float(y)) for x, y in (pt.split() for pt in plain.group(1).split("L"))]], M.FillRule.NonZero)
    return S.svg(M, path, 100.0)[0]


def _outline(M, Invalid, layer, n, missing):
    """One layer as an outline where the visitor put it."""
    kind = layer.get("kind")
    try:
        if kind == "text":
            cs, info = S.text(M, [str(layer.get("text", ""))], layer.get("font"), 10)
            missing += [c for c in info.get("missing_chars", []) if c not in missing]
        elif kind == "art":
            cs = S.load(M, {"artwork_path": layer.get("art_path")}, 100.0)[0]
        elif kind == "shape":
            cs = _shape(M, str(layer.get("shape", "")), layer.get("shape_path") or "")
        else:
            raise Invalid("bad_choice", "layers.%d.kind" % n)
    except S.ArtworkError as e:
        raise Invalid(e.code, str(e).split(": ", 1)[1] if ": " in str(e) else "")
    except (OSError, ValueError):
        raise Invalid("bad_choice", "layers.%d" % n)
    try:
        x, y, w, turn = (float(layer.get(k, d)) for k, d in (("x", 0), ("y", 0), ("w", 50), ("turn", 0)))
    except (TypeError, ValueError):
        raise Invalid("not_a_number", "layers.%d" % n)
    if not (5 <= w <= 250 and abs(x) <= 250 and abs(y) <= 250 and abs(turn) <= 360):
        raise Invalid("out_of_range", "layers.%d" % n)
    cs = S.fit(cs, width_mm=w)
    x0, y0, x1, y1 = cs.bounds()
    return cs.translate([-(x0 + x1) / 2, -(y0 + y1) / 2]).rotate(turn).translate([x, y])


def compose(M, Invalid, p):
    C, J = M.CrossSection, M.JoinType.Round
    n = lambda key, d: _num(Invalid, p, key, d)       # noqa: E731
    t, step = n("thickness", 3), n("step", 0.8)
    base = p.get("base", BASES[0])
    if base not in BASES:
        raise Invalid("bad_choice", "base")
    given = [layer for layer in (p.get("layers") or []) if isinstance(layer, dict) and not layer.get("hidden")]
    if not given:
        raise Invalid("no_layers")
    if len(given) > MAX_LAYERS:
        raise Invalid("too_many_layers")
    missing, warn = [], []
    own = [_outline(M, Invalid, layer, i, missing) for i, layer in enumerate(given)]
    if any(cs.is_empty() for cs in own):
        raise Invalid("empty_result")
    # under every layer the ones below are filled up to it: the outline of a band is its layer and everything above
    stack, above = [None] * len(own), C()
    for i in range(len(own) - 1, -1, -1):
        above = above + own[i]
        stack[i] = above
    ground = stack[0].simplify(0.02)
    ground, links = S.joined(M, ground, 0.8, 2.4)                 # what the visitor laid apart is tied where it shows least
    if links:
        warn.append("pieces_tied")
    x0, y0, x1, y1 = ground.bounds()
    added = {}
    if base == "eyelet":
        hole = n("eye_hole", 4)
        strip = ground ^ C.square([2.0, y1 - y0 + 2]).translate([(x0 + x1) / 2 - 1.0, y0 - 1])
        cx, cy = (x0 + x1) / 2, (strip.bounds()[3] if not strip.is_empty() else y1) + hole / 2 - 0.4
        ground = ground + C.circle(hole / 2 + 2.0, 64).translate([cx, cy]) - C.circle(hole / 2, 48).translate([cx, cy])
        added["eyelet"] = [round(cx - x0, 2), round(cy - y0, 2), hole]
    elif base == "sticks":
        length = n("spike", 60)
        bottom = y0 - length
        for share in (0.3, 0.7):
            x = x0 + (x1 - x0) * share
            for _ in range(12):                              # nothing above this place: move towards the middle
                over = ground ^ C.square([STICK, y1 - y0 + 2]).translate([x - STICK / 2, y0 - 1])
                if over.area() > STICK * 2:
                    break
                x += (x0 + x1 - 2 * x) * 0.15
            top = over.bounds()[1] + 3.0 if not over.is_empty() else y0 + 3.0
            ground = ground + C([[(x - STICK / 2, top), (x - STICK / 2, bottom + 6), (x, bottom), (x + STICK / 2, bottom + 6), (x + STICK / 2, top)]], M.FillRule.NonZero)
        added["sticks"] = round(length, 1)
    stack[0] = ground
    gx0, gy0, gx1, gy1 = ground.bounds()
    move = [-gx0, -gy0]
    bands, z = [], 0.0
    for i, cs in enumerate(stack):
        high = t if i == 0 else step
        cs = cs.translate(move)
        if not cs.is_empty():
            bands.append((i, z, high, cs))
        z += high
    pieces, paint, listed, changes, whole = [], {}, [], [], None
    last = None
    for i, z0, high, cs in bands:
        name = "layer_%d" % (i + 1)
        solid = cs.extrude(high).translate([0, 0, z0])
        pieces.append((name, solid))
        fused = cs.extrude(high + (0.01 if i else 0.0)).translate([0, 0, z0 - (0.01 if i else 0.0)])
        whole = fused if whole is None else whole + fused
        code, hx = str(given[i].get("code") or ""), str(given[i].get("hex") or "#888888")
        paint[name] = hx
        bx0, by0, bx1, by1 = own[i].translate(move).bounds()
        listed.append({"part": name, "index": i + 1, "code": code, "hex": hx, "box": [round(v, 1) for v in (bx0, by0, bx1, by1)], "z": round(z0 + high, 2)})
        if last is not None and code != last:
            changes.append({"z": round(z0, 2), "part": name, "code": code, "hex": hx})
        last = code
    thin = max((S.printability(M, cs, 0.45)["thin_pct"] for cs in own), default=0)
    if thin > 35:
        warn.append("thin_lines")
    if missing:
        warn.append("missing_chars")
    parts = {name: solid for name, solid in pieces}
    parts["all"] = whole
    parts["_pieces"] = {"all": pieces}
    notes = {"outer": [round(gx1 - gx0, 1), round(gy1 - gy0, 1), round(z, 1)], "layers": listed, "paint": paint, "parts": [name for name, _ in pieces],
             "filaments": len({c["code"] for c in listed}), "multi_material": False, "color_changes": changes, "origin": [round(-gx0, 2), round(-gy0, 2)],
             "warnings": warn, "thin_pct": thin, "missing_chars": missing, "links": links}
    notes.update(added)
    if len(changes) == 1:
        notes["color_change_mm"] = changes[0]["z"]
    return parts, notes


BUILDERS = {"compose": compose}
