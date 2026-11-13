/**
 * The decorations of a tool page: a text, a relief from a photo or a logo laid on the front or the back of the
 * product. One component for every tool that takes decorations (ParametricGenerator::DECOR); what it reads and
 * writes is the list `decor` of the design's parameters, the same record the server and the engine understand
 * (docs/AR.md, engines/python/decor.py).
 *
 * Round 2 (docs/AR.md §12): the texts are listed where the tool's own name is written (the step "Input"), so a
 * visitor finds every text in one place; the panel "Decoration" keeps the photo and the logo. A photo loses its
 * background unless the visitor keeps it. The decoration that is open has a frame in the preview: it is dragged,
 * resized and turned there, on the side of the product it lies on, and stays on the face (hold()).
 */
import { pickArtwork, uploadArtwork } from './artwork';
import { colorOf, paintSwatch, pickColor, spoolCode } from './colors';
import { icon } from '../site/icon';

export type DecorKind = 'text' | 'photo' | 'logo';
export interface DecorItem {
    kind: DecorKind; face: 'front' | 'back'; x: number; y: number; size: number; turn: number; mode: 'raised' | 'engraved' | 'flush'; depth: number; color: string | null;
    lines: string[]; typeface: string; image: string; image_name: string; invert: boolean; frame: 'circle' | 'rect'; contrast: number; smooth: number;
    background: 'keep' | 'remove';
    waiting?: boolean;          // its photo is on the way through the cut-out: the panel says so until the preview answers
}
/**
 * What the engine says about a decoration it laid: how it was really made (a face on the bed takes nothing raised),
 * whether the background of its photo went (`cutout`, absent when nobody asked), and where it lies in the preview:
 * `view.box` the frame round it, `view.hull` the corners that have to stay on the face (millimetres of the shown model).
 */
export interface DecorPlaced { index: number; kind: string; face: string; mode: string; on_bed: boolean; cap?: number; part?: string; cutout?: boolean; view?: { box: [number, number, number, number]; hull: [number, number][] } }
/** The face decorations are measured on, in the millimetres of the shown model: its middle, its width, the heights of its two sides, where decorations may lie. */
export interface DecorFace { centre: [number, number]; width: number; front_z: number; back_z: number; region: [number, number][][] }
/** What a drag of the frame in the preview has changed since the press (the viewer's FrameChange). */
export interface DecorChange { dx: number; dy: number; scale: number; turn: number }
export interface DecorCfg {
    max: number; line: number; fonts: Record<string, string>;
    depth: Record<DecorKind, [number, number, number]>;
    fields: Record<'x' | 'y' | 'size' | 'turn' | 'contrast' | 'smooth', [number, number, number]>;
    library: string; file: string;
}
export interface DecorPanel {
    read(): Record<string, unknown>[];
    write(items: unknown): void;
    /** One more decoration, opened for the visitor to go on with (a photo handed over from the home page). */
    add(item: Record<string, unknown>): void;
    placed(list: DecorPlaced[] | undefined, turnedUp: boolean): void;
    /** The decoration that is open, as the engine laid it (null when none is open, or the open one is not built yet). */
    active(): { placed: DecorPlaced; face: 'front' | 'back' } | null;
    /** Opens the decoration the engine numbers `index` (a click on it in the preview); false when there is none. */
    openPlaced(index: number): boolean;
    /** Closes the open decoration (the preview's frame goes to something else). */
    close(): void;
    /** What of a drag is allowed: the decoration stays on the face and inside the limits of its fields. */
    hold(change: DecorChange, face: DecorFace): DecorChange;
    /** A drag of the open decoration in the preview: writes its place, size and turn; answers with the words for the pointer. */
    drag(change: DecorChange, phase: 'move' | 'end', face: DecorFace): string;
}
export interface DecorOptions {
    /** Where the texts are listed (the step the tool's own name is written in); without it they stay in the panel. */
    texts?: HTMLElement | null;
    /** The open decoration changed (another one, none, or its side): the preview shows that side and frames it. */
    onOpen?: () => void;
}

type Words = (key: string, replace?: Record<string, string | number>) => string;
const ICON: Record<DecorKind, string> = { text: 'type', photo: 'camera', logo: 'shapes' };
const esc = (s: string): string => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c] as string));

/** Whether a point lies where decorations may (inside an odd number of the region's rings: a hole is a ring inside a ring). */
export function onFace(region: [number, number][][], x: number, y: number): boolean {
    let inside = false;
    for (const ring of region) {
        for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
            const [xi, yi] = ring[i]; const [xj, yj] = ring[j];
            if ((yi > y) !== (yj > y) && x < ((xj - xi) * (y - yi)) / (yj - yi) + xi) inside = !inside;
        }
    }
    return inside;
}

/** How many corners of a decoration lie off the face after a change (its middle counts for all of them). */
export function cornersOff(view: NonNullable<DecorPlaced['view']>, face: DecorFace, c: DecorChange): number {
    const mx = (view.box[0] + view.box[2]) / 2; const my = (view.box[1] + view.box[3]) / 2;
    const a = (c.turn * Math.PI) / 180; const cos = Math.cos(a); const sin = Math.sin(a);
    if (!onFace(face.region, mx + c.dx, my + c.dy)) return view.hull.length + 1;
    let off = 0;
    for (const [px, py] of view.hull) {
        const rx = (px - mx) * c.scale; const ry = (py - my) * c.scale;
        if (!onFace(face.region, mx + c.dx + rx * cos - ry * sin, my + c.dy + rx * sin + ry * cos)) off++;
    }
    return off;
}

export function bootDecor(host: HTMLElement, cfg: DecorCfg, t: Words, nf: Intl.NumberFormat, changed: () => void, opts: DecorOptions = {}): DecorPanel {
    const items: DecorItem[] = [];
    let open = -1;
    let said: DecorPlaced[] = []; let turned = false;
    let from: { x: number; y: number; size: number; turn: number; off: number; last: DecorChange } | null = null;     // the open decoration as a drag found it
    // two places, one list: the texts where the tool's own name is written, the photo and the logo in the panel
    const places: { kinds: DecorKind[]; list: HTMLElement; adders: HTMLElement; state: HTMLElement }[] = [];
    const place = (into: HTMLElement, kinds: DecorKind[]): void => {
        const list = document.createElement('div'); list.className = 'space-y-2';
        const adders = document.createElement('div'); adders.className = 'flex flex-wrap gap-1.5';
        const state = document.createElement('p'); state.className = 'hint !text-xs'; state.setAttribute('aria-live', 'polite');
        // (the texts follow the name they add to: the list first, the button to add one under it)
        if (into === host) into.append(adders, list, state); else into.append(list, adders, state);
        places.push({ kinds, list, adders, state });
    };
    if (opts.texts) { place(opts.texts, ['text']); place(host, ['photo', 'logo']); } else place(host, ['text', 'photo', 'logo']);
    const inLists = (el: Element | null): boolean => !!el && places.some((p) => p.list.contains(el));
    const openCard = (): HTMLElement | null => document.querySelector<HTMLElement>('[data-decor-open]');

    const fresh = (kind: DecorKind): DecorItem => ({
        kind, face: 'front', x: 0, y: 0, size: kind === 'photo' ? 70 : cfg.fields.size[2], turn: 0, mode: 'raised', depth: cfg.depth[kind][2], color: null,
        lines: [''], typeface: 'archivo', image: '', image_name: '', invert: false, frame: 'circle', contrast: cfg.fields.contrast[2], smooth: cfg.fields.smooth[2],
        background: 'remove',       // a new photo loses its background; a stored one keeps what it was stored with (from())
    });
    const filled = (d: DecorItem): boolean => (d.kind === 'text' ? d.lines.some((l) => l.trim() !== '') : d.image !== '');
    const num = (v: unknown, [lo, hi, def]: [number, number, number]): number => { const n = Number(v); return Number.isFinite(n) && v !== null && v !== '' ? Math.min(hi, Math.max(lo, n)) : def; };
    const from_ = (raw: Record<string, unknown>, stored: boolean): DecorItem | null => {
        const kind = raw.kind as DecorKind;
        if (!['text', 'photo', 'logo'].includes(kind)) return null;
        const d = fresh(kind);
        const colour = typeof raw.color === 'string' ? raw.color : (raw.color as { code?: string } | null)?.code ?? null;
        return {
            ...d,
            face: raw.face === 'back' ? 'back' : 'front',
            x: num(raw.x, cfg.fields.x), y: num(raw.y, cfg.fields.y), size: num(raw.size, [cfg.fields.size[0], cfg.fields.size[1], d.size]), turn: num(raw.turn, cfg.fields.turn),
            mode: raw.mode === 'engraved' || raw.mode === 'flush' ? raw.mode : 'raised',
            depth: num(raw.depth, cfg.depth[kind]),
            color: colour ? spoolCode(colour) : null,
            lines: Array.isArray(raw.lines) && raw.lines.length ? raw.lines.slice(0, 3).map((l) => String(l ?? '').slice(0, cfg.line)) : [''],
            typeface: typeof raw.typeface === 'string' && cfg.fonts[raw.typeface] ? raw.typeface : 'archivo',
            image: typeof raw.image === 'string' ? raw.image : '', image_name: typeof raw.image_name === 'string' ? raw.image_name : '',
            invert: Boolean(raw.invert), frame: raw.frame === 'rect' ? 'rect' : 'circle',
            contrast: num(raw.contrast, cfg.fields.contrast), smooth: num(raw.smooth, cfg.fields.smooth),
            // a design stored before photos lost their background has no word about it: it keeps the background it was built with
            background: raw.background === 'remove' || raw.background === 'keep' ? raw.background : stored ? 'keep' : 'remove',
        };
    };

    /** What the engine said about the decoration at this place of the list (it only knows the filled ones). */
    const placedOf = (i: number): DecorPlaced | undefined => (items[i] && filled(items[i]) ? said[items.slice(0, i).filter(filled).length] : undefined);
    const summary = (d: DecorItem): string => {
        const what = d.kind === 'text' ? d.lines.filter((l) => l.trim()).join(' · ') : d.image_name;
        return [t(`decor.kind.${d.kind}`) + (what ? `: ${what}` : ''), t(`decor.face.${d.face}`)].join(' · ');
    };
    const thumb = (d: DecorItem): string | null => {
        const lib = /^lib:(.+)$/.exec(d.image);
        return lib ? `${cfg.library}/${lib[1]}.svg` : /^[0-9a-f-]{36}$/.test(d.image) ? `${cfg.file}/${d.image}` : null;
    };
    const opened = (): void => { from = null; opts.onOpen?.(); };

    const slider = (d: DecorItem, key: 'size' | 'x' | 'y' | 'turn' | 'depth' | 'contrast' | 'smooth', unit: string, step: number): HTMLElement => {
        const [lo, hi] = key === 'depth' ? cfg.depth[d.kind] : cfg.fields[key];
        const box = document.createElement('div'); box.className = 'tool-num';
        const id = `decor-${key}`;
        box.innerHTML = `<label for="${id}" class="text-sm font-medium text-ink">${esc(t(`decor.${key}`))}</label>
            <div class="mt-1 flex items-center gap-3">
                <input type="range" min="${lo}" max="${hi}" step="${step}" value="${d[key]}" class="min-w-0 flex-1 accent-ink" aria-label="${esc(t(`decor.${key}`))}" tabindex="-1" data-decor-range="${key}">
                <span class="tool-unit" data-unit="${unit}"><input id="${id}" type="number" inputmode="decimal" min="${lo}" max="${hi}" step="${step}" value="${d[key]}" class="field !mt-0"></span>
            </div>`;
        const [range, number] = [...box.querySelectorAll<HTMLInputElement>('input')];
        const set = (from: HTMLInputElement, to: HTMLInputElement): void => {
            const v = Number(from.value);
            if (from.value === '' || Number.isNaN(v) || v < lo || v > hi) return;      // the field keeps what was typed, the design its last good value
            d[key] = v; to.value = from.value;
        };
        range.addEventListener('input', () => set(range, number));
        number.addEventListener('input', () => set(number, range));
        return box;
    };
    /** The fields of the open decoration show what a drag in the preview made of it. */
    const show = (d: DecorItem): void => {
        (['x', 'y', 'size', 'turn'] as const).forEach((key) => {
            const number = document.getElementById(`decor-${key}`) as HTMLInputElement | null;
            const range = openCard()?.querySelector<HTMLInputElement>(`[data-decor-range="${key}"]`);
            if (number) number.value = String(d[key]);
            if (range) range.value = String(d[key]);
        });
    };
    const choice = <K extends 'face' | 'mode' | 'frame'>(d: DecorItem, key: K, values: DecorItem[K][], after?: () => void): HTMLElement => {
        const box = document.createElement('fieldset');
        box.innerHTML = `<legend class="text-sm font-medium text-ink">${esc(t(`decor.${key}`))}</legend>`;
        const row = document.createElement('div'); row.className = 'mt-1 flex flex-wrap gap-1.5'; row.setAttribute('role', 'radiogroup');
        values.forEach((v) => {
            const label = document.createElement('label'); label.className = 'tool-choice inline-flex min-h-11 items-center !px-3.5';
            const radio = document.createElement('input'); radio.type = 'radio'; radio.name = `decor-${key}`; radio.value = v; radio.className = 'sr-only'; radio.checked = d[key] === v;
            radio.addEventListener('change', () => { d[key] = v; after?.(); });
            label.append(radio, t(`decor.${key}.${v}`));
            row.appendChild(label);
        });
        box.appendChild(row);
        return box;
    };

    const body = (d: DecorItem, i: number): HTMLElement => {
        const box = document.createElement('div'); box.className = 'space-y-3 border-t border-line p-3';
        const note = (text: string, cls = 'hint !text-xs'): HTMLElement => { const p = document.createElement('p'); p.className = cls; p.textContent = text; box.appendChild(p); return p; };
        const was = placedOf(i);
        if (d.kind === 'text') {
            d.lines.forEach((line, n) => {
                const label = document.createElement('label'); label.className = 'lbl';
                label.innerHTML = `<span class="text-sm font-medium">${esc(t('decor.line', { n: n + 1 }))}</span>`;
                const input = document.createElement('input'); input.className = 'field'; input.maxLength = cfg.line; input.value = line; input.autocomplete = 'off';
                input.addEventListener('input', () => { d.lines[n] = input.value; head(i); });
                label.appendChild(input); box.appendChild(label);
            });
            if (d.lines.length < 3) {
                const more = document.createElement('button'); more.type = 'button'; more.className = 'min-h-11 text-left text-sm font-semibold text-ink underline'; more.textContent = t('decor.line.add');
                more.onclick = () => { d.lines.push(''); render(); (openCard()?.querySelectorAll<HTMLInputElement>('input.field')[d.lines.length - 1])?.focus(); };
                box.appendChild(more);
            }
            const label = document.createElement('label'); label.className = 'lbl';
            label.innerHTML = `<span class="text-sm font-medium">${esc(t('decor.font'))}</span>`;
            const select = document.createElement('select'); select.className = 'field';
            Object.entries(cfg.fonts).forEach(([key, name]) => { const o = document.createElement('option'); o.value = key; o.textContent = name; o.selected = key === d.typeface; select.appendChild(o); });
            select.addEventListener('change', () => { d.typeface = select.value; });
            label.appendChild(select); box.appendChild(label);
        } else {
            const row = document.createElement('div'); row.className = 'flex items-center gap-3';
            const url = thumb(d);
            if (url) { const pic = document.createElement('div'); pic.className = 'flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-page'; pic.innerHTML = `<img src="${esc(url)}" alt="" class="max-h-full max-w-full object-contain">`; row.appendChild(pic); }
            const pick = document.createElement('button'); pick.type = 'button'; pick.className = 'btn-quiet !min-h-10 gap-1.5 !px-3 !py-2 text-sm';
            pick.innerHTML = `${icon(d.kind === 'photo' ? 'camera' : 'image')}<span>${esc(t(`decor.${d.kind}.${d.image ? 'change' : 'choose'}`))}</span>`;
            const msg = document.createElement('p'); msg.className = 'hidden text-sm';
            const took = (ref: string, name: string): void => { d.image = ref; d.image_name = name; d.waiting = d.kind === 'photo' && d.background === 'remove'; render(); changed(); };
            if (d.kind === 'photo') {
                // a photo is the visitor's own: straight from the phone's gallery or camera, no library in between
                const file = document.createElement('input'); file.type = 'file'; file.accept = 'image/png,image/jpeg,image/webp'; file.className = 'sr-only'; file.tabIndex = -1;
                file.addEventListener('click', (e) => e.stopPropagation());
                file.addEventListener('input', (e) => e.stopPropagation());
                file.onchange = async () => {
                    const f = file.files?.[0]; if (!f) return;
                    msg.className = 'text-sm text-muted'; msg.textContent = t('param.artwork.uploading');
                    try { const got = await uploadArtwork(f); took(got.ref, got.name); } catch (e) { msg.className = 'text-sm text-danger'; msg.textContent = (e as Error).message || t('param.artwork.failed'); }
                    file.value = '';
                };
                pick.onclick = () => file.click();
                row.append(pick, file);
            } else {
                pick.onclick = async () => { const got = await pickArtwork(d.image ? 'library' : 'upload'); if (got) took(got.ref, got.name); };
                row.appendChild(pick);
            }
            box.append(row, msg);
            if (d.kind === 'photo') {
                // the background goes unless the visitor keeps it: the subject alone stands out of the product
                const label = document.createElement('label'); label.className = 'flex min-h-11 items-center gap-3 text-sm text-ink';
                const tick = document.createElement('input'); tick.type = 'checkbox'; tick.className = 'h-5 w-5 shrink-0 accent-ink'; tick.checked = d.background === 'keep'; tick.dataset.decorKeep = '1';
                tick.addEventListener('change', () => { d.background = tick.checked ? 'keep' : 'remove'; d.waiting = !tick.checked && d.image !== ''; render(); });
                label.append(tick, t('decor.background.keep')); box.appendChild(label);
                if (d.waiting && d.image) {
                    // the cut-out takes a few seconds the first time a photo is seen: say what is going on, with something that moves
                    const line = note(t('decor.background.working'), 'flex items-center gap-2 text-sm text-ink');
                    line.setAttribute('role', 'status'); line.dataset.decorWorking = '1';
                    line.insertAdjacentHTML('afterbegin', icon('refresh-cw', 'h-4 w-4 animate-spin'));
                    const bar = document.createElement('div'); bar.className = 'h-1.5 overflow-hidden rounded-full bg-line';
                    bar.innerHTML = '<div class="h-full w-1/3 animate-pulse rounded-full bg-ink"></div>';
                    box.appendChild(bar);
                } else if (d.background === 'remove' && was && was.cutout === false) note(t('decor.background.failed'), 'text-sm text-danger');
                else note(t(d.background === 'remove' ? 'decor.background.hint' : 'decor.photo.hint'));
            }
        }
        box.appendChild(choice(d, 'face', ['front', 'back'], () => { head(i); opened(); changed(); }));
        box.appendChild(choice(d, 'mode', d.kind === 'photo' ? ['raised', 'engraved'] : ['raised', 'engraved', 'flush'], () => { render(); changed(); }));
        if (was && was.on_bed && was.mode !== d.mode) note(t(was.mode === 'flush' ? 'decor.note.inlaid' : 'decor.note.sunk'));
        if (d.kind === 'photo' && turned && d.face === 'front') note(t('decor.note.turned'));
        if (d.kind === 'photo') box.appendChild(choice(d, 'frame', ['circle', 'rect'], changed));
        if (was?.view) note(t('decor.drag.hint'));
        box.appendChild(slider(d, 'size', '%', 1));
        if (was?.cap) note(t('decor.note.cap', { n: nf.format(was.cap) }));
        box.appendChild(slider(d, 'x', '%', 1));
        box.appendChild(slider(d, 'y', '%', 1));
        box.appendChild(slider(d, 'turn', '°', 1));
        box.appendChild(slider(d, 'depth', 'mm', 0.2));
        if (d.kind === 'photo') {
            box.appendChild(slider(d, 'contrast', '%', 1));
            box.appendChild(slider(d, 'smooth', '', 0.05));
        }
        if (d.kind !== 'text') {
            const label = document.createElement('label'); label.className = 'flex items-center gap-3 text-sm text-ink';
            const tick = document.createElement('input'); tick.type = 'checkbox'; tick.className = 'h-5 w-5 accent-ink'; tick.checked = d.invert;
            tick.addEventListener('change', () => { d.invert = tick.checked; });
            label.append(tick, t('decor.invert')); box.appendChild(label);
        }
        if (d.kind === 'photo' || d.mode !== 'engraved') {
            // a filament of its own: the colour window of the page; none is the product's own colour
            const row = document.createElement('div'); row.className = 'flex flex-wrap items-center gap-2 text-sm';
            const swatch = document.createElement('button'); swatch.type = 'button'; swatch.className = 'h-11 w-11 shrink-0 rounded-full border border-slate-300'; swatch.setAttribute('aria-label', t('decor.color'));
            swatch.dataset.decorColor = '1';
            paintSwatch(swatch, d.color);
            const name = document.createElement('span'); name.className = 'text-ink'; name.textContent = `${t('decor.color')}: ${d.color ? (colorOf(d.color)?.name ?? d.color) : t('decor.color.same')}`;
            swatch.onclick = async () => { const got = await pickColor(d.color); if (got) { d.color = got; render(); changed(); } };
            row.append(swatch, name);
            if (d.color) {
                const clear = document.createElement('button'); clear.type = 'button'; clear.className = 'min-h-11 text-sm text-muted underline'; clear.textContent = t('decor.color.clear');
                clear.onclick = () => { d.color = null; if (d.mode === 'flush') d.mode = 'raised'; render(); changed(); };
                row.appendChild(clear);
            }
            box.appendChild(row);
            if (d.mode === 'flush' && !d.color) note(t('decor.note.flush'));
            if (d.color && d.kind === 'photo') note(t('decor.color.relief'));
        }
        return box;
    };

    /** The row of a decoration: what it is, where it is; a click opens it, the cross removes it. */
    const head = (i: number): void => { const el = document.querySelector<HTMLElement>(`[data-decor-title="${i}"]`); if (el) el.textContent = summary(items[i]); };
    const render = (): void => {
        places.forEach(({ kinds, list, adders, state }) => {
            const texts = kinds.length === 1 && kinds[0] === 'text';
            adders.replaceChildren();
            kinds.forEach((kind) => {
                const b = document.createElement('button'); b.type = 'button'; b.className = 'btn-quiet !min-h-11 gap-1.5 !px-3 !py-2 text-sm'; b.dataset.decorAdd = kind;
                b.innerHTML = `${icon('plus')}${icon(ICON[kind])}<span>${esc(t(texts ? 'decor.text.more' : `decor.kind.${kind}`))}</span>`;
                b.disabled = items.length >= cfg.max;
                b.onclick = () => { items.push(fresh(kind)); open = items.length - 1; render(); opened(); openCard()?.querySelector<HTMLElement>('input.field, button.btn-quiet')?.focus(); };
                adders.appendChild(b);
            });
            list.replaceChildren();
            items.forEach((d, i) => {
                if (!kinds.includes(d.kind)) return;
                const card = document.createElement('div'); card.className = 'overflow-hidden rounded-xl border border-line bg-card'; card.dataset.decorItem = String(i);
                if (i === open) card.dataset.decorOpen = '1';
                const row = document.createElement('div'); row.className = 'flex items-center gap-1';
                const toggle = document.createElement('button'); toggle.type = 'button'; toggle.className = 'flex min-h-11 min-w-0 flex-1 items-center gap-2 px-3 py-2 text-left text-sm font-medium text-ink'; toggle.setAttribute('aria-expanded', i === open ? 'true' : 'false');
                toggle.innerHTML = `${icon(ICON[d.kind], 'h-4 w-4 shrink-0')}<span class="min-w-0 flex-1 truncate" data-decor-title="${i}"></span>${icon('chevron-down', `h-4 w-4 shrink-0 transition ${i === open ? 'rotate-180' : ''}`)}`;
                toggle.onclick = () => { open = open === i ? -1 : i; render(); opened(); };
                const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'flex h-11 w-11 shrink-0 items-center justify-center text-muted hover:text-danger'; remove.setAttribute('aria-label', t('decor.remove'));
                remove.innerHTML = icon('trash-2');
                remove.onclick = () => { const had = filled(d); items.splice(i, 1); open = open === i ? -1 : open > i ? open - 1 : open; render(); opened(); if (had) changed(); };
                row.append(toggle, remove); card.appendChild(row);
                if (i === open) card.appendChild(body(d, i));
                list.appendChild(card);
                head(i);
            });
            const mine = items.filter((d) => kinds.includes(d.kind)).length;
            state.textContent = items.length >= cfg.max ? t('decor.limit', { n: cfg.max }) : texts ? t('decor.text.lead') : mine ? '' : t(places.length > 1 ? 'decor.empty.pictures' : 'decor.empty');
        });
    };
    render();

    return {
        read: () => items.map((d) => ({
            kind: d.kind, face: d.face, x: d.x, y: d.y, size: d.size, turn: d.turn, mode: d.mode, depth: d.depth, color: d.color,
            ...(d.kind === 'text' ? { lines: d.lines.map((l) => l.trim()).filter(Boolean), typeface: d.typeface } : { image: d.image || null, image_name: d.image_name, invert: d.invert }),
            ...(d.kind === 'photo' ? { frame: d.frame, contrast: d.contrast, smooth: d.smooth, background: d.background } : {}),
        })),
        write: (raw) => {
            const next = (Array.isArray(raw) ? raw : []).map((r) => from_((r ?? {}) as Record<string, unknown>, true)).filter((d): d is DecorItem => d !== null).slice(0, cfg.max);
            items.splice(0, items.length, ...next);
            open = Math.min(open, items.length - 1);
            render(); opened();
        },
        add: (raw) => {
            const d = from_(raw, false);
            if (!d || items.length >= cfg.max) return;
            d.waiting = d.kind === 'photo' && d.background === 'remove' && d.image !== '';
            items.push(d); open = items.length - 1; render(); opened();
        },
        placed: (now, turnedUp) => {
            const before = JSON.stringify([said, turned]);
            const waited = items.some((d) => d.waiting);
            said = now ?? []; turned = turnedUp;
            items.forEach((d, i) => { if (d.waiting && placedOf(i)) d.waiting = false; });
            // the notes under an open decoration follow what the engine made of it; a field being typed in keeps its focus
            if ((waited || before !== JSON.stringify([said, turned])) && !inLists(document.activeElement)) render();
        },
        active: () => {
            const was = open >= 0 ? placedOf(open) : undefined;
            return was?.view ? { placed: was, face: items[open].face } : null;
        },
        openPlaced: (index) => {
            const at = items.findIndex((d, i) => placedOf(i)?.index === index);
            if (at < 0) return false;
            if (open !== at) { open = at; render(); opened(); }
            return true;
        },
        close: () => { if (open >= 0) { open = -1; render(); opened(); } },
        hold: (c, face) => {
            const d = items[open]; const view = placedOf(open)?.view;
            if (!d || !view) return c;
            from ??= { x: d.x, y: d.y, size: d.size, turn: d.turn, off: cornersOff(view, face, { dx: 0, dy: 0, scale: 1, turn: 0 }), last: { dx: 0, dy: 0, scale: 1, turn: 0 } };
            const k = face.width / 100; const sign = d.face === 'back' ? -1 : 1;
            const within = (v: number, lo: number, hi: number): number => Math.max(lo, Math.min(hi, v));
            const [xlo, xhi] = cfg.fields.x; const [ylo, yhi] = cfg.fields.y; const [slo, shi] = cfg.fields.size;
            // inside the limits of the fields first (the middle within half the face each way, the size 10 to 100 %)…
            const asked: DecorChange = {
                dx: sign * (within(from.x + sign * c.dx / k, xlo, xhi) - from.x) * k, dy: (within(from.y + c.dy / k, ylo, yhi) - from.y) * k,
                scale: within(from.size * c.scale, slo, shi) / from.size, turn: c.turn,
            };
            // …then on the face: no more corners over its edge or in the eyelet than the drag started with. A move that
            // would leave slides along the edge instead (one direction of it may still be free)
            const ok = (x: DecorChange): boolean => cornersOff(view, face, x) <= from!.off;
            const tries: DecorChange[] = [asked, { ...asked, dy: from.last.dy }, { ...asked, dx: from.last.dx }];
            from.last = tries.find(ok) ?? from.last;
            return { ...from.last };
        },
        drag: (c, phase, face) => {
            const d = items[open];
            if (!d) return '';
            from ??= { x: d.x, y: d.y, size: d.size, turn: d.turn, off: 0, last: c };
            const k = face.width / 100; const sign = d.face === 'back' ? -1 : 1;
            d.x = Math.round(from.x + (sign * c.dx) / k); d.y = Math.round(from.y + c.dy / k);
            d.size = Math.round(from.size * c.scale);
            d.turn = ((Math.round(from.turn + sign * c.turn) + 540) % 360) - 180;
            show(d);
            const said_ = c.turn ? `${nf.format(d.turn)}°` : c.scale !== 1 ? `${nf.format(d.size)} %` : `${nf.format(d.x)} · ${nf.format(d.y)} %`;
            if (phase === 'end') { from = null; changed(); }
            return said_;
        },
    };
}
