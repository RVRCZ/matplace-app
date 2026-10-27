/**
 * The calculator inside another site's iframe (resources/views/embed/calc.blade.php, docs/AN.md §12): a file goes up,
 * the server measures it and prices it, the frame shows the estimate's range at once and the exact price when the
 * slice is done, and one button opens the calculation on matplace.com (a new tab, with utm_source=embed). No 3D
 * viewer and no three.js: the frame stays small. The frame needs no cookie: everything is made for whoever holds the link.
 */
import { uploadFile, createCalculation, getCalculation, getFile, CalcInfo, FileInfo } from './calc/api';
import { price as priceText } from './site/money';

interface EmbedCfg { i18n: Record<string, string>; formats: string[]; maxMb: number; host: string; utm: Record<string, string>; material: string }

const cfg = (window as unknown as { MP_EMBED?: EmbedCfg }).MP_EMBED;
const $ = <T extends HTMLElement>(id: string) => document.getElementById(id) as T;
const t = (k: string, r: Record<string, string | number> = {}) => Object.entries(r).reduce((s, [a, b]) => s.replace(`:${a}`, String(b)), cfg?.i18n[k] ?? k);
const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));
const minutesText = (min: number): string => { const h = Math.floor(min / 60), m = min % 60; return h ? `${h} h ${m} min` : `${m} min`; };
/** The low end down and the high end up, the way the calculator says a time (calculator.ts roundMinutes). */
const roundMinutes = (m: number, up: boolean): number => { const step = m < 60 ? 5 : m < 180 ? 10 : 30; return (up ? Math.ceil(m / step) : Math.floor(m / step)) * step; };

let run = 0;   // a newer file makes the older one's answers history

function status(key: string | null, spinning = true): void {
    const box = $('embed-status');
    box.classList.toggle('hidden', key === null);
    box.classList.toggle('flex', key !== null);
    $('embed-spinner').classList.toggle('hidden', !spinning);
    if (key) $('embed-status-text').textContent = t(key);
}

function error(key: string | null, replace: Record<string, string | number> = {}): void {
    const p = $('embed-error');
    p.classList.toggle('hidden', key === null);
    if (key) p.textContent = t(key, replace);
}

function reset(): void {
    run++;
    status(null);
    error(null);
    $('embed-result').classList.add('hidden');
    $('embed-drop').classList.remove('hidden');
    ($('embed-file') as HTMLInputElement).value = '';
    $('embed').dataset.state = 'idle';
}

/** The link to the calculation on matplace.com, said to come from this frame. */
function openUrl(c: CalcInfo): string {
    const url = new URL(c.url, location.href);
    Object.entries(cfg?.utm ?? {}).forEach(([k, v]) => url.searchParams.set(k, v));
    return url.toString();
}

function showRough(c: CalcInfo, file: FileInfo): void {
    $('embed-result').classList.remove('hidden');
    $('embed-file-name').textContent = file.name;
    $('embed-kind').textContent = t('embed.estimate');
    const r = c.rough;
    if (r && r.price_min != null) {
        $('embed-price').textContent = r.price_min === r.price_max ? `≈ ${priceText(r.price_min)}` : `${priceText(r.price_min)} – ${priceText(r.price_max)}`;
        const lo = Math.max(1, roundMinutes(r.minutes_min ?? r.minutes, false)), hi = Math.max(lo, roundMinutes(r.minutes_max ?? r.minutes, true));
        $('embed-time').textContent = t('embed.time', { time: lo === hi ? `≈ ${minutesText(lo)}` : `${minutesText(lo)} – ${minutesText(hi)}` });
    } else {
        $('embed-price').textContent = '…';
        $('embed-time').textContent = '';
    }
    ($('embed-open') as HTMLAnchorElement).href = openUrl(c);
    $('embed').dataset.state = 'rough';
}

function showExact(c: CalcInfo): void {
    const total = c.prices?.length ? Math.min(...c.prices.map((p) => p.total)) : null;
    if (total === null || !c.slicer) return;
    $('embed-kind').textContent = t('embed.exact');
    $('embed-price').textContent = priceText(total);
    $('embed-time').textContent = t('embed.time', { time: minutesText(c.slicer.minutes) });
    ($('embed-open') as HTMLAnchorElement).href = openUrl(c);
    $('embed').dataset.state = 'exact';
}

async function handle(file: File): Promise<void> {
    if (!cfg) return;
    const mine = ++run;
    const ext = (file.name.split('.').pop() ?? '').toLowerCase();
    error(null);
    if (!cfg.formats.includes(ext)) { error('embed.not_model'); return; }
    if (file.size > cfg.maxMb * 1024 * 1024) { error('embed.too_big', { max: cfg.maxMb }); return; }
    $('embed-drop').classList.add('hidden');
    $('embed').dataset.state = 'uploading';
    try {
        status('embed.reading');
        let info = await uploadFile(file);
        if (mine !== run) return;
        // the server measures the file (a job behind the request): wait for it, a minute at most
        status('embed.measuring');
        for (let i = 0; i < 40 && info.status !== 'ready' && info.status !== 'failed'; i++) {
            await sleep(1500);
            if (mine !== run) return;
            info = await getFile(info.uuid);
        }
        if (info.status === 'failed') { reset(); error('embed.not_model'); return; }
        if (info.status !== 'ready') { reset(); error('embed.failed'); return; }
        // the calculation: a big model gets its estimate only (the exact price comes when ordering), the rest is sliced
        status(info.big_model ? 'embed.big_model' : 'embed.counting');
        let c = await createCalculation({ file: info.uuid, material: cfg.material, quality: 'standard', infill: 15, supports: 'auto', scale: 1, quantity: 1, ...(info.big_model ? { rough_only: 1 } : {}) });
        if (mine !== run) return;
        showRough(c, info);
        if (c.status === 'done') { showExact(c); status(null); return; }
        if (info.big_model || c.status === 'rough') { status(null); return; }
        for (let i = 0; i < 120 && c.status !== 'done' && c.status !== 'failed'; i++) {
            await sleep(2000);
            if (mine !== run) return;
            c = await getCalculation(c.token);
        }
        if (c.status === 'done') showExact(c);
        status(null);
    } catch {
        if (mine !== run) return;
        reset();
        error('embed.failed');
    }
}

function boot(): void {
    const root = document.getElementById('embed');
    if (!root || !cfg) return;
    const input = $('embed-file') as HTMLInputElement;
    input.addEventListener('change', () => { const f = input.files?.[0]; if (f) handle(f); });
    const drop = $('embed-drop');
    drop.addEventListener('dragover', (e) => { e.preventDefault(); drop.classList.add('border-action'); });
    drop.addEventListener('dragleave', () => drop.classList.remove('border-action'));
    drop.addEventListener('drop', (e) => { e.preventDefault(); drop.classList.remove('border-action'); const f = e.dataTransfer?.files?.[0]; if (f) handle(f); });
    $('embed-again').addEventListener('click', reset);
}

document.addEventListener('DOMContentLoaded', boot);
