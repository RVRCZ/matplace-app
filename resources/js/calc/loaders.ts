import { BufferGeometry, Group, Mesh } from 'three';
import { STLLoader } from 'three/examples/jsm/loaders/STLLoader.js';
import { OBJLoader } from 'three/examples/jsm/loaders/OBJLoader.js';
import { ThreeMFLoader } from 'three/examples/jsm/loaders/3MFLoader.js';
import { mergeGeometries } from 'three/examples/jsm/utils/BufferGeometryUtils.js';

/** Formats the browser can parse itself for the instant estimate. Others wait for the server. */
export const BROWSER_FORMATS = ['stl', '3mf', 'obj'];

export function extensionOf(name: string): string {
    return (name.split('.').pop() ?? '').toLowerCase();
}

export async function loadGeometry(file: File): Promise<BufferGeometry | null> {
    const ext = extensionOf(file.name);
    if (!BROWSER_FORMATS.includes(ext)) return null;
    const buf = await file.arrayBuffer();
    if (ext === 'stl') return new STLLoader().parse(buf);
    if (ext === 'obj') return collect(new OBJLoader().parse(new TextDecoder().decode(buf)));
    if (ext === '3mf') return collect(new ThreeMFLoader().parse(buf));
    return null;
}

export async function loadGeometryFromUrl(url: string): Promise<BufferGeometry> {
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('stl fetch failed');
    return new STLLoader().parse(await res.arrayBuffer());
}

/** Merge every mesh in a loaded group into one world-space, non-indexed geometry. */
function collect(root: Group): BufferGeometry | null {
    root.updateMatrixWorld(true);
    const parts: BufferGeometry[] = [];
    root.traverse((o) => {
        const m = o as Mesh;
        if (m.isMesh && m.geometry) {
            const g = (m.geometry as BufferGeometry).clone();
            g.applyMatrix4(m.matrixWorld);
            const plain = g.index ? g.toNonIndexed() : g;
            for (const key of Object.keys(plain.attributes)) if (key !== 'position') plain.deleteAttribute(key);
            parts.push(plain);
        }
    });
    if (!parts.length) return null;
    const merged = parts.length === 1 ? parts[0] : mergeGeometries(parts, false);
    if (!merged) return null;
    merged.computeVertexNormals();
    return merged;
}
