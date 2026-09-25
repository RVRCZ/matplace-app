/**
 * Made-to-measure tools (organizer, box, phone stand, cable holder):
 * numbers → live solid from the server (exact, the same one that is exported) → orientation price → calculator / inquiry.
 */
import { STLLoader } from 'three/examples/jsm/loaders/STLLoader.js';
import { Viewer, Region, FILAMENT } from './viewer';
import { estimate, price, range, RoughConfig, Profile } from './rough';

interface Material { code: string; density: number }
interface Cfg {
    kind: string; preview: string; create: string; home: string; locale: string; artworkUrl: string; files: string; from: string | null;
    presets: Record<string, Record<string, number | string>>;
    config: { rough: RoughConfig; orientation_profiles: Profile[]; round_to: number; currency: string; materials: Material[] };
    i18n: Record<string, string>;
}
interface Hole { wall: string; shape: string; w: number; h: number; x: number; z: number }
interface Bin { x: number; y: number; w: number; h: number; color: string }
interface BomLine { size: string; w_mm: number; d_mm: number; color: string; count: number }
interface Meta { bbox: { x: number; y: number; z: number }; volume_mm3: number; area_mm2: number; notes: Record<string, unknown> }

export function bootParam(): void {
    const cfg = (window as unknown as { MP_PARAM?: Cfg }).MP_PARAM;
    const form = document.getElementById('param-form') as HTMLFormElement | null;
    const canvas = document.getElementById('param-viewer') as HTMLCanvasElement | null;
    if (!cfg || !form || !canvas) return;

    const t = (k: string, r: Record<string, string | number> = {}) => Object.entries(r).reduce((s, [a, b]) => s.split(`:${a}`).join(String(b)), cfg.i18n[k] ?? k);
    const $ = <T extends HTMLElement>(id: string) => document.getElementById(id) as T;
    const nf = new Intl.NumberFormat(cfg.locale, { maximumFractionDigits: 1 });
    const money = new Intl.NumberFormat(cfg.locale, { style: 'currency', currency: cfg.config.currency || 'CZK', maximumFractionDigits: 0 });
    const viewer = new Viewer(canvas);
    const holes: Hole[] = [];
    const bins: Bin[] = [];
    let binColor = 'blue'; let binStart: { x: number; y: number } | null = null; let binSelected = -1;
    let seq = 0; let timer = 0; let lastMeta: Meta | null = null; let valid = false;
    let artwork: string | null = null;      // uploaded SVG / picture reference
    let viewPart = 'all';                   // which part the preview shows

    const params = (): Record<string, unknown> => {
        const p: Record<string, unknown> = {};
        form.querySelectorAll<HTMLInputElement>('[data-param]').forEach((i) => { p[i.dataset.param!] = Number(i.value); });
        form.querySelectorAll<HTMLInputElement>('[data-flag]').forEach((i) => { p[i.dataset.flag!] = i.checked; });
        form.querySelectorAll<HTMLInputElement>('[data-choice]:checked').forEach((i) => { p[i.dataset.choice!] = i.value; });
        form.querySelectorAll<HTMLInputElement>('[data-text]').forEach((i) => { p[i.dataset.text!] = i.value; });
        if (artwork) p.artwork = artwork;
        if (cfg.kind === 'box') p.holes = holes;
        if (cfg.kind === 'modular') p.bins = bins;
        return p;
    };

    /** Browser-side range check: marks the field, the server checks again. */
    const fieldsOk = (): boolean => {
        let ok = true;
        form.querySelectorAll<HTMLInputElement>('[data-param]').forEach((i) => {
            const v = Number(i.value);
            const bad = i.value === '' || Number.isNaN(v) || v < Number(i.min) || v > Number(i.max);
            i.setAttribute('aria-invalid', bad ? 'true' : 'false');
            i.classList.toggle('border-red-600', bad);
            if (bad) ok = false;
        });
        return ok;
    };

    const showError = (msg: string | null): void => {
        const el = $('param-error');
        el.textContent = msg ?? '';
        el.classList.toggle('hidden', !msg);
        ($('param-go') as HTMLButtonElement).disabled = !!msg;
    };

    const renderDims = (m: Meta): void => {
        const n = m.notes as { outer?: number[]; inner?: number[]; cell?: number[]; slot?: number };
        const rows: [string, string][] = [];
        const dims = (a: number[]) => `${a.map((v) => nf.format(v)).join(' × ')} mm`;
        if (n.outer) rows.push([t('param.outer'), dims(n.outer)]);
        if (n.inner) rows.push([t('param.inner'), dims(n.inner)]);
        if (n.cell) rows.push([t('param.cell'), dims(n.cell)]);
        if (n.slot) rows.push([t('param.slot'), `${nf.format(n.slot)} mm`]);
        $('param-dims').innerHTML = rows.map(([k, v]) => `<div class="flex justify-between gap-3"><dt class="text-muted">${k}</dt><dd class="font-semibold text-ink">${v}</dd></div>`).join('');
    };

    const renderPrice = (): void => {
        if (!lastMeta) return;
        const code = ($('param-material') as HTMLSelectElement).value;
        const qty = Math.max(1, Math.min(1000, Number(($('param-qty') as HTMLInputElement).value) || 1));
        const density = cfg.config.materials.find((m) => m.code === code)?.density ?? 1.24;
        // a plain vase is priced the way it will be printed: one spiralled wall, no infill (the calculator gets the same hint)
        const vaseMode = cfg.kind === 'vase' && (params().purpose ?? 'vase') === 'vase';
        const est = estimate(cfg.config.rough, density, { volume_mm3: lastMeta.volume_mm3, area_mm2: lastMeta.area_mm2 }, { material: code, quality: 'standard', infill: 15, supports: false, scale: 1, quantity: qty, vase: vaseMode });
        const totals = cfg.config.orientation_profiles.map((p) => price(cfg.config.round_to, p, est.grams, est.minutes, qty).total);
        const [lo, hi] = range(cfg.config.rough, cfg.config.round_to, totals, true);
        $('param-price').textContent = hi > 0 ? `≈ ${money.format(lo)} – ${money.format(hi)}` : '—';
        const h = Math.floor((est.minutes * qty) / 60); const min = Math.round((est.minutes * qty) % 60);
        $('param-price-sub').textContent = t('param.estimate', { g: nf.format(est.grams * qty), t: h ? `${h} h ${min} min` : `${min} min`, q: qty });
    };

    /** Separately printed parts of the current design (mirrors UploadController::partsOf). */
    const partsNow = (): string[] => {
        const p = params();
        if (cfg.kind === 'box' && p.lid) return ['body', 'lid'];
        if (cfg.kind === 'vase' && p.purpose === 'pot' && p.saucer) return ['body', 'saucer'];
        if (cfg.kind === 'stamp' && p.handle === 'knob') return ['body', 'handle'];
        if (cfg.kind === 'logo' && p.mode === 'standing') return ['body', 'stand'];
        if (cfg.kind === 'qr' && p.stand) return ['body', 'stand'];
        if (cfg.kind === 'lightbox') return ['body', 'face', 'diffuser', 'back'];
        if (cfg.kind === 'modular') return [...(p.tray ? ['tray'] : []), ...new Set(bins.map((b) => `bin_${b.w}x${b.h}`))];
        return [];
    };

    /** "body" and "stand" mean different things per product: the box, the logo, the sign… */
    const partLabel = (v: string): string => {
        if (v.startsWith('bin_')) return t('param.part.bin', { s: v.slice(4).replace('x', ' × ') });
        const own = `param.part.${v}.${cfg.kind}`;
        return cfg.i18n[own] ? t(own) : t(`param.part.${v}`);
    };

    /** Assembly / single parts / (stamp) the imprint it leaves: buttons over the viewer. */
    const renderViews = (): void => {
        const box = document.getElementById('param-views');
        if (!box) return;
        const views = ['all', ...partsNow(), ...(cfg.kind === 'stamp' ? ['imprint'] : [])];
        if (!views.includes(viewPart)) viewPart = 'all';
        box.innerHTML = '';
        if (views.length < 2) return;
        views.forEach((v) => {
            const b = document.createElement('button');
            b.type = 'button'; b.className = `chip !py-1 text-xs ${v === viewPart ? 'chip-on' : ''}`; b.textContent = partLabel(v);
            b.setAttribute('aria-pressed', v === viewPart ? 'true' : 'false');
            b.onclick = () => { viewPart = v; refresh(); };
            box.appendChild(b);
        });
    };

    /** Bill of parts of a modular set: what gets printed, how many times and in which colour. */
    const bomText = (m: Meta): string[] => ((m.notes as { bom?: BomLine[] }).bom ?? []).map((b) => t('param.bom.line', { n: b.count, s: b.size.replace('x', ' × '), w: nf.format(b.w_mm), d: nf.format(b.d_mm), c: t(`color.${b.color}`) }));
    const renderBom = (m: Meta): void => {
        const el = document.getElementById('param-bom'); if (!el) return;
        const n = m.notes as { bom?: BomLine[]; unit?: number[]; free_cells?: number; tray_size?: number[] };
        if (!n.bom) { el.classList.add('hidden'); return; }
        const lines = bomText(m);
        if (n.tray_size) lines.unshift(`1 × ${t('param.part.tray')} ${n.tray_size.map((v) => nf.format(v)).join(' × ')} mm`);
        el.innerHTML = `<div class="font-bold text-ink">${t('param.bom')}</div><ul class="mt-1 list-disc space-y-0.5 pl-5 text-ink">${lines.map((l) => `<li>${l}</li>`).join('')}</ul>`
            + `<p class="mt-2 text-muted">${t('param.unit', { x: nf.format(n.unit?.[0] ?? 0), y: nf.format(n.unit?.[1] ?? 0) })}${n.free_cells ? ` ${t('param.bins.free', { n: n.free_cells })}` : ''}</p>`;
        el.classList.remove('hidden');
    };

    const renderWarnings = (m: Meta): void => {
        const n = m.notes as { warnings?: string[]; needs?: string[]; missing_chars?: string[]; thin_pct?: number; pieces?: number; module_mm?: number; modules?: number; quiet_zone_mm?: number; saucer_d?: number; drainage_holes?: number };
        const out: string[] = (n.warnings ?? []).map((w) => t(`param.warn.${w}`, { n: n.thin_pct ?? 0, c: (n.missing_chars ?? []).join(' '), p: n.pieces ?? 0 }));
        if (n.module_mm) out.push(t('param.qr.facts', { m: nf.format(n.module_mm), q: nf.format(n.quiet_zone_mm ?? 0), c: n.modules ?? 0 }));
        const lb = m.notes as { led_m?: number; bridges?: number };
        if (lb.bridges) out.push(t('param.bridges', { n: lb.bridges }));
        if (lb.led_m) out.push(t('param.lightbox.led', { m: nf.format(lb.led_m) }));
        if (n.saucer_d) out.push(t('param.saucer', { d: nf.format(n.saucer_d), h: n.drainage_holes ?? 0 }));
        if ((n.needs ?? []).length) out.push(`${t('param.needs')}: ${(n.needs ?? []).map((x) => t(`param.need.${x}`)).join(', ')}`);
        const el = $('param-warnings');
        el.innerHTML = out.map((o) => `<li>${o.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c] as string))}</li>`).join('');
        el.classList.toggle('hidden', out.length === 0);
    };

    const post = (body: Record<string, unknown>) => fetch(cfg.preview, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body) });
    const firstError = (b: { message?: string; errors?: Record<string, string[]> }) => Object.values(b.errors ?? {})[0]?.[0] ?? b.message ?? t('param.failed');

    const refresh = async (): Promise<void> => {
        if (!fieldsOk()) { valid = false; ($('param-go') as HTMLButtonElement).disabled = true; return; }
        const mine = ++seq;
        $('param-busy').classList.remove('hidden');
        try {
            renderViews();
            const res = await post({ kind: cfg.kind, params: params(), view: 'use', part: viewPart });
            if (mine !== seq) return;                       // a newer change is already on its way
            if (!res.ok) { valid = false; showError(firstError(await res.json())); return; }
            lastMeta = JSON.parse(res.headers.get('X-Model-Meta') ?? 'null');
            const regions = viewPart === 'all' ? ((lastMeta?.notes as { regions?: Region[] } | undefined)?.regions ?? null) : null;
            viewer.setGeometry(new STLLoader().parse(await res.arrayBuffer()), 1, cfg.kind, regions);
            valid = true; showError(null);
            if (lastMeta) { renderDims(lastMeta); renderWarnings(lastMeta); renderBom(lastMeta); if (viewPart === 'all') renderPrice(); }
            renderDownloads();
        } catch {
            if (mine === seq) { valid = false; showError(t('param.failed')); }
        } finally {
            if (mine === seq) $('param-busy').classList.add('hidden');
        }
    };
    const soon = (): void => { window.clearTimeout(timer); timer = window.setTimeout(refresh, 350); };

    /** Separate exports: whole plate, and for a box with a lid also the box and the lid alone. */
    const renderDownloads = (): void => {
        const parts = ['all', ...partsNow()];
        const box = $('param-downloads');
        box.innerHTML = '';
        parts.forEach((part) => {
            const b = document.createElement('button');
            b.type = 'button'; b.className = 'btn-quiet text-sm'; b.textContent = `${partLabel(part)} (.stl)`;
            b.onclick = async () => {
                if (!valid) return;
                b.disabled = true;
                try {
                    const res = await post({ kind: cfg.kind, params: params(), part, download: true });
                    if (!res.ok) { showError(firstError(await res.json())); return; }
                    const url = URL.createObjectURL(await res.blob());
                    const a = document.createElement('a');
                    a.href = url; a.download = `${cfg.kind.replace('_', '-')}${part === 'all' ? '' : `-${part}`}.stl`;
                    document.body.appendChild(a); a.click(); a.remove();
                    setTimeout(() => URL.revokeObjectURL(url), 5000);
                } finally { b.disabled = false; }
            };
            box.appendChild(b);
        });
    };

    // ── openings in the box walls ──────────────────────────────────────────
    const renderHoles = (): void => {
        const list = document.getElementById('holes');
        if (!list) return;
        list.innerHTML = '';
        holes.forEach((h, i) => {
            const row = document.createElement('div');
            row.className = 'grid grid-cols-2 gap-2 rounded-xl border border-line p-2 sm:grid-cols-6';
            const sel = (key: 'wall' | 'shape', opts: string[], prefix: string) => `<label class="text-xs text-muted">${t(`param.hole.${key === 'wall' ? 'x' : 'w'}`) && ''}<select data-h="${key}" class="field !mt-0 !min-h-10 text-sm">${opts.map((o) => `<option value="${o}" ${h[key] === o ? 'selected' : ''}>${t(`${prefix}.${o}`)}</option>`).join('')}</select></label>`;
            const numIn = (key: 'w' | 'h' | 'x' | 'z', label: string, hidden = false) => `<label class="text-xs text-muted ${hidden ? 'hidden' : ''}">${label}<input data-h="${key}" type="number" inputmode="decimal" min="0" step="0.5" value="${h[key]}" class="field !mt-0 !min-h-10 text-sm"></label>`;
            row.innerHTML = `<span class="col-span-2 text-sm font-semibold text-ink sm:col-span-6">${t('param.hole')} ${i + 1} <button type="button" data-h="remove" class="ml-2 text-sm font-normal text-action-dark underline">${t('param.hole.remove')}</button></span>`
                + sel('wall', ['front', 'back', 'left', 'right'], 'param.wall') + sel('shape', ['circle', 'rect'], 'param.shape')
                + numIn('w', h.shape === 'circle' ? t('param.hole.d') : t('param.hole.w')) + numIn('h', t('param.hole.h'), h.shape === 'circle')
                + numIn('x', t('param.hole.x')) + numIn('z', t('param.hole.z'));
            row.querySelectorAll<HTMLInputElement | HTMLSelectElement>('[data-h]').forEach((inp) => {
                const key = inp.dataset.h!;
                if (key === 'remove') { (inp as unknown as HTMLButtonElement).onclick = () => { holes.splice(i, 1); renderHoles(); soon(); }; return; }
                inp.onchange = () => {
                    if (key === 'wall' || key === 'shape') { h[key] = inp.value; renderHoles(); } else { (h as unknown as Record<string, number>)[key] = Number(inp.value); }
                    soon();
                };
            });
            list.appendChild(row);
        });
    };
    const addHole = document.getElementById('add-hole');
    if (addHole) {
        addHole.onclick = () => {
            if (holes.length >= 8) { showError(t('param.too_many_holes')); return; }
            const p = params() as { inner_w: number; inner_h: number };
            holes.push({ wall: 'front', shape: 'circle', w: 8, h: 8, x: Math.round(p.inner_w / 2), z: Math.round(Math.max(6, p.inner_h / 2 - 2)) });
            renderHoles(); soon();
        };
    }

    /** Fill the form from a preset or from a stored design. */
    const applyValues = (set: Record<string, unknown>): void => {
        Object.entries(set).forEach(([k, v]) => {
            const num = form.querySelector<HTMLInputElement>(`[data-param="${k}"]`); if (num) num.value = String(v);
            const txt = form.querySelector<HTMLInputElement>(`[data-text="${k}"]`); if (txt) txt.value = String(v ?? '');
            const flag = form.querySelector<HTMLInputElement>(`[data-flag="${k}"]`); if (flag) flag.checked = Boolean(v);
            const choice = form.querySelector<HTMLInputElement>(`[data-choice="${k}"][value="${String(v)}"]`); if (choice) choice.checked = true;
        });
        if (Array.isArray(set.holes)) { holes.splice(0, holes.length, ...(set.holes as Hole[])); renderHoles(); }
        if (Array.isArray(set.bins)) { bins.splice(0, bins.length, ...(set.bins as Bin[])); renderGrid(); }
        if (typeof set.artwork === 'string') { artwork = set.artwork; artworkState(t('param.artwork.remove'), true); }
    };

    // ── uploaded artwork (SVG or a simple picture) ─────────────────────────
    const artInput = document.getElementById('param-artwork') as HTMLInputElement | null;
    const artworkState = (text: string, removable = false): void => {
        const el = document.getElementById('param-artwork-state'); if (!el) return;
        el.textContent = '';
        if (!removable) { el.textContent = text; return; }
        const b = document.createElement('button'); b.type = 'button'; b.className = 'font-semibold text-action-dark underline'; b.textContent = text;
        b.onclick = () => { artwork = null; if (artInput) artInput.value = ''; artworkState(''); refresh(); };
        el.appendChild(b);
    };
    if (artInput) {
        artInput.onchange = async () => {
            const f = artInput.files?.[0]; if (!f) return;
            artworkState(t('param.artwork.uploading'));
            try {
                const fd = new FormData(); fd.append('file', f);
                const res = await fetch(cfg.artworkUrl, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body: fd });
                const body = await res.json();
                if (!res.ok) { artwork = null; artworkState(firstError(body)); return; }
                artwork = body.artwork; artworkState(t('param.artwork.remove'), true); refresh();
            } catch { artwork = null; artworkState(t('param.artwork.failed')); }
        };
    }

    // ── modular organizer: bins on the customer's own grid ─────────────────
    const grid = document.getElementById('bin-grid');
    const gridSize = (): [number, number] => {
        const v = (k: string, d: number) => Math.max(1, Math.min(12, Math.round(Number(form.querySelector<HTMLInputElement>(`[data-param="${k}"]`)?.value) || d)));
        return [v('cols', 6), v('rows', 3)];
    };
    const binAt = (x: number, y: number): number => bins.findIndex((b) => x >= b.x && x < b.x + b.w && y >= b.y && y < b.y + b.h);
    const say = (k: string, r: Record<string, string | number> = {}): void => { const el = document.getElementById('bins-state'); if (el) el.textContent = k ? t(k, r) : ''; };
    const css = (c: string): string => { const f = FILAMENT[c] ?? FILAMENT.white; return `rgb(${f.map((v) => Math.round(v * 255)).join(',')})`; };
    const renderGrid = (): void => {
        if (!grid) return;
        const [cols, rows] = gridSize();
        for (let i = bins.length - 1; i >= 0; i--) if (bins[i].x + bins[i].w > cols || bins[i].y + bins[i].h > rows) bins.splice(i, 1);   // the grid shrank
        if (binSelected >= bins.length) binSelected = -1;
        grid.style.gridTemplateColumns = `repeat(${cols}, minmax(0, 1fr))`;
        grid.innerHTML = '';
        for (let y = rows - 1; y >= 0; y--) {                                   // row 0 is the front of the drawer: drawn at the bottom
            for (let x = 0; x < cols; x++) {
                const i = binAt(x, y);
                const cell = document.createElement('button');
                cell.type = 'button'; cell.setAttribute('role', 'gridcell');
                cell.className = 'aspect-square min-h-8 rounded-md border text-xs font-semibold';
                if (i >= 0) {
                    const b = bins[i];
                    cell.style.background = css(b.color); cell.style.color = ['white', 'yellow', 'grey'].includes(b.color) ? '#172B4D' : '#fff';
                    cell.style.borderColor = i === binSelected ? '#C94714' : 'transparent'; cell.style.borderWidth = i === binSelected ? '3px' : '1px';
                    cell.textContent = x === b.x && y === b.y + b.h - 1 ? `${b.w}×${b.h}` : '';
                    cell.setAttribute('aria-label', t('param.bins.bin', { s: `${b.w} × ${b.h}`, c: t(`color.${b.color}`) }));
                } else {
                    const start = binStart && binStart.x === x && binStart.y === y;
                    cell.className += start ? ' border-action bg-action-soft' : ' border-dashed border-slate-300 bg-white';
                    cell.setAttribute('aria-label', t('param.bins.empty', { x: x + 1, y: y + 1 }));
                }
                cell.onclick = () => clickCell(x, y);
                grid.appendChild(cell);
            }
        }
    };
    /** Two taps make a bin: the first corner, then the opposite one. A tap on a bin selects it (colour, remove). */
    const clickCell = (x: number, y: number): void => {
        const hit = binAt(x, y);
        if (hit >= 0 && !binStart) { binSelected = hit === binSelected ? -1 : hit; if (binSelected >= 0) binColor = bins[hit].color; renderColors(); renderGrid(); return; }
        if (!binStart) { binStart = { x, y }; binSelected = -1; say('param.bins.pick_end'); renderGrid(); return; }
        const nb = { x: Math.min(binStart.x, x), y: Math.min(binStart.y, y), w: Math.abs(binStart.x - x) + 1, h: Math.abs(binStart.y - y) + 1, color: binColor };
        binStart = null;
        const clash = bins.some((b) => nb.x < b.x + b.w && nb.x + nb.w > b.x && nb.y < b.y + b.h && nb.y + nb.h > b.y);
        if (clash) { say('param.bins.taken'); renderGrid(); return; }
        if (bins.length >= 24) { renderGrid(); return; }
        bins.push(nb); say(''); renderGrid(); soon();
    };
    const renderColors = (): void => {
        const box = document.getElementById('bin-colors'); if (!box) return;
        box.innerHTML = '';
        Object.keys(FILAMENT).forEach((c) => {
            const b = document.createElement('button');
            b.type = 'button'; b.setAttribute('role', 'radio'); b.setAttribute('aria-checked', c === binColor ? 'true' : 'false'); b.setAttribute('aria-label', t(`color.${c}`)); b.title = t(`color.${c}`);
            b.className = 'h-9 w-9 rounded-full border-2'; b.style.background = css(c); b.style.borderColor = c === binColor ? '#C94714' : '#DDE2EA';
            b.onclick = () => { binColor = c; if (binSelected >= 0) { bins[binSelected].color = c; renderGrid(); soon(); } renderColors(); };
            box.appendChild(b);
        });
    };
    if (grid) {
        // a sensible start: a long tray for pens at the back, small bins in front (like a desk drawer)
        bins.push({ x: 0, y: 2, w: 4, h: 1, color: 'blue' }, { x: 4, y: 1, w: 2, h: 2, color: 'orange' }, { x: 0, y: 0, w: 2, h: 2, color: 'white' },
            { x: 2, y: 0, w: 2, h: 2, color: 'blue' }, { x: 4, y: 0, w: 2, h: 1, color: 'white' });
        document.getElementById('bins-fill')!.onclick = () => {
            const [cols, rows] = gridSize();
            for (let y = 0; y < rows; y++) for (let x = 0; x < cols; x++) if (binAt(x, y) < 0 && bins.length < 24) bins.push({ x, y, w: 1, h: 1, color: binColor });
            renderGrid(); soon();
        };
        document.getElementById('bins-remove')!.onclick = () => { if (binSelected >= 0) { bins.splice(binSelected, 1); binSelected = -1; renderGrid(); soon(); } };
        document.getElementById('bins-clear')!.onclick = () => { bins.splice(0, bins.length); binSelected = -1; binStart = null; renderGrid(); soon(); };
        form.querySelectorAll<HTMLInputElement>('[data-param="cols"],[data-param="rows"]').forEach((i) => i.addEventListener('input', renderGrid));
        renderColors(); renderGrid();
    }

    // ── presets, inputs, order choices ─────────────────────────────────────
    form.querySelectorAll<HTMLButtonElement>('[data-preset]').forEach((b) => {
        b.onclick = () => {
            applyValues(cfg.presets[b.dataset.preset!] ?? {});
            form.querySelectorAll('[data-preset]').forEach((o) => o.classList.toggle('chip-on', o === b));
            refresh();
        };
    });
    // fields and flags that belong to one choice only ("data-when=style=desk,wedge") hide for the other choices
    const applyWhen = (): void => {
        form.querySelectorAll<HTMLElement>('[data-when]').forEach((el) => {
            const [key, list] = (el.dataset.when ?? '').split('=');
            const current = form.querySelector<HTMLInputElement>(`[data-choice="${key}"]:checked`)?.value ?? '';
            el.classList.toggle('hidden', !list.split(',').includes(current));
        });
    };
    applyWhen();
    form.addEventListener('input', (e) => { if ((e.target as HTMLElement).closest('#holes')) return; applyWhen(); soon(); renderPrice(); });
    form.addEventListener('submit', (e) => e.preventDefault());

    // the design is saved (our own geometry, free) and opens in the calculation; "download" opens the printer picker there
    const save = async (go: HTMLButtonElement, download: boolean): Promise<void> => {
        if (!valid) return;
        const label = go.textContent;
        go.disabled = true; go.textContent = t('param.creating');
        try {
            const res = await fetch(cfg.create, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ kind: cfg.kind, params: params() }) });
            const body = await res.json();
            if (!res.ok) { showError(firstError(body)); go.disabled = false; go.textContent = label; return; }
            const bomNote = lastMeta ? bomText(lastMeta).join('; ') : '';
            const q = new URLSearchParams({ ...(bomNote ? { note: bomNote.slice(0, 900) } : {}), open: body.file.uuid, material: ($('param-material') as HTMLSelectElement).value, quantity: ($('param-qty') as HTMLInputElement).value || '1', color: ($('param-color') as HTMLSelectElement).value, ...(download ? { download: '1' } : {}) });
            location.href = `${cfg.home}?${q}`;
        } catch {
            showError(t('param.failed')); go.disabled = false; go.textContent = label;
        }
    };

    ($('param-go') as HTMLButtonElement).onclick = (e) => save(e.currentTarget as HTMLButtonElement, false);
    const project = document.getElementById('param-3mf') as HTMLButtonElement | null;
    if (project) project.onclick = () => save(project, true);

    // reopen a stored design ("edit" from the calculator): same numbers, same artwork
    if (cfg.from && /^[0-9a-f-]{36}$/.test(cfg.from)) {
        fetch(`${cfg.files}/${cfg.from}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : null))
            .then((b) => { if (b?.file?.tool?.kind === cfg.kind) applyValues(b.file.tool.params); })
            .catch(() => undefined)
            .finally(refresh);
    } else {
        refresh();
    }
}
