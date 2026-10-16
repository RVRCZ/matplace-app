/**
 * The Matplace logo of dots, over the viewer while a model is being made (a pet figurine, a bust, a relief): the dots
 * fly in and whirl, settle into the logo, the orange thread flows from the nozzle into the letters, and they scatter
 * and come again, about seven seconds a round. Under it the phase the page is in and the percent the server tells.
 * When the model comes the dots fade out (400 ms) and the viewer shows it; when the work fails they scatter and go.
 *
 * One 2D canvas, a few thousand dots drawn from small pre-rendered sprites (one per colour of the logo), its own clock
 * that stands while the tab is hidden, a still logo for visitors who asked for reduced motion. The dots of the logo
 * are sampled once from the alpha channel of public/img/logo.png (particles_points.ts).
 */
import { partOf, samplePoints, seeded, type LogoPoint } from './particles_points';

export interface Particles {
    /** The line under the logo: what is happening, and how far along when the server says so. */
    say(text: string, percent?: number | null): void;
    /** The model is here: the dots fade out, the overlay goes. */
    finish(): Promise<void>;
    /** The work failed: the dots scatter and the overlay goes; the error stays where the page writes it. */
    fail(): Promise<void>;
}

const LOGO_URL = '/img/logo.png';
const MAX_DOTS = 3000;
// the dark outline of the logo is left as a gap: in dots it would run the letters together, white keeps them apart
const PARTS: LogoPoint['part'][] = ['fill', 'filament'];
const ROUND_MS = 7000;
const FADE_MS = 400;
const LEAVE_MS = 800;

let logoPixels: Promise<{ data: Uint8ClampedArray; width: number; height: number } | null> | null = null;

/** The pixels of the logo, read once per page: the picture is drawn small on a canvas no one sees. */
function pixels(): Promise<{ data: Uint8ClampedArray; width: number; height: number } | null> {
    logoPixels ??= new Promise((resolve) => {
        const img = new Image();
        img.decoding = 'async';
        img.onload = () => {
            const w = 600; const h = Math.round((img.naturalHeight / img.naturalWidth) * w) || 356;
            const c = document.createElement('canvas'); c.width = w; c.height = h;
            const ctx = c.getContext('2d', { willReadFrequently: true });
            if (!ctx) { resolve(null); return; }
            ctx.drawImage(img, 0, 0, w, h);
            resolve({ data: ctx.getImageData(0, 0, w, h).data, width: w, height: h });
        };
        img.onerror = () => resolve(null);
        img.src = LOGO_URL;
    });
    return logoPixels;
}

const ease = (t: number): number => (t <= 0 ? 0 : t >= 1 ? 1 : t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);

/** A soft round dot with a faint shadow, drawn once per colour: copying it is far cheaper than drawing a circle. */
function sprite(rgb: [number, number, number], radius: number, dpr: number): HTMLCanvasElement {
    const r = radius * dpr; const pad = Math.ceil(2 * dpr);
    const c = document.createElement('canvas');
    c.width = c.height = Math.ceil(2 * r + 2 * pad);
    const ctx = c.getContext('2d')!;
    ctx.shadowColor = 'rgba(15, 23, 42, 0.18)'; ctx.shadowBlur = 1.5 * dpr; ctx.shadowOffsetY = 0.6 * dpr;
    ctx.fillStyle = `rgb(${rgb[0]}, ${rgb[1]}, ${rgb[2]})`;
    ctx.beginPath(); ctx.arc(c.width / 2, c.height / 2, r, 0, Math.PI * 2); ctx.fill();
    return c;
}

/** Starts the dots over `host` (the box of the viewer). Nothing happens twice: a second call returns the running show. */
export function startParticles(host: HTMLElement): Particles {
    const running = (host as HTMLElement & { _particles?: Particles })._particles;
    if (running) return running;

    const overlay = document.createElement('div');
    overlay.className = 'pointer-events-none absolute inset-0 z-[5] flex flex-col bg-card transition-opacity duration-300';
    overlay.dataset.particles = 'on';
    const canvas = document.createElement('canvas');
    canvas.className = 'block min-h-0 w-full flex-1';
    canvas.setAttribute('aria-hidden', 'true');
    const caption = document.createElement('p');
    caption.className = 'px-4 pb-4 text-center text-sm text-muted';
    caption.setAttribute('aria-hidden', 'true');      // the page says the same in its own line, which is read out
    overlay.append(canvas, caption);
    host.appendChild(overlay);
    const empty = document.getElementById('tool-empty');
    const emptyWasHidden = empty?.classList.contains('hidden') ?? true;
    empty?.classList.add('hidden');

    const still = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
    const ctx = canvas.getContext('2d');
    let dpr = 1; let W = 0; let H = 0;
    let n = 0;
    let tx = new Float32Array(0); let ty = new Float32Array(0);
    let pts: LogoPoint[] = []; let aspect = 1.66;
    let logo: { data: Uint8ClampedArray; width: number; height: number } | null = null;
    let opaqueShare = 0.45;      // how much of the logo's box the logo covers
    // per dot: where it whirls (angle, radius, speed), when it settles, which sprite
    let a0 = new Float32Array(0); let r0 = new Float32Array(0); let spin = new Float32Array(0); let late = new Float32Array(0);
    let kind = new Uint8Array(0);
    let sprites: HTMLCanvasElement[] = [];
    let clock = 0; let last = 0; let frame = 0; let alpha = 1;
    let leaving: { at: number; fade: boolean } | null = null;
    let done: (() => void) | null = null;
    let frames = 0; let fpsAt = 0;

    /** Sizes, targets and sprites for the box as it is now (and again when it changes). */
    const layout = (): void => {
        const box = canvas.getBoundingClientRect();
        dpr = Math.min(2, window.devicePixelRatio || 1);
        W = Math.max(1, Math.round(box.width * dpr)); H = Math.max(1, Math.round(box.height * dpr));
        canvas.width = W; canvas.height = H;
        if (!logo) return;
        // more dots on a bigger box, 2 400 on a phone (fewer would not draw the letters) up to 3 000 on a wide screen;
        // the grid of dots is laid for that number
        const want = Math.max(2400, Math.min(MAX_DOTS, Math.round((box.width * box.height) / 150)));
        if (pts.length === 0 || Math.abs(pts.length - want) > 0.08 * want) pts = samplePoints(logo.data, logo.width, logo.height, want, 7, PARTS);
        n = pts.length;
        if (!n) return;
        // the logo over 60 % of a wide box; on a phone nearly its whole width, or its letters would be a few dots tall
        const lw = Math.min((box.width < 520 ? 0.88 : 0.6) * W, 0.62 * H * aspect); const lh = lw / aspect;
        const ox = (W - lw) / 2; const oy = (H - lh) / 2;
        tx = new Float32Array(n); ty = new Float32Array(n); kind = new Uint8Array(n);
        a0 = new Float32Array(n); r0 = new Float32Array(n); spin = new Float32Array(n); late = new Float32Array(n);
        const rnd = seeded(11);
        for (let i = 0; i < n; i++) {
            const p = pts[i];
            tx[i] = ox + p.x * lw; ty[i] = oy + p.y * lh;
            kind[i] = p.part === 'filament' ? 2 : p.part === 'line' ? 1 : 0;
            a0[i] = rnd() * Math.PI * 2; r0[i] = 0.2 + 0.8 * Math.sqrt(rnd()); spin[i] = (0.25 + 0.35 * rnd()) * (rnd() < 0.85 ? 1 : -1);
            late[i] = 0.5 * rnd() + 0.25 * (1 - p.y);      // the top settles a little later: the logo builds from below
        }
        // the dots carry the colours of the logo: its teal, its dark outline, its orange thread (and the thread lit up)
        const mean = (part: LogoPoint['part'], fallback: [number, number, number]): [number, number, number] => {
            const of = pts.slice(0, n).filter((p) => p.part === part);
            if (!of.length) return fallback;
            return [0, 1, 2].map((k) => Math.round(of.reduce((s, p) => s + p.rgb[k], 0) / of.length)) as [number, number, number];
        };
        // a dot a little smaller than the gap to its neighbours: the letters stay letters on a phone and on a wide screen
        const spacing = Math.sqrt((opaqueShare * lw * lh) / n);
        const dot = Math.max(0.8, Math.min(2.2, (0.37 * spacing) / dpr));
        const orange = mean('filament', [197, 90, 62]);
        sprites = [sprite(mean('fill', [46, 168, 152]), dot, dpr), sprite(mean('line', [0, 71, 58]), dot * 0.95, dpr), sprite(orange, dot * 1.1, dpr),
            sprite([Math.min(255, orange[0] + 45), Math.min(255, orange[1] + 70), Math.min(255, orange[2] + 40)], dot * 1.45, dpr)];
    };

    /** Where a dot whirls at this moment: round the middle, a little wobbly; at the very start it comes from outside. */
    const whirl = (i: number, t: number): [number, number] => {
        const s = t / 1000;
        const R = 0.5 * Math.min(W, H) * 0.95;
        const coming = Math.max(0, 1 - s / 1.6);
        const r = R * (r0[i] + 0.07 * Math.sin(1.3 * s + a0[i] * 5)) * (1 + 1.4 * coming * coming);
        const a = a0[i] + spin[i] * s + 0.45 * Math.sin(0.8 * s + a0[i] * 3);
        return [W / 2 + r * Math.cos(a), H / 2 + r * Math.sin(a) * 0.82];
    };

    const draw = (t: number): void => {
        if (!ctx || !n) return;
        ctx.clearRect(0, 0, W, H);
        ctx.globalAlpha = alpha;
        const round = still ? 4 : (t % ROUND_MS) / 1000;      // seconds into the round
        const flowing = round > 3.2 && round < 5.8;
        const wave = ((t / 1000) * 0.55) % 1;                   // where the light runs along the thread
        const breathe = still ? 0 : dpr;
        for (let i = 0; i < n; i++) {
            // settled: comes in from 1.6 s (later for some), stays until 5.6 s, leaves by 6.9 s
            let s = still ? 1 : round < 5.6 ? ease((round - 1.6 - late[i]) / 1.8) : 1 - ease((round - 5.6) / 1.3);
            if (leaving) s *= 1 - ease((t - leaving.at) / (leaving.fade ? FADE_MS : LEAVE_MS));
            let x: number; let y: number;
            if (s >= 0.999) {
                x = tx[i] + breathe * Math.sin(t * 0.0021 + a0[i] * 7); y = ty[i] + breathe * Math.cos(t * 0.0017 + a0[i] * 5);
            } else {
                const [wx, wy] = whirl(i, t);
                x = wx + (tx[i] - wx) * s; y = wy + (ty[i] - wy) * s;
            }
            let k = kind[i];
            if (k === 2 && flowing && s > 0.9 && Math.abs(pts[i].along - wave) < 0.07) k = 3;
            const sp = sprites[k];
            ctx.drawImage(sp, x - sp.width / 2, y - sp.height / 2);
        }
        ctx.globalAlpha = 1;
    };

    const tick = (now: number): void => {
        // our own clock: a hidden tab stops the frames, and the show goes on where it stood, not seconds later
        clock += Math.min(50, last ? now - last : 16);
        last = now;
        if (leaving) {
            const gone = (clock - leaving.at) / (leaving.fade ? FADE_MS : LEAVE_MS);
            alpha = leaving.fade ? Math.max(0, 1 - gone) : Math.max(0, 1 - Math.max(0, gone - 0.6) / 0.4);
            if (gone >= 1) { stop(); return; }
        }
        draw(clock);
        frames++;
        if (now - fpsAt >= 1000) { host.dataset.particleFps = String(Math.round((frames * 1000) / (now - fpsAt))); frames = 0; fpsAt = now; }
        frame = requestAnimationFrame(tick);
    };

    const onVisible = (): void => { last = 0; };
    document.addEventListener('visibilitychange', onVisible);
    const resized = new ResizeObserver(() => { layout(); if (still || !frame) draw(clock); });
    resized.observe(canvas);

    const stop = (): void => {
        cancelAnimationFrame(frame); frame = 0;
        resized.disconnect();
        document.removeEventListener('visibilitychange', onVisible);
        overlay.remove();
        if (!emptyWasHidden && leaving && !leaving.fade) empty?.classList.remove('hidden');      // a failure leaves the empty viewer as it was
        delete (host as HTMLElement & { _particles?: Particles })._particles;
        done?.();
    };
    const leave = (fade: boolean): Promise<void> => new Promise((resolve) => {
        if (!overlay.isConnected) { resolve(); return; }
        done = resolve;
        if (still || !frame) { leaving = { at: clock, fade }; stop(); return; }
        leaving ??= { at: clock, fade };
    });

    void pixels().then((found) => {
        logo = found;
        if (found) {
            aspect = found.width / found.height;
            let solid = 0;
            for (let i = 0; i < found.data.length; i += 4) if (found.data[i + 3] >= 128 && PARTS.includes(partOf(found.data[i], found.data[i + 1], found.data[i + 2]))) solid++;
            opaqueShare = solid / (found.width * found.height);
        }
        layout();
        if (!overlay.isConnected) return;
        if (still) { draw(clock); return; }
        fpsAt = performance.now();
        frame = requestAnimationFrame(tick);
    });

    const show: Particles = {
        say: (text, percent = null) => { caption.textContent = percent !== null && percent !== undefined && percent > 0 ? `${text} · ${Math.round(percent)} %` : text; },
        finish: () => leave(true),
        fail: () => leave(false),
    };
    (host as HTMLElement & { _particles?: Particles })._particles = show;
    return show;
}

/** The box of the viewer on a tool page, where the dots belong; null on a page without one. */
export function viewerBox(): HTMLElement | null {
    return document.getElementById('tool-viewer')?.parentElement ?? null;
}
