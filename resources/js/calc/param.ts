/**
 * Made-to-measure tools (organizer, box, phone stand, cable holder):
 * numbers → live solid from the server (exact, the same one that is exported) → orientation price → calculator / inquiry.
 */
import { STLLoader } from 'three/examples/jsm/loaders/STLLoader.js';
import { Viewer } from './viewer';
import { estimate, price, range, RoughConfig, Profile } from './rough';

interface Material { code: string; density: number }
interface Cfg {
    kind: string; preview: string; create: string; home: string; locale: string; artworkUrl: string; files: string; from: string | null;
    presets: Record<string, Record<string, number | string>>;
    config: { rough: RoughConfig; orientation_profiles: Profile[]; round_to: number; currency: string; materials: Material[] };
    i18n: Record<string, string>;
}
interface Hole { wall: string; shape: string; w: number; h: number; x: number; z: number }
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
        const est = estimate(cfg.config.rough, density, { volume_mm3: lastMeta.volume_mm3, area_mm2: lastMeta.area_mm2 }, { material: code, quality: 'standard', infill: 15, supports: false, scale: 1, quantity: qty });
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
        if (cfg.kind === 'qr' && p.stand) return ['body', 'stand'];
        if (cfg.kind === 'lightbox') return ['body', 'face', 'diffuser', 'back'];
        return [];
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
            b.type = 'button'; b.className = `chip !py-1 text-xs ${v === viewPart ? 'chip-on' : ''}`; b.textContent = t(`param.part.${v}`);
            b.setAttribute('aria-pressed', v === viewPart ? 'true' : 'false');
            b.onclick = () => { viewPart = v; refresh(); };
            box.appendChild(b);
        });
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
            viewer.setGeometry(new STLLoader().parse(await res.arrayBuffer()), 1, cfg.kind);
            valid = true; showError(null);
            if (lastMeta) { renderDims(lastMeta); renderWarnings(lastMeta); if (viewPart === 'all') renderPrice(); }
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
            b.type = 'button'; b.className = 'btn-quiet text-sm'; b.textContent = `${t(`param.part.${part}`)} (.stl)`;
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

    // ── presets, inputs, order choices ─────────────────────────────────────
    form.querySelectorAll<HTMLButtonElement>('[data-preset]').forEach((b) => {
        b.onclick = () => {
            applyValues(cfg.presets[b.dataset.preset!] ?? {});
            form.querySelectorAll('[data-preset]').forEach((o) => o.classList.toggle('chip-on', o === b));
            refresh();
        };
    });
    form.addEventListener('input', (e) => { if ((e.target as HTMLElement).closest('#holes')) return; soon(); renderPrice(); });
    form.addEventListener('submit', (e) => e.preventDefault());

    ($('param-go') as HTMLButtonElement).onclick = async () => {
        if (!valid) return;
        const go = $('param-go') as HTMLButtonElement; const label = go.textContent;
        go.disabled = true; go.textContent = t('param.creating');
        try {
            const res = await fetch(cfg.create, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ kind: cfg.kind, params: params() }) });
            const body = await res.json();
            if (!res.ok) { showError(firstError(body)); go.textContent = label; return; }
            const q = new URLSearchParams({ open: body.file.uuid, material: ($('param-material') as HTMLSelectElement).value, quantity: ($('param-qty') as HTMLInputElement).value || '1', color: ($('param-color') as HTMLSelectElement).value });
            location.href = `${cfg.home}?${q}`;
        } catch {
            showError(t('param.failed')); go.disabled = false; go.textContent = label;
        }
    };

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
