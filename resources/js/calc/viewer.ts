import {
    AmbientLight, BufferGeometry, Color, ConeGeometry, CylinderGeometry, DirectionalLight, GridHelper, Group, HemisphereLight, Line, LineBasicMaterial,
    LineSegments, Material, Mesh, MeshBasicMaterial, MeshStandardMaterial, PerspectiveCamera, Raycaster, Scene, SphereGeometry, Vector2, Vector3,
    WebGLRenderer, Box3, Float32BufferAttribute, PlaneGeometry, EdgesGeometry, DoubleSide,
} from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { mergeVertices } from 'three/examples/jsm/utils/BufferGeometryUtils.js';

/** Default colour of a previewed model: a calm, slightly desaturated blue, like a light-blue PLA spool. */
export const MODEL_COLOR = 0x83a6d4;

/** Small three.js viewer: one mesh, orbit controls, auto-fit, resize aware, touch friendly. */
export class Viewer {
    private renderer: WebGLRenderer;
    private scene = new Scene();
    private camera: PerspectiveCamera;
    private controls: OrbitControls;
    private mesh: Mesh | null = null;
    private supports: LineSegments | null = null;
    private grid: GridHelper | null = null;
    private frontal = false;
    // soft filament blue + flat shading: layer-like facets and embossed letters stay readable; sits well with the ink and orange of the site
    private material = new MeshStandardMaterial({ color: MODEL_COLOR, roughness: 0.75, metalness: 0.0, flatShading: true });
    // generated busts and figures: light bronze and smooth shading, so the preview reads as a small sculpture, not as facets
    private sculptureMaterial = new MeshStandardMaterial({ color: 0xc98f5a, roughness: 0.42, metalness: 0.25, flatShading: false });
    // plates (signs, reliefs, lithophanes): colour follows the height, so letters and pictures read like a two-colour print
    private plateMaterial = new MeshStandardMaterial({ color: 0xffffff, roughness: 0.85, metalness: 0.0, flatShading: true, vertexColors: true });

    // ── the tool page's additions (see "Tool page" below); every other page leaves them untouched ──
    private pieces: Piece[] = [];
    private basePos: Float32Array | null = null;        // positions as the file has them (the spread view moves copies)
    private baseCol: Float32Array | null = null;        // colours as setGeometry painted them, null = plain material
    private baseMaterial: Material | null = null;
    private pieceColors = new Map<number, [number, number, number]>();
    private selected = -1;
    private highlight: Mesh | null = null;
    private spread = 0;
    private spreadAxis: 'x' | 'y' | 'z' | null = null;      // a stack of plates comes apart along one axis, in order
    private planes: Group | null = null;                    // cutting planes drawn on the model (the split tool)
    private hold = false;
    private framed = false;
    private framedSize = 0;
    private bed: { x: number; y: number; margin: number } | null = null;
    private bedGroup: Group | null = null;
    private over = false;
    private bedCheck = true;
    private handles: { id: string; axis: 'x' | 'y' | 'z'; group: Group; dir: Vector3 }[] = [];
    private onHandle: ((id: string, mm: number, phase: 'move' | 'end') => string | void) | null = null;
    private handleLabel: HTMLElement | null = null;
    private onPick: ((index: number, piece: Piece | null) => void) | null = null;
    private marker: { group: Group; path: [number, number][]; z: number } | null = null;
    private onMarker: ((share: number, phase: 'move' | 'end') => string | void) | null = null;
    private draw: { z: number; color: string } | null = null;
    private onDraw: ((points: [number, number][]) => void) | null = null;
    private frame: { group: Group; grips: Mesh[]; box: [number, number, number, number]; z: number } | null = null;
    private onFrame: ((change: FrameChange, phase: 'move' | 'end') => string | void) | null = null;
    private framing: { mode: 'move' | 'size' | 'turn'; from: Vector3; change: FrameChange; x: number; y: number; moved: boolean } | null = null;
    private listening = false;
    private anim: { from: Vector3; to: Vector3; t0: number; ms: number } | null = null;

    constructor(private canvas: HTMLCanvasElement) {
        this.renderer = new WebGLRenderer({ canvas, antialias: true, alpha: false });
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.scene.background = new Color(0xf8fafc);
        this.camera = new PerspectiveCamera(40, 1, 0.1, 100000);
        this.controls = new OrbitControls(this.camera, canvas);
        this.controls.enableDamping = true;
        this.scene.add(new AmbientLight(0xffffff, 0.9));
        this.scene.add(new HemisphereLight(0xffffff, 0x94a3b8, 1.2));
        const key = new DirectionalLight(0xffffff, 2.6);   // raking light from the front-left: relief casts readable shading
        key.position.set(-1.5, 2.2, 2.5);
        this.scene.add(key);
        const fill = new DirectionalLight(0xffffff, 1.0);
        fill.position.set(2.5, 1.0, -1.5);
        this.scene.add(fill);
        new ResizeObserver(() => this.resize()).observe(canvas);
        this.resize();
        this.loop();
    }

    /**
     * kind: what the model is ('lithophane' previews as if lit from behind: thin = bright)
     * faces: one number per triangle of the file and the colours they stand for; the triangles keep their order
     */
    setGeometry(geom: BufferGeometry, scale = 1, kind: string | null = null, regions: Region[] | null = null, faces: FacePaint | null = null): void {
        if (this.mesh) {
            this.scene.remove(this.mesh);
            this.mesh.geometry.dispose();
        }
        if (this.grid) this.scene.remove(this.grid);
        const painted = !!faces && paintByFaces(geom, faces);
        const sculpture = kind === 'generated' && !regions?.length && !painted;
        if (sculpture) {
            // STL has no shared vertices; weld them so the normals blend across faces
            geom.deleteAttribute('normal');
            geom = mergeVertices(geom, 1e-4);
            geom.computeVertexNormals();
        }
        if (!geom.getAttribute('normal')) geom.computeVertexNormals();
        // a real two-colour print changes colour at one height: no triangle may reach across it
        const change = regions?.find((r) => r.exact);
        if (change && !painted) geom = cutAtHeight(geom, change.z0);
        // Z-up files (all print formats) → three.js Y-up
        const plate = painted ? true : regions?.length ? paintByRegion(geom, regions) : paintByHeight(geom, kind === 'lithophane', kind === 'qr');
        this.mesh = new Mesh(geom, plate ? this.plateMaterial : sculpture ? this.sculptureMaterial : this.material);
        this.frontal = sculpture;
        this.mesh.rotation.x = -Math.PI / 2;
        this.mesh.scale.setScalar(scale);
        this.scene.add(this.mesh);
        this.fit();
        this.afterGeometry();
    }

    /** A picture of the model as it is framed now (drawn in this very call, so no kept drawing buffer is needed). */
    async snapshot(type = 'image/webp', quality = 0.85): Promise<Blob | null> {
        this.resize();
        this.controls.update();
        this.renderer.render(this.scene, this.camera);
        const data = this.canvas.toDataURL(type, quality);
        return data.startsWith('data:image/') ? (await fetch(data)).blob() : null;
    }

    setScale(scale: number): void {
        if (!this.mesh) return;
        this.mesh.scale.setScalar(scale);
    }

    /** The plain model in a filament colour (CSS hex); null goes back to the default blue. */
    setColor(hex: string | null): void {
        this.material.color.set(hex && /^#?[0-9a-f]{6}$/i.test(hex) ? (hex.startsWith('#') ? hex : `#${hex}`) : MODEL_COLOR);
    }

    /**
     * Support structures from the slicer (App\Engines\Gcode\SupportLines): float32 header of 8 (version, count,
     * model footprint min x, min y, max x, max y) then count × two points, in printer coordinates. They are hung under
     * the mesh and shifted so the footprint of the sliced part lands on the STL. null removes them.
     */
    setSupports(buffer: ArrayBuffer | null, visible = true): void {
        if (this.supports) {
            this.supports.parent?.remove(this.supports);
            this.supports.geometry.dispose();
            this.supports = null;
        }
        if (!buffer || !this.mesh || buffer.byteLength < 32) return;
        const head = new Float32Array(buffer, 0, 8);
        const n = Math.min(head[1], Math.floor((buffer.byteLength - 32) / 24));
        if (head[0] !== 1 || n < 1) return;
        const pts = new Float32Array(buffer, 32, n * 6);
        this.mesh.geometry.computeBoundingBox();
        const b = this.mesh.geometry.boundingBox!;
        const dx = (b.min.x + b.max.x) / 2 - (head[2] + head[4]) / 2;
        const dy = (b.min.y + b.max.y) / 2 - (head[3] + head[5]) / 2;
        const geom = new BufferGeometry();
        geom.setAttribute('position', new Float32BufferAttribute(pts, 3));
        this.supports = new LineSegments(geom, new LineBasicMaterial({ color: 0x64748b, transparent: true, opacity: 0.55 }));
        this.supports.position.set(dx, dy, b.min.z);
        this.supports.visible = visible;
        this.mesh.add(this.supports);
    }

    /** Paints the shown model triangle by triangle, or (null) gives it its plain colour back; the view stays as it is. */
    paint(faces: FacePaint | null): boolean {
        if (!this.mesh) return false;
        const painted = !!faces && paintByFaces(this.mesh.geometry, faces);
        this.mesh.material = painted ? this.plateMaterial : this.material;
        return painted;
    }

    showSupports(on: boolean): void {
        if (this.supports) this.supports.visible = on;
    }


    private fit(): void {
        if (!this.mesh) return;
        const box = new Box3().setFromObject(this.mesh);
        const size = box.getSize(new Vector3());
        const center = box.getCenter(new Vector3());
        this.mesh.position.sub(center);
        this.mesh.position.y += size.y / 2; // stand on the grid
        const radius = Math.max(size.x, size.y, size.z) || 1;
        this.grid = new GridHelper(radius * 2.5, 10, 0xcbd5e1, 0xe2e8f0);
        this.grid.visible = !this.bed;
        this.scene.add(this.grid);
        // frame the bounding sphere: whatever the proportions, the model fills the view without being cut off
        const sphere = 0.5 * Math.hypot(size.x, size.y, size.z) || 1;
        const half = (this.camera.fov * Math.PI) / 360;
        const dist = (sphere / Math.sin(half)) * 1.08 / Math.min(1, this.camera.aspect || 1);
        // flat things (signs, plates, reliefs) are looked at from above, tall things from the side
        const flat = size.y / radius < 0.2;
        const standingPlate = !flat && size.z / radius < 0.2; // lithophane standing on its edge: look at its face
        // a bust is looked at almost from the front, like a portrait
        const dir = flat ? new Vector3(0.14, 0.86, 0.5) : standingPlate ? new Vector3(0.25, 0.18, 0.95) : this.frontal ? new Vector3(0.38, 0.22, 0.9) : new Vector3(0.62, 0.45, 0.7);
        // the tool page keeps the visitor's own view while numbers change (keepView); "fit" frames again
        // …unless the model has become a clearly different thing (a lid laid next to its box): then it is framed anew
        const keep = this.hold && this.framed && Math.abs(sphere - this.framedSize) / (this.framedSize || 1) < 0.2;
        if (!keep) this.framedSize = sphere;
        if (!keep) this.camera.position.copy(dir.normalize().multiplyScalar(dist)).add(new Vector3(0, size.y / 2, 0));
        this.camera.near = Math.min(radius / 100, keep ? this.camera.near : Infinity);
        this.camera.far = Math.max(radius * 100, keep ? this.camera.far : 0);
        this.camera.updateProjectionMatrix();
        if (!keep) this.controls.target.set(0, size.y / 2, 0);
        this.controls.update();
        this.framed = true;
    }

    private resize(): void {
        const w = this.canvas.clientWidth || 300;
        const h = this.canvas.clientHeight || 300;
        this.renderer.setSize(w, h, false);
        this.camera.aspect = w / h;
        this.camera.updateProjectionMatrix();
    }

    private loop = (): void => {
        this.tick();
        this.controls.update();
        this.renderer.render(this.scene, this.camera);
        requestAnimationFrame(this.loop);
    };

    // ── Tool page: pieces, picking, colours, spread view, x-ray, fixed views, print bed, size handles ──────────

    /** Called at the end of setGeometry: what was set for the previous model does not hold for the new one. */
    private afterGeometry(): void {
        if (this.highlight) { this.highlight.geometry.dispose(); this.highlight = null; }
        this.selected = -1;
        this.pieces = [];
        this.basePos = null;
        this.baseCol = null;
        this.baseMaterial = this.mesh ? (this.mesh.material as Material) : null;
        if (this.mesh && !this.mesh.geometry.index) {
            const col = this.mesh.geometry.getAttribute('color');
            this.baseCol = col && this.mesh.material === this.plateMaterial ? new Float32Array(col.array as Float32Array) : null;
        }
        if (this.bed) this.drawBed();
        this.placeHandles();
    }

    /** While on, a new model keeps the camera where the visitor left it (numbers change, the view does not jump). */
    keepView(on: boolean): void { this.hold = on; }

    /** Frame the model again from the default side. */
    fitView(): void {
        if (!this.mesh) return;
        const was = this.hold; this.hold = false;
        if (this.grid) this.scene.remove(this.grid);
        this.fit();
        this.hold = was;
        this.placeHandles();
    }

    /**
     * Which triangles are which piece (the order of the file; from the preview's `parts`). Pieces that do not cover
     * the shown triangles exactly (the model was cut for two colours, welded for smooth shading) become one piece.
     */
    setPieces(pieces: Piece[] | null): void {
        const pos = this.mesh?.geometry.getAttribute('position');
        const count = pos && !this.mesh!.geometry.index ? pos.count / 3 : 0;
        const ok = !!pieces?.length && pieces[pieces.length - 1].tris[1] === count && pieces.every((p, i) => p.tris[0] === (i ? pieces[i - 1].tris[1] : 0));
        this.pieces = ok ? pieces! : count ? [{ name: 'body', tris: [0, count] }] : [];
        this.pieceColors.clear();
        this.basePos = pos && count ? new Float32Array(pos.array as Float32Array) : null;
        if (this.spread) this.setSpread(this.spread);
    }

    getPieces(): Piece[] { return this.pieces; }

    /** A click (not a drag) on the model tells which piece was hit; -1 and null for a click beside it. */
    onPiecePicked(cb: (index: number, piece: Piece | null) => void): void {
        this.onPick = cb;
        this.listen();
    }

    /** Marks one piece (an orange veil over it); -1 takes the mark away. */
    select(index: number): void {
        if (this.highlight) { this.highlight.parent?.remove(this.highlight); this.highlight.geometry.dispose(); this.highlight = null; }
        this.selected = index >= 0 && index < this.pieces.length && this.pieces.length > 1 ? index : -1;
        const pos = this.mesh?.geometry.getAttribute('position');
        if (this.selected < 0 || !pos || !this.mesh) return;
        const [a, b] = this.pieces[this.selected].tris;
        const geom = new BufferGeometry();
        geom.setAttribute('position', new Float32BufferAttribute((pos.array as Float32Array).slice(a * 9, b * 9), 3));
        this.highlight = new Mesh(geom, new MeshBasicMaterial({ color: 0xc94714, transparent: true, opacity: 0.3, depthWrite: false, polygonOffset: true, polygonOffsetFactor: -2, polygonOffsetUnits: -2 }));
        this.highlight.renderOrder = 5;
        this.mesh.add(this.highlight);
    }

    /** One piece in a filament colour (CSS hex), or null to give it back the colour the model came with. */
    setPieceColor(index: number, hex: string | null): void {
        const rgb = hex ? hexRgb(hex) : null;
        if (rgb) this.pieceColors.set(index, deep(rgb)); else this.pieceColors.delete(index);
        this.repaint();
    }

    /** Colours of the pieces and the red of what hangs over the bed, laid over the colours setGeometry gave the model. */
    private repaint(): void {
        const mesh = this.mesh;
        const pos = mesh?.geometry.getAttribute('position');
        if (!mesh || !pos || mesh.geometry.index || !this.baseMaterial || this.baseMaterial === this.sculptureMaterial) return;
        const beyond = this.bed && this.bedCheck ? this.beyondBed() : null;
        this.over = !!beyond;
        if (!this.pieceColors.size && !beyond) {
            if (this.baseCol) mesh.geometry.setAttribute('color', new Float32BufferAttribute(this.baseCol.slice(), 3)); else mesh.geometry.deleteAttribute('color');
            mesh.material = this.baseMaterial;
            return;
        }
        const plain = new Color(MODEL_COLOR);
        const col = this.baseCol ? this.baseCol.slice() : new Float32Array(pos.count * 3).map((_, i) => [plain.r, plain.g, plain.b][i % 3]);
        this.pieceColors.forEach((c, index) => {
            const piece = this.pieces[index]; if (!piece) return;
            for (let v = piece.tris[0] * 3; v < piece.tris[1] * 3; v++) { col[v * 3] = c[0]; col[v * 3 + 1] = c[1]; col[v * 3 + 2] = c[2]; }
        });
        if (beyond) for (let t = 0; t < beyond.length; t++) if (beyond[t]) for (let k = 0; k < 3; k++) { col[(t * 3 + k) * 3] = 0.62; col[(t * 3 + k) * 3 + 1] = 0.03; col[(t * 3 + k) * 3 + 2] = 0.03; }
        mesh.geometry.setAttribute('color', new Float32BufferAttribute(col, 3));
        mesh.material = this.plateMaterial;
    }

    /** 0…1: the pieces move apart from the middle of the whole, so each can be seen and picked. */
    setSpread(k: number): void {
        this.spread = Math.max(0, Math.min(1, k));
        const mesh = this.mesh; const pos = mesh?.geometry.getAttribute('position');
        if (!mesh || !pos || !this.basePos || this.pieces.length < 2) return;
        const out = pos.array as Float32Array; const base = this.basePos;
        const centre = (a: number, b: number): [number, number, number, number] => {
            const lo = [Infinity, Infinity, Infinity]; const hi = [-Infinity, -Infinity, -Infinity];
            for (let i = a * 9; i < b * 9; i += 3) for (let k = 0; k < 3; k++) { if (base[i + k] < lo[k]) lo[k] = base[i + k]; if (base[i + k] > hi[k]) hi[k] = base[i + k]; }
            return [(lo[0] + hi[0]) / 2, (lo[1] + hi[1]) / 2, (lo[2] + hi[2]) / 2, Math.hypot(hi[0] - lo[0], hi[1] - lo[1], hi[2] - lo[2]) / 2];
        };
        const whole = centre(0, base.length / 9);
        this.pieces.forEach((piece, n) => {
            const c = centre(piece.tris[0], piece.tris[1]);
            let d = [c[0] - whole[0], c[1] - whole[1], c[2] - whole[2]];
            let len = Math.hypot(d[0], d[1], d[2]);
            if (len < 0.5) { d = [0, 0, n % 2 ? 1 : -1]; len = 1; }                   // pieces that share a centre (a lid on its box) part along the height
            let move = this.spread * Math.max(len * 0.7, whole[3] * 0.3);
            if (this.spreadAxis) {
                // plates of a stack: each one further along the axis than the one before it, in the order of the list
                d = [this.spreadAxis === 'x' ? 1 : 0, this.spreadAxis === 'y' ? 1 : 0, this.spreadAxis === 'z' ? 1 : 0]; len = 1;
                move = this.spread * n * Math.max(whole[3] * 0.18, 4);
            }
            const o = [d[0] / len * move, d[1] / len * move, d[2] / len * move];
            for (let i = piece.tris[0] * 9; i < piece.tris[1] * 9; i += 3) { out[i] = base[i] + o[0]; out[i + 1] = base[i + 1] + o[1]; out[i + 2] = base[i + 2] + o[2]; }
        });
        pos.needsUpdate = true;
        mesh.geometry.computeBoundingBox(); mesh.geometry.computeBoundingSphere();
        if (this.selected >= 0) this.select(this.selected);
        this.handles.forEach((h) => { h.group.visible = this.spread === 0; });
    }

    /** The spread view moves the pieces along this axis of the file, one after another (a stack of plates); null: apart from the middle. */
    setSpreadAxis(axis: 'x' | 'y' | 'z' | null): void {
        this.spreadAxis = axis;
        if (this.spread) this.setSpread(this.spread);
    }

    /**
     * Cutting planes across the model (the split tool): `at` in the millimetres of the file along `axis`, drawn as
     * orange sheets over the whole model; null takes them away. They belong to the shown model and go with it.
     */
    setPlanes(planes: { axis: 'x' | 'y' | 'z'; at: number }[] | null): void {
        if (this.planes) { this.planes.parent?.remove(this.planes); this.planes.traverse((o) => { const m = o as Mesh; m.geometry?.dispose?.(); }); this.planes = null; }
        if (!planes?.length || !this.mesh) return;
        const geom = this.mesh.geometry;
        if (!geom.boundingBox) geom.computeBoundingBox();
        const box = geom.boundingBox!;
        const size = new Vector3(); box.getSize(size);
        const mid = new Vector3(); box.getCenter(mid);
        const group = new Group();
        const fill = new MeshBasicMaterial({ color: 0xc94714, transparent: true, opacity: 0.22, side: DoubleSide, depthWrite: false });
        const edge = new LineBasicMaterial({ color: 0xc94714 });
        planes.forEach((p) => {
            const grow = 1.06;
            const w = (p.axis === 'x' ? size.z : size.x) * grow; const h = (p.axis === 'z' ? size.y : p.axis === 'y' ? size.z : size.y) * grow;
            const sheet = new Mesh(new PlaneGeometry(w, h), fill);
            const outline = new LineSegments(new EdgesGeometry(sheet.geometry), edge);
            sheet.add(outline);
            if (p.axis === 'x') { sheet.rotation.y = Math.PI / 2; sheet.position.set(p.at, mid.y, mid.z); }
            else if (p.axis === 'y') { sheet.rotation.x = Math.PI / 2; sheet.position.set(mid.x, p.at, mid.z); }
            else sheet.position.set(mid.x, mid.y, p.at);
            sheet.renderOrder = 6;
            group.add(sheet);
        });
        this.planes = group;
        this.mesh.add(group);
    }

    /** See-through walls (35 %): what is inside a hollow thing. */
    setXray(on: boolean): void {
        [this.material, this.sculptureMaterial, this.plateMaterial].forEach((m) => { m.transparent = on; m.opacity = on ? 0.35 : 1; m.depthWrite = !on; m.needsUpdate = true; });
    }

    /** A fixed view, reached in 300 ms; the distance and what is looked at stay. */
    setView(view: ViewName, ms = 300): void {
        const dirs: Record<ViewName, [number, number, number]> = { iso: [0.62, 0.45, 0.7], top: [0, 1, 0.0005], bottom: [0, -1, 0.0005], front: [0, 0, 1], side: [1, 0, 0] };
        const target = this.controls.target;
        const dist = this.camera.position.distanceTo(target) || 100;
        const to = new Vector3(...dirs[view]).normalize().multiplyScalar(dist).add(target);
        this.anim = { from: this.camera.position.clone(), to, t0: performance.now(), ms: Math.max(1, ms) };
    }

    /** The print bed under the model (mm, the margin the farm keeps free); null takes it away. What hangs over turns red. */
    setBed(bed: { x: number; y: number; margin: number } | null): void {
        this.bed = bed;
        if (this.grid) this.grid.visible = !bed;
        this.drawBed();
    }

    /**
     * Whether what hangs over the bed is painted red. A set shown assembled (bins in their tray) may be wider than the
     * bed while every piece of it fits: the page knows, and switches the red off.
     */
    checkBed(on: boolean): void {
        if (this.bedCheck === on) return;
        this.bedCheck = on;
        this.drawBed();
    }

    /** True when the shown model does not fit the usable part of the bed. */
    overBed(): boolean { return this.over; }

    private drawBed(): void {
        if (this.bedGroup) { this.scene.remove(this.bedGroup); this.bedGroup = null; }
        if (!this.bed) { this.repaint(); return; }
        this.repaint();
        const { x, y, margin } = this.bed;
        const g = new Group();
        const lines = (pts: number[], color: number, opacity = 1): LineSegments => {
            const geom = new BufferGeometry(); geom.setAttribute('position', new Float32BufferAttribute(pts, 3));
            return new LineSegments(geom, new LineBasicMaterial({ color, transparent: opacity < 1, opacity }));
        };
        const rect = (w: number, d: number): number[] => { const a = w / 2; const b = d / 2; return [-a, 0, -b, a, 0, -b, a, 0, -b, a, 0, b, a, 0, b, -a, 0, b, -a, 0, b, -a, 0, -b]; };
        const cells: number[] = [];
        for (let i = 25; i < x; i += 25) cells.push(-x / 2 + i, 0, -y / 2, -x / 2 + i, 0, y / 2);
        for (let i = 25; i < y; i += 25) cells.push(-x / 2, 0, -y / 2 + i, x / 2, 0, -y / 2 + i);
        g.add(lines(cells, 0xe2e8f0));
        g.add(lines(rect(x, y), this.over ? 0xb42318 : 0x94a3b8));
        if (margin > 0) g.add(lines(rect(x - 2 * margin, y - 2 * margin), this.over ? 0xb42318 : 0xcbd5e1, 0.9));
        this.bedGroup = g;
        this.scene.add(g);
    }

    /** One flag per triangle that reaches beyond the usable bed (the model stands centred on it); null when all fits. */
    private beyondBed(): Uint8Array | null {
        const mesh = this.mesh; const pos = mesh?.geometry.getAttribute('position');
        if (!mesh || !pos || !this.bed || mesh.geometry.index) return null;
        const s = mesh.scale.x; const hx = this.bed.x / 2 - this.bed.margin; const hy = this.bed.y / 2 - this.bed.margin;
        const flags = new Uint8Array(pos.count / 3); let any = false;
        for (let t = 0; t < flags.length; t++) {
            for (let k = 0; k < 3; k++) {
                const wx = pos.getX(t * 3 + k) * s + mesh.position.x; const wz = -pos.getY(t * 3 + k) * s + mesh.position.z;
                if (Math.abs(wx) > hx + 0.01 || Math.abs(wz) > hy + 0.01) { flags[t] = 1; any = true; break; }
            }
        }
        return any ? flags : null;
    }

    /**
     * Arrows on the walls of the model for the sizes that can be dragged: x (width, right wall), y (depth, back wall),
     * z (height, top). The callback gets how far the wall was dragged along its axis in mm and may answer with the
     * text shown at the arrow ("120 mm"). The model itself is rebuilt by the page, not by the viewer.
     */
    setHandles(handles: { id: string; axis: 'x' | 'y' | 'z' }[], cb: ((id: string, mm: number, phase: 'move' | 'end') => string | void) | null): void {
        this.handles.forEach((h) => this.scene.remove(h.group));
        this.onHandle = cb;
        const ink = new MeshBasicMaterial({ color: 0x172b4d, depthTest: false, transparent: true, opacity: 0.92 });
        this.handles = handles.map((h) => {
            const group = new Group();
            const shaft = new Mesh(new CylinderGeometry(0.035, 0.035, 0.7, 10), ink); shaft.position.y = 0.35;
            const tip = new Mesh(new ConeGeometry(0.14, 0.34, 16), ink); tip.position.y = 0.86;
            const grab = new Mesh(new SphereGeometry(0.42, 8, 8), new MeshBasicMaterial({ visible: false })); grab.position.y = 0.6;
            [shaft, tip, grab].forEach((m) => { m.renderOrder = 10; m.userData.handle = h.id; group.add(m); });
            // file axes → the scene (Z of the file is up): x right, y away from the eye, z up
            const dir = h.axis === 'x' ? new Vector3(1, 0, 0) : h.axis === 'y' ? new Vector3(0, 0, -1) : new Vector3(0, 1, 0);
            group.quaternion.setFromUnitVectors(new Vector3(0, 1, 0), dir);
            this.scene.add(group);
            return { id: h.id, axis: h.axis, group, dir };
        });
        if (handles.length) this.listen();
        this.placeHandles();
    }

    /**
     * A grip that travels along a closed line lying on the model: the eyelet on the outline of a pendant. `path` is the
     * line in the millimetres of the file (x, y), its points evenly spread along its length; `z` the height it lies at;
     * `at` where the grip is now. Dragging reports the share of the line (0…1 from its first point) nearest to the pointer.
     */
    setMarker(marker: { path: [number, number][]; z: number; at: [number, number] } | null, cb: ((share: number, phase: 'move' | 'end') => string | void) | null): void {
        if (this.marker) this.scene.remove(this.marker.group);
        this.marker = null; this.onMarker = cb;
        if (!marker || !this.mesh || marker.path.length < 3) return;
        const group = new Group();
        const dot = new Mesh(new SphereGeometry(0.16, 20, 14), new MeshBasicMaterial({ color: 0xc94714, depthTest: false, transparent: true, opacity: 0.95 }));
        const halo = new Mesh(new SphereGeometry(0.24, 20, 14), new MeshBasicMaterial({ color: 0xffffff, depthTest: false, transparent: true, opacity: 0.55 }));
        const grab = new Mesh(new SphereGeometry(0.5, 8, 8), new MeshBasicMaterial({ visible: false }));
        halo.renderOrder = 10; dot.renderOrder = 11;
        group.add(halo, dot, grab);
        this.scene.add(group);
        this.marker = { group, path: marker.path, z: marker.z };
        this.moveMarker(marker.at[0], marker.at[1]);
        this.listen();
    }

    /**
     * Drawing on the model with the pointer: icing piped on a biscuit. While it is on, a press and a drag on the model
     * is a stroke (the view is seen from above and does not turn); `cb` gets its points in the millimetres of the file
     * when the pointer is lifted, a single point for a tap. `z` is the height the strokes are drawn at.
     */
    setDraw(draw: { z: number; color: string } | null, cb: ((points: [number, number][]) => void) | null): void {
        const starting = !!draw && !this.draw;
        this.draw = draw; this.onDraw = cb;
        this.controls.enableRotate = !draw;
        this.canvas.style.cursor = draw ? 'crosshair' : '';
        if (starting) { this.setView('top'); this.listen(); }
    }

    /**
     * A frame round a thing lying on the model that the visitor moves, resizes and turns with the pointer: a layer of
     * a composition. `box` is the frame in the millimetres of the file (x0, y0, x1, y1), `z` the height it is drawn at.
     * A drag inside it moves the thing, a drag by a corner resizes it about its middle, the knob above it turns it.
     * `cb` gets what has changed since the press; the model itself stays as it is until the caller builds it anew.
     */
    setFrame(frame: { box: [number, number, number, number]; z: number } | null, cb: ((change: FrameChange, phase: 'move' | 'end') => string | void) | null): void {
        if (this.frame) {
            this.scene.remove(this.frame.group);
            this.frame.group.traverse((o) => { (o as Mesh).geometry?.dispose(); });
        }
        if (this.framing) { this.framing = null; this.controls.enabled = true; this.handleLabel?.classList.add('hidden'); }
        this.frame = null; this.onFrame = cb;
        if (!frame || !this.mesh) { this.canvas.style.cursor = this.draw ? 'crosshair' : ''; return; }
        this.mesh.updateMatrixWorld();
        const [x0, y0, x1, y1] = frame.box; const z = frame.z + 0.05;
        const mid = this.mesh.localToWorld(new Vector3((x0 + x1) / 2, (y0 + y1) / 2, z));
        const at = (x: number, y: number): Vector3 => this.mesh!.localToWorld(new Vector3(x, y, z)).sub(mid);
        const knob = y1 + Math.max(3, (y1 - y0) * 0.18);
        const group = new Group();
        group.position.copy(mid);
        const line = new Line(
            new BufferGeometry().setFromPoints([at((x0 + x1) / 2, knob), at((x0 + x1) / 2, y1), at(x1, y1), at(x1, y0), at(x0, y0), at(x0, y1), at((x0 + x1) / 2, y1)]),
            new LineBasicMaterial({ color: 0xc94714, depthTest: false, transparent: true }),
        );
        line.renderOrder = 10; line.frustumCulled = false;
        group.add(line);
        const grip = (p: Vector3, mode: 'size' | 'turn'): Mesh => {
            const dot = new Mesh(new SphereGeometry(0.13, 16, 12), new MeshBasicMaterial({ color: mode === 'turn' ? 0xc94714 : 0xffffff, depthTest: false, transparent: true }));
            const rim = new Mesh(new SphereGeometry(0.17, 16, 12), new MeshBasicMaterial({ color: mode === 'turn' ? 0xffffff : 0xc94714, depthTest: false, transparent: true }));
            const grab = new Mesh(new SphereGeometry(0.45, 8, 8), new MeshBasicMaterial({ visible: false }));
            rim.renderOrder = 11; dot.renderOrder = 12;
            grab.add(rim, dot);
            grab.position.copy(p); grab.userData.mode = mode;
            group.add(grab);
            return grab;
        };
        const grips = [grip(at(x0, y0), 'size'), grip(at(x1, y0), 'size'), grip(at(x1, y1), 'size'), grip(at(x0, y1), 'size'), grip(at((x0 + x1) / 2, knob), 'turn')];
        this.scene.add(group);
        this.frame = { group, grips, box: frame.box, z: frame.z };
        this.listen();
    }

    /** What of the frame lies under the pointer: a corner, the knob, its inside, or nothing. */
    private frameUnder(ray: Raycaster): { mode: 'move' | 'size' | 'turn'; at: Vector3 } | null {
        if (!this.frame || !this.onFrame) return null;
        const p = this.fileAt(ray, this.frame.z);
        if (!p) return null;
        const hit = ray.intersectObjects(this.frame.grips, false)[0];
        if (hit) return { mode: hit.object.userData.mode, at: p };
        const [x0, y0, x1, y1] = this.frame.box;
        return p.x >= x0 && p.x <= x1 && p.y >= y0 && p.y <= y1 ? { mode: 'move', at: p } : null;
    }

    /** Where the pointer's ray meets the height z of the model, in the millimetres of the file. */
    private fileAt(ray: Raycaster, z: number): Vector3 | null {
        if (!this.mesh) return null;
        this.mesh.updateMatrixWorld();
        const height = this.mesh.localToWorld(new Vector3(0, 0, z)).y;
        const k = (height - ray.ray.origin.y) / (ray.ray.direction.y || 1e-9);
        return k > 0 ? this.mesh.worldToLocal(ray.ray.origin.clone().addScaledVector(ray.ray.direction, k)) : null;
    }

    private moveMarker(x: number, y: number): void {
        if (!this.marker || !this.mesh) return;
        this.mesh.updateMatrixWorld();
        this.marker.group.position.copy(this.mesh.localToWorld(new Vector3(x, y, this.marker.z)));
    }

    /** The point of the marker's line nearest to where the pointer's ray meets the line's height: [share, x, y] in file millimetres. */
    private markerUnder(ray: Raycaster): [number, number, number] | null {
        if (!this.marker || !this.mesh) return null;
        const p = this.fileAt(ray, this.marker.z);
        if (!p) return null;
        const path = this.marker.path; const n = path.length;
        let best: [number, number, number] | null = null; let bestD = Infinity;
        for (let i = 0; i < n; i++) {
            const a = path[i]; const b = path[(i + 1) % n];
            const dx = b[0] - a[0]; const dy = b[1] - a[1];
            const t = Math.max(0, Math.min(1, ((p.x - a[0]) * dx + (p.y - a[1]) * dy) / (dx * dx + dy * dy || 1)));
            const x = a[0] + dx * t; const y = a[1] + dy * t;
            const d = (p.x - x) ** 2 + (p.y - y) ** 2;
            if (d < bestD) { bestD = d; best = [(i + t) / n, x, y]; }
        }
        return best;
    }

    private placeHandles(): void {
        if (!this.mesh || !this.handles.length) return;
        const box = new Box3().setFromObject(this.mesh);
        const mid = box.getCenter(new Vector3());
        this.handles.forEach((h) => {
            h.group.position.set(h.axis === 'x' ? box.max.x : mid.x, h.axis === 'z' ? box.max.y : mid.y, h.axis === 'y' ? box.min.z : mid.z);
            h.group.visible = this.spread === 0;
        });
    }

    /** Every frame: the running change of view, and the arrows kept the same size on the screen. */
    private tick(): void {
        if (this.anim) {
            const k = Math.min(1, (performance.now() - this.anim.t0) / this.anim.ms);
            const e = k < 0.5 ? 2 * k * k : 1 - (-2 * k + 2) ** 2 / 2;
            const target = this.controls.target;
            const dist = this.anim.to.distanceTo(target);
            // along the sphere round the target, not through the model
            this.camera.position.lerpVectors(this.anim.from, this.anim.to, e).sub(target).setLength(dist).add(target);
            if (k >= 1) this.anim = null;
        }
        for (const h of this.handles) h.group.scale.setScalar(this.camera.position.distanceTo(h.group.position) * 0.055);
        if (this.marker) this.marker.group.scale.setScalar(this.camera.position.distanceTo(this.marker.group.position) * 0.055);
        if (this.frame) {
            const k = this.camera.position.distanceTo(this.frame.group.position) * 0.055 / this.frame.group.scale.x;
            for (const g of this.frame.grips) g.scale.setScalar(k);
        }
    }

    /** Pointer events of the tool page: a handle is dragged before the orbit controls see the press; a plain click picks a piece. */
    private listen(): void {
        if (this.listening) return;
        this.listening = true;
        const ray = new Raycaster();
        const at = (e: PointerEvent): Vector2 => { const r = this.canvas.getBoundingClientRect(); return new Vector2(((e.clientX - r.left) / r.width) * 2 - 1, -((e.clientY - r.top) / r.height) * 2 + 1); };
        let down: { x: number; y: number } | null = null;
        let drag: { id: string; x: number; y: number; px: Vector2; mm: number } | null = null;
        let grip: { share: number } | null = null;           // the marker is being dragged along its line
        let stroke: { pts: [number, number][]; line: Line } | null = null;      // a stroke is being drawn
        const endStroke = (): [number, number][] | null => {
            if (!stroke) return null;
            const pts = stroke.pts;
            this.scene.remove(stroke.line); stroke.line.geometry.dispose();
            stroke = null; this.controls.enabled = true;
            return pts;
        };
        const label = (text: string | void, e: PointerEvent): void => {
            if (!this.handleLabel) {
                this.handleLabel = document.createElement('div');
                this.handleLabel.className = 'num pointer-events-none absolute z-10 hidden rounded-md bg-ink px-2 py-1 text-xs font-semibold text-white shadow-sm';
                this.canvas.parentElement?.appendChild(this.handleLabel);
            }
            const r = this.canvas.getBoundingClientRect();
            this.handleLabel.textContent = text || '';
            this.handleLabel.classList.toggle('hidden', !text);
            this.handleLabel.style.left = `${e.clientX - r.left + 14}px`; this.handleLabel.style.top = `${e.clientY - r.top - 10}px`;
        };
        this.canvas.addEventListener('pointerdown', (e) => {
            down = { x: e.clientX, y: e.clientY };
            if (this.draw && this.onDraw && e.button === 0) {
                ray.setFromCamera(at(e), this.camera);
                const p = this.fileAt(ray, this.draw.z);
                if (!p) return;
                e.stopImmediatePropagation(); e.preventDefault();
                const line = new Line(new BufferGeometry(), new LineBasicMaterial({ color: new Color(this.draw.color), depthTest: false, transparent: true }));
                line.renderOrder = 12; line.frustumCulled = false;
                this.scene.add(line);
                stroke = { pts: [[p.x, p.y]], line };
                this.controls.enabled = false;
                this.canvas.setPointerCapture(e.pointerId);
                return;
            }
            if (this.marker && this.onMarker && this.marker.group.visible) {
                ray.setFromCamera(at(e), this.camera);
                if (ray.intersectObjects(this.marker.group.children, false).length) {
                    e.stopImmediatePropagation(); e.preventDefault();
                    grip = { share: this.markerUnder(ray)?.[0] ?? 0 };
                    this.controls.enabled = false;
                    this.canvas.setPointerCapture(e.pointerId);
                    return;
                }
            }
            if (this.frame && this.onFrame && e.button === 0) {
                ray.setFromCamera(at(e), this.camera);
                const under = this.frameUnder(ray);
                if (under) {
                    e.stopImmediatePropagation(); e.preventDefault();
                    this.framing = { mode: under.mode, from: under.at, change: { dx: 0, dy: 0, scale: 1, turn: 0 }, x: e.clientX, y: e.clientY, moved: false };
                    this.controls.enabled = false;
                    this.canvas.setPointerCapture(e.pointerId);
                    return;
                }
            }
            if (!this.handles.length || !this.onHandle) return;
            ray.setFromCamera(at(e), this.camera);
            const hit = ray.intersectObjects(this.handles.filter((h) => h.group.visible).flatMap((h) => h.group.children), false)[0];
            const h = hit && this.handles.find((x) => x.id === hit.object.userData.handle);
            if (!h) return;
            e.stopImmediatePropagation(); e.preventDefault();
            // how many pixels one millimetre along the axis is on the screen, here and now
            const r = this.canvas.getBoundingClientRect();
            const px = (p: Vector3): Vector2 => { const q = p.clone().project(this.camera); return new Vector2((q.x + 1) / 2 * r.width, (1 - q.y) / 2 * r.height); };
            const a = px(h.group.position); const b = px(h.group.position.clone().addScaledVector(h.dir, 10));
            drag = { id: h.id, x: e.clientX, y: e.clientY, px: b.sub(a).divideScalar(10), mm: 0 };
            this.controls.enabled = false;
            this.canvas.setPointerCapture(e.pointerId);
            label(this.onHandle(h.id, 0, 'move'), e);
        }, { capture: true });
        this.canvas.addEventListener('pointermove', (e) => {
            if (stroke && this.draw && this.mesh) {
                ray.setFromCamera(at(e), this.camera);
                const p = this.fileAt(ray, this.draw.z);
                const last = stroke.pts[stroke.pts.length - 1];
                if (!p || Math.hypot(p.x - last[0], p.y - last[1]) < 0.4) return;
                stroke.pts.push([p.x, p.y]);
                const z = this.draw.z + 0.05;
                const world = stroke.pts.flatMap(([x, y]) => this.mesh!.localToWorld(new Vector3(x, y, z)).toArray());
                stroke.line.geometry.setAttribute('position', new Float32BufferAttribute(world, 3));
                return;
            }
            if (grip && this.onMarker) {
                ray.setFromCamera(at(e), this.camera);
                const hit = this.markerUnder(ray);
                if (!hit) return;
                grip.share = hit[0];
                this.moveMarker(hit[1], hit[2]);
                label(this.onMarker(hit[0], 'move'), e);
                return;
            }
            if (this.framing && this.frame && this.onFrame && this.mesh) {
                const f = this.framing; const [x0, y0, x1, y1] = this.frame.box; const cx = (x0 + x1) / 2; const cy = (y0 + y1) / 2;
                if (!f.moved && Math.hypot(e.clientX - f.x, e.clientY - f.y) <= 5) return;      // a click, not a drag yet
                f.moved = true;
                ray.setFromCamera(at(e), this.camera);
                const p = this.fileAt(ray, this.frame.z);
                if (!p) return;
                const group = this.frame.group;
                if (f.mode === 'move') {
                    f.change.dx = p.x - f.from.x; f.change.dy = p.y - f.from.y;
                    group.position.copy(this.mesh.localToWorld(new Vector3(cx + f.change.dx, cy + f.change.dy, this.frame.z + 0.05)));
                } else if (f.mode === 'size') {
                    f.change.scale = Math.max(0.05, Math.min(20, Math.hypot(p.x - cx, p.y - cy) / (Math.hypot(f.from.x - cx, f.from.y - cy) || 1)));
                    group.scale.setScalar(f.change.scale);
                } else {
                    const deg = (Math.atan2(p.y - cy, p.x - cx) - Math.atan2(f.from.y - cy, f.from.x - cx)) * 180 / Math.PI;
                    f.change.turn = Math.round(((deg + 540) % 360) - 180);
                    group.rotation.y = f.change.turn * Math.PI / 180;
                }
                label(this.onFrame({ ...f.change }, 'move'), e);
                return;
            }
            if (this.frame && this.onFrame && !drag && !grip && !stroke && e.buttons === 0) {
                ray.setFromCamera(at(e), this.camera);
                const mode = this.frameUnder(ray)?.mode;
                this.canvas.style.cursor = mode === 'move' ? 'move' : mode === 'size' ? 'nwse-resize' : mode === 'turn' ? 'grab' : '';
            }
            if (!drag || !this.onHandle) return;
            const len = drag.px.lengthSq() || 1;
            drag.mm = ((e.clientX - drag.x) * drag.px.x + (e.clientY - drag.y) * drag.px.y) / len;
            label(this.onHandle(drag.id, drag.mm, 'move'), e);
        });
        const up = (e: PointerEvent): void => {
            const drawn = endStroke();
            if (drawn) { down = null; this.onDraw?.(drawn); return; }
            if (grip) {
                this.onMarker?.(grip.share, 'end');
                grip = null; this.controls.enabled = true;
                this.handleLabel?.classList.add('hidden');
                down = null;
                return;
            }
            if (drag) {
                this.onHandle?.(drag.id, drag.mm, 'end');
                drag = null; this.controls.enabled = true;
                this.handleLabel?.classList.add('hidden');
                down = null;
                return;
            }
            if (this.framing) {
                const f = this.framing;
                this.framing = null; this.controls.enabled = true;
                this.handleLabel?.classList.add('hidden');
                if (f.moved) { down = null; this.onFrame?.({ ...f.change }, 'end'); return; }
                // a press without a drag is a click on the piece under the pointer, as anywhere else
            }
            if (!down || !this.onPick || !this.mesh || Math.hypot(e.clientX - down.x, e.clientY - down.y) > 5) { down = null; return; }
            down = null;
            ray.setFromCamera(at(e), this.camera);
            const hit = ray.intersectObject(this.mesh, false)[0];
            const tri = hit?.faceIndex ?? -1;
            const index = tri < 0 ? -1 : this.pieces.findIndex((p) => tri >= p.tris[0] && tri < p.tris[1]);
            this.onPick(index, index >= 0 ? this.pieces[index] : null);
        };
        this.canvas.addEventListener('pointerup', up);
        this.canvas.addEventListener('pointercancel', () => { endStroke(); if (grip) { grip = null; this.controls.enabled = true; this.handleLabel?.classList.add('hidden'); } if (drag) { drag = null; this.controls.enabled = true; this.handleLabel?.classList.add('hidden'); } if (this.framing) { this.framing = null; this.controls.enabled = true; this.handleLabel?.classList.add('hidden'); } down = null; });
    }
}

/** A separately printed piece of the shown model: its name and its triangles [from, to) in the order of the file. */
export interface Piece { name: string; tris: [number, number]; bbox?: number[] }
/** What a drag of the frame has changed since the press: a shift in millimetres, a ratio of sizes, a turn in degrees (anticlockwise). */
export interface FrameChange { dx: number; dy: number; scale: number; turn: number }

export type ViewName = 'iso' | 'top' | 'front' | 'side' | 'bottom';

/** '#rrggbb' → [r, g, b] 0..1 as the swatch shows it; null for anything else. */
export function hexRgb(hex: string): [number, number, number] | null {
    const m = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex);
    return m ? [parseInt(m[1], 16) / 255, parseInt(m[2], 16) / 255, parseInt(m[3], 16) / 255] : null;
}

/**
 * The farm's colour catalogue joins the built-in filament colours: everything that paints by a colour name
 * (regions of a preview, bins of a modular set) then knows the spools by their code.
 */
export function setPalette(items: { code: string; hex: string }[]): void {
    items.forEach((c) => { const rgb = hexRgb(c.hex); if (rgb) FILAMENT[c.code] = rgb; });
}

/** One number per triangle (in the order of the file) and the colour each number stands for, as [r, g, b] 0..1. */
export interface FacePaint { flags: Uint8Array; color: (flag: number) => [number, number, number] }

function paintByFaces(geom: BufferGeometry, paint: FacePaint): boolean {
    const pos = geom.getAttribute('position');
    if (!pos || geom.index || paint.flags.length * 3 !== pos.count) return false;
    const colors = new Float32Array(pos.count * 3);
    for (let t = 0; t < paint.flags.length; t++) {
        const c = paint.color(paint.flags[t]);
        for (let k = 0; k < 3; k++) { colors[(t * 3 + k) * 3] = c[0]; colors[(t * 3 + k) * 3 + 1] = c[1]; colors[(t * 3 + k) * 3 + 2] = c[2]; }
    }
    geom.setAttribute('color', new Float32BufferAttribute(colors, 3));
    return true;
}

/** exact: a real two-colour print — the colour as its swatch shows it, and a sharp change at z0 (see cutAtHeight) */
export interface Region { x0: number; y0: number; x1: number; y1: number; z0: number; color: string; exact?: boolean; part?: string }

/**
 * A stored design that is one body in two colours, changed at one height: a QR sign (the plate and the code), a sign
 * or beads whose two parts were given colours (the plate or the body, and the text raised on it). Everything above the
 * change in the second colour, the rest in the first. null for every other model.
 */
export function twoColorRegions(params: Record<string, unknown> | null | undefined): Region[] | null {
    const p = (params ?? {}) as { color_change_mm?: number; plate_color?: string; code_color?: string; plate_color_hex?: string; code_color_hex?: string; part_colors?: Record<string, { hex?: string } | undefined> };
    // the look stored with the design wins: a free colour is its own hex, and a page without the catalogue knows no spool by its code
    const below = p.plate_color_hex ?? p.plate_color ?? p.part_colors?.plate?.hex ?? p.part_colors?.body?.hex;
    const above = p.code_color_hex ?? p.code_color ?? p.part_colors?.text?.hex;
    if (!p.color_change_mm || !below || !above) return null;
    const everywhere = { x0: -1e6, y0: -1e6, x1: 1e6, y1: 1e6, exact: true };
    return [{ ...everywhere, z0: p.color_change_mm + 0.05, color: above }, { ...everywhere, z0: -1e6, color: below }];
}

/**
 * The same model with every triangle that crosses the height z cut along it (file coordinates, Z up), so each
 * triangle lies wholly below or wholly above: the foot of a stand comes out in the first colour, its body in the second.
 */
export function cutAtHeight(geom: BufferGeometry, z: number): BufferGeometry {
    const pos = geom.getAttribute('position');
    if (!pos || geom.index) return geom;
    const out: number[] = [];
    const v = (i: number): [number, number, number] => [pos.getX(i), pos.getY(i), pos.getZ(i)];
    const mix = (a: number[], b: number[]): number[] => { const t = (z - a[2]) / (b[2] - a[2]); return [a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t, z]; };
    for (let t = 0; t < pos.count; t += 3) {
        const tri = [v(t), v(t + 1), v(t + 2)];
        const up = tri.map((p) => p[2] >= z);
        if (up[0] === up[1] && up[1] === up[2]) { out.push(...tri[0], ...tri[1], ...tri[2]); continue; }
        // the corner that is alone on its side comes first; the order round the triangle stays, so it keeps facing the same way
        const lone = up[0] !== up[1] && up[0] !== up[2] ? 0 : up[1] !== up[0] && up[1] !== up[2] ? 1 : 2;
        const a = tri[lone]; const b = tri[(lone + 1) % 3]; const c = tri[(lone + 2) % 3];
        const p = mix(a, b); const q = mix(a, c);
        out.push(...a, ...p, ...q, ...p, ...b, ...c, ...p, ...c, ...q);
    }
    const cut = new BufferGeometry();
    cut.setAttribute('position', new Float32BufferAttribute(out, 3));
    cut.computeVertexNormals();
    return cut;
}

/** Filament colours as they look printed (slightly muted), keyed by the colour names used across the app. */
export const FILAMENT: Record<string, [number, number, number]> = {
    white: [0.93, 0.9, 0.84], black: [0.09, 0.09, 0.1], grey: [0.55, 0.57, 0.6], brown: [0.76, 0.6, 0.42], red: [0.72, 0.13, 0.12], blue: [0.13, 0.24, 0.47],
    green: [0.16, 0.45, 0.27], yellow: [0.92, 0.74, 0.16], orange: [0.82, 0.32, 0.12],
};

/** A swatch colour (sRGB) as the linear value the renderer needs to show that very colour. */
export function deep(c: [number, number, number]): [number, number, number] {
    const lin = (x: number): number => (x <= 0.04045 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4);
    return [lin(c[0]), lin(c[1]), lin(c[2])];
}

/** Multi-part sets: each part gets the colour of the filament it will be printed from; the rest (a tray) stays neutral. */
function paintByRegion(geom: BufferGeometry, regions: Region[]): boolean {
    const pos = geom.getAttribute('position');
    if (!pos) return false;
    const colors = new Float32Array(pos.count * 3);
    const base = FILAMENT.white;
    for (let t = 0; t < pos.count; t += 3) {
        // a triangle belongs to one body: decide by its centre so neighbouring bins never bleed into each other
        const cx = (pos.getX(t) + pos.getX(t + 1) + pos.getX(t + 2)) / 3; const cy = (pos.getY(t) + pos.getY(t + 1) + pos.getY(t + 2)) / 3;
        const cz = (pos.getZ(t) + pos.getZ(t + 1) + pos.getZ(t + 2)) / 3;
        const r = regions.find((g) => cx >= g.x0 - 0.01 && cx <= g.x1 + 0.01 && cy >= g.y0 - 0.01 && cy <= g.y1 + 0.01 && cz >= g.z0);
        // vertex colours are taken as linear light and come out paler than the swatch (the look of the organizer bins);
        // a two-colour print is shown as the swatches are, black really black: the code has to stand out the way it will when printed
        const tone = r ? FILAMENT[r.color] ?? hexRgb(r.color) ?? base : base;     // a colour by its name or spool, or the colour itself ("#2a7fd5")
        const c = r?.exact ? deep(tone) : tone;
        for (let k = 0; k < 3; k++) { colors[(t + k) * 3] = c[0]; colors[(t + k) * 3 + 1] = c[1]; colors[(t + k) * 3 + 2] = c[2]; }
    }
    geom.setAttribute('color', new Float32BufferAttribute(colors, 3));
    return true;
}

/**
 * Thin plates get vertex colours by height along their thinnest axis. Returns false for everything else.
 * Normal plates: low = dark teal, high = near white (embossed text pops, a relief looks like its photo).
 * Lithophane: thin = bright, thick = dark — what you see with a light behind it.
 */
function paintByHeight(geom: BufferGeometry, backlit: boolean, darkOnLight = false): boolean {
    const pos = geom.getAttribute('position');
    if (!pos) return false;
    geom.computeBoundingBox();
    const bb = geom.boundingBox!;
    const size = [bb.max.x - bb.min.x, bb.max.y - bb.min.y, bb.max.z - bb.min.z];
    const longest = Math.max(...size) || 1;
    const axis = size.indexOf(Math.min(...size));
    if (size[axis] / longest >= 0.2 || size[axis] <= 0) { geom.deleteAttribute('color'); return false; }
    const min = [bb.min.x, bb.min.y, bb.min.z][axis];
    // the relief side is where heights vary; a standing lithophane has its flat back at max, relief towards min
    const towardsMin = backlit && axis === 1;
    // colours are multiplied by strong studio lights: keep the plate deep so the near-white top layer stands out
    // a QR sign is printed as a light plate with dark modules (filament swap): scanners expect dark on light
    const lo = backlit ? [0.13, 0.1, 0.07] : darkOnLight ? [0.97, 0.97, 0.95] : [0.02, 0.2, 0.19];
    const hi = backlit ? [1.0, 0.93, 0.78] : darkOnLight ? [0.04, 0.05, 0.08] : [0.96, 1.0, 0.99];
    const colors = new Float32Array(pos.count * 3);
    for (let i = 0; i < pos.count; i++) {
        let h = ((axis === 0 ? pos.getX(i) : axis === 1 ? pos.getY(i) : pos.getZ(i)) - min) / size[axis];
        if (towardsMin) h = 1 - h;
        const k = backlit ? 1 - h : darkOnLight ? (h > 0.85 ? 1 : 0) : h * h * h;   // cubic: the plate stays dark, only the top layer lights up
        colors[i * 3] = lo[0] + (hi[0] - lo[0]) * k;
        colors[i * 3 + 1] = lo[1] + (hi[1] - lo[1]) * k;
        colors[i * 3 + 2] = lo[2] + (hi[2] - lo[2]) * k;
    }
    geom.setAttribute('color', new Float32BufferAttribute(colors, 3));
    return true;
}
