#!/usr/bin/env python3
"""
Mesh utility for matplace engines (trimesh, optional pymeshfix, optional cadquery/OCP for CAD formats).

  mesh_tool.py probe                                  -> {"ok": true, "trimesh": "x.y.z", "pymeshfix": bool, "cad": bool}
  mesh_tool.py check <in>                             -> geometry + topology report (mm)
  mesh_tool.py repair <in> <out.stl>                  -> repaired binary STL + report
  mesh_tool.py convert <in> <out.stl>                 -> any trimesh-readable mesh format to binary STL (scene merged)
  mesh_tool.py normalize <in> <out.stl> <max_mm> <yup 0|1> [clean,pedestal,solid] [extras-json]
                                                          extras: {"pedestal": socle|antique|cut|round|square|hexagon|column|plaque|none, "name": "…", "dedication": "…", "font": path,
                                                                   "front": auto|keep|left|right|back, "cut": "bust", "tidy": true (cut off loose strands), "sink": 0-0.4, "source_out": path of the figure without a base,
                                                                   "strip_pedestal": true}
                                                       -> millimetres (largest side = max_mm), Z-up, on the bed;
                                                          clean = drop dust fragments, pedestal = flat round base,
                                                          solid = close holes and union all parts into one watertight body
  mesh_tool.py cad <in.step|iges> <out.stl> [lin] [ang] -> CAD B-rep to STL via OpenCascade (cadquery); lin=mm, ang=rad

Always prints exactly one JSON object on stdout.
"""
import json
import sys


def out(obj):
    print(json.dumps(obj))
    sys.exit(0)


def fail(msg):
    out({"ok": False, "error": str(msg)})


def load(path):
    import trimesh
    m = trimesh.load(path, force="mesh")
    if isinstance(m, trimesh.Scene):
        geoms = list(m.geometry.values())
        if not geoms:
            raise ValueError("empty scene")
        m = trimesh.util.concatenate(geoms)
    if m.is_empty or len(m.faces) == 0:
        raise ValueError("mesh has no faces")
    # Unit guess: printing meshes are in mm; values above 2000 are likely inches.
    if float(max(m.extents)) > 2000:
        m.apply_scale(25.4)
    return m


def report(m, extra=None):
    bb = m.extents
    shells = 1
    try:
        shells = len(m.split(only_watertight=False))
    except Exception:
        pass
    issues = []
    if not m.is_watertight:
        issues.append("not_watertight")
    if shells > 1:
        issues.append("multiple_shells")
    if m.is_watertight and not m.is_winding_consistent:
        issues.append("flipped_normals")
    if float(m.volume) < 0:
        issues.append("flipped_normals")
    r = {
        "ok": True,
        "watertight": bool(m.is_watertight),
        "volume_mm3": abs(float(m.volume)) if m.is_watertight else abs(float(m.convex_hull.volume)) * 0.6,
        "area_mm2": float(m.area),
        "bbox": {"x": float(bb[0]), "y": float(bb[1]), "z": float(bb[2])},
        "triangles": int(len(m.faces)),
        "shells": int(shells),
        "flipped_normals": "flipped_normals" in issues,
        "issues": sorted(set(issues)),
    }
    if extra:
        r.update(extra)
    return r


def meshfix_arrays(mf):
    """pymeshfix 0.18 renamed the result from .v/.f to .points/.faces; read whichever this version has."""
    import numpy as np
    v = getattr(mf, "points", None)
    f = getattr(mf, "faces", None)
    if v is None or f is None:
        v, f = mf.v, mf.f
    return np.asarray(v, dtype=float), np.asarray(f).reshape(-1, 3)


def fix_winding_fast(m):
    """
    Generated meshes arrive closed but with some faces turned inside out, and exact solids refuse such a mesh.
    trimesh needs minutes for two million faces; this walks the face graph once (scipy BFS) and flips by parity.
    """
    import numpy as np
    import trimesh
    from scipy.sparse import coo_matrix
    from scipy.sparse.csgraph import breadth_first_order
    pairs, edges = m.face_adjacency, m.face_adjacency_edges
    f = m.faces

    def runs_forward(face_idx):
        tri = f[face_idx]
        a, b = edges[:, 0][:, None], edges[:, 1][:, None]
        return ((tri == a) & (np.roll(tri, -1, axis=1) == b)).any(axis=1)

    # neighbours agree when the shared edge runs in opposite directions in the two faces
    clash = runs_forward(pairs[:, 0]) == runs_forward(pairs[:, 1])
    n = len(f)
    graph = coo_matrix((np.ones(len(pairs), dtype=np.int8), (pairs[:, 0], pairs[:, 1])), shape=(n, n)).tocsr()
    graph = graph + graph.T
    lookup = {}
    for (i, j), c in zip(pairs[clash].tolist(), [True] * int(clash.sum())):
        lookup[(i, j)] = lookup[(j, i)] = c
    flip = np.zeros(n, dtype=bool)
    seen = np.zeros(n, dtype=bool)
    for start in range(n):
        if seen[start]:
            continue
        order, pred = breadth_first_order(graph, start, directed=False, return_predecessors=True)
        seen[order] = True
        for node in order[1:].tolist():
            parent = int(pred[node])
            flip[node] = flip[parent] ^ ((parent, node) in lookup)
        if len(order) == n:
            break
    if flip.any():
        faces = f.copy()
        faces[flip] = faces[flip][:, ::-1]
        m = trimesh.Trimesh(vertices=m.vertices, faces=faces, process=False)
    if m.volume < 0:
        m.invert()
    return m


def solidify(m, target, extra=None):
    """
    Generated meshes have open edges and loose shells; slicers then guess. Close every real part (pymeshfix),
    drop dust, and union everything (manifold3d) into one watertight body. Every step falls back to the input.
    """
    import trimesh
    if m.is_watertight and not m.is_winding_consistent:
        try:
            m = fix_winding_fast(m)
        except Exception:
            pass
    limit = target * 0.02
    parts = [p for p in m.split(only_watertight=False) if float(max(p.extents)) >= limit and len(p.faces) >= 4]
    if not parts:
        parts = [m]
    fixed = []
    for p in parts:
        q = p
        if not p.is_watertight:
            try:
                import pymeshfix
                fx = pymeshfix.MeshFix(p.vertices, p.faces)
                fx.repair()
                vv = getattr(fx, "points", None)
                ff = getattr(fx, "faces", None)
                if vv is None or ff is None:
                    vv, ff = meshfix_arrays(fx)
                c = trimesh.Trimesh(vv, ff, process=True)
                # accept only a repair that kept the shape (pymeshfix may throw away a badly broken part)
                if len(c.faces) and c.is_watertight and abs(float(max(c.extents)) - float(max(p.extents))) <= 0.03 * float(max(p.extents)):
                    q = c
            except Exception:
                pass
        if q.is_watertight and q.volume < 0:
            q.invert()
        fixed.append(q)
    if extra is not None:
        fixed.append(extra)
    if len(fixed) > 1 and all(f.is_watertight for f in fixed):
        try:
            u = trimesh.boolean.union(fixed, engine="manifold")
            if len(u.faces) and u.is_watertight:
                return u
        except Exception:
            pass
    if len(fixed) == 1 and fixed[0].is_watertight:
        return fixed[0]
    # torn surfaces that cannot be closed exactly (hundreds of open pieces): rebuild the shape through a voxel grid
    try:
        # from the untouched parts: a patch that pymeshfix closed on its own has grown a back side, which shows as beads along the seams
        body = rebuild_solid(trimesh.util.concatenate(parts), target)
        if extra is None:
            return body
        u = trimesh.boolean.union([body, extra], engine="manifold")
        if len(u.faces) and u.is_watertight:
            return u
    except Exception:
        pass
    return fixed[0] if len(fixed) == 1 else trimesh.util.concatenate(fixed)


def rebuild_solid(m, target):
    """
    One closed body from any soup of surfaces. The voxel grid only decides what is inside (gaps closed, inside filled);
    the surface itself comes from the true distance to the original triangles in a narrow band around them, so the
    result follows the original to about a tenth of a millimetre instead of looking melted.
    """
    import numpy as np
    import trimesh
    from scipy import ndimage
    from scipy.spatial import cKDTree
    from skimage import measure

    pitch = max(0.25, float(max(m.extents)) / 260.0)
    close_it = max(2, int(round(1.0 / pitch)))                              # gaps up to about 2 mm are closed whatever the grid
    pad = close_it + 3
    vox = m.voxelized(pitch)
    grid = np.pad(vox.matrix, pad)
    origin = vox.transform[:3, 3] - pad * pitch
    closed = ndimage.binary_closing(grid, structure=ndimage.generate_binary_structure(3, 1), iterations=close_it)
    # inside = enclosed when looked at along at least two of the three axes; survives holes that a flood fill would leak through
    votes = np.zeros(closed.shape, dtype=np.uint8)
    for axis in range(3):
        moved = np.moveaxis(closed, axis, 0)
        votes += np.moveaxis(np.stack([ndimage.binary_fill_holes(layer) for layer in moved]), 0, axis)
    solid = (votes >= 2) | closed
    # torn models leak through that fill and stay hollow, and a thin hollow wall is pierced by every noisy seam.
    # A second, coarse look (3x3x3 blocks, gaps up to about 4 mm closed) finds the inside for sure; it is pulled
    # back two blocks from the surface so it never touches the detail.
    k = 3
    nx, ny, nz = (int(np.ceil(n / k)) * k for n in grid.shape)
    big = np.zeros((nx, ny, nz), dtype=bool)
    big[:grid.shape[0], :grid.shape[1], :grid.shape[2]] = grid
    coarse = big.reshape(nx // k, k, ny // k, k, nz // k, k).any(axis=(1, 3, 5))
    coarse = np.pad(coarse, 3)
    coarse = ndimage.binary_fill_holes(ndimage.binary_closing(coarse, iterations=2))
    core = ndimage.binary_erosion(coarse, iterations=2)[3:-3, 3:-3, 3:-3]
    core = np.repeat(np.repeat(np.repeat(core, k, 0), k, 1), k, 2)[:grid.shape[0], :grid.shape[1], :grid.shape[2]]
    solid |= core
    # a bust is cut open at the chest, so nothing encloses it from the side and the fills above leak. Horizontal slices
    # are rings; a tear crossing a slice opens the ring, so each slice is closed generously in 2D (tears up to ~7 mm),
    # filled, and the filling pulled back: bays narrower than that stay open and the detail near the surface is
    # left to the distance field.
    reach = max(3, int(round(3.6 / pitch)))
    edt = ndimage.distance_transform_edt
    for z in range(closed.shape[2]):
        ring = closed[:, :, z]
        if not ring.any():
            continue
        wide = edt(~ring) <= reach                                         # dilate
        full = ndimage.binary_fill_holes(wide)
        if full.sum() == wide.sum():
            continue                                                        # nothing enclosed in this slice
        inner = edt(full) > 2 * reach                                       # undo the dilation, then erode by the reach
        if inner.any():
            solid[:, :, z] |= (edt(~inner) <= reach - 3) & full            # grow back to about 1 mm under the outline
    solid = ndimage.binary_fill_holes(solid)

    # coarse field from the mask (positive inside, roughly in voxels); it rules wherever there is no surface nearby
    field = (ndimage.gaussian_filter(solid.astype(np.float32), 1.0) - 0.5) * 3.0
    band = ndimage.binary_dilation(grid, iterations=2)
    p = np.argwhere(band) * pitch + origin
    pts, fidx = trimesh.sample.sample_surface(m, 1_200_000)
    normals = m.face_normals
    near = 12
    kd, kn = cKDTree(pts).query(p, k=near, workers=-1)
    # which side are we on: a weighted vote of the nearest surface points, so a stray sliver at a seam between
    # two patches is outvoted by the skin around it (the nearest triangle alone leaves rows of pits and beads)
    vote = np.zeros(len(p))
    for j in range(near):
        side_j = np.einsum("ij,ij->i", p - pts[kn[:, j]], normals[fidx[kn[:, j]]]) / np.maximum(kd[:, j], 1e-9)
        vote += side_j / (kd[:, j] + 0.25 * pitch) ** 2
    tri = fidx[kn[:, 0]]
    dist = np.linalg.norm(p - trimesh.triangles.closest_point(m.triangles[tri], p), axis=1) / pitch
    outside = vote > 0
    sd = np.where(outside, -dist, dist)
    # deep inside the mask the normal of a stray inner patch must not open a bubble
    # (generated meshes often carry an inner skin about a millimetre under the outer one; it would carve the model hollow)
    sd = np.where(ndimage.binary_erosion(solid, iterations=3)[band], np.abs(sd), sd)
    w = np.clip(2.0 - dist, 0, 1)                                           # 1 closer than a voxel, 0 beyond two
    field[band] = w * sd + (1 - w) * field[band]

    # open borders of patches: the side of the nearest triangle means nothing next to them. Fill those voxels from
    # their trusted neighbours so a seam becomes a smooth join. Only seams lying on the body: thin free-standing
    # sheets (glasses, strands of hair) consist of borders and must stay.
    edges = m.edges_sorted
    _, first, count = np.unique(edges, axis=0, return_index=True, return_counts=True)
    border = edges[first[count == 1]]
    if len(border):
        a, b = m.vertices[border[:, 0]], m.vertices[border[:, 1]]
        steps = np.maximum(1, np.ceil(np.linalg.norm(b - a, axis=1) / (0.5 * pitch)).astype(int))
        rep = np.repeat(np.arange(len(border)), steps + 1)
        frac = np.concatenate([np.linspace(0, 1, n + 1) for n in steps])[:, None]
        cell = np.unique(np.round((a[rep] * (1 - frac) + b[rep] * frac - origin) / pitch).astype(int), axis=0)
        cell = cell[np.all((cell >= 0) & (cell < np.array(field.shape)), axis=1)]
        doubt = np.zeros(field.shape, dtype=bool)
        doubt[cell[:, 0], cell[:, 1], cell[:, 2]] = True
        doubt = ndimage.binary_dilation(doubt, iterations=2)
        doubt &= ndimage.binary_dilation(ndimage.binary_erosion(solid, iterations=3), iterations=5)
        trust = (~doubt).astype(np.float32)
        den = ndimage.gaussian_filter(trust, 1.2)
        fill = doubt & (den > 0.05)
        field[fill] = ndimage.gaussian_filter(field * trust, 1.2)[fill] / den[fill]

    verts, faces, _, _ = measure.marching_cubes(field, level=0.013)         # not exactly 0: values that ARE 0 give degenerate triangles
    out_mesh = trimesh.Trimesh(verts * pitch + origin, faces, process=True)
    if out_mesh.volume < 0:
        out_mesh.invert()
    bodies = [b for b in out_mesh.split(only_watertight=True) if b.volume > 0]
    if not bodies:
        raise ValueError("rebuild produced no closed body")
    biggest = max(b.volume for b in bodies)
    keep = [b for b in bodies if b.volume >= biggest * 0.02]
    body = keep[0] if len(keep) == 1 else trimesh.boolean.union(keep, engine="manifold")
    return simplified(body, 0.05)


def as_manifold(mesh):
    import numpy as np
    import manifold3d as M
    return M.Manifold(M.Mesh(vert_properties=np.asarray(mesh.vertices, dtype=np.float32), tri_verts=np.asarray(mesh.faces, dtype=np.uint32)))


def from_manifold(man):
    """
    Exact solids come back with vertices that may share a spot (a tunnel pinched shut, an edge collapsed). Every program
    that loads the STL welds such vertices and then calls the model "not watertight", so they are moved a few microns apart.
    """
    import numpy as np
    import trimesh
    res = man.to_mesh()
    faces = np.asarray(res.tri_verts)
    mesh = trimesh.Trimesh(vertices=np.asarray(res.vert_properties)[:, :3].astype(np.float64), faces=faces, process=False)
    for attempt in range(4):
        _, inv, cnt = np.unique(np.round(mesh.vertices.astype(np.float32), 4), axis=0, return_inverse=True, return_counts=True)
        twin = cnt[inv.ravel()] > 1
        if not twin.any():
            break
        vv = mesh.vertices.copy()
        vv[twin] += mesh.vertex_normals[twin] * -0.004 + np.random.default_rng(attempt).normal(0, 0.002, (int(twin.sum()), 3))
        mesh = trimesh.Trimesh(vertices=vv, faces=faces, process=False)
    return big_shells(trimesh.Trimesh(vertices=mesh.vertices, faces=mesh.faces, process=True))


def simplified(mesh, tolerance):
    """Marching cubes gives a million tiny triangles; merge the flat ones so the file stays small. Any failure hands back the input."""
    try:
        man = as_manifold(mesh)
        if man.is_empty():
            return mesh
        slim = from_manifold(man.simplify(tolerance))
        return slim if len(slim.faces) and slim.is_watertight else mesh
    except Exception:
        return mesh


def big_shells(mesh):
    """Keep the real bodies; crumbs and closed bubbles inside the model (shells with negative volume) go."""
    import trimesh
    parts = mesh.split(only_watertight=False)
    if len(parts) <= 1:
        return mesh
    biggest = max(float(p.volume) if p.is_watertight else 0.0 for p in parts)
    keep = [p for p in parts if p.is_watertight and float(p.volume) >= 0.01 * biggest]
    if not keep or biggest <= 0:
        return mesh
    return keep[0] if len(keep) == 1 else trimesh.util.concatenate(keep)


def bust_cut(m):
    """
    A generated bust often runs down to the elbows. A sculptor cuts it flat across the chest, about one head below
    the chin. Going down from the top the outline widens to the head, narrows to the neck and widens to the shoulders;
    the neck is the narrowest slice BELOW the widest slice of the head (the crown is narrow too, and long hair can
    hide the neck altogether). Without a clear head-neck-shoulders shape the mesh is handed back untouched.
    """
    import numpy as np
    z0, z1 = float(m.bounds[0][2]), float(m.bounds[1][2])
    h = z1 - z0
    v = m.vertices
    levels = np.linspace(z0 + 0.25 * h, z0 + 0.97 * h, 73)
    step = levels[1] - levels[0]
    layer = np.clip(((v[:, 2] - levels[0]) / step).astype(int), -1, len(levels))
    # measured along the shoulder line only: front to back a head is deeper than its neck is wide
    low = v[v[:, 2] < z0 + 0.4 * h]
    axis = int(np.argmax(np.ptp(low[:, :2], axis=0)))
    width = np.zeros(len(levels))
    for i in range(len(levels)):
        pts = v[layer == i]
        if len(pts) > 20:
            width[i] = float(np.ptp(pts[:, axis]))
    if (width <= 0).any():
        return m
    width = np.convolve(np.pad(width, 1, mode="edge"), np.ones(3) / 3, mode="valid")
    # walk down from the crown: wider and wider to the temples, then narrower to the neck, then out to the shoulders
    head = neck = None
    widest = narrowest = len(levels) - 1
    for i in range(len(levels) - 1, -1, -1):
        if head is None:
            if width[i] > width[widest]:
                widest = i
            elif width[i] < 0.92 * width[widest]:
                head, narrowest = widest, i
        else:
            if width[i] < width[narrowest]:
                narrowest = i
            elif width[i] > 1.15 * width[narrowest]:
                neck = narrowest
                break
    # two measures of the same thing, so every bust ends at the same place on the chest:
    #  - one head below the neck (needs a visible neck; long hair or a scarf hide it)
    #  - a good third of the crown-to-shoulder height below the shoulder line (always there)
    cuts = []
    if head is not None and neck is not None:
        span = float(width[:neck + 1].max()) if neck > 0 else 0.0
        if width[neck] <= 0.9 * width[head] and span >= 1.5 * width[neck]:
            cuts.append(float(levels[neck]) - 0.92 * (z1 - float(levels[neck])))
    broad = np.nonzero(width >= 0.8 * width.max())[0]
    if len(broad) and width.max() >= 1.3 * width[-8:].max():               # clearly broader than the top of the head
        line = float(levels[int(broad.max())])
        cuts.append(line - 0.43 * (z1 - line))
    if not cuts:
        return m
    cut = float(np.mean(cuts))
    if cut < z0 + 0.04 * h or (z1 - cut) < 0.45 * h:
        return m
    try:
        out_mesh = from_manifold(as_manifold(m).trim_by_plane([0, 0, 1], cut))
        return out_mesh if len(out_mesh.faces) and out_mesh.is_watertight else m
    except Exception:
        return m


def surface_grid(m, pitch, pad):
    """
    Occupied voxels of a closed mesh, in seconds even for a million faces: points sampled densely over the surface are
    binned, the inside is filled. Returns (grid, origin); grid[i, j, k] covers origin + (i, j, k) * pitch.
    """
    import numpy as np
    import trimesh
    from scipy import ndimage
    origin = m.bounds[0] - pad * pitch
    shape = np.ceil((m.bounds[1] - origin) / pitch).astype(int) + pad + 1
    n = int(min(6_000_000, max(300_000, 8 * float(m.area) / (pitch * pitch))))
    pts, _ = trimesh.sample.sample_surface(m, n)
    idx = np.clip(((pts - origin) / pitch).astype(int), 0, shape - 1)
    grid = np.zeros(shape, dtype=bool)
    grid[idx[:, 0], idx[:, 1], idx[:, 2]] = True
    # sampling leaves pinholes and the fill would leak: fill the dilated shell, then take the dilation back
    full = ndimage.generate_binary_structure(3, 3)
    return ndimage.binary_erosion(ndimage.binary_fill_holes(ndimage.binary_dilation(grid, structure=full)), structure=full) | grid, origin


def trim_loose(m, target):
    """
    Strands of hair, a scarf end or a loose sleeve that stand away from the body need supports and break off.
    The body is what survives an opening (everything thicker than about 2.5 mm); everything within a couple of
    millimetres of that body is kept in full (glasses, ears, hair lying on the head, the whole face), and only
    thin material further away is cut off, by intersecting the mesh with that keep volume. Returns (mesh, removed share).
    """
    import numpy as np
    import trimesh
    from scipy import ndimage
    from skimage import measure

    pitch = max(0.25, float(max(m.extents)) / 260.0)
    r = max(1.2, 0.015 * target) / pitch                                   # half the thickness that counts as body
    reach = r + max(1.0, 0.012 * target) / pitch                            # how far from the body thin things may stand
    pad = int(reach) + 3
    solid, origin = surface_grid(m, pitch, pad)
    core = ndimage.distance_transform_edt(solid) > r
    if not core.any():
        return m, 0.0
    keep = ndimage.distance_transform_edt(~core) <= reach
    loose = solid & ~keep
    # glasses stand off the face by more than the reach too. They differ from hair: a frame is a HORIZONTAL piece in
    # front of the head, a strand hangs down. Loose pieces are labelled and the horizontal ones in front of the head
    # (top half, sector towards -Y; busts are turned that way) are kept.
    labels, count = ndimage.label(loose, structure=np.ones((3, 3, 3), dtype=bool))
    if count:
        zs = np.nonzero(solid.any(axis=(0, 1)))[0]
        z_chin = zs[int(0.5 * (len(zs) - 1))]
        head = solid[:, :, zs[int(0.7 * (len(zs) - 1))]:]
        cx, cy = (float(np.nonzero(head)[i].mean()) for i in (0, 1))
        ijk = np.argwhere(loose)
        lab = labels[ijk[:, 0], ijk[:, 1], ijk[:, 2]]
        size = np.bincount(lab, minlength=count + 1)
        mean = np.stack([np.bincount(lab, weights=ijk[:, i], minlength=count + 1) for i in range(3)], 1) / np.maximum(size, 1)[:, None]
        d = ijk - mean[lab]
        cov = np.stack([np.bincount(lab, weights=d[:, i] * d[:, j], minlength=count + 1) for i in range(3) for j in range(3)], 1).reshape(-1, 3, 3) / np.maximum(size, 1)[:, None, None]
        axis = np.linalg.eigh(cov)[1][:, :, 2]                                 # main direction of each piece
        horizontal = np.abs(axis[:, 2]) < 0.5
        in_front = (mean[:, 2] >= z_chin) & (cy - mean[:, 1] > 0) & (np.abs(mean[:, 0] - cx) < (cy - mean[:, 1]) * np.tan(np.radians(70)))
        spared = np.nonzero(horizontal & in_front & (size >= 20))[0]
        spared = spared[spared > 0]
        if len(spared):
            keep |= np.isin(labels, spared)
            loose = solid & ~keep
    share = float(loose.sum()) / float(solid.sum())
    if share < 0.0005:
        return m, 0.0
    field = ndimage.gaussian_filter(keep.astype(np.float32), 1.0)
    verts, faces, _, _ = measure.marching_cubes(field, level=0.5)
    hull = trimesh.Trimesh(verts * pitch + origin, faces[:, ::-1], process=True)
    if hull.volume < 0:
        hull.invert()
    # the keep volume only has to be right where it cuts; a coarse hull makes the boolean ten times faster
    cut = from_manifold(as_manifold(m) ^ as_manifold(hull).simplify(0.5 * pitch))
    if not len(cut.faces) or not cut.is_watertight or cut.volume < 0.5 * m.volume:
        return m, 0.0
    return cut, share


def face_front(m):
    """
    Busts do not always arrive looking at -Y. Shoulders are the wide axis; the head sits in front of the chest.
    Returns the quarter turns about Z (0-3) that bring the face to -Y; 0 when the shape gives no clear answer.
    """
    import numpy as np
    c, a = m.triangles_center, m.area_faces
    z0, h = float(m.bounds[0][2]), float(m.extents[2])
    low = (c[:, 2] < z0 + 0.45 * h)
    top = (c[:, 2] > z0 + 0.62 * h)
    if low.sum() < 10 or top.sum() < 10:
        return 0
    spread = c[low][:, :2].max(0) - c[low][:, :2].min(0)
    if max(spread) < 1.25 * min(spread):
        return 0                                                          # no clear shoulder line
    depth_axis = 0 if spread[1] > spread[0] else 1
    chest = np.average(c[low][:, depth_axis], weights=a[low])
    head = np.average(c[top][:, depth_axis], weights=a[top])
    if abs(head - chest) >= 0.05 * h:
        forward = 1 if head > chest else -1
    else:
        # an upright head stands right above the chest; then the face gives itself away by its relief:
        # brows, nose, lips and chin make surfaces that look up and down, the back of a head falls straight
        forward = face_side(c, a, m.face_normals, z0, h, depth_axis)
        if forward == 0:
            return 0
    # quarter turns counter-clockwise: +X -> 3 (to -Y), +Y -> 2, -X -> 1, -Y -> 0
    return {(0, 1): 3, (1, 1): 2, (0, -1): 1, (1, -1): 0}[(depth_axis, forward)]


def face_side(c, a, n, z0, h, axis):
    """+1 / -1: the side of the depth axis the face is on, judged by the relief of the middle strip of the head; 0 = unclear."""
    import numpy as np
    other = 1 - axis
    z1 = z0 + h
    levels = np.linspace(z0 + 0.3 * h, z0 + 0.97 * h, 60)
    width = np.zeros(len(levels) - 1)
    for i, (lo, hi) in enumerate(zip(levels[:-1], levels[1:])):
        row = c[(c[:, 2] >= lo) & (c[:, 2] < hi)]
        width[i] = float(np.ptp(row[:, other])) if len(row) > 20 else 0.0
    if (width <= 0).any():
        return 0
    head = len(width) - 25 + int(np.argmax(width[-25:]))
    first = max(0, head - 30)
    if head <= first:
        return 0
    neck = float(levels[first + int(np.argmin(width[first:head]))])
    tall = z1 - neck
    mid = float(c[:, other].min() + c[:, other].max()) / 2
    strip = (c[:, 2] > neck + 0.15 * tall) & (c[:, 2] < neck + 0.7 * tall) & (np.abs(c[:, other] - mid) < 0.22 * width[head])
    relief = []
    for sign in (1, -1):
        side = strip & (n[:, axis] * sign > 0.3)
        if side.sum() < 50:
            return 0
        relief.append(float(np.average(np.abs(n[side][:, 2]), weights=a[side])))
    if abs(relief[0] - relief[1]) < 0.02:
        return 0
    return 1 if relief[0] > relief[1] else -1


def seat_of(m):
    """
    Where the base has to reach: the lowest level at which the figure is really there. A hanging hand or a strand
    of hair below the chest must not decide it, otherwise the body floats above the base.
    Returns (z_seat, cx, cy, r) from the slice of the body just above that level.
    """
    import numpy as np
    z0, h = float(m.bounds[0][2]), float(m.extents[2])
    z_seat = z0
    try:
        import manifold3d as M
        man = M.Manifold(M.Mesh(vert_properties=np.asarray(m.vertices, dtype=np.float32), tri_verts=np.asarray(m.faces, dtype=np.uint32)))
        levels = np.linspace(z0 + 0.003 * h, z0 + 0.35 * h, 48)
        areas = np.array([man.slice(float(z)).area() for z in levels])
        if areas.max() > 0:
            z_seat = float(levels[int(np.argmax(areas >= 0.5 * areas.max()))])
    except Exception:
        pass
    v = m.vertices
    band = v[(v[:, 2] >= z_seat) & (v[:, 2] <= z_seat + 0.10 * h)]
    if len(band) < 10:
        band = v[v[:, 2] <= z0 + 0.10 * h]
    cx, cy = float((band[:, 0].min() + band[:, 0].max()) / 2), float((band[:, 1].min() + band[:, 1].max()) / 2)
    r = float(np.percentile(np.hypot(band[:, 0] - cx, band[:, 1] - cy), 92)) * 1.08
    return z_seat, cx, cy, r


def without_pedestal(m):
    """Older generated files carry their base as a separate closed part standing on the bed; take it away again."""
    import trimesh
    parts = m.split(only_watertight=False)
    rest = [p for p in parts if not (p.is_watertight and abs(float(p.bounds[0][2]) - float(m.bounds[0][2])) < 0.05 and float(p.extents[2]) < 0.4 * float(m.extents[2]) and len(p.faces) < 20000)]
    if len(rest) == len(parts) or not rest:
        raise ValueError("no_separate_pedestal")
    return trimesh.util.concatenate(rest)


def pedestal_mesh(kind, cx, cy, r, ped_h, overlap, extras, z_top=None, sizes=None):
    """
    Base under a figure or bust, built as one exact solid (manifold3d) and handed back as a trimesh.
    "socle" and "antique" are the feet of a sculptor's bust (see BUST_STYLES, socle_mesh, antique_mesh).
    "plaque" is a taller plinth with a flat front that carries a name and an optional dedication, raised 1 mm.
    The front of a generated model looks towards -Y.
    """
    import os
    import numpy as np
    import trimesh
    import manifold3d as M
    sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
    import shape2d as S

    def rounded(w, d, rad):
        return S.rounded_rect(M, w, d, rad).translate([-w / 2, -d / 2])

    note = {"pedestal": kind}
    if kind in ("socle", "antique"):
        build = socle_mesh if kind == "socle" else antique_mesh
        return build(M, S, cx, cy, extras, overlap if z_top is None else z_top, overlap, sizes or socle_sizes(ped_h / 0.05, r, kind), note)
    if kind == "square":
        solid = rounded(2 * r, 2 * r, r * 0.12).extrude(ped_h + overlap)
    elif kind == "hexagon":
        a = np.linspace(0, 2 * np.pi, 6, endpoint=False) + np.pi / 6
        solid = M.CrossSection([np.stack([r * 1.12 * np.cos(a), r * 1.12 * np.sin(a)], 1)]).extrude(ped_h + overlap)
    elif kind == "column":
        ped_h = ped_h * 2.2
        step = ped_h * 0.35
        solid = M.Manifold.cylinder(step, r * 1.15, r * 1.15, 96) + M.Manifold.cylinder(ped_h + overlap - step, r * 0.92, r * 0.92, 96).translate([0, 0, step])
    elif kind == "plaque":
        ped_h = max(ped_h * 2.6, 12.0)
        w, d = 2 * r * 1.05, 2 * r * 0.9
        solid = rounded(w, d, 2.0).extrude(ped_h + overlap)
        lines = [t for t in (str(extras.get("name", "")).strip()[:24], str(extras.get("dedication", "")).strip()[:40]) if t]
        font = extras.get("font")
        if lines and font and os.path.isfile(font):
            try:
                y_top = ped_h * 0.86
                for i, line in enumerate(lines):
                    band = ped_h * (0.42 if i == 0 else 0.24)            # the name is the big line, the dedication the small one
                    cs, info = S.text(M, [line], font, band * 0.72)
                    tw, th = S.size(cs)
                    if tw > w * 0.86:
                        cs = S.fit(cs, width_mm=w * 0.86)
                        tw, th = S.size(cs)
                    x0, y0, _, _ = cs.bounds()
                    flat = cs.translate([-x0 - tw / 2, -y0]).extrude(1.2)
                    y_top -= band
                    # up stays up, the extrusion points at the viewer (-Y); 0.2 mm sits inside the plinth so the union is clean
                    solid = solid + flat.rotate([90, 0, 0]).translate([0, -d / 2 + 0.2, y_top + (band - th) / 2])
                    if info.get("missing_chars"):
                        note["missing_chars"] = info["missing_chars"]
                note["engraved_lines"] = len(lines)
            except Exception as e:  # noqa: BLE001 - a name that cannot be set must never lose the customer their model
                note["text_error"] = str(e)[:120]
    else:
        kind = "round"
        solid = M.Manifold.cylinder(ped_h + overlap, r, r, 96)
    # the top of the base ends `overlap` inside the figure
    mesh = solid.translate([cx, cy, (overlap if z_top is None else z_top) - (ped_h + overlap)]).to_mesh()
    note["pedestal"] = kind
    note["pedestal_height"] = round(float(ped_h), 1)
    return trimesh.Trimesh(vertices=np.asarray(mesh.vert_properties)[:, :3], faces=np.asarray(mesh.tri_verts), process=True), note


# Three ways a sculptor finishes a bust. Sizes are shares of the height of the bust itself (crown to the end of
# the chest), measured on the reference pictures the owner chose:
#   socle   - modern studio bust: chest with a rounded underside on a turned foot, the name round its band
#   antique - Roman bust: deeply rounded chest, a small tablet with the name, a turned socle with a wide foot
#   cut     - sculptor's cut: no foot, the bust stands on its own flat cut, the shoulders are cut off at a slant
BUST_STYLES = {
    "socle": {"chest": 0.88, "more": 0.22, "round": 0.13, "power": 2.0, "flat": 0.17,
              "foot": 0.35, "band": 0.135, "r_foot": 0.216, "r_top": 0.128},
    "antique": {"chest": 0.95, "more": 0.26, "round": 0.24, "power": 1.7, "flat": 0.19,
                "foot": 0.25, "r_foot": 0.20, "r_drum": 0.15, "r_waist": 0.115,
                "tablet_h": 0.085, "tablet_w": 0.27, "tablet_d": 0.17},
    # the added chest is a plain wall: under a slanted cut only a low one looks like the work of a sculptor
    "cut": {"chest": 0.8, "more": 0.10, "lean": 24.0, "keep": 0.6},
}


def socle_sizes(bust_h, r, kind="socle"):
    """Sizes in millimetres for a bust `bust_h` tall whose chest has the radius `r` at the cut."""
    k = BUST_STYLES[kind]
    out = {"kind": kind, "bust_h": float(bust_h)}
    for key, share in k.items():
        out[key] = share if key in ("chest", "more", "power", "lean", "keep") else share * bust_h
    if kind == "socle":
        out["r_foot"] = min(out["r_foot"], max(r * 0.9, out["r_top"] * 1.3))
        out["r_top"] = min(out["r_top"], out["r_foot"] * 0.62)
        out["band"] = max(5.0, out["band"])
    return out


def neck_level(m, z_from):
    """Height of the narrowest place between the shoulders and the head, or None when the shape shows no neck."""
    import numpy as np
    v = m.vertices
    z0, z1 = float(z_from), float(m.bounds[1][2])
    h = z1 - z0
    low = v[(v[:, 2] >= z0) & (v[:, 2] < z0 + 0.4 * h)]
    if len(low) < 50:
        return None
    axis = int(np.argmax(np.ptp(low[:, :2], axis=0)))
    levels = np.linspace(z0 + 0.15 * h, z0 + 0.97 * h, 70)
    width = np.zeros(len(levels) - 1)
    for i, (lo, hi) in enumerate(zip(levels[:-1], levels[1:])):
        row = v[(v[:, 2] >= lo) & (v[:, 2] < hi)]
        width[i] = float(np.ptp(row[:, axis])) if len(row) > 20 else 0.0
    if (width <= 0).any():
        return None
    head = len(width) - 28 + int(np.argmax(width[-28:]))
    first = max(0, head - 34)
    if head <= first:
        return None
    neck = first + int(np.argmin(width[first:head]))
    if width[neck] > 0.92 * width[head]:
        return None
    return float(levels[neck])


def polar_reach(M, outline, floor, count=180):
    """How far the (convex) outline reaches from the centre in `count` directions; never less than `floor`."""
    import numpy as np
    ang = np.linspace(0, 2 * np.pi, count, endpoint=False)
    dirs = np.stack([np.cos(ang), np.sin(ang)], 1)
    reach = np.full(count, float(floor))
    for poly in outline.to_polygons():
        pts = np.asarray(poly, dtype=np.float64)
        e = np.roll(pts, -1, axis=0) - pts
        den = dirs[:, None, 0] * e[None, :, 1] - dirs[:, None, 1] * e[None, :, 0]
        den = np.where(np.abs(den) < 1e-12, np.nan, den)
        t = (pts[None, :, 0] * e[None, :, 1] - pts[None, :, 1] * e[None, :, 0]) / den
        u = (pts[None, :, 0] * dirs[:, None, 1] - pts[None, :, 1] * dirs[:, None, 0]) / den
        hit = np.where((u >= 0) & (u <= 1) & (t > 0), t, np.nan)
        with np.errstate(all="ignore"):
            reach = np.fmax(reach, np.nan_to_num(np.nanmax(hit, axis=1), nan=0.0))
    return dirs, reach


def rings_solid(M, rings):
    """Closed solid from rings of points stacked bottom to top (the same count in every ring)."""
    import numpy as np
    n = len(rings[0])
    verts = np.vstack(list(rings) + [np.array([[0, 0, rings[0][0][2]], [0, 0, rings[-1][0][2]]])])
    faces = []
    for i in range(len(rings) - 1):
        for j in range(n):
            a0, a1, b0, b1 = i * n + j, i * n + (j + 1) % n, (i + 1) * n + j, (i + 1) * n + (j + 1) % n
            faces += [[a0, a1, b1], [a0, b1, b0]]
    low, high = len(rings) * n, len(rings) * n + 1
    for j in range(n):
        faces.append([low, (j + 1) % n, j])
        faces.append([high, (len(rings) - 1) * n + j, (len(rings) - 1) * n + (j + 1) % n])
    solid = M.Manifold(M.Mesh(vert_properties=verts.astype(np.float32), tri_verts=np.asarray(faces, dtype=np.uint32)))
    if solid.status() != M.Error.NoError or solid.volume() <= 0:
        raise ValueError("rings_solid")
    return solid


def longer_chest(body, m, z_cut, share, limit, least=0.0):
    """
    Generated busts end at the shoulders; a sculptor's bust carries about as much chest below the neck as there
    is head above it. The missing part is drawn straight down from the cut, the way the chest already falls there.
    Returns (body, new bottom).
    """
    neck = neck_level(m, z_cut)
    top = float(m.bounds[1][2])
    if neck is None:
        more = 0.12 * (top - z_cut)
    else:
        more = share * (top - neck) - (neck - z_cut)
    # `least`: the rounded underside needs room of its own, it must not eat the chest the model came with
    more = min(max(least * (top - z_cut), more), limit * (top - z_cut))
    if more < 0.5:
        return body, float(z_cut)
    section = body.slice(float(z_cut + 0.3))
    if section.area() <= 0:
        return body, float(z_cut)
    return body + section.extrude(more + 0.6).translate([0, 0, z_cut - more]), float(z_cut - more)


def round_under(body, cx, cy, z_bottom, high, flat, power):
    """
    Underside of the chest: flat over the foot (radius `flat`), then up to the chest in a rounded sweep `high` tall.
    Material is only taken away, so the chest above keeps every fold it has.
    """
    import numpy as np
    import manifold3d as M
    outline = body.slice(float(z_bottom + high)).translate([-cx, -cy]).hull()
    if outline.area() < 3.2 * flat ** 2:
        return body                                             # a chest hardly wider than the foot needs no shaping
    dirs, reach = polar_reach(M, outline, flat)
    reach = reach + 0.3
    inner = np.minimum(reach, flat)
    rings = []
    for k in np.linspace(0.0, 1.0, 33):
        grow = (1 - (1 - k) ** power) ** (1 / power)
        rho = inner + (reach - inner) * grow
        rings.append(np.column_stack([dirs * rho[:, None], np.full(len(rho), z_bottom - 0.5 + (high + 0.5) * k)]))
    # past the slab: two lids in one plane would leave a skin inside the body
    rings.append(np.column_stack([dirs * reach[:, None], np.full(len(reach), z_bottom + high + 1.0)]))
    bowl = rings_solid(M, rings).translate([cx, cy, 0])
    x0, y0, _, x1, y1, _ = body.bounding_box()
    slab = M.Manifold.cube([x1 - x0 + 4, y1 - y0 + 4, high + 1.0]).translate([x0 - 2, y0 - 2, z_bottom - 1.0])
    shaped = body - (slab - bowl)
    return shaped if shaped.volume() > 0.5 * body.volume() else body


def slant_cut(body, z_bottom, lean_deg, keep):
    """Sculptor's cut: both shoulders are cut off by planes that lean outwards, the bust stands on its own flat cut."""
    import numpy as np
    x0, _, _, x1, _, z1 = body.bounding_box()
    mid, half = (x0 + x1) / 2, (x1 - x0) / 2
    a = np.radians(lean_deg)
    # never cut into the neck: at the top of the chest the planes are still outside two thirds of the width
    foot = min(keep * half, half - np.tan(a) * 0.0)
    for side in (1, -1):
        normal = [-side * np.cos(a), 0.0, np.sin(a)]
        point = np.array([mid + side * foot, 0.0, z_bottom])
        body = body.trim_by_plane(normal, float(np.dot(normal, point)))
    return body


def bust_shape(kind, body, m, cx, cy, z_cut, r):
    """The chest of the bust the way the style wants it. Returns (body, bottom of the chest, sizes of the foot)."""
    style = BUST_STYLES[kind]
    before = float(m.bounds[1][2]) - float(z_cut)
    body, bottom = longer_chest(body, m, float(z_cut), style["chest"], style["more"], style.get("round", 0.0) * 0.8)
    sizes = socle_sizes(float(m.bounds[1][2]) - bottom, r, kind)
    sizes["added"] = round((float(m.bounds[1][2]) - bottom) - before, 1)
    if kind == "cut":
        return slant_cut(body, bottom, style["lean"], style["keep"]), bottom, sizes
    # the sweep never eats the chest the model came with: it lives in the part that was added, and a little above
    high = min(sizes["round"], sizes["added"] + 0.05 * sizes["bust_h"])
    if high > 1.0:
        body = round_under(body, cx, cy, bottom, high, sizes["flat"], style["power"])
    return body, bottom, sizes


def raised_name(M, S, extras, cap, width, note):
    """The name in capitals as a flat outline `cap` tall, no wider than `width`, centred on x, standing on y = 0."""
    import os
    name = str(extras.get("name", "")).strip()[:24]
    font = extras.get("font")
    if not name or not font or not os.path.isfile(font):
        return None
    cs, info = S.text(M, [name.upper()], font, cap)
    tw, th = S.size(cs)
    if tw > width:
        cs = S.fit(cs, width_mm=width)
        tw, th = S.size(cs)
    bx, by, _, _ = cs.bounds()
    note["engraved_lines"] = 1
    if th < 3.0:
        note["small_text_mm"] = round(float(th), 1)
    if info.get("missing_chars"):
        note["missing_chars"] = info["missing_chars"]
    return cs.translate([-bx - tw / 2, -by]), th


def socle_mesh(M, S, cx, cy, extras, z_top, overlap, sizes, note):
    """
    Turned foot of a modern bust, from the bed up: a round band that carries the name (capitals, bent round the
    band, facing the viewer at -Y), a bead, a hollow waist and a collar under the chest.
    """
    import numpy as np
    import trimesh

    height, band, r_foot, r_top = sizes["foot"], sizes["band"], sizes["r_foot"], sizes["r_top"]
    edge = min(1.2, band * 0.15)
    bead = max(0.8, band * 0.12)
    collar = max(1.2, height * 0.06)
    r_collar = r_top * 1.22
    flare = r_collar - r_top                                  # 45 degrees, prints without supports
    x0, y0 = r_foot * 0.95, band + bead
    y1 = height - collar - flare                              # the waist is narrowest here
    prof = [(0.0, 0.0), (r_foot - edge, 0.0), (r_foot, edge), (r_foot, band), (x0, y0)]
    for t in np.linspace(0.0, 1.0, 28)[1:]:
        # a hollow: it leaves the band flat and arrives upright at the waist
        prof.append((r_top + (x0 - r_top) * (1 - np.sin(t * np.pi / 2)), y0 + (y1 - y0) * (1 - np.cos(t * np.pi / 2))))
    prof += [(r_collar, y1 + flare), (r_collar, height + overlap), (0.0, height + overlap)]
    solid = M.CrossSection([np.array(prof, dtype=np.float64)]).revolve(144)

    try:
        made = raised_name(M, S, extras, band * 0.46, r_foot * np.radians(140), note)
        if made:
            cs, th = made
            raised = max(0.7, band * 0.09)
            flat = cs.translate([0, edge + (band - edge - th) / 2]).extrude(raised + 0.4).refine_to_length(0.5)

            def bend(v):
                # x runs round the band, y is the height, the extrusion points out of the band; 0.4 mm sits inside it
                a, rho = v[0] / r_foot, r_foot - 0.4 + v[2]
                return [rho * np.sin(a), -rho * np.cos(a), v[1]]

            solid = solid + flat.warp(bend)
    except Exception as e:  # noqa: BLE001 - a name that cannot be set must never lose the customer their model
        note["text_error"] = str(e)[:120]

    mesh = solid.translate([cx, cy, z_top - overlap - height]).to_mesh()
    note["pedestal"] = "socle"
    note["pedestal_height"] = round(float(height), 1)
    return trimesh.Trimesh(vertices=np.asarray(mesh.vert_properties)[:, :3], faces=np.asarray(mesh.tri_verts), process=True), note


def antique_mesh(M, S, cx, cy, extras, z_top, overlap, sizes, note):
    """
    Socle of a Roman bust, from the bed up: a wide foot with a rounded edge, a deep hollow, a drum with a rounded
    shoulder, and on it the small tablet that carries the name and the bust.
    """
    import numpy as np
    import trimesh

    height, r_foot, r_drum, r_waist = sizes["foot"], sizes["r_foot"], sizes["r_drum"], sizes["r_waist"]
    tab_h, tab_w, tab_d = sizes["tablet_h"], sizes["tablet_w"], sizes["tablet_d"]
    turned = height - tab_h
    foot_h, drum_h = turned * 0.26, turned * 0.34
    hollow = turned - foot_h - drum_h

    def arc(xc, yc, rad, a0, a1, n=10):
        t = np.radians(np.linspace(a0, a1, n))
        return [(xc + rad * np.cos(k), yc + rad * np.sin(k)) for k in t]

    rf = foot_h / 2                                           # the edge of the foot is half a circle
    prof = [(0.0, 0.0), (r_foot - rf, 0.0)] + arc(r_foot - rf, rf, rf, -90, 90)
    x0, y0, y1 = r_foot - rf - 0.2, foot_h, foot_h + hollow
    for t in np.linspace(0.0, 1.0, 26)[1:-1]:
        # the hollow: in from the foot, narrowest a little above the middle, out again to the drum
        bulge = np.sin(t * np.pi) ** 0.8
        line = x0 + (r_drum - x0) * t
        prof.append((line - (line - r_waist) * bulge, y0 + (y1 - y0) * t))
    rd = drum_h * 0.3
    prof += [(r_drum, y1)] + arc(r_drum - rd, turned - rd, rd, 0, 90)[1:] + [(0.0, turned)]
    solid = M.CrossSection([np.array(prof, dtype=np.float64)]).revolve(144)

    tablet = S.rounded_rect(M, tab_w, tab_d, min(1.0, tab_h * 0.1)).translate([-tab_w / 2, -tab_d / 2]).extrude(tab_h + overlap + 0.3).translate([0, 0, turned - 0.3])
    solid = solid + tablet
    try:
        made = raised_name(M, S, extras, tab_h * 0.5, tab_w * 0.86, note)
        if made:
            cs, th = made
            raised = max(0.7, tab_h * 0.1)
            flat = cs.extrude(raised + 0.3)
            # up stays up, the extrusion points at the viewer (-Y); 0.3 mm sits inside the tablet
            solid = solid + flat.rotate([90, 0, 0]).translate([0, -tab_d / 2 + 0.3, turned + (tab_h - th) / 2])
    except Exception as e:  # noqa: BLE001
        note["text_error"] = str(e)[:120]

    mesh = solid.translate([cx, cy, z_top - overlap - height]).to_mesh()
    note["pedestal"] = "antique"
    note["pedestal_height"] = round(float(height), 1)
    return trimesh.Trimesh(vertices=np.asarray(mesh.vert_properties)[:, :3], faces=np.asarray(mesh.tri_verts), process=True), note


def cad_to_stl(src, dst, lin=0.05, ang=0.3):
    """STEP/IGES/BREP → STL through OpenCascade (same kernel FreeCAD uses)."""
    import cadquery as cq
    from cadquery import exporters
    ext = src.lower().rsplit(".", 1)[-1]
    if ext in ("step", "stp"):
        shape = cq.importers.importStep(src)
    elif ext in ("iges", "igs"):
        # cadquery has no IGES importer; use OCP directly
        from OCP.IGESControl import IGESControl_Reader
        from OCP.IFSelect import IFSelect_RetDone
        reader = IGESControl_Reader()
        if reader.ReadFile(src) != IFSelect_RetDone:
            raise ValueError("cannot read IGES")
        reader.TransferRoots()
        shape = cq.Workplane("XY").newObject([cq.Shape.cast(reader.OneShape())])
    elif ext == "brep":
        from OCP.BRepTools import BRepTools
        from OCP.TopoDS import TopoDS_Shape
        from OCP.BRep import BRep_Builder
        s = TopoDS_Shape()
        BRepTools.Read_s(s, src, BRep_Builder())
        shape = cq.Workplane("XY").newObject([cq.Shape.cast(s)])
    else:
        raise ValueError("unsupported CAD format " + ext)
    exporters.export(shape, dst, exportType="STL", tolerance=lin, angularTolerance=ang)


def main(argv):
    if len(argv) < 2:
        fail("usage")
    cmd = argv[1]
    try:
        if cmd == "probe":
            import trimesh
            try:
                import pymeshfix  # noqa: F401
                pf = True
            except Exception:
                pf = False
            try:
                import cadquery  # noqa: F401
                cad = True
            except Exception:
                cad = False
            out({"ok": True, "trimesh": trimesh.__version__, "pymeshfix": pf, "cad": cad})
        if cmd == "check":
            out(report(load(argv[2])))
        if cmd == "convert":
            m = load(argv[2])
            m.export(argv[3], file_type="stl")
            out(report(m, {"out": argv[3]}))
        if cmd == "normalize":
            import numpy as np
            import trimesh
            m = trimesh.load(argv[2], force="mesh")
            if isinstance(m, trimesh.Scene):
                m = trimesh.util.concatenate(list(m.geometry.values()))
            if m.is_empty or len(m.faces) == 0:
                raise ValueError("mesh has no faces")
            target = float(argv[4])
            if len(argv) > 5 and argv[5] == "1":
                # glTF is Y-up; printers are Z-up: rotate +90 degrees about X
                m.apply_transform(trimesh.transformations.rotation_matrix(np.pi / 2, [1, 0, 0]))
            ext = float(max(m.extents))
            if ext <= 0:
                raise ValueError("degenerate mesh")
            opts = set((argv[6] if len(argv) > 6 else "").split(","))
            try:
                extras = json.loads(argv[7]) if len(argv) > 7 and argv[7] else {}
            except ValueError:
                extras = {}
            ped_note = {}
            m.apply_scale(target / ext)
            removed = 0
            if "clean" in opts:
                # generated meshes carry tiny floating fragments; keep everything that is a real part (glasses, hair)
                parts = m.split(only_watertight=False)
                if len(parts) > 1:
                    limit = target * 0.02
                    keep = [p for p in parts if float(max(p.extents)) >= limit]
                    removed = len(parts) - len(keep)
                    if keep and removed:
                        m = trimesh.util.concatenate(keep)
            if extras.get("strip_pedestal"):
                m = without_pedestal(m)
            m.apply_translation([-m.bounds[0][0], -m.bounds[0][1], -m.bounds[0][2]])
            if "solid" in opts:
                m = solidify(m, target, None)
                if len(m.faces) > 400000 and m.is_watertight:
                    # a detailed generation arrives with millions of faces; 0.02 mm is far below what a nozzle shows
                    m = simplified(m, 0.012)
            if extras.get("cut") == "bust" and m.is_watertight:
                before = float(m.extents[2])
                m = bust_cut(m)
                if float(m.extents[2]) < before - 0.01:
                    ped_note["bust_cut"] = True
                    m.apply_scale(target / float(max(m.extents)))
            front = str(extras.get("front", "keep"))
            turns = face_front(m) if front == "auto" else {"right": 3, "back": 2, "left": 1}.get(front, 0)      # where the face looks now, seen from the front
            if turns:
                m.apply_transform(trimesh.transformations.rotation_matrix(turns * np.pi / 2, [0, 0, 1]))
                ped_note["turned"] = turns * 90
            m.apply_translation([-m.bounds[0][0], -m.bounds[0][1], -m.bounds[0][2]])
            if extras.get("source_out"):
                # the figure alone, closed and facing the front: a different base later costs no new generation
                m.export(str(extras["source_out"]), file_type="stl")
            if extras.get("tidy") and m.is_watertight:
                # loose strands and flaps need supports and snap off; the stored source above keeps them
                try:
                    m, share = trim_loose(m, target)
                    if share > 0:
                        ped_note["trimmed_share"] = round(share, 4)
                except Exception as e:  # noqa: BLE001 - never lose the model over a cosmetic step
                    ped_note["trim_error"] = str(e)[:120]
            sink = min(0.4, max(0.0, float(extras.get("sink", 0) or 0)))
            if sink > 0 and m.is_watertight:
                # "lower into the base": more of the chest goes, the stored source above stays whole
                try:
                    lowered = from_manifold(as_manifold(m).trim_by_plane([0, 0, 1], float(m.bounds[0][2]) + sink * float(m.extents[2])))
                    if len(lowered.faces) and lowered.is_watertight:
                        m = lowered
                        m.apply_translation([0, 0, -float(m.bounds[0][2])])
                        ped_note["sink"] = sink
                except Exception:
                    pass
            kind = str(extras.get("pedestal", "round"))
            if "pedestal" in opts and kind != "none":
                # base under the figure: flat first layer, no supports under a ragged cut, stands on a shelf
                z_seat, cx, cy, r = seat_of(m)
                r = max(r, target * 0.18)
                r = min(r, float(max(m.extents[0], m.extents[1])) * 0.55)   # never much wider than the figure itself
                ped_h = max(3.0, target * 0.05)
                overlap = max(2.0, target * 0.035)
                whole = m
                shaped_kinds = tuple(BUST_STYLES)
                for kind in ([kind, "round"] if kind in ("socle", "antique") else [kind]):
                    m, ped, joined = whole, None, None
                    try:
                        sizes = None
                        z_base = float(z_seat)
                        if kind in shaped_kinds:
                            if not m.is_watertight:
                                raise ValueError("open_body")
                            # nothing of the figure may hang below the cut; then the chest gets the end the style asks for
                            body, z_base, sizes = bust_shape(kind, as_manifold(m).trim_by_plane([0, 0, 1], float(z_seat)), m, cx, cy, z_seat, r)
                            shaped = from_manifold(body)
                            if not (len(shaped.faces) and shaped.is_watertight):
                                raise ValueError("shape_failed")
                            m = shaped
                            ped_note["chest_added"] = sizes["added"]
                        if kind == "cut":
                            note = {"pedestal": "cut", "pedestal_height": 0.0}
                        else:
                            ped, note = pedestal_mesh(kind, cx, cy, r, ped_h, overlap, extras, z_base + overlap, sizes)
                    except Exception as e:  # noqa: BLE001 - fall back to the plain round base
                        if kind == "cut":
                            m, ped, note = whole, None, {"pedestal": "none", "pedestal_error": str(e)[:120]}
                        elif kind in shaped_kinds:
                            ped_note = {k: v for k, v in ped_note.items() if k not in ("engraved_lines", "small_text_mm", "chest_added")}
                            ped_note["socle_fallback"] = str(e)[:120]
                            continue
                        else:
                            ped = trimesh.creation.cylinder(radius=r, height=ped_h + overlap, sections=96)
                            ped.apply_translation([cx, cy, z_seat + overlap - (ped_h + overlap) / 2.0])
                            note = {"pedestal": "round", "pedestal_error": str(e)[:120]}
                    ped_note.update(note)
                    if ped is None:
                        joined = m
                        break
                    floor = float(ped.bounds[0][2])
                    if float(m.bounds[0][2]) < floor - 0.01:
                        # whatever hangs below the base (a hand, a strand of hair) would lift the print off the bed
                        try:
                            cut = from_manifold(as_manifold(m).trim_by_plane([0, 0, 1], floor))
                            m = cut if len(cut.faces) and cut.is_watertight else m
                        except Exception:
                            pass
                    if "solid" in opts and m.is_watertight:
                        try:
                            u = from_manifold(as_manifold(m) + as_manifold(ped))
                            joined = u if len(u.faces) and u.is_watertight else None
                        except Exception:
                            joined = None
                    if joined is not None or kind not in shaped_kinds:
                        break
                    # a foot narrower than the chest must be one body with it, otherwise the bust floats over it
                    ped_note = {k: v for k, v in ped_note.items() if k not in ("engraved_lines", "small_text_mm", "chest_added")}
                    ped_note["socle_fallback"] = "not_joined"
                m = joined if joined is not None else trimesh.util.concatenate([m, ped])
                m.apply_translation([-m.bounds[0][0], -m.bounds[0][1], -m.bounds[0][2]])
                m.apply_scale(target / float(max(m.extents)))
            m.export(argv[3], file_type="stl")
            out(report(m, dict({"out": argv[3], "removed_fragments": removed}, **ped_note)))
        if cmd == "cad":
            lin = float(argv[4]) if len(argv) > 4 else 0.05
            ang = float(argv[5]) if len(argv) > 5 else 0.3
            cad_to_stl(argv[2], argv[3], lin, ang)
            m = load(argv[3])
            m.export(argv[3], file_type="stl")  # normalise to binary STL
            out(report(m, {"out": argv[3]}))
        if cmd == "repair":
            m = load(argv[2])
            import trimesh
            trimesh.repair.fix_normals(m)
            trimesh.repair.fix_winding(m)
            trimesh.repair.fill_holes(m)
            if hasattr(m, "remove_degenerate_faces"):
                m.remove_degenerate_faces()
            if not m.is_watertight:
                try:
                    import pymeshfix
                    fx = pymeshfix.MeshFix(m.vertices, m.faces)
                    fx.repair()
                    m = trimesh.Trimesh(*meshfix_arrays(fx))
                except Exception:
                    pass
            m.export(argv[3], file_type="stl")
            out(report(m, {"out": argv[3]}))
        fail("unknown command " + cmd)
    except SystemExit:
        raise
    except Exception as e:  # noqa: BLE001
        fail(e)


if __name__ == "__main__":
    main(sys.argv)
