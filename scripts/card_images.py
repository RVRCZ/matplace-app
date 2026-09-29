#!/usr/bin/env python3
"""
Pictures for the tool cards (public/img/tools/<key>-800.jpg, -800.webp, -480.webp).

For every tool that builds a model from numbers, the model is built with engines/python/param_tool.py exactly as the
tool would build it, rendered, and handed to Gemini together with a description of the scene: the picture on the card
then shows the shape the customer really gets. Tools that start from the customer's own file or photo have a scene only.

  python scripts/card_images.py                 every tool that has no generated picture yet
  python scripts/card_images.py cap lightbox    these tools (again)
  python scripts/card_images.py --renders       only the renders, nothing is sent anywhere

The key is read from .env (GEMINI_API_KEY). Results and renders are kept in storage/app/card_images/ for review;
nothing is published until `--publish <key…>` copies the reviewed pictures to public/img/tools/.
"""
import base64
import io
import json
import os
import subprocess
import sys
import time

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ENGINE = os.path.join(ROOT, "engines", "python")
WORK = os.path.join(ROOT, "storage", "app", "card_images")
PUBLIC = os.path.join(ROOT, "public", "img", "tools")
DEJAVU = os.path.join(ROOT, "vendor", "dompdf", "dompdf", "lib", "fonts", "DejaVuSans-Bold.ttf")
SCRIPT = os.path.join(ROOT, "engines", "fonts", "Pacifico-Regular.ttf")
MODEL = "gemini-2.5-flash-image"

STYLE = (
    "Studio product photograph of a 3D printed object. High quality FDM print: smooth matte surface, clean sharp edges, "
    "layer lines barely visible and only on close inspection, no stringing, no blobs, no rough texture. Light wooden or "
    "light stone desk, a blurred 3D printer and a green plant far in the background, soft daylight from the left, shallow "
    "depth of field. Realistic, no people, no hands, no captions, no logos, no watermark, no brand names or lettering on the printer or anywhere in the background. Landscape format, aspect ratio "
    "3:2, the object centred, close to the camera: it fills about half of the width of the picture."
)
FROM_RENDER = (
    "The attached picture is a plain render of the real object. Keep its shape, its proportions and every detail exactly "
    "as rendered: do not add, remove or reshape anything on the object. Replace only the material, the light and the "
    "surroundings."
)

# key → (scene, [(kind, params, part, view, colour)…] models to render; empty = a scene without a model)
CARDS = {
    # the picture of the homepage (public/img/home/hero-*): what the tools make, together on one desk
    "hero": ("The render shows exactly four things made with a 3D printer, from left to right: a vase with a spiral twist, a drawer organizer with six compartments, a phone stand, a pendant in the shape of a handwritten name with a heart. Show exactly these four, each one once, nothing repeated and nothing added, standing close together as one group in the front of the desk, photographed from close by so that the group fills the whole width of the picture. The vase is warm orange and holds a few dried flowers. The organizer is dark navy blue and holds pens and paper clips. The phone stand is light grey and stands empty: there is no phone and no second stand anywhere in the picture. The pendant is pink, lies flat in front with a metal key ring in its eyelet, and reads Emma in joined handwriting followed by a heart, exactly as rendered. Keep the viewpoint of the render, looking slightly down on the desk.",
             [("vase", {"style": "twist", "profile": "neck", "height": 170, "top_d": 62, "bottom_d": 54, "ribs": 20, "flute": 20, "twist": 200}, "all", "use", "orange"),
              ("organizer", {"width": 150, "depth": 100, "height": 40, "rows": 2, "cols": 3}, "all", "use", "navy"),
              ("phone_stand", {"style": "desk"}, "all", "use", "grey"),
              ("sign", {"style": "name", "typeface": "script", "text_height": 28, "thickness": 5, "relief": 2, "keyring": True, "font": SCRIPT, "lines": ["Emma ♥"]}, "all", "use", "pink")]),
    "calc": ("Two copies of the same small boat side by side on a printer build plate, same size and pose. The left one is a computer wireframe: only thin glowing blue lines of a polygon mesh, see-through, no surface. The right one is the finished print in solid grey plastic.", []),
    "repair": ("A computer monitor showing a grey 3D model of a classical bust, with holes in the mesh and flipped patches highlighted in red. In front of the monitor the same bust printed flawlessly in grey plastic. The printed bust is intact, nothing is broken.", []),
    "check": ("A grey printed machine bracket with a round bore and four mounting holes. A digital caliper measures the bore. Clean, technical look.", []),
    "mold": ("The objects are the two halves of a printed casting mould lying open with their flat inner faces up, and the chess pawn that is cast in it. Each half holds one half of the pawn-shaped hollow and half of the pouring funnel at its end. One half carries three small half-ball keys that stand out of its face, the other half has three matching round dimples at the same places. Show the halves in grey plastic and the pawn as a cream coloured resin cast standing in front of them. The hollow in the mould has exactly the shape and the size of the pawn. Keep the viewpoint of the render, looking down from above, so that the hollows, the three keys and the three dimples are clearly seen. Count them in the render and keep them where they are: one half has three small half balls standing up from its face, the other half has three round holes, drawn dark in the render. The holes stay holes: dark, sunk into the face. The half with the half balls has no holes and no thin pins: only three low round domes, like halves of a marble. Add nothing of your own. The pawn stands to the right of the mould, not in front of it.",
             [("@mold", {"wall": 8, "keys": True, "funnel": True}, "all", "print", "grey"), ("@pawn", {}, "all", "print", "white")]),
    "figure": ("A grey printed bust of a curly-haired man in a hoodie on a round pedestal. A short name is engraved on the front of the pedestal. A printed photograph of the same man stands beside it.", []),
    "relief": ("Two versions of the same portrait of a smiling child. Left: a thin white lithophane plate with a narrow frame, lit from behind, the picture glowing in warm tones. Right: the same portrait as a grey printed relief plate, unlit.", []),
    "organizer": ("The object is a grey printed drawer insert: one piece with a regular grid of equal compartments. Show it lying in an open desk drawer, holding pens, paper clips and a cable, seen from above at an angle.",
                  [("organizer", {"width": 200, "depth": 120, "height": 40, "rows": 2, "cols": 3}, "all", "use", "grey")]),
    "modular": ("The objects are separate open-top printed bins of different sizes that fill a drawer without gaps. Give each bin one plain colour: yellow, blue, green, red, orange, grey. Show them in an open drawer holding chargers, cables, screwdrivers, screws and tape.",
                [("modular", {"inner_w": 300, "inner_d": 200, "height": 45, "cols": 3, "rows": 2,
                              "bins": [{"x": 0, "y": 0, "w": 1, "h": 1, "color": "yellow"}, {"x": 1, "y": 0, "w": 2, "h": 1, "color": "blue"},
                                       {"x": 0, "y": 1, "w": 1, "h": 1, "color": "grey"}, {"x": 1, "y": 1, "w": 1, "h": 1, "color": "green"}, {"x": 2, "y": 1, "w": 1, "h": 1, "color": "red"}]}, "all", "use", "grey")]),
    "box": ("The object is a grey printed box with its lift-off lid lying beside it. A slot for a cable runs from the top rim down one side wall. Show the lid beside the box and a white cable laid through the slot into the box.",
            [("box", {"inner_w": 120, "inner_d": 70, "inner_h": 50, "lid": True, "cable_slot": True, "cable_d": 10}, "all", "use", "grey")]),
    "phone_stand": ("The object is a grey printed phone stand. Show a smartphone resting on it on a desk, a charging cable leaving downwards.",
                    [("phone_stand", {"style": "desk"}, "all", "use", "grey")]),
    "holder": ("The object is a grey printed wall hook with two screw holes in its back plate. Show it screwed to a vertical wooden post with black headphones hanging on it.",
               [("holder", {"style": "hook", "obj_w": 40, "obj_d": 45, "hook_h": 21, "bend": 0, "edge": 0, "wall": 4, "mount": True}, "all", "use", "grey")]),
    "cap": ("The objects are printed plugs and caps: a round push-on cap, a rectangular plug with ribs, a hexagonal plug with ribs, a round cap with a dome top, a round screw cap. Show them in grey and black on a desk, with a square aluminium profile behind them.",
            [("cap", {"style": "push", "shape": "round", "size_a": 40, "height": 14, "grip": True}, "all", "use", "grey"),
             ("cap", {"style": "plug", "shape": "rect", "size_a": 40, "size_b": 30, "height": 14}, "all", "print", "black"),
             ("cap", {"style": "plug", "shape": "hex", "size_a": 32, "height": 14}, "all", "print", "grey"),
             ("cap", {"style": "push", "shape": "round", "head": "dome", "size_a": 30, "height": 12}, "all", "print", "grey"),
             ("cap", {"style": "thread", "shape": "round", "size_a": 28, "height": 14, "pitch": 3, "grip": True}, "all", "print", "black")]),
    "cable_holder": ("The object is a grey printed block with channels for cables. Show it on the edge of a desk with three cables with different plugs clicked into the channels.",
                     [("cable_holder", {"count": 4, "cable": 6}, "all", "use", "grey")]),
    "vase": ("The objects are a printed vase and a printed planter with its saucer. Show the vase in white holding dried flowers and the planter in green with a leafy plant.",
             [("vase", {"style": "twist", "profile": "neck", "height": 180, "top_d": 62, "bottom_d": 54, "ribs": 20, "flute": 20, "twist": 200}, "all", "use", "white"),
              ("vase", {"purpose": "pot", "style": "ribs", "profile": "cone", "height": 120, "top_d": 130, "bottom_d": 100, "ribs": 16, "flute": 10, "saucer": True, "drainage": True}, "all", "use", "green")]),
    "gifts": ("The object is a printed pendant in the shape of a handwritten name followed by a heart, with a small eyelet on the left. Show it in pink with a metal key ring through the eyelet, a gift box with a ribbon behind it.",
              [("sign", {"style": "name", "typeface": "script", "text_height": 14, "thickness": 3, "relief": 1, "keyring": True, "font": SCRIPT, "lines": ["Emma ♥"]}, "all", "use", "pink")]),
    "sign": ("The objects are a door sign with raised letters and a raised rim, and a small oval tag with an eyelet. Show them close up, lying on the desk and seen from the front above, the letters in white on dark grey plates. Keep the words exactly as rendered.",
             [("sign", {"style": "emboss", "shape": "rounded", "text_height": 20, "thickness": 3, "relief": 1.4, "margin": 8, "radius": 8, "border": True, "font": DEJAVU, "lines": ["Workshop"]}, "all", "use", "grey"),
              ("sign", {"style": "emboss", "shape": "oval", "text_height": 9, "thickness": 3, "relief": 1, "margin": 5, "keyring": True, "border": True, "font": DEJAVU, "lines": ["Alex"]}, "all", "use", "grey")]),
    "qr": ("The object is a square printed plate with a raised QR code, no lettering on it. Show it close up on a shop counter, standing upright and slightly leaning back in a small plain holder with a slot. The raised code is dark grey, the plate white. No text anywhere on the plate.",
           [("qr", {"size": 70, "stand": False, "url": "https://matplace.com", "label": "", "font": DEJAVU}, "all", "use", "white")]),
    "logo": ("The object is a printed round medallion: a flat disc with a raised emblem of a mountain with a snow cap and a sun. Show it in grey, standing upright on its edge and facing the camera, leaning against a small block, so that the emblem is clearly readable.",
             [("logo", {"mode": "relief", "shape": "circle", "width": 80, "artwork_path": "@mountains", "font": DEJAVU}, "all", "use", "grey")]),
    "cutter": ("The objects are a cookie cutter, a thin standing wall with a flange and empty inside, and beside it a flat stamp plate of the same outline with raised eyes, nose and mouth. Show both in grey plastic on a floured wooden board. In front of them lies one piece of pale dough cut to the same bear outline; the eyes, the nose and the mouth are pressed into the dough as shallow dents of the same dough colour. Nothing grey on the dough. A rolling pin at the back.",
               [("cutter", {"width": 80, "height": 18, "wall": 1.0, "edge": "sharp", "stamp": True, "artwork_path": "@bear", "font": DEJAVU}, "all", "print", "grey")]),
    "stamp": ("The objects are the two parts of a printed stamp: a square plate with a raised motif of three leaves, and a knob handle. Show them glued together into one stamp, the handle on the back of the plate, in grey plastic, lying on its side so that the raised leaves are visible. Next to it a slab of pale clay with the three leaves pressed into it.",
              [("stamp", {"handle": "knob", "mode": "raised", "artwork_path": "@leaf", "font": DEJAVU}, "all", "use", "grey")]),
    "stencil": ("The object is a thin flat printed stencil with a cut-out mountain motif. Show it in grey, lying on the desk to the right of a wooden board. On the board there is a painting made with this stencil: a solid green mountain with a green sun, the same shapes as the holes of the stencil, on bare wood; the wood around the mountain is clean apart from a faint green mist. One stencil only. A plain spray can beside it.",
                [("stencil", {"width": 140, "artwork_path": "@mountains", "font": DEJAVU}, "all", "use", "grey")]),
    "lightbox": ("The object is a round illuminated sign standing on a flat foot. The render shows its four parts pulled apart along the depth: back plate, ring-shaped body, diffuser disc, front plate with the cut-out motif. Show it put together and switched on: a dark front plate, warm light shining through the cut-out motif from a white diffuser behind it, a thin cable leaving low at the side.",
                 [("lightbox", {"shape": "round", "width": 160, "depth": 35, "artwork_path": "@mountains", "font": DEJAVU}, "use", "use", "dark")]),
}

# how high the camera of the render stands (the default looks from the front above)
HEIGHT = {"mold": 1.5, "hero": 0.7}
COLOURS = {"orange": (0.85, 0.42, 0.2), "navy": (0.16, 0.2, 0.36), "grey": (0.62, 0.64, 0.68), "black": (0.22, 0.22, 0.24), "white": (0.92, 0.92, 0.9), "green": (0.45, 0.62, 0.45), "pink": (0.93, 0.62, 0.68), "dark": (0.3, 0.31, 0.34)}


def env_key():
    for line in io.open(os.path.join(ROOT, ".env"), encoding="utf-8", errors="ignore"):
        if line.startswith("GEMINI_API_KEY="):
            return line.split("=", 1)[1].strip().strip('"').strip("'")
    return ""


def drawings():
    """The three motifs a customer would upload, drawn here so the run needs no outside files."""
    from PIL import Image, ImageDraw
    paths = {}
    im = Image.new("L", (800, 800), 255)
    d = ImageDraw.Draw(im)
    for s in ((90, 60, 310, 280), (490, 60, 710, 280), (120, 150, 680, 710)):
        d.ellipse(s, fill=0)
    for s in ((150, 120, 250, 220), (550, 120, 650, 220), (280, 330, 330, 380), (470, 330, 520, 380), (370, 450, 430, 495)):
        d.ellipse(s, fill=255)
    d.ellipse((300, 430, 500, 600), outline=255, width=12)
    d.arc((350, 480, 450, 570), 20, 160, fill=255, width=10)
    paths["@bear"] = os.path.join(WORK, "art_bear.png")
    im.save(paths["@bear"])

    im = Image.new("L", (900, 600), 255)
    d = ImageDraw.Draw(im)
    d.polygon([(40, 560), (300, 170), (400, 330), (520, 110), (860, 560)], fill=0)
    d.polygon([(520, 110), (470, 250), (530, 215), (575, 300), (610, 235)], fill=255)      # snow on the summit
    d.ellipse((650, 50, 790, 190), fill=0)
    paths["@mountains"] = os.path.join(WORK, "art_mountains.png")
    im.save(paths["@mountains"])

    import math
    im = Image.new("L", (600, 600), 255)
    d = ImageDraw.Draw(im)
    for turn in (-38, 0, 38):                                      # three leaves on one stalk
        t = math.radians(turn)
        pts = []
        for i in range(61):
            u = i / 60.0
            along = 60 + 330 * u
            half = 95 * math.sin(math.pi * u) ** 0.8
            pts.append((half, along))
        outline = pts + [(-x, y) for x, y in reversed(pts)]
        d.polygon([(300 + x * math.cos(t) + y * math.sin(t), 520 + x * math.sin(t) - y * math.cos(t)) for x, y in outline], fill=0)
        d.line([(300 + 70 * math.sin(t), 520 - 70 * math.cos(t)), (300 + 360 * math.sin(t), 520 - 360 * math.cos(t))], fill=255, width=9)
    d.line((300, 585, 300, 470), fill=0, width=18)
    paths["@leaf"] = os.path.join(WORK, "art_leaf.png")
    im.save(paths["@leaf"])
    return paths


def pawn():
    """A chess pawn, 60 mm tall: what the mould on the card is made for."""
    import manifold3d as M
    import numpy as np
    stl = os.path.join(WORK, "model_pawn.stl")
    profile = [(0, 0), (15, 0), (15, 3), (13, 5), (12, 8), (8, 12), (6, 22), (5.2, 34), (9, 36), (9, 38), (5.5, 40)]
    import math
    profile += [(9.5 * math.cos(t), 49 + 9.5 * math.sin(t)) for t in np.linspace(-1.1, math.pi / 2, 24)]
    profile[-1] = (0, 58.5)
    solid = M.Manifold.revolve(M.CrossSection([profile]), 96)
    mesh = solid.to_mesh()
    import trimesh
    trimesh.Trimesh(vertices=np.asarray(mesh.vert_properties)[:, :3], faces=np.asarray(mesh.tri_verts), process=True).export(stl)
    return stl


def dimples(stl):
    """
    The sockets of the keys are taken out of the mould's STL into <stl>.dark.stl, which the render paints dark:
    in a plain shaded picture a dimple and a bump look the same, and the picture must show which half has which.
    """
    import numpy as np
    import trimesh
    m = trimesh.load(stl, process=True)
    up = m.face_normals[:, 2] > 0.999
    zs = np.round(m.triangles_center[up][:, 2], 2)
    levels, index = np.unique(zs, return_inverse=True)
    face = float(levels[np.argmax(np.bincount(index, weights=m.area_faces[up]))])      # the parting face: the largest flat top
    lo, hi = m.bounds
    c = m.triangles_center
    n = m.face_normals
    outside = (np.abs(c[:, 0] - lo[0]) < 0.01) | (np.abs(c[:, 0] - hi[0]) < 0.01) | (np.abs(c[:, 1] - lo[1]) < 0.01) | (np.abs(c[:, 1] - hi[1]) < 0.01) | (c[:, 2] < 0.01)
    flat = (n[:, 2] > 0.999) & (np.abs(c[:, 2] - face) < 0.01)
    inner = np.where(~outside & ~flat)[0]
    keep = np.zeros(len(m.faces), dtype=bool)
    keep[inner] = True
    adjacency = m.face_adjacency[keep[m.face_adjacency].all(axis=1)]
    dark = np.zeros(len(m.faces), dtype=bool)
    for group in trimesh.graph.connected_components(adjacency, nodes=inner):
        top = m.vertices[m.faces[group]][:, :, 2].max()
        if top <= face + 0.01 and m.area_faces[group].sum() < 400:              # below the face and small: a socket
            dark[group] = True
    if not dark.any():
        raise RuntimeError("mold: no sockets found")
    trimesh.Trimesh(vertices=m.vertices, faces=m.faces[dark], process=False).export(stl + ".dark.stl")
    trimesh.Trimesh(vertices=m.vertices, faces=m.faces[~dark], process=False).export(stl)


def build(kind, params, part, view, art):
    if kind == "@pawn":
        return pawn()
    if kind == "@mold":
        stl = os.path.join(WORK, "model_mold.stl")
        r = subprocess.run([sys.executable, os.path.join(ENGINE, "mold_tool.py"), pawn(), stl, json.dumps(params)], capture_output=True, text=True, cwd=ENGINE)
        out = json.loads((r.stdout.strip().splitlines() or ["{}"])[-1])
        if not out.get("ok") or not out.get("keys"):
            raise RuntimeError("mold: %s" % out)
        dimples(stl)
        return stl
    params = dict(params)
    if params.get("artwork_path") in art:
        params["artwork_path"] = art[params["artwork_path"]]
    stl = os.path.join(WORK, "model_%s_%d.stl" % (kind, abs(hash(json.dumps(params, sort_keys=True))) % 10 ** 8))
    r = subprocess.run([sys.executable, os.path.join(ENGINE, "param_tool.py"), kind, stl, json.dumps(params), part, view], capture_output=True, text=True, cwd=ENGINE)
    out = json.loads((r.stdout.strip().splitlines() or ["{}"])[-1])
    if not out.get("ok"):
        raise RuntimeError("%s: %s" % (kind, out))
    return stl


def render(models, png, size=(1200, 800), height=0.62):
    """All models side by side on a light floor, shaded, seen from the front above (VTK, off screen)."""
    import vtk
    ren = vtk.vtkRenderer()
    ren.SetBackground(0.97, 0.97, 0.96)
    x = 0.0
    top = 0.0
    for stl, colour in models:
        reader = vtk.vtkSTLReader()
        reader.SetFileName(stl)
        reader.Update()
        b = reader.GetOutput().GetBounds()
        move = vtk.vtkTransform()
        move.Translate(x - b[0], -(b[2] + b[3]) / 2, -b[4])
        moved = vtk.vtkTransformPolyDataFilter()
        moved.SetTransform(move)
        moved.SetInputConnection(reader.GetOutputPort())
        normals = vtk.vtkPolyDataNormals()
        normals.SetInputConnection(moved.GetOutputPort())
        normals.SetFeatureAngle(35)
        normals.SplittingOn()
        mapper = vtk.vtkPolyDataMapper()
        mapper.SetInputConnection(normals.GetOutputPort())
        actor = vtk.vtkActor()
        actor.SetMapper(mapper)
        p = actor.GetProperty()
        p.SetColor(*COLOURS[colour])
        p.SetAmbient(0.28)
        p.SetDiffuse(0.75)
        p.SetSpecular(0.08)
        ren.AddActor(actor)
        if os.path.isfile(stl + ".dark.stl"):
            extra = vtk.vtkSTLReader()
            extra.SetFileName(stl + ".dark.stl")
            shifted = vtk.vtkTransformPolyDataFilter()
            shifted.SetTransform(move)
            shifted.SetInputConnection(extra.GetOutputPort())
            shade = vtk.vtkPolyDataMapper()
            shade.SetInputConnection(shifted.GetOutputPort())
            hollow = vtk.vtkActor()
            hollow.SetMapper(shade)
            hollow.GetProperty().SetColor(0.12, 0.12, 0.14)
            hollow.GetProperty().SetAmbient(0.6)
            hollow.GetProperty().SetDiffuse(0.3)
            ren.AddActor(hollow)
        x += (b[1] - b[0]) * 1.18 + 6
        top = max(top, b[5] - b[4])
    win = vtk.vtkRenderWindow()
    win.SetOffScreenRendering(1)
    win.SetMultiSamples(8)
    win.AddRenderer(ren)
    win.SetSize(*size)
    cam = ren.GetActiveCamera()
    ren.ResetCamera()
    fx, fy, fz = cam.GetFocalPoint()
    dist = cam.GetDistance()
    cam.SetViewUp(0, 0, 1)
    cam.SetPosition(fx + dist * 0.35, fy - dist * 0.8, fz + dist * height)
    ren.ResetCamera()
    cam.Zoom(1.25)
    ren.ResetCameraClippingRange()
    win.Render()
    grab = vtk.vtkWindowToImageFilter()
    grab.SetInput(win)
    grab.Update()
    writer = vtk.vtkPNGWriter()
    writer.SetFileName(png)
    writer.SetInputConnection(grab.GetOutputPort())
    writer.Write()


def generate(key, prompt, image):
    import urllib.request
    import urllib.error
    parts = [{"text": prompt}]
    if image:
        parts.append({"inline_data": {"mime_type": "image/png", "data": base64.b64encode(open(image, "rb").read()).decode()}})
    body = json.dumps({"contents": [{"parts": parts}], "generationConfig": {"responseModalities": ["TEXT", "IMAGE"], "imageConfig": {"aspectRatio": "3:2"}}}).encode()
    url = "https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent" % MODEL
    last = ""
    for attempt in range(3):
        req = urllib.request.Request(url, data=body, headers={"x-goog-api-key": key, "Content-Type": "application/json"})
        try:
            data = json.loads(urllib.request.urlopen(req, timeout=180).read())
        except urllib.error.HTTPError as e:
            last = "HTTP %s: %s" % (e.code, e.read()[:300].decode("utf-8", "ignore"))
            if e.code in (429, 500, 502, 503, 504):
                time.sleep(6 * (attempt + 1))
                continue
            raise RuntimeError(last)
        for p in (data.get("candidates") or [{}])[0].get("content", {}).get("parts", []):
            inline = p.get("inlineData") or p.get("inline_data")
            if inline and inline.get("data"):
                return base64.b64decode(inline["data"])
        last = "no picture in the answer: %s" % json.dumps(data)[:300]
        time.sleep(3)
    raise RuntimeError(last)


def files_of(key):
    """What a picture is saved as, and where it is published."""
    if key == "hero":
        return ["hero-640.webp", "hero-1024.webp", "hero-1536.webp", "hero-1024.jpg"], os.path.join(ROOT, "public", "img", "home")
    return [key + "-800.jpg", key + "-800.webp", key + "-480.webp"], PUBLIC


def save_sizes(raw, folder, key):
    """3:2, cut from the middle when the model answered in another shape; 800 px jpg + webp and 480 px webp."""
    from PIL import Image
    im = Image.open(io.BytesIO(raw)).convert("RGB")
    w, h = im.size
    if w / h > 1.5:
        nw = int(h * 1.5)
        im = im.crop(((w - nw) // 2, 0, (w - nw) // 2 + nw, h))
    else:
        nh = int(w / 1.5)
        im = im.crop((0, (h - nh) // 2, w, (h - nh) // 2 + nh))
    if key == "hero":
        for width in (640, 1024, 1536):
            im.resize((width, width * 2 // 3), Image.LANCZOS).save(os.path.join(folder, "hero-%d.webp" % width), quality=84, method=6)
        im.resize((1024, 683), Image.LANCZOS).save(os.path.join(folder, "hero-1024.jpg"), quality=86, optimize=True, progressive=True)
        return
    big = im.resize((800, 533), Image.LANCZOS)
    big.save(os.path.join(folder, key + "-800.jpg"), quality=86, optimize=True, progressive=True)
    big.save(os.path.join(folder, key + "-800.webp"), quality=82, method=6)
    im.resize((480, 320), Image.LANCZOS).save(os.path.join(folder, key + "-480.webp"), quality=80, method=6)


def main(argv):
    os.makedirs(WORK, exist_ok=True)
    args = [a for a in argv[1:] if not a.startswith("--")]
    flags = {a for a in argv[1:] if a.startswith("--")}
    if "--publish" in flags:
        import shutil
        for key in args:
            names, target = files_of(key)
            for name in names:
                shutil.copyfile(os.path.join(WORK, name), os.path.join(target, name))
            print("published", key)
        return
    keys = args or [k for k in CARDS if not os.path.isfile(os.path.join(WORK, files_of(k)[0][0]))]
    unknown = [k for k in keys if k not in CARDS]
    if unknown:
        sys.exit("unknown: " + ", ".join(unknown))
    api = env_key()
    if not api and "--renders" not in flags:
        sys.exit("GEMINI_API_KEY is not set in .env")
    art = drawings()
    for key in keys:
        scene, models = CARDS[key]
        png = None
        try:
            if models:
                png = os.path.join(WORK, key + "-render.png")
                render([(build(kind, params, part, view, art), colour) for kind, params, part, view, colour in models], png, height=HEIGHT.get(key, 0.62))
            if "--renders" in flags:
                print(key, "render" if png else "scene only")
                continue
            prompt = STYLE + "\n\n" + (FROM_RENDER + "\n\n" if png else "") + scene
            save_sizes(generate(api, prompt, png), WORK, key)
            print(key, "ok")
        except Exception as e:  # noqa: BLE001 - one failed card must not stop the others
            print(key, "FAILED", str(e)[:300])
        time.sleep(2)


if __name__ == "__main__":
    main(sys.argv)
