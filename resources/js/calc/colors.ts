/**
 * Filament colours of the tools: the farm's catalogue (about 250 spools) as one palette, a swatch that shows one
 * colour, and the window a colour is chosen in (search, material, in stock only, grouped by hue, sorted by lightness).
 * A colour is always the `code` of a spool; the nine names older designs carry (white, black…) are mapped to the
 * nearest spool by the server (`legacy`).
 */
import { setPalette } from './viewer';

export interface PaletteColor { code: string; name: string; hex: string; material: string; finish: string; photo: string | null; in_stock: boolean; hue: string; light: number; search: string }
export interface PalettePayload { items: PaletteColor[]; legacy: Record<string, string>; farm: boolean; hues: string[] }
type Words = (key: string, replace?: Record<string, string | number>) => string;

const RECENT_KEY = 'mp_colors_recent';
const RECENT_MAX = 8;
let palette: PalettePayload = { items: [], legacy: {}, farm: false, hues: [] };
let byCode = new Map<string, PaletteColor>();
let finishes: Record<string, string> = {};
let t: Words = (k) => k;

export function initPalette(payload: PalettePayload, words: Words, finishNames: Record<string, string> = {}): void {
    palette = payload;
    byCode = new Map(payload.items.map((c) => [c.code, c]));
    finishes = finishNames;
    t = words;
    setPalette(payload.items);
}

/** A colour as the catalogue knows it now; a name from an older design answers with the spool nearest to it. */
export function colorOf(code: string | null | undefined): PaletteColor | null {
    if (!code) return null;
    return byCode.get(code) ?? byCode.get(palette.legacy[code] ?? '') ?? null;
}

/** The code a stored value stands for today (an old name becomes its spool; an unknown code stays as it is). */
export function spoolCode(code: string): string {
    return byCode.has(code) ? code : palette.legacy[code] ?? code;
}

export function isFarmPalette(): boolean { return palette.farm; }

/** "PLA+ matný": the kind of filament and its finish, as the customer knows them. */
export function materialLabel(c: PaletteColor): string {
    const finish = finishes[c.finish] ?? '';
    return finish ? `${c.material} ${finish}` : c.material;
}

/** Shows a colour in a swatch element: the photo of a print when there is one, else the hex; `hex` stands in for a spool that is gone. */
export function paintSwatch(el: HTMLElement, code: string | null, hex: string | null = null): void {
    const c = colorOf(code);
    const colour = c?.hex ?? hex;
    el.style.backgroundColor = colour ?? '#ffffff';
    el.style.backgroundImage = c?.photo ? `url("${c.photo}")` : colour ? '' : 'repeating-linear-gradient(45deg, #dde2ea 0 4px, #ffffff 4px 8px)';
    el.title = c ? `${c.name} · ${materialLabel(c)}` : '';
    el.dataset.code = code ?? '';
}

export function recentColors(): string[] {
    try {
        const list = JSON.parse(localStorage.getItem(RECENT_KEY) ?? '[]') as unknown;
        return Array.isArray(list) ? list.filter((c): c is string => typeof c === 'string' && byCode.has(c)).slice(0, RECENT_MAX) : [];
    } catch { return []; }
}

export function rememberColor(code: string): void {
    if (!byCode.has(code)) return;
    try { localStorage.setItem(RECENT_KEY, JSON.stringify([code, ...recentColors().filter((c) => c !== code)].slice(0, RECENT_MAX))); } catch { /* private mode */ }
}

const esc = (s: string): string => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c] as string));
const plain = (s: string): string => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

let dialog: HTMLDialogElement | null = null;
let resolve: ((code: string | null) => void) | null = null;
let onlyStock = true;

function build(): HTMLDialogElement {
    const d = document.createElement('dialog');
    d.className = 'tool-dialog';
    d.setAttribute('aria-labelledby', 'color-window-title');
    const kinds = [...new Map(palette.items.map((c) => [`${c.material}|${c.finish}`, materialLabel(c)])).entries()];
    d.innerHTML = `<div class="flex max-h-[inherit] flex-col">
        <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
            <h2 id="color-window-title" class="text-lg font-semibold">${esc(t('toolpage.color.window'))}</h2>
            <button type="button" data-close class="tool-icon-btn" aria-label="${esc(t('toolpage.color.close'))}"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><use href="#i-x"/></svg></button>
        </div>
        <div class="flex flex-wrap items-center gap-2 border-b border-line px-4 py-3">
            <input type="search" data-q class="field !mt-0 min-w-[12rem] flex-1" placeholder="${esc(t('toolpage.color.search'))}" aria-label="${esc(t('toolpage.color.search'))}">
            <select data-kind class="field !mt-0 !w-auto" aria-label="${esc(t('toolpage.color.material'))}"><option value="">${esc(t('toolpage.color.material'))}: ${esc(t('toolpage.color.all'))}</option>${kinds.map(([v, l]) => `<option value="${esc(v)}">${esc(l)}</option>`).join('')}</select>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" data-stock class="h-4 w-4 accent-ink" checked>${esc(t('toolpage.color.in_stock'))}</label>
        </div>
        <div data-list class="min-h-0 flex-1 overflow-y-auto px-4 py-3"></div>
    </div>`;
    const done = (code: string | null): void => { const r = resolve; resolve = null; if (d.open) d.close(); r?.(code); };
    d.addEventListener('close', () => done(null));
    d.addEventListener('click', (e) => {
        const el = e.target as HTMLElement;
        if (el === d || el.closest('[data-close]')) { done(null); return; }
        const tile = el.closest<HTMLElement>('[data-code]');
        if (tile) { rememberColor(tile.dataset.code!); done(tile.dataset.code!); }
    });
    d.querySelector<HTMLInputElement>('[data-q]')!.addEventListener('input', () => render(d));
    d.querySelector<HTMLSelectElement>('[data-kind]')!.addEventListener('change', () => render(d));
    d.querySelector<HTMLInputElement>('[data-stock]')!.addEventListener('change', (e) => { onlyStock = (e.target as HTMLInputElement).checked; render(d); });
    document.body.appendChild(d);
    return d;
}

function render(d: HTMLDialogElement, current: string | null = d.dataset.current ?? null): void {
    const q = plain(d.querySelector<HTMLInputElement>('[data-q]')!.value.trim());
    const kind = d.querySelector<HTMLSelectElement>('[data-kind]')!.value;
    const words = q.split(/\s+/).filter(Boolean);
    const shown = palette.items.filter((c) => (!onlyStock || c.in_stock || c.code === current) && (!kind || `${c.material}|${c.finish}` === kind) && words.every((w) => c.search.includes(w)));
    const tile = (c: PaletteColor): string => `<button type="button" class="tool-tile" data-code="${esc(c.code)}" aria-pressed="${c.code === current}" title="${esc(c.code)}">
            <span class="h-12 w-12 rounded-full border border-slate-300 bg-cover bg-center" style="background-color:${esc(c.hex)}${c.photo ? `;background-image:url('${esc(c.photo)}')` : ''}"></span>
            <span class="line-clamp-2 font-medium leading-tight">${esc(c.name)}</span>
            <span class="text-muted">${esc(materialLabel(c))}</span>
            ${c.in_stock ? '' : `<span class="rounded-full bg-warn-soft px-1.5 text-[0.65rem] text-warn">${esc(t('toolpage.color.out.badge'))}</span>`}
        </button>`;
    const recent = !q && !kind ? recentColors().map((code) => byCode.get(code)!).filter((c) => !onlyStock || c.in_stock) : [];
    const groups = palette.hues.map((hue) => ({ hue, items: shown.filter((c) => c.hue === hue).sort((a, b) => b.light - a.light) })).filter((g) => g.items.length);
    const block = (title: string, items: PaletteColor[]): string => `<section class="mb-4"><h3 class="mb-2 text-sm font-semibold text-ink">${esc(title)} <span class="font-normal text-muted">${items.length}</span></h3><div class="grid grid-cols-3 gap-2 sm:grid-cols-5 lg:grid-cols-7">${items.map(tile).join('')}</div></section>`;
    d.querySelector<HTMLElement>('[data-list]')!.innerHTML = (recent.length ? block(t('toolpage.color.recent'), recent) : '')
        + (groups.length ? groups.map((g) => block(t(`toolpage.hue.${g.hue}`), g.items)).join('') : `<p class="py-8 text-center text-sm text-muted">${esc(t('toolpage.color.none'))}</p>`);
}

/** Opens the colour window; answers with the code of the chosen spool, or null when it was closed without a choice. */
export function pickColor(current: string | null = null): Promise<string | null> {
    dialog = dialog ?? build();
    const d = dialog;
    d.dataset.current = current ?? '';
    d.querySelector<HTMLInputElement>('[data-q]')!.value = '';
    render(d, current);
    return new Promise((done) => {
        resolve = done;
        d.showModal();
        d.querySelector<HTMLElement>('[aria-pressed="true"]')?.scrollIntoView({ block: 'center' });
    });
}
