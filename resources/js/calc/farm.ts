/**
 * Print farm ("Rent a printer").
 *   bootFarmCta    calculator: the button makes the order from the shared calculation (/c/{token}), a guest's too (docs/AO.md)
 *   bootFarmStart  /farm: the way in that does not start in the calculator: the model handed over, or an STL uploaded
 *   bootFarmOrder  /farm/orders/{token}: preview of the print pose, presets, colours, pay, progress (polled)
 *   bootFarmDashboard  /admin/farm: buttons post over fetch, the answer is a short toast, cards redraw in place
 */
import { icon } from '../site/icon';
import { BufferGeometry, Float32BufferAttribute } from 'three';
import { Viewer, FacePaint, cutAtHeight, deep } from './viewer';
import { loadGeometryFromUrl } from './loaders';
import { money as moneyText } from '../site/money';
import { timelinePrint, timelineDispatch } from './order_timeline';
import { printOnly, shippingText, totalText } from './order_price';
import { estimate as roughEstimate, shapeOf, rangeFactors, type RoughConfig } from './rough';

interface Price { time: number; material: number; fixed: number; min_price_applied: boolean; net: number; vat: number; shipping: number; total: number; print_total: number; royalty?: number; inputs: { vat_percent: number } }
/** A colour on offer: one per colour, with the codes of every spool of it that could print the order ("U33" = farm U, printer 3, slot 3). Its price once sliced; before that the rates the page estimates with. */
interface Color { slot: number; name: string; kind?: string; hex: string; finish?: string | null; photo: string | null; code?: string | null; codes?: string[]; enough: boolean; price: Price | null; total: number | null; rates?: Rates; starts_now: boolean; sliced: boolean; second?: Second[]; color_id?: number; machines?: Machine[] }
/** What the price of a colour is made of on its machine: the kind's price per gram and density, the machine's rate and factors (PriceCalculator). */
interface Rates { price_per_gram: number; density: number; hourly_rate: number; time_factor: number; weight_factor: number }
/** A big model before its slice (docs/AL.md §11): what the rough price is computed from, the way the calculator's first page does it. */
interface EstimateCfg { volume_mm3: number; area_mm2: number | null; overhang_share: number | null; triangles?: number | null; rough: RoughConfig; strengths: Record<string, number>; rates: { fixed_fee: number; min_price: number; vat_percent: number; rounding: number }; royalty_czk: number; royalty_cap: number; eur_rate: number }
/** A spool for a second colour or a part; `main` names the first colour's slot on its machine when it sits in another machine than the chosen spool. */
interface Second { slot: number; name: string; kind?: string; hex: string; finish?: string | null; photo: string | null; code?: string | null; main?: number | null }
/** One spool of a machine that holds the first colour (by parts): the colour it is, and its slot there. */
interface Spool { slot: number; color_id: number; name: string; kind?: string; hex: string; finish?: string | null; photo: string | null; code?: string | null }
/** A machine holding the first colour: its slot of it (`main`, with its code) and its other spools; the parts' colours must all be here. */
interface Machine { main: number; name: string; code?: string | null; colors: Spool[] }
/** One spool of the order as chosen so far (FarmOrder::spoolLines). */
interface SpoolLine { role: string; n: number | null; part: string | null; name: string; kind: string; hex: string | null; code: string | null }
const UNIT_MM: Record<string, number> = { mm: 1, cm: 10, in: 25.4, m: 1000 };
const clampScale = (v: number, max: number) => Math.max(0.25, Math.min(max || 4, v));

/** One change of the design: where (mm, as printed), the colour it was made in, and the spool chosen for it so far. */
interface Change { z: number; hex: string; code: string | null; slot_id: number | null; name?: string | null }
/** One part of a design printed by parts: its plate, the colour the design names for it, and the spool chosen so far. */
interface Part { part: string; plate: number; label: string; hex: string | null; slot_id: number | null; color_id?: number | null; name?: string | null; copies: number; minutes?: number | null; grams?: number | null; spool_hex?: string | null; model_url?: string | null }
interface FarmState {
    token: string; number: string | null; status: string; status_text: string; stage: string | null; stage_step: number | null; stage_total: number; error: string | null; error_text: string | null; big_model?: string | null;
    /** a big model: sliced only on the customer's word (awaiting_choices), the rough price until then; `choosing` = the colours are on offer right now */
    big?: boolean; choosing?: boolean; slice_minutes?: number | null; slice_run?: number; estimate?: EstimateCfg | null;
    quality: string; strength: string; copies: number; max_copies: number | null; plates: number; plates_done: number; plate_layout: number[]; plate_now: number | null;
    scale: number; raw_bbox: { x: number; y: number; z: number } | null; slot: number | null; printer: { name: string; bed: string } | null;
    unit: string; unit_guess: { unit: string; confident: boolean } | null; second_slot?: number | null; changes?: Change[]; max_colors?: number; by_parts?: boolean; parts?: Part[]; part_count?: number; one_plate?: boolean; filament_changes?: number; one_plate_required?: boolean;
    settings?: Record<string, number> | null; admin_overrides?: Record<string, string> | null;
    dims: { x: number; y: number; z: number } | null; warnings: string[]; orientation_changed: boolean; supports: boolean; supports_mode: string; color_change_mm: number | null; second_color: { name: string; hex: string } | null;
    minutes: number | null; grams: number | null; meters: number | null; price: Price | null; total: number | null; currency: string;
    /** the smallest amount the card can be charged in this currency (the gateway's floor); a smaller shortfall is charged as this */
    min_charge?: number;
    shipping: Shipping | null; destination: string | null; tracking_url: string | null;
    colors: Color[]; color: { name: string; hex: string; finish?: string | null; code?: string | null } | null; spools?: SpoolLine[]; delivery: string; balance: number; model_url: string | null; supports_url: string | null;
    queue: { start_in: number; finish_in: number; ahead: number; blocked: string | null } | null; cancel_keep: number | null;
    print: { status: string; progress: number; snapshot_url: string | null; snapshot_at: string | null } | null;
    timelapse_url?: string | null;
    /** the finished print photographed in the photo box, on its backdrop */
    photo_url?: string | null;
    /** the last card payment made for this order from its page: what the gateway says and what the webhook did with it */
    card?: { status: string; result: string | null } | null;
    short_url?: string | null;
    can_cancel: boolean; final: boolean;
}
/** What delivery is on offer: per country the price of a parcel to a pickup point and to the door (null = not offered there). */
interface Shipping { weight_g: number; too_big: boolean; modes: string[]; countries: Record<string, { name: string; point: number | null; home: number | null; vendors: Record<string, string>[] }> }
interface Prefill { name: string; phone: string; street: string; city: string; zip: string; country: string; point: { id: string; name: string; carrier_id: string; country: string } | null; email?: string | null }
/** `guest`: an order without an account (docs/AO.md): no credit on the page, the card payment carries the e-mail typed in #farm-email */
interface FarmCfg { state: FarmState; prefill?: Prefill; routes: Record<string, string>; csrf: string; guest?: boolean; paid?: number; i18n: Record<string, string> }

const $ = <T extends HTMLElement = HTMLElement>(id: string) => document.getElementById(id) as T | null;
const show = (el: HTMLElement | null, on: boolean) => el?.classList.toggle('hidden', !on);
const esc = (s: string) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c] as string));
const duration = (min: number) => (min >= 60 ? `${Math.floor(min / 60)} h ${min % 60} min` : `${min} min`);

export function bootFarmCta(): void {
    const btn = $<HTMLButtonElement>('cta-farm');
    if (!btn) return;
    const note = $('cta-farm-note');
    const wait = note?.textContent ?? '';
    btn.addEventListener('click', async () => {
        // the calculator rewrites the address to /c/{token} (/en/c/{token} in another language) once the calculation exists
        const m = location.pathname.match(/^(?:\/[a-z]{2})?\/c\/([A-Za-z0-9_-]+)/);
        if (!m) { if (note) note.textContent = wait; show(note, true); return; }
        const start = `${btn.dataset.url}?calc=${encodeURIComponent(m[1])}`;
        if (!btn.dataset.store) { location.href = start; return; }
        // the order is made now (a guest's under the anonymous session) and its page opens (material, settings, colours, price); the button says it works
        const label = btn.innerHTML;
        btn.disabled = true; show(note, false);
        btn.textContent = btn.dataset.busy || label;
        btn.setAttribute('aria-busy', 'true');
        const done = (): void => { btn.innerHTML = label; btn.disabled = false; btn.removeAttribute('aria-busy'); };
        try {
            const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
            const res = await fetch(btn.dataset.store, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ calc: m[1], copies: Number((document.getElementById('quantity') as HTMLInputElement | null)?.value) || undefined }) });
            const json = await res.json().catch(() => ({})) as { url?: string; message?: string };
            if (res.ok && json.url) { location.href = json.url; return; }
            // the farm said why not (closed, daily limit, e-mail not confirmed…): said here, the button stays
            if ((res.status === 422 || res.status === 403) && json.message) { if (note) note.textContent = json.message; show(note, true); done(); return; }
        } catch { /* offline for a moment: the start page tries again */ }
        location.href = start;
    });
}

/** Admin order page: the model in its print pose; an order by parts as its plates, each in the colour of its spool. */
export function bootFarmAdminViewer(): void {
    const canvas = $<HTMLCanvasElement>('admin-farm-viewer');
    if (!canvas?.dataset.model) return;
    const viewer = new Viewer(canvas);
    let parts: { url: string; hex: string | null }[] = [];
    try { parts = JSON.parse(canvas.dataset.parts ?? '[]'); } catch { parts = []; }
    if (parts.length >= 2) {
        loadParts(parts.map((x) => x.url), canvas.dataset.together === '1').then(({ geom, flags }) => { viewer.setGeometry(geom, 1, null); viewer.paint(partPaint(flags, parts.map((x) => x.hex))); }).catch(() => undefined);
        return;
    }
    loadGeometryFromUrl(canvas.dataset.model).then((g) => viewer.setGeometry(g, 1, null)).catch(() => undefined);
}

/**
 * An order printed by parts as it comes off the machines. Every part on its own plate (prepared on its own, so the parts
 * do not share coordinates): laid side by side 10 mm apart, standing on the bed. All parts on one plate, the machine
 * changing spools (`together`): every part was moved by the same vector, so they stay where they are, assembled. Every
 * triangle is flagged with its part, so FacePaint colours each part in the colour of its spool.
 */
async function loadParts(urls: string[], together = false): Promise<{ geom: BufferGeometry; flags: Uint8Array }> {
    const loaded = await Promise.all(urls.map((u) => loadGeometryFromUrl(u)));
    const chunks: Float32Array[] = [];
    const counts: number[] = [];
    let x = 0;
    loaded.forEach((g0) => {
        const g = g0.index ? g0.toNonIndexed() : g0;
        g.computeBoundingBox();
        const b = g.boundingBox!;
        const pos = g.getAttribute('position');
        const out = new Float32Array(pos.count * 3);
        for (let v = 0; v < pos.count; v++) {
            out[v * 3] = together ? pos.getX(v) : pos.getX(v) - b.min.x + x;
            out[v * 3 + 1] = together ? pos.getY(v) : pos.getY(v) - (b.min.y + b.max.y) / 2;
            out[v * 3 + 2] = together ? pos.getZ(v) : pos.getZ(v) - b.min.z;
        }
        chunks.push(out);
        counts.push(pos.count / 3);
        x += b.max.x - b.min.x + 10;
    });
    const all = new Float32Array(chunks.reduce((n, c) => n + c.length, 0));
    let at = 0;
    chunks.forEach((c) => { all.set(c, at); at += c.length; });
    const geom = new BufferGeometry();
    geom.setAttribute('position', new Float32BufferAttribute(all, 3));
    geom.computeVertexNormals();
    const flags = new Uint8Array(all.length / 9);
    let t = 0;
    counts.forEach((n, i) => { flags.fill(i, t, t + n); t += n; });
    return { geom, flags };
}

/** The parts in their colours: the spool's as the swatches show it, the viewer's default for a part without one. */
function partPaint(flags: Uint8Array, hexes: (string | null)[]): FacePaint {
    const rgb = hexes.map((h) => deep(rgbOf(h ?? '')));
    return { flags, color: (f) => rgb[f] ?? rgb[0] };
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
    // a look at the model: the one handed over (from the calculator, a card, a print made before), or the one just
    // uploaded; colours and settings are chosen on the order page
    const preview = $<HTMLCanvasElement>('farm-preview');
    let viewer: Viewer | null = null;
    const showModel = (url: string, scale: number): void => {
        if (!preview) return;
        viewer ??= new Viewer(preview);
        show($('farm-preview-box'), true);
        loadGeometryFromUrl(url).then((g) => viewer!.setGeometry(g, scale, null)).catch(() => show($('farm-preview-box'), false));
    };
    if (preview?.dataset.model) showModel(preview.dataset.model, Number(preview.dataset.scale || 1) || 1);

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
            say(file.name);
            zone?.querySelector('.btn-primary')?.replaceChildren(document.createTextNode(file.name));
            showModel(`${cfg.files}/${info.uuid}/model.stl`, 1);
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
    let picked: number | null = state.slot ?? null;                    // the colour the order was made with, if it is still on offer
    let kindShown: string | null = null;                               // the kind of plastic the colours are filtered by; null = all
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
    // the price depends on the machine, and a second or part spool may move the order to another machine holding the
    // same first colour: the quote carries those spools too
    const otherSlots = (): { change_slots: (number | null)[]; part_slots: Record<string, number | null> | undefined } => ({ change_slots: changes, part_slots: resolved().part_slots });
    const quoteKey = (): string => `${resolved().slot}|${delivery}|${country}|${JSON.stringify(otherSlots())}`;
    const askQuote = async (): Promise<void> => {
        const key = quoteKey();
        if (!kind() || picked === null || quoted?.key === key || quoting === key) return;
        quoting = key;
        const r = await post(cfg.routes.quote, { slot: resolved().slot, delivery, country, ...otherSlots() });
        if (quoting === key) quoting = '';
        if (r.ok && key === quoteKey()) { quoted = { key, price: r.json.price as unknown as Price, total: Number(r.json.total) }; render(); }
    };
    // the spool of every change of the design (bottom to top); null = no change there, the colour before goes on
    let changes: (number | null)[] = (state.changes ?? []).map((c) => c.slot_id ?? null);
    const byHand = new Set<number>();                                  // rows where "no change" was chosen on purpose
    // printed by parts: the colour of every part (null = the first colour) – the customer picks colours, the farm the machine:
    // the one that holds the first colour and every part's colour (`machine`, found when the colours are rendered); a part
    // stored on the first colour's spool was put there on purpose and stays so
    const partColours: Record<string, number | null> = Object.fromEntries((state.parts ?? []).map((p) => [p.part, p.color_id ?? null]));
    const partByHand = new Set<string>((state.parts ?? []).slice(1).filter((p) => p.slot_id !== null && p.color_id === (state.parts ?? [])[0]?.color_id).map((p) => p.part));
    let machine: Machine | null = null;
    // what the server gets: the first colour's slot on that machine and, per part, its colour's slot there – never two machines'
    const resolved = (): { slot: number | null; part_slots: Record<string, number | null> | undefined } => {
        if (!state.by_parts) return { slot: picked, part_slots: undefined };
        const main = machine ? machine.main : picked;
        return { slot: main, part_slots: Object.fromEntries((state.parts ?? []).map((p) => { const id = partColours[p.part] ?? null; const s = id !== null && machine ? machine.colors.find((x) => x.color_id === id)?.slot : undefined; return [p.part, s ?? main]; })) };
    };
    // colours chosen that no single machine holds together: said on the page, nothing is sent
    const noMachine = (): boolean => !!state.by_parts && machine === null && (state.parts ?? []).slice(1).some((p) => (partColours[p.part] ?? null) !== null);
    const changeHeights = (): number[] => (state.changes ?? []).map((c) => c.z);
    let geom: BufferGeometry | null = null;
    let partFlags: Uint8Array | null = null;          // an order by parts shown as its plates: the part of every triangle
    // the colour a part prints in: the spool chosen for it (none = the main colour), the design's own colour before that
    const partHex = (p: Part): string | null => {
        const c = state.colors.find((x) => x.slot === picked);
        if (!state.choosing) return p.spool_hex ?? p.hex ?? state.color?.hex ?? null;
        const id = partColours[p.part] ?? null;
        const spools = (c?.machines ?? []).flatMap((m) => m.colors);
        return (id === null || id === c?.color_id ? c?.hex : spools.find((x) => x.color_id === id)?.hex) ?? p.hex ?? c?.hex ?? null;
    };
    // the model as the machine will print it: the first colour, then every change at its height
    const paintTones = (): void => {
        if (partFlags) {
            viewer.paint(partPaint(partFlags, (state.parts ?? []).map(partHex)));
            return;
        }
        const c = state.colors.find((x) => x.slot === picked);
        const first = (state.choosing ? c?.hex : state.color?.hex) ?? null;
        const spools = c ? [{ slot: c.slot, hex: c.hex }, ...(c.second ?? [])] : [];
        const picks = (state.changes ?? []).map((w, i) => (state.choosing ? spools.find((s) => s.slot === changes[i])?.hex ?? null : (w.slot_id === null ? null : w.hex)));
        viewer.paint(geom && changeHeights().length && first ? multiTone(geom, changeHeights(), first, picks) : null);
    };
    let timer = 0;
    const wanted = { quality: state.quality, strength: state.strength, supports: state.supports_mode || 'auto', unit: state.unit, copies: state.copies || 1, scale: state.scale || 1, by_parts: !!state.by_parts, settings: { ...(state.settings ?? {}) } as Record<string, number | string> };
    // the parts of the design printed apart or together: a preset like the others, the order is sliced again
    $('farm-by-parts')?.addEventListener('change', (e) => { wanted.by_parts = (e.target as HTMLInputElement).checked; render(); });
    // the "advanced" numbers and choices: what the fields hold, only the filled ones (a select is a word, an input a number)
    const settingInputs = [...document.querySelectorAll<HTMLInputElement | HTMLSelectElement>('#farm-advanced [data-setting]')];
    const settingsOf = (s: Record<string, number | string> | null | undefined): string => JSON.stringify(Object.fromEntries(Object.entries(s ?? {}).filter(([, v]) => v !== null && v !== undefined).sort()));
    settingInputs.forEach((i) => i.addEventListener(i instanceof HTMLSelectElement ? 'change' : 'input', () => {
        const v = i.value.trim();
        if (v === '') delete wanted.settings[i.dataset.setting!]; else wanted.settings[i.dataset.setting!] = i instanceof HTMLSelectElement ? v : Math.round(Number(v));
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
    // the price of the print alone (with VAT and the designer's share, without delivery): the big number of the page.
    // Before payment the picked colour's (or the delivery quote's, the country may change the VAT), after it the order's
    const priceNow = (): Price | null => (state.status === 'sliced' && ((kind() && quoted?.key === quoteKey() ? quoted.price : null) ?? state.colors.find((x) => x.slot === picked)?.price)) || state.price;
    const printPrice = (): number | null => printOnly(priceNow());
    // the small line under it: what the delivery chosen costs (before payment), or what it cost (after) – order_price.ts
    const shippingLine = (): string => {
        const open = state.status === 'sliced';
        return shippingText(open ? delivery : state.delivery, open ? (kind() && quoted?.key === quoteKey() ? quoted.price : null) : state.price, open, tr, money);
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

    // "U33 · U13": the spools a colour sits in (farm, printer, slot), small, after the kind
    const codeTag = (codes: (string | null | undefined)[]): string => { const list = codes.filter(Boolean) as string[]; return list.length ? `<span class="font-mono text-[11px] text-slate-500" title="${esc(tr('farm.order.codes_hint'))}">${esc(list.join(' · '))}</span>` : ''; };
    const renderColors = (): void => {
        const box = $('farm-colors')!;
        const kindsBox = $('farm-kinds');
        if (!state.colors.length) { box.innerHTML = `<p class="col-span-full text-sm text-slate-600">${esc(tr('farm.order.no_colors'))}</p>`; if (kindsBox) kindsBox.innerHTML = ''; picked = null; return; }
        if (picked === null || !state.colors.some((c) => c.slot === picked && c.enough)) picked = (state.colors.find((c) => c.enough && c.sliced) ?? state.colors.find((c) => c.enough))?.slot ?? null;
        // the kinds loaded right now, the most colours first; the list opens on ALL colours and the kinds only narrow it
        // (Roman, 17 Oct 2026: the customer picks a colour first, the kind of plastic is a filter)
        const count: Record<string, number> = {};
        state.colors.forEach((c) => { const k = c.kind ?? ''; count[k] = (count[k] ?? 0) + 1; });
        const kinds = Object.keys(count).sort((a, b) => count[b] - count[a] || a.localeCompare(b));
        if (kindShown === null || (kindShown !== '*' && !kinds.includes(kindShown))) kindShown = '*';
        if (kindsBox) {
            const chip = (value: string, label: string): string => `<button type="button" role="radio" aria-checked="${kindShown === value}" data-kind="${esc(value)}" class="seg min-h-0 px-3 py-1.5 text-sm ${kindShown === value ? 'seg-on' : ''}">${esc(label)}</button>`;
            kindsBox.innerHTML = kinds.length > 1
                ? chip('*', tr('farm.order.kind_all', { n: state.colors.length })) + kinds.map((k) => chip(k, `${k} (${count[k]})`)).join('')
                : `<span class="rounded-full bg-slate-100 px-3 py-1.5 text-sm font-semibold">${esc(kinds[0] || '')}</span>`;
            kindsBox.querySelectorAll<HTMLButtonElement>('button[data-kind]').forEach((b) => b.addEventListener('click', () => {
                kindShown = b.dataset.kind ?? '*';
                // another kind: its first colour (the machine the order was computed for first) takes the place of the chosen one
                const cur = state.colors.find((x) => x.slot === picked);
                if (kindShown !== '*' && cur?.kind !== kindShown) {
                    const own = state.colors.filter((x) => x.kind === kindShown && x.enough);
                    picked = (own.find((x) => x.sliced) ?? own[0])?.slot ?? picked;
                }
                render();
            }));
        }
        const shown = state.colors.filter((c) => kindShown === '*' || c.kind === kindShown || c.slot === picked);
        box.innerHTML = shown.map((c) => `
            <button type="button" role="radio" aria-checked="${c.slot === picked}" data-slot="${c.slot}" ${c.enough ? '' : 'disabled'}
                class="flex items-center gap-2 rounded-xl border bg-white p-2 text-left text-sm disabled:opacity-50 ${c.slot === picked ? 'border-action ring-2 ring-action' : 'border-slate-300'}">
                ${c.photo ? `<img src="${esc(c.photo)}" alt="${esc(c.name)}" data-zoom="${esc(c.photo)}" data-zoom-title="${esc(c.name)}${c.kind ? ' · ' + esc(c.kind) : ''}" class="h-10 w-10 shrink-0 cursor-zoom-in rounded-lg object-cover">` : `<span class="h-10 w-10 shrink-0 rounded-lg border border-slate-200" style="background:${esc(c.hex)}"></span>`}
                <span class="min-w-0"><span class="font-semibold">${esc(c.name)}</span>${c.kind ? `<br><span class="text-xs text-slate-500">${esc(c.kind)}</span>` : ''}${(c.codes ?? []).length ? `<br>${codeTag(c.codes ?? [])}` : ''}${c.enough ? '' : `<br><span class="text-xs text-amber-700">${esc(tr('farm.order.low_filament'))}</span>`}</span>
            </button>`).join('');
        box.querySelectorAll<HTMLButtonElement>('button[data-slot]').forEach((b) => b.addEventListener('click', () => { picked = Number(b.dataset.slot); render(); }));
        const c = state.colors.find((x) => x.slot === picked);
        $('farm-start-note')!.textContent = c ? tr(c.starts_now ? 'farm.order.starts_now' : 'farm.order.goes_to_queue') : '';
        viewer.setColor(c?.hex ?? null, c?.finish ?? null);   // the spool's finish: silk shines, matte does not
        const heading0 = $('farm-second')?.querySelector<HTMLElement>('div');
        const hint0 = $('farm-second')?.querySelector<HTMLElement>('p');
        const list0 = $('farm-second-colors');
        const dot0 = (hex: string): string => `<span class="h-10 w-10 shrink-0 rounded-lg border border-slate-200" style="background:${esc(hex)}"></span>`;
        const colorTitle = $('farm-color-title');
        if (colorTitle) colorTitle.textContent = colorTitle.dataset.plain ?? '';
        if (state.by_parts && (state.parts ?? []).length) {
            // printed by parts: the customer picks a colour for every part, the farm the machine – the one that holds the first
            // colour and every part's colour (one plate for all of them when it changes spools itself, else one plate after
            // another). A part is offered the colours some machine holds together with the first colour and the other parts'
            // colours, so what is sent (resolved) is always one machine's slots. The first part (the plate, the body) is the
            // colour chosen above: it has no row of its own below, the list above is named after it
            const parts = (state.parts ?? []).slice(1);
            const first = (state.parts ?? [])[0];
            if (first) partColours[first.part] = null;
            if (colorTitle && first) colorTitle.textContent = `${colorTitle.dataset.plain ?? ''}: ${first.label}`;
            const machines = c?.machines ?? [];
            const holds = (m: Machine, colours: (number | null)[]): boolean => colours.every((id) => id === null || m.colors.some((s) => s.color_id === id));
            parts.forEach((p) => { if (partColours[p.part] === c?.color_id) partColours[p.part] = null; });
            const offerFor = (part: string): Spool[] => {
                const others = parts.filter((p) => p.part !== part).map((p) => partColours[p.part] ?? null);
                const out: Spool[] = [];
                machines.filter((m) => holds(m, others)).forEach((m) => m.colors.forEach((s) => { if (!out.some((o) => o.color_id === s.color_id)) out.push(s); }));
                return out;
            };
            parts.forEach((p) => {
                const offer = offerFor(p.part);
                const cur = partColours[p.part] ?? null;
                if (cur !== null && offer.some((o) => o.color_id === cur)) return;
                partColours[p.part] = null;
                if (partByHand.has(p.part) || !offer.length || !p.hex) return;
                // nothing chosen yet (a fresh order, a colour no machine holds with the others): the colour nearest to the design's
                const far = (hex: string): number => { const a = rgbOf(hex); const b = rgbOf(p.hex ?? ''); return Math.hypot(a[0] - b[0], a[1] - b[1], a[2] - b[2]); };
                partColours[p.part] = [...offer].sort((a, b) => far(a.hex) - far(b.hex))[0]?.color_id ?? null;
            });
            machine = machines.find((m) => holds(m, parts.map((p) => partColours[p.part] ?? null))) ?? null;
            const any = machines.some((m) => m.colors.length > 0);
            show($('farm-second'), any);
            if (heading0) heading0.textContent = tr('farm.order.parts_title');
            if (hint0) hint0.textContent = (noMachine() ? `${tr('farm.order.colors_no_machine')} ` : '')
                + (state.one_plate ? tr('farm.order.parts_one_plate', { n: String(state.filament_changes ?? 0) }) : tr('farm.order.parts_hint', { max: String(state.max_colors ?? 4) }))
                + (state.one_plate_required ? ` ${tr('farm.order.one_plate_required')}` : '') + (machines.length > 1 ? ` ${tr('farm.order.parts_one_machine')}` : '')
                + (state.one_plate_required && (state.copies ?? 1) > 1 ? ` ${tr('farm.order.one_plate_copies')}` : '');
            if (list0 && any) {
                const tile = (part: string, s: Spool | null, name: string, kind: string, swatch: string, code: string | null | undefined): string => {
                    const on = s === null ? (partColours[part] ?? null) === null : partColours[part] === s.color_id;
                    return `<button type="button" role="radio" aria-checked="${on}" data-part="${esc(part)}" data-colour="${s?.color_id ?? ''}"
                        class="flex items-center gap-2 rounded-xl border bg-white p-2 text-left text-sm ${on ? 'border-action ring-2 ring-action' : 'border-slate-300'}">
                        ${swatch}<span class="min-w-0"><span class="font-semibold">${esc(name)}</span>${kind ? `<br><span class="text-xs text-slate-500">${esc(kind)}</span>` : ''}${code ? `<br>${codeTag([code])}` : ''}</span>
                    </button>`;
                };
                list0.innerHTML = parts.map((p) => `<div class="col-span-full mt-1 text-xs font-semibold text-slate-700">${esc(p.label)}${p.minutes ? ` <span class="font-normal text-slate-500">· ${p.minutes} min, ${p.grams ?? ''} g</span>` : ''}</div>`
                    + tile(p.part, null, tr('farm.order.part_main'), c?.name ?? '', dot0(c?.hex ?? '#ffffff'), c?.code)
                    + offerFor(p.part).map((o) => tile(p.part, o, o.name, o.kind ?? '', o.photo ? `<img src="${esc(o.photo)}" alt="" class="h-10 w-10 shrink-0 rounded-lg object-cover">` : dot0(o.hex), o.code)).join('')).join('');
                list0.querySelectorAll<HTMLButtonElement>('button[data-part]').forEach((b) => b.addEventListener('click', () => {
                    const part = b.dataset.part ?? '';
                    partColours[part] = b.dataset.colour ? Number(b.dataset.colour) : null;
                    if (partColours[part] === null) partByHand.add(part); else partByHand.delete(part);
                    render();
                }));
            }
            paintTones();      // the model in the parts' colours right away, not only after the next slice
            return;
        }
        // a plate with a raised text, a picture in colours one on another: every change picks a spool of this machine
        // (the first colour itself counts: a print may go back to it)
        const wanted = state.changes ?? [];
        const offer = wanted.length && c?.second?.length ? [{ slot: c.slot, name: c.name, kind: c.kind ?? '', hex: c.hex, photo: c.photo, code: c.code ?? null }, ...c.second] : [];
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
            const tile = (i: number, slot: number | null, name: string, kind: string, swatch: string, code: string | null = null): string => `
                <button type="button" role="radio" aria-checked="${slot === changes[i]}" data-change="${i}" data-second="${slot ?? ''}"
                    class="flex items-center gap-2 rounded-xl border bg-white p-2 text-left text-sm ${slot === changes[i] ? 'border-action ring-2 ring-action' : 'border-slate-300'}">
                    ${swatch}<span class="min-w-0"><span class="font-semibold">${esc(name)}</span>${kind ? `<br><span class="text-xs text-slate-500">${esc(kind)}</span>` : ''}${code ? `<br>${codeTag([code])}` : ''}</span>
                </button>`;
            const dot = (hex: string): string => `<span class="h-10 w-10 shrink-0 rounded-lg border border-slate-200" style="background:${esc(hex)}"></span>`;
            list.innerHTML = wanted.map((w, i) => (wanted.length > 1 ? `<div class="col-span-full mt-1 text-xs font-semibold text-slate-700">${esc(tr('farm.order.change_title', { n: i + 2, z: w.z.toFixed(1) }))}</div>` : '')
                + tile(i, null, tr(i === 0 ? 'farm.order.second_same' : 'farm.order.change_same'), tr(i === 0 ? 'farm.order.second_same_hint' : 'farm.order.change_same_hint'), dot(i === 0 ? c?.hex ?? '#ffffff' : '#ffffff'))
                + offer.filter((o) => i > 0 || o.slot !== c?.slot).map((o) => tile(i, o.slot, o.name, o.slot === c?.slot ? tr('farm.order.change_first') : (o.kind ?? ''), o.photo ? `<img src="${esc(o.photo)}" alt="" class="h-10 w-10 shrink-0 rounded-lg object-cover">` : dot(o.hex), o.code ?? null)).join('')).join('');
            list.querySelectorAll<HTMLButtonElement>('button[data-change]').forEach((b) => b.addEventListener('click', () => {
                const i = Number(b.dataset.change);
                changes[i] = b.dataset.second ? Number(b.dataset.second) : null;
                if (changes[i] === null) byHand.add(i); else byHand.delete(i);
                render();
            }));
        }
        paintTones();
    };

    /** Step 3: the colours chosen above as tags with their spool codes; only shown here, "Změnit" goes back up. */
    const renderSummary = (): void => {
        const box = $('farm-summary');
        if (!box) return;
        const c = state.colors.find((x) => x.slot === picked);
        if (!c) { box.innerHTML = `<li class="text-slate-600">${esc(tr('farm.order.no_colors'))}</li>`; return; }
        const spools = [{ slot: c.slot, name: c.name, kind: c.kind ?? '', hex: c.hex, code: c.code ?? null }, ...(c.second ?? []).map((s) => ({ slot: s.slot, name: s.name, kind: s.kind ?? '', hex: s.hex, code: s.code ?? null }))];
        const tag = (label: string, s: { name: string; kind: string; hex: string; code: string | null }): string => `<li class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white px-2.5 py-1">
            <span class="h-3.5 w-3.5 shrink-0 rounded-full border border-slate-300" style="background:${esc(s.hex)}"></span>${label ? `<span class="text-slate-500">${esc(label)}</span>` : ''}<span class="font-semibold">${esc(s.name)}</span><span class="text-xs text-slate-500">${esc(s.kind)}</span>${s.code ? codeTag([s.code]) : ''}</li>`;
        // the first colour with the code of its spool on the machine the parts settled on
        const tags = [tag('', machine && machine.main !== c.slot ? { ...spools[0], code: machine.code ?? spools[0].code } : spools[0])];
        if (state.by_parts) {
            const held = machine?.colors ?? (c.machines ?? []).flatMap((m) => m.colors);
            (state.parts ?? []).forEach((p) => { const id = partColours[p.part] ?? null; const s = id === null || id === c.color_id ? null : held.find((x) => x.color_id === id); if (s) tags.push(tag(`${p.label}:`, { name: s.name, kind: s.kind ?? '', hex: s.hex, code: s.code ?? null })); });
        } else {
            (state.changes ?? []).forEach((w, i) => {
                const s = spools.find((x) => x.slot === changes[i]);
                if (s && (i > 0 || s.slot !== c.slot)) tags.push(tag((state.changes ?? []).length === 1 ? `${tr('farm.order.second_color')}:` : `${tr('farm.order.change_title', { n: i + 2, z: w.z.toFixed(1) })}:`, s));
            });
        }
        box.innerHTML = tags.join('');
    };

    /**
     * A big model before (or during) its slice: the price estimated from the model's volume and surface and the choices
     * on the page, the way the calculator's first page estimates (rough.ts) and the way the farm prices (PriceCalculator:
     * hours × factor × rate + grams × factor × price per gram + fee, the minimum, VAT, rounded up), as a range the way the
     * first page shows it. Null until a colour is picked (the kind and the machine set the rates).
     */
    const roundUp = (v: number, step: number): number => (step <= 0 ? Math.round(v * 100) / 100 : Math.round(Math.ceil(Math.round((v / step) * 1e6) / 1e6) * step * 100) / 100);
    const estimateNow = (): { low: number; high: number; minutes: number } | null => {
        const e = state.estimate;
        const c = state.colors.find((x) => x.slot === picked);
        if (!e || !c?.rates) return null;
        const infill = Number(wanted.settings.infill ?? e.strengths[wanted.strength] ?? 15);
        const per = roughEstimate(e.rough, c.rates.density, { volume_mm3: e.volume_mm3, area_mm2: e.area_mm2 }, { material: c.kind ?? '', quality: wanted.quality, infill, supports: null, scale: wanted.scale * (UNIT_MM[wanted.unit] ?? 1), quantity: 1 });
        const copies = Math.max(1, wanted.copies);
        const grams = per.grams * copies;
        const minutes = per.minutes * copies;
        const r = c.rates;
        const f = e.rates;
        const sum = (minutes / 60) * r.time_factor * r.hourly_rate + grams * r.weight_factor * r.price_per_gram + f.fixed_fee;
        const print = roundUp(Math.max(sum, f.min_price) * (1 + f.vat_percent / 100), f.rounding);
        const royalty = e.royalty_czk > 0 ? Math.floor(Math.min(e.royalty_czk, (e.royalty_cap * print) / copies)) * copies : 0;
        const total = print + royalty;
        const step = Math.max(1, f.rounding);
        const floor = roundUp(f.min_price * (1 + f.vat_percent / 100), f.rounding);
        // crowns are where the rates are defined; an account in euros sees them at the farm's rate, rounded up to ten cents
        const toCur = (czk: number): number => (state.currency === 'EUR' ? Math.ceil((czk / e.eur_rate) * 10) / 10 : czk);
        // the range by the shape, as the first page counts it (rough.ts rangeFactors, docs/M.md §6): the model's box as it
        // prints, the estimated grams, the overhang share the first page measured (unknown counts on the wide side), the supports choice
        const factors = rangeFactors(e.rough.range, shapeOf(state.dims, 1, per.grams, e.overhang_share ?? null, e.triangles ?? null), wanted.supports === 'on' ? true : wanted.supports === 'off' ? false : null, per.minutes);
        return { low: toCur(Math.max(floor, Math.floor((total * factors.price[0]) / step) * step)), high: toCur(Math.ceil((total * factors.price[1]) / step) * step), minutes };
    };
    const renderEstimate = (awaiting: boolean, working: boolean): void => {
        const box = $('farm-estimate');
        if (!box) return;
        const est = state.big && (awaiting || working) ? estimateNow() : null;
        show(box, est !== null);
        if (!est) return;
        $('farm-estimate-price')!.textContent = tr('farm.order.estimate_range', { from: money(est.low), to: money(est.high) });
        $('farm-estimate-time')!.textContent = tr('farm.order.estimate_time', { time: duration(est.minutes) });
        const n = state.slice_minutes ?? 1;
        $('farm-estimate-wait')!.textContent = tr(awaiting ? 'farm.order.estimate_wait' : 'farm.order.estimate_running', { n });
    };

    // what the price was made of before a new calculation: said again when the new one comes back with another price
    let before: { total: number | null; printer: string | null; kind: string | null; minutes: number | null; settings: string } | null = null;
    const kindOf = (): string | null => state.colors.find((c) => c.slot === picked)?.kind ?? null;
    const settingsKey = (): string => [state.quality, state.strength, state.supports_mode, state.copies, state.scale, state.unit, !!state.by_parts, settingsOf(state.settings)].join('|');
    const sayWhy = (): void => {
        const box = $('farm-price-why');
        if (!box || !before || state.status !== 'sliced') return;
        const now = printPrice();
        if (now === null) return;                                       // the delivery quote is not back yet
        const was = before; before = null;
        if (was.total === null || Math.abs(now - was.total) < 0.005) { show(box, false); return; }
        const printer = state.printer?.name ?? null;
        const kind = kindOf();
        const reasons = [
            printer && was.printer && printer !== was.printer ? tr('farm.order.why.printer', { from: was.printer, to: printer }) : '',
            kind && was.kind && kind !== was.kind ? tr('farm.order.why.kind', { from: was.kind, to: kind }) : '',
            settingsKey() !== was.settings ? tr('farm.order.why.settings') : '',
            state.minutes !== null && was.minutes !== null && state.minutes !== was.minutes ? tr('farm.order.why.time', { from: duration(was.minutes), to: duration(state.minutes) }) : '',
        ].filter(Boolean);
        box.textContent = `${tr('farm.order.why.lead', { from: money(was.total), to: money(now) })} ${reasons.join(', ')}.`.replace(/:\s*\.$/, '.');
        show(box, true);
    };
    const render = (): void => {
        const s = state;
        const working = s.status === 'uploaded';
        // a big model (docs/AL.md §11): waiting for the choices and the colour before any slice; while its slice runs a
        // change of the choices starts it over
        const awaiting = s.status === 'awaiting_choices';
        const restartable = working && !!s.big && !s.number;
        $('farm-status')!.textContent = (working && s.stage ? tr(`farm.stage.${s.stage}`) : s.status_text) + (working && s.big_model ? ` ${s.big_model}` : '');
        // what is happening right now, as a step of the whole preparation
        const step = working && s.stage_step ? s.stage_step : 0;
        show($('farm-progress'), step > 0);
        if (step > 0) {
            ($('farm-progress-bar') as HTMLElement).style.width = `${Math.round(((step - 0.5) / (s.stage_total || 5)) * 100)}%`;
            $('farm-progress-step')!.textContent = tr('farm.stage_step', { n: step, total: s.stage_total || 5 });
        }
        show($('farm-spinner'), working || s.status === 'printing');
        // "F26-000077 · PLA+ Modrá (U33), druhá barva: bílá (U31)": every spool of the order with its code
        const spoolLine = (l: SpoolLine): string => (l.role === 'second' ? tr('farm.order.second_line', { name: l.name }) : l.role === 'change' ? tr('farm.order.change_line', { n: l.n ?? 2, name: l.name }) : l.role === 'part' ? tr('farm.order.part_line', { part: l.part ?? '', name: l.name }) : `${l.kind} ${l.name}`.trim()) + (l.code ? ` (${l.code})` : '');
        const num = $('farm-number')!; num.textContent = s.number ? [s.number, (s.spools ?? []).map(spoolLine).join(', ') || s.color?.name || ''].filter(Boolean).join(' · ') : ''; show(num, !!s.number);
        const pr = $('farm-printer'); if (pr) { pr.textContent = s.printer ? tr('farm.order.printer', { name: s.printer.name, bed: s.printer.bed }) : ''; show(pr, !!s.printer); }
        const err = $('farm-error')!; err.textContent = s.error_text ?? ''; show(err, s.status === 'failed' && !!s.error_text);
        $('farm-warnings')!.innerHTML = s.warnings.map((w) => `<li class="flex items-start gap-1.5">${icon('triangle-alert', 'mt-0.5 h-4 w-4')}<span>${esc(w)}</span></li>`).join('');
        $('farm-dims')!.textContent = s.dims ? `${s.dims.x.toFixed(1)} × ${s.dims.y.toFixed(1)} × ${s.dims.z.toFixed(1)} mm` : '';
        show($('farm-oriented'), s.orientation_changed && s.status !== 'failed');
        const balance = $('farm-balance'); if (balance) balance.textContent = money(s.balance);   // a guest has none

        const hasResult = s.minutes !== null && s.status !== 'uploaded' && !(s.status === 'failed' && !s.number);
        show($('farm-result'), hasResult);
        show($('farm-total-line'), false);   // filled below once the delivery on offer is known (sliced)
        if (hasResult) {
            const print = printPrice();
            $('farm-price')!.textContent = print !== null ? money(print) : '—';
            const shipLine = $('farm-shipping-line');
            if (shipLine) { shipLine.textContent = shippingLine(); show(shipLine, shipLine.textContent !== ''); }
            const copiesLine = $('farm-copies-line');
            if (copiesLine) {
                // "9 pieces on 2 plates (5 + 4), printed one after another" when one plate is not enough
                copiesLine.textContent = s.plates > 1 && !s.by_parts ? tr('farm.copies.plates', { n: s.copies, p: s.plates, layout: (s.plate_layout ?? []).join(' + ') }) : s.copies > 1 ? tr('farm.copies.note', { n: s.copies }) : '';
                show(copiesLine, s.copies > 1);
            }
            $('farm-time')!.textContent = duration(s.minutes!);
            // the print line follows the order: the time on the printer, what is left while it prints, printed (order_timeline.ts)
            const tlPrint = $('farm-tl-print');
            const printLine = timelinePrint(s, tr, duration);
            if (tlPrint) tlPrint.textContent = printLine ?? '';
            show($('farm-timeline'), printLine !== null);
            const tlDispatch = $('farm-tl-dispatch-text');
            if (tlDispatch) tlDispatch.textContent = timelineDispatch(s, tr) ?? tlDispatch.dataset.rule ?? '';
            const tlDelivery = $('farm-tl-delivery');
            const chosen = s.status === 'sliced' ? delivery : s.delivery;
            if (tlDelivery) tlDelivery.textContent = tr(chosen === 'pickup' ? 'farm.order.tl.pickup' : chosen ? 'farm.order.tl.parcel' : 'farm.order.tl.none');
            show($('farm-tl-dispatch'), chosen !== 'pickup');
            $('farm-grams')!.textContent = `${s.grams} g${s.meters ? ` · ${s.meters} m` : ''}`;
            $('farm-supports')!.textContent = tr(s.supports ? 'farm.order.supports_yes' : s.supports_mode === 'off' ? 'farm.order.supports_off' : 'farm.order.supports_no');
        }

        // presets stay editable until the order is paid; a failed check can be retried with other units
        const editable = s.status === 'sliced' || (s.status === 'failed' && !s.number);
        // the choices stay on the page while the slice runs (Roman, 24 Oct 2026: "after Recalculate all the choices
        // vanished"): what is changed meanwhile waits for the button, which comes back with the result – for a big
        // model the button is there at once and starts the slice over
        show($('farm-presets'), editable || working || awaiting);
        const wait = $('farm-presets-wait');
        if (wait) { wait.textContent = tr(restartable ? 'farm.order.while_slicing_big' : 'farm.order.while_slicing'); show(wait, working); }
        document.querySelectorAll<HTMLElement>('#farm-presets [data-group]').forEach((g) => g.querySelectorAll<HTMLElement>('.seg').forEach((b) => b.classList.toggle('seg-on', b.dataset.value === String(wanted[g.dataset.group as 'quality' | 'strength' | 'supports']))));
        ($('farm-unit') as HTMLSelectElement).value = wanted.unit;
        renderSizeInputs();
        const copiesInput = $<HTMLInputElement>('farm-copies');
        if (copiesInput && document.activeElement !== copiesInput) copiesInput.value = String(wanted.copies);
        const copiesNote = $('farm-copies-note');
        if (copiesNote) {
            const more = s.max_copies && wanted.copies > s.max_copies ? ` ${tr('farm.copies.more_plates', { p: Math.ceil(wanted.copies / s.max_copies) })}` : '';
            // a design that must print on one plate goes one piece at a time: said here before the price is asked for
            copiesNote.textContent = s.one_plate_required ? tr(wanted.copies > 1 ? 'farm.order.one_plate_copies' : 'farm.order.one_plate_single') : (s.max_copies ? tr('farm.copies.max', { n: s.max_copies }) + more : '');
            show(copiesNote, !!s.max_copies || !!s.one_plate_required);
        }
        const note = $('farm-unit-note')!;
        const guess = s.unit_guess;
        note.textContent = guess && guess.unit !== 'mm' ? (guess.confident && s.unit === guess.unit ? tr('farm.units.guess', { unit: tr(`farm.units.${guess.unit}`) }) : (!guess.confident && s.unit === 'mm' ? tr('farm.units.ask') : '')) : '';
        show(note, note.textContent !== '');
        settingInputs.forEach((i) => { if (document.activeElement !== i) { const v = wanted.settings[i.dataset.setting!]; i.value = v === undefined ? '' : String(v); } });
        const adv = $('farm-advanced') as HTMLDetailsElement | null;
        if (adv && Object.keys(wanted.settings).length && !adv.open) adv.open = true;
        const partsBox = $('farm-by-parts-box');
        if (partsBox) {
            const many = !!s.choosing && (s.part_count ?? 0) >= 2;
            partsBox.classList.toggle('hidden', !many); partsBox.classList.toggle('flex', many);
            ($('farm-by-parts') as HTMLInputElement).checked = wanted.by_parts;
            $('farm-by-parts-title')!.textContent = tr('farm.order.by_parts', { n: s.part_count ?? 0 });
        }
        const changed = wanted.by_parts !== !!s.by_parts || wanted.quality !== s.quality || wanted.strength !== s.strength || wanted.supports !== (s.supports_mode || 'auto') || wanted.unit !== s.unit || wanted.copies !== (s.copies || 1) || Math.abs(wanted.scale - (s.scale || 1)) > 0.0005 || settingsOf(wanted.settings) !== settingsOf(s.settings);
        const reslice = $<HTMLButtonElement>('farm-reslice');
        // while a big model is being sliced another colour is a change too: the running slice is for the one it started with
        show(reslice, (editable && changed) || (restartable && (changed || (picked !== null && picked !== s.slot))));
        if (reslice && !reslice.disabled) reslice.textContent = tr(restartable ? 'farm.order.restart_slice' : 'farm.recalculate');
        // a big model waiting for the choices: one button starts the exact computation for the colour and the settings above
        show($('farm-slice-box'), awaiting);
        if (awaiting) {
            const start = $<HTMLButtonElement>('farm-slice-start');
            if (start && start.dataset.label === undefined) start.disabled = picked === null || noMachine() || !s.colors.length;
            $('farm-slice-hint')!.textContent = tr('farm.order.slice_start_hint', { n: s.slice_minutes ?? 1 });
        }

        show($('farm-pay'), s.status === 'sliced');
        show($('farm-material'), !!s.choosing);
        if (s.choosing && s.status !== 'sliced') renderColors();
        renderEstimate(awaiting, working);   // after the colours: the picked colour's kind and machine set the rates
        if (s.status === 'sliced') {
            renderColors();
            renderSummary();
            renderDelivery();
            // short of credit: the button pays what is missing by card (the order starts when the bank confirms it);
            // a top-up of the account stays as the second way
            // the sum with the delivery right under the price: exact from the quote, until then from the carrier's list
            const totalLine = $('farm-total-line');
            if (totalLine) {
                const k = kind();
                const offered = k ? (s.shipping?.countries[country]?.[k] ?? null) : null;
                totalLine.textContent = totalText(delivery, printPrice(), k && quoted?.key === quoteKey() ? quoted.price : null, offered, tr, money);
                show(totalLine, totalLine.textContent !== '');
            }
            const payBtn = $('farm-pay-btn') as HTMLButtonElement;
            const short = total() !== null ? Math.max(0, total()! - s.balance) : 0;
            // what the card takes: the shortfall rounded up to whole crowns or euros, never under the gateway's smallest charge
            const need = short > 0.005 ? Math.max(Math.ceil(short), s.min_charge ?? 0) : 0;
            payBtn.dataset.need = need > 0 ? String(need) : '';
            payBtn.textContent = need > 0 ? (s.balance > 0.005 ? tr('farm.order.pay_card_rest', { amount: money(need), balance: money(s.balance) }) : tr('farm.order.pay_card', { amount: money(need) })) : tr('farm.order.pay');
            payBtn.disabled = picked === null || total() === null || deliveryMissing() !== null || noMachine() || !($('farm-terms') as HTMLInputElement).checked;
            // under the button: the card takes a little more than the shortfall (rounding, or the gateway's floor), the rest stays as credit
            const payNote = $('farm-pay-note');
            if (payNote) {
                payNote.textContent = need > 0 && need - short > 0.005 ? tr(need - Math.ceil(short) > 0.005 ? 'farm.order.pay_note_min' : 'farm.order.pay_note_round', { amount: money(need) }) : '';
                show(payNote, payNote.textContent !== '');
            }
            // the sum is said here, where it is paid: the print and the delivery
            const payTotal = $('farm-pay-total');
            if (payTotal) {
                const sum = total(); const print = printPrice();
                payTotal.textContent = sum !== null && print !== null && delivery ? tr('farm.order.pay_total', { total: money(sum), print: money(print), shipping: money(Math.max(0, sum - print)) }) : '';
                show(payTotal, payTotal.textContent !== '');
            }
            const topup = $<HTMLAnchorElement>('farm-topup');
            if (topup && payBtn.dataset.need && !cfg.guest) { topup.href = `${cfg.routes.topup}&need=${payBtn.dataset.need}`; show(topup, true); } else show(topup, false);
            // back from the card payment: the page waits for the bank's word; a payment that arrived but could not start the order says so
            const note = $('farm-card-note');
            const pending = (cfg.paid && !s.card) || s.card?.status === 'pending' || (s.card?.status === 'paid' && !s.card.result);
            const kept = s.card?.status === 'paid' && s.card.result && s.card.result !== 'paid';
            if (note) { note.textContent = kept ? tr('farm.order.card_kept', { reason: s.card!.result! }) : pending ? tr('farm.order.card_pending') : ''; show(note, !!(kept || pending)); }
            // another colour may mean another machine and another kind of filament: the numbers above must be computed again first
            const recolor = picked !== null && state.colors.find((c) => c.slot === picked)?.sliced === false;
            show($('farm-recolor'), recolor); show($('farm-recolor-note'), recolor); show($('farm-pay-recolor'), recolor); show($('farm-pay-btn'), !recolor);
            sayWhy();
        }

        const print = s.print && ['sent', 'printing', 'paused', 'done', 'unknown'].includes(s.print.status) && ['queued', 'printing', 'done', 'handed_over'].includes(s.status) ? s.print : null;
        show($('farm-print'), !!print || !!s.timelapse_url);
        const photo = $<HTMLImageElement>('farm-photo');
        if (photo) {
            show($('farm-photo-box'), !!s.photo_url);
            if (s.photo_url && photo.getAttribute('src') !== s.photo_url) photo.src = s.photo_url;
        }
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
        if (!s.choosing) viewer.setColor(s.color?.hex ?? null, s.color?.finish ?? null);
        // printed by parts: every part's own plate, side by side in its colour (the print STL is the first plate alone)
        const partUrls = s.by_parts ? (s.parts ?? []).map((p) => p.model_url ?? '') : [];
        const asParts = partUrls.length >= 2 && partUrls.every((u) => u !== '');
        const modelKey = asParts ? (s.one_plate ? '1 ' : '') + partUrls.join(' ') : s.model_url;
        if (modelKey && modelKey !== shownModel) {
            shownModel = modelKey;
            shownSupports = '';
            if (asParts) {
                viewer.setSupports(null);
                loadParts(partUrls, !!s.one_plate).then(({ geom: g, flags }) => { geom = g; partFlags = flags; viewer.setGeometry(g, 1, null); paintTones(); }).catch(() => { shownModel = ''; });
            } else {
                partFlags = null;
                loadGeometryFromUrl(modelKey).then((g) => { geom = cutForTones(g, (s.changes ?? []).map((x) => x.z)); viewer.setGeometry(geom, 1, null); viewer.setColor((s.choosing ? state.colors.find((x) => x.slot === picked)?.hex : s.color?.hex) ?? null); paintTones(); showSupports(); }).catch(() => { shownModel = ''; });
            }
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
        if (url === shownSupports || !shownModel || partFlags) return;      // the supports belong to the first plate alone
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
        const live = ['paid', 'queued', 'printing', 'sliced', 'awaiting_choices', 'done'].includes(state.status);
        // while it prints the page follows the camera: a new picture every 15 s
        if (fast || live) timer = window.setTimeout(poll, fast ? 2000 : state.status === 'printing' ? 15000 : ['sliced', 'awaiting_choices'].includes(state.status) ? 20000 : 8000);
    };

    document.querySelectorAll<HTMLElement>('#farm-presets [data-group] .seg').forEach((b) => b.addEventListener('click', () => {
        wanted[(b.parentElement as HTMLElement).dataset.group as 'quality' | 'strength' | 'supports'] = b.dataset.value!; render();
    }));
    $('farm-unit')?.addEventListener('change', (e) => { wanted.unit = (e.target as HTMLSelectElement).value; render(); });
    $('farm-copies')?.addEventListener('input', (e) => { const v = Number((e.target as HTMLInputElement).value); wanted.copies = Math.max(1, Math.min(64, Math.round(v) || 1)); render(); });
    $('farm-presets')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        // a slice of a small model is running: the choices made now are taken when it is done. A big model's slice is
        // started over with the new choices (the server drops the running one's result, docs/AL.md §11)
        if (state.status === 'uploaded' && !state.big) return;
        const btn = $<HTMLButtonElement>('farm-reslice');
        const recolor = $<HTMLButtonElement>('farm-recolor');
        const start = $<HTMLButtonElement>('farm-slice-start');
        before = { total: printPrice(), printer: state.printer?.name ?? null, kind: kindOf(), minutes: state.minutes, settings: settingsKey() };
        // the request takes a moment: the buttons say so and cannot be pressed twice
        [btn, recolor, start].forEach((b) => { if (b) { b.disabled = true; b.dataset.label = b.textContent ?? ''; b.textContent = tr('farm.order.reslicing'); } });
        show($('farm-error'), false);
        // the spools of the parts travel with the first colour: the server keeps them on its machine (20 Oct 2026, order 198)
        const r = await post(cfg.routes.reslice, { ...wanted, slot: resolved().slot, ...otherSlots() });
        [btn, recolor, start].forEach((b) => { if (b) { b.disabled = false; b.textContent = b.dataset.label ?? ''; delete b.dataset.label; } });
        if (r.ok) { state = r.json as unknown as FarmState; picked = state.slot ?? null; wanted.by_parts = !!state.by_parts; render(); poll(); } else { before = null; const err = $('farm-error')!; err.textContent = String(r.json.message ?? ''); show(err, true); }
    });
    document.querySelectorAll<HTMLElement>('#farm-delivery .seg').forEach((b) => b.addEventListener('click', () => { delivery = b.dataset.value!; render(); }));
    $('farm-country')?.addEventListener('change', (e) => { country = (e.target as HTMLSelectElement).value; render(); });
    pointBox?.addEventListener('pickup:change', () => render());
    $('farm-address')?.addEventListener('input', () => render());
    $('farm-recolor')?.addEventListener('click', () => ($('farm-presets') as HTMLFormElement | null)?.requestSubmit());
    // "Spočítat přesnou cenu": the same request as a recalculation, the first one of a waiting big model
    $('farm-slice-start')?.addEventListener('click', () => ($('farm-presets') as HTMLFormElement | null)?.requestSubmit());
    // "Změnit" in the summary: back up to the colours, chosen only there
    $('farm-change-colors')?.addEventListener('click', (e) => { e.preventDefault(); $('farm-material')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); });
    $('farm-terms')?.addEventListener('change', render);

    $('farm-pay')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = $<HTMLButtonElement>('farm-pay-btn')!;
        const errBox = $('farm-pay-error')!;
        const form = e.target as HTMLFormElement;
        const missing = deliveryMissing();
        if (missing) { errBox.textContent = missing; show(errBox, true); return; }
        const address: Record<string, unknown> = { country };
        new FormData(form).forEach((v, k) => { const m = k.match(/^address\[(\w+)\]$/); if (m) address[m[1]] = String(v); });
        if (kind() === 'point') address.point = pointOf();
        btn.disabled = true; btn.textContent = tr('farm.order.paying'); show(errBox, false); show($('farm-topup'), false);
        // short of credit: the gateway's page for what is missing; the webhook then pays the order with these very choices
        const r = await post(btn.dataset.need ? cfg.routes.checkout : cfg.routes.pay, {
            slot: resolved().slot, change_slots: changes, part_slots: resolved().part_slots, delivery, terms: ($('farm-terms') as HTMLInputElement).checked, expected_total: total(),
            video_consent: ($('farm-video-consent') as HTMLInputElement | null)?.checked ?? false,
            note: (form.elements.namedItem('note') as HTMLTextAreaElement).value, address: kind() ? address : null,
            // a guest: the e-mail the account is made for when the bank confirms the payment
            ...(cfg.guest ? { email: ($('farm-email') as HTMLInputElement | null)?.value.trim() ?? '' } : {}),
        });
        if (r.ok && r.json.checkout_url) { window.location.href = String(r.json.checkout_url); return; }
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
