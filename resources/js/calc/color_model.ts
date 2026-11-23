/**
 * The coloured look of a generated model (22 Nov 2026): the GLB a generator stored beside the STL (Tripo with its texture) is
 * laid over the STL in the viewer. The STL stays the truth (size, price, print); the GLB is only what the eye sees, so it is
 * fitted to the STL: turned from glTF's Y-up to Z-up, a quarter turn about the vertical picked by the shadow it casts
 * (the front of a bust was turned to face front after the generator), scaled uniformly to the STL's longest side and put
 * on its footprint and top (a pedestal or a cut chest changes the bottom, never the head).
 */
import { BufferGeometry, Float32BufferAttribute, Group, Matrix4, Mesh, MeshBasicMaterial, Object3D, Vector3, Material, Texture } from 'three';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';

/** Vertices looked at when two shapes are compared: enough for a silhouette, cheap for a mesh of millions. */
const SAMPLES = 20000;
const GRID = 32;

export async function loadColoredModel(url: string): Promise<Object3D> {
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('glb fetch failed');
    const gltf = await new GLTFLoader().parseAsync(await res.arrayBuffer(), '');
    flatten(gltf.scene);
    return gltf.scene;
}

type Lit = Material & { map?: Texture | null; color?: { r: number; g: number; b: number }; vertexColors?: boolean };

/** The lamps of the viewer are strong (they shade a grey print): a lit texture would burn out, so the texture is drawn as it is. */
function flatten(root: Object3D): void {
    root.traverse((o) => {
        const m = o as Mesh;
        if (!m.isMesh) return;
        const list = (Array.isArray(m.material) ? m.material : [m.material]) as Lit[];
        const out = list.map((old) => {
            const basic = new MeshBasicMaterial({ map: old.map ?? null, vertexColors: !!old.vertexColors && !old.map });
            if (old.color && !old.map) basic.color.setRGB(old.color.r, old.color.g, old.color.b);
            old.dispose();
            return basic;
        });
        m.material = Array.isArray(m.material) ? out : out[0];
    });
}

/** Vertices (at most ~SAMPLES of them, evenly spread) of a root in its world coordinates. */
function points(root: Object3D): Vector3[] {
    root.updateMatrixWorld(true);
    const all: { m: Mesh; n: number }[] = [];
    let total = 0;
    root.traverse((o) => {
        const m = o as Mesh;
        const pos = m.isMesh ? m.geometry?.getAttribute('position') : null;
        if (pos) { all.push({ m, n: pos.count }); total += pos.count; }
    });
    const step = Math.max(1, Math.floor(total / SAMPLES));
    const out: Vector3[] = [];
    for (const { m, n } of all) {
        const pos = m.geometry.getAttribute('position');
        for (let i = 0; i < n; i += step) out.push(new Vector3().fromBufferAttribute(pos, i).applyMatrix4(m.matrixWorld));
    }
    return out;
}

function stlPoints(geom: BufferGeometry): Vector3[] {
    const pos = geom.getAttribute('position');
    const step = Math.max(1, Math.floor(pos.count / SAMPLES));
    const out: Vector3[] = [];
    for (let i = 0; i < pos.count; i += step) out.push(new Vector3().fromBufferAttribute(pos, i));
    return out;
}

type Box = { min: Vector3; max: Vector3 };

const boundsOf = (p: Vector3[]): Box => {
    const min = new Vector3(Infinity, Infinity, Infinity); const max = new Vector3(-Infinity, -Infinity, -Infinity);
    for (const v of p) { min.min(v); max.max(v); }
    return { min, max };
};

/** The cells of a GRID x GRID shadow (seen from above) that the points fall in, over the footprint of `b`. */
function shadow(p: Vector3[], b: Box, side = false): Set<number> {
    // from above (x, y), or from the side (x, z): the side view decides how high the figure sits
    const w = (b.max.x - b.min.x) || 1; const d = (side ? b.max.z - b.min.z : b.max.y - b.min.y) || 1;
    const lo = side ? b.min.z : b.min.y;
    const cells = new Set<number>();
    for (const v of p) {
        const x = Math.min(GRID - 1, Math.max(0, Math.floor(((v.x - b.min.x) / w) * GRID)));
        const y = Math.min(GRID - 1, Math.max(0, Math.floor((((side ? v.z : v.y) - lo) / d) * GRID)));
        cells.add(y * GRID + x);
    }
    return cells;
}

function overlap(a: Set<number>, b: Set<number>): number {
    let both = 0;
    a.forEach((c) => { if (b.has(c)) both++; });
    return both / (a.size + b.size - both || 1);
}

/**
 * The matrix that takes the GLB's own coordinates to the STL's, or null when it cannot be worked out.
 * `color` is the loaded scene, `stl` the geometry of the STL the viewer shows.
 */
export function fitToStl(color: Object3D, stl: BufferGeometry): Matrix4 | null {
    const target = stlPoints(stl);
    const raw = points(color);
    if (!target.length || !raw.length) return null;
    const want = boundsOf(target);
    const up = new Matrix4().makeRotationX(Math.PI / 2);     // glTF is Y-up, a printer's file is Z-up
    const upright = raw.map((v) => v.clone().applyMatrix4(up));
    const have = boundsOf(upright);
    const longest = Math.max(have.max.x - have.min.x, have.max.y - have.min.y, have.max.z - have.min.z);
    const wanted = Math.max(want.max.x - want.min.x, want.max.y - want.min.y, want.max.z - want.min.z);
    if (!(longest > 0) || !(wanted > 0)) return null;
    const scale = wanted / longest;
    const aim = shadow(target, want);
    let best: { score: number; shift: Vector3; turn: Matrix4; turned: Vector3[]; b: Box } | null = null;
    for (let k = 0; k < 4; k++) {
        const turn = new Matrix4().makeRotationZ((k * Math.PI) / 2);
        const turned = upright.map((v) => v.clone().applyMatrix4(turn).multiplyScalar(scale));
        const b = boundsOf(turned);
        // footprints centred on each other, the tops level
        const shift = new Vector3((want.min.x + want.max.x) / 2 - (b.min.x + b.max.x) / 2, (want.min.y + want.max.y) / 2 - (b.min.y + b.max.y) / 2, want.max.z - b.max.z);
        const score = overlap(shadow(turned.map((v) => v.clone().add(shift)), want), aim);
        if (!best || score > best.score) {
            best = { score, shift, turn, turned, b };
        }
    }
    // the colours belong only on the same shape: a bust is cut from the whole figure the generator sent,
    // and dressing the cut STL in the uncut body misled more than grey (Vémola bust, 23 Nov 2026)
    if (best) console.debug('colored preview silhouette match', Math.round(best.score * 100) / 100);
    if (!best || best.score < 0.45) return null;
    // how high the figure sits: tops level put a dog 10 mm over its base (its print is a touch taller than
    // the generator's body, 23 Nov 2026), so the seat between bottoms-level and tops-level with the best
    // SIDE silhouette wins - a figure on a base lands with its feet on the base, not in the air
    const aimSide = shadow(target, want, true);
    const zTop = best.shift.z;
    const zBottom = want.min.z - best.b.min.z;
    let seat = zTop; let bestSide = -1;
    for (let t = 0; t <= 8; t++) {
        const z = zBottom + ((zTop - zBottom) * t) / 8;
        const probe = new Vector3(best.shift.x, best.shift.y, z);
        const side = overlap(shadow(best.turned.map((v) => v.clone().add(probe)), want, true), aimSide);
        if (side > bestSide) { bestSide = side; seat = z; }
    }
    return new Matrix4().makeTranslation(best.shift.x, best.shift.y, seat).multiply(new Matrix4().makeScale(scale, scale, scale)).multiply(best.turn).multiply(up);
}

/** The coloured model wrapped so it can be hung on the STL mesh (same coordinates); null when it cannot be fitted. */
export function colorHolder(color: Object3D, stl: BufferGeometry): Group | null {
    const m = fitToStl(color, stl);
    if (!m) return null;
    const holder = new Group();
    holder.matrixAutoUpdate = false;
    holder.matrix.copy(m);
    holder.add(color);
    // where the coloured figure ends at the bottom, in the STL's millimetres: whatever of the STL lies deeper
    // is ours, not the generator's (the pedestal), and the viewer sketches it in wires under the colours
    let bottom = Infinity;
    for (const v of points(color)) { const z = v.applyMatrix4(m).z; if (z < bottom) bottom = z; }
    holder.userData.bottomZ = bottom;
    return holder;
}

/** The triangles of the STL that lie wholly under `zMax` (the printed pedestal the coloured model does not have). */
export function underSlice(geom: BufferGeometry, zMax: number): BufferGeometry | null {
    const pos = geom.getAttribute('position');
    const idx = geom.getIndex();
    const n = idx ? idx.count : pos.count;
    const out: number[] = [];
    for (let i = 0; i < n; i += 3) {
        const a = idx ? idx.getX(i) : i, b = idx ? idx.getX(i + 1) : i + 1, c = idx ? idx.getX(i + 2) : i + 2;
        if (pos.getZ(a) <= zMax && pos.getZ(b) <= zMax && pos.getZ(c) <= zMax) {
            out.push(pos.getX(a), pos.getY(a), pos.getZ(a), pos.getX(b), pos.getY(b), pos.getZ(b), pos.getX(c), pos.getY(c), pos.getZ(c));
        }
    }
    if (!out.length) return null;
    const g = new BufferGeometry();
    g.setAttribute('position', new Float32BufferAttribute(out, 3));
    return g;
}
