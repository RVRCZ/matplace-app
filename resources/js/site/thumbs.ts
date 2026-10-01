/**
 * Pictures for model lists. A model without a stored picture carries <img data-thumb-stl="…">: when it scrolls
 * into view, one shared off-screen 3D viewer draws it, the picture goes into the <img> and is sent to the server
 * (data-thumb-store), so the next visit gets a plain image. One at a time: browsers allow only a few 3D contexts.
 */
import { Viewer } from '../calc/viewer';
import { loadGeometryFromUrl } from '../calc/loaders';

let viewer: Viewer | null = null;

function stage(): Viewer {
    if (viewer) return viewer;
    const canvas = document.createElement('canvas');
    canvas.setAttribute('aria-hidden', 'true');
    canvas.style.cssText = 'position:fixed;left:-9999px;top:0;width:480px;height:360px;pointer-events:none';
    document.body.appendChild(canvas);
    viewer = new Viewer(canvas);
    return viewer;
}

async function draw(img: HTMLImageElement): Promise<void> {
    const geometry = await loadGeometryFromUrl(img.dataset.thumbStl as string);
    const v = stage();
    v.setGeometry(geometry, 1, img.dataset.thumbKind ?? null);
    const picture = await v.snapshot();
    if (!picture) return;
    img.src = URL.createObjectURL(picture);
    const store = img.dataset.thumbStore;
    if (store) {
        const form = new FormData();
        form.append('image', picture, 'preview.webp');
        fetch(store, { method: 'POST', body: form, credentials: 'same-origin', headers: { Accept: 'application/json' } }).catch(() => { /* the picture is drawn again next time */ });
    }
}

export function bootThumbs(): void {
    const images = Array.from(document.querySelectorAll<HTMLImageElement>('img[data-thumb-stl]'));
    if (!images.length || !('IntersectionObserver' in window)) return;
    const queue: HTMLImageElement[] = [];
    let busy = false;
    const next = async (): Promise<void> => {
        if (busy) return;
        const img = queue.shift();
        if (!img) return;
        busy = true;
        try { await draw(img); } catch { /* a model the browser cannot read keeps the empty frame */ }
        img.removeAttribute('data-thumb-stl');
        busy = false;
        void next();
    };
    const seen = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (!entry.isIntersecting) continue;
            seen.unobserve(entry.target);
            queue.push(entry.target as HTMLImageElement);
            void next();
        }
    }, { rootMargin: '200px' });
    images.forEach((img) => seen.observe(img));
}
