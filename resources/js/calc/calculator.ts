import { BufferGeometry } from 'three';
import { Viewer } from './viewer';
import { loadGeometry, loadGeometryFromUrl, extensionOf, BROWSER_FORMATS } from './loaders';
import { stats, normaliseUnits, GeoStats } from './geometry';
import { estimate, price, range, RoughConfig, Profile, Params } from './rough';
import { uploadFile, createCalculation, getCalculation, getFile, CalcInfo, FileInfo } from './api';
import { bootInquiry } from './inquiry';
import { renderCheck } from './check';
import { renderAdvice } from './advice';
import { bootDownload, setDownload, refresh as refreshDownload, openWhenReady as openDownloadWhenReady } from './download';

const routes = () => (window as unknown as { MP_ROUTES: Record<string, string> }).MP_ROUTES;

interface MaterialCfg { code: string; density: number; lay: string[]; sliceable: boolean; label: string; hint: string }
interface Config {
    rough: RoughConfig; orientation_profiles: Profile[]; round_to: number; currency: string; max_scale: number;
    bed_mm: { x: number; y: number; z: number }; materials: MaterialCfg[]; default_material: string;
    formats: string[]; max_upload_mb: number; lay: Record<string, string>;
    marketplace?: boolean;   // false: the calculator shows the slicer's facts and no prices (the farm has its own)
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

function setStatus(key: string, spinning: boolean): void {
    $('status').textContent = t(key);
    $('status-spinner').classList.toggle('hidden', !spinning);
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
    const fit = document.getElementById('bed-fit');
    if (fit) {
        const n = b ? piecesOnBed({ x: b.x * s, y: b.y * s, z: b.z * s }) : null;
        const bed = cfg.bed_mm ? `${fmt.format(cfg.bed_mm.x)} × ${fmt.format(cfg.bed_mm.y)} mm` : '';
        fit.textContent = n === null ? '' : n > 0 ? t('calc.fit.bed', { n, b: bed }) : t('calc.fit.none', { b: bed });
        fit.classList.toggle('text-amber-700', n === 0);
    }
}

/** How many copies fit on the smaller farm printer's plate at once (5 mm apart, either way round); 0 when one does not fit. */
function piecesOnBed(d: { x: number; y: number; z: number }): number | null {
    const bed = cfg.bed_mm;
    if (!bed || !(d.x > 0 && d.y > 0)) return null;
    if (d.z > bed.z) return 0;
    const gap = 5;
    const along = (size: number, room: number) => Math.floor((room + gap) / (size + gap));
    return Math.max(along(d.x, bed.x) * along(d.y, bed.y), along(d.y, bed.x) * along(d.x, bed.y));
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

/** Rough numbers from browser geometry — under a second, on every slider move. */
function renderRough(): void {
    const g = state.geo ?? (state.file && state.file.volume_mm3 != null ? { volume_mm3: state.file.volume_mm3, area_mm2: state.file.area_mm2, bbox: state.file.bbox!, triangles: 0 } : null);
    if (!g) return;
    const est = estimate(cfg.rough, material(state.params.material).density, { volume_mm3: g.volume_mm3, area_mm2: g.area_mm2 }, state.params);
    const bds = cfg.orientation_profiles.map((p) => price(cfg.round_to, p, est.grams, est.minutes, state.params.quantity));
    const q = state.params.quantity;
    const s = state.params.scale;
    $('dims-badge').textContent = `${fmt.format(g.bbox.x * s)} × ${fmt.format(g.bbox.y * s)} × ${fmt.format(g.bbox.z * s)} mm`;
    renderSize();
    $('stat-grams').textContent = `≈ ${fmt.format(est.grams * q)} g`;
    $('stat-time').textContent = `≈ ${minutesText(est.minutes * q)}`;
    if (!marketplace() && !farmPriced()) {
        renderFacts({ minutes: est.minutes * q, grams: est.grams * q, rough: true });
        return;
    }
    const [lo, hi] = range(cfg.rough, cfg.round_to, bds.map((b) => b.total), true);
    $('price-main').textContent = lo === hi ? fmt.format(lo) : `${fmt.format(lo)} – ${fmt.format(hi)}`;
    $('price-sub').textContent = q > 1 ? `${t('calc.price.per_piece')} ≈ ${fmt.format(Math.round(lo / q))} – ${fmt.format(Math.round(hi / q))}` : '';
    const leads = bds.map((b) => b.lead_time_days);
    $('stat-lead').textContent = t('calc.days', { n: leadRange(leads) });
    renderBreakdown(bds.map((b) => ({ ...b, label: t(`calc.profile.${b.profile}`) })), true);
    if (!material(state.params.material).sliceable) $('price-sub').textContent = t('calc.est_only');
}

const marketplace = () => cfg.marketplace !== false;
// the farm's single price list: the calculator prices like the marketplace does, with one list
const farmPriced = () => !marketplace() && cfg.orientation_profiles.length === 1 && cfg.orientation_profiles[0].key === 'farm';

/**
 * Without the marketplace the result card is about the print, not about money: the headline is the print time,
 * with the machine's speed modes when the slicer knows them, then material, layers and supports.
 */
function renderFacts(f: { minutes: number; grams: number; meters?: number | null; modes?: Record<string, number>; layers?: number | null; supports?: boolean; rough: boolean }): void {
    const approx = f.rough ? '≈ ' : '';
    $('price-main').textContent = approx + minutesText(f.minutes);
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
function renderPrecise(c: CalcInfo): void {
    if (!c.slicer || !c.prices) return;
    const q = state.params.quantity;
    if (!marketplace() && !farmPriced()) {
        $('stat-grams').textContent = `${fmt.format(c.slicer.grams * q)} g`;
        $('stat-time').textContent = minutesText(c.slicer.minutes * q);
        $('dims-badge').textContent = `${fmt.format(c.slicer.dims.x)} × ${fmt.format(c.slicer.dims.y)} × ${fmt.format(c.slicer.dims.z)} mm`;
        renderFacts({ minutes: c.slicer.minutes * q, grams: c.slicer.grams * q, meters: (c.slicer.meters ?? 0) * q || null, modes: c.slicer.minutes_by_mode, layers: c.slicer.layers, supports: c.slicer.supports_used, rough: false });
        enableQuote(c);
        renderCheck(document.getElementById('model-check'), c.file?.check);
        renderAdvice(document.getElementById('model-advice'), state.file?.uuid, c.token);
        $('warnings').innerHTML = [...new Set(c.slicer.warnings ?? [])].filter((w) => i18n[`calc.warn.${w}`]).map((w) => `<li>⚠️ ${t(`calc.warn.${w}`)}</li>`).join('');
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
        const [lo, hi] = range(cfg.rough, cfg.round_to, totals, false);
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
    $('stat-lead').textContent = t('calc.days', { n: leadRange(leads) });
    $('dims-badge').textContent = `${fmt.format(c.slicer.dims.x)} × ${fmt.format(c.slicer.dims.y)} × ${fmt.format(c.slicer.dims.z)} mm`;
    renderBreakdown(c.prices.map((p) => ({ ...p, label: ownProfileId && (p as { printer_profile_id?: number | null }).printer_profile_id === ownProfileId ? t('calc.profile.mine') : (p.label ?? t(`calc.profile.${p.profile}`)) })), false);
    renderCheck(document.getElementById('model-check'), c.file?.check);
    renderAdvice(document.getElementById('model-advice'), state.file?.uuid, c.token);
    const warns = [...(c.slicer.warnings ?? [])];
    $('warnings').innerHTML = [...new Set(warns)].filter((w) => i18n[`calc.warn.${w}`]).map((w) => `<li>⚠️ ${t(`calc.warn.${w}`)}</li>`).join('');
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
            if (c.file) state.file = c.file;
            if (c.status === 'done') {
                setStatus(marketplace() ? 'calc.status.done' : 'calc.status.done_facts', false);
                renderPrecise(c);
                enableDownload(c.file);
                if (!state.geometry && c.file?.stl_url) await showServerStl(c.file.stl_url);
                return;
            }
            if (c.status === 'failed') {
                setStatus('calc.status.failed', false);
                return;
            }
            setStatus(c.file && c.file.status !== 'ready' && !BROWSER_FORMATS.includes(c.file.ext) ? 'calc.status.converting' : 'calc.status.queued', true);
            state.pollTimer = window.setTimeout(tick, 1500);
        } catch {
            state.pollTimer = window.setTimeout(tick, 3000);
        }
    };
    tick();
}

async function showServerStl(url: string): Promise<void> {
    try {
        const geom = await loadGeometryFromUrl(url);
        state.geometry = geom;
        state.geo = stats(geom);
        viewer?.setGeometry(geom, state.params.scale, state.file?.kind ?? null);
    } catch { /* viewer is optional */ }
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

/** Ask the server for a precise calculation (debounced on slider moves). */
function requestPrecise(): void {
    if (!state.file) return;
    window.clearTimeout(state.debounce);
    state.debounce = window.setTimeout(async () => {
        try {
            setStatus('calc.status.queued', true);
            enableQuote(null);
            const c = await createCalculation({
                file: state.file!.uuid,
                ...state.params,
                supports: state.params.supports === null ? 'auto' : state.params.supports ? 1 : 0,
                geometry: state.geo ? { volume_mm3: state.geo.volume_mm3, area_mm2: state.geo.area_mm2 } : undefined,
            });
            state.calc = c;
            history.replaceState(null, '', c.url);
            if (c.status === 'done') {
                setStatus(marketplace() ? 'calc.status.done' : 'calc.status.done_facts', false);
                renderPrecise(c);
                enableDownload(c.file);
            } else {
                poll(c.token);
            }
        } catch {
            setStatus('calc.status.failed', false);
        }
    }, 700);
}

function onParamsChanged(): void {
    renderRough();
    viewer?.setScale(state.params.scale);
    refreshDownload();
    requestPrecise();
}

function showResult(): void {
    $('hero').classList.add('hidden');
    $('result').classList.remove('hidden');
    if (!viewer) viewer = new Viewer($('viewer') as HTMLCanvasElement);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function showHero(): void {
    stopPolling();
    state.file = null; state.calc = null; state.geometry = null; state.geo = null;
    $('result').classList.add('hidden');
    $('hero').classList.remove('hidden');
    history.replaceState(null, '', '/');
}

function heroError(msg: string | null): void {
    const el = $('hero-error');
    el.textContent = msg ?? '';
    el.classList.toggle('hidden', !msg);
}

async function handleFile(file: File): Promise<void> {
    heroError(null);
    const ext = extensionOf(file.name);
    if (!cfg.formats.includes(ext)) { heroError(t('calc.error.read')); return; }
    if (file.size > cfg.max_upload_mb * 1024 * 1024) { heroError(t('calc.error.too_big', { max: cfg.max_upload_mb })); return; }

    state.localFile = file;
    showKindTip(undefined);
    showResult();
    $('file-badge').textContent = file.name;
    setStatus('calc.status.reading', true);

    // 1) instant: parse in the browser, show model + rough price
    try {
        const geom = await loadGeometry(file);
        if (geom) {
            let s = stats(geom);
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

    // 2) background: upload → precise slice
    try {
        const info = await uploadFile(file, state.geo ? { volume_mm3: state.geo.volume_mm3, area_mm2: state.geo.area_mm2 } : undefined);
        state.file = info;
        requestPrecise();
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
        b.innerHTML = `${m.label} <span class="opacity-70 text-xs">${m.lay.map((l) => cfg.lay[l] ?? l).join(' · ')}</span>`;
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
    qty.onchange = () => { state.params.quantity = Math.max(1, Math.min(1000, Number(qty.value) || 1)); qty.value = String(state.params.quantity); onParamsChanged(); };
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
        if (navigator.share) { try { await navigator.share({ url }); } catch { /* cancelled */ } }
        const b = $('cta-share'); const old = b.textContent; b.textContent = t('calc.cta.copied'); setTimeout(() => (b.textContent = old), 1500);
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
    const plaque = () => box.querySelectorAll<HTMLElement>('[data-plaque]').forEach((e) => { e.classList.toggle('hidden', type.value !== 'plaque'); e.classList.toggle('block', type.value === 'plaque'); });
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
        const m = file.mold;
        const nf = new Intl.NumberFormat(document.documentElement.lang || 'cs', { maximumFractionDigits: 1 });
        report.textContent = [
            t('mold.report', { w: nf.format(m.box[0]), d: nf.format(m.box[1]), h: nf.format(m.box[2]), ml: nf.format(m.resin_ml), wall: m.wall }),
            m.warnings.includes('undercuts') ? t('mold.report.undercuts', { pct: nf.format(m.undercut_pct) }) : '',
            m.warnings.includes('large_mold') ? t('mold.report.large') : '',
        ].filter(Boolean).join(' ');
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

/** Tool-specific line under the viewer: how this kind of model is meant to be printed. */
function showKindTip(kind: string | undefined): void {
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
    else if (c.status === 'failed') { setStatus('calc.status.failed', false); renderRough(); }
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
            if (info.status === 'failed') { setStatus('calc.status.failed', false); return; }
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
    requestPrecise();
}

export function boot(): void {
    if (!document.getElementById('calculator')) return;
    bindControls();
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
        history.replaceState(null, '', '/');
        openFile(open);
    }
}
