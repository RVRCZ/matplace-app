/**
 * Print farm ("Rent a printer").
 *   bootFarmCta    calculator: the button takes the shared calculation (/c/{token} in the address) to /farm
 *   bootFarmStart  /farm: upload an STL when the customer did not come from the calculator
 *   bootFarmOrder  /farm/orders/{token}: preview of the print pose, presets, colours, pay, progress (polled)
 *   bootFarmDashboard  /admin/farm: buttons post over fetch, the answer is a short toast, cards redraw in place
 */
import { icon } from '../site/icon';
import { BufferGeometry } from 'three';
import { Viewer, FacePaint, cutAtHeight, deep } from './viewer';
import { loadGeometryFromUrl } from './loaders';
import { money as moneyText } from '../site/money';

interface Price { time: number; material: number; fixed: number; min_price_applied: boolean; net: number; vat: number; shipping: number; total: number; print_total: number; royalty?: number; inputs: { vat_percent: number } }
interface Color { slot: number; name: string; kind?: string; hex: string; photo: string | null; enough: boolean; price: Price; total: number; starts_now: boolean; sliced: boolean; second?: Second[] }
interface Second { slot: number; name: string; kind?: string; hex: string; photo: string | null }
const UNIT_MM: Record<string, number> = { mm: 1, cm: 10, in: 25.4, m: 1000 };
const clampScale = (v: number, max: number) => Math.max(0.25, Math.min(max || 4, v));

/** One change of the design: where (mm, as printed), the colour it was made in, and the spool chosen for it so far. */
interface Change { z: number; hex: string; code: string | null; slot_id: number | null; name?: string | null }
interface FarmState {
    token: string; number: string | null; status: string; status_text: string; stage: string | null; stage_step: number | null; stage_total: number; error: string | null; error_text: string | null;
    quality: string; strength: string; copies: number; max_copies: number | null; plates: number; plates_done: number; plate_layout: number[]; plate_now: number | null;
    scale: number; raw_bbox: { x: number; y: number; z: number } | null; slot: number | null; printer: { name: string; bed: string } | null;
    unit: string; unit_guess: { unit: string; confident: boolean } | null; second_slot?: number | null; changes?: Change[]; max_colors?: number;
    settings?: Record<string, number> | null; admin_overrides?: Record<string, string> | null;
    dims: { x: number; y: number; z: number } | null; warnings: string[]; orientation_changed: boolean; supports: boolean; supports_mode: string; color_change_mm: number | null; second_color: { name: string; hex: string } | null;
    minutes: number | null; grams: number | null; meters: number | null; price: Price | null; total: number | null; currency: string;
    shipping: Shipping | null; destination: string | null; tracking_url: string | null;
    colors: Color[]; color: { name: string; hex: string } | null; delivery: string; balance: number; model_url: string | null; supports_url: string | null;
    queue: { start_in: number; finish_in: number; ahead: number; blocked: string | null } | null; cancel_keep: number | null;
    print: { status: string; progress: number; snapshot_url: string | null; snapshot_at: string | null } | null;
    timelapse_url?: string | null;
    short_url?: string | null;
    can_cancel: boolean; final: boolean;
}
/** What delivery is on offer: per country the price of a parcel to a pickup point and to the door (null = not offered there). */
interface Shipping { weight_g: number; too_big: boolean; modes: string[]; countries: Record<string, { name: string; point: number | null; home: number | null; vendors: Record<string, string>[] }> }
interface Prefill { name: string; phone: string; street: string; city: string; zip: string; country: string; point: { id: string; name: string; carrier_id: string; country: string } | null }
interface FarmCfg { state: FarmState; prefill?: Prefill; routes: Record<string, string>; csrf: string; i18n: Record<string, string> }

const $ = <T extends HTMLElement = HTMLElement>(id: string) => document.getElementById(id) as T | null;
const show = (el: HTMLElement | null, on: boolean) => el?.classList.toggle('hidden', !on);
const esc = (s: string) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c] as string));
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

/**
 * Where a new colour starts: a hair above the change. The top face of the layer below lies exactly at the height of
 * the change and belongs to the colour below (measured at the change itself, rounding put the whole face into the
 * new colour and a code was lost in it).
 */
const edge = (changeZ: number): number => changeZ * 1.02;

/** The model as the colours will print it: cut at every change, so each face lies whole in one colour. */
const cutForTones = (geom: BufferGeometry, heights: number[]): BufferGeometry => heights.filter((z) => z > 0).reduce((g, z) => cutAtHeight(g, edge(z)), geom);

/**
 * A plate with a text, a picture in colours one on another: every triangle takes the colour of the highest change
 * below its middle. A change the customer left "the same" keeps the colour printing before it.
 */
function multiTone(geom: BufferGeometry, heights: number[], first: string, picked: (string | null)[]): FacePaint | null {
    const pos = geom.getAttribute('position');
    if (!pos || geom.index) return null;
    const tones: string[] = [first];
    heights.forEach((_, i) => tones.push(picked[i] ?? tones[i]));
    const flags = new Uint8Array(pos.count / 3);
    for (let t = 0; t < flags.length; t++) {
        const z = (pos.getZ(t * 3) + pos.getZ(t * 3 + 1) + pos.getZ(t * 3 + 2)) / 3;
        let k = 0;
        heights.forEach((h, i) => { if (h > 0 && z > edge(h)) k = i + 1; });
        flags[t] = k;
    }
    // the colours as the swatches of the spools show them: a dark code stays dark on a light plate
    const rgb = tones.map((h) => deep(rgbOf(h)));
    return { flags, color: (f) => rgb[f] ?? rgb[0] };
}

export function bootFarmStart(): void {
    const cfg = (window as unknown as { MP_FARM_START?: { upload: string; files: string; maxMb: number; text: Record<string, string> } }).MP_FARM_START;
    // a look at the model: the one handed over from the calculator, or the one just uploaded
    const preview = $<HTMLCanvasElement>('farm-preview');
    let viewer: Viewer | null = null;
    let geom: BufferGeometry | null = null;
    const form = preview?.closest('form') ?? document;
    const checkedHex = (name: string): string | null => (form.querySelector<HTMLInputElement>(`input[name="${name}"]:checked`)?.dataset.hex ?? '') || null;
    // every change of the design (bottom to top) has a block of spools; all of them from the machine of the first colour
    const blocks = [...form.querySelectorAll<HTMLElement>('[data-change-index]')];
    const heights: number[] = (() => { try { return (JSON.parse(preview?.dataset.changes || '[]') as number[]).map(Number); } catch { return []; } })();
    const byHand = new Set<number>();          // blocks where the customer chose "no change" on purpose
    const paint = (): void => {
        const first = checkedHex('color');
        const chosen = form.querySelector<HTMLInputElement>('input[name="color"]:checked')?.value ?? '';
        blocks.forEach((block, i) => {
            const name = `change_color[${i}]`;
            let visible = 0;
            let before = '';                   // the spool that was ticked under the previous first colour
            block.querySelectorAll<HTMLElement>('[data-second-for]').forEach((el) => {
                const on = el.dataset.secondFor === '*' || el.dataset.secondFor === chosen;
                el.classList.toggle('hidden', !on); el.classList.toggle('flex', on);
                const radio = el.querySelector<HTMLInputElement>('input');
                if (radio && !on && radio.checked) { before = radio.value; radio.checked = false; block.querySelector<HTMLInputElement>('input[value=""]')!.checked = true; }
                if (on && el.dataset.secondFor !== '*') visible++;
            });
            const none = block.querySelector<HTMLElement>('.farm-change-none'); if (none) show(none, visible === 0 && !!chosen);
            // the first colour changed: the choice stays when the new machine holds that spool too; else the spool nearest
            // to the colour the design was made in (a QR code in one colour cannot be read), unless "no change" was asked for by hand
            if (!byHand.has(i) && !checkedHex(name)) {
                const offer = [...block.querySelectorAll<HTMLInputElement>(`[data-second-for="${chosen}"] input`)];
                const want = block.dataset.want ?? '';
                const far = (hex: string): number => { const a = rgbOf(hex); const b = rgbOf(want); return Math.hypot(a[0] - b[0], a[1] - b[1], a[2] - b[2]); };
                const best = offer.find((r) => before !== '' && r.value === before) ?? (want ? [...offer].sort((a, b) => far(a.dataset.hex ?? '') - far(b.dataset.hex ?? ''))[0] : undefined);
                if (best) best.checked = true;
            }
        });
        if (!viewer) return;
        viewer.setColor(first);
        const picked = blocks.map((_, i) => checkedHex(`change_color[${i}]`));
        viewer.paint(geom && heights.length ? multiTone(geom, heights, first ?? '#83a6d4', picked) : null);
    };
    blocks.forEach((block, i) => block.querySelectorAll<HTMLInputElement>('input').forEach((r) => r.addEventListener('change', () => { if (r.value === '') byHand.add(i); else byHand.delete(i); })));
    form.querySelectorAll<HTMLInputElement>('input[name="color"], [data-change-index] input').forEach((r) => r.addEventListener('change', paint));
    const showModel = (url: string): void => {
        if (!preview) return;
        viewer ??= new Viewer(preview);
        show($('farm-preview-box'), true);
        loadGeometryFromUrl(url).then((g) => { geom = cutForTones(g, heights); viewer!.setGeometry(geom, Number(($('farm-scale') as HTMLInputElement | null)?.value || 1) || 1, null); paint(); }).catch(() => show($('farm-preview-box'), false));
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
    // what the order holds; a kind the farm does not offer (any more) is replaced once the offer is drawn
    let delivery = state.delivery || '';
    // amounts of this order, in the currency it is priced in
    const money = (n: number): string => moneyText(n, state.currency);
    // where a parcel would go: the country of the profile when we send there, else the first one on offer
    let country = cfg.prefill?.country ?? 'CZ';
    const kind = (): 'point' | 'home' | null => (delivery === 'packeta_point' ? 'point' : delivery === 'packeta_home' ? 'home' : null);
    const pointBox = $('farm-point');
    const pointOf = (): { id: string; name: string; carrier_id: string; country: string } => {
        const v = (f: string) => pointBox?.querySelector<HTMLInputElement>(`[data-pickup="${f}"]`)?.value ?? '';
        return { id: v('id'), name: v('name'), carrier_id: v('carrier_id'), country: v('country') };
    };
    // the price with a parcel comes from the server (the country may change the VAT, the weight the band): asked
    // whenever colour, kind of delivery or country change; until the answer is here there is nothing to pay
    let quoted: { key: string; price: Price; total: number } | null = null;
    let quoting = '';
    const quoteKey = (): string => `${picked}|${delivery}|${country}`;
    const askQuote = async (): Promise<void> => {
        const key = quoteKey();
        if (!kind() || picked === null || quoted?.key === key || quoting === key) return;
        quoting = key;
        const r = await post(cfg.routes.quote, { slot: picked, delivery, country });
        if (quoting === key) quoting = '';
        if (r.ok && key === quoteKey()) { quoted = { key, price: r.json.price as unknown as Price, total: Number(r.json.total) }; render(); }
    };
    // the spool of every change of the design (bottom to top); null = no change there, the colour before goes on
    let changes: (number | null)[] = (state.changes ?? []).map((c) => c.slot_id ?? null);
    const byHand = new Set<number>();                                  // rows where "no change" was chosen on purpose
    const changeHeights = (): number[] => (state.changes ?? []).map((c) => c.z);
    let geom: BufferGeometry | null = null;
    // the model as the machine will print it: the first colour, then every change at its height
    const paintTones = (): void => {
        const c = state.colors.find((x) => x.slot === picked);
        const first = (state.status === 'sliced' ? c?.hex : state.color?.hex) ?? null;
        const spools = c ? [{ slot: c.slot, hex: c.hex }, ...(c.second ?? [])] : [];
        const picks = (state.changes ?? []).map((w, i) => (state.status === 'sliced' ? spools.find((s) => s.slot === changes[i])?.hex ?? null : (w.slot_id === null ? null : w.hex)));
        viewer.paint(geom && changeHeights().length && first ? multiTone(geom, changeHeights(), first, picks) : null);
    };
    let timer = 0;
    const wanted = { quality: state.quality, strength: state.strength, supports: state.supports_mode || 'auto', unit: state.unit, copies: state.copies || 1, scale: state.scale || 1, settings: { ...(state.settings ?? {}) } as Record<string, number> };
    // the "advanced" numbers: what the fields hold, only the filled ones
    const settingInputs = [...document.querySelectorAll<HTMLInputElement>('#farm-advanced [data-setting]')];
    const settingsOf = (s: Record<string, number> | null | undefined): string => JSON.stringify(Object.fromEntries(Object.entries(s ?? {}).filter(([, v]) => v !== null && v !== undefined).sort()));
    settingInputs.forEach((i) => i.addEventListener('input', () => {
        const v = i.value.trim();
        if (v === '') delete wanted.settings[i.dataset.setting!]; else wanted.settings[i.dataset.setting!] = Math.round(Number(v));
        render();
    }));
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
        if (!c) return state.total;
        if (delivery === 'pickup') return c.total;
        return kind() && quoted?.key === quoteKey() ? quoted.total : null;
    };

    // delivery: the kinds on offer with their price for the chosen country, the country, the pickup point or the address
    const renderDelivery = (): void => {
        const ship = state.shipping;
        const countries = ship?.countries ?? {};
        const codes = Object.keys(countries);
        if (!countries[country]) country = codes[0] ?? 'CZ';
        const here = countries[country];
        const select = $<HTMLSelectElement>('farm-country');
        if (select && select.options.length !== codes.length) {
            select.innerHTML = codes.map((c) => `<option value="${esc(c)}">${esc(countries[c].name)}</option>`).join('');
        }
        if (select) select.value = country;
        const pickup = (ship?.modes ?? []).includes('pickup');
        const kindOf = (mode: string): 'point' | 'home' | null => (mode === 'packeta_point' ? 'point' : mode === 'packeta_home' ? 'home' : null);
        const offered = (mode: string): boolean => { const k = kindOf(mode); return k === null ? mode === 'pickup' && pickup : (here?.[k] ?? null) !== null; };
        // a kind that is not offered in the chosen country (or at all) cannot stay chosen: the next one on offer takes its place
        if (ship && !offered(delivery)) delivery = ['packeta_point', 'packeta_home', 'pickup'].find(offered) ?? '';
        document.querySelectorAll<HTMLButtonElement>('#farm-delivery .seg').forEach((b) => {
            const mode = b.dataset.value!;
            const k = kindOf(mode);
            const anywhere = k === null ? pickup : codes.some((c) => countries[c][k] !== null);
            const amount = k === null ? 0 : here?.[k] ?? null;
            b.classList.toggle('hidden', !anywhere);
            b.classList.toggle('seg-on', mode === delivery);
            b.disabled = amount === null;
            const label = b.querySelector<HTMLElement>('[data-price]');
            if (label) label.textContent = amount === null ? tr('farm.delivery.not_here') : amount > 0 ? money(amount) : tr('farm.delivery.free');
        });
        const note = $('farm-delivery-note');
        if (note) { note.textContent = ship?.too_big ? tr(pickup ? 'farm.delivery.too_big_pickup' : 'farm.delivery.too_big') : ''; show(note, !!ship?.too_big); }
        show($('farm-parcel'), kind() !== null);
        show(pointBox, kind() === 'point');
        document.querySelectorAll<HTMLElement>('#farm-address [data-home]').forEach((el) => show(el, kind() === 'home'));
        if (pointBox && ship) {
            pointBox.dataset.vendors = JSON.stringify(Object.fromEntries(codes.map((c) => [c, countries[c].vendors])));
            pointBox.dataset.weight = String(ship.weight_g / 1000);
            // the favourite point of another country does not apply here
            const p = pointOf();
            if (p.id && p.country && p.country !== country) pointBox.dispatchEvent(new CustomEvent('pickup:clear'));
        }
        void askQuote();
    };

    /** What is still missing before a parcel can be paid for: null = nothing. */
    const deliveryMissing = (): string | null => {
        if (delivery === 'pickup') return null;
        // nothing on offer for this print (too big for a parcel and nobody hands prints over), or nothing chosen yet
        if (!kind()) return tr(state.shipping?.too_big ? 'farm.delivery.too_big' : 'farm.delivery.none');
        const val = (f: string) => (document.querySelector<HTMLInputElement>(`#farm-address [name="address[${f}]"]`)?.value ?? '').trim();
        if (kind() === 'point') return pointOf().id && val('name') ? null : tr('farm.delivery.pick_point');
        return ['name', 'phone', 'street', 'city', 'zip'].every((f) => val(f) !== '') ? null : tr('farm.delivery.fill_address');
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
        // a plate with a raised text, a picture in colours one on another: every change picks a spool of this machine
        // (the first colour itself counts: a print may go back to it)
        const wanted = state.changes ?? [];
        const offer = wanted.length && c?.second?.length ? [{ slot: c.slot, name: c.name, kind: c.kind ?? '', hex: c.hex, photo: c.photo }, ...c.second] : [];
        changes = wanted.map((w, i) => (changes[i] !== null && changes[i] !== undefined && offer.some((o) => o.slot === changes[i]) ? changes[i] : null));
        // nothing chosen yet (a fresh order, another machine): the spool nearest to the colour the design was made in
        wanted.forEach((w, i) => {
            if (changes[i] !== null || byHand.has(i) || !offer.length) return;
            const far = (hex: string): number => { const a = rgbOf(hex); const b = rgbOf(w.hex); return Math.hypot(a[0] - b[0], a[1] - b[1], a[2] - b[2]); };
            const pool = i === 0 ? offer.filter((o) => o.slot !== c?.slot) : offer;
            changes[i] = [...pool].sort((a, b) => far(a.hex) - far(b.hex))[0]?.slot ?? null;
        });
        show($('farm-second'), offer.length > 0);
        const list = $('farm-second-colors');
        const heading = $('farm-second')?.querySelector<HTMLElement>('div');
        if (heading) heading.textContent = tr(wanted.length === 1 ? 'farm.order.second_color' : 'farm.order.changes_title');
        if (list && offer.length) {
            const tile = (i: number, slot: number | null, name: string, kind: string, swatch: string): string => `
                <button type="button" role="radio" aria-checked="${slot === changes[i]}" data-change="${i}" data-second="${slot ?? ''}"
                    class="flex items-center gap-2 rounded-xl border bg-white p-2 text-left text-sm ${slot === changes[i] ? 'border-action ring-2 ring-action' : 'border-slate-300'}">
                    ${swatch}<span><span class="font-semibold">${esc(name)}</span>${kind ? `<br><span class="text-xs text-slate-500">${esc(kind)}</span>` : ''}</span>
                </button>`;
            const dot = (hex: string): string => `<span class="h-10 w-10 shrink-0 rounded-lg border border-slate-200" style="background:${esc(hex)}"></span>`;
            list.innerHTML = wanted.map((w, i) => (wanted.length > 1 ? `<div class="col-span-full mt-1 text-xs font-semibold text-slate-700">${esc(tr('farm.order.change_title', { n: i + 2, z: w.z.toFixed(1) }))}</div>` : '')
                + tile(i, null, tr(i === 0 ? 'farm.order.second_same' : 'farm.order.change_same'), tr(i === 0 ? 'farm.order.second_same_hint' : 'farm.order.change_same_hint'), dot(i === 0 ? c?.hex ?? '#ffffff' : '#ffffff'))
                + offer.filter((o) => i > 0 || o.slot !== c?.slot).map((o) => tile(i, o.slot, o.name, o.slot === c?.slot ? tr('farm.order.change_first') : (o.kind ?? ''), o.photo ? `<img src="${esc(o.photo)}" alt="" class="h-10 w-10 shrink-0 rounded-lg object-cover">` : dot(o.hex))).join('')).join('');
            list.querySelectorAll<HTMLButtonElement>('button[data-change]').forEach((b) => b.addEventListener('click', () => {
                const i = Number(b.dataset.change);
                changes[i] = b.dataset.second ? Number(b.dataset.second) : null;
                if (changes[i] === null) byHand.add(i); else byHand.delete(i);
                render();
            }));
        }
        paintTones();
    };

    const renderBreakdown = (): void => {
        // before payment the breakdown follows the picked colour (its kind's price per gram, its machine's rate)
        const p = (state.status === 'sliced' && ((kind() && quoted?.key === quoteKey() ? quoted.price : null) ?? state.colors.find((x) => x.slot === picked)?.price)) || state.price;
        const box = $('farm-breakdown')!;
        if (!p) { box.innerHTML = ''; return; }
        const ship = p.shipping;
        const row = (k: string, v: number, strong = false) => `<div class="flex justify-between ${strong ? 'font-semibold' : ''}"><dt>${esc(k)}</dt><dd>${esc(money(v))}</dd></div>`;
        box.innerHTML = row(tr('farm.order.b_time'), p.time) + row(tr('farm.order.b_material'), p.material) + row(tr('farm.order.b_fixed'), p.fixed)
            + (p.min_price_applied ? `<div class="text-xs text-slate-500">${esc(tr('farm.order.b_min'))}</div>` : '')
            + row(tr('farm.order.b_net'), p.net) + row(tr('farm.order.b_vat', { p: p.inputs.vat_percent }), p.vat)
            + ((p.royalty ?? 0) > 0 ? row(tr('models.price.to_author'), p.royalty ?? 0) : '')
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
        const num = $('farm-number')!; num.textContent = s.number ? `${s.number}${s.color ? ` · ${s.color.name}` : ''}${(s.changes ?? []).some((c) => c.name) ? `, ${(s.changes ?? []).length === 1 ? tr('farm.order.second_line', { name: s.changes![0].name ?? '' }) : (s.changes ?? []).map((c, i) => (c.name ? tr('farm.order.change_line', { n: i + 2, name: c.name }) : '')).filter(Boolean).join(', ')}` : ''}` : ''; show(num, !!s.number);
        const pr = $('farm-printer'); if (pr) { pr.textContent = s.printer ? tr('farm.order.printer', { name: s.printer.name, bed: s.printer.bed }) : ''; show(pr, !!s.printer); }
        const err = $('farm-error')!; err.textContent = s.error_text ?? ''; show(err, s.status === 'failed' && !!s.error_text);
        $('farm-warnings')!.innerHTML = s.warnings.map((w) => `<li class="flex items-start gap-1.5">${icon('triangle-alert', 'mt-0.5 h-4 w-4')}<span>${esc(w)}</span></li>`).join('');
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
        settingInputs.forEach((i) => { if (document.activeElement !== i) { const v = wanted.settings[i.dataset.setting!]; i.value = v === undefined ? '' : String(v); } });
        const adv = $('farm-advanced') as HTMLDetailsElement | null;
        if (adv && Object.keys(wanted.settings).length && !adv.open) adv.open = true;
        show($('farm-reslice'), editable && (wanted.quality !== s.quality || wanted.strength !== s.strength || wanted.supports !== (s.supports_mode || 'auto') || wanted.unit !== s.unit || wanted.copies !== (s.copies || 1) || Math.abs(wanted.scale - (s.scale || 1)) > 0.0005 || settingsOf(wanted.settings) !== settingsOf(s.settings)));

        show($('farm-pay'), s.status === 'sliced');
        if (s.status === 'sliced') {
            renderColors();
            renderDelivery();
            // short of credit: the button says by how much and leads to the top-up instead of a payment that would fail
            const payBtn = $('farm-pay-btn') as HTMLButtonElement;
            const short = total() !== null ? Math.max(0, total()! - s.balance) : 0;
            payBtn.dataset.need = short > 0.005 ? String(Math.ceil(short)) : '';
            payBtn.textContent = payBtn.dataset.need ? tr('farm.order.pay_short', { missing: money(short) }) : tr('farm.order.pay');
            payBtn.disabled = payBtn.dataset.need ? false : picked === null || total() === null || deliveryMissing() !== null || !($('farm-terms') as HTMLInputElement).checked;
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
        const short = $<HTMLAnchorElement>('farm-short');
        if (short) {
            show(short, !!s.short_url);
            if (s.short_url) short.href = s.short_url;
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
        // a paid parcel: where it goes, and the link to follow it once it has left
        const dest = $('farm-destination');
        if (dest) {
            dest.innerHTML = s.destination && s.status !== 'sliced'
                ? `${esc(tr('farm.delivery.to'))} ${esc(s.destination)}${s.tracking_url ? ` · <a class="font-semibold text-action-dark underline" target="_blank" rel="noopener" href="${esc(s.tracking_url)}">${esc(tr('farm.delivery.track'))}</a>` : ''}`
                : '';
            show(dest, dest.innerHTML !== '');
        }
        show($('farm-cancel'), s.can_cancel);
        // once the print is over (or fell through) the same model can be ordered again with today's colours
        show($('farm-repeat'), ['done', 'handed_over', 'cancelled', 'failed'].includes(s.status));

        // the model in the colour that will print it; once paid, the chosen colour
        if (s.status !== 'sliced') viewer.setColor(s.color?.hex ?? null);
        if (s.model_url && s.model_url !== shownModel) {
            shownModel = s.model_url;
            shownSupports = '';
            loadGeometryFromUrl(s.model_url).then((g) => { geom = cutForTones(g, (s.changes ?? []).map((x) => x.z)); viewer.setGeometry(geom, 1, null); viewer.setColor((s.status === 'sliced' ? state.colors.find((x) => x.slot === picked)?.hex : s.color?.hex) ?? null); paintTones(); showSupports(); }).catch(() => { shownModel = ''; });
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
    $('farm-country')?.addEventListener('change', (e) => { country = (e.target as HTMLSelectElement).value; render(); });
    pointBox?.addEventListener('pickup:change', () => render());
    $('farm-address')?.addEventListener('input', () => render());
    $('farm-recolor')?.addEventListener('click', () => ($('farm-presets') as HTMLFormElement | null)?.requestSubmit());
    $('farm-terms')?.addEventListener('change', render);

    $('farm-pay')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = $<HTMLButtonElement>('farm-pay-btn')!;
        if (btn.dataset.need) { window.location.href = `${cfg.routes.topup}&need=${btn.dataset.need}`; return; }
        const errBox = $('farm-pay-error')!;
        const form = e.target as HTMLFormElement;
        const missing = deliveryMissing();
        if (missing) { errBox.textContent = missing; show(errBox, true); return; }
        const address: Record<string, unknown> = { country };
        new FormData(form).forEach((v, k) => { const m = k.match(/^address\[(\w+)\]$/); if (m) address[m[1]] = String(v); });
        if (kind() === 'point') address.point = pointOf();
        btn.disabled = true; btn.textContent = tr('farm.order.paying'); show(errBox, false); show($('farm-topup'), false);
        const r = await post(cfg.routes.pay, {
            slot: picked, change_slots: changes, delivery, terms: ($('farm-terms') as HTMLInputElement).checked, expected_total: total(),
            video_consent: ($('farm-video-consent') as HTMLInputElement | null)?.checked ?? false,
            note: (form.elements.namedItem('note') as HTMLTextAreaElement).value, address: kind() ? address : null,
        });
        btn.textContent = tr('farm.order.pay');
        if (r.ok) { state = r.json as unknown as FarmState; render(); poll(); return; }
        errBox.textContent = String(r.json.message ?? (r.json.errors ? Object.values(r.json.errors as Record<string, string[]>)[0][0] : ''));
        show(errBox, true);
        if (r.status === 402 && r.json.topup_url) { const a = $<HTMLAnchorElement>('farm-topup')!; a.href = String(r.json.topup_url); show(a, true); }
        quoted = null;   // whatever went wrong, the price is asked again
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
