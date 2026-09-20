import {
    AmbientLight, BufferGeometry, Color, DirectionalLight, GridHelper, HemisphereLight, Mesh, MeshStandardMaterial,
    PerspectiveCamera, Scene, Vector3, WebGLRenderer, Box3, Float32BufferAttribute,
} from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';

/** Small three.js viewer: one mesh, orbit controls, auto-fit, resize aware, touch friendly. */
export class Viewer {
    private renderer: WebGLRenderer;
    private scene = new Scene();
    private camera: PerspectiveCamera;
    private controls: OrbitControls;
    private mesh: Mesh | null = null;
    private grid: GridHelper | null = null;
    // light teal + flat shading: layer-like facets and embossed letters stay readable
    private material = new MeshStandardMaterial({ color: 0x5eead4, roughness: 0.75, metalness: 0.0, flatShading: true });
    // plates (signs, reliefs, lithophanes): colour follows the height, so letters and pictures read like a two-colour print
    private plateMaterial = new MeshStandardMaterial({ color: 0xffffff, roughness: 0.85, metalness: 0.0, flatShading: true, vertexColors: true });

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

    /** kind: what the model is ('lithophane' previews as if lit from behind: thin = bright) */
    setGeometry(geom: BufferGeometry, scale = 1, kind: string | null = null): void {
        if (this.mesh) {
            this.scene.remove(this.mesh);
            this.mesh.geometry.dispose();
        }
        if (this.grid) this.scene.remove(this.grid);
        if (!geom.getAttribute('normal')) geom.computeVertexNormals();
        // Z-up files (all print formats) → three.js Y-up
        const plate = paintByHeight(geom, kind === 'lithophane', kind === 'qr');
        this.mesh = new Mesh(geom, plate ? this.plateMaterial : this.material);
        this.mesh.rotation.x = -Math.PI / 2;
        this.mesh.scale.setScalar(scale);
        this.scene.add(this.mesh);
        this.fit();
    }

    setScale(scale: number): void {
        if (!this.mesh) return;
        this.mesh.scale.setScalar(scale);
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
        this.scene.add(this.grid);
        const dist = radius / Math.tan((this.camera.fov * Math.PI) / 360) * 1.1;
        // flat things (signs, plates, reliefs) are looked at from above, tall things from the side
        const flat = size.y / radius < 0.2;
        const standingPlate = !flat && size.z / radius < 0.2; // lithophane standing on its edge: look at its face
        if (flat) this.camera.position.set(dist * 0.1, dist * 0.62, dist * 0.36);   // plates fill the view
        else if (standingPlate) this.camera.position.set(dist * 0.25, size.y / 2 + dist * 0.12, dist * 0.95);
        else this.camera.position.set(dist * 0.8, dist * 0.6, dist * 0.9);
        this.camera.near = radius / 100;
        this.camera.far = radius * 100;
        this.camera.updateProjectionMatrix();
        this.controls.target.set(0, size.y / 2, 0);
        this.controls.update();
    }

    private resize(): void {
        const w = this.canvas.clientWidth || 300;
        const h = this.canvas.clientHeight || 300;
        this.renderer.setSize(w, h, false);
        this.camera.aspect = w / h;
        this.camera.updateProjectionMatrix();
    }

    private loop = (): void => {
        this.controls.update();
        this.renderer.render(this.scene, this.camera);
        requestAnimationFrame(this.loop);
    };
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
