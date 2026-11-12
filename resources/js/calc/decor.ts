/**
 * The panel "Decoration" of a tool page: a text, a relief from a photo or a logo laid on the front or the back of the
 * product. One component for every tool that takes decorations (ParametricGenerator::DECOR); what it reads and
 * writes is the list `decor` of the design's parameters, the same record the server and the engine understand
 * (docs/AQ.md, engines/python/decor.py).
 */
import { pickArtwork, uploadArtwork } from './artwork';
import { colorOf, paintSwatch, pickColor, spoolCode } from './colors';
import { icon } from '../site/icon';

export type DecorKind = 'text' | 'photo' | 'logo';
export interface DecorItem {
    kind: DecorKind; face: 'front' | 'back'; x: number; y: number; size: number; turn: number; mode: 'raised' | 'engraved' | 'flush'; depth: number; color: string | null;
    lines: string[]; typeface: string; image: string; image_name: string; invert: boolean; frame: 'circle' | 'rect'; contrast: number; smooth: number;
}
/** What the engine says about a decoration it laid: how it was really made (a face on the bed takes nothing raised). */
export interface DecorPlaced { index: number; kind: string; face: string; mode: string; on_bed: boolean; cap?: number; part?: string }
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
}

type Words = (key: string, replace?: Record<string, string | number>) => string;
const ICON: Record<DecorKind, string> = { text: 'type', photo: 'camera', logo: 'shapes' };
const esc = (s: string): string => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c] as string));

export function bootDecor(host: HTMLElement, cfg: DecorCfg, t: Words, nf: Intl.NumberFormat, changed: () => void): DecorPanel {
    const items: DecorItem[] = [];
    let open = -1;
    let said: DecorPlaced[] = []; let turned = false;
    const list = document.createElement('div'); list.className = 'space-y-2';
    const adders = document.createElement('div'); adders.className = 'flex flex-wrap gap-1.5';
    const state = document.createElement('p'); state.className = 'hint !text-xs'; state.setAttribute('aria-live', 'polite');
    host.append(adders, list, state);

    const fresh = (kind: DecorKind): DecorItem => ({
        kind, face: 'front', x: 0, y: 0, size: kind === 'photo' ? 70 : cfg.fields.size[2], turn: 0, mode: 'raised', depth: cfg.depth[kind][2], color: null,
        lines: [''], typeface: 'archivo', image: '', image_name: '', invert: false, frame: 'circle', contrast: cfg.fields.contrast[2], smooth: cfg.fields.smooth[2],
    });
    const filled = (d: DecorItem): boolean => (d.kind === 'text' ? d.lines.some((l) => l.trim() !== '') : d.image !== '');
    const num = (v: unknown, [lo, hi, def]: [number, number, number]): number => { const n = Number(v); return Number.isFinite(n) && v !== null && v !== '' ? Math.min(hi, Math.max(lo, n)) : def; };
    const from = (raw: Record<string, unknown>): DecorItem | null => {
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
        };
    };

    /** What the engine said about the decoration at this place of the list (it only knows the filled ones). */
    const placedOf = (i: number): DecorPlaced | undefined => (filled(items[i]) ? said[items.slice(0, i).filter(filled).length] : undefined);
    const summary = (d: DecorItem): string => {
        const what = d.kind === 'text' ? d.lines.filter((l) => l.trim()).join(' · ') : d.image_name;
        return [t(`decor.kind.${d.kind}`) + (what ? `: ${what}` : ''), t(`decor.face.${d.face}`)].join(' · ');
    };
    const thumb = (d: DecorItem): string | null => {
        const lib = /^lib:(.+)$/.exec(d.image);
        return lib ? `${cfg.library}/${lib[1]}.svg` : /^[0-9a-f-]{36}$/.test(d.image) ? `${cfg.file}/${d.image}` : null;
    };

    const slider = (d: DecorItem, key: 'size' | 'x' | 'y' | 'turn' | 'depth' | 'contrast' | 'smooth', unit: string, step: number): HTMLElement => {
        const [lo, hi] = key === 'depth' ? cfg.depth[d.kind] : cfg.fields[key];
        const box = document.createElement('div'); box.className = 'tool-num';
        const id = `decor-${key}`;
        box.innerHTML = `<label for="${id}" class="text-sm font-medium text-ink">${esc(t(`decor.${key}`))}</label>
            <div class="mt-1 flex items-center gap-3">
                <input type="range" min="${lo}" max="${hi}" step="${step}" value="${d[key]}" class="min-w-0 flex-1 accent-ink" aria-label="${esc(t(`decor.${key}`))}" tabindex="-1">
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
        const note = (text: string): void => { const p = document.createElement('p'); p.className = 'hint !text-xs'; p.textContent = text; box.appendChild(p); };
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
                more.onclick = () => { d.lines.push(''); render(); (list.querySelectorAll<HTMLInputElement>('[data-decor-open] input.field')[d.lines.length - 1])?.focus(); };
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
            const took = (ref: string, name: string): void => { d.image = ref; d.image_name = name; render(); changed(); };
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
            if (d.kind === 'photo') note(t('decor.photo.hint'));
        }
        box.appendChild(choice(d, 'face', ['front', 'back'], () => { head(i); changed(); }));
        box.appendChild(choice(d, 'mode', d.kind === 'photo' ? ['raised', 'engraved'] : ['raised', 'engraved', 'flush'], () => { render(); changed(); }));
        const was = placedOf(i);
        if (was && was.on_bed && was.mode !== d.mode) note(t(was.mode === 'flush' ? 'decor.note.inlaid' : 'decor.note.sunk'));
        if (d.kind === 'photo' && turned && d.face === 'front') note(t('decor.note.turned'));
        if (d.kind === 'photo') box.appendChild(choice(d, 'frame', ['circle', 'rect'], changed));
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
        if (d.kind !== 'photo' && d.mode !== 'engraved') {
            // a filament of its own: the colour window of the page; none is the product's own colour
            const row = document.createElement('div'); row.className = 'flex flex-wrap items-center gap-2 text-sm';
            const swatch = document.createElement('button'); swatch.type = 'button'; swatch.className = 'h-11 w-11 shrink-0 rounded-full border border-slate-300'; swatch.setAttribute('aria-label', t('decor.color'));
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
        }
        return box;
    };

    /** The row of a decoration: what it is, where it is; a click opens it, the cross removes it. */
    const head = (i: number): void => { const el = list.querySelector<HTMLElement>(`[data-decor-title="${i}"]`); if (el) el.textContent = summary(items[i]); };
    const render = (): void => {
        adders.replaceChildren();
        (['text', 'photo', 'logo'] as DecorKind[]).forEach((kind) => {
            const b = document.createElement('button'); b.type = 'button'; b.className = 'btn-quiet !min-h-11 gap-1.5 !px-3 !py-2 text-sm'; b.dataset.decorAdd = kind;
            b.innerHTML = `${icon('plus')}${icon(ICON[kind])}<span>${esc(t(`decor.kind.${kind}`))}</span>`;
            b.disabled = items.length >= cfg.max;
            b.onclick = () => { items.push(fresh(kind)); open = items.length - 1; render(); list.querySelector<HTMLElement>('[data-decor-open] input.field, [data-decor-open] button.btn-quiet')?.focus(); };
            adders.appendChild(b);
        });
        list.replaceChildren();
        items.forEach((d, i) => {
            const card = document.createElement('div'); card.className = 'overflow-hidden rounded-xl border border-line bg-card'; card.dataset.decorItem = String(i);
            if (i === open) card.dataset.decorOpen = '1';
            const row = document.createElement('div'); row.className = 'flex items-center gap-1';
            const toggle = document.createElement('button'); toggle.type = 'button'; toggle.className = 'flex min-h-11 min-w-0 flex-1 items-center gap-2 px-3 py-2 text-left text-sm font-medium text-ink'; toggle.setAttribute('aria-expanded', i === open ? 'true' : 'false');
            toggle.innerHTML = `${icon(ICON[d.kind], 'h-4 w-4 shrink-0')}<span class="min-w-0 flex-1 truncate" data-decor-title="${i}"></span>${icon('chevron-down', `h-4 w-4 shrink-0 transition ${i === open ? 'rotate-180' : ''}`)}`;
            toggle.onclick = () => { open = open === i ? -1 : i; render(); };
            const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'flex h-11 w-11 shrink-0 items-center justify-center text-muted hover:text-danger'; remove.setAttribute('aria-label', t('decor.remove'));
            remove.innerHTML = icon('trash-2');
            remove.onclick = () => { const had = filled(d); items.splice(i, 1); open = open === i ? -1 : open > i ? open - 1 : open; render(); if (had) changed(); };
            row.append(toggle, remove); card.appendChild(row);
            if (i === open) card.appendChild(body(d, i));
            list.appendChild(card);
            head(i);
        });
        state.textContent = items.length >= cfg.max ? t('decor.limit', { n: cfg.max }) : items.length ? '' : t('decor.empty');
    };
    render();

    return {
        read: () => items.map((d) => ({
            kind: d.kind, face: d.face, x: d.x, y: d.y, size: d.size, turn: d.turn, mode: d.mode, depth: d.depth, color: d.kind === 'photo' ? null : d.color,
            ...(d.kind === 'text' ? { lines: d.lines.map((l) => l.trim()).filter(Boolean), typeface: d.typeface } : { image: d.image || null, image_name: d.image_name, invert: d.invert }),
            ...(d.kind === 'photo' ? { frame: d.frame, contrast: d.contrast, smooth: d.smooth } : {}),
        })),
        write: (raw) => {
            const next = (Array.isArray(raw) ? raw : []).map((r) => from((r ?? {}) as Record<string, unknown>)).filter((d): d is DecorItem => d !== null).slice(0, cfg.max);
            items.splice(0, items.length, ...next);
            open = Math.min(open, items.length - 1);
            render();
        },
        add: (raw) => {
            const d = from(raw);
            if (!d || items.length >= cfg.max) return;
            items.push(d); open = items.length - 1; render();
        },
        placed: (now, turnedUp) => {
            const before = JSON.stringify([said, turned]);
            said = now ?? []; turned = turnedUp;
            // the notes under an open decoration follow what the engine made of it; a field being typed in keeps its focus
            if (before !== JSON.stringify([said, turned]) && !list.contains(document.activeElement)) render();
        },
    };
}
