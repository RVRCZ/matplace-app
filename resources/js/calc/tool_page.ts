/**
 * The one page every tool lives on (resources/views/tools/page.blade.php). What all tools share is here: the viewer
 * with its views, x-ray, spread view, print bed and picking; the status line; the price card with the main action
 * and the download menu; warnings shown in the section they belong to; the marked step as the panel scrolls;
 * undo / redo and the settings kept in this browser. What differs is the input of each tool: a text, a file,
 * a photo. That part is a module (param.ts, relief.ts, mold.ts, repair.ts, check.ts, figure.ts) handed this Stage.
 */
import { BufferGeometry } from 'three';
import { Viewer, Region, FacePaint, Piece, ViewName } from './viewer';
import { price as priceText } from '../site/money';
import { estimate, price, range, RoughConfig, Profile } from './rough';
import { initPalette, PalettePayload } from './colors';
import { initArtwork } from './artwork';
import { bootParam } from './param';
import { bootRelief } from './relief';
import { bootMoldPage } from './mold';
import { bootRepairPage } from './repair';
import { bootCheckPage } from './check';
import { bootFigure } from './figure';
import { bootArt } from './art';
import { bootEdit } from './edit';
import { FileInfo } from './api';
import { loadGeometryFromUrl } from './loaders';

export interface ToolCfg {
    tool: string; module: string; locale: string; next: 'inquiry' | 'farm' | 'download'; home: string; files: string; tools: string; zip: string;
    bed: { x: number; y: number; z: number; margin: number };
    colors: PalettePayload;
    price: PriceConfig | null;
    artwork: { upload: string; library: string; mine: string; file: string };
    i18n: Record<string, unknown>;
}
export interface PriceConfig { rough: RoughConfig; orientation_profiles: Profile[]; round_to: number; materials: { code: string; density: number }[]; default_material?: string }
export type MenuItem = { heading: string } | { label: string; hint?: string; icon?: string; href?: string; download?: string; run?: () => void | Promise<void> };
export interface Shown { scale?: number; kind?: string | null; regions?: Region[] | null; faces?: FacePaint | null; pieces?: Piece[] | null }
export interface Status { bbox: { x: number; y: number; z: number } | null; pieces?: number; colors?: number; each?: number[][]; extra?: string[] }
type Replace = Record<string, string | number>;

const icon = (name: string, cls = 'h-4 w-4'): string => `<svg class="${cls} shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-${name}"/></svg>`;
const esc = (s: string): string => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c] as string));

export class Stage {
    readonly viewer: Viewer;
    readonly nf: Intl.NumberFormat;
    private plural: Intl.PluralRules;
    private first = true;
    private goAction: (() => void | Promise<void>) | null = null;
    private goHref: string | null = null;
    private history: { stack: string[]; at: number; io: TrackIo } | null = null;

    constructor(readonly cfg: ToolCfg, canvas: HTMLCanvasElement) {
        this.nf = new Intl.NumberFormat(cfg.locale, { maximumFractionDigits: 1 });
        this.plural = new Intl.PluralRules(cfg.locale);
        this.viewer = new Viewer(canvas);
        initPalette(cfg.colors, this.t, (cfg.i18n['farm.finish'] ?? {}) as Record<string, string>);
        initArtwork(cfg.artwork, this.t);
        this.toolbar();
        this.menu();
        this.spy();
        this.el('tool-go').addEventListener('click', () => { if (this.goHref) location.href = this.goHref; else void this.goAction?.(); });
    }

    /** A text of the page (lang/<locale>/toolpage.php), with :name placeholders filled in. */
    t = (key: string, replace: Replace = {}): string => {
        const text = this.cfg.i18n[key];
        return Object.entries(replace).reduce((s, [a, b]) => s.split(`:${a}`).join(String(b)), typeof text === 'string' ? text : key);
    };

    el<T extends HTMLElement>(id: string): T { return document.getElementById(id) as T; }

    /** "2 díly", "5 dílů": the form of the word the number asks for. */
    count(key: string, n: number): string {
        const rule = this.plural.select(n);
        const form = rule === 'one' ? 'one' : rule === 'few' ? 'few' : 'other';
        return this.t(`${key}.${form}`, { n });
    }

    // ── the viewer ──────────────────────────────────────────────────────────────────────────────────

    /** Shows a model; from the second one on the visitor's own view is kept (numbers change, the camera does not jump). */
    show(geom: BufferGeometry, o: Shown = {}): void {
        this.viewer.setGeometry(geom, o.scale ?? 1, o.kind ?? null, o.regions ?? null, o.faces ?? null);
        this.viewer.setPieces(o.pieces ?? null);
        // the bed comes with the first model: under an empty viewer it would be a stray line across the picture
        if (this.first) { this.first = false; this.viewer.keepView(true); if (this.el('tool-bed').getAttribute('aria-pressed') === 'true') this.viewer.setBed({ x: this.cfg.bed.x, y: this.cfg.bed.y, margin: this.cfg.bed.margin }); }
        this.el('tool-empty').classList.add('hidden');
        const many = this.viewer.getPieces().length > 1;
        const wrap = this.el('tool-spread-wrap');
        wrap.classList.toggle('hidden', !many); wrap.classList.toggle('flex', many);
        const spread = this.el<HTMLInputElement>('tool-spread');
        if (!many) spread.value = '0'; else if (spread.value !== '0') this.viewer.setSpread(Number(spread.value) / 100);
    }

    private toolbar(): void {
        const views = this.el('tool-views');
        views.querySelectorAll<HTMLButtonElement>('[data-view]').forEach((b) => b.addEventListener('click', () => {
            this.viewer.setView(b.dataset.view as ViewName);
            views.querySelectorAll('[data-view]').forEach((o) => o.setAttribute('aria-pressed', o === b ? 'true' : 'false'));
        }));
        this.el('tool-fit').addEventListener('click', () => {
            this.viewer.fitView();
            views.querySelectorAll('[data-view]').forEach((o) => o.setAttribute('aria-pressed', (o as HTMLElement).dataset.view === 'iso' ? 'true' : 'false'));
        });
        const toggle = (id: string, set: (on: boolean) => void): void => {
            const b = this.el(id);
            b.addEventListener('click', () => { const on = b.getAttribute('aria-pressed') !== 'true'; b.setAttribute('aria-pressed', on ? 'true' : 'false'); set(on); });
        };
        toggle('tool-xray', (on) => this.viewer.setXray(on));
        toggle('tool-bed', (on) => this.viewer.setBed(on ? { x: this.cfg.bed.x, y: this.cfg.bed.y, margin: this.cfg.bed.margin } : null));
        this.el<HTMLInputElement>('tool-spread').addEventListener('input', (e) => this.viewer.setSpread(Number((e.target as HTMLInputElement).value) / 100));
    }

    busy(on: boolean): void { const b = this.el('tool-busy'); b.classList.toggle('hidden', !on); b.classList.toggle('flex', on); }

    error(message: string | null): void {
        const el = this.el('tool-error');
        el.textContent = message ?? '';
        el.classList.toggle('hidden', !message);
    }

    // ── the status line ─────────────────────────────────────────────────────────────────────────────

    /**
     * "97,8 × 143 × 4,4 mm · 2 díly · 2 barvy · vejde se na 250 mm", or in red what does not fit and by how much.
     * `each`: the sizes of the separately printed pieces: what has to fit the bed is every piece alone.
     */
    status(s: Status | null): void {
        const el = this.el('tool-status');
        if (!s?.bbox) { el.innerHTML = `<span class="text-muted">${esc(this.t('toolpage.status.empty'))}</span>`; this.viewer.checkBed(true); return; }
        const dims = `${[s.bbox.x, s.bbox.y, s.bbox.z].map((v) => this.nf.format(v)).join(' × ')} mm`;
        const bits = [`<span class="font-medium">${esc(dims)}</span>`];
        if ((s.pieces ?? 0) > 1) bits.push(esc(this.count('toolpage.status.parts', s.pieces!)));
        if ((s.colors ?? 0) > 1) bits.push(esc(this.count('toolpage.status.colors', s.colors!)));
        (s.extra ?? []).forEach((x) => bits.push(esc(x)));
        const bed = this.cfg.bed;
        const usable = [bed.x - 2 * bed.margin, bed.y - 2 * bed.margin].sort((a, b) => b - a);
        let worst = 0;
        (s.each?.length ? s.each : [[s.bbox.x, s.bbox.y, s.bbox.z]]).forEach(([x, y, z]) => {
            const flat = [x, y].sort((a, b) => b - a);
            if (flat[0] > usable[0] + 0.05 || flat[1] > usable[1] + 0.05) worst = Math.max(worst, flat[0] > usable[0] ? flat[0] : flat[1]);
            if (z > bed.z + 0.05) worst = Math.max(worst, z);
        });
        // the viewer paints red what hangs over the bed only when the line below says so too
        this.viewer.checkBed(worst > 0);
        const size = `${this.nf.format(bed.x)} × ${this.nf.format(bed.y)}`;
        bits.push(worst
            ? `<span class="inline-flex items-center gap-1 font-medium text-danger" title="${esc(this.t('toolpage.status.nofit.hint', { bed: size }))}">${icon('triangle-alert', 'h-3.5 w-3.5')}${esc(this.t('toolpage.status.nofit', { mm: this.nf.format(worst) }))}</span>`
            : `<span class="inline-flex items-center gap-1 text-ok">${icon('check', 'h-3.5 w-3.5')}${esc(this.t('toolpage.status.fits', { bed: this.nf.format(Math.min(bed.x, bed.y)) }))}</span>`);
        el.innerHTML = bits.join('<span class="text-line" aria-hidden="true">·</span>');
    }

    /** One more line in the status row for a moment: which piece was picked. */
    note(text: string | null): void {
        const el = this.el('tool-status');
        el.querySelector('[data-note]')?.remove();
        if (!text) return;
        el.insertAdjacentHTML('beforeend', `<span data-note class="ml-auto rounded-full bg-slate-100 px-2 py-0.5 text-xs">${esc(text)}</span>`);
    }

    // ── the price ───────────────────────────────────────────────────────────────────────────────────

    /** The rough estimate, the same way the calculator counts it before the slicer has spoken. */
    price(c: PriceConfig, g: { volume_mm3: number; area_mm2: number | null } | null, o: { material?: string; quantity?: number; vase?: boolean; infill?: number; supports?: boolean; subText?: (grams: number, time: string, qty: number) => string } = {}): void {
        const out = this.el('tool-price'); const sub = this.el('tool-price-sub');
        if (!g || !(g.volume_mm3 > 0)) { out.textContent = '—'; sub.textContent = ''; return; }
        const code = o.material ?? c.default_material ?? c.materials[0]?.code ?? 'PLA';
        const qty = Math.max(1, Math.min(1000, o.quantity ?? 1));
        const density = c.materials.find((m) => m.code === code)?.density ?? 1.24;
        const est = estimate(c.rough, density, { volume_mm3: g.volume_mm3, area_mm2: g.area_mm2 as number }, { material: code, quality: 'standard', infill: o.infill ?? 15, supports: o.supports ?? false, scale: 1, quantity: qty, vase: o.vase });
        const totals = c.orientation_profiles.map((p) => price(c.round_to, p, est.grams, est.minutes, qty).total);
        const [lo, hi] = range(c.rough, c.round_to, totals, true);
        out.textContent = hi > 0 ? `≈ ${priceText(lo)} – ${priceText(hi)}` : '—';
        const h = Math.floor((est.minutes * qty) / 60); const min = Math.round((est.minutes * qty) % 60);
        const time = h ? `${h} h ${min} min` : `${min} min`;
        sub.textContent = o.subText ? o.subText(est.grams * qty, time, qty) : `≈ ${this.nf.format(est.grams * qty)} g · ≈ ${time}`;
    }

    /** The main action: one orange button on the page. A link (a finished model) or a function (save the design first). */
    go(o: { label?: string; href?: string | null; run?: (() => void | Promise<void>) | null; disabled?: boolean }): void {
        const b = this.el<HTMLButtonElement>('tool-go');
        if (o.label !== undefined) this.el('tool-go-label').textContent = o.label;
        if (o.href !== undefined) this.goHref = o.href;
        if (o.run !== undefined) this.goAction = o.run;
        if (o.disabled !== undefined) b.disabled = o.disabled;
    }

    // ── the download menu ───────────────────────────────────────────────────────────────────────────

    private menu(): void {
        const b = this.el<HTMLButtonElement>('tool-download'); const m = this.el('tool-download-menu');
        const open = (on: boolean): void => { m.classList.toggle('hidden', !on); b.setAttribute('aria-expanded', on ? 'true' : 'false'); };
        b.addEventListener('click', (e) => { e.stopPropagation(); open(m.classList.contains('hidden')); });
        document.addEventListener('click', (e) => { if (!m.contains(e.target as Node)) open(false); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') open(false); });
        m.addEventListener('click', () => open(false));
    }

    /** What can be downloaded, the slicer project with colours first; an empty list switches the button off. */
    downloads(items: MenuItem[]): void {
        const b = this.el<HTMLButtonElement>('tool-download'); const m = this.el('tool-download-menu');
        b.disabled = items.length === 0;
        m.innerHTML = '';
        items.forEach((item) => {
            if ('heading' in item) { m.insertAdjacentHTML('beforeend', `<div class="px-3 pb-1 pt-2 text-xs font-medium uppercase tracking-wide text-muted">${esc(item.heading)}</div>`); return; }
            const row = document.createElement(item.href ? 'a' : 'button') as HTMLAnchorElement & HTMLButtonElement;
            row.className = 'tool-menu-item'; row.setAttribute('role', 'menuitem');
            if (item.href) { row.href = item.href; if (item.download) row.setAttribute('download', item.download); row.dataset.track = 'download'; } else row.type = 'button';
            row.innerHTML = `${icon(item.icon ?? 'download', 'mt-0.5 h-4 w-4')}<span><span class="block font-medium">${esc(item.label)}</span>${item.hint ? `<span class="block text-xs text-muted">${esc(item.hint)}</span>` : ''}</span>`;
            if (item.run) row.addEventListener('click', async () => { row.setAttribute('disabled', ''); try { await item.run!(); } catch { this.error(this.t('toolpage.download.failed')); } finally { row.removeAttribute('disabled'); } });
            m.appendChild(row);
        });
    }

    /** Hands the browser a file made on the fly. */
    save(blob: Blob, name: string): void {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = name;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 5000);
    }

    /** The usual downloads of a finished model file: the project for a slicer, the STL, its parts. */
    fileDownloads(file: FileInfo, partLabel: (part: string) => string = (p) => p): void {
        const open = `${this.cfg.home}?open=${file.uuid}&download=1`;
        const items: MenuItem[] = [
            { label: this.t('toolpage.download.orca'), hint: this.t('toolpage.download.project.hint'), icon: 'file-box', href: `${open}&slicer=orca` },
            { label: this.t('toolpage.download.prusa'), hint: this.t('toolpage.download.project.hint'), icon: 'file-box', href: `${open}&slicer=prusaslicer` },
        ];
        if (file.stl_url) items.push({ label: this.t('toolpage.download.whole'), href: file.stl_url, download: `${file.name.replace(/\.[^.]+$/, '')}.stl` });
        if ((file.parts ?? []).length) {
            items.push({ heading: this.t('toolpage.download.parts') });
            (file.parts ?? []).forEach((p) => items.push({ label: this.t('toolpage.download.part', { name: partLabel(p) }), href: `${this.cfg.files.replace(/\/files$/, '')}/tools/param/${file.uuid}/${p}.stl` }));
        }
        this.downloads(items);
    }

    // ── tools that end with a model file (a relief, a mold, a repaired or checked model, a figure) ──

    async fetchFile(uuid: string): Promise<FileInfo> {
        return (await (await fetch(`${this.cfg.files}/${uuid}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })).json()).file;
    }

    /** Waits until the server has processed a file (about five minutes at most); the answer may still say "failed". */
    async untilReady(info: FileInfo, tries = 200): Promise<FileInfo> {
        for (let i = 0; i < tries && info.status !== 'ready' && info.status !== 'failed'; i++) {
            await new Promise((r) => setTimeout(r, 1500));
            info = await this.fetchFile(info.uuid);
        }
        return info;
    }

    /**
     * A finished model on the stage: in the viewer, its size and parts in the status line, the rough price, the
     * downloads, and the main action leading to the exact price (the calculator opens the very same file).
     */
    async fileResult(file: FileInfo, o: Shown & { goLabel?: string; partLabel?: (part: string) => string; noPrice?: boolean } = {}): Promise<void> {
        if (file.stl_url) this.show(await loadGeometryFromUrl(file.stl_url), { kind: o.kind ?? file.kind ?? null, faces: o.faces ?? null, regions: o.regions ?? null });
        this.status({ bbox: file.bbox, pieces: (file.parts ?? []).length || 1 });
        if (this.cfg.price && !o.noPrice && file.volume_mm3) {
            this.price(this.cfg.price, { volume_mm3: file.volume_mm3, area_mm2: file.area_mm2 }, { infill: file.hints?.infill, supports: file.hints?.supports, vase: file.hints?.vase });
        }
        this.fileDownloads(file, o.partLabel);
        this.go({ href: `${this.cfg.home}?open=${file.uuid}`, disabled: false, ...(o.goLabel ? { label: o.goLabel } : {}) });
    }

    /** A drop zone with a hidden file input: a click, a drop, or the keyboard hand over one file. */
    dropZone(zone: HTMLElement | null, input: HTMLInputElement, take: (file: File) => void): void {
        input.addEventListener('change', () => { if (input.files?.[0]) take(input.files[0]); });
        if (!zone) return;
        zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('border-ink'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('border-ink'));
        zone.addEventListener('drop', (e) => { e.preventDefault(); zone.classList.remove('border-ink'); const f = e.dataTransfer?.files?.[0]; if (f) take(f); });
    }

    // ── warnings, in the section they are about ─────────────────────────────────────────────────────

    /** section id → lines; a section that is not on the page hands its lines to the first one that is. */
    warnings(by: Record<string, string[]>): void {
        const lists = [...document.querySelectorAll<HTMLElement>('[data-warnings]')];
        const spare: string[] = [];
        Object.entries(by).forEach(([id, lines]) => { if (!lists.some((l) => l.dataset.warnings === id)) spare.push(...lines); });
        lists.forEach((list, i) => {
            const lines = [...(by[list.dataset.warnings!] ?? []), ...(i === 0 ? spare : [])];
            list.innerHTML = lines.map((l) => `<li class="tool-warn">${icon('triangle-alert', 'mt-0.5 h-4 w-4')}<span>${esc(l)}</span></li>`).join('');
            list.classList.toggle('hidden', lines.length === 0);
        });
    }

    // ── the steps of the panel ──────────────────────────────────────────────────────────────────────

    /** Every section stays on the page; the step whose section is in view is the marked one. */
    private spy(): void {
        const links = [...document.querySelectorAll<HTMLElement>('#tool-nav [data-nav]')];
        const sections = [...document.querySelectorAll<HTMLElement>('[data-section]')];
        if (!links.length || !sections.length) return;
        const mark = (id: string): void => links.forEach((l) => l.setAttribute('aria-current', l.dataset.nav === id ? 'true' : 'false'));
        mark(sections[0].dataset.section!);
        const seen = new Map<string, number>();
        const io = new IntersectionObserver((entries) => {
            entries.forEach((e) => seen.set((e.target as HTMLElement).dataset.section!, e.isIntersecting ? e.intersectionRatio : 0));
            // the topmost section that is at least partly in the upper half of the window
            const top = sections.find((s) => (seen.get(s.dataset.section!) ?? 0) > 0);
            if (top) mark(top.dataset.section!);
        }, { rootMargin: '-56px 0px -55% 0px', threshold: [0, 0.2, 0.6] });
        sections.forEach((s) => io.observe(s));
    }

    /** Brings a section (and one element in it) into view and marks it for a moment: "the panel jumps to the part". */
    reveal(sectionId: string, target: HTMLElement | null = null): void {
        const el = target ?? document.getElementById(`sec-${sectionId}`);
        if (!el) return;
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.animate([{ boxShadow: '0 0 0 3px rgb(201 71 20 / 0.55)' }, { boxShadow: '0 0 0 3px rgb(201 71 20 / 0)' }], { duration: 1200, easing: 'ease-out' });
    }

    // ── undo, redo, and the settings kept in this browser ───────────────────────────────────────────

    /**
     * read: the settings as plain data; write: put such data back into the form (the module then refreshes itself).
     * commit() is called by the module whenever a change has settled. `offerSaved`: show "restore my last settings"
     * when this browser holds some (not when the page was opened with a design or a preset in its address).
     */
    track(io: TrackIo, offerSaved = true): () => void {
        const key = `mp_tool_${this.cfg.tool}`;
        const start = JSON.stringify(io.read());
        this.history = { stack: [start], at: 0, io };
        const undo = this.el<HTMLButtonElement>('tool-undo'); const redo = this.el<HTMLButtonElement>('tool-redo');
        const buttons = (): void => { undo.disabled = this.history!.at === 0; redo.disabled = this.history!.at >= this.history!.stack.length - 1; };
        const move = (by: number): void => {
            const h = this.history!; const to = h.at + by;
            if (to < 0 || to >= h.stack.length) return;
            h.at = to;
            io.write(JSON.parse(h.stack[to]));
            try { localStorage.setItem(key, h.stack[to]); } catch { /* private mode */ }
            buttons();
        };
        undo.addEventListener('click', () => move(-1));
        redo.addEventListener('click', () => move(1));
        document.addEventListener('keydown', (e) => {
            if (!(e.ctrlKey || e.metaKey) || e.key.toLowerCase() !== 'z') return;
            // a text being typed keeps the browser's own undo
            const el = e.target as HTMLElement;
            if (el instanceof HTMLTextAreaElement || (el instanceof HTMLInputElement && !['number', 'range', 'checkbox', 'radio'].includes(el.type))) return;
            e.preventDefault();
            move(e.shiftKey ? 1 : -1);
        });
        let saved: string | null = null;
        try { saved = localStorage.getItem(key); } catch { /* private mode */ }
        const chip = this.el('tool-restore');
        if (offerSaved && saved && saved !== start) {
            chip.classList.remove('hidden'); chip.classList.add('inline-flex');
            chip.addEventListener('click', () => {
                chip.classList.add('hidden'); chip.classList.remove('inline-flex');
                try { io.write(JSON.parse(saved!)); } catch { return; }
                commit();
            });
        }
        const commit = (): void => {
            const h = this.history!; const now = JSON.stringify(io.read());
            if (now === h.stack[h.at]) return;
            h.stack.splice(h.at + 1); h.stack.push(now);
            if (h.stack.length > 60) h.stack.shift();
            h.at = h.stack.length - 1;
            try { localStorage.setItem(key, now); } catch { /* private mode */ }
            chip.classList.add('hidden'); chip.classList.remove('inline-flex');
            buttons();
        };
        buttons();
        return commit;
    }
}

export interface TrackIo { read: () => unknown; write: (state: Record<string, unknown>) => void }

export function bootToolPage(): void {
    const cfg = (window as unknown as { MP_TOOL?: ToolCfg }).MP_TOOL;
    const root = document.getElementById('tool-page');
    const canvas = document.getElementById('tool-viewer') as HTMLCanvasElement | null;
    if (!cfg || !root || !canvas) return;
    const stage = new Stage(cfg, canvas);
    const modules: Record<string, (stage: Stage) => void> = { param: bootParam, relief: bootRelief, mold: bootMoldPage, repair: bootRepairPage, check: bootCheckPage, figure: bootFigure, art: bootArt, edit: bootEdit };
    modules[cfg.module]?.(stage);
}
