import sys, trimesh, numpy as np
from trimesh.creation import icosphere, cylinder, box
def t(m,x,y,z): m.apply_translation([x,y,z]); return m
head = t(icosphere(subdivisions=5,radius=12),0,0,82)
neck = icosphere(subdivisions=4,radius=1.0); neck.apply_scale([5.5,5.5,18]); t(neck,0,0,62)
body = icosphere(subdivisions=4,radius=1.0); body.apply_scale([32,13,24]); t(body,0,0,24)
shoul = t(box(extents=[64,26,10]),0,0,45)
import manifold3d as M
def man(m): return M.Manifold(M.Mesh(vert_properties=np.asarray(m.vertices,dtype=np.float32),tri_verts=np.asarray(m.faces,dtype=np.uint32)))
u = man(head)+man(neck)+man(body)
r = u.to_mesh()
mesh = trimesh.Trimesh(vertices=np.asarray(r.vert_properties)[:,:3], faces=np.asarray(r.tri_verts))
mesh.export(sys.argv[1])
print(mesh.is_watertight, mesh.extents)
