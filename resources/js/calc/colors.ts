/**
 * The colours of a design. A design is drawn in any colour: the value of a colour is the colour itself, a hex like
 * "#2a7fd5". The window offers sixteen basic colours, a free choice (the system's colour dialog and a hex field) and
 * the colours used last. Nothing here is about the farm's spools: which spool a colour is printed from is said later,
 * on the calculation and on the farm's pages.
 *
 * Two older kinds of value are still read, because stored designs carry them: the code of a spool of the farm's
 * catalogue (`items`) and the nine built-in names (white, black…; `named`). They are shown as the colour they are.
 */
import { setPalette } from './viewer';

export interface PaletteColor { code: string; name: string; hex: string; material: string; finish: string; photo: string | null; in_stock: boolean; hue: string; light: number; search: string }
export interface BasicColor { key: string; hex: string; name: string }
export interface PalettePayload { items: PaletteColor[]; legacy: Record<string, string>; farm: boolean; hues: string[]; basic?: BasicColor[]; named?: Record<string, string> }
type Words = (key: string, replace?: Record<string, string | number>) => string;

const RECENT_KEY = 'mp_colors_recent';
const RECENT_MAX = 8;
const HEX = /^#[0-9a-f]{6}$/;
let palette: PalettePayload = { items: [], legacy: {}, farm: false, hues: [] };
let byCode = new Map<string, PaletteColor>();
let basic: (BasicColor & { lab: [number, number, number] })[] = [];
let named: Record<string, string> = {};
let t: Words = (k) => k;

export function initPalette(payload: PalettePayload, words: Words, _finishNames: Record<string, string> = {}): void {
    palette = payload;
    byCode = new Map(payload.items.map((c) => [c.code, c]));
    basic = (payload.basic ?? []).map((c) => ({ ...c, hex: c.hex.toLowerCase(), lab: lab(c.hex) }));
    named = payload.named ?? {};
    t = words;
    setPalette(payload.items);      // a design stored with a spool is still painted by its code; a free colour needs no table
}

/** "#2A7FD5", "2a7fd5" → "#2a7fd5"; null for anything that is not six hex digits. */
export function hexOf(value: string | null | undefined): string | null {
    const v = (value ?? '').trim().toLowerCase();
    const h = v.startsWith('#') ? v : `#${v}`;
    return HEX.test(h) ? h : null;
}

/** CIE L*a*b* (D65) of an sRGB hex: the distance two colours are compared by (the same numbers as Palette::lab on the server). */
function lab(hex: string): [number, number, number] {
    const [r, g, b] = [1, 3, 5].map((i) => { const v = parseInt(hex.slice(i, i + 2), 16) / 255; return v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; });
    const f = (v: number): number => (v > 0.008856 ? Math.cbrt(v) : 7.787 * v + 16 / 116);
    const x = f((0.4124 * r + 0.3576 * g + 0.1805 * b) / 0.95047); const y = f(0.2126 * r + 0.7152 * g + 0.0722 * b); const z = f((0.0193 * r + 0.1192 * g + 0.9505 * b) / 1.08883);
    return [116 * y - 16, 500 * (x - y), 200 * (y - z)];
}

/** The basic colour a colour is nearest to: what it is called ("modrá" for #2a7fd5). */
function nearestBasic(hex: string): BasicColor | null {
    const want = lab(hex);
    let best: BasicColor | null = null; let bestD = Infinity;
    basic.forEach((c) => { const d = (c.lab[0] - want[0]) ** 2 + (c.lab[1] - want[1]) ** 2 + (c.lab[2] - want[2]) ** 2; if (d < bestD) { best = c; bestD = d; } });
    return best;
}

/** What a stored value looks like: a free colour is its own hex, a built-in name and a spool's code have theirs. */
function lookOf(code: string): string | null {
    return hexOf(code) ?? (named[code] ? hexOf(named[code]) : null) ?? hexOf(byCode.get(code)?.hex);
}

/**
 * A colour as the tool pages show it: its hex and the name of the basic colour nearest to it. A design stored with a
 * spool or a built-in name answers with the colour that was; no material, photo or stock is ever told here.
 */
export function colorOf(code: string | null | undefined): PaletteColor | null {
    if (!code) return null;
    const hex = lookOf(code);
    if (!hex) return null;
    return { code: hexOf(code) ?? code, name: nearestBasic(hex)?.name ?? hex, hex, material: '', finish: '', photo: null, in_stock: true, hue: '', light: 0, search: '' };
}

/**
 * The value a colour is kept as from now on: the colour itself. A built-in name becomes the colour it stands for; the
 * code of a spool (a design stored before) stays, so the design is sent back as it was.
 */
export function spoolCode(code: string): string {
    return hexOf(code) ?? (named[code] ? hexOf(named[code]) : null) ?? code;
}

export function isFarmPalette(): boolean { return palette.farm; }

/** The second half of "modrá · #2a7fd5": the colour's hex (the tool pages name no material). */
export function materialLabel(c: PaletteColor): string {
    return c.hex;
}

/** Shows a colour in a swatch element; `hex` stands in for a value the page does not know (a spool that is gone). */
export function paintSwatch(el: HTMLElement, code: string | null, hex: string | null = null): void {
    const c = colorOf(code);
    const colour = c?.hex ?? hexOf(hex);
    el.style.backgroundColor = colour ?? '#ffffff';
    el.style.backgroundImage = colour ? '' : 'repeating-linear-gradient(45deg, #dde2ea 0 4px, #ffffff 4px 8px)';
    el.title = c ? `${c.name} · ${c.hex}` : '';
    el.dataset.code = code ?? '';
}

export function recentColors(): string[] {
    try {
        const list = JSON.parse(localStorage.getItem(RECENT_KEY) ?? '[]') as unknown;
        return Array.isArray(list) ? list.filter((c): c is string => typeof c === 'string' && HEX.test(c)).slice(0, RECENT_MAX) : [];
    } catch { return []; }
}

export function rememberColor(code: string): void {
    const hex = hexOf(code);
    if (!hex) return;
    try { localStorage.setItem(RECENT_KEY, JSON.stringify([hex, ...recentColors().filter((c) => c !== hex)].slice(0, RECENT_MAX))); } catch { /* private mode */ }
}

const esc = (s: string): string => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c] as string));

let dialog: HTMLDialogElement | null = null;
let resolve: ((code: string | null) => void) | null = null;

function build(): HTMLDialogElement {
    const d = document.createElement('dialog');
    d.className = 'tool-dialog tool-dialog-color';
    d.setAttribute('aria-labelledby', 'color-window-title');
    d.innerHTML = `<div class="flex max-h-[inherit] flex-col">
        <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
            <h2 id="color-window-title" class="text-lg font-semibold">${esc(t('toolpage.color.window'))}</h2>
            <button type="button" data-close class="tool-icon-btn" aria-label="${esc(t('toolpage.color.close'))}"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><use href="#i-x"/></svg></button>
        </div>
        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-4 py-4">
            <section aria-labelledby="color-basic-title">
                <h3 id="color-basic-title" class="mb-2 text-sm font-semibold text-ink">${esc(t('toolpage.color.basic'))}</h3>
                <div data-basic class="grid grid-cols-4 gap-2 sm:grid-cols-8"></div>
            </section>
            <section aria-labelledby="color-custom-title">
                <h3 id="color-custom-title" class="mb-2 text-sm font-semibold text-ink">${esc(t('toolpage.color.custom'))}</h3>
                <form data-custom class="flex flex-wrap items-center gap-3" novalidate>
                    <input type="color" data-wheel class="tool-color-input" aria-label="${esc(t('toolpage.color.custom'))}">
                    <input type="text" data-hex class="field !mt-0 !w-36 font-mono" inputmode="text" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="7" placeholder="#2a7fd5" aria-label="${esc(t('toolpage.color.custom_hex'))}" aria-describedby="color-hex-hint">
                    <span data-preview class="h-11 w-11 shrink-0 rounded-full border border-slate-300" aria-hidden="true"></span>
                    <span data-name class="min-w-[5rem] text-sm text-muted" aria-live="polite"></span>
                    <button type="submit" data-use class="btn-ink ml-auto">${esc(t('toolpage.color.use'))}</button>
                </form>
                <p id="color-hex-hint" class="hint mt-2 !text-xs">${esc(t('toolpage.color.custom_hex'))}</p>
            </section>
            <section data-recent-box aria-labelledby="color-recent-title" class="hidden">
                <h3 id="color-recent-title" class="mb-2 text-sm font-semibold text-ink">${esc(t('toolpage.color.recent'))}</h3>
                <div data-recent class="flex flex-wrap gap-2"></div>
            </section>
        </div>
    </div>`;
    const done = (code: string | null): void => { const r = resolve; resolve = null; if (d.open) d.close(); r?.(code); };
    const wheel = d.querySelector<HTMLInputElement>('[data-wheel]')!;
    const field = d.querySelector<HTMLInputElement>('[data-hex]')!;
    d.addEventListener('close', () => done(null));
    d.addEventListener('click', (e) => {
        const el = e.target as HTMLElement;
        if (el === d || el.closest('[data-close]')) { done(null); return; }
        const tile = el.closest<HTMLElement>('[data-pick]');
        if (tile) { rememberColor(tile.dataset.pick!); done(tile.dataset.pick!); }
    });
    // the system's colour dialog and the hex field show the same colour; what is typed is checked as it is typed
    wheel.addEventListener('input', () => { field.value = wheel.value.toLowerCase(); show(d); });
    field.addEventListener('input', () => { const hex = hexOf(field.value); if (hex) wheel.value = hex; show(d); });
    field.addEventListener('focus', () => field.select());      // the field is full (seven characters): typing or pasting replaces what is there
    d.querySelector<HTMLFormElement>('[data-custom]')!.addEventListener('submit', (e) => {
        e.preventDefault();
        const hex = hexOf(field.value);
        if (hex) { rememberColor(hex); done(hex); }
    });
    document.body.appendChild(d);
    return d;
}

/** The free colour as it stands in the hex field: the preview, its name, and whether it can be used. */
function show(d: HTMLDialogElement): void {
    const field = d.querySelector<HTMLInputElement>('[data-hex]')!;
    const hex = hexOf(field.value);
    const bad = !hex && field.value.trim() !== '';
    field.setAttribute('aria-invalid', bad ? 'true' : 'false');
    field.classList.toggle('!border-danger', bad);
    const preview = d.querySelector<HTMLElement>('[data-preview]')!;
    preview.style.backgroundColor = hex ?? '#ffffff';
    preview.style.backgroundImage = hex ? '' : 'repeating-linear-gradient(45deg, #dde2ea 0 4px, #ffffff 4px 8px)';
    d.querySelector<HTMLElement>('[data-name]')!.textContent = hex ? nearestBasic(hex)?.name ?? '' : bad ? t('toolpage.color.custom_bad') : '';
    d.querySelector<HTMLButtonElement>('[data-use]')!.disabled = !hex;
}

function render(d: HTMLDialogElement, current: string | null): void {
    const now = current ? lookOf(current) : null;
    d.querySelector<HTMLElement>('[data-basic]')!.innerHTML = basic.map((c) => `<button type="button" class="tool-tile" data-pick="${esc(c.hex)}" aria-pressed="${c.hex === now}" title="${esc(c.hex)}">
            <span class="h-10 w-10 rounded-full border border-slate-300" style="background-color:${esc(c.hex)}"></span>
            <span class="leading-tight">${esc(c.name)}</span>
        </button>`).join('');
    const recent = recentColors();
    d.querySelector<HTMLElement>('[data-recent-box]')!.classList.toggle('hidden', recent.length === 0);
    d.querySelector<HTMLElement>('[data-recent]')!.innerHTML = recent.map((hex) => `<button type="button" class="tool-swatch" data-pick="${esc(hex)}" aria-pressed="${hex === now}" aria-label="${esc(`${nearestBasic(hex)?.name ?? ''} ${hex}`.trim())}" title="${esc(hex)}" style="background-color:${esc(hex)}"></button>`).join('');
    const start = now ?? basic[0]?.hex ?? '#ffffff';
    d.querySelector<HTMLInputElement>('[data-wheel]')!.value = start;
    d.querySelector<HTMLInputElement>('[data-hex]')!.value = start;
    show(d);
}

/**
 * Opens the colour window; answers with the chosen colour as a hex ("#2a7fd5", lower case), or null when it was closed
 * without a choice. `current` is the value the part has now: a hex, or what an older design carries.
 */
export function pickColor(current: string | null = null): Promise<string | null> {
    dialog = dialog ?? build();
    const d = dialog;
    render(d, current);
    return new Promise((done) => {
        resolve = done;
        d.showModal();
    });
}
