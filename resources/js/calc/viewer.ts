import {
    AmbientLight, BufferGeometry, Color, DirectionalLight, GridHelper, Mesh, MeshStandardMaterial,
    PerspectiveCamera, Scene, Vector3, WebGLRenderer, Box3,
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
    private material = new MeshStandardMaterial({ color: 0x0f766e, roughness: 0.55, metalness: 0.05 });

    constructor(private canvas: HTMLCanvasElement) {
        this.renderer = new WebGLRenderer({ canvas, antialias: true, alpha: false });
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.scene.background = new Color(0xf8fafc);
        this.camera = new PerspectiveCamera(40, 1, 0.1, 100000);
        this.controls = new OrbitControls(this.camera, canvas);
        this.controls.enableDamping = true;
        this.scene.add(new AmbientLight(0xffffff, 0.7));
        const key = new DirectionalLight(0xffffff, 1.1);
        key.position.set(1, 2, 3);
        this.scene.add(key);
        const fill = new DirectionalLight(0xffffff, 0.4);
        fill.position.set(-2, -1, -1);
        this.scene.add(fill);
        new ResizeObserver(() => this.resize()).observe(canvas);
        this.resize();
        this.loop();
    }

    setGeometry(geom: BufferGeometry, scale = 1): void {
        if (this.mesh) {
            this.scene.remove(this.mesh);
            this.mesh.geometry.dispose();
        }
        if (this.grid) this.scene.remove(this.grid);
        if (!geom.getAttribute('normal')) geom.computeVertexNormals();
        // Z-up files (all print formats) → three.js Y-up
        this.mesh = new Mesh(geom, this.material);
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
        this.camera.position.set(dist * 0.8, dist * 0.6, dist * 0.9);
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
