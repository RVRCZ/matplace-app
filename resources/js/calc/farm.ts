/**
 * Print farm ("Rent a printer").
 *   bootFarmCta    calculator: the button takes the shared calculation (/c/{token} in the address) to /farm
 *   bootFarmStart  /farm: upload an STL when the customer did not come from the calculator
 *   bootFarmOrder  /farm/orders/{token}: preview of the print pose, presets, colours, pay, progress (polled)
 *   bootFarmDashboard  /admin/farm: buttons post over fetch, the answer is a short toast, cards redraw in place
 */
import { BufferGeometry } from 'three';
import { Viewer, FacePaint } from './viewer';
import { loadGeometryFromUrl } from './loaders';

interface Price { time: number; material: number; fixed: number; min_price_applied: boolean; net: number; vat: number; shipping: number; total: number; print_total: number; inputs: { vat_percent: number } }
interface Color { slot: number; name: string; kind?: string; hex: string; photo: string | null; enough: boolean; price: Price; total: number; starts_now: boolean; sliced: boolean; second?: Second[] }
interface Second { slot: number; name: string; kind?: string; hex: string; photo: string | null }
const UNIT_MM: Record<string, number> = { mm: 1, cm: 10, in: 25.4, m: 1000 };
const clampScale = (v: number, max: number) => Math.max(0.25, Math.min(max || 4, v));

interface FarmState {
    token: string; number: string | null; status: string; status_text: string; stage: string | null; stage_step: number | null; stage_total: number; error: string | null; error_text: string | null;
    quality: string; strength: string; copies: number; max_copies: number | null; plates: number; plates_done: number; plate_layout: number[]; plate_now: number | null;
    scale: number; raw_bbox: { x: number; y: number; z: number } | null; slot: number | null; printer: { name: string; bed: string } | null;
    unit: string; unit_guess: { unit: string; confident: boolean } | null; second_slot?: number | null;
    dims: { x: number; y: number; z: number } | null; warnings: string[]; orientation_changed: boolean; supports: boolean; supports_mode: string; color_change_mm: number | null; second_color: { name: string; hex: string } | null;
    minutes: number | null; grams: number | null; meters: number | null; price: Price | null; total: number | null; shipping_price: number;
    colors: Color[]; color: { name: string; hex: string } | null; delivery: string; balance: number; model_url: string | null; supports_url: string | null;
    queue: { start_in: number; finish_in: number; ahead: number; blocked: string | null } | null; cancel_keep: number | null;
    print: { status: string; progress: number; snapshot_url: string | null; snapshot_at: string | null } | null;
    timelapse_url?: string | null;
    can_cancel: boolean; final: boolean;
}
interface FarmCfg { state: FarmState; routes: Record<string, string>; csrf: string; i18n: Record<string, string> }

const $ = <T extends HTMLElement = HTMLElement>(id: string) => document.getElementById(id) as T | null;
const show = (el: HTMLElement | null, on: boolean) => el?.classList.toggle('hidden', !on);
const esc = (s: string) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c] as string));
const money = (n: number) => Math.round(n).toLocaleString('cs-CZ');
const duration = (min: number) => (min >= 60 ? `${Math.floor(min / 60)} h ${min % 60} min` : `${min} min`);

export function bootFarmCta(): void {
    const btn = $<HTMLButtonElement>('cta-farm');
    if (!btn) return;
    btn.addEventListener('click', () => {
        // the calculator rewrites the address to /c/{token} once the calculation exists
        const m = location.pathname.match(/^\/c\/([A-Za-z0-9_-]+)/);
        if (!m) { show($('cta-farm-note'), true); return; }
        location.href = `${btn.dataset.url}?calc=${encodeURIComponent(m[1])}`;
    });
}

/** Admin order page: the model in its print pose, nothing else. */
export function bootFarmAdminViewer(): void {
    const canvas = $<HTMLCanvasElement>('admin-farm-viewer');
    if (!canvas?.dataset.model) return;
    const viewer = new Viewer(canvas);
    loadGeometryFromUrl(canvas.dataset.model).then((g) => viewer.setGeometry(g, 1, null)).catch(() => undefined);
}

/** "#rrggbb" as numbers 0..1; anything else is the default blue of the viewer. */
function rgbOf(hex: string): [number, number, number] {
    const m = /^#?([0-9a-f]{6})$/i.exec(hex);
    const n = m ? parseInt(m[1], 16) : 0x83a6d4;
    return [((n >> 16) & 255) / 255, ((n >> 8) & 255) / 255, (n & 255) / 255];
}

/** A plate with a code or a text: the triangles above the plate in the second colour, the rest in the first. */
function twoTone(geom: BufferGeometry, changeZ: number, first: string, second: string): FacePaint | null {
    const pos = geom.getAttribute('position');
    if (!pos || geom.index) return null;
    const flags = new Uint8Array(pos.count / 3);
    for (let t = 0; t < flags.length; t++) flags[t] = (pos.getZ(t * 3) + pos.getZ(t * 3 + 1) + pos.getZ(t * 3 + 2)) / 3 > changeZ ? 1 : 0;
    // vertex colours are multiplied by the viewer's lights: kept a little deeper than the swatch
    const rgb = (hex: string): [number, number, number] => { const c = rgbOf(hex); return [c[0] * 0.85, c[1] * 0.85, c[2] * 0.85]; };
    const a = rgb(first); const b = rgb(second);
    return { flags, color: (f) => (f ? b : a) };
}

export function bootFarmStart(): void {
    const cfg = (window as unknown as { MP_FARM_START?: { upload: string; files: string; maxMb: number; text: Record<string, string> } }).MP_FARM_START;
    // a look at the model: the one handed over from the calculator, or the one just uploaded
    const preview = $<HTMLCanvasElement>('farm-preview');
    let viewer: Viewer | null = null;
    let geom: BufferGeometry | null = null;
    const form = preview?.closest('form') ?? document;
    const checkedHex = (name: string): string | null => (form.querySelector<HTMLInputElement>(`input[name="${name}"]:checked`)?.dataset.hex ?? '') || null;
    const want = $('farm-second-start')?.dataset.want ?? '';
    let oneColorByHand = false;
    // the second colour has to come from the machine of the first: only its spools are offered, the rest stay hidden
    const paint = (): void => {
        const first = checkedHex('color');
        const changeZ = Number(preview?.dataset.change || 0);
        const chosen = form.querySelector<HTMLInputElement>('input[name="color"]:checked')?.value ?? '';
        let visible = 0;
        let before = '';                       // the second colour that was ticked under the previous first colour
        form.querySelectorAll<HTMLElement>('[data-second-for]').forEach((el) => {
            const on = el.dataset.secondFor === '*' || el.dataset.secondFor === chosen;
            el.classList.toggle('hidden', !on); el.classList.toggle('flex', on);
            const radio = el.querySelector<HTMLInputElement>('input');
            if (radio && !on && radio.checked) { before = radio.value; radio.checked = false; form.querySelector<HTMLInputElement>('input[name="second_color"][value=""]')!.checked = true; }
            if (on && el.dataset.secondFor !== '*') visible++;
        });
        const none = $('farm-second-none'); if (none) show(none, !!preview?.dataset.change && visible === 0 && !!chosen);
        // the first colour changed: the second stays when the new machine holds it too. A QR code in one colour cannot be
        // read, so there it otherwise goes to the spool nearest to the colour of the design, unless the customer asked
        // for one colour by hand
        if (!oneColorByHand && !checkedHex('second_color')) {
            const offer = [...form.querySelectorAll<HTMLInputElement>(`[data-second-for="${chosen}"] input`)];
            const far = (hex: string): number => { const a = rgbOf(hex); const b = rgbOf(want); return Math.hypot(a[0] - b[0], a[1] - b[1], a[2] - b[2]); };
            const best = offer.find((r) => before !== '' && r.value === before) ?? (want ? [...offer].sort((a, b) => far(a.dataset.hex ?? '') - far(b.dataset.hex ?? ''))[0] : undefined);
            if (best) best.checked = true;
        }
        if (!viewer) return;
        viewer.setColor(first);
        const second = checkedHex('second_color');
        const painted = geom && changeZ > 0 && second ? twoTone(geom, changeZ, first ?? '#83a6d4', second) : null;
        viewer.paint(painted);
    };
    form.querySelectorAll<HTMLInputElement>('input[name="second_color"]').forEach((r) => r.addEventListener('change', () => { oneColorByHand = r.value === ''; }));
    form.querySelectorAll<HTMLInputElement>('input[name="color"], input[name="second_color"]').forEach((r) => r.addEventListener('change', paint));
    const showModel = (url: string): void => {
        if (!preview) return;
        viewer ??= new Viewer(preview);
        show($('farm-preview-box'), true);
        loadGeometryFromUrl(url).then((g) => { geom = g; viewer!.setGeometry(g, Number(($('farm-scale') as HTMLInputElement | null)?.value || 1) || 1, null); paint(); }).catch(() => show($('farm-preview-box'), false));
    };
    paint();
    // the size: the file's own millimetres times the factor from the calculator; one dimension typed scales the whole model
    const sizeBox = $('farm-size');
    const scaleInput = $<HTMLInputElement>('farm-scale');
    let native: { x: number; y: number; z: number } | null = null;
    try { native = sizeBox?.dataset.bbox && sizeBox.dataset.bbox !== 'null' ? JSON.parse(sizeBox.dataset.bbox) : null; } catch { native = null; }
    let scale = Number(sizeBox?.dataset.scale || 1) || 1;
    const maxScale = Number(sizeBox?.dataset.max || 4) || 4;
    const renderSize = (): void => {
        if (!sizeBox) return;
        sizeBox.querySelectorAll<HTMLInputElement>('input[data-axis]').forEach((i) => {
            const axis = i.dataset.axis as 'x' | 'y' | 'z';
            if (document.activeElement !== i) i.value = native ? String(Math.round(native[axis] * scale * 10) / 10) : '';
        });
        const pct = $('farm-size-pct'); if (pct) pct.textContent = native ? `${Math.round(scale * 100)} %` : '';
        show($('farm-size-reset'), !!native && Math.abs(scale - 1) > 0.0005);
        if (scaleInput) scaleInput.value = String(Math.round(scale * 1000) / 1000);
        viewer?.setScale(scale);
    };
    $('farm-size-reset')?.addEventListener('click', () => { scale = 1; renderSize(); });
    sizeBox?.querySelectorAll<HTMLInputElement>('input[data-axis]').forEach((i) => i.addEventListener('change', () => {
        const axis = i.dataset.axis as 'x' | 'y' | 'z';
        const wanted = Number(i.value);
        if (native && native[axis] > 0 && wanted > 0) scale = clampScale(wanted / native[axis], maxScale);
        renderSize();
    }));
    renderSize();
    if (preview?.dataset.model) showModel(preview.dataset.model);

    const input = $<HTMLInputElement>('farm-upload');
    if (!cfg || !input) return;
    const status = $('farm-upload-status')!;
    const go = $<HTMLButtonElement>('farm-continue')!;
    const say = (t: string) => { status.textContent = t; show(status, t !== ''); };

    // drag & drop onto the zone hands the file to the input
    const zone = $('farm-dropzone');
    zone?.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('border-action', 'bg-action-soft'); });
    zone?.addEventListener('dragleave', () => zone.classList.remove('border-action', 'bg-action-soft'));
    zone?.addEventListener('drop', (e) => {
        e.preventDefault();
        zone.classList.remove('border-action', 'bg-action-soft');
        const file = e.dataTransfer?.files?.[0];
        if (!file) return;
        const dt = new DataTransfer(); dt.items.add(file); input.files = dt.files;
        input.dispatchEvent(new Event('change'));
    });

    input.addEventListener('change', async () => {
        const file = input.files?.[0];
        go.disabled = true;
        if (!file) return;
        if (!file.name.toLowerCase().endsWith('.stl')) { say(cfg.text.badFormat); return; }
        if (file.size > cfg.maxMb * 1024 * 1024) { say(cfg.text.tooBig); return; }
        say(cfg.text.uploading);
        try {
            const fd = new FormData(); fd.append('file', file);
            const up = await fetch(cfg.upload, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body: fd });
            if (!up.ok) throw new Error('upload');
            let info = (await up.json()).file;
            say(cfg.text.processing);
            for (let i = 0; i < 80 && info.status !== 'ready' && info.status !== 'failed'; i++) {
                await new Promise((r) => setTimeout(r, 1500));
                info = (await (await fetch(`${cfg.files}/${info.uuid}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })).json()).file;
            }
            if (info.status !== 'ready') throw new Error('processing');
            $<HTMLInputElement>('farm-file')!.value = info.uuid;
            native = info.bbox ?? null; scale = 1; renderSize();
            say(file.name);
            zone?.querySelector('.btn-primary')?.replaceChildren(document.createTextNode(file.name));
            showModel(`${cfg.files}/${info.uuid}/model.stl`);
            go.disabled = false;
        } catch {
            say(cfg.text.failed);
        }
    });
}

export function bootFarmOrder(): void {
    const cfg = (window as unknown as { MP_FARM?: FarmCfg }).MP_FARM;
    const canvas = $<HTMLCanvasElement>('farm-viewer');
    if (!cfg || !canvas) return;

    const tr = (k: string, p: Record<string, string | number> = {}) => Object.entries(p).reduce((s, [a, b]) => s.replace(`:${a}`, String(b)), cfg.i18n[k] ?? k);
    const viewer = new Viewer(canvas);
    let state = cfg.state;
    let shownModel = '';
    let shownSupports = '';
    let supportsOn = true;
    let picked: number | null = state.slot ?? null;                    // the colour chosen on the start page, if it is still on offer
    let delivery = state.delivery || 'pickup';
    let second: number | null = state.second_slot ?? null;             // the spool of the code or text; null = the colour of the plate
    let geom: BufferGeometry | null = null;
    // the plate in the first colour, the code or text in the second, as the machine will print it
    const paintTwo = (): void => {
        const c = state.colors.find((x) => x.slot === picked);
        const o = c?.second?.find((x) => x.slot === second);
        const first = (state.status === 'sliced' ? c?.hex : state.color?.hex) ?? null;
        const other = state.status === 'sliced' ? o?.hex : state.second_color?.hex;
        viewer.paint(geom && state.color_change_mm && first && other ? twoTone(geom, state.color_change_mm, first, other) : null);
    };
    let timer = 0;
    const wanted = { quality: state.quality, strength: state.strength, supports: state.supports_mode || 'auto', unit: state.unit, copies: state.copies || 1, scale: state.scale || 1 };
    // the model's own millimetres (in the chosen unit) so a typed dimension gives a factor
    const nativeMm = (): { x: number; y: number; z: number } | null => state.raw_bbox ? { x: state.raw_bbox.x * (UNIT_MM[wanted.unit] ?? 1), y: state.raw_bbox.y * (UNIT_MM[wanted.unit] ?? 1), z: state.raw_bbox.z * (UNIT_MM[wanted.unit] ?? 1) } : null;
    const renderSizeInputs = (): void => {
        const n = nativeMm();
        (['x', 'y', 'z'] as const).forEach((axis) => {
            const el = $<HTMLInputElement>(`farm-size-${axis}`);
            if (el && document.activeElement !== el) el.value = n ? String(Math.round(n[axis] * wanted.scale * 10) / 10) : '';
        });
        const pct = $('farm-size-pct'); if (pct) pct.textContent = n ? `${Math.round(wanted.scale * 100)} %` : '';
        show($('farm-size-reset'), !!n && Math.abs(wanted.scale - 1) > 0.0005);
    };
    $('farm-size-reset')?.addEventListener('click', () => { wanted.scale = 1; render(); });
    (['x', 'y', 'z'] as const).forEach((axis) => $(`farm-size-${axis}`)?.addEventListener('change', (e) => {
        const n = nativeMm(); const v = Number((e.target as HTMLInputElement).value);
        if (n && n[axis] > 0 && v > 0) wanted.scale = Math.round(clampScale(v / n[axis], 4) * 1000) / 1000;
        render();
    }));

    const post = async (url: string, body: unknown): Promise<{ ok: boolean; status: number; json: Record<string, unknown> }> => {
        const res = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf }, body: JSON.stringify(body) });
        return { ok: res.ok, status: res.status, json: await res.json().catch(() => ({})) };
    };

    const total = (): number | null => {
        const c = state.colors.find((x) => x.slot === picked);
        return c ? c.total + (delivery === 'shipping' ? state.shipping_price : 0) : state.total;
    };

    const renderColors = (): void => {
        const box = $('farm-colors')!;
        if (!state.colors.length) { box.innerHTML = `<p class="col-span-full text-sm text-slate-600">${esc(tr('farm.order.no_colors'))}</p>`; picked = null; return; }
        if (picked === null || !state.colors.some((c) => c.slot === picked && c.enough)) picked = (state.colors.find((c) => c.enough && c.sliced) ?? state.colors.find((c) => c.enough))?.slot ?? null;
        box.innerHTML = state.colors.map((c) => `
            <button type="button" role="radio" aria-checked="${c.slot === picked}" data-slot="${c.slot}" ${c.enough ? '' : 'disabled'}
                class="flex items-center gap-2 rounded-xl border bg-white p-2 text-left text-sm disabled:opacity-50 ${c.slot === picked ? 'border-action ring-2 ring-action' : 'border-slate-300'}">
                ${c.photo ? `<img src="${esc(c.photo)}" alt="${esc(c.name)}" data-zoom="${esc(c.photo)}" data-zoom-title="${esc(c.name)}${c.kind ? ' · ' + esc(c.kind) : ''}" class="h-10 w-10 shrink-0 cursor-zoom-in rounded-lg object-cover">` : `<span class="h-10 w-10 shrink-0 rounded-lg border border-slate-200" style="background:${esc(c.hex)}"></span>`}
                <span><span class="font-semibold">${esc(c.name)}</span>${c.kind ? `<br><span class="text-xs text-slate-500">${esc(c.kind)}</span>` : ''}${c.enough ? '' : `<br><span class="text-xs text-amber-700">${esc(tr('farm.order.low_filament'))}</span>`}</span>
            </button>`).join('');
        box.querySelectorAll<HTMLButtonElement>('button[data-slot]').forEach((b) => b.addEventListener('click', () => { picked = Number(b.dataset.slot); render(); }));
        const c = state.colors.find((x) => x.slot === picked);
        $('farm-start-note')!.textContent = c ? tr(c.starts_now ? 'farm.order.starts_now' : 'farm.order.goes_to_queue') : '';
        viewer.setColor(c?.hex ?? null);
        // a plate with a raised text or a code: the raised part may have its own colour from the same machine
        const offer = state.color_change_mm && c?.second?.length ? c.second : [];
        if (second !== null && !offer.some((o) => o.slot === second)) second = null;
        show($('farm-second'), offer.length > 0);
        const list = $('farm-second-colors');
        if (list && offer.length) {
            const tile = (slot: number | null, name: string, kind: string, swatch: string): string => `
                <button type="button" role="radio" aria-checked="${slot === second}" data-second="${slot ?? ''}"
                    class="flex items-center gap-2 rounded-xl border bg-white p-2 text-left text-sm ${slot === second ? 'border-action ring-2 ring-action' : 'border-slate-300'}">
                    ${swatch}<span><span class="font-semibold">${esc(name)}</span>${kind ? `<br><span class="text-xs text-slate-500">${esc(kind)}</span>` : ''}</span>
                </button>`;
            const dot = (hex: string): string => `<span class="h-10 w-10 shrink-0 rounded-lg border border-slate-200" style="background:${esc(hex)}"></span>`;
            list.innerHTML = tile(null, tr('farm.order.second_same'), tr('farm.order.second_same_hint'), dot(c?.hex ?? '#ffffff'))
                + offer.map((o) => tile(o.slot, o.name, o.kind ?? '', o.photo ? `<img src="${esc(o.photo)}" alt="" class="h-10 w-10 shrink-0 rounded-lg object-cover">` : dot(o.hex))).join('');
            list.querySelectorAll<HTMLButtonElement>('button[data-second]').forEach((b) => b.addEventListener('click', () => { second = b.dataset.second ? Number(b.dataset.second) : null; render(); }));
        }
        paintTwo();
    };

    const renderBreakdown = (): void => {
        // before payment the breakdown follows the picked colour (its kind's price per gram, its machine's rate)
        const p = (state.status === 'sliced' && state.colors.find((x) => x.slot === picked)?.price) || state.price;
        const box = $('farm-breakdown')!;
        if (!p) { box.innerHTML = ''; return; }
        const ship = delivery === 'shipping' && state.status === 'sliced' ? state.shipping_price : p.shipping;
        const row = (k: string, v: number, strong = false) => `<div class="flex justify-between ${strong ? 'font-semibold' : ''}"><dt>${esc(k)}</dt><dd>${money(v)} Kč</dd></div>`;
        box.innerHTML = row(tr('farm.order.b_time'), p.time) + row(tr('farm.order.b_material'), p.material) + row(tr('farm.order.b_fixed'), p.fixed)
            + (p.min_price_applied ? `<div class="text-xs text-slate-500">${esc(tr('farm.order.b_min'))}</div>` : '')
            + row(tr('farm.order.b_net'), p.net) + row(tr('farm.order.b_vat', { p: p.inputs.vat_percent }), p.vat)
            + (ship > 0 ? row(tr('farm.order.b_shipping'), ship) : '') + row(tr('farm.order.b_total'), (total() ?? p.total), true);
    };

    const render = (): void => {
        const s = state;
        const working = s.status === 'uploaded';
        $('farm-status')!.textContent = working && s.stage ? tr(`farm.stage.${s.stage}`) : s.status_text;
        // what is happening right now, as a step of the whole preparation
        const step = working && s.stage_step ? s.stage_step : 0;
        show($('farm-progress'), step > 0);
        if (step > 0) {
            ($('farm-progress-bar') as HTMLElement).style.width = `${Math.round(((step - 0.5) / (s.stage_total || 5)) * 100)}%`;
            $('farm-progress-step')!.textContent = tr('farm.stage_step', { n: step, total: s.stage_total || 5 });
        }
        show($('farm-spinner'), working || s.status === 'printing');
        const num = $('farm-number')!; num.textContent = s.number ? `${s.number}${s.color ? ` · ${s.color.name}` : ''}${s.second_color ? `, ${tr('farm.order.second_line', { name: s.second_color.name })}` : ''}` : ''; show(num, !!s.number);
        const pr = $('farm-printer'); if (pr) { pr.textContent = s.printer ? tr('farm.order.printer', { name: s.printer.name, bed: s.printer.bed }) : ''; show(pr, !!s.printer); }
        const err = $('farm-error')!; err.textContent = s.error_text ?? ''; show(err, s.status === 'failed' && !!s.error_text);
        $('farm-warnings')!.innerHTML = s.warnings.map((w) => `<li>⚠ ${esc(w)}</li>`).join('');
        $('farm-dims')!.textContent = s.dims ? `${s.dims.x.toFixed(1)} × ${s.dims.y.toFixed(1)} × ${s.dims.z.toFixed(1)} mm` : '';
        show($('farm-oriented'), s.orientation_changed && s.status !== 'failed');
        $('farm-balance')!.textContent = money(s.balance);

        const hasResult = s.minutes !== null && s.status !== 'uploaded' && !(s.status === 'failed' && !s.number);
        show($('farm-result'), hasResult);
        if (hasResult) {
            $('farm-price')!.textContent = total() !== null ? money(total()!) : '—';
            const copiesLine = $('farm-copies-line');
            if (copiesLine) {
                // "9 pieces on 2 plates (5 + 4), printed one after another" when one plate is not enough
                copiesLine.textContent = s.plates > 1 ? tr('farm.copies.plates', { n: s.copies, p: s.plates, layout: (s.plate_layout ?? []).join(' + ') }) : s.copies > 1 ? tr('farm.copies.note', { n: s.copies }) : '';
                show(copiesLine, s.copies > 1);
            }
            $('farm-time')!.textContent = duration(s.minutes!);
            $('farm-grams')!.textContent = `${s.grams} g${s.meters ? ` · ${s.meters} m` : ''}`;
            $('farm-supports')!.textContent = tr(s.supports ? 'farm.order.supports_yes' : s.supports_mode === 'off' ? 'farm.order.supports_off' : 'farm.order.supports_no');
            renderBreakdown();
        }

        // presets stay editable until the order is paid; a failed check can be retried with other units
        const editable = s.status === 'sliced' || (s.status === 'failed' && !s.number);
        show($('farm-presets'), editable);
        document.querySelectorAll<HTMLElement>('#farm-presets [data-group]').forEach((g) => g.querySelectorAll<HTMLElement>('.seg').forEach((b) => b.classList.toggle('seg-on', b.dataset.value === String(wanted[g.dataset.group as 'quality' | 'strength' | 'supports']))));
        ($('farm-unit') as HTMLSelectElement).value = wanted.unit;
        renderSizeInputs();
        const copiesInput = $<HTMLInputElement>('farm-copies');
        if (copiesInput && document.activeElement !== copiesInput) copiesInput.value = String(wanted.copies);
        const copiesNote = $('farm-copies-note');
        if (copiesNote) {
            const more = s.max_copies && wanted.copies > s.max_copies ? ` ${tr('farm.copies.more_plates', { p: Math.ceil(wanted.copies / s.max_copies) })}` : '';
            copiesNote.textContent = s.max_copies ? tr('farm.copies.max', { n: s.max_copies }) + more : '';
            show(copiesNote, !!s.max_copies);
        }
        const note = $('farm-unit-note')!;
        const guess = s.unit_guess;
        note.textContent = guess && guess.unit !== 'mm' ? (guess.confident && s.unit === guess.unit ? tr('farm.units.guess', { unit: tr(`farm.units.${guess.unit}`) }) : (!guess.confident && s.unit === 'mm' ? tr('farm.units.ask') : '')) : '';
        show(note, note.textContent !== '');
        show($('farm-reslice'), editable && (wanted.quality !== s.quality || wanted.strength !== s.strength || wanted.supports !== (s.supports_mode || 'auto') || wanted.unit !== s.unit || wanted.copies !== (s.copies || 1) || Math.abs(wanted.scale - (s.scale || 1)) > 0.0005));

        show($('farm-pay'), s.status === 'sliced');
        if (s.status === 'sliced') {
            renderColors();
            document.querySelectorAll<HTMLElement>('#farm-delivery .seg').forEach((b) => b.classList.toggle('seg-on', b.dataset.value === delivery));
            const addr = $('farm-address')!; addr.classList.toggle('hidden', delivery !== 'shipping'); addr.classList.toggle('grid', delivery === 'shipping');
            ($('farm-pay-btn') as HTMLButtonElement).disabled = picked === null || !($('farm-terms') as HTMLInputElement).checked;
            // another colour may mean another machine and another kind of filament: the numbers above must be computed again first
            const recolor = picked !== null && state.colors.find((c) => c.slot === picked)?.sliced === false;
            show($('farm-recolor'), recolor); show($('farm-recolor-note'), recolor); show($('farm-pay-btn'), !recolor);
        }

        const print = s.print && ['sent', 'printing', 'paused', 'done', 'unknown'].includes(s.print.status) && ['queued', 'printing', 'done', 'handed_over'].includes(s.status) ? s.print : null;
        show($('farm-print'), !!print || !!s.timelapse_url);
        const video = $<HTMLVideoElement>('farm-timelapse');
        if (video) {
            show(video.parentElement, !!s.timelapse_url);
            if (s.timelapse_url && video.getAttribute('src') !== s.timelapse_url) video.src = s.timelapse_url;
        }
        if (print) {
            const plateNow = s.plates > 1 ? ` · ${tr('farm.copies.plate_of', { i: s.plate_now ?? Math.min(s.plates, s.plates_done + 1), p: s.plates })}` : '';
            $('farm-progress-val')!.textContent = `${Math.round(print.progress)} %${plateNow}`;
            $('farm-progress-bar')!.style.width = `${Math.min(100, print.progress)}%`;
            show($('farm-camera'), !!print.snapshot_url);
            if (print.snapshot_url) {
                const img = $<HTMLImageElement>('farm-camera-img')!;
                if (img.getAttribute('src') !== print.snapshot_url) img.src = print.snapshot_url;
                $('farm-camera-at')!.textContent = print.snapshot_at ? new Date(print.snapshot_at).toLocaleTimeString() : '';
            }
        }
        const q = $('farm-queue')!;
        if (s.queue && s.status !== 'printing') {
            const lines = [s.queue.ahead > 0 ? tr('farm.order.queue_ahead', { n: s.queue.ahead }) : '', s.queue.start_in > 0 ? tr('farm.order.queue_start', { time: duration(s.queue.start_in) }) : tr('farm.order.queue_starting'), tr('farm.order.queue_finish', { time: duration(s.queue.finish_in) })];
            if (s.queue.blocked) lines.unshift(tr(`farm.order.blocked_${s.queue.blocked}`));
            q.innerHTML = lines.filter(Boolean).map(esc).join('<br>');
        } else if (s.queue) {
            q.textContent = tr('farm.order.queue_finish', { time: duration(s.queue.finish_in) });
        }
        show(q, !!s.queue);
        show($('farm-cancel'), s.can_cancel);
        // once the print is over (or fell through) the same model can be ordered again with today's colours
        show($('farm-repeat'), ['done', 'handed_over', 'cancelled', 'failed'].includes(s.status));

        // the model in the colour that will print it; once paid, the chosen colour
        if (s.status !== 'sliced') viewer.setColor(s.color?.hex ?? null);
        if (s.model_url && s.model_url !== shownModel) {
            shownModel = s.model_url;
            shownSupports = '';
            loadGeometryFromUrl(s.model_url).then((g) => { geom = g; viewer.setGeometry(g, 1, null); viewer.setColor((s.status === 'sliced' ? state.colors.find((x) => x.slot === picked)?.hex : s.color?.hex) ?? null); paintTwo(); showSupports(); }).catch(() => { shownModel = ''; });
        } else {
            showSupports();
        }
    };

    // the supports the slicer built, drawn as thin lines; the customer can hide them to see the piece alone
    const toggle = $<HTMLButtonElement>('farm-supports-toggle');
    const showSupports = (): void => {
        const url = state.supports_url;
        show(toggle, !!url);
        if (!url) { if (shownSupports) { viewer.setSupports(null); shownSupports = ''; } return; }
        if (url === shownSupports || !shownModel) return;
        shownSupports = url;
        fetch(url, { credentials: 'same-origin' }).then((r) => (r.ok ? r.arrayBuffer() : Promise.reject())).then((buf) => { if (shownSupports === url) viewer.setSupports(buf, supportsOn); }).catch(() => { shownSupports = ''; });
    };
    toggle?.addEventListener('click', () => {
        supportsOn = !supportsOn;
        viewer.showSupports(supportsOn);
        toggle.setAttribute('aria-pressed', String(supportsOn));
        toggle.textContent = tr(supportsOn ? 'farm.order.supports_hide' : 'farm.order.supports_show');
    });

    const poll = async (): Promise<void> => {
        window.clearTimeout(timer);
        if (state.final) return;
        try {
            const res = await fetch(cfg.routes.status, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (res.ok) { state = await res.json(); render(); }
        } catch { /* offline for a moment: try again */ }
        const fast = state.status === 'uploaded';
        const live = ['paid', 'queued', 'printing', 'sliced', 'done'].includes(state.status);
        // while it prints the page follows the camera: a new picture every 15 s
        if (fast || live) timer = window.setTimeout(poll, fast ? 2000 : state.status === 'printing' ? 15000 : state.status === 'sliced' ? 20000 : 8000);
    };

    document.querySelectorAll<HTMLElement>('#farm-presets [data-group] .seg').forEach((b) => b.addEventListener('click', () => {
        wanted[(b.parentElement as HTMLElement).dataset.group as 'quality' | 'strength' | 'supports'] = b.dataset.value!; render();
    }));
    $('farm-unit')?.addEventListener('change', (e) => { wanted.unit = (e.target as HTMLSelectElement).value; render(); });
    $('farm-copies')?.addEventListener('input', (e) => { const v = Number((e.target as HTMLInputElement).value); wanted.copies = Math.max(1, Math.min(64, Math.round(v) || 1)); render(); });
    $('farm-presets')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const r = await post(cfg.routes.reslice, { ...wanted, slot: picked });
        if (r.ok) { state = r.json as unknown as FarmState; picked = state.slot ?? null; render(); poll(); } else { const err = $('farm-error')!; err.textContent = String(r.json.message ?? ''); show(err, true); }
    });
    document.querySelectorAll<HTMLElement>('#farm-delivery .seg').forEach((b) => b.addEventListener('click', () => { delivery = b.dataset.value!; render(); }));
    $('farm-recolor')?.addEventListener('click', () => ($('farm-presets') as HTMLFormElement | null)?.requestSubmit());
    $('farm-terms')?.addEventListener('change', render);

    $('farm-pay')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = $<HTMLButtonElement>('farm-pay-btn')!;
        const errBox = $('farm-pay-error')!;
        const form = e.target as HTMLFormElement;
        const address: Record<string, string> = {};
        new FormData(form).forEach((v, k) => { const m = k.match(/^address\[(\w+)\]$/); if (m) address[m[1]] = String(v); });
        btn.disabled = true; btn.textContent = tr('farm.order.paying'); show(errBox, false); show($('farm-topup'), false);
        const r = await post(cfg.routes.pay, {
            slot: picked, second_slot: second, delivery, terms: ($('farm-terms') as HTMLInputElement).checked, expected_total: total(),
            note: (form.elements.namedItem('note') as HTMLTextAreaElement).value, address: delivery === 'shipping' ? address : null,
        });
        btn.textContent = tr('farm.order.pay');
        if (r.ok) { state = r.json as unknown as FarmState; render(); poll(); return; }
        errBox.textContent = String(r.json.message ?? (r.json.errors ? Object.values(r.json.errors as Record<string, string[]>)[0][0] : ''));
        show(errBox, true);
        if (r.status === 402 && r.json.topup_url) { const a = $<HTMLAnchorElement>('farm-topup')!; a.href = String(r.json.topup_url); show(a, true); }
        if (r.json.error === 'price_changed' || r.json.error === 'color_gone' || r.json.error === 'filament_low') await poll();
        render();
    });

    $('farm-cancel')?.addEventListener('click', async () => {
        const keep = state.status === 'printing' ? state.cancel_keep : null;
        if (!window.confirm(keep !== null ? tr('farm.order.cancel_running_confirm', { amount: money(keep) }) : tr('farm.order.cancel_confirm'))) return;
        const r = await post(cfg.routes.cancel, {});
        if (r.ok) { state = r.json as unknown as FarmState; render(); } else { const err = $('farm-error')!; err.textContent = String(r.json.message ?? ''); show(err, true); }
    });

    render();
    poll();
}

/**
 * Admin "Printers and queue". Every button used to post a form and come back through a redirect, which reloaded the
 * page and threw the operator to the top. Now the form posts over fetch, the answer pops up as a toast for a moment
 * and the cards are redrawn in place from a fresh copy of the page - the scroll position never moves. The same
 * in-place redraw replaces the meta refresh (kept in <noscript>).
 */
export function bootFarmDashboard(): void {
    const box = $('farm-dashboard');
    if (!box) return;
    const toast = $('farm-toast');
    let hideAt: number | undefined;
    const say = (text: string, ok: boolean): void => {
        const pill = toast?.firstElementChild as HTMLElement | null;
        if (!toast || !pill) return;
        pill.textContent = text;
        pill.classList.toggle('bg-red-700', !ok);
        pill.classList.toggle('bg-slate-900', ok);
        toast.classList.remove('opacity-0');
        window.clearTimeout(hideAt);
        hideAt = window.setTimeout(() => toast.classList.add('opacity-0'), 3500);
    };

    let posting = false;
    const refresh = async (): Promise<void> => {
        // not under the operator's hands: a request in flight, a menu being chosen, or a tab nobody looks at
        const a = document.activeElement;
        if (posting || document.hidden || (a && box.contains(a) && /^(SELECT|INPUT|TEXTAREA)$/.test(a.tagName))) return;
        try {
            const r = await fetch(box.dataset.refresh!, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
            if (!r.ok) return;
            const fresh = new DOMParser().parseFromString(await r.text(), 'text/html').getElementById('farm-dashboard');
            if (fresh) box.innerHTML = fresh.innerHTML;
        } catch {
            // offline for a moment: the next tick tries again
        }
    };
    window.setInterval(() => void refresh(), Number(box.dataset.every || '30') * 1000);

    box.addEventListener('submit', async (e) => {
        const form = e.target as HTMLFormElement;
        if (!form.matches('form[data-ajax]')) return;
        e.preventDefault();
        const submitter = e.submitter as HTMLButtonElement | null;
        const body = new FormData(form);
        if (submitter?.name) body.append(submitter.name, submitter.value);
        // only the buttons that were live get locked while the request runs (a server-disabled one stays disabled)
        const live = Array.from(form.querySelectorAll<HTMLButtonElement>('button')).filter((b) => !b.disabled);
        live.forEach((b) => { b.disabled = true; });
        posting = true;
        try {
            const r = await fetch(form.action, { method: 'POST', body, headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
            const data = (await r.json().catch(() => null)) as { ok?: boolean; message?: string } | null;
            say(data?.message || box.dataset.error || '', r.ok && data?.ok === true);
        } catch {
            say(box.dataset.error || '', false);
        } finally {
            posting = false;
            live.forEach((b) => { b.disabled = false; });
        }
        void refresh();
    });
}
