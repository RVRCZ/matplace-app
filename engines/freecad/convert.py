# FreeCAD console script: STEP/IGES/BREP -> binary STL
# usage: freecadcmd convert.py -- <in> <out.stl> [linear_deflection_mm] [angular_deflection_rad]
import sys

args = [a for a in sys.argv if not a.startswith("-")]
# freecadcmd passes the script path first; find our positional args after it
try:
    idx = next(i for i, a in enumerate(args) if a.endswith("convert.py"))
    args = args[idx + 1:]
except StopIteration:
    args = args[1:]

if len(args) < 2:
    print("usage: convert.py <in> <out.stl> [lin] [ang]")
    sys.exit(2)

src, dst = args[0], args[1]
lin = float(args[2]) if len(args) > 2 else 0.05
ang = float(args[3]) if len(args) > 3 else 0.3

import FreeCAD  # noqa: E402
import Part  # noqa: E402
import Mesh  # noqa: E402
import MeshPart  # noqa: E402

shape = Part.Shape()
shape.read(src)
if shape.isNull():
    print("empty shape")
    sys.exit(3)

mesh = MeshPart.meshFromShape(Shape=shape, LinearDeflection=lin, AngularDeflection=ang, Relative=False)
mesh.write(dst)
print("ok facets=%d" % mesh.CountFacets)
