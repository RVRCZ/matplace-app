#!/usr/bin/env python3
"""
The background off a product photo (session 4, /tools/photo): rembg with the u2net model on the CPU. The thing
stays, everything round it becomes transparent; the result is an RGBA PNG cropped to the thing with a small margin.
One JSON object on stdout.

  photo_cut.py <photo> <out.png> [--max 2000]      {"ok": true, "width", "height", "coverage"}
  photo_cut.py --probe                              {"ok": true|false, "rembg": version}

The model is looked for in $U2NET_HOME (/opt/matplace-py/u2net on the server); the PHP side passes the variable.
"""
import json
import os
import sys

from PIL import Image, ImageOps

try:  # photos from iPhones come as HEIC: readable when pillow-heif is installed, otherwise not_a_picture
    from pillow_heif import register_heif_opener

    register_heif_opener()
except Exception:
    pass


def out(obj):
    print(json.dumps(obj))
    sys.exit(0)


def probe():
    try:
        import rembg  # noqa: F401
        import onnxruntime  # noqa: F401
    except Exception as e:  # pragma: no cover - depends on the machine
        out({"ok": False, "error": str(e)[:200]})
    home = os.environ.get("U2NET_HOME", "")
    model = os.path.join(home, "u2net.onnx") if home else ""
    out({"ok": True, "rembg": getattr(rembg, "__version__", "?"), "model_present": bool(model and os.path.isfile(model)), "home": home})


def main(argv):
    if argv and argv[0] == "--probe":
        probe()
    if len(argv) < 2:
        out({"ok": False, "error": "usage"})
    src, dst = argv[0], argv[1]
    limit = int(argv[argv.index("--max") + 1]) if "--max" in argv else 2000
    try:
        im = Image.open(src)
        im = ImageOps.exif_transpose(im).convert("RGB")
    except Exception as e:
        out({"ok": False, "error": "not_a_picture: " + str(e)[:120]})
    if max(im.size) > limit:
        k = limit / max(im.size)
        im = im.resize((max(1, round(im.width * k)), max(1, round(im.height * k))), Image.LANCZOS)
    try:
        from rembg import new_session, remove
    except Exception as e:
        out({"ok": False, "error": "rembg_missing: " + str(e)[:120]})
    try:
        session = new_session("u2net")
        cut = remove(im, session=session, post_process_mask=True)
    except Exception as e:
        out({"ok": False, "error": "rembg_failed: " + str(e)[:200]})
    cut = cut.convert("RGBA")
    alpha = cut.getchannel("A")
    bbox = alpha.point(lambda a: 255 if a > 8 else 0).getbbox()
    if not bbox:
        out({"ok": False, "error": "nothing_found"})
    # a margin of 2 % round the thing, so a shadow and a frame have room
    mx, my = round((bbox[2] - bbox[0]) * 0.02) + 2, round((bbox[3] - bbox[1]) * 0.02) + 2
    box = (max(0, bbox[0] - mx), max(0, bbox[1] - my), min(cut.width, bbox[2] + mx), min(cut.height, bbox[3] + my))
    cut = cut.crop(box)
    hist = cut.getchannel("A").histogram()
    coverage = sum(hist[128:]) / float(cut.width * cut.height)
    cut.save(dst, "PNG", optimize=True)
    out({"ok": True, "width": cut.width, "height": cut.height, "coverage": round(coverage, 3)})


if __name__ == "__main__":
    main(sys.argv[1:])
