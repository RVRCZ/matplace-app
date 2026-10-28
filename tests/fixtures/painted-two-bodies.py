"""tests/fixtures/painted-two-bodies.3mf: two cubes painted the way Bambu Studio / OrcaSlicer / Anycubic Slicer Next save
it. Cube A (20 mm, filament 1 green) carries a yellow sticker on its top made of two 4 x 4 blocks of cells that touch at
one corner only (a bow-tie vertex, the turtle's bug of 28 Oct 2026) and one white cell (0.1 %, merged); cube B (15 mm,
filament 4 red) is plain. Every face is a 10 x 10 grid of cells, two triangles each."""
import json, sys, zipfile

N = 10
FIL = ['#80FF80', '#FFFF00', '#FFFFFF', '#E53935']
CODE = {1: '4', 2: '8', 3: '0C'}   # paint_color of an unsplit triangle in filament 1, 2, 3


def cube(size, ox, paint):
    verts, index, tris = [], {}, []

    def v(p):
        p = tuple(round(c, 4) for c in p)
        if p not in index:
            index[p] = len(verts)
            verts.append(p)
        return index[p]

    s = size / N
    # (origin, u, w, outward normal sign) per face; a cell (i, j) is origin + i*u + j*w
    faces = [
        ('top', (0, 0, size), (s, 0, 0), (0, s, 0), False),
        ('bottom', (0, 0, 0), (0, s, 0), (s, 0, 0), False),
        ('front', (0, 0, 0), (s, 0, 0), (0, 0, s), False),
        ('back', (0, size, 0), (0, 0, s), (s, 0, 0), False),
        ('left', (0, 0, 0), (0, 0, s), (0, s, 0), False),
        ('right', (size, 0, 0), (0, s, 0), (0, 0, s), False),
    ]
    for name, o, u, w, _ in faces:
        for i in range(N):
            for j in range(N):
                pt = lambda a, b: (o[0] + a * u[0] + b * w[0] + ox, o[1] + a * u[1] + b * w[1], o[2] + a * u[2] + b * w[2])
                a, b, c, d = v(pt(i, j)), v(pt(i + 1, j)), v(pt(i + 1, j + 1)), v(pt(i, j + 1))
                state = paint(name, i, j)
                tris.append((a, b, c, state))
                tris.append((a, c, d, state))
    return verts, tris


HOOK = {(i, j) for i in range(2, 5) for j in range(2, 5)} | {(i, j) for i in range(5, 8) for j in range(5, 8)} | {(2, 5), (2, 6), (2, 7), (3, 7), (4, 7)}
HOOK -= {(3, 3)}


def sticker(name, i, j):
    if name != 'top':
        return 0
    # a hook of cells that comes round and touches itself at one corner, the vertex (5, 5): one patch with a bow-tie
    if (i, j) in HOOK:
        return 2
    if i == 8 and j == 1:                # a white dot, 4 mm2 of 3750
        return 3
    return 0


def obj(oid, verts, tris):
    x = ['<object id="%d" type="model"><mesh><vertices>' % oid]
    x += ['<vertex x="%.4f" y="%.4f" z="%.4f"/>' % p for p in verts]
    x.append('</vertices><triangles>')
    for a, b, c, st in tris:
        x.append('<triangle v1="%d" v2="%d" v3="%d"%s/>' % (a, b, c, (' paint_color="%s"' % CODE[st]) if st else ''))
    x.append('</triangles></mesh></object>')
    return ''.join(x)


va, ta = cube(20.0, 0.0, sticker)
vb, tb = cube(15.0, 30.0, lambda *a: 0)
model = ('<?xml version="1.0" encoding="UTF-8"?><model unit="millimeter" xmlns="http://schemas.microsoft.com/3dmanufacturing/core/2015/02"><resources>'
         + obj(1, va, ta) + obj(2, vb, tb) + '</resources><build><item objectid="1"/><item objectid="2"/></build></model>')
settings = ('<?xml version="1.0" encoding="UTF-8"?><config>'
            '<object id="1"><metadata key="name" value="A"/><metadata key="extruder" value="1"/></object>'
            '<object id="2"><metadata key="name" value="B"/><metadata key="extruder" value="4"/></object></config>')
with zipfile.ZipFile(sys.argv[1], 'w', zipfile.ZIP_DEFLATED) as z:
    z.writestr('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="model" ContentType="application/vnd.ms-package.3dmanufacturing-3dmodel+xml"/></Types>')
    z.writestr('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Target="/3D/3dmodel.model" Id="rel0" Type="http://schemas.microsoft.com/3dmanufacturing/2013/01/3dmodel"/></Relationships>')
    z.writestr('3D/3dmodel.model', model)
    z.writestr('Metadata/model_settings.config', settings)
    z.writestr('Metadata/project_settings.config', json.dumps({'filament_colour': FIL}))
print('ok', len(ta) + len(tb), 'triangles')
