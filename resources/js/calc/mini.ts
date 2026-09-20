import { Viewer } from './viewer';
import { loadGeometryFromUrl } from './loaders';

/** Small read-only 3D previews: every <canvas data-stl="…"> (quote page, inquiry pages). */
export function bootMiniViewers(): void {
    document.querySelectorAll<HTMLCanvasElement>('canvas[data-stl]').forEach(async (canvas) => {
        try {
            const viewer = new Viewer(canvas);
            viewer.setGeometry(await loadGeometryFromUrl(canvas.dataset.stl as string));
        } catch {
            canvas.classList.add('hidden');
        }
    });
}
