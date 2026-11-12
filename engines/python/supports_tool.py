"""Does a model, standing as it will be printed, need supports at all? (docs/AL.md 22, part D)

    python supports_tool.py check <model.stl> <threshold_deg> <layer_mm>
        -> {"ok": true, "needed": false, "reason": "none", "seconds": 0.05, "triangles": 350000, "down_mm2": 0.0}

The farm slices every "automatic" order with tree supports switched on and lets the slicer find the overhangs. This
check answers the easy half of the question from the triangles alone, in a fraction of a second, so a model that has
none can be sliced with supports off. It errs on one side only: "not needed" is said when nothing of the model can hang
in the air, anything unclear is "needed" and the slicer decides as before.

Measured on 12 Nov 2026 it saves no slicing time: with nothing to prop up, Orca 2.5 takes the same time with supports
on as off (two meshes of 350 000 triangles: 6.6 s against 6.2 s, 20.4 s against 20.0 s); what supports cost is the
building of those a model needs. The farm therefore leaves the check switched off (config engines.mesh.supports_check).

"Not needed" means all of this holds for the mesh as it stands on Z = 0:
  1. no face off the plate looks down flatter than the slicer's threshold plus a margin (reason "overhang"). Level
     ceilings count too, short bridges included: Orca 2.5 with the farm's profile (max_bridge_length 10,
     bridge_no_support 0) put supports under an 8 mm bridge and under an 8 mm window (12 Nov 2026);
  2. every face that looks down at all (steeper than the limit: a chamfer, the wall of a vase that widens upwards) is
     reached from the plate through such faces: its lowest corner is on the plate or is an upper corner of another
     face looking down (reason "hanging": a tip, an edge or an underside that starts in the air, or on top of
     something);
  3. where such a wall lands on the plate, the model stands there on a real base: a level bottom patch of at least
     BASE_MM2 and at least BASE_WIDTH_MM wide in every direction (reason "base"). Orca's tree(auto) props up "sharp
     tails": what starts on the plate smaller than 6 mm across (or 36 mm2) and leans gets supports however steep it
     is (a cone on its tip, a leaning fin 6 mm thick: supports; 7 mm: none; measured 12 Nov 2026), and goes on
     getting them while it stays that thin. What starts wide is never a sharp tail later, even when it thins out.
Only binary STL is read (what the farm writes), with numpy alone. A mesh wound inside out is turned by the sign of its
volume. The check was held against Orca 2.4, 2.5.0-alpha and 2.5.0 on 94 test shapes (docs/AL.md 22): it never said
"not needed" where the slicer built supports.
"""
import json
import math
import os
import sys
import time

MARGIN_DEG = 5.0        # faces up to this much steeper than the threshold still count as overhang
DOWN_NZ = -0.02         # a face whose normal points down more than this "looks down" (about 1° past vertical)
AREA_EPS_MM2 = 0.1      # flat overhang area below this is rounding, not a face
BASE_MM2 = 45.0         # the bottom patch a leaning wall may stand on: at least this area (Orca: 36) …
BASE_WIDTH_MM = 7.5     # … and this wide in its narrowest direction (Orca: 6 along X and Y)
QUANT_MM = 0.001        # corners closer than this are one vertex


def read_binary_stl(path):
    import numpy as np

    with open(path, 'rb') as fh:
        head = fh.read(84)
        if len(head) < 84:
            return None
        n = int.from_bytes(head[80:84], 'little')
        if n <= 0 or os.path.getsize(path) != 84 + 50 * n:
            return None
        rec = np.dtype([('n', '<f4', (3,)), ('v', '<f4', (3, 3)), ('a', '<u2')])
        data = np.fromfile(fh, dtype=rec, count=n)
    return data['v'].astype(np.float64)


def good_base_vertices(ids, faces, area, vxy, nv):
    """The vertices of the bottom patches a wall may stand on: bottom faces joined over shared edges, a patch counted
    when it is big and wide enough."""
    import numpy as np

    good = np.zeros(nv, dtype=bool)
    if len(faces) == 0:
        return good
    tri = ids[faces]
    a = tri[:, [0, 1, 2]].reshape(-1)
    b = tri[:, [1, 2, 0]].reshape(-1)
    owner = np.repeat(np.arange(len(faces)), 3)
    key = np.minimum(a, b) * np.int64(nv) + np.maximum(a, b)
    order = np.argsort(key, kind='stable')
    key, a, b, owner = key[order], a[order], b[order], owner[order]
    same = key[1:] == key[:-1]
    parent = list(range(len(faces)))

    def root(i):
        while parent[i] != i:
            parent[i] = parent[parent[i]]
            i = parent[i]
        return i

    for i in np.nonzero(same)[0].tolist():
        ra, rb = root(int(owner[i])), root(int(owner[i + 1]))
        if ra != rb:
            parent[ra] = rb
    label = np.array([root(i) for i in range(len(faces))])
    patch_area = np.bincount(label, weights=area[faces], minlength=len(faces))
    turns = np.radians(np.arange(0.0, 180.0, 5.0))
    axes = np.stack([np.cos(turns), np.sin(turns)], axis=1)
    for patch in np.nonzero(patch_area >= BASE_MM2)[0].tolist():
        verts = np.unique(tri[label == patch])
        along = vxy[verts] @ axes.T                      # the patch seen from 36 directions: its narrowest extent
        if float((along.max(axis=0) - along.min(axis=0)).min()) >= BASE_WIDTH_MM:
            good[verts] = True
    return good


def check(path, threshold_deg, layer_mm):
    import numpy as np

    t0 = time.perf_counter()

    def done(needed, reason, **more):
        return {'ok': True, 'needed': bool(needed), 'reason': reason, 'seconds': round(time.perf_counter() - t0, 3)} | more

    v = read_binary_stl(path)
    if v is None or len(v) == 0:
        return done(True, 'format')
    tri = len(v)
    cross = np.cross(v[:, 1] - v[:, 0], v[:, 2] - v[:, 0])
    twice = np.linalg.norm(cross, axis=1)
    area = twice / 2.0
    real = twice > 1e-12
    volume = float(np.einsum('ij,ij->i', v[:, 0], np.cross(v[:, 1], v[:, 2])).sum() / 6.0)
    if abs(volume) < 1e-6:
        return done(True, 'volume', triangles=tri)
    nz = np.zeros(tri)
    nz[real] = cross[real, 2] / twice[real]
    if volume < 0:
        nz = -nz
    z = v[:, :, 2]
    zmin = float(z.min())
    bed_gap = max(0.05, float(layer_mm) / 2.0)      # the first layer is cut in its middle: what lies higher is not on the plate
    on_bed = z.max(axis=1) <= zmin + bed_gap
    down = real & (nz < DOWN_NZ) & ~on_bed
    flat = down & (nz < -math.cos(math.radians(min(89.0, float(threshold_deg) + MARGIN_DEG))))
    facts = {'triangles': tri, 'down_mm2': round(float(area[down].sum()), 2), 'overhang_mm2': round(float(area[flat].sum()), 2)}
    if facts['overhang_mm2'] > AREA_EPS_MM2:
        return done(True, 'overhang', **facts)
    if not down.any():
        return done(False, 'none', **facts)

    # one id per vertex (corners closer than QUANT_MM are one): 21 bits a coordinate in one integer
    flatv = v.reshape(-1, 3)
    q = np.rint((flatv - flatv.min(axis=0)) / QUANT_MM).astype(np.int64)
    if int(q.max()) >= 1 << 21:
        return done(True, 'size', **facts)
    _, first, ids = np.unique((q[:, 0] << 42) | (q[:, 1] << 21) | q[:, 2], return_index=True, return_inverse=True)
    ids = ids.reshape(-1, 3)
    nv = len(first)
    vz = flatv[first, 2]

    # every face looking down hangs on another one below it, down to the plate
    zc = vz[ids[down]]
    low = zc.min(axis=1, keepdims=True)
    is_low = zc <= low + 1e-4
    lowest = np.zeros(nv, dtype=bool)
    lowest[ids[down][is_low]] = True
    upper = np.zeros(nv, dtype=bool)
    upper[ids[down][~is_low]] = True
    plate = vz <= zmin + bed_gap
    hanging = lowest & ~upper & ~plate
    if hanging.any():
        return done(True, 'hanging', hanging=int(hanging.sum()), **facts)

    # … and where they reach the plate the model stands on a real base
    landing = lowest & plate
    bottom = np.nonzero(real & on_bed & (nz < -0.99))[0]
    base = good_base_vertices(ids, bottom, area, flatv[first, :2], nv)
    # a wall may land a hair above the bottom itself (within the first layer): a corner next to the base stands on it
    beside = np.zeros(nv, dtype=bool)
    touching = base[ids].any(axis=1) & real
    beside[ids[touching]] = True
    base |= beside & plate
    if (landing & ~base).any():
        return done(True, 'base', landing=int((landing & ~base).sum()), **facts)
    return done(False, 'none', **facts)


if __name__ == '__main__':
    if len(sys.argv) < 5 or sys.argv[1] != 'check':
        print(__doc__)
        sys.exit(2)
    try:
        print(json.dumps(check(sys.argv[2], float(sys.argv[3]), float(sys.argv[4]))))
    except Exception as e:  # noqa: BLE001 - whatever goes wrong, the slicer decides as before
        print(json.dumps({'ok': False, 'needed': True, 'reason': 'error', 'error': str(e)[:300]}))
