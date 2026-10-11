#!/usr/bin/env python3
"""
A 3D map of a city or a landscape as a printed plate (the tool /tools/map), from open data the PHP side has already
fetched and put on disk: an Overpass answer (OpenStreetMap: buildings, roads, water, railways, green) and the
Terrarium height tiles of the area. Nothing here touches the network.

  map_tool.py build   <params.json> <out.stl>    {ok, bbox, volume_mm3, triangles, notes}
  map_tool.py preview <params.json> <out.png>    {ok, width, height}   a picture of the area from above, 600 × 600

params.json:
  {"osm": "<path of the Overpass JSON>", "dem": [{"z": 14, "x": 8840, "y": 5560, "path": "<png>"}, …],
   "center": [lat, lon], "side_m": 1000, "font": "<ttf of the name on the frame>", "params": {…the page's settings…}}

The plate is `size` mm square: a frame of `frame_mm` round a window `side_m` metres across, so the scale is
(size − 2·frame) : side_m. A city stands on a flat plate: the roads 0.6 mm high on it (printed in a second colour),
the buildings above them (a third colour), water and railways sunk into the plate. A landscape is the relief of the
height tiles with its lowest point on the plate. Everything is one solid: nothing hangs in the air. The colours are
printed by height (notes.color_changes), and the viewer paints the model by the same heights (notes.regions).

Progress goes to <out.stl>.stage (reading, buildings, roads, writing) for the page to show.
"""
import json
import math
import numpy as np
import os
import sys
import time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

EARTH = 6378137.0
ROAD_WIDTH = {"motorway": 12.0, "motorway_link": 8.0, "trunk": 11.0, "trunk_link": 8.0, "primary": 9.0, "primary_link": 7.0, "secondary": 8.0, "secondary_link": 6.0,
              "tertiary": 7.0, "tertiary_link": 6.0, "residential": 6.0, "unclassified": 5.0, "living_street": 5.0, "pedestrian": 5.0, "service": 4.0,
              "track": 3.0, "path": 2.0, "footway": 2.0, "cycleway": 2.5, "steps": 2.0, "bridleway": 2.0}
ROAD_SKIP = {"proposed", "construction", "abandoned", "razed", "platform", "bus_stop", "elevator", "corridor"}
# roofs and towers: what OSM says (roof:shape, building:part, man_made=tower), and what a Czech village is like when it says nothing
HOUSE_KINDS = {"house", "residential", "detached", "semidetached_house", "farm", "terrace", "bungalow"}
ROOF_KIND = {"gabled": "gabled", "gambrel": "gabled", "round": "gabled", "skillion": "gabled", "saltbox": "gabled",
             "hipped": "hipped", "half-hipped": "hipped", "mansard": "hipped",
             "pyramidal": "pyramidal", "dome": "pyramidal", "onion": "pyramidal", "cone": "pyramidal"}
ROOF_MIN = 0.8          # mm: no roof lower than this in the print (a slope a nozzle cannot draw is raised to it, not dropped: at 1 km on 200 mm a house's roof is 0.6 mm)
ROOF_SHARE = 0.35       # a roof's height against the walls' when OSM gives no roof:height
SPIRE_SHARE = 0.5       # a tower's spire against its walls
TOWER_OVER_NAVE = 2.5   # a tower without a height: this many times the church it stands in (or the default height)
TOWER_NODE_M = 5.0      # a tower mapped as a point: a square this wide, in metres
WATERWAY_WIDTH = {"river": 20.0, "canal": 10.0, "stream": 4.0, "ditch": 2.0, "drain": 2.0}
RAIL_KIND = {"rail", "light_rail", "narrow_gauge", "tram", "subway"}
RAIL_WIDTH = 3.0
ROAD_LIFT = 0.6              # mm: the roads on the plate (two layers of 0.3 mm), where the second colour starts
BUILDING_MIN = 1.0           # mm: a building lower than this would be a smear; it is raised to it
FRAME_LIFT = 1.2             # mm: the frame above the plate
NAME_LIFT = 0.6              # mm: the name raised on the frame
WATER_SINK, RAIL_SINK, GREEN_SINK = 0.8, 0.4, 0.4
THIN = 0.8                   # mm: the narrowest road or building wall a nozzle of 0.4 mm prints well
COLORS = {"base": "#e8e4d8", "roads": "#2b2b2b", "buildings": "#c9a96b", "water": "#3b82c4", "terrain": "#2d6a4f", "green": "#8fbf7a"}


class Invalid(Exception):
    def __init__(self, code, detail=""):
        super().__init__(code + (": " + detail if detail else ""))
        self.code = code


def out(obj):
    print(json.dumps(obj))
    sys.exit(0)


def stage(dst, name):
    if not dst:
        return
    try:
        with open(dst + ".stage", "w", encoding="utf-8") as fh:
            fh.write(name)
    except OSError:
        pass


# ── the data ──────────────────────────────────────────────────────────────────────────────────────────────────

def _number(text):
    """"12", "12 m", "12.5", "40 ft" → metres; None for anything else."""
    if text is None:
        return None
    s = str(text).strip().lower().replace(",", ".")
    feet = s.endswith("ft") or s.endswith("'")
    digits = "".join(ch for ch in s if ch.isdigit() or ch == ".")
    try:
        v = float(digits)
    except ValueError:
        return None
    return v * 0.3048 if feet else v


class Area:
    """The OpenStreetMap elements of a square, projected to the millimetres of the plate (x east, y north, the centre at 0, 0)."""

    def __init__(self, path, center, side_m, inner_mm):
        try:
            with open(path, encoding="utf-8") as fh:
                doc = json.load(fh)
        except (OSError, ValueError) as e:
            raise Invalid("osm_unreadable", str(e)[:80])
        self.k = inner_mm / float(side_m)                   # mm per metre
        self.side_m, self.inner = float(side_m), float(inner_mm)
        self.date = str((doc.get("osm3s") or {}).get("timestamp_osm_base", ""))[:10]
        lat0, lon0 = math.radians(center[0]), math.radians(center[1])
        cos0 = math.cos(lat0)
        y0 = math.log(math.tan(math.pi / 4 + lat0 / 2))
        self.nodes, self.ways, self.relations, self.node_tags = {}, {}, {}, {}
        for el in doc.get("elements", []):
            t = el.get("type")
            if t == "node":
                lon, lat = math.radians(float(el["lon"])), math.radians(float(el["lat"]))
                # Web Mercator, scaled back to metres on the ground at this latitude, then to the plate
                self.nodes[el["id"]] = (EARTH * (lon - lon0) * cos0 * self.k, EARTH * (math.log(math.tan(math.pi / 4 + lat / 2)) - y0) * cos0 * self.k)
                if el.get("tags"):
                    self.node_tags[el["id"]] = el["tags"]
            elif t == "way":
                self.ways[el["id"]] = el
            elif t == "relation":
                self.relations[el["id"]] = el

    def line(self, way):
        pts = [self.nodes[n] for n in way.get("nodes", []) if n in self.nodes]
        return pts if len(pts) >= 2 else None

    def ring(self, way):
        pts = self.line(way)
        if not pts or len(pts) < 3:
            return None
        if pts[0] == pts[-1]:
            pts = pts[:-1]
        return pts if len(pts) >= 3 else None

    def rings_of(self, el):
        """The closed rings of a way or a multipolygon relation (outer ways joined end to end when they come in pieces)."""
        if el.get("type") == "way":
            ring = self.ring(el)
            return [ring] if ring else []
        pieces = []
        for m in el.get("members", []):
            w = self.ways.get(m.get("ref")) if m.get("type") == "way" else None
            line = self.line(w) if w else None
            if line:
                pieces.append(line)
        rings = []
        while pieces:
            chain = pieces.pop(0)
            changed = True
            while chain[0] != chain[-1] and changed:
                changed = False
                for i, other in enumerate(pieces):
                    if other[0] == chain[-1]:
                        chain, changed = chain + other[1:], True
                    elif other[-1] == chain[-1]:
                        chain, changed = chain + other[-2::-1], True
                    elif other[-1] == chain[0]:
                        chain, changed = other[:-1] + chain, True
                    elif other[0] == chain[0]:
                        chain, changed = other[::-1][:-1] + chain, True
                    if changed:
                        pieces.pop(i)
                        break
            if chain[0] == chain[-1]:
                chain = chain[:-1]
            if len(chain) >= 3:
                rings.append(chain)
        return rings

    def polygons(self, keep):
        """Every way and relation `keep(tags)` says yes to, as (rings, tags); the member ways of a relation are not counted twice."""
        used = set()
        found = []
        for rel in self.relations.values():
            tags = rel.get("tags") or {}
            if tags.get("type") == "multipolygon" and keep(tags):
                rings = self.rings_of(rel)
                if rings:
                    found.append((rings, tags))
                    used.update(m.get("ref") for m in rel.get("members", []) if m.get("type") == "way")
        for wid, way in self.ways.items():
            tags = way.get("tags") or {}
            if wid in used or not keep(tags):
                continue
            ring = self.ring(way)
            if ring:
                found.append(([ring], tags))
        return found

    def lines(self, keep):
        return [(self.line(w), w.get("tags") or {}) for w in self.ways.values() if keep(w.get("tags") or {}) and self.line(w)]

    def points(self, keep):
        """The tagged nodes `keep(tags)` says yes to (a tower mapped as a point), as ((x, y), tags)."""
        return [(self.nodes[nid], tags) for nid, tags in self.node_tags.items() if nid in self.nodes and keep(tags)]


# ── 2D shapes ─────────────────────────────────────────────────────────────────────────────────────────────────

def _cs(M, rings):
    """Rings → a CrossSection with the inner rings as holes (even-odd), empty when the rings make nothing."""
    try:
        return M.CrossSection(rings, M.FillRule.EvenOdd)
    except Exception:  # noqa: BLE001 - a ring the library will not take (a repeated point): nothing of it
        return M.CrossSection()


def _band(M, pts, width):
    """A polyline as a strip `width` wide with round joints: a quad per segment and a disc at every point."""
    C = M.CrossSection
    r = width / 2.0
    shapes = []
    for (x0, y0), (x1, y1) in zip(pts, pts[1:]):
        dx, dy = x1 - x0, y1 - y0
        length = math.hypot(dx, dy)
        if length < 1e-6:
            continue
        nx, ny = -dy / length * r, dx / length * r
        shapes.append(C([[(x0 + nx, y0 + ny), (x1 + nx, y1 + ny), (x1 - nx, y1 - ny), (x0 - nx, y0 - ny)]], M.FillRule.NonZero))
    for x, y in pts:
        shapes.append(C.circle(r, 10).translate([x, y]))
    return shapes


def _union(M, shapes):
    shapes = [s for s in shapes if not s.is_empty()]
    if not shapes:
        return M.CrossSection()
    return M.CrossSection.batch_boolean(shapes, M.OpType.Add)


def _rounded(M, cs, r):
    """Corners rounded both ways (an opening, then a closing): a toy town of soft blocks."""
    J = M.JoinType.Round
    return cs.offset(-r, J, 2.0, 8).offset(2 * r, J, 2.0, 8).offset(-r, J, 2.0, 8)


def _no_slivers(M, cs, r):
    """Nothing narrower than 2r survives (an opening with square corners), so no building wall is a hairline."""
    J = M.JoinType.Miter
    return cs.offset(-r, J, 2.0, 8).offset(r, J, 2.0, 8)


# ── the city ──────────────────────────────────────────────────────────────────────────────────────────────────

def _area(poly):
    """The signed area of a ring: positive when it runs counter-clockwise (an outer ring), negative for a hole."""
    pts = np.asarray(poly, float)
    x, y = pts[:, 0], pts[:, 1]
    return 0.5 * float(np.dot(x, np.roll(y, -1)) - np.dot(y, np.roll(x, -1)))


def _inside(pt, ring):
    """Whether a point lies in a ring (a ray to the right crosses its edges an odd number of times)."""
    x, y = pt
    ok = False
    n = len(ring)
    for i in range(n):
        (x0, y0), (x1, y1) = ring[i], ring[(i + 1) % n]
        if (y0 > y) != (y1 > y) and x < x0 + (y - y0) * (x1 - x0) / (y1 - y0):
            ok = not ok
    return ok


def _obb(pts):
    """The smallest rectangle round the points: (centre x, centre y, the angle of its long side, long, short); None for nothing."""
    from scipy.spatial import ConvexHull
    pts = np.asarray(pts, float)
    try:
        hull = pts[ConvexHull(pts).vertices]
    except Exception:  # noqa: BLE001 - fewer than three distinct points, or all on one line
        hull = pts
    best = None
    n = len(hull)
    for i in range(n):
        dx, dy = hull[(i + 1) % n] - hull[i]
        if abs(dx) + abs(dy) < 1e-9:
            continue
        th = math.atan2(dy, dx)
        c, s = math.cos(-th), math.sin(-th)
        rx, ry = hull[:, 0] * c - hull[:, 1] * s, hull[:, 0] * s + hull[:, 1] * c
        w, h = float(rx.max() - rx.min()), float(ry.max() - ry.min())
        if best is None or w * h < best[0]:
            best = (w * h, th, float(rx.min() + rx.max()) / 2, float(ry.min() + ry.max()) / 2, w, h)
    if best is None:
        return None
    _, th, mx, my, w, h = best
    c, s = math.cos(th), math.sin(th)
    cx, cy = mx * c - my * s, mx * s + my * c
    if h > w:                                                 # the long side runs along the local y: a quarter turn
        th, w, h = th + math.pi / 2, h, w
    return cx, cy, th, w, h


def _roof(M, cs, kind, roof_mm):
    """
    The roof over a footprint, `roof_mm` high: the ridge along the longer side of the ring's smallest bounding
    rectangle (gabled: the top a line as long as the ring; hipped: shorter by the short side, so the hips slope like
    the sides; pyramidal: a point in the middle). A ring with a hole or narrower than a nozzle's line gets none.
    Returns the solids (the footprint may be in pieces at the window's edge) or None.
    """
    polys = cs.to_polygons()
    outers = [p for p in polys if _area(p) > 0]
    if not outers or any(_area(p) < 0 for p in polys):
        return None
    solids = []
    for ring in outers:
        box = _obb(ring)
        if box is None:
            continue
        cx, cy, th, long, short = box
        if short < THIN or long < THIN:
            continue
        c, s = math.cos(-th), math.sin(-th)
        local = [((x - cx) * c - (y - cy) * s, (x - cx) * s + (y - cy) * c) for x, y in np.asarray(ring, float).tolist()]
        scale = (1.0, 0.0) if kind == "gabled" else (max(0.0, (long - short) / long), 0.0) if kind == "hipped" else (0.0, 0.0)
        try:
            solids.append(M.CrossSection([local]).extrude(roof_mm, 0, 0.0, scale).rotate([0.0, 0.0, math.degrees(th)]).translate([cx, cy, 0.0]))
        except Exception:  # noqa: BLE001 - a ring the library will not raise: that piece stays flat
            continue
    return solids or None


def city_layers(M, area, p):
    """
    The 2D layers of a city in plate millimetres: buildings by height, roads, railways, water, green, each clipped
    to the window, the lower ones without what the higher ones take. Returns a dict the solid and the picture are
    both made from.
    """
    C = M.CrossSection
    k, inner = area.k, area.inner
    miniature = p.get("style") == "miniature"
    window = C.square([inner, inner]).translate([-inner / 2, -inner / 2])
    default_h = float(p.get("default_h", 6.0))
    lift = 1.5 if miniature else 1.0

    roofs_mode = p.get("roofs", "houses")   # houses: a gabled roof on every house OSM says nothing about; data: only as mapped; flat: none

    def height_m(tags, kind="building", inside=None):
        """The full height in metres: as mapped, by the storeys, or the default; a tower without a word stands 2.5x the church it is in."""
        h = _number(tags.get("height"))
        if h is None:
            levels = _number(tags.get("building:levels"))
            h = levels * 3.0 if levels else None
        if h is None or h <= 0:
            h = (inside or default_h) * TOWER_OVER_NAVE if kind == "tower" else default_h
        return max(2.0, min(150.0, h))

    def split(tags, total, kind):
        """(walls m, roof m, roof kind or None): the roof as mapped, a gabled one on a house without a word, a spire on a toy tower."""
        roof = ROOF_KIND.get(str(tags.get("roof:shape", "")).lower())
        if roof is None and kind == "house" and roofs_mode == "houses":
            roof = "gabled"
        if roof is None and kind == "tower" and miniature:
            roof = "pyramidal"
        if roof is None or roofs_mode == "flat":
            return total, 0.0, None
        rh = _number(tags.get("roof:height"))
        if rh is None:
            levels = _number(tags.get("roof:levels"))
            rh = levels * 3.0 if levels else None
        if rh is None:
            share = SPIRE_SHARE if kind == "tower" else ROOF_SHARE
            rh = total * share / (1.0 + share)
        rh = max(0.0, min(rh, total - 2.0))
        return total - rh, rh, roof

    def kind_of(tags):
        """tower (a tower, or a part of a building that is one: a tower:type, a steeple, a spire), house, building, or part."""
        part = tags.get("building:part", "no")
        if tags.get("man_made") == "tower" or (part != "no" and ("tower:type" in tags or part in ("tower", "steeple", "bell_tower") or ROOF_KIND.get(str(tags.get("roof:shape", "")).lower()) == "pyramidal")):
            return "tower"
        if "building" in tags and tags["building"] != "no":
            return "house" if tags["building"] in HOUSE_KINDS else "building"
        return "part"

    # buildings, the parts of buildings with their own height (a church's tower) and towers; each its walls in a band
    # by printed height (to 0.1 mm, the tallest first) and, when it has one, its roof on top
    found = [(rings, tags, kind_of(tags)) for rings, tags in area.polygons(lambda t: ("building" in t and t.get("building") != "no") or t.get("building:part", "no") != "no" or t.get("man_made") == "tower")]
    half = TOWER_NODE_M * k / 2.0
    found += [([[(x - half, y - half), (x + half, y - half), (x + half, y + half), (x - half, y + half)]], tags, "tower") for (x, y), tags in area.points(lambda t: t.get("man_made") == "tower")]
    naves = [(rings[0], height_m(tags)) for rings, tags, kind in found if kind in ("house", "building")]
    bands, roofs = {}, []
    count = towers = pitched = 0
    top = 0.0
    for rings, tags, kind in found:
        cs = _cs(M, rings) ^ window
        if cs.is_empty():
            continue
        inside = None
        if kind == "tower":
            towers += 1
            cx, cy = sum(x for x, _ in rings[0]) / len(rings[0]), sum(y for _, y in rings[0]) / len(rings[0])
            inside = max([h for ring, h in naves if _inside((cx, cy), ring)] + [0.0]) or None
        elif kind != "part":
            count += 1
        walls, roof_m, roof = split(tags, height_m(tags, kind, inside), kind)
        roof_mm = max(ROOF_MIN, roof_m * k * lift) if roof else 0.0
        made = _roof(M, cs, roof, roof_mm) if roof else None
        if made is None:                                      # no roof after all: the walls take the whole height
            walls, roof_mm = walls + roof_m, 0.0
        h_mm = round(max(BUILDING_MIN, walls * k * lift), 1)
        bands.setdefault(h_mm, []).append(cs)
        if made is not None:
            roofs.append((h_mm, made))
            pitched += kind != "tower"
        top = max(top, h_mm + roof_mm)
    footprint = C()
    buildings = []                                            # [(h_mm, CrossSection)] from the tallest down, disjoint
    for h_mm in sorted(bands, reverse=True):
        shape = _union(M, bands[h_mm])
        shape = _rounded(M, shape, max(0.4, min(1.0, 0.6 * k))) if miniature else _no_slivers(M, shape, THIN / 2)
        shape = shape - footprint
        if not shape.is_empty():
            buildings.append((h_mm, shape))
            footprint = footprint + shape

    # roads: a strip per way, as wide as its kind, never narrower than a nozzle prints
    strips, roads_m = [], 0.0
    if p.get("roads", "raised") != "none":
        for pts, tags in area.lines(lambda t: "highway" in t and t["highway"] not in ROAD_SKIP and t.get("area") != "yes"):
            w = ROAD_WIDTH.get(tags["highway"], 3.0) * (1.4 if miniature else 1.0)
            strips += _band(M, pts, max(THIN, w * k))
            roads_m += sum(math.hypot(x1 - x0, y1 - y0) for (x0, y0), (x1, y1) in zip(pts, pts[1:])) / k
    roads = (_union(M, strips) ^ window) - footprint

    rails = C()
    if p.get("rail", True):
        rails = (_union(M, [s for pts, _ in area.lines(lambda t: t.get("railway") in RAIL_KIND) for s in _band(M, pts, max(THIN, RAIL_WIDTH * k))]) ^ window) - footprint - roads

    water = C()
    if p.get("water", True):
        shapes = [_cs(M, rings) for rings, _ in area.polygons(lambda t: t.get("natural") == "water" or "water" in t or t.get("landuse") in ("reservoir", "basin"))]
        for pts, tags in area.lines(lambda t: t.get("waterway") in WATERWAY_WIDTH):
            shapes += _band(M, pts, max(THIN, WATERWAY_WIDTH[tags["waterway"]] * k))
        water = (_union(M, shapes) ^ window) - footprint - roads - rails

    green = C()
    if p.get("green", False):
        shapes = [_cs(M, rings) for rings, _ in area.polygons(lambda t: t.get("landuse") in ("forest", "grass", "meadow", "orchard", "village_green", "recreation_ground", "cemetery") or t.get("leisure") in ("park", "garden", "pitch", "playground") or t.get("natural") in ("wood", "grassland", "scrub", "heath"))]
        green = (_union(M, shapes) ^ window) - footprint - roads - rails - water

    return {"window": window, "buildings": buildings, "roofs": roofs, "footprint": footprint, "roads": roads, "rails": rails, "water": water, "green": green,
            "count": count, "towers": towers, "pitched": pitched, "top": top, "roads_m": roads_m, "k": k, "inner": inner, "miniature": miniature}


def frame_and_name(M, p, inner, size, base_h, font, miniature):
    """The frame round the window and the name on its front edge; (frame solid or None, name solid or None, what was written)."""
    C = M.CrossSection
    frame_mm = float(p.get("frame_mm", 6.0)) if p.get("frame", True) else 0.0
    if frame_mm <= 0:
        return None, None, ""
    outer = C.square([size, size]).translate([-size / 2, -size / 2])
    if miniature:
        outer = outer.offset(-3.0, M.JoinType.Round, 2.0, 12).offset(3.0, M.JoinType.Round, 2.0, 12)
    ring = outer - C.square([inner, inner]).translate([-inner / 2, -inner / 2])
    frame = ring.extrude(base_h + FRAME_LIFT)
    name = str(p.get("name") or "").strip()[:40]
    if not name or not font:
        return frame, None, ""
    import shape2d as S
    cap = max(2.0, frame_mm * 0.6)
    try:
        cs, _ = S.text(M, [name], font, cap)
    except Exception:  # noqa: BLE001 - a font that cannot draw it: a frame without a name
        return frame, None, ""
    x0, y0, x1, y1 = cs.bounds()
    room = size - 2 * frame_mm - 6.0
    if x1 - x0 > room:
        f = room / (x1 - x0)
        if cap * f < 2.0:
            return frame, None, ""
        cs = cs.scale([f, f])
        x0, y0, x1, y1 = cs.bounds()
    cs = cs.translate([-(x0 + x1) / 2, -inner / 2 - frame_mm / 2 - (y0 + y1) / 2])
    return frame, cs.extrude(NAME_LIFT).translate([0, 0, base_h + FRAME_LIFT]), name


def build_city(M, params, dst):
    p = params.get("params") or {}
    size, base_h = float(p.get("size", 150)), float(p.get("base_h", 4))
    frame_mm = float(p.get("frame_mm", 6.0)) if p.get("frame", True) else 0.0
    inner = size - 2 * frame_mm
    if inner < 40:
        raise Invalid("frame_too_wide")
    stage(dst, "reading")
    area = Area(params["osm"], params["center"], params["side_m"], inner)
    stage(dst, "buildings")
    L = city_layers(M, area, p)
    miniature = L["miniature"]
    stage(dst, "roads")
    C = M.CrossSection
    plate = C.square([size, size]).translate([-size / 2, -size / 2]) if frame_mm <= 0 else L["window"]
    if miniature and frame_mm <= 0:
        plate = plate.offset(-3.0, M.JoinType.Round, 2.0, 12).offset(3.0, M.JoinType.Round, 2.0, 12)
    base = plate.extrude(base_h)
    cuts = []
    if not L["water"].is_empty():
        cuts.append(L["water"].extrude(WATER_SINK + 0.01).translate([0, 0, base_h - WATER_SINK]))
    if not L["rails"].is_empty():
        cuts.append(L["rails"].extrude(RAIL_SINK + 0.01).translate([0, 0, base_h - RAIL_SINK]))
    if not L["green"].is_empty():
        cuts.append(L["green"].extrude(GREEN_SINK + 0.01).translate([0, 0, base_h - GREEN_SINK]))
    raised = p.get("roads", "raised") == "raised"             # sunk roads, or none: the buildings' colour starts at the plate
    if not raised and not L["roads"].is_empty():
        cuts.append(L["roads"].extrude(ROAD_LIFT + 0.01).translate([0, 0, base_h - ROAD_LIFT]))
    if cuts:
        base = base - M.Manifold.batch_boolean(cuts, M.OpType.Add)
    solids = [base]
    if raised and not L["roads"].is_empty():
        solids.append(L["roads"].extrude(ROAD_LIFT).translate([0, 0, base_h]))
    for h_mm, shape in L["buildings"]:
        solids.append(shape.extrude(h_mm).translate([0, 0, base_h]))
    for h_mm, pieces in L["roofs"]:                           # each roof on its own walls
        solids += [r.translate([0, 0, base_h + h_mm]) for r in pieces]
    frame, name, written = frame_and_name(M, p, inner, size, base_h, params.get("font"), miniature)
    if frame is not None:
        solids.append(frame)
    if name is not None:
        solids.append(name)
    stage(dst, "writing")
    model = M.Manifold.batch_boolean(solids, M.OpType.Add)
    colors = {**COLORS, **{k: v for k, v in (p.get("colors") or {}).items() if isinstance(v, str)}}
    top = base_h + L["top"]
    # the colours by height, as the print changes filament and as the viewer paints: the plate, the roads from its top, the buildings above the roads
    changes = ([{"z": round(base_h, 2), "hex": colors["roads"]}, {"z": round(base_h + ROAD_LIFT, 2), "hex": colors["buildings"]}] if raised
               else [{"z": round(base_h, 2), "hex": colors["buildings"]}])
    far = 9999.0
    regions = ([{"x0": -far, "y0": -far, "x1": far, "y1": far, "z0": round(base_h + ROAD_LIFT + 0.01, 2), "color": colors["buildings"]},
                {"x0": -far, "y0": -far, "x1": far, "y1": far, "z0": round(base_h + 0.01, 2), "color": colors["roads"]}] if raised
               else [{"x0": -far, "y0": -far, "x1": far, "y1": far, "z0": round(base_h + 0.01, 2), "color": colors["buildings"]}])
    regions.append({"x0": -far, "y0": -far, "x1": far, "y1": far, "z0": -1.0, "color": colors["base"]})
    warn = []
    if L["count"] == 0:
        warn.append("no_buildings")
    if L["roads_m"] <= 0 and p.get("roads", "raised") != "none":
        warn.append("no_roads")
    notes = {"type": "city", "scale": scale_text(area.side_m, inner), "scale_n": int(round(area.side_m * 1000.0 / inner)), "osm_date": area.date,
             "buildings": L["count"], "roofs": L["pitched"], "towers": L["towers"], "roads_m": int(round(L["roads_m"])), "water": not L["water"].is_empty(), "name": written,
             "top_mm": round(top, 2), "color_changes": changes, "regions": regions, "colors": colors, "warnings": warn,
             "attribution": "Data © OpenStreetMap contributors (ODbL)"}
    return model, notes


def scale_text(side_m, inner_mm):
    n = side_m * 1000.0 / inner_mm
    step = 10 ** max(0, int(math.log10(n)) - 1)
    n = int(round(n / step) * step)
    return "1 : " + "{:,}".format(n).replace(",", " ")


# ── the landscape ─────────────────────────────────────────────────────────────────────────────────────────────

TOWN_LIFT = 3.0              # mm: a built-up area of a landscape as a low block on the relief
GRID = (160, 320)            # the relief's grid: at least, at most (cells across the window)


def heightmap(params, cols, rows, side_m):
    """
    The heights of the square (metres), on a grid of cols × rows (row 0 the northern edge), read from the Terrarium
    tiles bilinearly: height = R·256 + G + B/256 − 32768.
    """
    import numpy as np
    from PIL import Image
    from scipy import ndimage
    tiles = params.get("dem") or []
    if not tiles:
        raise Invalid("dem_missing")
    z = int(tiles[0]["z"])
    n = 2 ** z
    lat0, lon0 = float(params["center"][0]), float(params["center"][1])
    lat0r = math.radians(lat0)
    cos0 = math.cos(lat0r)
    m0 = math.log(math.tan(math.pi / 4 + lat0r / 2))
    xs = np.linspace(-side_m / 2, side_m / 2, cols)
    ys = np.linspace(side_m / 2, -side_m / 2, rows)
    X, Y = np.meshgrid(xs, ys)
    # local metres → latitude and longitude (the inverse of Area's projection) → pixels of the tiles at this zoom
    lon = lon0 + np.degrees(X / (EARTH * cos0))
    lat = np.degrees(np.arctan(np.sinh(m0 + Y / (EARTH * cos0))))
    latr = np.radians(lat)
    px = (lon + 180.0) / 360.0 * n * 256
    py = (1.0 - np.log(np.tan(latr) + 1.0 / np.cos(latr)) / math.pi) / 2.0 * n * 256
    xs_t, ys_t = [int(t["x"]) for t in tiles], [int(t["y"]) for t in tiles]
    x_min, y_min = min(xs_t), min(ys_t)
    mosaic = np.full(((max(ys_t) - y_min + 1) * 256, (max(xs_t) - x_min + 1) * 256), np.nan, dtype=np.float64)
    for t in tiles:
        try:
            a = np.asarray(Image.open(t["path"]).convert("RGB"), dtype=np.float64)
        except Exception as e:  # noqa: BLE001
            raise Invalid("dem_unreadable", str(e)[:80])
        h = a[:, :, 0] * 256.0 + a[:, :, 1] + a[:, :, 2] / 256.0 - 32768.0
        ty, tx = (int(t["y"]) - y_min) * 256, (int(t["x"]) - x_min) * 256
        mosaic[ty:ty + 256, tx:tx + 256] = h
    if np.isnan(mosaic).all():
        raise Invalid("dem_missing")
    mosaic = np.where(np.isnan(mosaic), np.nanmin(mosaic), mosaic)
    fy, fx = py - y_min * 256 - 0.5, px - x_min * 256 - 0.5
    return ndimage.map_coordinates(mosaic, [fy, fx], order=1, mode="nearest")


def relief_solid(M, Z, xs, ys):
    """
    The relief as a closed solid: the grid's surface, four walls down to z = 0 and a flat bottom, every face wound
    outwards (the surface counter-clockwise from above, the walls along the clockwise rim, the floor as a fan seen
    from below); (manifold, triangles).
    """
    import numpy as np
    rows, cols = Z.shape
    X, Y = np.meshgrid(xs, ys)
    top = np.stack([X.ravel(), Y.ravel(), Z.ravel()], 1)
    idx = np.arange(rows * cols).reshape(rows, cols)
    a, b_, c, d = idx[:-1, :-1].ravel(), idx[:-1, 1:].ravel(), idx[1:, 1:].ravel(), idx[1:, :-1].ravel()
    faces = [np.stack([a, c, b_], 1), np.stack([a, d, c], 1)]
    # the rim of the surface, clockwise from the north-west corner, and the same points on the floor
    rim = np.concatenate([idx[0, :], idx[1:, -1], idx[-1, -2::-1], idx[-2:0:-1, 0]])
    base = rows * cols
    floor = np.stack([top[rim, 0], top[rim, 1], np.zeros(len(rim))], 1)
    r0, r1 = rim, np.roll(rim, -1)
    f0, f1 = base + np.arange(len(rim)), base + (np.arange(len(rim)) + 1) % len(rim)
    faces.append(np.stack([r0, f1, f0], 1))
    faces.append(np.stack([r0, r1, f1], 1))
    centre = base + len(rim)
    faces.append(np.stack([f0, f1, np.full(len(rim), centre)], 1))
    verts = np.concatenate([top, floor, [[0.0, 0.0, 0.0]]]).astype(np.float32)
    tris = np.concatenate(faces).astype(np.uint32)
    solid = M.Manifold(M.Mesh(vert_properties=verts, tri_verts=tris))
    if solid.status() != M.Error.NoError or solid.volume() <= 0:
        solid = M.Manifold(M.Mesh(vert_properties=verts, tri_verts=tris[:, [0, 2, 1]]))
    if solid.status() != M.Error.NoError or solid.volume() <= 0:
        raise Invalid("relief_failed", str(solid.status()))
    return solid, int(len(tris))


def landscape_layers(M, area, p):
    """The 2D layers a landscape carries on its relief: water sunk in, roads and the built-up areas raised; all in plate millimetres."""
    C = M.CrossSection
    k, inner = area.k, area.inner
    window = C.square([inner, inner]).translate([-inner / 2, -inner / 2])
    water, roads, towns = C(), C(), C()
    if p.get("water", True):
        shapes = [_cs(M, rings) for rings, _ in area.polygons(lambda t: t.get("natural") == "water" or "water" in t or t.get("landuse") in ("reservoir", "basin"))]
        for pts, tags in area.lines(lambda t: t.get("waterway") in ("river", "canal")):
            shapes += _band(M, pts, max(THIN, WATERWAY_WIDTH[tags["waterway"]] * k))
        water = _union(M, shapes) ^ window
    if p.get("roads", "raised") != "none":
        # on a landscape only the roads that matter at this scale: motorways to tertiary, and the rest when the square is small
        big = {"motorway", "motorway_link", "trunk", "trunk_link", "primary", "primary_link", "secondary", "secondary_link", "tertiary"}
        keep = (lambda t: t.get("highway") in big) if area.side_m > 6000 else (lambda t: "highway" in t and t["highway"] not in ROAD_SKIP and t["highway"] not in ("path", "footway", "steps", "cycleway", "bridleway", "track", "service"))
        roads = _union(M, [s for pts, tags in area.lines(keep) for s in _band(M, pts, max(THIN, ROAD_WIDTH.get(tags["highway"], 5.0) * k * 2.0))]) ^ window
    if p.get("towns", True):
        towns = (_union(M, [_cs(M, rings) for rings, _ in area.polygons(lambda t: t.get("landuse") == "residential")]) ^ window) - roads - water
    return {"window": window, "water": water, "roads": roads, "towns": towns}


def grid_mask(cs, n, inner):
    """A 2D shape in plate millimetres as a mask over the relief's grid (row 0 the northern edge): True where it lies."""
    import numpy as np
    from PIL import Image, ImageDraw
    rings = [np.asarray(r, dtype=np.float64) for r in cs.to_polygons()]
    if not rings:
        return np.zeros((n, n), dtype=bool)
    area_of = lambda r: 0.5 * float(np.sum(r[:, 0] * np.roll(r[:, 1], -1) - np.roll(r[:, 0], -1) * r[:, 1]))      # noqa: E731
    im = Image.new("L", (n, n), 0)
    d = ImageDraw.Draw(im)
    f = (n - 1) / inner
    for r in sorted(rings, key=lambda r: -abs(area_of(r))):
        if len(r) >= 3:
            d.polygon([((x + inner / 2) * f, (inner / 2 - y) * f) for x, y in r], fill=255 if area_of(r) > 0 else 0)
    return np.asarray(im) > 127


def build_landscape(M, params, dst):
    import numpy as np
    p = params.get("params") or {}
    size, base_h = float(p.get("size", 150)), float(p.get("base_h", 4))
    frame_mm = float(p.get("frame_mm", 6.0)) if p.get("frame", True) else 0.0
    inner = size - 2 * frame_mm
    if inner < 40:
        raise Invalid("frame_too_wide")
    side_m = float(params["side_m"])
    k = inner / side_m
    ex = max(1.0, min(3.0, float(p.get("exaggeration", 1.5))))
    miniature = p.get("style") == "miniature"
    stage(dst, "reading")
    area = Area(params["osm"], params["center"], side_m, inner)
    L = landscape_layers(M, area, p)
    stage(dst, "terrain_mesh")
    n = int(max(GRID[0], min(GRID[1], inner * 2.0)))
    H = heightmap(params, n, n, side_m)
    lo, hi = float(np.percentile(H, 0.5)), float(np.percentile(H, 99.5))
    relief_m = max(0.0, hi - lo)
    Z = base_h + np.clip(H - lo, 0.0, None) * k * ex
    xs = np.linspace(-inner / 2, inner / 2, n)
    ys = np.linspace(inner / 2, -inner / 2, n)
    stage(dst, "roads")
    # the water sunk into the relief, the roads and the built-up areas raised on it: carved into the grid itself, so
    # the relief stays one surface and no boolean has to chew through its hundred thousand triangles
    ground = Z.copy()
    if not L["water"].is_empty():
        Z = np.where(grid_mask(L["water"], n, inner), ground - WATER_SINK, Z)
    if not L["towns"].is_empty():
        Z = np.where(grid_mask(L["towns"], n, inner), ground + TOWN_LIFT, Z)
    if not L["roads"].is_empty():
        Z = np.where(grid_mask(L["roads"], n, inner), ground + (-ROAD_LIFT if (params.get("params") or {}).get("roads") == "sunk" else ROAD_LIFT), Z)
    whole, tris = relief_solid(M, Z, xs, ys)
    frame, name, written = frame_and_name(M, p, inner, size, base_h, params.get("font"), miniature)
    solids = [whole] + [x for x in (frame, name) if x is not None]
    stage(dst, "writing")
    model = M.Manifold.batch_boolean(solids, M.OpType.Add) if len(solids) > 1 else whole
    colors = {**COLORS, **{kk: v for kk, v in (p.get("colors") or {}).items() if isinstance(v, str)}}
    far = 9999.0
    changes = [{"z": round(base_h, 2), "hex": colors["terrain"]}]
    regions = [{"x0": -far, "y0": -far, "x1": far, "y1": far, "z0": round(base_h + 0.01, 2), "color": colors["terrain"]},
               {"x0": -far, "y0": -far, "x1": far, "y1": far, "z0": -1.0, "color": colors["base"]}]
    warn = []
    if relief_m * k * ex < 1.5:
        warn.append("flat")
    notes = {"type": "landscape", "scale": scale_text(side_m, inner), "scale_n": int(round(side_m * 1000.0 / inner)), "osm_date": area.date,
             "relief_m": int(round(relief_m)), "low_m": int(round(lo)), "high_m": int(round(hi)), "relief_mm": round(relief_m * k * ex, 2), "grid": n, "name": written,
             "water": not L["water"].is_empty(), "roads_m": 0, "buildings": 0, "top_mm": round(float(Z.max()), 2),
             "color_changes": changes, "regions": regions, "colors": colors, "warnings": warn, "attribution": "Data © OpenStreetMap contributors (ODbL) · Mapzen / AWS Open Data"}
    return model, notes


def shaded(params, cols, rows, side_m, colors):
    """The relief from above, lit from the north-west, in the colour of the terrain: the picture of a landscape."""
    import numpy as np
    from PIL import Image
    H = heightmap(params, cols, rows, side_m)
    cell_m = side_m / cols
    gy, gx = np.gradient(H, cell_m)
    nx, ny, nz = -gx * 2.0, gy * 2.0, np.ones_like(H)          # the slopes twice as steep, so a gentle country still reads
    norm = np.sqrt(nx * nx + ny * ny + nz * nz)
    light = (nx * -0.5 + ny * 0.5 + nz * 0.7071) / norm
    shade = np.clip(0.45 + 0.55 * np.clip(light, 0, 1), 0.3, 1.1)
    r, g, b = (int(colors["terrain"].lstrip("#")[i:i + 2], 16) for i in (0, 2, 4))
    rgb = np.stack([np.clip(r * shade, 0, 255), np.clip(g * shade, 0, 255), np.clip(b * shade, 0, 255)], 2).astype(np.uint8)
    return Image.fromarray(rgb), float(np.percentile(H, 99.5) - np.percentile(H, 0.5))


# ── output ────────────────────────────────────────────────────────────────────────────────────────────────────

def write_stl(model, path):
    import numpy as np
    mesh = model.to_mesh()
    verts = np.asarray(mesh.vert_properties, dtype=np.float32)[:, :3]
    tris = np.asarray(mesh.tri_verts, dtype=np.int64)
    tri = verts[tris]
    normals = np.cross(tri[:, 1] - tri[:, 0], tri[:, 2] - tri[:, 0])
    lens = np.linalg.norm(normals, axis=1, keepdims=True)
    normals = np.divide(normals, lens, out=np.zeros_like(normals), where=lens > 0)
    rec = np.zeros(len(tris), dtype=[("n", "<f4", 3), ("v", "<f4", (3, 3)), ("a", "<u2")])
    rec["n"], rec["v"] = normals, tri
    with open(path, "wb") as fh:
        fh.write(b"matplace map".ljust(80, b" "))
        fh.write(np.uint32(len(tris)).tobytes())
        fh.write(rec.tobytes())
    return int(len(tris))


def build(params, dst):
    import manifold3d as M
    p = params.get("params") or {}
    t = time.time()
    model, notes = build_landscape(M, params, dst) if p.get("type") == "landscape" else build_city(M, params, dst)
    if model.is_empty() or model.status() != M.Error.NoError or model.volume() <= 0:
        raise Invalid("empty_result")
    x0, y0, z0, x1, y1, z1 = model.bounding_box()
    model = model.translate([-x0, -y0, -z0])
    tris = write_stl(model, dst)
    stage(dst, "done")
    notes["seconds"] = round(time.time() - t, 2)
    return {"ok": True, "bbox": {"x": round(x1 - x0, 2), "y": round(y1 - y0, 2), "z": round(z1 - z0, 2)}, "volume_mm3": round(model.volume(), 1),
            "area_mm2": round(model.surface_area(), 1), "triangles": tris, "notes": notes}


def preview(params, dst):
    """The area from above as the plate will show it: water, green, railways, roads, buildings, the frame, a scale bar."""
    import manifold3d as M
    from PIL import Image, ImageDraw, ImageFont
    p = params.get("params") or {}
    size, base_h = float(p.get("size", 150)), float(p.get("base_h", 4))
    frame_mm = float(p.get("frame_mm", 6.0)) if p.get("frame", True) else 0.0
    inner = size - 2 * frame_mm
    if inner < 40:
        raise Invalid("frame_too_wide")
    area = Area(params["osm"], params["center"], params["side_m"], inner)
    landscape = p.get("type") == "landscape"
    L = landscape_layers(M, area, p) if landscape else city_layers(M, area, p)
    colors = {**COLORS, **{k: v for k, v in (p.get("colors") or {}).items() if isinstance(v, str)}}
    px = 600
    s = px / size                                          # pixels per mm, the frame included
    im = Image.new("RGB", (px, px), colors["base"])
    relief_m = 0.0
    if landscape:
        shade, relief_m = shaded(params, 300, 300, float(params["side_m"]), colors)
        win = int(round(inner * s))
        im.paste(shade.resize((win, win), Image.BILINEAR), ((px - win) // 2, (px - win) // 2))
    d = ImageDraw.Draw(im)

    def xy(pt):
        return (px / 2 + pt[0] * s, px / 2 - pt[1] * s)

    def fill(cs, color):
        import numpy as np
        rings = [np.asarray(r, dtype=np.float64) for r in cs.to_polygons()]
        area_of = lambda r: 0.5 * float(np.sum(r[:, 0] * np.roll(r[:, 1], -1) - np.roll(r[:, 0], -1) * r[:, 1]))      # noqa: E731
        under = im.copy() if landscape else None            # a hole in a shape shows what lies under it
        for r in sorted(rings, key=lambda r: -abs(area_of(r))):
            if len(r) < 3:
                continue
            if area_of(r) > 0 or under is None:
                d.polygon([xy(pt) for pt in r], fill=color if area_of(r) > 0 else colors["base"])
            else:
                mask = Image.new("L", im.size, 0)
                ImageDraw.Draw(mask).polygon([xy(pt) for pt in r], fill=255)
                im.paste(under, (0, 0), mask)

    def shade(hexes, f):
        r, g, b = (int(hexes.lstrip("#")[i:i + 2], 16) for i in (0, 2, 4))
        return "#%02x%02x%02x" % tuple(max(0, min(255, int(v * f))) for v in (r, g, b))

    if landscape:
        fill(L["towns"], shade(colors["terrain"], 1.35))
        fill(L["water"], colors["water"])
        fill(L["roads"], colors["roads"])
    else:
        fill(L["green"], colors["green"])
        fill(L["water"], colors["water"])
        fill(L["rails"], shade(colors["base"], 0.75))
        fill(L["roads"], colors["roads"])
        for h_mm, shape in L["buildings"][::-1]:
            fill(shape, colors["buildings"])
    if frame_mm > 0:
        d.rectangle([0, 0, px - 1, px - 1], outline=colors["roads"], width=max(2, int(frame_mm * s)))
    # a scale bar in the lower left: a round number of metres that is about a fifth of the window
    metres = area.side_m / 5.0
    step = 10 ** int(math.log10(metres))
    metres = max(step, round(metres / step) * step)
    bar = metres * area.k * s
    x, y = frame_mm * s + 14, px - frame_mm * s - 18
    d.rectangle([x, y, x + bar, y + 5], fill=colors["roads"], outline=colors["base"])
    try:
        font = ImageFont.truetype(params.get("font") or "", 14)
    except Exception:  # noqa: BLE001
        font = ImageFont.load_default()
    label = ("%d km" % (metres / 1000)) if metres >= 1000 else ("%d m" % metres)
    d.text((x, y - 18), label, fill=colors["roads"], font=font)
    im.save(dst, "PNG", optimize=True)
    return {"ok": True, "width": px, "height": px, "buildings": L.get("count", 0), "roads_m": int(round(L.get("roads_m", 0))), "relief_m": int(round(relief_m)), "scale": scale_text(area.side_m, inner)}


def main(argv):
    if len(argv) < 4 or argv[1] not in ("build", "preview"):
        out({"ok": False, "code": "usage", "error": "map_tool.py build|preview <params.json> <out>"})
    try:
        with open(argv[2], encoding="utf-8") as fh:
            params = json.load(fh)
        out(build(params, argv[3]) if argv[1] == "build" else preview(params, argv[3]))
    except Invalid as e:
        out({"ok": False, "code": e.code, "error": str(e)})
    except SystemExit:
        raise
    except Exception as e:  # noqa: BLE001
        out({"ok": False, "code": "failed", "error": str(e)[:300]})


if __name__ == "__main__":
    main(sys.argv)
