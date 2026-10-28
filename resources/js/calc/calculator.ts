import { flash, revealIfHidden } from '../site/sticky';
import { icon } from '../site/icon';
import { BufferGeometry, Float32BufferAttribute } from 'three';
import { moldReport } from './mold';
import { Viewer, twoColorRegions, Piece } from './viewer';
import { paintGroups, colorDots } from './painted';
import { loadGeometry, loadGeometryFromUrl, extensionOf, BROWSER_FORMATS } from './loaders';
import ParseWorker from './parse.worker?worker';
import type { ParseReply } from './parse.worker';
import { stats, normaliseUnits, GeoStats } from './geometry';
import { estimate, price, profilesFor, range, rangeFactors, shapeOf, RangeFactors, RoughConfig, Profile, Params } from './rough';
import { uploadFile, createCalculation, getCalculation, getFile, CalcInfo, FileInfo } from './api';
import { bootInquiry } from './inquiry';
import { renderCheck } from './check';
import { price as priceText } from '../site/money';
import { renderAdvice } from './advice';
import { bootDownload, setDownload, refresh as refreshDownload, openWhenReady as openDownloadWhenReady } from './download';

const routes = () => (window as unknown as { MP_ROUTES: Record<string, string> }).MP_ROUTES;
/**
 * The start page in the language of this page (/, /en, /es): the address the calculator falls back to. The API takes
 * its language from the page that calls it (Locales::forApi, the Referer), so a plain "/" here would make the next
 * calculation answer with a Czech /c/<code> on an English page.
 */
const homeUrl = (): string => routes()?.home ?? '/';

interface MaterialProps { heat: number; strength: number; flexible: boolean; outdoor: string; food: string; time: number; price: number }
interface MaterialCfg { code: string; density: number; lay: string[]; sliceable: boolean; label: string; hint: string; props?: MaterialProps | null }
interface Config {
    rough: RoughConfig; orientation_profiles: Profile[]; round_to: number; currency: string; max_scale: number;
    bed_mm: { x: number; y: number; z: number }; bed_margin_mm?: number; materials: MaterialCfg[]; default_material: string;
    formats: string[]; max_upload_mb: number; lay: Record<string, string>;
    marketplace?: boolean;   // false: the calculator shows the slicer's facts and no prices (the farm has its own)
    shipping_hint?: string | null;   // what delivery adds, worded by the server for the visitor's country and currency
}

// MP_CONFIG exists only on calculator pages; this module is bundled into every page, so stay safe at import time.
const cfg = ((window as unknown as { MP_CONFIG?: Config }).MP_CONFIG ?? { default_material: 'PLA', materials: [], orientation_profiles: [] }) as Config;
const i18n = (window as unknown as { MP_I18N?: Record<string, string> }).MP_I18N ?? {};
const initial = (window as unknown as { MP_INITIAL?: CalcInfo | null }).MP_INITIAL ?? null;
const ownProfileId = (window as unknown as { MP_OWN_PROFILE_ID: number | null }).MP_OWN_PROFILE_ID ?? null;
const t = (k: string, r: Record<string, string | number> = {}) => Object.entries(r).reduce((s, [a, b]) => s.replace(`:${a}`, String(b)), i18n[k] ?? k);
const $ = <T extends HTMLElement>(id: string) => document.getElementById(id) as T;
const fmt = new Intl.NumberFormat({ en: 'en-GB', es: 'es-ES' }[document.documentElement.lang] ?? 'cs-CZ', { maximumFractionDigits: 0 });

/** State of the single screen. */
const state = {
    geometry: null as BufferGeometry | null,
    geo: null as GeoStats | null,        // browser-measured, mm
    file: null as FileInfo | null,
    calc: null as CalcInfo | null,
    params: { material: cfg.default_material, quality: 'standard', infill: 15, supports: null, scale: 1, quantity: 1 } as Params,
    pollTimer: 0 as number,
    debounce: 0 as number,
    localFile: null as File | null,
    stale: false,                        // settings changed since the last calculation: the visitor presses "Recalculate"
};

let viewer: Viewer | null = null;

function material(code: string): MaterialCfg {
    return cfg.materials.find((m) => m.code === code) ?? cfg.materials[0];
}

function minutesText(min: number): string {
    const h = Math.floor(min / 60), m = min % 60;
    return h ? `${h} h ${m} min` : `${m} min`;
}

function leadRange(days: number[]): string {
    const lo = Math.min(...days), hi = Math.max(...days);
    return lo === hi ? String(lo) : `${lo}–${hi}`;
}

/**
 * A text with the forms trans_choice uses on the server ("{1} :n den|[2,4] :n dny|[5,*] :n dní", lang/*.json): the form
 * for n, so "2–4 dny" and "5–7 dní" read right when n is the range's last number.
 */
function plural(key: string, n: number, r: Record<string, string | number>): string {
    const forms = (i18n[key] ?? key).split('|').map((f) => f.trim());
    const form = forms.find((f) => {
        const one = /^\{(\d+)\}/.exec(f); if (one) return Number(one[1]) === n;
        const range = /^\[(\d+),(\d+|\*)\]/.exec(f);
        return range ? n >= Number(range[1]) && (range[2] === '*' || n <= Number(range[2])) : false;
    }) ?? forms[forms.length - 1];
    return Object.entries(r).reduce((s, [a, b]) => s.replace(`:${a}`, String(b)), form.replace(/^(\{\d+\}|\[\d+,(?:\d+|\*)\])\s*/, ''));
}

/** A calculation that failed because the file is no model we can read says so: "Recalculate" would not help (docs/AH.md §9). */
const failedKey = (c: { error?: string | null; file?: { status?: string; error?: string | null } | null } | null): string => (c?.file?.error === 'too_many_triangles' ? 'calc.status.too_many_triangles' : (c?.error === 'file_processing_failed' || c?.file?.status === 'failed' ? 'calc.status.not_model' : 'calc.status.failed'));

function setStatus(key: string, spinning: boolean): void {
    $('status').textContent = t(key);
    $('status-spinner').classList.toggle('hidden', !spinning);
}

/** "Počítám přesně…" – and, for a model of millions of triangles, why it takes minutes (the server thins it first, docs/AL.md §10.11). */
function setComputing(file: FileInfo | null | undefined, key = 'calc.status.queued'): void {
    setStatus(key, true);
    if (file?.big_model && file.triangles) {
        const million = (file.triangles / 1e6).toFixed(1).replace('.', document.documentElement.lang === 'en' ? '.' : ',');
        $('status').textContent += ` ${t('calc.status.big_model').replace(':n', million)}`;
    }
}

/** "1,8" for a model of 1.8 million triangles, null under a million. */
function millions(file: FileInfo | null | undefined): string | null {
    if (!file?.triangles || file.triangles < 1e6) return null;
    return (file.triangles / 1e6).toFixed(1).replace('.', document.documentElement.lang === 'en' ? '.' : ',');
}

/**
 * A big model is not sliced on this page (TODO #41): the estimate with its range is the answer, the order page slices once
 * the customer has chosen. Said without a spinner: nothing is being waited for. The same for a design of several parts
 * larger than the plate (docs/AN.md §17.2): the farm prints it by parts, the server says so in the estimate.
 */
function setBigOnly(file: FileInfo | null | undefined, calc?: CalcInfo | null): void {
    if (calc) renderColors(calc.file ?? file ?? null, calc);   // an estimate only: the two prices come with it (rough)
    if ((calc as { rough?: { by_parts?: boolean } } | null | undefined)?.rough?.by_parts) { setStatus('calc.status.by_parts', false); return; }
    const n = millions(file);
    setStatus(n ? 'calc.status.big_only_n' : 'calc.status.big_only', false);
    if (n) $('status').textContent = t('calc.status.big_only_n', { n });
}

/** The model as stored (scale 1): measured in the browser, or what the server measured. */
function nativeBbox(): { x: number; y: number; z: number } | null {
    return state.geo?.bbox ?? state.file?.bbox ?? null;
}

const SCALE_MIN = 0.25;

/**
 * Size block: the three dimensions in millimetres as they will print, the slider and the percentage behind them.
 * A field the visitor is typing into is left alone.
 */
function renderSize(): void {
    const b = nativeBbox();
    const s = state.params.scale;
    const mm = (v: number) => (v < 10 ? String(Math.round(v * 10) / 10) : String(Math.round(v)));
    (['x', 'y', 'z'] as const).forEach((axis) => {
        const el = document.getElementById(`size-${axis}`) as HTMLInputElement | null;
        if (el && document.activeElement !== el) el.value = b ? mm(b[axis] * s) : '';
    });
    const slider = document.getElementById('scale') as HTMLInputElement | null;
    if (slider && document.activeElement !== slider) slider.value = String(Math.round(s * 100));
    const val = document.getElementById('scale-val');
    if (val) val.textContent = `${Math.round(s * 100)} %`;
    document.getElementById('size-generated')?.classList.toggle('hidden', state.file?.kind !== 'generated');
    document.getElementById('size-reset')?.classList.toggle('hidden', Math.abs(s - 1) < 0.0005);   // back to the file's own size, once it was changed
    const fit = document.getElementById('bed-fit');
    if (fit) {
        // the server's count by the model's shadow once it has answered for this file and size, the rectangle before
        const key = plateKey();
        if (key && key !== plate.asked) askPlate(key);
        const n = b ? (key && plate.answers.has(key) ? plate.answers.get(key)! : piecesOnBed({ x: b.x * s, y: b.y * s, z: b.z * s })) : null;
        const bed = cfg.bed_mm ? `${fmt.format(cfg.bed_mm.x)} × ${fmt.format(cfg.bed_mm.y)} mm` : '';
        const q = state.params.quantity;
        // more pieces than one plate takes: the farm prints them on several plates one after another
        const plates = n && q > n ? ` ${t('calc.fit.plates', { q, p: Math.ceil(q / n) })}` : '';
        fit.textContent = n === null ? '' : n > 0 ? t('calc.fit.bed', { n, b: bed }) + plates : t('calc.fit.none', { b: bed });
        fit.classList.toggle('text-amber-700', n === 0);
    }
}

/**
 * How many copies fit on the smaller farm printer's plate at once by the rectangle: 5 mm apart, either way round, and
 * inside the edge the farm keeps clear (the grid of App\Domain\Farm\PlateLayout, so never more than the farm lays).
 * 0 when one does not fit. Shown until the server's count by the shadow (askPlate) is in.
 */
function piecesOnBed(d: { x: number; y: number; z: number }): number | null {
    const bed = cfg.bed_mm;
    if (!bed || !(d.x > 0 && d.y > 0)) return null;
    if (d.z > bed.z) return 0;
    const gap = 5;
    const margin = 2 * (cfg.bed_margin_mm ?? 0);
    const along = (size: number, room: number) => Math.floor((room - margin + gap) / (size + gap));
    return Math.max(along(d.x, bed.x) * along(d.y, bed.y), along(d.y, bed.x) * along(d.x, bed.y));
}

/**
 * How many pieces fit by the model's shadow (POST /api/calc/plate, App\Domain\Farm\PlateLayout: the farm lays the
 * plate with the same code). Never fewer than piecesOnBed; asked once per file and size, a slider move waits 300 ms.
 */
const plate = { answers: new Map<string, number>(), asked: '', timer: 0 };

function plateKey(): string | null {
    const f = state.file;
    return f && f.status === 'ready' && f.bbox ? `${f.uuid}|${Math.round(state.params.scale * 1000)}` : null;
}

function askPlate(key: string): void {
    plate.asked = key;
    window.clearTimeout(plate.timer);
    plate.timer = window.setTimeout(async () => {
        const [file, scale] = key.split('|');
        const url = routes().plate;
        if (!url) return;
        try {
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ file, scale: Number(scale) / 1000 }),
            });
            if (!res.ok) return;
            const body = (await res.json()) as { max?: number };
            if (typeof body.max === 'number') {
                plate.answers.set(key, body.max);
                if (plateKey() === key) renderSize();
            }
        } catch { /* the rectangle's count stays */ }
    }, 300);
}

/** One dimension typed in millimetres → the whole model scales to it (within the allowed range). */
function sizeTyped(axis: 'x' | 'y' | 'z', wanted: number): void {
    const b = nativeBbox();
    if (!b || !(b[axis] > 0) || !(wanted > 0)) { renderSize(); return; }
    const raw = wanted / b[axis];
    const max = cfg.max_scale || 4;
    const clamped = Math.min(max, Math.max(SCALE_MIN, raw));
    document.getElementById('size-limit')?.classList.toggle('hidden', clamped === raw);
    state.params.scale = Math.round(clamped * 1000) / 1000;
    onParamsChanged();
}

/** The estimate for the model as it is set, and how far the slice may land from it by the model's shape (docs/M.md §6). */
function roughNow(): { est: { grams: number; minutes: number }; factors: RangeFactors; bbox: { x: number; y: number; z: number } } | null {
    const g = state.geo ?? (state.file && state.file.volume_mm3 != null ? { volume_mm3: state.file.volume_mm3, area_mm2: state.file.area_mm2, bbox: state.file.bbox! } : null);
    if (!g) return null;
    const est = estimate(cfg.rough, material(state.params.material).density, { volume_mm3: g.volume_mm3, area_mm2: g.area_mm2 }, state.params);
    // the browser measured the overhangs when it read the file itself; a file only the server could read has no such measure
    const shape = shapeOf(g.bbox, state.params.scale, est.grams, state.geo?.overhang_share ?? null, state.geo?.triangles || state.file?.triangles || null);
    return { est, factors: rangeFactors(cfg.rough.range, shape, state.params.supports, est.minutes), bbox: g.bbox };
}

/** Rough numbers from browser geometry — under a second, on every slider move. */
function renderRough(): void {
    const r = roughNow();
    if (!r) return;
    const { est, factors, bbox: g } = r;
    // the farm's list of this very material (one per material, the kind it would print it with); every list on the marketplace
    const bds = profilesFor(cfg.orientation_profiles, state.params.material).map((p) => price(cfg.round_to, p, est.grams, est.minutes, state.params.quantity));
    const q = state.params.quantity;
    const s = state.params.scale;
    $('dims-badge').textContent = `${fmt.format(g.x * s)} × ${fmt.format(g.y * s)} × ${fmt.format(g.z * s)} mm`;
    renderSize();
    $('stat-grams').textContent = `≈ ${fmt.format(est.grams * q)} g`;
    // the time as a range: the estimate counts minutes from grams, the shape says how far the slicer may land from them
    const minutes = minutesRangeText(est.minutes * q, factors.time);
    $('stat-time').textContent = minutes;
    renderCompare({ grams: est.grams, minutes: est.minutes }, false);
    if (!marketplace()) {
        // the design page is about the print; the farm's list gives a price to orient by, the binding one comes with the order
        renderFacts({ minutes: est.minutes * q, grams: est.grams * q, rough: true, range: minutes });
        const [olo, ohi] = range(cfg.round_to, bds.map((b) => b.total), factors.price);
        orientPrice(farmPriced() ? [olo, ohi] : null, true);
        return;
    }
    const [lo, hi] = range(cfg.round_to, bds.map((b) => b.total), factors.price);
    $('price-main').textContent = lo === hi ? fmt.format(lo) : `${fmt.format(lo)} – ${fmt.format(hi)}`;
    $('price-sub').textContent = [q > 1 ? `${t('calc.price.per_piece')} ≈ ${fmt.format(Math.round(lo / q))} – ${fmt.format(Math.round(hi / q))}` : '', t('calc.price.shape_hint')].filter(Boolean).join(' · ');
    const leads = bds.map((b) => b.lead_time_days);
    $('stat-lead').textContent = plural('calc.days', Math.max(...leads), { n: leadRange(leads) });
    renderBreakdown(bds.map((b) => ({ ...b, label: t(`calc.profile.${b.profile}`) })), true);
    if (!material(state.params.material).sliceable) $('price-sub').textContent = t('calc.est_only');
}

/** "asi 1 h 40 min až 3 h": the estimate's minutes with how far the slice may land, rounded the way one says a time. */
function minutesRangeText(minutes: number, factors: [number, number]): string {
    const lo = Math.max(1, roundMinutes(minutes * factors[0], false));
    const hi = Math.max(lo, roundMinutes(minutes * factors[1], true));
    return lo === hi ? `≈ ${minutesText(lo)}` : t('calc.time.range', { lo: minutesText(lo), hi: minutesText(hi) });
}

/** To 5 minutes under an hour, 10 minutes under three hours, half an hour above; down for the low end, up for the high end. */
function roundMinutes(m: number, up: boolean): number {
    const step = m < 60 ? 5 : m < 180 ? 10 : 30;
    return (up ? Math.ceil(m / step) : Math.floor(m / step)) * step;
}

const marketplace = () => cfg.marketplace !== false;

/**
 * Material comparison for THIS model: weight by density, time and price converted from the numbers of the chosen
 * material (the precise ones once they exist), beside what each material stands up to. A row picks the material.
 */
function renderCompare(base: { grams: number; minutes: number } | null, precise: boolean): void {
    const head = document.getElementById('mat-compare-head');
    const body = document.getElementById('mat-compare-body');
    if (!head || !body) return;
    const rows = cfg.materials.filter((m) => m.props);
    const cur = material(state.params.material);
    if (!base || !rows.length || !cur.props) { body.innerHTML = ''; return; }
    const q = state.params.quantity;
    const priced = cfg.orientation_profiles.length > 0 && (marketplace() || farmPriced());
    const th = (k: string) => `<th class="py-1 pr-2 font-normal">${t(k)}</th>`;
    head.innerHTML = `<tr>${th('compare.col.material')}${priced ? th('compare.col.price') : ''}${th('compare.col.time')}${th('compare.col.weight')}${th('compare.col.heat')}${th('compare.col.strength')}${th('compare.col.outdoor')}${th('compare.col.food')}</tr>`;
    const approx = precise ? '' : '≈ ';
    body.innerHTML = rows.map((m) => {
        const p = m.props!;
        const same = m.code === cur.code;
        const grams = base.grams * (m.density / cur.density);
        const minutes = Math.max(1, Math.round(base.minutes * (p.time / cur.props!.time)));
        let cost = '';
        if (priced) {
            // the farm names a list per material (its price per gram in the kind it would print that material with); the marketplace's
            // lists are scaled by the material's price factor against the chosen one
            const own = cfg.orientation_profiles.some((prof) => prof.material);
            const profs = own ? profilesFor(cfg.orientation_profiles, m.code) : cfg.orientation_profiles.map((prof) => ({ ...prof, price_per_gram: prof.price_per_gram * (p.price / cur.props!.price) }));
            const totals = profs.map((prof) => price(cfg.round_to, prof, grams, minutes, q).total);
            cost = `<td class="py-1 pr-2 font-semibold">${totals.length ? `${same && precise ? '' : '≈ '}${priceText(Math.min(...totals))}` : '—'}</td>`;
        }
        const dots = '●'.repeat(p.strength) + '○'.repeat(Math.max(0, 3 - p.strength));
        return `<tr data-material="${m.code}" class="cursor-pointer border-t border-slate-100 ${same ? 'bg-action-soft' : 'hover:bg-slate-50'}" title="${same ? t('compare.chosen') : t('compare.pick')}">`
            + `<td class="py-1 pr-2"><span class="font-semibold text-ink">${m.label}</span> <span class="text-slate-500">${m.code}</span></td>${cost}`
            + `<td class="py-1 pr-2">${same ? approx : '≈ '}${minutesText(minutes * q)}</td><td class="py-1 pr-2">${same ? approx : '≈ '}${fmt.format(grams * q)} g</td>`
            + `<td class="py-1 pr-2">${p.heat} °C</td><td class="py-1 pr-2" aria-label="${p.strength}/3">${dots}${p.flexible ? ` ${t('compare.flexible')}` : ''}</td>`
            + `<td class="py-1 pr-2">${t(`compare.outdoor.${p.outdoor}`)}</td><td class="py-1">${t(`compare.food.${p.food}`)}</td></tr>`;
    }).join('');
    body.querySelectorAll<HTMLElement>('tr[data-material]').forEach((tr) => {
        tr.onclick = () => {
            if (tr.dataset.material === state.params.material) return;
            state.params.material = tr.dataset.material!;
            buildMaterials();
            onParamsChanged();
        };
    });
}

/** The farm's list price as a line to orient by; the binding price comes on the order page after colour and printer are chosen. */
function orientPrice(r: [number, number] | null, rough: boolean): void {
    const el = document.getElementById('price-orient');
    const ship = document.getElementById('price-shipping');
    if (!el) return;
    if (!r) { el.classList.add('hidden'); ship?.classList.add('hidden'); return; }
    const q = state.params.quantity;
    // the farm's list is in crowns; the visitor reads it in their own currency
    const txt = r[0] === r[1] ? priceText(r[0]) : `${priceText(r[0])} – ${priceText(r[1])}`;
    // the estimate's range says what narrows it down (supports, shape, colour, printer); the exact price just where it is made binding
    // the amount in a span of its own: the bar of a phone mirrors it (components/action-bar)
    const [before, after] = t(rough ? 'calc.price.orient_range' : 'calc.price.orient', { price: '\u0000' }).split('\u0000');
    const span = document.createElement('span'); span.id = 'price-orient-amount'; span.textContent = `${rough ? '≈ ' : ''}${txt}`;
    el.replaceChildren(before ?? '', span, (after ?? '') + (q > 1 ? ` ${t('calc.price.per_piece')} ≈ ${priceText(Math.round(r[0] / q))}.` : ''));
    el.classList.remove('hidden');
    // the print alone is priced above; what delivery adds comes worded by the server for this visitor (ShippingHint)
    if (ship) { ship.textContent = cfg.shipping_hint ?? ''; ship.classList.toggle('hidden', !cfg.shipping_hint); }
}
// the farm's own price list (one per material): the calculator prices like the marketplace does, with that list
const farmPriced = () => !marketplace() && cfg.orientation_profiles.length > 0 && cfg.orientation_profiles.every((p) => p.key === 'farm');

/**
 * Without the marketplace the result card is about the print, not about money: the headline is the print time,
 * with the machine's speed modes when the slicer knows them, then material, layers and supports.
 */
function renderFacts(f: { minutes: number; grams: number; meters?: number | null; modes?: Record<string, number>; layers?: number | null; supports?: boolean; rough: boolean; range?: string }): void {
    const approx = f.rough ? '≈ ' : '';
    // the estimate's time as a range ("asi 1 h 40 min až 3 h") is long for the headline: a size smaller while it shows
    $('price-main').textContent = f.range ?? approx + minutesText(f.minutes);
    $('price-main').classList.toggle('text-3xl', !!f.range);
    $('price-main').classList.toggle('text-4xl', !f.range);
    const modes = f.modes && Object.keys(f.modes).length > 1 ? f.modes : null;
    $('price-sub').innerHTML = modes
        ? Object.entries(modes).map(([k, v]) => `<span class="mr-3">${t(`calc.mode.${k}`)}: <strong>${minutesText(v * state.params.quantity)}</strong></span>`).join('')
        : (f.rough ? t('calc.facts.rough') : '');
    $('stat-lead').textContent = f.layers ? String(f.layers) : '—';
    $('stat-time').textContent = f.meters ? `${f.meters.toFixed(1)} m` : (f.rough ? '—' : $('stat-time').textContent);
    const rows: string[] = [];
    rows.push(`<span>${t('calc.facts.material')}: <strong>${approx}${fmt.format(f.grams)} g${f.meters ? ` · ${f.meters.toFixed(1)} m` : ''}</strong></span>`);
    if (f.layers) rows.push(`<span>${t('calc.facts.layers')}: <strong>${f.layers}</strong> · ${cfg.rough.quality_layer_mm[state.params.quality]} mm</span>`);
    if (f.supports !== undefined) rows.push(`<span>${t('calc.facts.supports')}: <strong>${t(f.supports ? 'calc.facts.supports_yes' : 'calc.facts.supports_no')}</strong></span>`);
    rows.push(`<span>${t('calc.facts.infill')}: <strong>${state.params.infill} %</strong></span>`);
    $('breakdown').innerHTML = `<div class="grid gap-1 rounded-lg bg-slate-50 p-2 text-sm text-slate-600">${rows.join('')}</div>`;
}

function renderBreakdown(bds: { profile: string; label?: string | null; printer_profile_id?: number | null; unit: { material: number; time: number }; setup: number; subtotal?: number; discount?: number; margin?: number; total: number; lead_time_days: number }[], rough: boolean): void {
    // everything between the three lines and the total is said out loud: discount, margin, minimum order price, rounding
    const extras = (b: typeof bds[number]): string => {
        if (b.subtotal === undefined) return '';
        const sum = b.subtotal - (b.discount ?? 0) + (b.margin ?? 0);
        const out: string[] = [];
        if ((b.discount ?? 0) > 0.005) out.push(`${t('calc.breakdown.discount')}: −${fmt.format(Math.round(b.discount!))}`);
        if ((b.margin ?? 0) > 0.005) out.push(`${t('calc.breakdown.margin')}: ${fmt.format(Math.round(b.margin!))}`);
        if (b.total - sum > cfg.round_to) out.push(`<strong class="text-slate-700">${t('calc.breakdown.min_price')}: ${fmt.format(b.total)}</strong>`);
        else if (b.total - sum > 0.5) out.push(`${t('calc.breakdown.rounded')}: ${fmt.format(Math.round(sum))} → ${fmt.format(b.total)}`);
        return out.length ? `<div class="mt-1 flex flex-wrap gap-x-3 text-xs text-slate-500">${out.map((o) => `<span>${o}</span>`).join('')}</div>` : '';
    };
    $('breakdown').innerHTML = bds.map((b) => `
        <div class="rounded-lg bg-slate-50 p-2">
            <div class="flex justify-between font-semibold"><span>${b.printer_profile_id ? `<a class="underline decoration-slate-300 hover:text-action-dark" target="_blank" href="${routes().printerShow}/${b.printer_profile_id}">${b.label ?? ''}</a>` : (b.label ?? t(`calc.profile.${b.profile}`))}</span><span>${rough ? '≈ ' : ''}${fmt.format(b.total)}</span></div>
            <div class="grid grid-cols-3 gap-1 text-xs text-slate-500">
                <span>${t('calc.breakdown.material')}: ${fmt.format(b.unit.material * state.params.quantity)}</span>
                <span>${t('calc.breakdown.time')}: ${fmt.format(b.unit.time * state.params.quantity)}</span>
                <span>${t('calc.breakdown.setup')}: ${fmt.format(b.setup)}</span>
            </div>${extras(b)}
        </div>`).join('');
}

/** Precise numbers from the server slice. */
/** The price card gets a ring when the numbers it shows were asked for by a click elsewhere (Recalculate): nothing moves. */
let flashNext = false;

function renderPrecise(c: CalcInfo): void {
    renderColors(c.file ?? state.file, c);   // every way a done calculation arrives (created done, polled, restored)
    if (!c.slicer || !c.prices) return;
    if (flashNext) { flashNext = false; flash(document.getElementById('price-card')); }
    renderCompare({ grams: c.slicer.grams, minutes: c.slicer.minutes }, true);
    const q = state.params.quantity;
    if (!marketplace()) {
        orientPrice(farmPriced() && c.prices.length ? [c.prices[0].total, c.prices[0].total] : null, false);
        $('stat-grams').textContent = `${fmt.format(c.slicer.grams * q)} g`;
        $('stat-time').textContent = minutesText(c.slicer.minutes * q);
        $('dims-badge').textContent = `${fmt.format(c.slicer.dims.x)} × ${fmt.format(c.slicer.dims.y)} × ${fmt.format(c.slicer.dims.z)} mm`;
        renderFacts({ minutes: c.slicer.minutes * q, grams: c.slicer.grams * q, meters: (c.slicer.meters ?? 0) * q || null, modes: c.slicer.minutes_by_mode, layers: c.slicer.layers, supports: c.slicer.supports_used, rough: false });
        enableQuote(c);
        renderCheck(document.getElementById('model-check'), c.file?.check);
        renderAdvice(document.getElementById('model-advice'), state.file?.uuid, c.token);
        $('warnings').innerHTML = [...new Set(c.slicer.warnings ?? [])].filter((w) => i18n[`calc.warn.${w}`]).map((w) => `<li class="flex items-start gap-1.5">${icon('triangle-alert', 'mt-0.5 h-4 w-4')}<span>${t(`calc.warn.${w}`)}</span></li>`).join('');
        return;
    }
    const own = ownProfileId ? c.prices.find((p) => (p as { printer_profile_id?: number | null }).printer_profile_id === ownProfileId) : undefined;
    const others = own ? c.prices.filter((p) => p !== own) : c.prices;
    if (own) {
        $('price-main').textContent = fmt.format(own.total);
        const parts = [q > 1 ? `${t('calc.price.per_piece')} ${fmt.format(Math.round(own.total / q))}` : ''];
        if (others.length) parts.push(t('calc.others_from', { n: fmt.format(Math.min(...others.map((p) => p.total))), c: others.length }));
        $('price-sub').textContent = parts.filter(Boolean).join(' · ');
    } else {
        const totals = c.prices.map((p) => p.total);
        const [lo, hi] = range(cfg.round_to, totals, null);
        $('price-main').textContent = lo === hi ? fmt.format(lo) : `${fmt.format(lo)} – ${fmt.format(hi)}`;
        const real = c.prices.filter((p) => (p as { printer_profile_id?: number | null }).printer_profile_id);
        const sub = [q > 1 ? `${t('calc.price.per_piece')} ${fmt.format(Math.round(lo / q))} – ${fmt.format(Math.round(hi / q))}` : ''];
        if (real.length) sub.push(t('calc.printers_count', { c: real.length }));
        $('price-sub').textContent = sub.filter(Boolean).join(' · ');
    }
    enableQuote(c);
    $('stat-grams').textContent = `${fmt.format(c.slicer.grams * q)} g`;
    const mins = c.prices.map((p) => (p as { minutes?: number | null }).minutes ?? c.slicer!.minutes);
    const tLo = Math.min(c.slicer.minutes, ...mins) * q; const tHi = Math.max(c.slicer.minutes, ...mins) * q;
    $('stat-time').textContent = tHi > tLo * 1.05 ? `${minutesText(tLo)} – ${minutesText(tHi)}` : minutesText(tLo);
    const leads = c.prices.map((p) => p.lead_time_days);
    $('stat-lead').textContent = plural('calc.days', Math.max(...leads), { n: leadRange(leads) });
    $('dims-badge').textContent = `${fmt.format(c.slicer.dims.x)} × ${fmt.format(c.slicer.dims.y)} × ${fmt.format(c.slicer.dims.z)} mm`;
    renderBreakdown(c.prices.map((p) => ({ ...p, label: ownProfileId && (p as { printer_profile_id?: number | null }).printer_profile_id === ownProfileId ? t('calc.profile.mine') : (p.label ?? t(`calc.profile.${p.profile}`)) })), false);
    renderCheck(document.getElementById('model-check'), c.file?.check);
    renderAdvice(document.getElementById('model-advice'), state.file?.uuid, c.token);
    const warns = [...(c.slicer.warnings ?? [])];
    $('warnings').innerHTML = [...new Set(warns)].filter((w) => i18n[`calc.warn.${w}`]).map((w) => `<li class="flex items-start gap-1.5">${icon('triangle-alert', 'mt-0.5 h-4 w-4')}<span>${t(`calc.warn.${w}`)}</span></li>`).join('');
}

function stopPolling(): void {
    if (state.pollTimer) window.clearTimeout(state.pollTimer);
    state.pollTimer = 0;
}

function poll(token: string): void {
    stopPolling();
    const tick = async () => {
        try {
            const c = await getCalculation(token);
            if (state.calc?.token !== token) return; // superseded by a newer calculation
            state.calc = c;
            takeFile(c.file);
            if (c.status === 'done') {
                setStatus(marketplace() ? 'calc.status.done' : 'calc.status.done_facts', false);
                renderPrecise(c);
                enableDownload(c.file);
                if (!state.geometry && c.file?.stl_url) await showServerStl(c.file.stl_url);
                return;
            }
            if (c.status === 'failed') {
                setStatus(failedKey(c), false);
                return;
            }
            if (c.status === 'rough') {   // a big model's calculation is never queued: the estimate is the answer (TODO #41)
                renderRough(); setBigOnly(c.file, c); enableDownload(c.file);
                return;
            }
            setComputing(c.file, c.file && c.file.status !== 'ready' && !BROWSER_FORMATS.includes(c.file.ext) ? 'calc.status.converting' : 'calc.status.queued');
            state.pollTimer = window.setTimeout(tick, 1500);
        } catch {
            state.pollTimer = window.setTimeout(tick, 3000);
        }
    };
    tick();
}

async function showServerStl(url: string): Promise<boolean> {
    try {
        const geom = await loadGeometryFromUrl(url);
        state.geometry = geom;
        state.geo = stats(geom);
        const parts = partsByColour(geom, state.file?.tool?.params);
        viewer?.setGeometry(geom, state.params.scale, state.file?.kind ?? null, parts ? null : twoColorRegions(state.file?.tool?.params));
        // colours painted in a slicer: the server's STL has its triangles sorted by colour, the runs paint it (docs/AN.md §19);
        // the canvas says how it went (data-painted: colors, or mismatch when the runs do not cover this STL), for a check from outside
        if (viewer && !parts && state.file?.groups?.length) {
            const ok = paintGroups(viewer, state.file.groups, triangleCount(geom));
            if (ok) paintedFor = state.file.uuid;
            markPainted(ok ? 'colors' : 'mismatch');
        }
        if (viewer && parts) {
            viewer.setPieces(parts.pieces);
            parts.colors.forEach((hex, i) => { if (hex) viewer!.setPieceColor(i, hex); });
            // the farm prints such parts one after another, each from its own spool: that line replaces the kind's
            // usual "printed together" tip
            const tip = document.getElementById('kind-tip');
            if (tip) { tip.textContent = t('farm.calc_parts_by_colour'); tip.classList.remove('hidden'); }
        }
        return true;
    } catch { return false; /* viewer is optional */ }
}

/**
 * A design of separately printed parts in different colours (a box and its lid, a logo and its stand): the bodies of
 * the model are matched to the parts by their size and the triangles reordered body by body, so the viewer can
 * show every part in the colour the design gave it. null for a one-body model or a design in one colour.
 */
function partsByColour(geom: BufferGeometry, params: Record<string, unknown> | null | undefined): { pieces: Piece[]; colors: (string | null)[] } | null {
    const p = (params ?? {}) as { parts_bbox?: Record<string, number[]>; part_colors?: Record<string, { hex?: string }> };
    const names = Object.keys(p.parts_bbox ?? {});
    if (names.length < 2 || new Set(names.map((n) => p.part_colors?.[n]?.hex ?? '').filter(Boolean)).size < 2) return null;
    const pos = geom.getAttribute('position');
    if (!pos || geom.index || pos.count > 900000) return null;
    // the bodies: triangles joined by shared corners (union–find over positions rounded to 0.01 mm)
    const tri = pos.count / 3;
    const parent = new Int32Array(tri);
    for (let i = 0; i < tri; i++) parent[i] = i;
    const find = (i: number): number => { while (parent[i] !== i) { parent[i] = parent[parent[i]]; i = parent[i]; } return i; };
    const seen = new Map<string, number>();
    for (let tr = 0; tr < tri; tr++) for (let k = 0; k < 3; k++) {
        const i = tr * 3 + k;
        const key = `${Math.round(pos.getX(i) * 100)},${Math.round(pos.getY(i) * 100)},${Math.round(pos.getZ(i) * 100)}`;
        const other = seen.get(key);
        if (other === undefined) seen.set(key, tr); else { const a = find(tr); const b = find(other); if (a !== b) parent[a] = b; }
    }
    const groups = new Map<number, number[]>();
    for (let tr = 0; tr < tri; tr++) { const r = find(tr); const g = groups.get(r); if (g) g.push(tr); else groups.set(r, [tr]); }
    if (groups.size < 2) return null;
    // a body is the part whose size it has (the three dimensions sorted, so a turned part still matches)
    const sorted = (v: number[]): number[] => [...v].sort((a, b) => b - a);
    const sizeOf = (ts: number[]): number[] => {
        const lo = [Infinity, Infinity, Infinity]; const hi = [-Infinity, -Infinity, -Infinity];
        ts.forEach((tr) => { for (let k = 0; k < 3; k++) { const i = tr * 3 + k; const v = [pos.getX(i), pos.getY(i), pos.getZ(i)]; for (let a = 0; a < 3; a++) { lo[a] = Math.min(lo[a], v[a]); hi[a] = Math.max(hi[a], v[a]); } } });
        return sorted([hi[0] - lo[0], hi[1] - lo[1], hi[2] - lo[2]]);
    };
    const partOf = (size: number[]): string | null => {
        let best: [number, string] | null = null;
        names.forEach((n) => { const s = sorted(p.parts_bbox![n]); const d = Math.hypot(size[0] - s[0], size[1] - s[1], size[2] - s[2]); if (!best || d < best[0]) best = [d, n]; });
        return best && best[0] < 2 + 0.02 * size[0] ? best[1] : null;
    };
    const pieces: Piece[] = []; const colors: (string | null)[] = []; const order: number[] = [];
    groups.forEach((ts) => {
        const part = partOf(sizeOf(ts));
        pieces.push({ name: part ?? 'body', tris: [order.length, order.length + ts.length] });
        colors.push(part ? p.part_colors?.[part]?.hex ?? null : null);
        for (const tr of ts) order.push(tr);
    });
    if (!colors.some(Boolean)) return null;
    const out = new Float32Array(order.length * 9);
    order.forEach((tr, j) => { for (let k = 0; k < 3; k++) { const i = tr * 3 + k; out[j * 9 + k * 3] = pos.getX(i); out[j * 9 + k * 3 + 1] = pos.getY(i); out[j * 9 + k * 3 + 2] = pos.getZ(i); } });
    geom.setAttribute('position', new Float32BufferAttribute(out, 3));
    geom.deleteAttribute('normal');
    geom.computeVertexNormals();
    return { pieces, colors };
}

/** Printer mode: "Create quote" needs a finished calculation token. */
function enableQuote(c: CalcInfo | null): void {
    const btn = document.getElementById('cta-quote') as HTMLButtonElement | null;
    const tok = document.getElementById('quote-calc-token') as HTMLInputElement | null;
    if (!btn || !tok) return;
    const ok = !!c && c.status === 'done';
    btn.disabled = !ok;
    tok.value = ok ? c!.token : '';
}

function enableDownload(file: FileInfo | null): void {
    setDownload(
        file,
        () => ({ material: state.params.material, quality: state.params.quality, infill: state.params.infill, supports: state.params.supports, scale: state.params.scale }),
        () => { const b = state.calc?.slicer?.dims ?? null; return b ? { x: b.x, y: b.y, z: b.z } : null; },
    );
}

/** What a calculation is made from: the file, the settings, and what the browser measured (the box and the overhangs shape the range). */
function calcPayload(): Record<string, unknown> {
    return {
        file: state.file!.uuid,
        ...state.params,
        supports: state.params.supports === null ? 'auto' : state.params.supports ? 1 : 0,
        geometry: state.geo ? { volume_mm3: state.geo.volume_mm3, area_mm2: state.geo.area_mm2, bbox: state.geo.bbox, overhang_share: Math.round(state.geo.overhang_share * 1000) / 1000, triangles: state.geo.triangles } : undefined,
    };
}

/** The numbers for the file as it is set: the exact slice for a small model, the estimate alone for a big one (TODO #41). */
function requestNumbers(): void {
    if (state.file?.big_model) requestRough();
    else requestPrecise();
}

/**
 * A big model (millions of triangles, or a slice that took over a minute last time) is not sliced on this page: the
 * calculation is made for the share link and the order button, the estimate with its range is the answer, and the
 * order page slices once the customer has chosen (docs/prompts/kalkulace-odhad.md). A finished slice of the same
 * settings still comes back exact.
 */
function requestRough(): void {
    if (!state.file) return;
    window.clearTimeout(state.debounce);
    state.debounce = window.setTimeout(async () => {
        const file = state.file;
        try {
            setBigOnly(file);
            enableQuote(null);
            const c = await createCalculation({ ...calcPayload(), rough_only: 1 });
            if (state.file !== file) return;   // another file was chosen meanwhile: this one is history
            state.calc = c;
            takeFile(c.file);
            history.replaceState(null, '', c.url);
            if (c.status === 'done') { setStatus(marketplace() ? 'calc.status.done' : 'calc.status.done_facts', false); renderPrecise(c); }
            else setBigOnly(c.file ?? file, c);
            enableDownload(c.file ?? file);   // "I have a printer": the model is there to download, slice or no slice
        } catch {
            setStatus('calc.status.failed', false);
        }
    }, 700);
}

/** Ask the server for a precise calculation (debounced on slider moves). */
function requestPrecise(): void {
    if (!state.file) return;
    window.clearTimeout(state.debounce);
    state.debounce = window.setTimeout(async () => {
        try {
            setComputing(state.file);
            enableQuote(null);
            const c = await createCalculation(calcPayload());
            state.calc = c;
            takeFile(c.file);   // a slice the server already had comes back done at once, with the file processed meanwhile
            history.replaceState(null, '', c.url);
            if (c.status === 'done') {
                setStatus(marketplace() ? 'calc.status.done' : 'calc.status.done_facts', false);
                renderPrecise(c);
                enableDownload(c.file);
            } else if (c.status === 'rough') {   // the server kept the estimate (a design printed by parts): nothing to wait for
                renderRough(); setBigOnly(c.file, c); enableDownload(c.file);
            } else {
                poll(c.token);
            }
        } catch {
            setStatus('calc.status.failed', false);
        }
    }, 700);
}

/** A setting changed: the model and its size follow at once, the numbers wait for "Recalculate" (a slice costs the server real work). */
function onParamsChanged(): void {
    renderSize();
    viewer?.setScale(state.params.scale);
    const b = nativeBbox();
    if (b) { const s = state.params.scale; $('dims-badge').textContent = `${fmt.format(b.x * s)} × ${fmt.format(b.y * s)} × ${fmt.format(b.z * s)} mm`; }
    markStale();
}

function markStale(): void {
    if (!state.file && !state.geo) return;
    state.stale = true;
    stopPolling();
    setStatus('calc.status.stale', false);
    const btn = document.getElementById('cta-recalc');
    btn?.classList.add('ring-4', 'ring-action/40');
}

/** "Recalculate": the rough numbers at once, the precise slice from the server behind them. */
function recalculate(): void {
    state.stale = false;
    document.getElementById('cta-recalc')?.classList.remove('ring-4', 'ring-action/40');
    renderRough();
    refreshDownload();
    requestNumbers();
}

function showResult(): void {
    $('hero').classList.add('hidden');
    $('result').classList.remove('hidden');
    if (!viewer) viewer = new Viewer($('viewer') as HTMLCanvasElement);
    document.getElementById('calc-bar')?.removeAttribute('hidden');   // a phone: the price and the way on at the bottom
    revealIfHidden($('result'), 'start');   // the result replaces the hero at the top: the page moves only when it is not in view (docs/AN.md §18)
}

function showHero(): void {
    stopPolling();
    document.getElementById('calc-bar')?.setAttribute('hidden', '');
    state.file = null; state.calc = null; state.geometry = null; state.geo = null; paintedFor = null; paintingFor = null;
    document.getElementById('viewer')?.removeAttribute('data-painted');
    $('result').classList.add('hidden');
    $('hero').classList.remove('hidden');
    history.replaceState(null, '', homeUrl());
}

function heroError(msg: string | null): void {
    const el = $('hero-error');
    el.textContent = msg ?? '';
    el.classList.toggle('hidden', !msg);
}

/** From this size a model is read in a worker; a smaller one is faster on the page itself (no second copy of three). */
const WORKER_FROM_BYTES = 20 * 1024 * 1024;

/**
 * The model read into a geometry with its volume, area and box: a big STL or OBJ in a worker, so the page keeps
 * breathing and the upload keeps going; everything else, and a browser without workers, on the page as before.
 */
async function readModel(file: File): Promise<{ geom: BufferGeometry; stats: GeoStats } | null> {
    const ext = extensionOf(file.name);
    if (file.size >= WORKER_FROM_BYTES && (ext === 'stl' || ext === 'obj') && typeof Worker !== 'undefined') {
        let reply: ParseReply | null = null;
        try {
            reply = await new Promise<ParseReply>((resolve, reject) => {
                const worker = new ParseWorker();
                worker.onmessage = (e: MessageEvent<ParseReply>) => { resolve(e.data); worker.terminate(); };
                worker.onerror = (e) => { reject(new Error(e.message)); worker.terminate(); };
                worker.postMessage(file);
            });
        } catch {
            reply = null;   // the worker could not be started: the page reads the file itself
        }
        if (reply) {
            if (!reply.ok) throw new Error(reply.error);
            if (!reply.model) return null;
            const geom = new BufferGeometry();
            geom.setAttribute('position', new Float32BufferAttribute(reply.model.position, 3));
            if (reply.model.normal) geom.setAttribute('normal', new Float32BufferAttribute(reply.model.normal, 3));
            return { geom, stats: reply.model.stats };
        }
    }
    const geom = await loadGeometry(file);
    return geom ? { geom, stats: stats(geom) } : null;
}

/** A file refused before any upload began (the format, the size): one line in our statistics, the funnel's "upload began" step; nothing about the file. */
function refused(kind: 'format' | 'too_big'): void {
    const url = routes()?.refused;
    if (!url) return;
    fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ step: 'upload', kind }), keepalive: true }).catch(() => undefined);
}

async function handleFile(file: File): Promise<void> {
    heroError(null);
    const ext = extensionOf(file.name);
    if (!cfg.formats.includes(ext)) { heroError(t('calc.error.read')); refused('format'); return; }
    if (file.size > cfg.max_upload_mb * 1024 * 1024) { heroError(t('calc.error.too_big', { max: cfg.max_upload_mb })); refused('too_big'); return; }

    state.localFile = file;
    document.getElementById('viewer')?.removeAttribute('data-painted');   // the mark is the shown file's
    showKindTip(undefined);
    showResult();
    $('file-badge').textContent = file.name;
    setStatus('calc.status.reading', true);

    // 1) at once: the upload starts before the browser reads the file, so the server's measuring and slicing do not wait
    // for the picture and the rough price (a 90 MB dragon: the browser alone took Roman a long wait, 24 Oct 2026);
    // the browser's volume and area used to travel with it, the server does not use them
    const upload = uploadFile(file);
    upload.catch(() => undefined);   // judged below, after the model is shown

    // 2) meanwhile: parse in the browser, show model + rough price
    try {
        const read = await readModel(file);
        if (read) {
            const geom = read.geom;
            let s = read.stats;
            const k = normaliseUnits(geom, s);
            if (k !== 1) s = stats(geom);
            state.geometry = geom;
            state.geo = s;
            viewer!.setGeometry(geom, state.params.scale);
            setStatus('calc.status.rough', true);
            renderRough();
        } else {
            setStatus('calc.status.converting', true);
        }
    } catch {
        setStatus('calc.status.converting', true);
    }

    // 3) the upload that has been running all along → precise slice
    try {
        const info = await upload;
        if (state.localFile !== file) return;   // another file was chosen meanwhile: this one is history
        state.file = info;
        renderSize();     // a file the browser could not read shows its size as soon as the server measured it
        void showPainted(info);
        requestNumbers();
    } catch (e) {
        setStatus('calc.status.failed', false);
        heroError(t('calc.error.upload'));
    }
}

function buildMaterials(): void {
    const box = $('materials');
    box.innerHTML = '';
    for (const m of cfg.materials) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'chip' + (m.code === state.params.material ? ' chip-on' : '');
        b.dataset.material = m.code;
        // the plain name, the filament code everyone knows from the spool, then what it is good for
        b.innerHTML = `${m.label} <span class="font-normal">${m.code}</span> <span class="opacity-70 text-xs">${m.lay.map((l) => cfg.lay[l] ?? l).join(' · ')}</span>`;
        b.onclick = () => {
            state.params.material = m.code;
            box.querySelectorAll('.chip').forEach((c) => c.classList.toggle('chip-on', (c as HTMLElement).dataset.material === m.code));
            $('material-hint').textContent = `${m.hint} (${m.code})`;
            onParamsChanged();
        };
        box.appendChild(b);
    }
    $('material-hint').textContent = `${material(state.params.material).hint} (${state.params.material})`;
}

function bindControls(): void {
    buildMaterials();
    $('quality').querySelectorAll<HTMLButtonElement>('[data-quality]').forEach((b) => b.onclick = () => {
        state.params.quality = b.dataset.quality!;
        $('quality').querySelectorAll('.seg').forEach((s) => s.classList.toggle('seg-on', s === b));
        onParamsChanged();
    });
    $('supports').querySelectorAll<HTMLButtonElement>('[data-supports]').forEach((b) => b.onclick = () => {
        const v = b.dataset.supports!;
        state.params.supports = v === 'auto' ? null : v === '1';
        $('supports').querySelectorAll('.seg').forEach((s) => s.classList.toggle('seg-on', s === b));
        onParamsChanged();
    });
    const infill = $('infill') as HTMLInputElement;
    infill.oninput = () => { state.params.infill = Number(infill.value); $('infill-val').textContent = `${infill.value} %`; onParamsChanged(); };
    const qty = $('quantity') as HTMLInputElement;
    // the number of pieces needs no new slice (the plate layout and the price are counted on the server in a moment),
    // so it is counted again at once instead of waiting for "Recalculate" (Roman, 18 Oct 2026: the order button sat idle)
    let qtyTimer: number | undefined;
    qty.onchange = () => {
        state.params.quantity = Math.max(1, Math.min(1000, Number(qty.value) || 1)); qty.value = String(state.params.quantity);
        window.clearTimeout(qtyTimer);
        qtyTimer = window.setTimeout(() => { if (state.file || state.geo) recalculate(); }, 400);
    };
    const recalc = document.getElementById('cta-recalc');
    // the button sits under the settings, the numbers above them: bring the result back into view
    // the numbers stay where they are (the price card is on the screen from lg, the bar on a phone): the card flashes when they land
    if (recalc) recalc.onclick = () => { flashNext = true; recalculate(); };
    const reset = document.getElementById('size-reset');
    if (reset) reset.onclick = () => { state.params.scale = 1; document.getElementById('size-limit')?.classList.add('hidden'); onParamsChanged(); };
    const scale = $('scale') as HTMLInputElement;
    scale.oninput = () => { state.params.scale = Number(scale.value) / 100; $('scale-val').textContent = `${scale.value} %`; document.getElementById('size-limit')?.classList.add('hidden'); onParamsChanged(); };
    (['x', 'y', 'z'] as const).forEach((axis) => {
        const el = document.getElementById(`size-${axis}`) as HTMLInputElement | null;
        if (!el) return;
        el.onchange = () => sizeTyped(axis, Number(el.value));
        el.onkeydown = (e) => { if (e.key === 'Enter') { e.preventDefault(); el.blur(); } };
    });

    const input = $('file-input') as HTMLInputElement;
    input.onchange = () => { if (input.files?.[0]) handleFile(input.files[0]); input.value = ''; };
    const drop = $('dropzone');
    drop.ondragover = (e) => { e.preventDefault(); drop.classList.add('border-action', 'bg-action-soft'); };
    drop.ondragleave = () => drop.classList.remove('border-action', 'bg-action-soft');
    drop.ondrop = (e) => { e.preventDefault(); drop.classList.remove('border-action', 'bg-action-soft'); const f = e.dataTransfer?.files?.[0]; if (f) handleFile(f); };
    document.body.addEventListener('dragover', (e) => e.preventDefault());
    document.body.addEventListener('drop', (e) => { e.preventDefault(); const f = e.dataTransfer?.files?.[0]; if (f && $('hero').classList.contains('hidden') === false) handleFile(f); });

    $('cta-new').onclick = showHero;
    $('cta-share').onclick = async () => {
        const url = state.calc?.url ?? location.href;
        try { await navigator.clipboard.writeText(url); } catch { /* ignore */ }
        let how: 'share' | 'copy' = 'copy';
        if (navigator.share) { try { await navigator.share({ url }); how = 'share'; } catch { /* cancelled */ } }
        const b = $('cta-share'); const old = b.textContent; b.textContent = t('calc.cta.copied'); setTimeout(() => (b.textContent = old), 1500);
        // one `share` event in our statistics (and in GA when allowed): the kind and the tool the model came from, nothing else (docs/AN.md §12)
        const shared = routes()?.shared;
        if (shared) fetch(shared, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ kind: 'calc', tool: state.file?.tool?.kind ?? null, how }), keepalive: true }).catch(() => undefined);
    };
    bootDownload();
    bootInquiry(() => (state.calc && state.calc.status === 'done' ? state.calc.token : null));
}

/** Shared link: restore parameters and result from the server. */
/** Make the controls show what is in state.params (after restoring a link or applying tool hints). */
function syncControls(): void {
    ($('infill') as HTMLInputElement).value = String(state.params.infill); $('infill-val').textContent = `${state.params.infill} %`;
    $('quality').querySelectorAll<HTMLElement>('.seg').forEach((s) => s.classList.toggle('seg-on', s.dataset.quality === state.params.quality));
    const sup = state.params.supports === null ? 'auto' : state.params.supports ? '1' : '0';
    $('supports').querySelectorAll<HTMLElement>('.seg').forEach((s) => s.classList.toggle('seg-on', s.dataset.supports === sup));
}

/** Generated models: "change it in words" → a new generation, then the new model opens here. */
function showRefine(file: FileInfo | null): void {
    const box = document.getElementById('refine-box') as HTMLFormElement | null;
    if (!box) return;
    const gen = file?.generation ?? null;
    box.classList.toggle('hidden', !gen);
    if (!gen) return;
    const input = $('refine-text') as HTMLInputElement; const msg = $('refine-msg'); const btn = box.querySelector('button') as HTMLButtonElement;
    const hint = msg.dataset.hint ?? (msg.dataset.hint = msg.textContent ?? '');
    input.disabled = btn.disabled = !gen.refinable;
    msg.textContent = gen.refinable ? hint : t('refine.photo_only');
    box.onsubmit = async (e) => {
        e.preventDefault();
        const instruction = input.value.trim();
        if (instruction.length < 3) { input.focus(); return; }
        btn.disabled = true; msg.textContent = t('refine.working');
        try {
            const res = await fetch(`${routes().generateShow}/${gen.token}/refine`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ instruction }) });
            const body = await res.json();
            if (res.status === 429) { msg.textContent = t(body.error === 'global_limit' ? 'search.gen_global_limit' : 'search.gen_daily_limit', { n: body.limit, m: body.login_limit }); btn.disabled = false; return; }
            if (!res.ok) throw new Error(body.error ?? 'refine');
            let g = body.generation;
            while (g.status !== 'done' && g.status !== 'failed') {
                msg.textContent = `${t('refine.working')} ${Math.max(5, g.progress)} %`;
                await new Promise((r) => setTimeout(r, 3000));
                g = (await (await fetch(`${routes().generateShow}/${g.token}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })).json()).generation;
            }
            if (g.status === 'failed' || !g.file) throw new Error('failed');
            input.value = '';
            await openFile(g.file.uuid);
        } catch {
            msg.textContent = t('refine.failed'); btn.disabled = false;
        }
    };
}

/** Generated busts and figures: another base, name or front. Pure geometry on the server, no new generation. */
function showPedestal(file: FileInfo | null): void {
    const box = document.getElementById('pedestal-box') as HTMLFormElement | null;
    if (!box) return;
    const ped = file?.generation?.pedestal ?? null;
    box.classList.toggle('hidden', !ped || !file);
    if (!ped || !file) return;
    const type = $('pedestal-type') as HTMLSelectElement; const front = $('pedestal-front') as HTMLSelectElement; const sink = $('pedestal-sink') as HTMLSelectElement; const tidy = $('pedestal-tidy') as HTMLInputElement;
    const name = $('pedestal-name') as HTMLInputElement; const dedication = $('pedestal-dedication') as HTMLInputElement;
    const msg = $('pedestal-msg'); const btn = box.querySelector('button') as HTMLButtonElement;
    const hint = msg.dataset.hint ?? (msg.dataset.hint = msg.textContent ?? '');
    // the turned foot and the plinth carry a name, only the plinth has room for a dedication
    const field = (sel: string, on: boolean) => box.querySelectorAll<HTMLElement>(sel).forEach((e) => { e.classList.toggle('hidden', !on); e.classList.toggle('block', on); });
    const plaque = () => { field('[data-pedestal-name]', ['socle', 'antique', 'plaque'].includes(type.value)); field('[data-pedestal-dedication]', type.value === 'plaque'); };
    type.value = ped.type; front.value = 'keep'; sink.value = String(ped.sink ?? 0); tidy.checked = ped.tidy ?? true; name.value = ped.name; dedication.value = ped.dedication;
    msg.textContent = hint; btn.disabled = false;
    plaque(); type.onchange = plaque;
    box.onsubmit = async (e) => {
        e.preventDefault();
        btn.disabled = true; msg.textContent = t('pedestal.working');
        try {
            const res = await fetch(`${routes().files}/${file.uuid}/pedestal`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ type: type.value, front: front.value, sink: Number(sink.value), tidy: tidy.checked, name: name.value.trim(), dedication: dedication.value.trim() }) });
            const body = await res.json();
            if (!res.ok || !body.file) throw new Error(body.error ?? 'pedestal');
            await openFile(body.file.uuid);
        } catch {
            msg.textContent = t('pedestal.failed'); btn.disabled = false;
        }
    };
}

/** Any ready model: a two-part casting mold around it (free geometry, seconds). A mold file shows what the tool measured instead. */
function showMold(file: FileInfo | null): void {
    const box = document.getElementById('mold-box') as HTMLFormElement | null;
    const report = document.getElementById('mold-report');
    if (!box || !report) return;
    const isMold = file?.kind === 'mold';
    box.classList.toggle('hidden', !file || file.status !== 'ready' || isMold);
    report.classList.toggle('hidden', !isMold || !file?.mold);
    if (isMold && file?.mold) {
        report.textContent = moldReport(file.mold, t).map((l) => l.text).join(' ');
    }
    if (!file || isMold) return;
    const msg = $('mold-msg'); const btn = box.querySelector('button') as HTMLButtonElement;
    const hint = msg.dataset.hint ?? (msg.dataset.hint = msg.textContent ?? '');
    msg.textContent = hint; btn.disabled = false;
    box.onsubmit = async (e) => {
        e.preventDefault();
        btn.disabled = true; msg.textContent = t('mold.working');
        const split = ($('mold-split') as HTMLSelectElement).value;
        try {
            const res = await fetch(`${routes().files}/${file.uuid}/mold`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ wall: Number(($('mold-wall') as HTMLSelectElement).value), axis: ($('mold-axis') as HTMLSelectElement).value, split: split ? Number(split) : null }) });
            const body = await res.json();
            if (res.status === 503) { msg.textContent = t('mold.unavailable'); btn.disabled = false; return; }
            if (!res.ok || !body.file) throw new Error(body.error ?? 'mold');
            await openFile(body.file.uuid);
        } catch {
            msg.textContent = t('mold.failed'); btn.disabled = false;
        }
    };
}

/**
 * Beside "print it with us": the spools of our farm nearest to the colours of the design, each once, with the photo of
 * a print from it. A design is drawn in free colours; this is where the customer first sees what we would print from.
 */
function showNearest(file: FileInfo | null): void {
    const el = document.getElementById('cta-farm-nearest');
    if (!el) return;
    const safe = (s: string): string => s.replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`);
    const seen = new Set<string>();
    const spools = (file?.nearest ?? []).map((n) => n.spool).filter((s) => (seen.has(s.code) ? false : Boolean(seen.add(s.code))));
    el.classList.toggle('hidden', spools.length === 0); el.classList.toggle('flex', spools.length > 0);
    if (!spools.length) { el.innerHTML = ''; return; }
    const dot = 'inline-block h-5 w-5 shrink-0 rounded-full border border-slate-300 object-cover';
    const list = spools.map((s) => `<span class="inline-flex items-center gap-1.5">${s.photo ? `<img src="${safe(s.photo)}" alt="" class="${dot}">` : `<span class="${dot}" style="background-color:${/^#[0-9a-f]{6}$/i.test(s.hex) ? s.hex : '#ffffff'}"></span>`}<span>${safe(s.name)} ${safe(s.material)}${s.in_stock ? '' : ` <span class="text-warn">${safe(t('farm.calc_nearest_out'))}</span>`}</span></span>`).join('');
    const [before, after] = t('farm.calc_nearest', { list: ' ' }).split(' ');
    el.innerHTML = `<span class="font-medium">${safe(before.trim())}</span>${list}${after?.trim() ? `<span>${safe(after.trim())}</span>` : ''}`;
}

/** The file whose painted colours the viewer shows (one load of the server's sorted STL per file). */
let paintedFor: string | null = null;
/** The file whose sorted STL is being loaded now (two answers in a row do not load it twice). */
let paintingFor: string | null = null;

/**
 * The file as a calculation brings it (polled, or created already done): the server may have processed it since the
 * upload answered, so its colour runs, the nearest spools and the size come from here. A file other than the shown
 * one (an answer that came late) is left alone.
 */
function takeFile(f: FileInfo | null | undefined): void {
    if (!f || (state.file && state.file.uuid !== f.uuid)) return;
    state.file = f;
    showNearest(f);
    renderSize();
    void showPainted(f);
}

const triangleCount = (g: BufferGeometry): number => (g.index ? g.index.count : g.getAttribute('position').count) / 3;

/**
 * A 3MF with colours painted in a slicer: the browser read it in its own order, the server sorted the triangles by colour.
 * Once the file says so (`groups`), the server's STL takes the viewer's place, painted run by run; the camera stays.
 * Without groups nothing changes.
 */
/**
 * The line of painted colours under the price: how many the file has (the small ones merged into the body's colour),
 * the price in one colour and in colour, and the farm's button turned into "print in colour" with a second one for one colour.
 * Hidden for a file without painted colours. Nothing above it moves: the card grows at its end.
 */
function renderColors(file: FileInfo | null, calc: CalcInfo | null): void {
    const box = document.getElementById('calc-colors');
    if (!box) return;
    const colors = file?.colors ?? [];
    const main = colors.filter((c) => !c.merged);
    const small = colors.length - main.length;
    const painted = main.length >= 2;
    box.classList.toggle('hidden', !painted);
    const farm = document.getElementById('cta-farm');
    const one = document.getElementById('cta-farm-one');
    if (farm) {
        if (!farm.dataset.label) farm.dataset.label = farm.textContent?.trim() ?? '';
        farm.dataset.colors = painted ? 'painted' : 'one';
        const label = painted ? t('calc.colors.order') : farm.dataset.label;
        const text = [...farm.childNodes].find((n) => n.nodeType === Node.TEXT_NODE && n.textContent?.trim());
        if (text) text.textContent = ` ${label} `;
        farm.dataset.barLabel = label;   // the bar of a phone says the same (components/action-bar)
    }
    one?.classList.toggle('hidden', !painted);
    if (!painted) return;
    const line = $('calc-colors-line');
    line.replaceChildren(colorDots(main.map((c) => c.hex)), plural('calc.colors.count', main.length, { n: main.length }) + (small > 0 ? ` ${plural('calc.colors.merged', small, { n: small })}` : ''));
    const prices = $('calc-colors-prices');
    const p = calc?.colors;
    prices.classList.toggle('hidden', !p);
    // two equal prices side by side look like a mistake (the turtle: both at the price list's minimum): then one line, one price
    if (p) prices.textContent = priceText(p.multi) === priceText(p.one)
        ? t('calc.colors.price_same', { price: priceText(p.one) })
        : `${t('calc.colors.price_multi', { price: priceText(p.multi) })} · ${t('calc.colors.price_one', { price: priceText(p.one) })}`;
}

async function showPainted(file: FileInfo | null): Promise<void> {
    renderColors(file, state.calc);
    if (!file?.groups?.length || !file.stl_url || paintedFor === file.uuid || paintingFor === file.uuid || !viewer) return;
    paintingFor = file.uuid;
    markPainted('loading');
    try {
        // the sorted STL once per file; a load that failed (the network, a busy server) is tried again on its own: the
        // answers that could retry it stop coming once the price is done (the turtle stayed blue on production, 28 Oct 2026)
        for (let attempt = 0; attempt < 4; attempt++) {
            if (attempt) await new Promise((r) => window.setTimeout(r, 1500 * attempt));
            if (state.file?.uuid !== file.uuid || !viewer) return;   // another file meanwhile
            // the same model in another triangle order: the visitor's view stays (the viewer frames anew only a clearly different model)
            viewer.keepView(true);
            const loaded = await showServerStl(file.stl_url).finally(() => viewer?.keepView(false));
            if (loaded) { paintedFor = file.uuid; return; }   // painted, or runs that do not fit (data-painted says which): not again
        }
        markPainted('failed');
    } finally {
        if (paintingFor === file.uuid) paintingFor = null;
    }
}

/** How the painted preview went, on the viewer's canvas (read by the review harness and by hand in the console). */
function markPainted(how: 'loading' | 'colors' | 'mismatch' | 'failed'): void {
    const canvas = document.getElementById('viewer');
    if (canvas) canvas.dataset.painted = how;
}

/** Tool-specific line under the viewer: how this kind of model is meant to be printed. */
function showKindTip(kind: string | undefined): void {
    showNearest(state.file);
    const el = document.getElementById('kind-tip');
    if (!el) return;
    const key = `calc.tip.${kind ?? ''}`;
    const text = t(key);
    el.textContent = text === key ? '' : text;
    el.classList.toggle('hidden', text === key);
    const edit = state.file?.tool?.url ?? null;
    const bar = document.getElementById('edit-design');
    if (bar) {
        bar.classList.toggle('hidden', !edit); bar.classList.toggle('flex', Boolean(edit));
        if (edit) (document.getElementById('edit-design-link') as HTMLAnchorElement).href = edit;
    }
    showRefine(kind === 'generated' ? state.file : null);
    showPedestal(kind === 'generated' ? state.file : null);
    showMold(state.file);
}

async function restore(c: CalcInfo): Promise<void> {
    const p = c.params as Record<string, unknown>;
    state.params = {
        material: String(p.material ?? cfg.default_material), quality: String(p.quality ?? 'standard'), infill: Number(p.infill ?? 15),
        supports: p.supports === null || p.supports === undefined ? null : Boolean(p.supports), scale: Number(p.scale ?? 1), quantity: Number(p.quantity ?? 1),
    };
    ($('infill') as HTMLInputElement).value = String(state.params.infill); $('infill-val').textContent = `${state.params.infill} %`;
    ($('quantity') as HTMLInputElement).value = String(state.params.quantity);
    $('quality').querySelectorAll<HTMLElement>('.seg').forEach((s) => s.classList.toggle('seg-on', s.dataset.quality === state.params.quality));
    buildMaterials();
    state.calc = c; state.file = c.file;
    syncControls();
    renderSize();
    showKindTip(c.file?.kind);
    showResult();
    $('file-badge').textContent = c.file?.name ?? '';
    if (c.file?.stl_url) await showServerStl(c.file.stl_url);
    if (c.status === 'done') { setStatus(marketplace() ? 'calc.status.done' : 'calc.status.done_facts', false); renderPrecise(c); enableDownload(c.file); }
    else if (c.status === 'failed') { setStatus(failedKey(c), false); renderRough(); }
    else if (c.status === 'rough') { renderRough(); setBigOnly(c.file, c); enableDownload(c.file); }   // a big model or a design printed by parts: never sliced here (TODO #41, docs/AN.md §17.2)
    else { renderRough(); poll(c.token); }
}

/** Open a file that already exists on the server (generated model): wait until processed, show it, price it. */
export async function openFile(uuid: string): Promise<void> {
    stopPolling();
    state.geometry = null; state.geo = null; state.calc = null; state.localFile = null;
    showResult();
    setStatus('calc.status.converting', true);
    for (let i = 0; i < 60; i++) {
        try {
            const info = await getFile(uuid);
            state.file = info;
            $('file-badge').textContent = info.name;
            if (info.status === 'ready') break;
            if (info.status === 'failed') { setStatus('calc.status.not_model', false); return; }
        } catch { /* retry */ }
        await new Promise((r) => setTimeout(r, 1500));
    }
    const h = state.file?.hints;
    if (h) {
        if (h.supports !== undefined) state.params.supports = h.supports;
        if (h.infill !== undefined) state.params.infill = h.infill;
        if (h.quality) state.params.quality = h.quality;
        if (h.vase !== undefined) state.params.vase = h.vase;            // a vase prints as one spiralled wall: no infill, no seam
        syncControls();
    }
    showKindTip(state.file?.kind);
    renderSize();                                   // the server already measured the file: the size shows before the model has loaded
    if (state.file?.stl_url) await showServerStl(state.file.stl_url);
    renderRough();
    requestNumbers();
}

export function boot(): void {
    if (!document.getElementById('calculator')) return;
    bindControls();
    document.getElementById('cta-farm-one')?.addEventListener('click', () => {
        const farm = document.getElementById('cta-farm');
        if (!farm) return;
        farm.dataset.colors = 'one';
        farm.click();
        farm.dataset.colors = (state.file?.colors ?? []).filter((c) => !c.merged).length >= 2 ? 'painted' : 'one';
    });
    if (initial) { restore(initial); return; }
    const query = new URLSearchParams(location.search);
    const open = query.get('open');
    if (open && /^[0-9a-f-]{36}$/.test(open)) {
        const m = (query.get('material') ?? '').toUpperCase();
        if (cfg.materials.some((x) => x.code === m)) state.params.material = m;
        const q = Number(query.get('quantity'));
        if (q >= 1 && q <= 1000) { state.params.quantity = Math.round(q); ($('quantity') as HTMLInputElement).value = String(state.params.quantity); }
        const colour = document.querySelector<HTMLInputElement>('#inquiry-form [name=color]');
        if (colour && query.get('color')) colour.value = (query.get('color') ?? '').slice(0, 40);
        const note = document.querySelector<HTMLTextAreaElement>('#inquiry-form [name=note]');
        if (note && query.get('note')) note.value = (query.get('note') ?? '').slice(0, 900);
        if (query.get('download') === '1') openDownloadWhenReady();
        buildMaterials();
        history.replaceState(null, '', homeUrl());
        openFile(open);
    }
}
