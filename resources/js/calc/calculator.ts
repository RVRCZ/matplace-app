import { BufferGeometry } from 'three';
import { Viewer } from './viewer';
import { loadGeometry, loadGeometryFromUrl, extensionOf, BROWSER_FORMATS } from './loaders';
import { stats, normaliseUnits, GeoStats } from './geometry';
import { estimate, price, range, RoughConfig, Profile, Params } from './rough';
import { uploadFile, createCalculation, getCalculation, getFile, CalcInfo, FileInfo } from './api';
import { bootInquiry } from './inquiry';

const routes = () => (window as unknown as { MP_ROUTES: Record<string, string> }).MP_ROUTES;

interface MaterialCfg { code: string; density: number; lay: string[]; sliceable: boolean; label: string; hint: string }
interface Config {
    rough: RoughConfig; orientation_profiles: Profile[]; round_to: number; currency: string; max_scale: number;
    bed_mm: { x: number; y: number; z: number }; materials: MaterialCfg[]; default_material: string;
    formats: string[]; max_upload_mb: number; lay: Record<string, string>;
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

/** Rough numbers from browser geometry — under a second, on every slider move. */
function renderRough(): void {
    const g = state.geo ?? (state.file && state.file.volume_mm3 != null ? { volume_mm3: state.file.volume_mm3, area_mm2: state.file.area_mm2, bbox: state.file.bbox!, triangles: 0 } : null);
    if (!g) return;
    const est = estimate(cfg.rough, material(state.params.material).density, { volume_mm3: g.volume_mm3, area_mm2: g.area_mm2 }, state.params);
    const bds = cfg.orientation_profiles.map((p) => price(cfg.round_to, p, est.grams, est.minutes, state.params.quantity));
    const [lo, hi] = range(cfg.rough, cfg.round_to, bds.map((b) => b.total), true);
    $('price-main').textContent = lo === hi ? fmt.format(lo) : `${fmt.format(lo)} – ${fmt.format(hi)}`;
    $('price-sub').textContent = state.params.quantity > 1 ? `${t('calc.price.per_piece')} ≈ ${fmt.format(Math.round(lo / state.params.quantity))} – ${fmt.format(Math.round(hi / state.params.quantity))}` : '';
    $('stat-grams').textContent = `≈ ${fmt.format(est.grams * state.params.quantity)} g`;
    $('stat-time').textContent = `≈ ${minutesText(est.minutes * state.params.quantity)}`;
    const leads = bds.map((b) => b.lead_time_days);
    $('stat-lead').textContent = t('calc.days', { n: leadRange(leads) });
    const s = state.params.scale;
    $('dims-badge').textContent = `${fmt.format(g.bbox.x * s)} × ${fmt.format(g.bbox.y * s)} × ${fmt.format(g.bbox.z * s)} mm`;
    renderBreakdown(bds.map((b) => ({ ...b, label: t(`calc.profile.${b.profile}`) })), true);
    if (!material(state.params.material).sliceable) $('price-sub').textContent = t('calc.est_only');
}

function renderBreakdown(bds: { profile: string; label?: string | null; unit: { material: number; time: number }; setup: number; subtotal?: number; discount?: number; margin?: number; total: number; lead_time_days: number }[], rough: boolean): void {
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
            <div class="flex justify-between font-semibold"><span>${b.label ?? t(`calc.profile.${b.profile}`)}</span><span>${rough ? '≈ ' : ''}${fmt.format(b.total)}</span></div>
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
    const warns = [...(c.slicer.warnings ?? []), ...(c.file?.issues ?? [])];
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
                setStatus('calc.status.done', false);
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
    const a = $('cta-download') as HTMLAnchorElement;
    if (file?.stl_url) {
        a.href = file.stl_url;
        a.setAttribute('download', file.name.replace(/\.[^.]+$/, '') + '.stl');
        a.setAttribute('aria-disabled', 'false');
    }
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
                setStatus('calc.status.done', false);
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
    scale.oninput = () => { state.params.scale = Number(scale.value) / 100; $('scale-val').textContent = `${scale.value} %`; onParamsChanged(); };

    const input = $('file-input') as HTMLInputElement;
    input.onchange = () => { if (input.files?.[0]) handleFile(input.files[0]); input.value = ''; };
    const drop = $('dropzone');
    drop.ondragover = (e) => { e.preventDefault(); drop.classList.add('border-teal-500', 'bg-teal-50'); };
    drop.ondragleave = () => drop.classList.remove('border-teal-500', 'bg-teal-50');
    drop.ondrop = (e) => { e.preventDefault(); drop.classList.remove('border-teal-500', 'bg-teal-50'); const f = e.dataTransfer?.files?.[0]; if (f) handleFile(f); };
    document.body.addEventListener('dragover', (e) => e.preventDefault());
    document.body.addEventListener('drop', (e) => { e.preventDefault(); const f = e.dataTransfer?.files?.[0]; if (f && $('hero').classList.contains('hidden') === false) handleFile(f); });

    $('cta-new').onclick = showHero;
    $('cta-share').onclick = async () => {
        const url = state.calc?.url ?? location.href;
        try { await navigator.clipboard.writeText(url); } catch { /* ignore */ }
        if (navigator.share) { try { await navigator.share({ url }); } catch { /* cancelled */ } }
        const b = $('cta-share'); const old = b.textContent; b.textContent = t('calc.cta.copied'); setTimeout(() => (b.textContent = old), 1500);
    };
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

/** Tool-specific line under the viewer: how this kind of model is meant to be printed. */
function showKindTip(kind: string | undefined): void {
    const el = document.getElementById('kind-tip');
    if (!el) return;
    const key = `calc.tip.${kind ?? ''}`;
    const text = t(key);
    el.textContent = text === key ? '' : text;
    el.classList.toggle('hidden', text === key);
    showRefine(kind === 'generated' ? state.file : null);
}

async function restore(c: CalcInfo): Promise<void> {
    const p = c.params as Record<string, unknown>;
    state.params = {
        material: String(p.material ?? cfg.default_material), quality: String(p.quality ?? 'standard'), infill: Number(p.infill ?? 15),
        supports: p.supports === null || p.supports === undefined ? null : Boolean(p.supports), scale: Number(p.scale ?? 1), quantity: Number(p.quantity ?? 1),
    };
    ($('infill') as HTMLInputElement).value = String(state.params.infill); $('infill-val').textContent = `${state.params.infill} %`;
    ($('quantity') as HTMLInputElement).value = String(state.params.quantity);
    ($('scale') as HTMLInputElement).value = String(Math.round(state.params.scale * 100)); $('scale-val').textContent = `${Math.round(state.params.scale * 100)} %`;
    $('quality').querySelectorAll<HTMLElement>('.seg').forEach((s) => s.classList.toggle('seg-on', s.dataset.quality === state.params.quality));
    buildMaterials();
    state.calc = c; state.file = c.file;
    syncControls();
    showKindTip(c.file?.kind);
    showResult();
    $('file-badge').textContent = c.file?.name ?? '';
    if (c.file?.stl_url) await showServerStl(c.file.stl_url);
    if (c.status === 'done') { setStatus('calc.status.done', false); renderPrecise(c); enableDownload(c.file); }
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
        syncControls();
    }
    showKindTip(state.file?.kind);
    if (state.file?.stl_url) await showServerStl(state.file.stl_url);
    renderRough();
    requestPrecise();
}

export function boot(): void {
    if (!document.getElementById('calculator')) return;
    bindControls();
    if (initial) { restore(initial); return; }
    const open = new URLSearchParams(location.search).get('open');
    if (open && /^[0-9a-f-]{36}$/.test(open)) { history.replaceState(null, '', '/'); openFile(open); }
}
