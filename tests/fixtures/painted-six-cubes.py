"""tests/fixtures/painted-six-cubes.3mf: six cubes side by side, each a filament of its own by its object's `extruder`
(the way a slicer saves a multi-material project without painting), the first the largest. Six colours above the four an
S1 holds: on a farm with a Kobra X (7 positions) all six print, on an S1 alone the two smallest join the first
(App\\Domain\\Farm\\ColorLimit, docs/AL.md §19). Every face is a 4 x 4 grid of cells, two triangles each.
Usage: python painted-six-cubes.py painted-six-cubes.3mf"""
import json, sys, zipfile

N = 4
FIL = ['#F4F4F0', '#C01818', '#1830C0', '#18A018', '#E0D000', '#101010']


def cube(size, ox):
    verts, index, tris = [], {}, []

    def v(p):
        p = tuple(round(c, 4) for c in p)
        if p not in index:
            index[p] = len(verts)
            verts.append(p)
        return index[p]

    s = size / N
    faces = [
        ((0, 0, size), (s, 0, 0), (0, s, 0)),
        ((0, 0, 0), (0, s, 0), (s, 0, 0)),
        ((0, 0, 0), (s, 0, 0), (0, 0, s)),
        ((0, size, 0), (0, 0, s), (s, 0, 0)),
        ((0, 0, 0), (0, 0, s), (0, s, 0)),
        ((size, 0, 0), (0, s, 0), (0, 0, s)),
    ]
    for o, u, w in faces:
        for i in range(N):
            for j in range(N):
                pt = lambda a, b: (o[0] + a * u[0] + b * w[0] + ox, o[1] + a * u[1] + b * w[1], o[2] + a * u[2] + b * w[2])
                a, b, c, d = v(pt(i, j)), v(pt(i + 1, j)), v(pt(i + 1, j + 1)), v(pt(i, j + 1))
                tris.append((a, b, c))
                tris.append((a, c, d))
    return verts, tris


def obj(oid, verts, tris):
    x = ['<object id="%d" type="model"><mesh><vertices>' % oid]
    x += ['<vertex x="%.4f" y="%.4f" z="%.4f"/>' % p for p in verts]
    x.append('</vertices><triangles>')
    x += ['<triangle v1="%d" v2="%d" v3="%d"/>' % t for t in tris]
    x.append('</triangles></mesh></object>')
    return ''.join(x)


objects, items, settings = [], [], []
for k in range(6):
    verts, tris = cube(10.0 - 0.4 * k, 15.0 * k)
    objects.append(obj(k + 1, verts, tris))
    items.append('<item objectid="%d"/>' % (k + 1))
    settings.append('<object id="%d"><metadata key="name" value="cube %d"/><metadata key="extruder" value="%d"/></object>' % (k + 1, k + 1, k + 1))
model = ('<?xml version="1.0" encoding="UTF-8"?><model unit="millimeter" xmlns="http://schemas.microsoft.com/3dmanufacturing/core/2015/02"><resources>'
         + ''.join(objects) + '</resources><build>' + ''.join(items) + '</build></model>')
config = '<?xml version="1.0" encoding="UTF-8"?><config>' + ''.join(settings) + '</config>'
with zipfile.ZipFile(sys.argv[1], 'w', zipfile.ZIP_DEFLATED) as z:
    z.writestr('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="model" ContentType="application/vnd.ms-package.3dmanufacturing-3dmodel+xml"/></Types>')
    z.writestr('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Target="/3D/3dmodel.model" Id="rel0" Type="http://schemas.microsoft.com/3dmanufacturing/2013/01/3dmodel"/></Relationships>')
    z.writestr('3D/3dmodel.model', model)
    z.writestr('Metadata/model_settings.config', config)
    z.writestr('Metadata/project_settings.config', json.dumps({'filament_colour': FIL}))
