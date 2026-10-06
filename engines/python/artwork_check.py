#!/usr/bin/env python3
"""
Checks the library of silhouettes (engines/artwork): every SVG has to load through the tools' own loader
(shape2d.svg: plain filled paths, no rasters, within the point limit) and give a shape with an area.

    python artwork_check.py <engines/artwork> [width_mm]

Prints one JSON object: {ok, count, failed: [{file, error}], points: {file: n}} — run it after adding pictures;
tests/Feature/ArtworkLibraryTest.php runs it too.
"""
import glob
import json
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))


def main(argv):
    if len(argv) < 2:
        print(json.dumps({"ok": False, "error": "usage: artwork_check.py <dir> [width_mm]"}))
        return 2
    import manifold3d as M
    import shape2d as S
    root = argv[1]
    width = float(argv[2]) if len(argv) > 2 else 80.0
    failed, points = [], {}
    files = sorted(glob.glob(os.path.join(root, "*", "*.svg")))
    for path in files:
        rel = os.path.relpath(path, root).replace(os.sep, "/")
        try:
            cs, info = S.svg(M, path, width)
            if cs.is_empty() or cs.area() <= 1.0:
                raise ValueError("empty shape")
            points[rel] = int(cs.num_vert())
        except S.ArtworkError as e:
            failed.append({"file": rel, "error": getattr(e, "code", str(e))})
        except Exception as e:  # noqa: BLE001 - the caller reads the reason
            failed.append({"file": rel, "error": str(e)[:120]})
    print(json.dumps({"ok": not failed and bool(files), "count": len(files), "failed": failed, "points": points}))
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
