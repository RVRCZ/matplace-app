"""
The portrait mode of papel picado (creative_kinds.papel, treatment "portrait"): a photo of a face as a picture of two
tones that lies on a backing plate, so nothing in it has to hold on to anything.

  subject()   who is in the photo: the picture's own transparency, else rembg (u2net) when it is installed
  lay()       the photo set into the window: its lower part trimmed, the person cut out, sized, turned and moved
  split()     dark and light: a threshold on the picture sharpened against its own blur, so a face keeps its lines
  ground()    what is round the person, printed dark, with a light line where the person is dark at the edge
  scatter()   where the border's cut-outs go on that ground
  preview()   the result as a small PNG for the page

Everything works on the grid of creative_kinds.PAPEL_PX cells, the one the cut-out mode reads a photo on.
"""
import base64
import hashlib
import io
import math
import os
import sys
import tempfile
import time

HALO_MM = 1.2               # the light line between a dark head of hair and the dark ground
LINE_MM = 0.9               # nothing of the picture is finer than about two printed lines
PREVIEW_PX = 150            # the small picture of the result travels in a response header: it has to stay light


def otsu(np, a):
    """The grey level (0–255) that splits a picture into its dark and its light half best; None for a flat picture."""
    hist, _ = np.histogram(a, bins=256, range=(0, 255))
    w = np.cumsum(hist).astype(np.float64)
    m = np.cumsum(hist * np.arange(256)).astype(np.float64)
    with np.errstate(divide="ignore", invalid="ignore"):
        between = (m[-1] * w - m * w[-1]) ** 2 / (w * (w[-1] - w))
    return None if np.all(np.isnan(between)) else float(np.nanargmax(between))


def _cut_out(img, path):
    """
    The person in a photo as a mask, by rembg. The answer is kept in the temporary directory under the file's name,
    size and time: the sliders of the page ask for the same photo many times and the network takes a second or two.
    """
    from PIL import Image
    if os.environ.get("PAPEL_REMBG", "") == "off":           # the tests ask for a server without it, whatever this machine has
        raise ImportError("rembg is switched off")
    st = os.stat(path)
    key = hashlib.sha1(("%s|%d|%d" % (os.path.abspath(path), st.st_size, int(st.st_mtime))).encode("utf-8")).hexdigest()
    folder = os.path.join(tempfile.gettempdir(), "matplace-papel")
    kept = os.path.join(folder, key + ".png")
    if os.path.isfile(kept):
        try:
            return Image.open(kept).convert("L").resize(img.size, Image.BILINEAR)
        except Exception:  # noqa: BLE001 - a half-written file: cut the person out again
            pass
    if "U2NET_HOME" not in os.environ and os.path.isdir(os.path.join(sys.prefix, "u2net")):
        os.environ["U2NET_HOME"] = os.path.join(sys.prefix, "u2net")      # the model lies next to the interpreter on the server
    from rembg import new_session, remove
    small = img.convert("RGB")
    small.thumbnail((1024, 1024), Image.LANCZOS)
    mask = remove(small, session=new_session("u2net"), only_mask=True).convert("L")
    try:
        os.makedirs(folder, exist_ok=True)
        mask.save(kept + ".tmp", "PNG")
        os.replace(kept + ".tmp", kept)
        for name in os.listdir(folder):                    # yesterday's photos are not asked for again
            old = os.path.join(folder, name)
            if time.time() - os.path.getmtime(old) > 2 * 86400:
                os.remove(old)
    except OSError:
        pass
    return mask.resize(img.size, Image.BILINEAR)


def subject(img, alpha, path, isolate):
    """
    Who is in the picture: (mask "L" with the person white, or None; whether rembg answered, None when it was not asked).
    A picture that brings its own transparency is its own mask. A photo is cut out by rembg when the visitor asks for
    it and the server has it; a mask that holds nearly nothing or nearly everything says nothing and is dropped.
    """
    import numpy as np
    mask, answered = None, None
    if alpha is not None and float((np.asarray(alpha) < 128).mean()) > 0.01:
        mask = alpha
    elif isolate:
        try:
            mask, answered = _cut_out(img, path), True
        except Exception:  # noqa: BLE001 - rembg or its model is not there: the photo is used whole
            mask, answered = None, False
    if mask is not None:
        share = float((np.asarray(mask) > 127).mean())
        if share < 0.03 or share > 0.97:
            mask = None
    return mask, answered


def lay(img, mask, cols, rows, o):
    """
    The picture as the window sees it, on a grid of cols × rows. Returns (grey levels as float32, where the picture
    lies, where the person is or None, where the picture is to be read), all of the window's size, and the box of
    the photo that was used (left, top, right, bottom in its own pixels).

    o: trim   share of the height cut off from below, so the face comes forward (from the person's own lower edge
              when the person is known, and the picture is then cut to the person)
       scale, turn (degrees, anticlockwise), dx, dy (cells from the middle of the window, dy upwards)
       plain  the ground round a known person is white (a face then gets its outline); otherwise it is filled with
              what the person looks like at the edge, so no line is drawn where the person ends
    """
    import numpy as np
    from PIL import Image, ImageOps
    from scipy import ndimage
    w, h = img.size
    trim = max(0.0, min(0.6, float(o.get("trim", 0.0))))
    box = mask.point(lambda v: 255 if v > 127 else 0).getbbox() if mask is not None else None
    if box:
        left, top, right, low = box
        low = low - trim * (low - top)
        room = 0.06 * max(right - left, low - top)           # a little air above the head and beside it
        crop, centre = (max(0, left - room), max(0, top - room), min(w, right + room), max(top + 8, low)), (0.5, 0.0)
    else:
        crop, centre = (0, 0, w, max(8, h * (1 - trim))), (0.5, 0.35)        # a face is in the upper half of a photo
    crop = tuple(int(round(v)) for v in crop)
    scale = float(o.get("scale", 1.0))
    size = (max(8, int(round(cols * scale))), max(8, int(round(rows * scale))))
    grey = ImageOps.fit(img.convert("L").crop(crop), size, Image.LANCZOS, centering=centre)
    who = ImageOps.fit(mask.crop(crop), size, Image.LANCZOS, centering=centre) if mask is not None else None
    try:                                                     # the contrast is the person's, not the wall's behind them
        grey = ImageOps.autocontrast(grey, cutoff=1, mask=who.point(lambda v: 255 if v > 127 else 0) if who is not None else None)
    except TypeError:                                        # a Pillow from before masks
        grey = ImageOps.autocontrast(grey, cutoff=1)
    cover = Image.new("L", size, 255)
    turn = float(o.get("turn", 0.0))
    if abs(turn) > 0.01:
        grey = grey.rotate(turn, Image.BICUBIC, expand=True, fillcolor=255)
        cover = cover.rotate(turn, Image.NEAREST, expand=True, fillcolor=0)
        who = who.rotate(turn, Image.BICUBIC, expand=True, fillcolor=0) if who is not None else None
    at = (int(round(cols / 2.0 + float(o.get("dx", 0.0)) - grey.width / 2.0)), int(round(rows / 2.0 - float(o.get("dy", 0.0)) - grey.height / 2.0)))

    def placed(layer, empty):
        canvas = Image.new("L", (cols, rows), empty)
        canvas.paste(layer, at)
        return np.asarray(canvas)

    a = placed(grey, 255).astype(np.float32)
    inside = placed(cover, 0) > 127
    person = (placed(who, 0) > 127) & inside if who is not None else None
    if person is not None:
        # crumbs of a cut-out (a lamp behind the person, a speck of the wall) are not the person
        labels, count = ndimage.label(person)
        if count > 1:
            sizes = ndimage.sum(person, labels, range(1, count + 1))
            person = np.isin(labels, 1 + np.flatnonzero(sizes >= 0.05 * sizes.max()))
    read = inside if person is None else person
    if person is not None and o.get("plain", True):
        soft = placed(who, 0).astype(np.float32) / 255.0
        a = a * soft + 255.0 * (1.0 - soft)
    else:
        if person is not None:
            # the rim of a cut-out still holds a little of the wall behind the person: it is not read, and so a light
            # line stays between a dark head of hair and the dark ground
            read = ndimage.binary_erosion(person, iterations=max(1, int(round(0.011 * max(cols, rows)))))
        # what is not the picture takes the look of the picture next to it: a blur of the picture alone, spread outwards
        held = read.astype(np.float32)
        spread = max(4.0, 0.03 * max(cols, rows))
        near = ndimage.gaussian_filter(held, spread)
        fill = np.where(near > 1e-3, ndimage.gaussian_filter(a * held, spread) / np.maximum(near, 1e-3), float(a[held > 0].mean()) if held.any() else 255.0)
        a = np.where(held > 0, a, fill).astype(np.float32)
    return a, inside, person, read, crop


def split(a, where, cell, darkness, detail):
    """
    Dark and light of a picture laid by lay(): True where the dark layer is printed, only within `where`.

    A plain threshold makes a face a blot (half of it lies in shadow). The picture is first sharpened against a wide
    blur of itself: where it is darker than its surroundings it gets darker still, so eyes, lips, glasses and strands
    of hair come out as lines, while big dark areas stay filled. `detail` (0–100) is how fine the picture is read;
    `darkness` moves the level from the one the picture splits itself at (50).
    Returns (dark, the smallest piece kept in cells, the radius in cells below which nothing is printed).
    """
    import numpy as np
    from scipy import ndimage
    d = max(0.0, min(1.0, detail / 100.0))
    thin = max(1, int(math.ceil(LINE_MM / cell - 0.05)))     # a pair of printed lines, in cells
    sigma = max(2.4 - 1.8 * d, 0.5 * thin)                   # a small panel has small cells: never finer than it prints
    near, far = ndimage.gaussian_filter(a, sigma), ndimage.gaussian_filter(a, sigma * 3.5)
    sharp = near + (6.0 + 14.0 * d) * (near - far)
    level = otsu(np, near[where]) if where.any() else None
    if level is None:
        return np.zeros(a.shape, dtype=bool), 4, thin
    dark = (sharp <= level + (darkness - 50.0) * 2.0) & where
    small = max(4, int(round(((2.4 - 1.2 * d) / cell) ** 2)))           # specks under 2.4 mm (few details) … 1.2 mm (many) go
    return dark, small, thin


def ground(np, dark, person, cell):
    """
    What is round the person, printed dark like the frame. Where the person is dark at the edge (hair, a coat), a
    light line is left between the two, or the head would melt into the ground.
    """
    from scipy import ndimage
    reach = max(1, int(round(HALO_MM / cell)))
    return ~person & ~ndimage.binary_dilation(dark & person, iterations=reach)


def scatter(np, field, cell, unit_mm, density):
    """
    Where the cut-outs of the ground go: [(x, y in mm from the window's lower left corner, size factor, turn in degrees)].
    A staggered grid, shaken a little, always the same one (the picture must not jump while a slider moves); a place
    is taken only where the ground is wide enough round it, a small cut-out where a big one has no room.
    """
    from scipy import ndimage
    rows, cols = field.shape
    free = ndimage.distance_transform_edt(np.pad(field, 1, mode="constant", constant_values=False))[1:-1, 1:-1] * cell
    pitch = unit_mm * (2.6 - 1.25 * max(0.2, min(1.0, density)))      # never so close that two cut-outs run into one
    rng = np.random.default_rng(7)
    spots = []
    for j in range(int(rows * cell / (pitch * 0.866)) + 2):
        for i in range(int(cols * cell / pitch) + 2):
            sx, sy, k, turn, turn2 = rng.uniform(-0.16, 0.16), rng.uniform(-0.16, 0.16), rng.uniform(0.8, 1.25), rng.uniform(0.0, 360.0), rng.uniform(0.0, 360.0)
            shift = 0.5 if j % 2 else 0.0
            # a big cut-out on every place of the grid, a small one in the gap between three of them
            for x, y, sizes, a in (((i + shift + sx) * pitch, (j * 0.866 + sy) * pitch, (k, 0.62), turn), ((i + shift + 0.5) * pitch, (j * 0.866 + 0.29) * pitch, (0.5,), turn2)):
                c, r = int(x / cell), rows - 1 - int(y / cell)
                if not (0 <= c < cols and 0 <= r < rows):
                    continue
                for size in sizes:
                    if free[r, c] >= 0.42 * unit_mm * size + 1.0:
                        spots.append((x, y, size, a))
                        break
    return spots


def preview(np, dark):
    """The picture of two tones as a small PNG in base64: what is printed dark is dark."""
    from PIL import Image
    im = Image.fromarray(np.where(dark, 0, 255).astype(np.uint8), "L")
    im.thumbnail((PREVIEW_PX, PREVIEW_PX), Image.LANCZOS)
    out = io.BytesIO()
    im.point(lambda v: 255 if v > 127 else 0).convert("1").save(out, "PNG", optimize=True)
    return base64.b64encode(out.getvalue()).decode("ascii")
