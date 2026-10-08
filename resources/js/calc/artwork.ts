/**
 * The picture window of the tools that take a drawing (logo, stamp, stencil, illuminated sign, cookie cutter):
 * Upload · Library (our CC0 silhouettes, searched in the visitor's language) · My pictures (own uploads of the last
 * 30 days). It answers with a reference the tool sends along as params.artwork. A fourth tab ("create") joins later.
 */
export interface PickedArtwork { ref: string; name: string; url: string | null }
interface Cfg { upload: string; library: string; mine: string }
interface LibraryItem { ref: string; name: string; cat: string; url: string }
interface LibraryAnswer { cats: { id: string; name: string; count: number }[]; items: LibraryItem[] }
type Words = (key: string, replace?: Record<string, string | number>) => string;
type Tab = 'upload' | 'library' | 'mine';

let cfg: Cfg | null = null;
let t: Words = (k) => k;
let dialog: HTMLDialogElement | null = null;
let resolve: ((picked: PickedArtwork | null) => void) | null = null;
let tab: Tab = 'library';
let category = '';
let asked = 0;

export function initArtwork(config: Cfg, words: Words): void { cfg = config; t = words; }

const esc = (s: string): string => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c] as string));
const icon = (name: string, cls = 'h-4 w-4'): string => `<svg class="${cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-${name}"/></svg>`;

function done(picked: PickedArtwork | null): void {
    const r = resolve; resolve = null;
    if (dialog?.open) dialog.close();
    r?.(picked);
}

/** Uploads a file; throws with the server's own words when it is refused. */
export async function uploadArtwork(file: File): Promise<PickedArtwork> {
    const fd = new FormData(); fd.append('file', file);
    const res = await fetch(cfg!.upload, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body: fd });
    const body = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(Object.values((body.errors ?? {}) as Record<string, string[]>)[0]?.[0] ?? body.message ?? '');
    return { ref: body.artwork as string, name: (body.name as string) ?? file.name, url: URL.createObjectURL(file) };
}

function build(): HTMLDialogElement {
    const d = document.createElement('dialog');
    d.className = 'tool-dialog';
    d.setAttribute('aria-labelledby', 'art-window-title');
    d.innerHTML = `<div class="flex max-h-[inherit] flex-col">
        <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
            <h2 id="art-window-title" class="text-lg font-semibold">${esc(t('toolpage.artwork.title'))}</h2>
            <button type="button" data-close class="tool-icon-btn" aria-label="${esc(t('toolpage.color.close'))}">${icon('x')}</button>
        </div>
        <div class="flex flex-wrap gap-1 border-b border-line px-4 py-2" role="tablist">
            ${(['upload', 'library', 'mine'] as Tab[]).map((k) => `<button type="button" role="tab" class="tool-tab inline-flex items-center gap-1.5" data-tab="${k}">${icon({ upload: 'upload', library: 'images', mine: 'folder-open' }[k])}${esc(t(`toolpage.artwork.tab.${k}`))}</button>`).join('')}
        </div>
        <div data-body class="min-h-[18rem] flex-1 overflow-y-auto px-4 py-3"></div>
    </div>`;
    d.addEventListener('close', () => done(null));
    d.addEventListener('click', (e) => {
        const el = e.target as HTMLElement;
        if (el === d || el.closest('[data-close]')) { done(null); return; }
        const tabBtn = el.closest<HTMLElement>('[data-tab]');
        if (tabBtn) { tab = tabBtn.dataset.tab as Tab; show(d); return; }
        const cat = el.closest<HTMLElement>('[data-cat]');
        if (cat) { category = cat.dataset.cat ?? ''; void library(d); return; }
        const tile = el.closest<HTMLElement>('[data-ref]');
        if (tile) done({ ref: tile.dataset.ref!, name: tile.dataset.name ?? '', url: tile.dataset.url ?? null });
    });
    document.body.appendChild(d);
    return d;
}

const tiles = (items: { ref: string; name: string; url: string }[]): string => `<div class="grid grid-cols-3 gap-2 sm:grid-cols-5 lg:grid-cols-6">${items.map((i) => `<button type="button" class="tool-tile" data-ref="${esc(i.ref)}" data-name="${esc(i.name)}" data-url="${esc(i.url)}">
        <img src="${esc(i.url)}" alt="" loading="lazy" decoding="async" class="h-16 w-16 object-contain">
        <span class="line-clamp-2 leading-tight">${esc(i.name)}</span>
    </button>`).join('')}</div>`;

function show(d: HTMLDialogElement): void {
    d.querySelectorAll<HTMLElement>('[data-tab]').forEach((b) => b.setAttribute('aria-selected', b.dataset.tab === tab ? 'true' : 'false'));
    const body = d.querySelector<HTMLElement>('[data-body]')!;
    if (tab === 'upload') {
        body.innerHTML = `<label data-drop class="flex min-h-[14rem] cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-line p-6 text-center hover:border-ink">
                ${icon('upload', 'h-8 w-8 text-muted')}
                <span class="font-medium">${esc(t('toolpage.artwork.drop'))}</span>
                <span class="text-sm text-muted">${esc(t('toolpage.artwork.formats'))}</span>
                <input type="file" class="sr-only" accept=".svg,image/svg+xml,image/png,image/jpeg,image/webp">
            </label><p data-msg class="mt-2 text-sm text-danger" role="alert"></p>`;
        const drop = body.querySelector<HTMLElement>('[data-drop]')!;
        const msg = body.querySelector<HTMLElement>('[data-msg]')!;
        const send = async (file: File | undefined): Promise<void> => {
            if (!file) return;
            msg.className = 'mt-2 text-sm text-muted'; msg.textContent = t('param.artwork.uploading');
            try { done(await uploadArtwork(file)); } catch (e) { msg.className = 'mt-2 text-sm text-danger'; msg.textContent = (e as Error).message || t('param.artwork.failed'); }
        };
        drop.querySelector<HTMLInputElement>('input')!.onchange = (e) => { void send((e.target as HTMLInputElement).files?.[0]); };
        drop.ondragover = (e) => { e.preventDefault(); drop.classList.add('border-ink'); };
        drop.ondragleave = () => drop.classList.remove('border-ink');
        drop.ondrop = (e) => { e.preventDefault(); drop.classList.remove('border-ink'); void send(e.dataTransfer?.files?.[0]); };
    } else if (tab === 'library') {
        body.innerHTML = `<input type="search" data-q class="field !mt-0" placeholder="${esc(t('toolpage.artwork.search'))}" aria-label="${esc(t('toolpage.artwork.search'))}">
            <div data-cats class="mt-2 flex flex-wrap gap-1.5"></div><div data-grid class="mt-3"></div>
            <p class="mt-3 text-xs text-muted">${esc(t('toolpage.artwork.licence'))}</p>`;
        let timer = 0;
        body.querySelector<HTMLInputElement>('[data-q]')!.oninput = () => { window.clearTimeout(timer); timer = window.setTimeout(() => { void library(d); }, 200); };
        void library(d);
    } else {
        body.innerHTML = `<p class="text-sm text-muted">${esc(t('toolpage.artwork.mine.note'))}</p><div data-grid class="mt-3 text-sm text-muted">${esc(t('toolpage.artwork.loading'))}</div>`;
        const grid = body.querySelector<HTMLElement>('[data-grid]')!;
        fetch(cfg!.mine, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : Promise.reject(new Error('mine'))))
            .then((b: { items: { ref: string; name: string; url: string }[] }) => { if (tab === 'mine') grid.innerHTML = b.items.length ? tiles(b.items) : esc(t('toolpage.artwork.mine.empty')); })
            .catch(() => { grid.textContent = t('toolpage.artwork.failed'); });
    }
}

async function library(d: HTMLDialogElement): Promise<void> {
    const body = d.querySelector<HTMLElement>('[data-body]')!;
    const grid = body.querySelector<HTMLElement>('[data-grid]'); const cats = body.querySelector<HTMLElement>('[data-cats]');
    if (!grid || !cats || tab !== 'library') return;
    const q = body.querySelector<HTMLInputElement>('[data-q]')?.value.trim() ?? '';
    const mine = ++asked;
    try {
        const res = await fetch(`${cfg!.library}?${new URLSearchParams({ q, ...(category ? { cat: category } : {}) })}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error('library');
        const found = (await res.json()) as LibraryAnswer;
        if (mine !== asked || tab !== 'library') return;
        cats.innerHTML = [{ id: '', name: t('toolpage.artwork.all') }, ...found.cats].map((c) => `<button type="button" class="chip !py-1 text-xs ${c.id === category ? 'chip-on' : ''}" data-cat="${esc(c.id)}" aria-pressed="${c.id === category}">${esc(c.name)}</button>`).join('');
        grid.innerHTML = found.items.length ? tiles(found.items) : `<p class="py-8 text-center text-sm text-muted">${esc(t('toolpage.artwork.none'))}</p>`;
    } catch {
        if (mine === asked) grid.textContent = t('toolpage.artwork.failed');
    }
}

/**
 * Opens the picture window; answers with the chosen picture, or null when it was closed without a choice.
 * `shelf` is the category of the library the window opens on the first time (the cookie tool: its blanks).
 */
export function pickArtwork(start: Tab = 'library', shelf = ''): Promise<PickedArtwork | null> {
    if (!dialog && shelf) category = shelf;
    dialog = dialog ?? build();
    tab = start;
    show(dialog);
    return new Promise((answer) => { resolve = answer; dialog!.showModal(); });
}
