"""Photos of printed test objects: one JSON object on stdout.

  stats <in> -                      {"ok": true, "mean", "std"} the brightness of the photo (a black frame has both ~0)
  normalize <in> <out> [max]        any photo (JPEG/PNG/WebP, HEIC when pillow-heif is installed) -> upright JPEG,
                                    long edge at most <max> px (default 4032)
  fit <in> <out> <max>              JPEG copy with the long edge at most <max> px (what the vision model sees)
  crop <in> <out> x0 y0 x1 y1 <max> region of the photo (pixel coordinates of the photo itself), enlarged or
                                    reduced so its long edge is <max> px
"""
import json
import sys

from PIL import Image, ImageOps, ImageStat

try:  # iPhone photos arrive as HEIC when they come through Drive or a desktop upload
    import pillow_heif

    pillow_heif.register_heif_opener()
    HEIF = True
except ImportError:
    HEIF = False


def out(obj):
    print(json.dumps(obj))
    sys.exit(0)


def load(path):
    try:
        im = Image.open(path)
        im = ImageOps.exif_transpose(im)
    except Exception as e:  # noqa: BLE001 - anything Pillow cannot read is "not a photo"
        out({"ok": False, "error": "unreadable", "detail": str(e)[:200], "heif": HEIF})
    return im.convert("RGB")


def brightness(im):
    """Mean and spread of the grey levels on a small copy: a black frame (the camera did not capture, F26-000097)
    or one flat colour has both near zero (PrintPhotos::isDark decides)."""
    g = im.convert("L")
    g.thumbnail((256, 256))
    st = ImageStat.Stat(g)
    return {"mean": round(st.mean[0], 2), "std": round(st.stddev[0], 2)}


def save(im, path, facts=None):
    try:
        im.save(path, "JPEG", quality=90, optimize=True)
    except OSError:  # the optimizing encoder gives up on some large images; a plain save always works
        im.save(path, "JPEG", quality=90)
    out({"ok": True, "w": im.width, "h": im.height, **(facts or {})})


def shrink(im, limit):
    if max(im.size) > limit:
        im = im.copy()
        im.thumbnail((limit, limit), Image.LANCZOS)
    return im


def main(argv):
    if len(argv) < 3:
        out({"ok": False, "error": "usage"})
    cmd, src, dst = argv[0], argv[1], argv[2]
    if cmd == "normalize":
        im = shrink(load(src), int(argv[3]) if len(argv) > 3 else 4032)
        save(im, dst, brightness(im))
    if cmd == "stats":
        out({"ok": True, **brightness(load(src))})
    if cmd == "fit":
        save(shrink(load(src), int(argv[3])), dst)
    if cmd == "crop":
        im = load(src)
        x0, y0, x1, y1 = (int(round(float(v))) for v in argv[3:7])
        limit = int(argv[7])
        x0, x1 = sorted((max(0, min(im.width, x0)), max(0, min(im.width, x1))))
        y0, y1 = sorted((max(0, min(im.height, y0)), max(0, min(im.height, y1))))
        if x1 - x0 < 8 or y1 - y0 < 8:
            out({"ok": False, "error": "empty_region"})
        part = im.crop((x0, y0, x1, y1))
        scale = limit / max(part.size)
        part = part.resize((max(1, round(part.width * scale)), max(1, round(part.height * scale))), Image.LANCZOS)
        save(part, dst)
    out({"ok": False, "error": "unknown_command"})


if __name__ == "__main__":
    main(sys.argv[1:])
