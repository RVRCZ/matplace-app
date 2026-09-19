import { BufferGeometry, Box3, Vector3 } from 'three';

export interface GeoStats {
    volume_mm3: number;
    area_mm2: number;
    bbox: { x: number; y: number; z: number };
    triangles: number;
}

/** Volume (signed tetrahedra), surface area and bounding box of a non-indexed or indexed geometry. */
export function stats(geom: BufferGeometry): GeoStats {
    const pos = geom.getAttribute('position');
    const idx = geom.getIndex();
    const n = idx ? idx.count : pos.count;
    const a = new Vector3(), b = new Vector3(), c = new Vector3(), u = new Vector3(), v = new Vector3();
    let volume = 0, area = 0;
    for (let i = 0; i < n; i += 3) {
        const ia = idx ? idx.getX(i) : i, ib = idx ? idx.getX(i + 1) : i + 1, ic = idx ? idx.getX(i + 2) : i + 2;
        a.fromBufferAttribute(pos, ia);
        b.fromBufferAttribute(pos, ib);
        c.fromBufferAttribute(pos, ic);
        volume += a.dot(u.copy(b).cross(c)) / 6;
        area += u.copy(b).sub(a).cross(v.copy(c).sub(a)).length() / 2;
    }
    const box = new Box3().setFromBufferAttribute(pos as never);
    const size = box.getSize(new Vector3());
    return {
        volume_mm3: Math.abs(volume),
        area_mm2: area,
        bbox: { x: size.x, y: size.y, z: size.z },
        triangles: n / 3,
    };
}

/** Meshes above 2000 mm are almost always inches or metres; bring them to mm like the server does. */
export function normaliseUnits(geom: BufferGeometry, s: GeoStats): number {
    const max = Math.max(s.bbox.x, s.bbox.y, s.bbox.z);
    if (max > 2000) {
        geom.scale(25.4, 25.4, 25.4);
        return 25.4;
    }
    return 1;
}
