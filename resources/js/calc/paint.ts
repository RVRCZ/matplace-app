/**
 * The tool "Vybarvit model" (resources/views/tools/paint.blade.php, docs/AS.md), a module of the tool page: a model
 * of the visitor's (uploaded here, or any ready file with ?from=<uuid>) is painted in filament colours and saved as a
 * painted 3MF, the kind the calculator, the order and the farm already know (docs/AL.md §14).
 *
 *   fill      a tap on a surface colours the connected patch up to an edge (the angle is set), or the whole part
 *   brush     a drag paints what lies under a ball on the surface; the mesh is refined along its rim (paint_mesh.ts)
 *   eraser    the same brush with the model's own colour
 *   by height from a height up another colour for what is not painted by hand: drawn by the shader, cut by the server
 *
 * The model is drawn by this module in the tool page's viewer (Viewer.host): one buffer of triangles with a state per
 * corner, the colours looked up in the shader, so a change of a colour or of a height costs nothing. A tap is found
 * through a picture of the triangles' numbers (one render when the view changes, then a look into an array).
 */
import {
    BufferAttribute, BufferGeometry, Color, Matrix4, Mesh, MeshBasicMaterial, MeshStandardMaterial, MOUSE, NearestFilter, Raycaster, ShaderMaterial,
    SphereGeometry, TOUCH, Vector2, Vector3, WebGLRenderTarget,
} from 'three';
import type { Stage } from './tool_page';
import type { ViewerHost } from './viewer';
import type { FileInfo } from './api';
import { extensionOf } from './loaders';
import { PaintMesh, Step, cleanBands, usedStates } from './paint_mesh';

interface Limits { change_minutes: number; max_triangles: number; max_triangles_phone: number; max_added: number; brush_triangles: number; brush_triangles_phone: number; max_colors: number; max_heights: number; edge_mm: number; speck_mm2: number }
interface Tile { hex: string; name: string; loaded: boolean }
interface Cfg { upload: string; files: string; download: string; home: string; from: string | null; formats: string[]; maxMb: number; limits: Limits; tiles: Tile[]; fromFarm: boolean; i18n: Record<string, string> }
interface Band { z: number; color: number }
/** What can be undone besides the triangles: the colours (index = a triangle's state, null = a free place), the bands, the colour in hand. */
interface Ui { slots: (string | null)[]; bands: Band[]; current: number }
interface Entry { step: Step | null; before: Ui | null; after: Ui | null }
type Mode = 'fill' | 'brush' | 'erase' | 'turn';
type Painted = FileInfo & { paint?: { report?: { specks?: { count?: number } } | null; lost?: boolean } | null };
interface Hit { tri: number; point: [number, number, number]; dist: number }

const SLOTS = 16;
const MAX_BANDS = 8;
const esc = (s: string): string => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c] as string));
const icon = (name: string, cls = 'h-4 w-4'): string => `<svg class="${cls} shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-${name}"/></svg>`;

/** The triangles of a binary STL as nine numbers each; null when the file is not one. */
export function soupOfStl(buffer: ArrayBuffer): Float32Array | null {
    if (buffer.byteLength < 84) return null;
    const n = new DataView(buffer).getUint32(80, true);
    if (n < 1 || buffer.byteLength < 84 + n * 50) return null;
    const soup = new Float32Array(n * 9);
    const to = new Uint8Array(soup.buffer);
    const from = new Uint8Array(buffer);
    // a record is 50 bytes (a normal, three corners, two bytes): the corners are copied as they lie
    for (let t = 0; t < n; t++) to.set(from.subarray(84 + t * 50 + 12, 84 + t * 50 + 48), t * 36);
    return soup;
}

/** The painted model in the page's viewer. */
class PaintView {
    private mesh: Mesh | null = null;
    private geom: BufferGeometry | null = null;
    private pos: BufferAttribute | null = null;
    private st: BufferAttribute | null = null;
    private cap = 0;
    private palette = Array.from({ length: SLOTS }, () => new Color(0xffffff));
    private bands = Array.from({ length: MAX_BANDS }, () => new Vector2(0, 0));
    private uniforms = { uPal: { value: this.palette }, uBands: { value: this.bands }, uBandCount: { value: 0 } };
    private material: MeshStandardMaterial;
    private idMaterial: ShaderMaterial;
    private rt: WebGLRenderTarget | null = null;
    private ids: Uint8Array | null = null;
    private idsStale = true;
    private cursor: Mesh;
    private ray = new Raycaster();
    private inverse = new Matrix4();

    constructor(private host: ViewerHost) {
        this.material = new MeshStandardMaterial({ color: 0xffffff, roughness: 0.5, metalness: 0, flatShading: true });
        this.material.onBeforeCompile = (shader) => {
            Object.assign(shader.uniforms, this.uniforms);
            shader.vertexShader = shader.vertexShader
                .replace('#include <common>', '#include <common>\nattribute float aState;\nvarying float vState;\nvarying float vModelZ;')
                .replace('#include <begin_vertex>', '#include <begin_vertex>\nvState = aState;\nvModelZ = position.z;');
            shader.fragmentShader = shader.fragmentShader
                .replace('#include <common>', `#include <common>\nuniform vec3 uPal[${SLOTS}];\nuniform vec2 uBands[${MAX_BANDS}];\nuniform int uBandCount;\nvarying float vState;\nvarying float vModelZ;`)
                // a triangle that is not painted takes the colour of the highest band that starts at or below the point
                .replace('#include <color_fragment>', `int paintState = int(vState + 0.5);
                    if (paintState == 0) { for (int i = 0; i < ${MAX_BANDS}; i++) { if (i < uBandCount && vModelZ >= uBands[i].x) paintState = int(uBands[i].y + 0.5); } }
                    diffuseColor.rgb = uPal[clamp(paintState, 0, ${SLOTS - 1})];`);
        };
        this.material.customProgramCacheKey = () => 'mp-paint';
        // the number of every triangle as a colour (non-indexed triangles: three corners each, in order)
        this.idMaterial = new ShaderMaterial({
            vertexShader: 'flat varying vec4 vId;\nvoid main() {\n int id = gl_VertexID / 3 + 1;\n vId = vec4(float(id & 255), float((id >> 8) & 255), float((id >> 16) & 255), 255.0) / 255.0;\n gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);\n}',
            fragmentShader: 'flat varying vec4 vId;\nvoid main() { gl_FragColor = vId; }',
        });
        this.cursor = new Mesh(new SphereGeometry(1, 24, 16), new MeshBasicMaterial({ color: 0xc94714, transparent: true, opacity: 0.28, depthWrite: false }));
        this.cursor.visible = false;
        this.cursor.renderOrder = 8;
        host.controls.addEventListener('change', () => { this.idsStale = true; });
        new ResizeObserver(() => { this.idsStale = true; }).observe(host.canvas);
    }

    /** A new model: its triangles into the buffers, standing on the grid in the middle of the picture, framed. */
    load(pm: PaintMesh): void {
        if (this.mesh) { this.host.scene.remove(this.mesh); this.geom?.dispose(); }
        this.geom = new BufferGeometry();
        this.mesh = new Mesh(this.geom, this.material);
        this.mesh.frustumCulled = false;
        this.mesh.add(this.cursor);
        this.build(pm);
        // files are Z-up, the picture is Y-up (as the page's own viewer turns them)
        let lo = [Infinity, Infinity, Infinity]; let hi = [-Infinity, -Infinity, -Infinity];
        for (let v = 0; v < pm.nV; v++) for (let k = 0; k < 3; k++) { const c = pm.pos[v * 3 + k]; if (c < lo[k]) lo[k] = c; if (c > hi[k]) hi[k] = c; }
        if (!pm.nV) { lo = [0, 0, 0]; hi = [1, 1, 1]; }
        this.mesh.rotation.x = -Math.PI / 2;
        this.mesh.position.set(-(lo[0] + hi[0]) / 2, -lo[2], (lo[1] + hi[1]) / 2);
        this.host.scene.add(this.mesh);
        const size = new Vector3(hi[0] - lo[0], hi[2] - lo[2], hi[1] - lo[1]);
        this.frame(size);
        this.idsStale = true;
    }

    /** The camera on the whole model from the usual side. */
    frame(size: Vector3 | null = null): void {
        if (!this.mesh) return;
        const s = size ?? this.size;
        this.size = s;
        const { camera, controls } = this.host;
        const radius = Math.max(s.x, s.y, s.z) || 1;
        const sphere = 0.5 * Math.hypot(s.x, s.y, s.z) || 1;
        const half = (camera.fov * Math.PI) / 360;
        const dist = (sphere / Math.sin(half)) * 1.08 / Math.min(1, camera.aspect || 1);
        camera.position.copy(new Vector3(0.62, 0.45, 0.7).normalize().multiplyScalar(dist)).add(new Vector3(0, s.y / 2, 0));
        camera.near = radius / 100; camera.far = radius * 100;
        camera.updateProjectionMatrix();
        controls.target.set(0, s.y / 2, 0);
        controls.update();
        this.idsStale = true;
    }

    private size = new Vector3(1, 1, 1);

    /** The buffers anew from the whole mesh, with room for what the brush will add. */
    private build(pm: PaintMesh): void {
        const geom = this.geom!;
        this.cap = pm.nT + Math.max(20000, Math.ceil(pm.nT * 0.25));
        const pos = new Float32Array(this.cap * 9);
        const st = new Uint8Array(this.cap * 3);
        for (let t = 0; t < pm.nT; t++) { pm.corners(t, pos, t * 9); st[t * 3] = st[t * 3 + 1] = st[t * 3 + 2] = pm.state[t]; }
        this.pos = new BufferAttribute(pos, 3);
        this.st = new BufferAttribute(st, 1);
        geom.setAttribute('position', this.pos);
        geom.setAttribute('aState', this.st);
        geom.setDrawRange(0, pm.nT * 3);
        pm.dirty.length = 0; pm.dirtyGeom.length = 0;
    }

    /** What the mesh changed since the last time: only those triangles go to the graphics card. */
    sync(pm: PaintMesh): void {
        if (!this.geom || !this.pos || !this.st) return;
        if (!pm.dirty.length && !pm.dirtyGeom.length) return;
        if (pm.nT > this.cap) { this.geom.dispose(); this.build(pm); return; }
        const pos = this.pos.array as Float32Array; const st = this.st.array as Uint8Array;
        if (pm.dirtyGeom.length) {
            const whole = pm.dirtyGeom.length > 4000;
            for (const t of pm.dirtyGeom) { pm.corners(t, pos, t * 9); if (!whole) this.pos.addUpdateRange(t * 9, 9); }
            this.pos.needsUpdate = true;
        }
        let lo = Infinity; let hi = -1;
        for (const t of pm.dirty) { st[t * 3] = st[t * 3 + 1] = st[t * 3 + 2] = pm.state[t]; if (t < lo) lo = t; if (t > hi) hi = t; }
        if (hi >= 0) { this.st.addUpdateRange(lo * 3, (hi - lo + 1) * 3); this.st.needsUpdate = true; }
        this.geom.setDrawRange(0, pm.nT * 3);
        pm.dirty.length = 0; pm.dirtyGeom.length = 0;
    }

    /** The picture of the triangles' numbers is out of date (the mesh was refined). */
    stale(): void { this.idsStale = true; }

    setColors(slots: (string | null)[]): void {
        slots.forEach((hex, i) => { if (i < SLOTS) this.palette[i].set(hex ?? '#ffffff'); });
    }

    /** The bands as heights of the file (zLo = the model's lowest point). */
    setBands(bands: Band[], zLo: number): void {
        bands.slice(0, MAX_BANDS).forEach((b, i) => this.bands[i].set(zLo + b.z, b.color));
        this.uniforms.uBandCount.value = Math.min(MAX_BANDS, bands.length);
    }

    /** The ball of the brush on the model (a point of the file), or nothing. */
    showCursor(point: [number, number, number] | null, r = 1): void {
        this.cursor.visible = !!point;
        if (point) { this.cursor.position.set(point[0], point[1], point[2]); this.cursor.scale.setScalar(r); }
    }

    private renderIds(pm: PaintMesh): void {
        const { renderer, camera, canvas } = this.host;
        const mesh = this.mesh!;
        this.sync(pm);
        const w = Math.max(1, canvas.clientWidth); const h = Math.max(1, canvas.clientHeight);
        if (!this.rt || this.rt.width !== w || this.rt.height !== h) {
            this.rt?.dispose();
            this.rt = new WebGLRenderTarget(w, h, { minFilter: NearestFilter, magFilter: NearestFilter });
            this.ids = new Uint8Array(w * h * 4);
        }
        const before = renderer.getRenderTarget();
        const clear = new Color(); renderer.getClearColor(clear);
        const alpha = renderer.getClearAlpha();
        const shown = this.cursor.visible;
        this.cursor.visible = false;
        mesh.material = this.idMaterial;
        renderer.setRenderTarget(this.rt);
        renderer.setClearColor(0x000000, 0);
        renderer.clear();
        renderer.render(mesh, camera);
        renderer.readRenderTargetPixels(this.rt, 0, 0, w, h, this.ids!);
        renderer.setRenderTarget(before);
        renderer.setClearColor(clear, alpha);
        mesh.material = this.material;
        this.cursor.visible = shown;
        this.idsStale = false;
        pm.markEpoch();
    }

    /** The triangle and the point of the model under a place of the window. */
    pick(clientX: number, clientY: number, pm: PaintMesh): Hit | null {
        const mesh = this.mesh;
        if (!mesh) return null;
        // drawn anew when the view has changed, and when the brush has split so much that the old numbers stand for crowds
        if (this.idsStale || pm.sinceEpoch() > 3000) this.renderIds(pm);
        const rt = this.rt!; const ids = this.ids!;
        const rect = this.host.canvas.getBoundingClientRect();
        if (rect.width < 1 || rect.height < 1) return null;
        const fx = (clientX - rect.left) / rect.width; const fy = (clientY - rect.top) / rect.height;
        const px = Math.floor(fx * rt.width); const py = rt.height - 1 - Math.floor(fy * rt.height);
        const at = (x: number, y: number): number => {
            if (x < 0 || y < 0 || x >= rt.width || y >= rt.height) return 0;
            const i = (y * rt.width + x) * 4;
            return ids[i] | (ids[i + 1] << 8) | (ids[i + 2] << 16);
        };
        // the very pixel, else the ones around it (a thin triangle may fall between two)
        let id = at(px, py);
        for (let r = 1; r <= 2 && !id; r++) for (let dy = -r; dy <= r && !id; dy++) for (let dx = -r; dx <= r && !id; dx++) id = at(px + dx, py + dy);
        if (!id) return null;
        mesh.updateMatrixWorld();
        this.ray.setFromCamera(new Vector2(fx * 2 - 1, -(fy * 2 - 1)), this.host.camera);
        this.inverse.copy(mesh.matrixWorld).invert();
        const o = this.ray.ray.origin.clone().applyMatrix4(this.inverse);
        const d = this.ray.ray.direction.clone().transformDirection(this.inverse);
        const hit = pm.hitNear(id - 1, [o.x, o.y, o.z], [d.x, d.y, d.z]);
        if (!hit) return null;
        return { tri: hit[0], point: [o.x + d.x * hit[1], o.y + d.y * hit[1], o.z + d.z * hit[1]], dist: hit[1] };
    }

    /** How many pixels of the window a millimetre is at a distance from the eye. */
    pixelsPerMm(dist: number): number {
        const h = Math.max(1, this.host.canvas.clientHeight);
        return h / 2 / (Math.max(1e-6, dist) * Math.tan((this.host.camera.fov * Math.PI) / 360));
    }

    /** How the pointer moves the view: with a painting tool the left button and one finger paint, the rest turns. */
    controlsFor(mode: Mode): void {
        const c = this.host.controls;
        const paints = mode === 'brush' || mode === 'erase';
        c.mouseButtons = { LEFT: paints ? null : MOUSE.ROTATE, MIDDLE: MOUSE.DOLLY, RIGHT: paints ? MOUSE.ROTATE : MOUSE.PAN } as typeof c.mouseButtons;
        c.touches = { ONE: paints ? null : TOUCH.ROTATE, TWO: paints ? TOUCH.DOLLY_ROTATE : TOUCH.DOLLY_PAN } as typeof c.touches;
        this.host.canvas.style.cursor = paints ? 'crosshair' : mode === 'fill' ? 'pointer' : '';
    }
}

export function bootPaint(stage: Stage): void {
    const cfg = (window as unknown as { MP_PAINT?: Cfg }).MP_PAINT;
    const input = document.getElementById('paint-file') as HTMLInputElement | null;
    const bar = document.getElementById('paint-bar');
    if (!cfg || !input || !bar) return;
    const $ = <T extends HTMLElement>(id: string) => document.getElementById(id) as T;
    const t = (key: string, replace: Record<string, string | number> = {}): string => Object.entries(replace).reduce((s, [a, b]) => s.split(`:${a}`).join(String(b)), cfg.i18n[`paint.${key}`] ?? stage.t(key));
    const plural = new Intl.PluralRules(stage.cfg.locale);
    const count = (key: string, n: number): string => { const rule = plural.select(n); return t(`${key}.${rule === 'one' ? 'one' : rule === 'few' ? 'few' : 'other'}`, { n }); };
    const nf = stage.nf;
    const whole = new Intl.NumberFormat(stage.cfg.locale, { maximumFractionDigits: 0 });
    const limits = cfg.limits;
    const phone = window.matchMedia?.('(pointer: coarse)').matches ?? false;
    const brushLimit = phone ? limits.brush_triangles_phone : limits.brush_triangles;
    // a phone has less memory: a model it could not hold is refused with a sentence instead of a page that dies
    const openLimit = phone ? Math.min(limits.max_triangles, limits.max_triangles_phone) : limits.max_triangles;
    const tileName = new Map(cfg.tiles.map((x) => [x.hex.toLowerCase(), x.name]));
    const nameOf = (hex: string): string => tileName.get(hex.toLowerCase()) ?? hex;

    const view = new PaintView(stage.viewer.host());
    const canvas = stage.viewer.host().canvas;
    // the bar of tools right under the model, inside its card; what the page's viewer has for its own models is put away
    canvas.parentElement?.after(bar);
    ['tool-xray', 'tool-bed', 'tool-spread-wrap'].forEach((id) => document.getElementById(id)?.classList.add('!hidden'));

    let pm: PaintMesh | null = null;
    let model: FileInfo | null = null;
    let zLo = 0; let height = 1;
    let ui: Ui = { slots: Array.from({ length: SLOTS }, () => null), bands: [], current: 1 };
    let mode: Mode = 'fill';
    let brushOn = true;
    let entries: Entry[] = []; let at = 0;
    let rev = 0;
    let saved: { rev: number; file: Painted } | null = null;
    let saving: Promise<Painted | null> | null = null;
    let areas: Float64Array = new Float64Array(256);
    let full = false;        // the brush met its limit of triangles
    let changes = 0;         // filament changes a print of the colouring as it is would take
    let timings: Record<string, number> = {};     // how long the last model took to open (docs/AS.md §6)

    const state = $('paint-state'); const note = $('paint-note'); const source = $('paint-source'); const result = $('paint-result');
    const say = (text: string | null): void => { state.textContent = text ?? ''; state.classList.toggle('hidden', !text); };
    const snap = (): Ui => ({ slots: [...ui.slots], bands: ui.bands.map((b) => ({ ...b })), current: ui.current });
    const same = (a: Ui, b: Ui): boolean => JSON.stringify([a.slots, a.bands]) === JSON.stringify([b.slots, b.bands]);
    const taken = (): number => ui.slots.filter((s) => s !== null).length;

    // ── what is shown ────────────────────────────────────────────────────────────────────────────────

    /** The colours, the bands and the numbers after any change; the mesh's own changes go to the viewer first. */
    const refresh = (stats = true): void => {
        if (!pm) return;
        view.sync(pm);
        view.setColors(ui.slots);
        ui.bands = cleanBands(ui.bands.filter((b) => ui.slots[b.color] !== null), height, SLOTS, limits.max_heights);
        view.setBands(ui.bands, zLo);
        if (stats) areas = pm.areas();
        const used = usedStates(areas, ui.bands);
        // what the colours cost: a filament change for every colour a layer holds beyond its first, about two minutes each
        if (stats) changes = used.length > 1 ? pm.colourChanges(ui.bands) : 0;
        const minutes = Math.round(changes * limits.change_minutes);
        const time = minutes >= 60 ? `${Math.floor(minutes / 60)} h ${minutes % 60} min` : `${minutes} min`;
        stage.status({ bbox: model?.bbox ?? null, colors: used.length, extra: changes > 0 ? [count('changes', changes).replace(':time', time)] : [] });
        drawSwatches(); drawColors(); drawBands();
        const undo = [$('paint-undo') as HTMLButtonElement, $('tool-undo') as HTMLButtonElement]; const redo = [$('paint-redo') as HTMLButtonElement, $('tool-redo') as HTMLButtonElement];
        undo.forEach((b) => { b.disabled = at === 0; }); redo.forEach((b) => { b.disabled = at >= entries.length; });
        ($('paint-clear') as HTMLButtonElement).disabled = used.length < 2;
        const left = limits.max_colors - taken();
        $('paint-left').textContent = left > 0 ? count('colors.left', left) : t('colors.left.none', { max: limits.max_colors });
        ($('paint-add') as HTMLButtonElement).disabled = left <= 0;
        ($('paint-band-add') as HTMLButtonElement).disabled = ui.bands.length >= limits.max_heights;
    };

    const swatch = (i: number, cls: string, pressed: boolean, extra = ''): string => `<button type="button" class="${cls}" data-slot="${i}" role="radio" aria-checked="${pressed}" aria-pressed="${pressed}" title="${esc(i === 0 ? `${t('colors.base')}: ${nameOf(ui.slots[i]!)}` : nameOf(ui.slots[i]!))}" aria-label="${esc(i === 0 ? `${t('colors.base')}: ${nameOf(ui.slots[i]!)}` : nameOf(ui.slots[i]!))}" style="background-color:${esc(ui.slots[i]!)}" ${extra}></button>`;

    /** The colours to paint with, under the model: a tap takes one in hand. */
    const drawSwatches = (): void => {
        const box = $('paint-swatches');
        const html = ui.slots.map((hex, i) => (hex === null ? '' : swatch(i, 'tool-swatch', i === ui.current && mode !== 'erase'))).join('');
        const add = taken() < limits.max_colors ? `<button type="button" data-add class="tool-icon-btn !h-9 !w-9 shrink-0 !rounded-full" title="${esc(cfg.i18n['paint.colors.window'] ?? '')}" aria-label="${esc(cfg.i18n['paint.colors.window'] ?? '')}">${icon('plus')}</button>` : '';
        box.innerHTML = html + add;
    };

    /** The list of colours in the panel: the model's own first, each with its share of the surface, change and remove. */
    const drawColors = (): void => {
        const total = areas.reduce((a, b) => a + b, 0) || 1;
        $('paint-colors').innerHTML = ui.slots.map((hex, i) => {
            if (hex === null) return '';
            // with bands the share of what is not painted is split among them: not said, rather than said wrong
            const share = ui.bands.length && i === 0 ? '' : ui.bands.some((b) => b.color === i) ? '' : t('colors.share', { pct: whole.format(Math.round(100 * areas[i] / total)) });
            return `<div class="flex items-center gap-2" data-row="${i}">
                <button type="button" class="tool-swatch-sm" data-change="${i}" title="${esc(t('colors.change'))}" aria-label="${esc(t('colors.change'))}" style="background-color:${esc(hex)}"></button>
                <span class="min-w-0 flex-1 text-sm"><span class="block truncate font-medium text-ink">${esc(i === 0 ? t('colors.base') : nameOf(hex))}</span><span class="block truncate text-xs text-muted">${esc(i === 0 ? `${nameOf(hex)} · ${t('colors.base.hint')}` : share)}</span></span>
                ${i === 0 ? '' : `<button type="button" class="tool-icon-btn" data-remove="${i}" title="${esc(t('colors.remove'))}" aria-label="${esc(t('colors.remove'))}: ${esc(nameOf(hex))}">${icon('x')}</button>`}
            </div>`;
        }).join('');
    };

    let bandDrag: Ui | null = null;      // the state before a slider of a band began to move

    /** The colour changes by height: a slider and the band's colour each. */
    const drawBands = (): void => {
        const box = $('paint-bands');
        if (box.contains(document.activeElement) && bandDrag) return;      // a slider is being dragged: its row stays as it is
        box.innerHTML = ui.bands.map((b, n) => `<div class="rounded-lg border border-line p-3" data-band="${n}">
            <div class="flex items-center justify-between gap-2"><label class="text-sm font-medium text-ink" for="paint-band-${n}">${esc(t('height.from'))}</label><span class="num text-sm text-muted" data-band-val>${esc(nf.format(b.z))} mm</span>
                <button type="button" class="tool-icon-btn" data-band-remove="${n}" title="${esc(t('height.remove'))}" aria-label="${esc(t('height.remove'))}">${icon('x')}</button></div>
            <input id="paint-band-${n}" type="range" min="0.2" max="${Math.max(0.4, height - 0.2).toFixed(1)}" step="0.2" value="${b.z}" class="mt-1 w-full accent-ink" data-band-z="${n}">
            <div class="mt-2 flex flex-wrap items-center gap-2" role="radiogroup" aria-label="${esc(t('height.color'))}">${ui.slots.map((hex, i) => (hex === null ? '' : swatch(i, `tool-swatch-sm${i === b.color ? ' ring-2 ring-ink ring-offset-2' : ''}`, i === b.color, `data-band-color="${n}"`))).join('')}</div>
        </div>`).join('');
    };

    // ── steps: undo and redo ─────────────────────────────────────────────────────────────────────────

    /** A change has settled: the triangles' step (null: none) and the colours and bands as they were before it. */
    const commit = (step: Step | null, before: Ui | null): void => {
        const changedUi = before !== null && !same(before, ui);
        if (!step && !changedUi) { refresh(false); return; }
        entries.splice(at);
        entries.push({ step, before: changedUi ? before : null, after: changedUi ? snap() : null });
        // what is kept is limited by the triangles the steps hold (a fill of a million triangles is six megabytes)
        let held = entries.reduce((n, e) => n + (e.step?.tris.length ?? 0), 0);
        while (entries.length > 80 || (held > 6_000_000 && entries.length > 1)) { held -= entries[0].step?.tris.length ?? 0; entries.shift(); }
        at = entries.length;
        rev++;
        result.classList.add('hidden');
        stage.error(null);
        refresh();
    };

    const move = (by: number): void => {
        if (!pm || stroke) return;
        if (by < 0 && at > 0) {
            const e = entries[--at];
            if (e.step) pm.undo(e.step);
            if (e.before) ui = { ...snapOf(e.before), current: e.before.slots[ui.current] !== null ? ui.current : e.before.current };
        } else if (by > 0 && at < entries.length) {
            const e = entries[at++];
            if (e.step) pm.redo(e.step);
            if (e.after) ui = { ...snapOf(e.after), current: e.after.slots[ui.current] !== null ? ui.current : e.after.current };
        } else return;
        rev++;
        refresh();
    };
    const snapOf = (u: Ui): Ui => ({ slots: [...u.slots], bands: u.bands.map((b) => ({ ...b })), current: u.current });

    // ── the colours ──────────────────────────────────────────────────────────────────────────────────

    let dialog: HTMLDialogElement | null = null;
    let chosen: ((hex: string | null) => void) | null = null;

    /** The window of filament colours: one tile per colour, those in a machine first. Answers with a hex or null. */
    const pickTile = (now: string | null): Promise<string | null> => {
        if (!dialog) {
            const d = document.createElement('dialog');
            d.className = 'tool-dialog tool-dialog-color';
            d.setAttribute('aria-labelledby', 'paint-window-title');
            d.innerHTML = `<div class="flex max-h-[inherit] flex-col">
                <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                    <h2 id="paint-window-title" class="text-lg font-semibold">${esc(t('colors.window'))}</h2>
                    <button type="button" data-close class="tool-icon-btn" aria-label="${esc(t('colors.close'))}">${icon('x')}</button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4">
                    <p class="hint mb-3 !text-xs">${esc(t(cfg.fromFarm ? 'colors.window.farm' : 'colors.window.basic'))}</p>
                    <div data-tiles class="grid grid-cols-3 gap-2 sm:grid-cols-5"></div>
                </div>
            </div>`;
            const done = (hex: string | null): void => { const r = chosen; chosen = null; if (d.open) d.close(); r?.(hex); };
            d.addEventListener('close', () => done(null));
            d.addEventListener('click', (e) => {
                const el = e.target as HTMLElement;
                if (el === d || el.closest('[data-close]')) { done(null); return; }
                const tile = el.closest<HTMLButtonElement>('[data-pick]');
                if (tile && !tile.disabled) done(tile.dataset.pick!);
            });
            document.body.appendChild(d);
            dialog = d;
        }
        const d = dialog;
        const have = new Set(ui.slots.filter((s): s is string => s !== null).map((s) => s.toLowerCase()));
        d.querySelector<HTMLElement>('[data-tiles]')!.innerHTML = cfg.tiles.map((x) => {
            const mine = x.hex === now?.toLowerCase(); const dup = have.has(x.hex) && !mine;
            return `<button type="button" class="tool-tile disabled:cursor-not-allowed disabled:opacity-40" data-pick="${esc(x.hex)}" aria-pressed="${mine}" ${dup ? `disabled title="${esc(t('colors.taken'))}"` : ''}>
                <span class="h-10 w-10 rounded-full border border-slate-300" style="background-color:${esc(x.hex)}"></span>
                <span class="leading-tight">${esc(x.name)}</span>${x.loaded ? `<span class="text-[11px] leading-tight text-ok">${esc(t('colors.loaded'))}</span>` : ''}
            </button>`;
        }).join('');
        return new Promise((done) => { chosen = done; d.showModal(); });
    };

    const addColor = async (): Promise<number> => {
        if (taken() >= limits.max_colors) return -1;
        const hex = await pickTile(null);
        if (!hex) return -1;
        const before = snap();
        const i = ui.slots.findIndex((s, n) => n > 0 && s === null);
        if (i < 0) return -1;
        ui.slots[i] = hex; ui.current = i;
        commit(null, before);
        return i;
    };

    const changeColor = async (i: number): Promise<void> => {
        const hex = await pickTile(ui.slots[i]);
        if (!hex || hex === ui.slots[i]) return;
        const before = snap();
        ui.slots[i] = hex;
        commit(null, before);
    };

    const removeColor = (i: number): void => {
        if (!pm || i < 1 || ui.slots[i] === null) return;
        const before = snap();
        pm.begin();
        pm.replaceState(i, 0);
        const step = pm.end();
        ui.slots[i] = null;
        ui.bands = ui.bands.filter((b) => b.color !== i);
        if (ui.current === i) ui.current = Math.max(1, ui.slots.findIndex((s, n) => n > 0 && s !== null));
        commit(step, before);
    };

    const clearAll = (): void => {
        if (!pm) return;
        const before = snap();
        pm.begin();
        pm.fillAll(0);
        const step = pm.end();
        ui.bands = [];
        commit(step, before);
    };

    // ── the tools ────────────────────────────────────────────────────────────────────────────────────

    const setMode = (m: Mode): void => {
        if ((m === 'brush' || m === 'erase') && !brushOn) return;
        mode = m;
        bar.querySelectorAll<HTMLElement>('[data-mode]').forEach((b) => b.setAttribute('aria-pressed', b.dataset.mode === m ? 'true' : 'false'));
        const fill = $('paint-opt-fill'); const brush = $('paint-opt-brush');
        fill.classList.toggle('hidden', m !== 'fill'); fill.classList.toggle('flex', m === 'fill');
        brush.classList.toggle('hidden', m !== 'brush' && m !== 'erase'); brush.classList.toggle('flex', m === 'brush' || m === 'erase');
        $('paint-hint').textContent = full && (m === 'brush' || m === 'erase') ? t('brush.full') : t(`mode.${m}.hint`);
        view.controlsFor(m);
        view.showCursor(null);
        drawSwatches();
    };

    const angle = (): number => (($('paint-whole') as HTMLInputElement).checked ? 180 : Number(($('paint-angle') as HTMLInputElement).value) || 30);
    const radius = (): number => Number(($('paint-size') as HTMLInputElement).value) || 2;

    /** A colour to paint with: the one in hand; when there is none but the model's own, the window opens for one. */
    const colorInHand = async (): Promise<number> => {
        if (ui.current > 0 && ui.slots[ui.current] !== null) return ui.current;
        const other = ui.slots.findIndex((s, n) => n > 0 && s !== null);
        if (other > 0) { ui.current = other; return other; }
        return addColor();
    };

    // a stroke being drawn: where the pointer was, the last point on the model and the way the stroke was going there
    let stroke: { state: number; r: number; last: [number, number]; before: Ui; at: [number, number, number] | null; dir: [number, number, number] | null } | null = null;
    const down = new Map<number, { x: number; y: number; moved: boolean }>();

    const dab = (x: number, y: number): boolean => {
        if (!pm || !stroke) return false;
        const hit = view.pick(x, y, pm);
        if (!hit) return false;
        lastPx = view.pixelsPerMm(hit.dist);
        // the way the stroke goes, from the last point on the model to this one; the first dab, a jump and a sharp turn have none
        let dir: [number, number, number] | null = null;
        if (stroke.at) {
            const d: [number, number, number] = [hit.point[0] - stroke.at[0], hit.point[1] - stroke.at[1], hit.point[2] - stroke.at[2]];
            const len = Math.hypot(d[0], d[1], d[2]);
            if (len < stroke.r * 0.05) return true;      // the pointer has not moved on the model
            dir = [d[0] / len, d[1] / len, d[2] / len];
            const was = stroke.dir;
            if (len > stroke.r * 1.2 || (was && was[0] * dir[0] + was[1] * dir[1] + was[2] * dir[2] < 0.7)) dir = null;
        }
        // the rim in pieces of two fifths of the brush, never finer than the nozzle draws nor coarser than 2 mm
        const edge = Math.min(2, Math.max(limits.edge_mm, stroke.r * 0.4));
        if (!pm.strokeDab(hit.tri, hit.point, edge, pm.srcT + limits.max_added, dir) && !full) {
            full = true;
            $('paint-hint').textContent = t('brush.full');
        }
        stroke.dir = dir; stroke.at = hit.point;
        view.showCursor(hit.point, stroke.r);
        return true;
    };

    const endStroke = (): void => {
        if (!pm || !stroke) return;
        pm.strokeEnd();
        const before = stroke.before;
        stroke = null;
        view.sync(pm);
        view.stale();
        view.showCursor(null);
        commit(pm.end(), before);
    };

    canvas.addEventListener('pointerdown', (e) => {
        down.set(e.pointerId, { x: e.clientX, y: e.clientY, moved: false });
        if (!pm) return;
        // a second finger: the stroke ends, the two fingers turn and zoom
        if (down.size > 1) { endStroke(); return; }
        if ((mode !== 'brush' && mode !== 'erase') || e.button !== 0) return;
        const state = mode === 'erase' ? 0 : ui.current;
        if (mode === 'brush' && (state < 1 || ui.slots[state] === null)) { void colorInHand(); return; }
        e.preventDefault();
        pm.begin();
        stroke = { state, r: radius(), last: [e.clientX, e.clientY], before: snap(), at: null, dir: null };
        pm.strokeStart(stroke.r, state);
        try { canvas.setPointerCapture(e.pointerId); } catch { /* a pointer that is gone */ }
        dab(e.clientX, e.clientY);
        view.sync(pm);
    }, { capture: true });

    let hoverAt: [number, number] | null = null;
    canvas.addEventListener('pointermove', (e) => {
        const d = down.get(e.pointerId);
        if (d && Math.hypot(e.clientX - d.x, e.clientY - d.y) > 6) d.moved = true;
        if (!pm) return;
        if (stroke && down.size === 1) {
            // dabs along the way the pointer went, close enough to leave no gap
            const [x0, y0] = stroke.last;
            const px = Math.max(3, stroke.r * 0.45 * (lastPx || 4));
            const n = Math.min(60, Math.max(1, Math.ceil(Math.hypot(e.clientX - x0, e.clientY - y0) / px)));
            for (let i = 1; i <= n; i++) dab(x0 + (e.clientX - x0) * i / n, y0 + (e.clientY - y0) * i / n);
            stroke.last = [e.clientX, e.clientY];
            view.sync(pm);
            return;
        }
        // the mouse over the model shows the size of the brush before it paints
        if ((mode === 'brush' || mode === 'erase') && e.pointerType === 'mouse' && e.buttons === 0) {
            const first = hoverAt === null;
            hoverAt = [e.clientX, e.clientY];
            if (first) requestAnimationFrame(() => {
                const p = hoverAt; hoverAt = null;
                if (!pm || !p || stroke) return;
                const hit = view.pick(p[0], p[1], pm);
                if (hit) lastPx = view.pixelsPerMm(hit.dist);
                view.showCursor(hit?.point ?? null, radius());
            });
        }
    });
    let lastPx = 0;       // pixels a millimetre takes where the pointer last met the model

    const up = (e: PointerEvent): void => {
        const d = down.get(e.pointerId);
        down.delete(e.pointerId);
        if (!pm) return;
        if (stroke) { endStroke(); return; }
        // a tap (not a drag, one finger) with the fill
        if (mode === 'fill' && d && !d.moved && down.size === 0 && e.type === 'pointerup' && e.button === 0) {
            const x = e.clientX; const y = e.clientY;
            void colorInHand().then((s) => {
                if (!pm || s < 1) return;
                const hit = view.pick(x, y, pm);
                if (!hit) return;
                const before = snap();
                pm.begin();
                pm.flood(hit.tri, angle(), s);
                commit(pm.end(), before);
            });
        }
    };
    canvas.addEventListener('pointerup', up);
    canvas.addEventListener('pointercancel', up);
    canvas.addEventListener('pointerleave', () => { if (!stroke) view.showCursor(null); });

    bar.addEventListener('click', (e) => {
        const el = e.target as HTMLElement;
        const m = el.closest<HTMLElement>('[data-mode]');
        if (m) { setMode(m.dataset.mode as Mode); return; }
        const s = el.closest<HTMLElement>('[data-slot]');
        if (s) { ui.current = Number(s.dataset.slot); if (mode === 'erase' || mode === 'turn') setMode(brushOn && ui.current > 0 ? 'brush' : 'fill'); else drawSwatches(); return; }
        if (el.closest('[data-add]')) void addColor();
    });
    const angleEl = $('paint-angle') as HTMLInputElement; const wholeEl = $('paint-whole') as HTMLInputElement; const sizeEl = $('paint-size') as HTMLInputElement;
    const showOptions = (): void => {
        $('paint-angle-val').textContent = wholeEl.checked ? '' : `${angleEl.value}°`;
        angleEl.disabled = wholeEl.checked;
        $('paint-size-val').textContent = `${nf.format(Number(sizeEl.value))} mm`;
    };
    [angleEl, wholeEl, sizeEl].forEach((el) => el.addEventListener('input', showOptions));

    [$('paint-undo'), $('tool-undo')].forEach((b) => b.addEventListener('click', () => move(-1)));
    [$('paint-redo'), $('tool-redo')].forEach((b) => b.addEventListener('click', () => move(1)));
    document.addEventListener('keydown', (e) => {
        if (!(e.ctrlKey || e.metaKey) || e.key.toLowerCase() !== 'z') return;
        const el = e.target as HTMLElement;
        if (el instanceof HTMLTextAreaElement || (el instanceof HTMLInputElement && !['number', 'range', 'checkbox', 'radio', 'file'].includes(el.type))) return;
        e.preventDefault();
        move(e.shiftKey ? 1 : -1);
    });
    // the page's own buttons of views turn the camera; the picture of the triangles' numbers follows the controls' change
    $('tool-fit').addEventListener('click', () => view.frame());

    $('paint-colors').addEventListener('click', (e) => {
        const el = e.target as HTMLElement;
        const change = el.closest<HTMLElement>('[data-change]'); const remove = el.closest<HTMLElement>('[data-remove]');
        if (change) void changeColor(Number(change.dataset.change));
        else if (remove) removeColor(Number(remove.dataset.remove));
    });
    $('paint-add').addEventListener('click', () => void addColor());
    $('paint-clear').addEventListener('click', clearAll);

    // the bands
    $('paint-band-add').addEventListener('click', async () => {
        if (!pm) { say(t('height.need_model')); return; }
        if (ui.bands.length >= limits.max_heights) return;
        const color = await colorInHand();
        if (color < 1) return;
        const before = snap();
        const top = ui.bands.length ? ui.bands[ui.bands.length - 1].z : 0;
        ui.bands.push({ z: Math.round((top + (height - top) / 2) * 5) / 5, color });
        commit(null, before);
    });
    const bands = $('paint-bands');
    bands.addEventListener('input', (e) => {
        const el = (e.target as HTMLElement).closest<HTMLInputElement>('[data-band-z]');
        if (!el || !pm) return;
        bandDrag = bandDrag ?? snap();
        const b = ui.bands[Number(el.dataset.bandZ)];
        if (!b) return;
        b.z = Number(el.value);
        el.closest('[data-band]')!.querySelector('[data-band-val]')!.textContent = `${nf.format(b.z)} mm`;
        // the shader shows the height at once; the list is put in order when the slider is let go
        view.setBands(cleanBands(ui.bands, height, SLOTS, limits.max_heights), zLo);
    });
    bands.addEventListener('change', (e) => {
        if (!(e.target as HTMLElement).closest('[data-band-z]')) return;
        const before = bandDrag; bandDrag = null;
        if (before) commit(null, before);
    });
    bands.addEventListener('click', (e) => {
        const el = e.target as HTMLElement;
        const remove = el.closest<HTMLElement>('[data-band-remove]'); const color = el.closest<HTMLElement>('[data-band-color]');
        if (!remove && !color) return;
        const before = snap();
        if (remove) ui.bands.splice(Number(remove.dataset.bandRemove), 1);
        else if (color) { const b = ui.bands[Number(color.dataset.bandColor)]; if (b) b.color = Number(color.dataset.slot); }
        commit(null, before);
    });

    // ── saving: the record to the server, a painted 3MF back ────────────────────────────────────────

    const failText = (code: string | null | undefined): string => {
        const key = `error.${(code ?? '').split(':')[0]}`;
        return cfg.i18n[`paint.${key}`] ? t(key, { max: limits.max_colors, n: whole.format(pm?.nT ?? 0) }) : t('error.failed');
    };

    const save = (): Promise<Painted | null> => {
        if (!pm || !model) return Promise.resolve(null);
        if (saved && saved.rev === rev) return Promise.resolve(saved.file);
        if (saving) return saving;
        const mesh = pm; const src = model; const mine = rev;
        const run = async (): Promise<Painted | null> => {
            const used = usedStates(mesh.areas(), ui.bands);
            if (used.length < 2) { stage.error(t('go.need')); return null; }
            stage.error(null);
            stage.busy(true);
            say(t('saving'));
            try {
                // the palette without its free places: the model's own colour first, then the colours in use
                const order = [0, ...used.filter((s) => s !== 0)];
                const map = new Uint8Array(256).fill(0);
                order.forEach((slot, i) => { map[slot] = i; });
                map[255] = 255;
                const rec = mesh.record();
                const form = new FormData();
                form.append('record', JSON.stringify({ v: 1, triangles: rec.triangles, palette: order.map((s) => ui.slots[s]), heights: ui.bands.map((b) => ({ z: b.z, color: map[b.color] })) }));
                form.append('states', new Blob([rec.states.map((s) => map[s])], { type: 'application/octet-stream' }), 'states.bin');
                if (rec.added.length) {
                    form.append('added', new Blob([rec.added.buffer as ArrayBuffer], { type: 'application/octet-stream' }), 'added.bin');
                    form.append('added_states', new Blob([rec.addedStates.map((s) => map[s])], { type: 'application/octet-stream' }), 'added-states.bin');
                }
                const res = await fetch(`${cfg.files}/${src.uuid}/paint`, { method: 'POST', body: form, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' } });
                const body = await res.json().catch(() => ({})) as { file?: Painted; reason?: string };
                if (!res.ok || !body.file) { stage.error(failText(body.reason)); return null; }
                say(t('building'));
                const file = await stage.untilReady(body.file) as Painted;
                if (file.status !== 'ready') { stage.error(failText(file.error)); return null; }
                if (file.paint?.lost) { stage.error(t('saved.lost')); return null; }
                if (mine === rev) saved = { rev: mine, file };
                // a step back from the calculation, or a reload, opens the coloured model, not the bare one
                stage.keep(file.uuid);
                const specks = file.paint?.report?.specks?.count ?? 0;
                result.textContent = specks > 0 ? count('saved.specks', specks) : '';
                result.classList.toggle('hidden', specks < 1);
                return file;
            } catch {
                stage.error(t('error.failed'));
                return null;
            } finally {
                stage.busy(false);
                say(null);
                saving = null;
            }
        };
        saving = run();
        return saving;
    };

    const ways = (): void => {
        stage.go({ disabled: !pm, href: null, run: async () => {
            const fresh = !(saved && saved.rev === rev);
            const file = await save();
            if (!file) return;
            // specks were merged: said first, the next click goes on
            if (fresh && (file.paint?.report?.specks?.count ?? 0) > 0) return;
            location.href = stage.ordered(`${cfg.home}?open=${file.uuid}`);
        } });
        stage.downloads(pm ? [{ label: t('download.3mf'), hint: t('download.3mf.hint'), icon: 'file-box', run: async () => { const file = await save(); if (file) location.href = `${cfg.download}/${file.uuid}/colors.3mf`; } }] : []);
    };

    // ── the model ────────────────────────────────────────────────────────────────────────────────────

    /** A ready model file into the tool: its STL read, welded and shown; colours it came painted in are kept. */
    const open = async (file: FileInfo): Promise<void> => {
        if (file.status !== 'ready' || !file.stl_url) { say(t('model_failed')); return; }
        const n = file.triangles ?? 0;
        if (n > openLimit) { say(t('error.too_heavy', { n: whole.format(n), max: whole.format(openLimit) })); return; }
        say(t('loading'));
        const t0 = performance.now();
        const res = await fetch(file.stl_url, { credentials: 'same-origin' });
        const soup = res.ok ? soupOfStl(await res.arrayBuffer()) : null;
        const t1 = performance.now();
        if (!soup) { say(t('model_failed')); return; }
        if (soup.length / 9 > openLimit) { say(t('error.too_heavy', { n: whole.format(soup.length / 9), max: whole.format(openLimit) })); return; }
        say(t('preparing'));
        await new Promise((r) => setTimeout(r, 30));      // the line above is drawn before the page is busy welding
        const t2 = performance.now();
        const mesh = PaintMesh.fromSoup(soup);
        const t3 = performance.now();
        model = file; pm = mesh;
        [zLo, height] = (() => { const [lo, hi] = mesh.heightRange(); return [lo, Math.max(0.4, hi - lo)]; })();
        entries = []; at = 0; rev++; saved = null; full = false;
        // the colours: those the file came painted in (its STL is ordered by them), else the lightest filament for the
        // model and the first other one in hand
        const slots: (string | null)[] = Array.from({ length: SLOTS }, () => null);
        const groups = (file.groups ?? []).slice(0, Math.min(SLOTS, limits.max_colors));
        const fits = groups.length >= 2 && groups[0].from === 0 && groups[groups.length - 1].to <= mesh.nT;
        if (fits) {
            groups.forEach((g, i) => { slots[i] = g.hex.toLowerCase(); });
            mesh.setRanges(groups.map((g, i) => ({ from: g.from, to: g.to, state: i })));
        } else {
            const light = (hex: string): number => parseInt(hex.slice(1, 3), 16) * 0.3 + parseInt(hex.slice(3, 5), 16) * 0.59 + parseInt(hex.slice(5, 7), 16) * 0.11;
            const base = [...cfg.tiles].sort((a, b) => light(b.hex) - light(a.hex))[0]?.hex ?? '#ffffff';
            slots[0] = base;
            slots[1] = cfg.tiles.find((x) => x.hex !== base)?.hex ?? '#d62828';
        }
        ui = { slots, bands: [], current: 1 };
        view.load(mesh);
        timings = { triangles: mesh.srcT, fetch_ms: Math.round(t1 - t0), weld_ms: Math.round(t3 - t2), buffers_ms: Math.round(performance.now() - t3) };
        stage.el('tool-empty').classList.add('hidden');
        bar.classList.remove('hidden');
        source.textContent = t('source', { name: file.name, n: whole.format(mesh.srcT) });
        source.classList.remove('hidden');
        // the brush refines the mesh: above its limit only the fill and the heights are offered, and the page says so
        // (a model coloured here comes back with what the brush added to it: that much more is still the brush's to work on)
        brushOn = mesh.srcT <= brushLimit + (file.kind === 'paint' ? limits.max_added : 0);
        bar.querySelectorAll<HTMLButtonElement>('[data-mode="brush"], [data-mode="erase"]').forEach((b) => { b.disabled = !brushOn; b.classList.toggle('opacity-40', !brushOn); });
        note.textContent = brushOn ? (fits ? t('opened.painted') : '') : t('brush.off', { n: whole.format(mesh.srcT), max: whole.format(brushLimit) });
        note.classList.toggle('hidden', note.textContent === '');
        // the brush from half a millimetre to a sixth of the model, a fortieth to start with
        const diag = Math.hypot(file.bbox?.x ?? 50, file.bbox?.y ?? 50, file.bbox?.z ?? 50);
        sizeEl.max = String(Math.min(40, Math.max(5, Math.round(diag / 6))));
        sizeEl.value = String(Math.min(5, Math.max(1, Math.round(diag / 40 * 2) / 2)));
        showOptions();
        setMode('fill');
        say(null);
        stage.keep(file.uuid);
        ways();
        refresh();
        stage.revealStage();
    };

    const take = async (picked: File): Promise<void> => {
        stage.error(null);
        if (!cfg.formats.includes(extensionOf(picked.name))) { say(t('bad_format', { formats: cfg.formats.map((f) => f.toUpperCase()).join(', ') })); return; }
        if (picked.size > cfg.maxMb * 1024 * 1024) { say(t('too_big', { max: cfg.maxMb })); return; }
        // a binary STL says how many triangles it has in its header: one too fine is refused before it is sent anywhere
        if (extensionOf(picked.name) === 'stl' && picked.size >= 84) {
            const n = new DataView(await picked.slice(80, 84).arrayBuffer()).getUint32(0, true);
            if (picked.size === 84 + n * 50 && n > openLimit) { say(t('error.too_heavy', { n: whole.format(n), max: whole.format(openLimit) })); return; }
        }
        say(t('uploading', { pct: 0 }));
        try {
            const file = await new Promise<FileInfo>((resolve, reject) => {
                const form = new FormData();
                form.append('file', picked, picked.name);
                const xhr = new XMLHttpRequest();
                xhr.open('POST', cfg.upload);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '');
                xhr.withCredentials = true;
                xhr.upload.onprogress = (e) => { if (e.lengthComputable) say(t('uploading', { pct: Math.round((e.loaded / e.total) * 100) })); };
                xhr.onload = () => { try { const body = JSON.parse(xhr.responseText) as { file?: FileInfo }; if (xhr.status < 300 && body.file) resolve(body.file); else reject(new Error('upload')); } catch { reject(new Error('upload')); } };
                xhr.onerror = () => reject(new Error('upload'));
                xhr.send(form);
            });
            say(t('processing'));
            await open(await stage.untilReady(file));
        } catch {
            say(t('model_failed'));
        }
    };

    stage.dropZone($('paint-drop'), input, (f) => void take(f));
    showOptions();
    setMode('fill');
    ways();
    stage.status(null);
    if (cfg.from) {
        say(t('loading'));
        void stage.fetchFile(cfg.from).then((f) => stage.untilReady(f)).then(open).catch(() => say(t('model_failed')));
    }
    // for the walk-through scripts and the measurements (docs/AS.md §6): the mesh and the view of this page
    (window as unknown as { mpPaint?: unknown }).mpPaint = { mesh: () => pm, view, save, state: () => ({ ui, mode, rev, entries: entries.length, at, timings, brushOn, phone, changes }) };
}
