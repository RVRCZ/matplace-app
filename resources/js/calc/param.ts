/**
 * Made-to-measure tools (organizer, box, phone stand, sign, logo, QR, vase…) as a module of the tool page:
 * numbers, texts and a picture → the live solid from the server (exact, the same one that is exported) → rough price
 * → the calculator, the farm or a download. The viewer, the status line, the price card and the download menu are
 * the page's (tool_page.ts); this file is the form and what the answer of the server means for it.
 */
import { STLLoader } from 'three/examples/jsm/loaders/STLLoader.js';
import { Region, FILAMENT, Piece } from './viewer';
import type { Stage, MenuItem, PriceConfig } from './tool_page';
import { colorOf, spoolCode, paintSwatch, pickColor, recentColors, rememberColor, materialLabel } from './colors';
import { pickArtwork, PickedArtwork } from './artwork';
import { icon } from '../site/icon';

interface Cfg {
    kind: string; family: string | null; sample: string | null; preset: string | null; captioned: boolean; preview: string; create: string; home: string; locale: string; artworkUrl: string; files: string; from: string | null;
    presets: Record<string, Record<string, number | string>>;
    fills: Record<string, Record<string, Record<string, number | string>>>;
    config: PriceConfig & { currency: string };
    handles: Record<string, 'x' | 'y' | 'z'>;
    warnAt: Record<string, string>;
    i18n: Record<string, string>;
}
interface Hole { wall: string; shape: string; w: number; h: number; x: number; z: number }
interface Bin { x: number; y: number; w: number; h: number; color: string }
interface BomLine { size: string; w_mm: number; d_mm: number; color: string; count: number }
/** One colour of a picture as the tool read it: its part, its number in the list, the colour in the picture, the filament it got. */
interface ShapeColor { part: string; index: number; rgb: string; code: string; hex: string; share: number }
interface ShapeNotes {
    colors?: ShapeColor[]; paint?: Record<string, string>; parts?: string[]; body_color?: { code: string; hex: string }; rim_color?: { code: string; hex: string };
    filaments?: number; multi_material?: boolean; color_changes?: { z: number }[]; found?: number; wanted?: number; each?: number[]; copies?: number;
    eyelet?: { x: number; y: number; z: number }; outline?: [number, number][]; thickened?: number; magnet?: { d: number; h: number; mount: string }; chain?: { links: number; length: number }; pockets?: { kind: string; count: number; depth: number }; pin?: { d: number; head: number; height: number }; stand?: number[]; source?: string;
    frame?: [number, number, number]; draw_z?: number;
    layers?: { part: string; index: number; box: [number, number, number, number]; z: number }[]; outer?: number[];
}
/** A layer of a composition: a text, a picture (of the library or the visitor's own) or a shape; where its middle lies, how wide it is, how it is turned, its filament. */
interface Layer { kind: string; text: string; typeface: string; art: string; art_name: string; shape: string; x: number; y: number; w: number; turn: number; code: string; hidden: boolean }

/** A stroke of icing drawn on a biscuit: the filament, the width in mm, the nib, the points in shares of the picture's width. */
interface Stroke { c: string; w: number; t: string; p: [number, number][] }
interface Meta { bbox: { x: number; y: number; z: number }; volume_mm3: number; area_mm2: number; notes: Record<string, unknown>; parts?: Piece[] }

export function bootParam(stage: Stage): void {
    const cfg = (window as unknown as { MP_PARAM?: Cfg }).MP_PARAM;
    const form = document.getElementById('param-form') as HTMLFormElement | null;
    if (!cfg || !form) return;

    const t = (k: string, r: Record<string, string | number> = {}) => Object.entries(r).reduce((s, [a, b]) => s.split(`:${a}`).join(String(b)), cfg.i18n[k] ?? stage.t(k));
    const $ = <T extends HTMLElement>(id: string) => document.getElementById(id) as T;
    const nf = stage.nf;
    const viewer = stage.viewer;
    const holes: Hole[] = [];
    const bins: Bin[] = [];
    let binColor = spoolCode('blue'); let binStart: { x: number; y: number } | null = null; let binSelected = -1;
    let seq = 0; let timer = 0; let lastMeta: Meta | null = null; let valid = false;
    let artwork: string | null = null;      // uploaded SVG / picture / library silhouette reference
    let artworkShown: { name: string; url: string | null } | null = null;
    let viewPart = 'all';                   // which part the preview shows
    const partColors: Record<string, string> = {};   // part → code of the spool it is printed from
    const ownColors = $('tool-parts')?.dataset.ownColors === '1';   // the design carries its colours itself (QR sign, modular set)
    let commit: () => void = () => undefined;
    // a picture in colours (pendant, earrings, ornament, magnet, coaster): which of its colours were joined and their order, bottom to top
    const shape = cfg.family === 'shape';
    let merge: number[][] = []; let order: number[] = [];
    const shapeNotes = (): ShapeNotes => (lastMeta?.notes ?? {}) as ShapeNotes;
    // a biscuit: the icing drawn on it by hand
    const cookie = cfg.kind === 'cookie';
    const compose = cfg.kind === 'compose';
    const layers: Layer[] = []; let chosen = 0;         // a composition: its layers from the bottom up, and which one is being edited
    const strokes: Stroke[] = [];
    let drawing = false; let pen = spoolCode('white');
    /** A new picture has new colours: what was said about the old ones (which filament, which order) no longer holds. */
    const forgetColours = (): void => {
        merge = []; order = [];
        Object.keys(partColors).forEach((p) => { if (p.startsWith('color_')) delete partColors[p]; });
    };

    const colorName = (code: string): string => colorOf(code)?.name ?? (cfg.i18n[`color.${code}`] ?? code);

    const params = (): Record<string, unknown> => {
        const p: Record<string, unknown> = {};
        form.querySelectorAll<HTMLInputElement>('[data-param]').forEach((i) => { p[i.dataset.param!] = Number(i.value); });
        form.querySelectorAll<HTMLInputElement>('[data-flag]').forEach((i) => { p[i.dataset.flag!] = i.checked; });
        form.querySelectorAll<HTMLInputElement>('[data-choice]:checked, [data-choice][data-color]').forEach((i) => { p[i.dataset.choice!] = i.value; });
        form.querySelectorAll<HTMLInputElement>('[data-text]').forEach((i) => { p[i.dataset.text!] = i.value; });
        if (artwork) p.artwork = artwork;
        if (cfg.kind === 'box') p.holes = holes;
        if (cfg.kind === 'modular') p.bins = bins;
        if (shape && merge.length) p.merge = merge;
        if (shape && order.length) p.order = order;
        if (cookie && strokes.length) p.strokes = strokes;
        if (compose) p.layers = layers;
        if (Object.keys(partColors).length) p.part_colors = { ...partColors };
        return p;
    };

    /** Browser-side range check: marks the field, the server checks again. A required text (the sign's first line) counts too. */
    const fieldsOk = (): boolean => {
        let ok = true;
        form.querySelectorAll<HTMLInputElement>('[data-param]').forEach((i) => {
            const v = Number(i.value);
            const bad = i.value === '' || Number.isNaN(v) || v < Number(i.min) || v > Number(i.max);
            i.setAttribute('aria-invalid', bad ? 'true' : 'false');
            i.classList.toggle('border-red-600', bad);
            if (bad) ok = false;
        });
        let textMissing = false;
        form.querySelectorAll<HTMLInputElement>('[data-text][required]').forEach((i) => {
            const bad = i.value.trim() === '';
            i.setAttribute('aria-invalid', bad ? 'true' : 'false');
            i.classList.toggle('border-red-600', bad);
            if (bad) { ok = false; textMissing = true; }
        });
        if (textMissing) showError(t('param.text_required')); else if ($('tool-error').textContent === t('param.text_required')) showError(null);
        return ok;
    };

    const showError = (msg: string | null): void => {
        stage.error(msg);
        stage.go({ disabled: !!msg });
    };

    /** Facts the tool measured (outer and inner size, a cell, what it fits…): rows under the status line. */
    const renderDims = (m: Meta): void => {
        const n = m.notes as { outer?: number[]; inner?: number[]; cell?: number[]; slot?: number; fits?: number[]; module_mm?: number; modules?: number; quiet_zone_mm?: number; saucer_d?: number; drainage_holes?: number; needs?: string[]; led_m?: number; bridges?: number; ties?: number; open_pct?: number; things?: number[][] };
        const rows: [string, string][] = [];
        const dims = (a: number[]) => `${a.map((v) => nf.format(v)).join(' × ')} mm`;
        if (n.inner) rows.push([t('param.inner'), dims(n.inner)]);
        if (n.cell) rows.push([t('param.cell'), dims(n.cell)]);
        if (n.slot) rows.push([t('param.slot'), `${nf.format(n.slot)} mm`]);
        if (n.fits) rows.push([t('param.fits'), dims(n.fits)]);
        const facts: string[] = [];
        if (n.module_mm) facts.push(t('param.qr.facts', { m: nf.format(n.module_mm), q: nf.format(n.quiet_zone_mm ?? 0), c: n.modules ?? 0 }));
        if (n.bridges) facts.push(t('param.bridges', { n: n.bridges }));
        if (n.ties) facts.push(t('param.papel.ties', { n: n.ties, o: n.open_pct ?? 0 }));
        if (n.things) facts.push(t('param.insert.things', { n: n.things.length, s: n.things.map((s) => s.map((v) => nf.format(v)).join(' × ')).join(', ') }));
        if (n.led_m) facts.push(t('param.lightbox.led', { m: nf.format(n.led_m) }));
        if (n.saucer_d) facts.push(t('param.saucer', { d: nf.format(n.saucer_d), h: n.drainage_holes ?? 0 }));
        const beadCount = (m.notes as { count?: number; each?: number[] });
        if (cfg.kind === 'beads' && beadCount.count && beadCount.each) facts.push(t('param.beads.count', { n: beadCount.count, s: beadCount.each.map((v) => nf.format(v)).join(' × ') }));
        const pocket = (m.notes as { pocket_mm?: number }).pocket_mm;
        if (pocket) facts.push(t('param.cup.pocket', { w: nf.format(pocket) }));
        const magnet = shapeNotes().magnet;
        if (shape && magnet && magnet.mount !== 'through') facts.push(t('shape.magnet.fact', { d: nf.format(magnet.d), h: nf.format(magnet.h) }));
        const chain = shapeNotes().chain;
        if (shape && chain) facts.push(t('shape.chain.fact', { n: chain.links, l: nf.format(chain.length / 10) }));
        const pin = shapeNotes().pin;
        if (shape && pin) facts.push(t('shape.pin.fact', { d: nf.format(pin.d), h: nf.format(pin.head), l: nf.format(pin.height) }));
        const base = shapeNotes().stand;
        if (shape && base) facts.push(t('shape.stand.fact', { w: nf.format(base[0]), d: nf.format(base[1]), h: nf.format(base[2]) }));
        const pockets = shapeNotes().pockets;
        if (shape && pockets && pockets.kind !== 'open') facts.push(t(`shape.pockets.${pockets.kind}`, { n: pockets.count, d: nf.format(pockets.depth) }));
        if ((n.needs ?? []).length) facts.push(`${t('param.needs')}: ${(n.needs ?? []).map((x) => t(`param.need.${x}`)).join(', ')}`);
        const el = $('param-dims');
        el.innerHTML = rows.map(([k, v]) => `<div class="flex justify-between gap-3"><dt class="text-muted">${k}</dt><dd class="font-medium text-ink">${v}</dd></div>`).join('')
            + facts.map((f) => `<div class="text-muted sm:col-span-2">${f.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c] as string))}</div>`).join('');
        const on = rows.length + facts.length > 0;
        el.classList.toggle('hidden', !on); el.classList.toggle('grid', on);
    };

    const renderPrice = (): void => {
        if (!lastMeta) return;
        const qty = Math.max(1, Math.min(1000, Number(($('param-qty') as HTMLInputElement).value) || 1));
        // a plain vase is priced the way it will be printed: one spiralled wall, no infill (the calculator gets the same hint)
        const vaseMode = cfg.kind === 'vase' && (params().purpose ?? 'vase') === 'vase';
        stage.price(cfg.config, { volume_mm3: lastMeta.volume_mm3, area_mm2: lastMeta.area_mm2 }, {
            material: ($('param-material') as HTMLSelectElement).value, quantity: qty, vase: vaseMode,
            subText: (g, time, q) => t('param.estimate', { g: nf.format(g), t: time, q }),
        });
    };

    /** Separately printed parts of the current design (mirrors UploadController::partsOf). */
    const partsNow = (): string[] => {
        const p = params();
        if (shape) return shapeNotes().parts ?? [];
        if (cfg.kind === 'box' && p.lid) return ['body', 'lid'];
        if (cfg.kind === 'vase' && p.purpose === 'pot' && p.saucer) return ['body', 'saucer'];
        if (cfg.kind === 'stamp' && p.handle === 'knob') return ['body', 'handle'];
        if (cfg.kind === 'logo' && p.mode === 'standing') return ['body', 'stand'];
        if (cfg.kind === 'sign' && p.two_color && p.style !== 'engrave') return ['plate', 'text'];
        if (cfg.kind === 'beads' && p.style === 'raised') return ['body', 'text'];
        if (cfg.kind === 'qr' && p.stand) return ['body', 'stand'];
        if (cfg.kind === 'lightbox') return ['body', 'face', 'diffuser', 'back'];
        if (cfg.kind === 'cutter') return ((lastMeta?.notes as { parts?: string[] } | undefined)?.parts ?? []);   // a stamp only when the drawing had inner lines
        if (cfg.kind === 'modular') return [...(p.tray ? ['tray'] : []), ...new Set(bins.map((b) => `bin_${b.w}x${b.h}`))];
        return [];
    };

    /** "body" and "stand" mean different things per product: the box, the logo, the sign… */
    const partLabel = (v: string): string => {
        if (v.startsWith('bin_')) return t('param.part.bin', { s: v.slice(4).replace('x', ' × ') });
        if (shape && cfg.i18n[`shape.part.${v}.${cfg.kind}`]) return t(`shape.part.${v}.${cfg.kind}`);
        if (shape && v.startsWith('icing_')) return t('shape.part.icing', { n: v.slice(6) });
        if (shape && v.startsWith('color_')) return t('shape.part.color', { n: v.slice(6) });
        if (shape && (v === 'body' || v === 'rim')) return t(`shape.part.${v}`);
        const own = `param.part.${v}.${cfg.kind}`;
        return cfg.i18n[own] ? t(own) : cfg.i18n[`param.part.${v}`] ? t(`param.part.${v}`) : v;
    };

    /** Assembly / single parts / (stamp) the imprint it leaves: buttons over the viewer. */
    const renderViews = (): void => {
        const box = document.getElementById('param-views');
        if (!box) return;
        if (shape) { box.innerHTML = ''; viewPart = 'all'; return; }      // the colours lie on one plate: nothing to look at one by one
        // a threaded cap: the thread is inside, a look at the cut model shows it
        const views = ['all', ...partsNow().filter((p) => !['plate', 'text'].includes(p)), ...(cfg.kind === 'stamp' ? ['imprint'] : []), ...(cfg.kind === 'cap' && params().style === 'thread' ? ['cut'] : [])];
        if (!views.includes(viewPart)) viewPart = 'all';
        box.innerHTML = '';
        if (views.length < 2) return;
        views.forEach((v) => {
            const b = document.createElement('button');
            b.type = 'button'; b.className = `chip !py-1 text-xs shadow-sm ${v === viewPart ? 'chip-on' : ''}`; b.textContent = partLabel(v);
            b.setAttribute('aria-pressed', v === viewPart ? 'true' : 'false');
            b.onclick = () => { viewPart = v; refresh(); };
            box.appendChild(b);
        });
    };

    /** Bill of parts of a modular set: what gets printed, how many times and in which colour. */
    const bomText = (m: Meta): string[] => ((m.notes as { bom?: BomLine[] }).bom ?? []).map((b) => t('param.bom.line', { n: b.count, s: b.size.replace('x', ' × '), w: nf.format(b.w_mm), d: nf.format(b.d_mm), c: colorName(b.color) }));
    const renderBom = (m: Meta): void => {
        const el = document.getElementById('param-bom'); if (!el) return;
        const n = m.notes as { bom?: BomLine[]; unit?: number[]; free_cells?: number; tray_size?: number[] };
        if (!n.bom) { el.classList.add('hidden'); return; }
        const lines = bomText(m);
        if (n.tray_size) lines.unshift(`1 × ${t('param.part.tray')} ${n.tray_size.map((v) => nf.format(v)).join(' × ')} mm`);
        el.innerHTML = `<div class="font-semibold text-ink">${t('param.bom')}</div><ul class="mt-1 list-disc space-y-0.5 pl-5 text-ink">${lines.map((l) => `<li>${l}</li>`).join('')}</ul>`
            + `<p class="mt-2 text-muted">${t('param.unit', { x: nf.format(n.unit?.[0] ?? 0), y: nf.format(n.unit?.[1] ?? 0) })}${n.free_cells ? ` ${t('param.bins.free', { n: n.free_cells })}` : ''}</p>`;
        el.classList.remove('hidden');
    };

    /** Warnings go to the section they are about (a thin line → the input, weak contrast → the colours, the rest → the sizes). */
    const renderWarnings = (m: Meta | null): void => {
        const by: Record<string, string[]> = {};
        const add = (section: string, line: string): void => { (by[section] = by[section] ?? []).push(line); };
        const n = (m?.notes ?? {}) as { warnings?: string[]; missing_chars?: string[]; thin_pct?: number; pieces?: number };
        (n.warnings ?? []).forEach((w) => add(cfg.warnAt[w] ?? 'size', t(cfg.i18n[`shape.warn.${w}`] ? `shape.warn.${w}` : `param.warn.${w}`, { n: n.thin_pct ?? 0, c: (n.missing_chars ?? []).join(' '), p: n.pieces ?? 0 })));
        const thickened = shapeNotes().thickened;
        if (shape && m && thickened) add('size', t('shape.thickened', { t: nf.format(thickened) }));
        // a spool that is out of stock: the design keeps its colour, the visitor is told to pick another
        const used = [...form.querySelectorAll<HTMLInputElement>('[data-choice][data-color]')].map((i) => i.value).concat(Object.values(partColors), cfg.kind === 'modular' ? bins.map((b) => b.color) : []);
        if (used.some((code) => { const c = colorOf(code); return c !== null && !c.in_stock; })) add('colors', stage.t('toolpage.color.out'));
        stage.warnings(by);
    };

    const post = (body: Record<string, unknown>) => fetch(cfg.preview, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body) });
    // the server's first validation message, or our own words for a rate limit (Laravel's "Too Many Attempts." is English only)
    const firstError = (b: { message?: string; errors?: Record<string, string[]> }, status = 0) => (status === 429 ? t('param.too_fast') : Object.values(b.errors ?? {})[0]?.[0] ?? b.message ?? t('param.failed'));
    const errorOf = async (res: Response): Promise<string> => { try { return firstError(await res.json(), res.status); } catch { return firstError({}, res.status); } };

    /** The pieces of the shown model that are the given part, as indexes into the viewer's list. */
    const piecesOf = (part: string): number[] => viewer.getPieces().map((p, i) => (p.name === part ? i : -1)).filter((i) => i >= 0);

    /** Paints the pieces in the colours picked for their parts. A one-body design takes the colour of its only row. */
    const paintParts = (): void => {
        // a set of bins: each bin whole in its own colour (the region its middle lies in), outer walls included
        if (shape || compose) {
            // every colour is a piece of its own and the tool said which filament it is
            const paint = shapeNotes().paint ?? {};
            viewer.getPieces().forEach((piece, i) => viewer.setPieceColor(i, paint[piece.name] ?? null));
            return;
        }
        const regions = (lastMeta?.notes as { regions?: Region[] } | undefined)?.regions ?? [];
        if (cfg.kind === 'modular' && viewPart === 'all' && viewer.getPieces().length > 1) {
            viewer.getPieces().forEach((piece, i) => {
                if (!piece.bbox) return;
                const cx = (piece.bbox[0] + piece.bbox[3]) / 2; const cy = (piece.bbox[1] + piece.bbox[4]) / 2;
                const r = regions.find((g) => cx >= g.x0 && cx <= g.x1 && cy >= g.y0 && cy <= g.y1);
                if (r) viewer.setPieceColor(i, colorOf(r.color)?.hex ?? null);
            });
            return;
        }
        if (ownColors || viewPart !== 'all') return;
        const pieces = viewer.getPieces();
        const rows = partRows();
        pieces.forEach((piece, i) => {
            const part = rows.includes(piece.name) ? piece.name : rows.length === 1 ? rows[0] : piece.name;
            viewer.setPieceColor(i, partColors[part] ? colorOf(partColors[part])?.hex ?? null : null);
        });
    };

    /** The rows of the colours section: the parts printed separately, or the one body. */
    const partRows = (): string[] => { const p = partsNow(); return p.length ? p : ['body']; };

    /** The filament of a part of a picture: the visitor's own choice, else the one the tool matched to the picture. */
    const shapeCode = (part: string): string | null => {
        const n = shapeNotes();
        if (partColors[part]) return partColors[part];
        if (part === 'body') return n.body_color?.code ?? null;
        if (part === 'rim') return n.rim_color?.code ?? null;
        return n.colors?.find((c) => c.part === part)?.code ?? null;
    };
    const setShapeColor = (part: string, code: string): void => {
        rememberColor(code);
        if (part.startsWith('icing_')) {
            // icing has the colour it was drawn in: another filament for it is another filament for its strokes
            const was = shapeCode(part);
            strokes.forEach((s) => { if (s.c === was) s.c = code; });
            if (pen === was) pen = code;
        } else partColors[part] = code;
        void refresh().then(commit);
    };
    /**
     * The colours of the picture as a list, the top layer first and the plate last: each with the filament it is printed
     * from (a click opens the colour window), the colour it had in the picture and how much of it there is. A colour can
     * be moved up or down (what lies on what) and joined with the one under it.
     */
    const renderShapeColors = (): void => {
        const box = document.getElementById('tool-parts'); if (!box) return;
        const n = shapeNotes(); const every = n.colors ?? [];
        const colors = every.filter((c) => c.part.startsWith('color_'));      // the picture's own colours: only these can be reordered and joined
        box.innerHTML = '';
        const rows = [...(n.rim_color ? ['rim'] : []), ...every.map((c) => c.part).reverse(), 'body'];
        rows.forEach((part) => {
            const c = colors.find((x) => x.part === part);
            const pos = c ? colors.indexOf(c) : -1;
            const code = shapeCode(part); const spool = colorOf(code);
            const row = document.createElement('div');
            row.className = 'flex items-center gap-3 rounded-lg'; row.dataset.part = part;
            const fact = c ? `<span class="inline-block h-3 w-3 shrink-0 rounded-full border border-line" style="background:${c.rgb}" title="${t('shape.colors.picture')}"></span><span class="font-normal text-muted">${t('shape.colors.share', { p: nf.format(Math.round(c.share * 1000) / 10) })}</span>` : '';
            const move = (act: string, label: string, name: string, off: boolean): string => `<button type="button" data-act="${act}" class="chip !min-h-8 !px-2 !py-1" aria-label="${label}" title="${label}" ${off ? 'disabled' : ''}>${icon(name, 'h-3.5 w-3.5')}</button>`;
            const tools = c && colors.length > 1 ? `<span class="flex shrink-0 gap-1">${move('up', t('shape.colors.up'), 'arrow-up', pos === colors.length - 1)}${move('down', t('shape.colors.down'), 'arrow-down', pos === 0)}${move('merge', pos > 0 ? t('shape.colors.merge.into', { n: colors[pos - 1].index }) : t('shape.colors.merge'), 'layers', pos === 0)}</span>` : '';
            row.innerHTML = `<button type="button" class="tool-swatch" aria-label="${partLabel(part)}: ${stage.t('toolpage.color.pick')}"></button>
                <span class="min-w-0 flex-1 text-sm"><span class="flex items-center gap-1.5 font-medium text-ink">${partLabel(part)}${fact}</span><span class="block truncate text-muted">${spool ? `${spool.name} · ${materialLabel(spool)}` : stage.t('toolpage.color.pick')}</span></span>${tools}`;
            const swatch = row.querySelector<HTMLElement>('.tool-swatch')!;
            paintSwatch(swatch, code);
            swatch.onclick = async () => { lastColorTarget = () => (picked) => setShapeColor(part, picked); const picked = await pickColor(code); if (picked) setShapeColor(part, picked); };
            row.querySelectorAll<HTMLButtonElement>('[data-act]').forEach((b) => {
                b.onclick = () => {
                    const now = colors.map((x) => x.index);
                    if (b.dataset.act === 'merge') {
                        merge = [...merge, [c!.index, colors[pos - 1].index]];
                        delete partColors[part];
                    } else {
                        const to = pos + (b.dataset.act === 'up' ? 1 : -1);
                        [now[pos], now[to]] = [now[to], now[pos]];
                        order = now;
                    }
                    void refresh().then(commit);
                };
            });
            box.appendChild(row);
        });
        if (merge.length || order.length) {
            const undo = document.createElement('button');
            undo.type = 'button'; undo.className = 'text-left text-sm text-muted underline'; undo.textContent = t('shape.colors.split');
            undo.onclick = () => { merge = []; order = []; void refresh().then(commit); };
            box.appendChild(undo);
        }
        ($('param-color') as HTMLInputElement).value = shapeCode('body') ? colorName(shapeCode('body')!) : '';
        const found = document.getElementById('shape-found');
        if (found) found.textContent = n.source !== 'text' && n.found && n.wanted && n.found < n.wanted ? t('shape.colors.found', { n: n.found, w: n.wanted }) : '';
        const print = document.getElementById('shape-print');
        const swaps = (n.color_changes ?? []).length;
        // the farm loads four spools for one print: a design of more different colours is told so (a colour may come back, that costs no spool)
        const spools = n.filaments ?? 1;
        if (print) print.textContent = n.multi_material ? t('shape.print.multi') : spools > 4 ? t('shape.print.many', { n: swaps, c: spools }) : swaps > 1 ? t('shape.print.swap', { n: swaps }) : swaps ? t('shape.print.swap1') : t('shape.print.one');
        renderRecent();
    };

    const renderParts = (): void => {
        const box = document.getElementById('tool-parts'); if (!box) return;
        if (shape) { renderShapeColors(); return; }
        if (ownColors) { box.innerHTML = ''; renderRecent(); return; }
        const rows = partRows();
        Object.keys(partColors).forEach((p) => { if (!rows.includes(p)) delete partColors[p]; });
        box.innerHTML = '';
        rows.forEach((part) => {
            const row = document.createElement('div');
            row.className = 'flex items-center gap-3 rounded-lg'; row.dataset.part = part;
            const code = partColors[part] ?? null; const c = colorOf(code);
            row.innerHTML = `<button type="button" class="tool-swatch" aria-label="${rows.length > 1 ? partLabel(part) : stage.t('toolpage.color.one')}: ${stage.t('toolpage.color.pick')}"></button>
                <span class="min-w-0 text-sm"><span class="block font-medium text-ink">${rows.length > 1 ? partLabel(part) : stage.t('toolpage.color.one')}</span><span class="block truncate text-muted">${c ? `${c.name} · ${materialLabel(c)}` : stage.t('toolpage.color.pick')}</span></span>`;
            const swatch = row.querySelector<HTMLElement>('.tool-swatch')!;
            paintSwatch(swatch, code);
            swatch.onclick = async () => { const picked = await pickColor(code); if (picked) setPartColor(part, picked); };
            box.appendChild(row);
        });
        // the order starts from the colour of the first part
        ($('param-color') as HTMLInputElement).value = partColors[rows[0]] ? colorName(partColors[rows[0]]) : '';
        renderRecent();
    };

    const setPartColor = (part: string, code: string): void => {
        partColors[part] = code; rememberColor(code);
        renderParts(); paintParts(); renderWarnings(lastMeta); renderStatus(); commit();
    };

    /** "Used recently": one click gives the colour to the part (or the colour field) touched last. */
    let lastColorTarget: (() => ((code: string) => void)) | null = null;
    const renderRecent = (): void => {
        const wrap = document.getElementById('tool-recent'); const list = document.getElementById('tool-recent-list');
        if (!wrap || !list) return;
        const recent = recentColors();
        wrap.classList.toggle('hidden', recent.length === 0);
        list.innerHTML = '';
        recent.forEach((code) => {
            const b = document.createElement('button');
            b.type = 'button'; b.className = 'tool-swatch-sm'; paintSwatch(b, code); b.setAttribute('aria-label', colorName(code));
            b.onclick = () => {
                if (lastColorTarget) { lastColorTarget()(code); return; }
                if (shape) { setShapeColor('body', code); return; }
                const field = form.querySelector<HTMLInputElement>('[data-choice][data-color]');
                if (field) setChoiceColor(field, code); else if (cfg.kind === 'modular') setBinColor(code); else setPartColor(partRows()[0], code);
            };
            list.appendChild(b);
        });
    };

    // colours that are part of the design (the plate and the code of a QR sign)
    const paintChoice = (input: HTMLInputElement): void => {
        const key = input.dataset.choice!;
        const sw = form.querySelector<HTMLElement>(`[data-swatch-for="${key}"]`); const name = form.querySelector<HTMLElement>(`[data-swatch-name="${key}"]`);
        const c = colorOf(input.value);
        if (sw) paintSwatch(sw, input.value);
        if (name) name.textContent = c ? `${c.name} · ${materialLabel(c)}` : colorName(input.value);
    };
    const setChoiceColor = (input: HTMLInputElement, code: string): void => {
        input.value = code; rememberColor(code); paintChoice(input); renderRecent();
        input.dispatchEvent(new Event('input', { bubbles: true }));
    };
    form.querySelectorAll<HTMLInputElement>('[data-choice][data-color]').forEach((input) => {
        paintChoice(input);
        const sw = form.querySelector<HTMLElement>(`[data-swatch-for="${input.dataset.choice}"]`);
        if (sw) sw.onclick = async () => { lastColorTarget = () => (code) => setChoiceColor(input, code); const picked = await pickColor(input.value); if (picked) setChoiceColor(input, picked); };
    });

    const colorCount = (): number => {
        if (cfg.kind === 'modular') return new Set(bins.map((b) => b.color)).size;
        const own = [...form.querySelectorAll<HTMLInputElement>('[data-choice][data-color]')].map((i) => i.value);
        if (own.length) return new Set(own).size;
        const regions = (lastMeta?.notes as { regions?: Region[] } | undefined)?.regions;
        const picked = new Set(Object.values(partColors)).size;
        return Math.max(picked, regions?.length ? new Set(regions.map((r) => r.color)).size : 0);
    };

    const renderStatus = (): void => {
        if (!lastMeta) { stage.status(null); return; }
        if (shape) {
            // one thing (two for a pair of earrings), measured as it comes off the bed; the colours are filaments, not parts
            const n = shapeNotes(); const e = n.each ?? [lastMeta.bbox.x, lastMeta.bbox.y, lastMeta.bbox.z];
            stage.status({ bbox: { x: e[0], y: e[1], z: e[2] }, pieces: n.copies ?? 1, colors: n.filaments ?? 1 });
            return;
        }
        const pieces = viewer.getPieces();
        const sizes = pieces.filter((p) => p.bbox).map((p) => [p.bbox![3] - p.bbox![0], p.bbox![4] - p.bbox![1], p.bbox![5] - p.bbox![2]]);
        const outer = (lastMeta.notes as { outer?: number[] }).outer;
        const parts = partsNow().length;
        stage.status({
            bbox: viewPart === 'all' && outer ? { x: outer[0], y: outer[1], z: outer[2] } : lastMeta.bbox,
            pieces: viewPart === 'all' ? Math.max(pieces.length, parts, cfg.kind === 'modular' ? bins.length + (params().tray ? 1 : 0) : 0) : 1,
            colors: colorCount(),
            // what has to fit the bed is each piece alone; a set shown assembled is measured by its outer size
            each: sizes.length > 1 ? sizes : undefined,
        });
    };

    const refresh = async (): Promise<void> => {
        if (!fieldsOk()) { valid = false; stage.go({ disabled: true }); return; }
        const mine = ++seq;
        stage.busy(true);
        try {
            renderViews();
            const res = await post({ kind: cfg.kind, params: params(), view: 'use', part: viewPart, pieces: true });
            if (mine !== seq) return;                       // a newer change is already on its way
            if (!res.ok) { valid = false; showError(await errorOf(res)); return; }
            lastMeta = JSON.parse(res.headers.get('X-Model-Meta') ?? 'null');
            // the colours of a QR sign go by height alone, so they hold for the sign and the stand shown on their own too
            const regions = viewPart === 'all' || cfg.kind === 'qr' ? ((lastMeta?.notes as { regions?: Region[] } | undefined)?.regions ?? null) : null;
            stage.show(new STLLoader().parse(await res.arrayBuffer()), { kind: cfg.kind, regions, pieces: lastMeta?.parts ?? null });
            valid = true; showError(null);
            if (lastMeta) { renderDims(lastMeta); renderBom(lastMeta); if (viewPart === 'all') renderPrice(); }
            renderParts(); paintParts(); renderWarnings(lastMeta); renderStatus();
            renderDownloads(); placeEyelet(); placeFrame();
            if (cookie) { renderPen(); armDraw(); }
        } catch {
            if (mine === seq) { valid = false; showError(t('param.failed')); }
        } finally {
            if (mine === seq) stage.busy(false);
        }
    };
    const soon = (ms = 350): void => { window.clearTimeout(timer); timer = window.setTimeout(() => { void refresh().then(commit); }, ms); };

    /** One part (or the whole plate) as an STL, built from what the form says right now. */
    const downloadPart = async (part: string): Promise<void> => {
        if (!valid) return;
        const res = await post({ kind: cfg.kind, params: params(), part, download: true });
        if (!res.ok) { showError(await errorOf(res)); return; }
        stage.save(await res.blob(), `${cfg.kind.replace('_', '-')}${part === 'all' ? '' : `-${part}`}.stl`);
    };
    const downloadZip = async (): Promise<void> => {
        if (!valid) return;
        const res = await fetch(stage.cfg.zip, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ kind: cfg.kind, params: params() }) });
        if (!res.ok) { showError(await errorOf(res)); return; }
        stage.save(await res.blob(), `${cfg.kind.replace('_', '-')}.zip`);
    };

    /** The project with colours first, then the STL: the whole plate, all parts in one archive, each part alone. */
    const renderDownloads = (): void => {
        const parts = partsNow();
        const items: MenuItem[] = [
            { label: stage.t('toolpage.download.orca'), hint: stage.t('toolpage.download.project.hint'), icon: 'file-box', run: () => save(true, 'orca') },
            { label: stage.t('toolpage.download.prusa'), hint: stage.t('toolpage.download.project.hint'), icon: 'file-box', run: () => save(true, 'prusaslicer') },
        ];
        if (parts.length) items.push({ label: stage.t('toolpage.download.zip'), icon: 'package', run: downloadZip });
        items.push({ label: stage.t('toolpage.download.whole'), run: () => downloadPart('all') });
        if (parts.length) {
            items.push({ heading: stage.t('toolpage.download.parts') });
            parts.forEach((p) => items.push({ label: stage.t('toolpage.download.part', { name: partLabel(p) }), run: () => downloadPart(p) }));
        }
        stage.downloads(valid ? items : []);
    };

    // ── openings in the box walls ──────────────────────────────────────────
    const renderHoles = (): void => {
        const list = document.getElementById('holes');
        if (!list) return;
        list.innerHTML = '';
        holes.forEach((h, i) => {
            const row = document.createElement('div');
            row.className = 'grid grid-cols-2 gap-2 rounded-xl border border-line p-2';
            const sel = (key: 'wall' | 'shape', opts: string[], prefix: string) => `<label class="text-xs text-muted"><select data-h="${key}" class="field !mt-0 !min-h-10 text-sm">${opts.map((o) => `<option value="${o}" ${h[key] === o ? 'selected' : ''}>${t(`${prefix}.${o}`)}</option>`).join('')}</select></label>`;
            const numIn = (key: 'w' | 'h' | 'x' | 'z', label: string, hidden = false) => `<label class="text-xs text-muted ${hidden ? 'hidden' : ''}">${label}<input data-h="${key}" type="number" inputmode="decimal" min="0" step="0.5" value="${h[key]}" class="field !mt-0 !min-h-10 text-sm"></label>`;
            row.innerHTML = `<span class="col-span-2 text-sm font-medium text-ink">${t('param.hole')} ${i + 1} <button type="button" data-h="remove" class="ml-2 text-sm font-normal text-muted underline">${t('param.hole.remove')}</button></span>`
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

    /** Fill the form from a preset, a stored design or a step back in the history. */
    // the font picker scrolls inside its box: the face a design was made with must be the one in view
    const showFace = (): void => {
        const box = form.querySelector<HTMLElement>('[data-font-picker]'); const on = box?.querySelector<HTMLInputElement>('input:checked')?.closest<HTMLElement>('label');
        if (box && on) box.scrollTop = Math.max(0, on.offsetTop - box.clientHeight / 2 + on.clientHeight / 2);
    };
    const applyValues = (set: Record<string, unknown>): void => {
        Object.entries(set).forEach(([k, v]) => {
            const num = form.querySelector<HTMLInputElement>(`[data-param="${k}"]`); if (num) { num.value = String(v); syncRange(num); }
            const txt = form.querySelector<HTMLInputElement>(`[data-text="${k}"]`); if (txt) txt.value = String(v ?? '');
            const flag = form.querySelector<HTMLInputElement>(`[data-flag="${k}"]`); if (flag) flag.checked = Boolean(v);
            // a colour: designs stored before the farm's catalogue carry a name (white, black…) — it becomes the nearest spool
            const colour = form.querySelector<HTMLInputElement>(`[data-choice="${k}"][data-color]`);
            if (colour && typeof v === 'string' && v) { colour.value = spoolCode(v); paintChoice(colour); return; }
            const choice = form.querySelector<HTMLInputElement>(`[data-choice="${k}"][value="${String(v)}"]`); if (choice) choice.checked = true;
        });
        if ('typeface' in set) showFace();
        if (compose && Array.isArray(set.layers)) {
            layers.splice(0, layers.length, ...(set.layers as Partial<Layer>[]).map((l) => ({
                kind: String(l.kind ?? 'shape'), text: String(l.text ?? ''), typeface: String(l.typeface ?? 'sans'), art: String(l.art ?? ''), art_name: String(l.art_name ?? ''), shape: String(l.shape ?? 'rounded'),
                x: Number(l.x ?? 0), y: Number(l.y ?? 0), w: Number(l.w ?? 50), turn: Number(l.turn ?? 0), code: spoolCode(String(l.code ?? 'white')), hidden: Boolean(l.hidden),
            })));
            chosen = Math.min(chosen, layers.length - 1);
            renderLayers();
        }
        if (Array.isArray(set.holes)) { holes.splice(0, holes.length, ...(set.holes as Hole[]).map((h) => ({ ...h }))); renderHoles(); }
        if (Array.isArray(set.bins)) { bins.splice(0, bins.length, ...(set.bins as Bin[]).map((b) => ({ x: b.x, y: b.y, w: b.w, h: b.h, color: spoolCode(b.color) }))); renderGrid(); }
        if ('artwork' in set) {
            if (typeof set.artwork === 'string' && set.artwork) setArtwork({ ref: set.artwork, name: (set._artwork_name as string) ?? '', url: null });
            else setArtwork(null);
        }
        if ('strokes' in set) strokes.splice(0, strokes.length, ...(Array.isArray(set.strokes) ? (set.strokes as Stroke[]).map((s) => ({ c: spoolCode(s.c), w: Number(s.w), t: String(s.t), p: s.p.map((pt) => [Number(pt[0]), Number(pt[1])] as [number, number]) })) : []));
        if ('merge' in set) merge = Array.isArray(set.merge) ? (set.merge as number[][]).map((pair) => [Number(pair[0]), Number(pair[1])]) : [];
        if ('order' in set) order = Array.isArray(set.order) ? (set.order as number[]).map(Number) : [];
        if (set.part_colors && typeof set.part_colors === 'object') {
            Object.keys(partColors).forEach((p) => delete partColors[p]);
            Object.entries(set.part_colors as Record<string, unknown>).forEach(([part, c]) => { const code = typeof c === 'string' ? c : (c as { code?: string } | null)?.code; if (code) partColors[part] = spoolCode(code); });
        }
        applyWhen();   // a preset or a reopened design may have changed the choice the visible fields depend on
    };

    // ── a picture instead of the text: upload, the library, own pictures ───
    /** The photo's own sliders show at once on the small picture; the model follows a moment later. */
    const adjustThumb = (): void => {
        const img = document.querySelector<HTMLImageElement>('#param-artwork-thumb img'); if (!img || !shape) return;
        const v = (k: string): number => Number(form.querySelector<HTMLInputElement>(`[data-param="${k}"]`)?.value ?? 100);
        img.style.filter = `contrast(${v('contrast')}%) brightness(${v('brightness')}%) saturate(${v('saturation')}%)`;
    };
    const setArtwork = (picked: PickedArtwork | null): void => {
        if (shape && (picked?.ref ?? null) !== artwork) { forgetColours(); strokes.length = 0; }      // another picture: what was drawn on the old one does not fit it
        artwork = picked?.ref ?? null;
        // the thumbnail by the reference wherever it can be: an address of this very page load would not survive a reload
        const lib = /^lib:(.+)$/.exec(picked?.ref ?? ''); const own = /^[0-9a-f-]{36}$/.test(picked?.ref ?? '');
        if (picked) picked = { ...picked, url: lib ? `${stage.cfg.artwork.library}/${lib[1]}.svg` : own ? `${stage.cfg.artwork.file}/${picked.ref}` : picked.url };
        artworkShown = picked ? { name: picked.name, url: picked.url } : null;
        const state = document.getElementById('param-artwork-state'); const thumb = document.getElementById('param-artwork-thumb'); const open = document.getElementById('param-artwork-open');
        if (!state || !thumb || !open) return;
        const label = open.lastChild; if (label) label.textContent = stage.t(picked ? 'toolpage.artwork.change' : 'toolpage.artwork.choose');
        thumb.innerHTML = picked?.url ? `<img src="${picked.url.replace(/"/g, '&quot;')}" alt="" class="max-h-full max-w-full object-contain">` : '';
        thumb.classList.toggle('hidden', !picked?.url); thumb.classList.toggle('flex', !!picked?.url);
        adjustThumb();
        state.textContent = '';
        if (!picked || !form.querySelector('[data-text]')) return;      // nothing to fall back on: the picture can only be changed
        const b = document.createElement('button'); b.type = 'button'; b.className = 'text-left text-sm text-muted underline'; b.textContent = stage.t('toolpage.artwork.remove');
        b.onclick = () => { setArtwork(null); void refresh().then(commit); };
        state.appendChild(b);
    };
    const artOpen = document.getElementById('param-artwork-open');
    if (artOpen) artOpen.onclick = async () => { const picked = await pickArtwork('library', cfg.kind === 'cookie' ? 'cookies' : ''); if (picked) { setArtwork(picked); void refresh().then(commit); } };

    // ── modular organizer: bins on the customer's own grid ─────────────────
    const grid = document.getElementById('bin-grid');
    const gridSize = (): [number, number] => {
        const v = (k: string, d: number) => Math.max(1, Math.min(12, Math.round(Number(form.querySelector<HTMLInputElement>(`[data-param="${k}"]`)?.value) || d)));
        return [v('cols', 6), v('rows', 3)];
    };
    const binAt = (x: number, y: number): number => bins.findIndex((b) => x >= b.x && x < b.x + b.w && y >= b.y && y < b.y + b.h);
    const say = (k: string, r: Record<string, string | number> = {}): void => { const el = document.getElementById('bins-state'); if (el) el.textContent = k ? t(k, r) : ''; };
    const rgb = (c: string): [number, number, number] => FILAMENT[c] ?? FILAMENT.white;
    const css = (c: string): string => `rgb(${rgb(c).map((v) => Math.round(v * 255)).join(',')})`;
    const light = (c: string): boolean => { const [r, g, b] = rgb(c); return 0.299 * r + 0.587 * g + 0.114 * b > 0.6; };
    const renderGrid = (): void => {
        if (!grid) return;
        const [cols, rows] = gridSize();
        for (let i = bins.length - 1; i >= 0; i--) if (bins[i].x + bins[i].w > cols || bins[i].y + bins[i].h > rows) bins.splice(i, 1);   // the grid shrank
        if (binSelected >= bins.length) binSelected = -1;
        grid.style.gridTemplateColumns = `repeat(${cols}, minmax(0, 1fr))`;
        grid.innerHTML = '';
        for (let y = rows - 1; y >= 0; y--) {                                   // row 0 is the front of the drawer: drawn at the bottom
            for (let x = 0; x < cols; x++) {
                const i = binAt(x, y);
                const cell = document.createElement('button');
                cell.type = 'button'; cell.setAttribute('role', 'gridcell');
                cell.className = 'aspect-square min-h-7 rounded-md border text-xs font-semibold';
                if (i >= 0) {
                    const b = bins[i];
                    cell.style.background = css(b.color); cell.style.color = light(b.color) ? '#172B4D' : '#fff';
                    cell.style.borderColor = i === binSelected ? '#C94714' : 'transparent'; cell.style.borderWidth = i === binSelected ? '3px' : '1px';
                    cell.textContent = x === b.x && y === b.y + b.h - 1 ? `${b.w}×${b.h}` : '';
                    cell.setAttribute('aria-label', t('param.bins.bin', { s: `${b.w} × ${b.h}`, c: colorName(b.color) }));
                } else {
                    const start = binStart && binStart.x === x && binStart.y === y;
                    cell.className += start ? ' border-ink bg-slate-200' : ' border-dashed border-slate-300 bg-white';
                    cell.setAttribute('aria-label', t('param.bins.empty', { x: x + 1, y: y + 1 }));
                }
                cell.onclick = () => clickCell(x, y);
                grid.appendChild(cell);
            }
        }
    };
    /** Two taps make a bin: the first corner, then the opposite one. A tap on a bin selects it (colour, remove). */
    const clickCell = (x: number, y: number): void => {
        const hit = binAt(x, y);
        if (hit >= 0 && !binStart) { binSelected = hit === binSelected ? -1 : hit; if (binSelected >= 0) binColor = bins[hit].color; renderColors(); renderGrid(); return; }
        if (!binStart) { binStart = { x, y }; binSelected = -1; say('param.bins.pick_end'); renderGrid(); return; }
        const nb = { x: Math.min(binStart.x, x), y: Math.min(binStart.y, y), w: Math.abs(binStart.x - x) + 1, h: Math.abs(binStart.y - y) + 1, color: binColor };
        binStart = null;
        const clash = bins.some((b) => nb.x < b.x + b.w && nb.x + nb.w > b.x && nb.y < b.y + b.h && nb.y + nb.h > b.y);
        if (clash) { say('param.bins.taken'); renderGrid(); return; }
        if (bins.length >= 24) { renderGrid(); return; }
        bins.push(nb); say(''); renderGrid(); soon();
    };
    const setBinColor = (code: string): void => {
        binColor = code; rememberColor(code);
        if (binSelected >= 0) { bins[binSelected].color = code; renderGrid(); soon(); }
        renderColors(); renderRecent();
    };
    /** The colour new bins get (and the selected bin has): one swatch that opens the colour window, the recent ones beside it. */
    const renderColors = (): void => {
        const box = document.getElementById('bin-colors'); if (!box) return;
        box.innerHTML = '';
        const main = document.createElement('button');
        main.type = 'button'; main.className = 'tool-swatch'; paintSwatch(main, binColor); main.setAttribute('aria-label', `${colorName(binColor)}: ${stage.t('toolpage.color.pick')}`);
        main.onclick = async () => { const picked = await pickColor(binColor); if (picked) setBinColor(picked); };
        const name = document.createElement('span'); name.className = 'text-sm text-muted'; name.textContent = colorName(binColor);
        box.append(main, name);
        [...new Set([...bins.map((b) => b.color), ...recentColors()])].filter((c) => c !== binColor).slice(0, 6).forEach((c) => {
            const b = document.createElement('button');
            b.type = 'button'; b.className = 'tool-swatch-sm'; paintSwatch(b, c); b.setAttribute('aria-label', colorName(c));
            b.onclick = () => setBinColor(c);
            box.appendChild(b);
        });
    };
    if (grid) {
        // a sensible start: a long tray for pens at the back, small bins in front (like a desk drawer)
        const [blue, orange, white] = ['blue', 'orange', 'white'].map(spoolCode);
        bins.push({ x: 0, y: 2, w: 4, h: 1, color: blue }, { x: 4, y: 1, w: 2, h: 2, color: orange }, { x: 0, y: 0, w: 2, h: 2, color: white },
            { x: 2, y: 0, w: 2, h: 2, color: blue }, { x: 4, y: 0, w: 2, h: 1, color: white });
        document.getElementById('bins-fill')!.onclick = () => {
            const [cols, rows] = gridSize();
            for (let y = 0; y < rows; y++) for (let x = 0; x < cols; x++) if (binAt(x, y) < 0 && bins.length < 24) bins.push({ x, y, w: 1, h: 1, color: binColor });
            renderGrid(); soon();
        };
        document.getElementById('bins-remove')!.onclick = () => { if (binSelected >= 0) { bins.splice(binSelected, 1); binSelected = -1; renderGrid(); soon(); } };
        document.getElementById('bins-clear')!.onclick = () => { bins.splice(0, bins.length); binSelected = -1; binStart = null; renderGrid(); soon(); };
        form.querySelectorAll<HTMLInputElement>('[data-param="cols"],[data-param="rows"]').forEach((i) => i.addEventListener('input', renderGrid));
        renderColors(); renderGrid();
    }

    // ── presets, inputs, order choices ─────────────────────────────────────
    form.querySelectorAll<HTMLButtonElement>('[data-preset]').forEach((b) => {
        b.onclick = () => {
            applyValues(cfg.presets[b.dataset.preset!] ?? {});
            form.querySelectorAll('[data-preset]').forEach((o) => o.classList.toggle('chip-on', o === b));
            void refresh().then(commit);
        };
    });
    // a symbol goes where the cursor was in the text field used last
    let lastText = form.querySelector<HTMLInputElement>('[data-text]');
    form.querySelectorAll<HTMLInputElement>('[data-text]').forEach((i) => i.addEventListener('focus', () => { lastText = i; }));
    form.querySelectorAll<HTMLButtonElement>('[data-symbol]').forEach((b) => b.addEventListener('click', () => {
        const input = lastText;
        if (!input) return;
        const at = input.selectionStart ?? input.value.length;
        const end = input.selectionEnd ?? at;
        const next = input.value.slice(0, at) + b.dataset.symbol! + input.value.slice(end);
        if (input.maxLength > 0 && next.length > input.maxLength) return;
        input.value = next;
        input.focus();
        const caret = at + b.dataset.symbol!.length;
        input.setSelectionRange(caret, caret);
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }));

    // a slider and its number are one value
    const syncRange = (num: HTMLInputElement): void => { const r = form.querySelector<HTMLInputElement>(`[data-range="${num.dataset.param}"]`); if (r && r.value !== num.value) r.value = num.value; };
    form.querySelectorAll<HTMLInputElement>('[data-range]').forEach((r) => r.addEventListener('input', (e) => {
        const num = form.querySelector<HTMLInputElement>(`[data-param="${r.dataset.range}"]`);
        if (!num) return;
        e.stopPropagation();                                 // the number speaks for both
        num.value = r.value;
        num.dispatchEvent(new Event('input', { bubbles: true }));
    }));

    // ── sizes dragged in the viewer: an arrow on the wall a size moves ─────
    const fieldOf = (id: string): HTMLInputElement | null => form.querySelector<HTMLInputElement>(`[data-param="${id}"]`);
    const shownField = (id: string): boolean => { const f = fieldOf(id); return !!f && !f.closest('.is-off'); };
    let dragFrom: number | null = null;
    const setHandles = (): void => {
        const list = viewPart === 'all' ? Object.entries(cfg.handles ?? {}).filter(([id]) => shownField(id)).map(([id, axis]) => ({ id, axis })) : [];
        viewer.setHandles(list, (id, mm, phase) => {
            const input = fieldOf(id); if (!input) return;
            if (dragFrom === null) dragFrom = Number(input.value);
            // a size that grows to both sides counts the dragged way twice (the model stays centred), the height once
            const step = Number(input.step) || 1;
            const raw = dragFrom + mm * (cfg.handles[id] === 'z' ? 1 : 2);
            const value = Math.max(Number(input.min), Math.min(Number(input.max), Math.round(raw / step) * step));
            const text = String(Math.round(value * 100) / 100);
            if (input.value !== text) { input.value = text; syncRange(input); applyWhen(); renderPrice(); soon(150); }
            if (phase === 'end') { dragFrom = null; soon(0); }
            const label = input.closest('[data-field]')?.querySelector('label')?.textContent?.trim() ?? '';
            return `${label} ${stage.t('toolpage.handle.mm', { v: nf.format(value) })}`.trim();
        });
    };

    // ── the eyelet of a pendant: a grip on the outline, dragged in the viewer ──
    const placeEyelet = (): void => {
        const n = shapeNotes();
        if (!shape || !n.eyelet || !n.outline || viewPart !== 'all' || drawing) { viewer.setMarker(null, null); return; }
        viewer.setMarker({ path: n.outline, z: n.eyelet.z, at: [n.eyelet.x, n.eyelet.y] }, (share, phase) => {
            const input = fieldOf('eye_pos'); if (!input) return;
            const value = String((Math.round(share * 200) / 2) % 100);
            if (input.value !== value) { input.value = value; syncRange(input); soon(phase === 'end' ? 0 : 150); } else if (phase === 'end') soon(0);
            return `${nf.format(Number(value))} %`;
        });
    };
    document.getElementById('shape-eyelet-top')?.addEventListener('click', () => {
        const input = fieldOf('eye_pos'); if (!input) return;
        input.value = '0'; syncRange(input); soon(0);
    });

    // ── the chosen layer of a composition: a frame in the viewer to move, resize and turn it by ──
    const showSlide = (l: Layer, k: 'w' | 'x' | 'y' | 'turn'): void => {
        const s = document.querySelector<HTMLInputElement>(`#compose-edit [data-layer-slide="${k}"]`);
        if (s) s.value = String(l[k]);
        const v = document.querySelector<HTMLElement>(`#compose-edit [data-layer-value="${k}"]`);
        if (v) v.textContent = `${nf.format(l[k])} ${k === 'turn' ? '°' : t('compose.unit')}`;
    };
    const placeFrame = (): void => {
        const l = layers[chosen]; const n = shapeNotes();
        // the tool numbers the layers it built: the hidden ones are not among them
        const at = l && !l.hidden ? layers.slice(0, chosen).filter((x) => !x.hidden).length + 1 : 0;
        const built = n.layers?.find((x) => x.index === at);
        if (!compose || !l || !built || !valid || viewPart !== 'all') { viewer.setFrame(null, null); return; }
        let from: { x: number; y: number; w: number; turn: number } | null = null;
        viewer.setFrame({ box: built.box, z: n.outer?.[2] ?? built.z }, (c, phase) => {
            from ??= { x: l.x, y: l.y, w: l.w, turn: l.turn };
            const within = (v: number, lo: number, hi: number): number => Math.max(lo, Math.min(hi, v));
            l.x = within(Math.round((from.x + c.dx) * 2) / 2, -150, 150);
            l.y = within(Math.round((from.y + c.dy) * 2) / 2, -150, 150);
            l.w = within(Math.round(from.w * c.scale), 5, 250);
            l.turn = ((Math.round(from.turn + c.turn) + 540) % 360) - 180;
            (['w', 'x', 'y', 'turn'] as const).forEach((k) => showSlide(l, k));
            const said = c.turn ? `${nf.format(l.turn)}°` : c.scale !== 1 ? `${nf.format(l.w)} ${t('compose.unit')}` : `${nf.format(l.x)} × ${nf.format(l.y)} ${t('compose.unit')}`;
            if (phase === 'end') { from = null; void refresh().then(commit); }
            return said;
        });
    };

    // ── a composition: its layers and the fields of the chosen one ──
    /** What a picture is called in the list: the name it came with, or the last word of its place in the library. */
    const artName = (l: Layer): string => l.art_name || (l.art.startsWith('lib:') ? (l.art.split('/').pop() ?? '') : '');
    const renderLayers = (): void => {
        const box = document.getElementById('compose-layers'); const edit = document.getElementById('compose-edit');
        if (!compose || !box || !edit) return;
        box.innerHTML = layers.length ? '' : `<p class="text-sm text-muted">${t('compose.empty')}</p>`;
        // the list shows the layers as they lie: the top one first
        layers.map((l, i) => i).reverse().forEach((i) => {
            const l = layers[i];
            const row = document.createElement('div');
            row.className = `flex items-center gap-1 rounded-lg border px-2 py-1 text-sm ${i === chosen ? 'border-ink bg-white' : 'border-transparent'} ${l.hidden ? 'opacity-50' : ''}`;
            const what = l.kind === 'text' ? (l.text || '…') : l.kind === 'art' ? artName(l) : (document.querySelector<HTMLOptionElement>(`#compose-shape option[value="${l.shape}"]`)?.textContent ?? l.shape);
            const act = (name: string, sign: string, off = false): string => `<button type="button" class="chip !min-h-8 !px-2 !py-0.5" data-layer-act="${name}" aria-label="${t(`compose.layer.${name}`)}" title="${t(`compose.layer.${name}`)}" ${off ? 'disabled' : ''}>${sign}</button>`;
            row.innerHTML = `<button type="button" class="flex min-w-0 flex-1 items-center gap-2 text-left" data-layer-pick>
                    <span class="inline-block h-3 w-3 shrink-0 rounded-full border border-line" style="background:${colorOf(l.code)?.hex ?? '#888888'}"></span>
                    <span class="min-w-0 truncate text-ink" title="${what.replace(/[<>&"]/g, '')}">${t(`compose.layer.${l.kind}`)}: ${what.replace(/[<>&]/g, '')}</span></button>
                ${act('up', '↑', i === layers.length - 1)}${act('down', '↓', i === 0)}${act(l.hidden ? 'show' : 'hide', l.hidden ? '○' : '●')}${act('copy', '⧉', layers.length >= 12)}${act('remove', '×')}`;
            row.querySelector<HTMLButtonElement>('[data-layer-pick]')!.onclick = () => { chosen = i; renderLayers(); placeFrame(); };
            row.querySelectorAll<HTMLButtonElement>('[data-layer-act]').forEach((b) => {
                b.onclick = () => {
                    const a = b.dataset.layerAct;
                    if (a === 'up' || a === 'down') { const to = i + (a === 'up' ? 1 : -1); [layers[i], layers[to]] = [layers[to], layers[i]]; chosen = to; }
                    if (a === 'hide' || a === 'show') l.hidden = !l.hidden;
                    if (a === 'copy') { layers.splice(i + 1, 0, { ...l, x: l.x + 6, y: l.y - 6 }); chosen = i + 1; }
                    if (a === 'remove') { layers.splice(i, 1); chosen = Math.max(0, Math.min(chosen, layers.length - 1)); }
                    renderLayers(); void refresh().then(commit);
                };
            });
            box.appendChild(row);
        });
        const l = layers[chosen];
        edit.classList.toggle('hidden', !l);
        if (!l) return;
        edit.querySelectorAll<HTMLElement>('[data-layer-for]').forEach((el) => el.classList.toggle('hidden', el.dataset.layerFor !== l.kind));
        ($('compose-text') as HTMLInputElement).value = l.text;
        ($('compose-font') as HTMLSelectElement).value = l.typeface;
        ($('compose-shape') as HTMLSelectElement).value = l.shape;
        $('compose-art-name').textContent = artName(l);
        paintSwatch($('compose-color'), l.code);
        $('compose-color-name').textContent = colorOf(l.code) ? `${colorOf(l.code)!.name} · ${materialLabel(colorOf(l.code)!)}` : colorName(l.code);
        edit.querySelectorAll<HTMLInputElement>('[data-layer-slide]').forEach((s) => {
            const k = s.dataset.layerSlide as 'w' | 'x' | 'y' | 'turn';
            s.value = String(l[k]);
            edit.querySelector<HTMLElement>(`[data-layer-value="${k}"]`)!.textContent = `${nf.format(l[k])} ${k === 'turn' ? '°' : t('compose.unit')}`;
        });
    };
    if (compose) {
        const changed = (): void => { renderLayers(); void refresh().then(commit); };
        const now = (): Layer | undefined => layers[chosen];
        // these fields belong to a layer, not to the design: the form's own listener must not take them for parameters
        const own = (el: HTMLElement, on: string, f: () => void): void => el.addEventListener(on, (e) => { e.stopPropagation(); f(); });
        own($('compose-text'), 'input', () => { const l = now(); if (l) l.text = ($('compose-text') as HTMLInputElement).value; });
        own($('compose-text'), 'change', changed);
        own($('compose-font'), 'input', () => undefined);
        own($('compose-font'), 'change', () => { const l = now(); if (l) { l.typeface = ($('compose-font') as HTMLSelectElement).value; changed(); } });
        own($('compose-shape'), 'input', () => undefined);
        own($('compose-shape'), 'change', () => { const l = now(); if (l) { l.shape = ($('compose-shape') as HTMLSelectElement).value; changed(); } });
        document.querySelectorAll<HTMLInputElement>('#compose-edit [data-layer-slide]').forEach((s) => {
            const k = s.dataset.layerSlide as 'w' | 'x' | 'y' | 'turn';
            own(s, 'input', () => { const l = now(); if (!l) return; l[k] = Number(s.value); document.querySelector<HTMLElement>(`#compose-edit [data-layer-value="${k}"]`)!.textContent = `${nf.format(l[k])} ${k === 'turn' ? '°' : t('compose.unit')}`; });
            own(s, 'change', changed);
        });
        $('compose-color').onclick = async () => {
            const l = now(); if (!l) return;
            lastColorTarget = () => (code) => { l.code = code; changed(); };
            const picked = await pickColor(l.code);
            if (picked) { l.code = picked; rememberColor(picked); changed(); }
        };
        $('compose-art').onclick = async () => {
            const l = now(); if (!l) return;
            const picked = await pickArtwork('library');
            if (picked) { l.art = picked.ref; l.art_name = picked.name; changed(); }
        };
        document.querySelectorAll<HTMLButtonElement>('[data-add-layer]').forEach((b) => {
            b.onclick = async () => {
                if (layers.length >= 12) { showError(t('compose.limit')); return; }
                const kind = b.dataset.addLayer!;
                const fresh: Layer = { kind, text: kind === 'text' ? 'Text' : '', typeface: 'sans', art: '', art_name: '', shape: 'rounded', x: 0, y: 0, w: kind === 'shape' ? 80 : 40, turn: 0, code: spoolCode(layers.length ? 'black' : 'white'), hidden: false };
                if (kind === 'art') {
                    const picked = await pickArtwork('library');
                    if (!picked) return;
                    fresh.art = picked.ref; fresh.art_name = picked.name;
                }
                layers.push(fresh); chosen = layers.length - 1;
                changed();
            };
        });
    }

    // ── icing piped on a biscuit: strokes drawn in the viewer ──
    let penChosen = false;
    const renderPen = (): void => {
        if (!cookie) return;
        if (!penChosen && !strokes.length) {
            // the pen starts with the lightest filament the biscuit already has: two near-whites would be two spools
            const light = (hex: string): number => { const v = parseInt(hex.slice(1), 16); return (0.299 * (v >> 16) + 0.587 * ((v >> 8) & 255) + 0.114 * (v & 255)) / 255; };
            const lightest = [...(shapeNotes().colors ?? [])].sort((x, y) => light(y.hex) - light(x.hex))[0];
            pen = lightest && light(lightest.hex) > 0.75 ? lightest.code : spoolCode('white');
        }
        paintSwatch($('cookie-pen'), pen);
        $('cookie-pen-name').textContent = colorOf(pen) ? `${colorOf(pen)!.name} · ${materialLabel(colorOf(pen)!)}` : colorName(pen);
        $('cookie-count').textContent = strokes.length ? t('cookie.count', { n: strokes.length }) : t('cookie.hint');
        $('cookie-width-v').textContent = `${nf.format(Number(($('cookie-width') as HTMLInputElement).value))} mm`;
        renderStrokes();
    };
    /** Every stroke drawn so far, each with its own arrows and its own way out: any of them can be nudged or taken away, not only the last. */
    const renderStrokes = (): void => {
        const box = document.getElementById('cookie-strokes'); if (!box) return;
        box.innerHTML = '';
        const moves: [string, number, number][] = [['←', -1, 0], ['↑', 0, 1], ['↓', 0, -1], ['→', 1, 0]];
        strokes.forEach((s, i) => {
            const row = document.createElement('div');
            row.className = 'flex items-center gap-1 text-sm';
            row.innerHTML = `<span class="inline-block h-3 w-3 shrink-0 rounded-full border border-line" style="background:${colorOf(s.c)?.hex ?? '#888888'}"></span>
                <span class="min-w-0 flex-1 truncate text-ink">${t('cookie.stroke', { n: i + 1 })} · ${t(`cookie.nib.${s.t}`)}</span>
                ${moves.map(([sign], k) => `<button type="button" class="chip !min-h-8 !px-2 !py-0.5" data-move="${k}" aria-label="${t('cookie.stroke.move')} ${sign}" title="${t('cookie.stroke.move')}">${sign}</button>`).join('')}
                <button type="button" class="chip !min-h-8 !px-2 !py-0.5" data-remove aria-label="${t('cookie.stroke.remove')}" title="${t('cookie.stroke.remove')}">×</button>`;
            row.querySelectorAll<HTMLButtonElement>('[data-move]').forEach((b) => {
                b.onclick = () => {
                    const [, dx, dy] = moves[Number(b.dataset.move)];
                    // two hundredths of the picture's width a step: 1.6 mm on a biscuit of 80 mm
                    s.p = s.p.map(([x, y]) => [Math.round((x + dx * 0.02) * 10000) / 10000, Math.round((y + dy * 0.02) * 10000) / 10000]);
                    void refresh().then(commit);
                };
            });
            row.querySelector<HTMLButtonElement>('[data-remove]')!.onclick = () => { strokes.splice(i, 1); renderPen(); void refresh().then(commit); };
            box.appendChild(row);
        });
    };
    /** Points of a stroke no closer than 0.6 mm, at most 48 of them: what the hand drew, light enough to send. */
    const thinned = (pts: [number, number][]): [number, number][] => {
        const kept: [number, number][] = [];
        pts.forEach((p, i) => { const last = kept[kept.length - 1]; if (!last || i === pts.length - 1 || Math.hypot(p[0] - last[0], p[1] - last[1]) >= 0.6) kept.push(p); });
        if (kept.length <= 48) return kept;
        return Array.from({ length: 48 }, (_, i) => kept[Math.round((i * (kept.length - 1)) / 47)]);
    };
    const armDraw = (): void => {
        const n = shapeNotes();
        if (!cookie || !drawing || !n.frame) { viewer.setDraw(null, null); return; }
        viewer.setMarker(null, null);
        viewer.setDraw({ z: n.draw_z ?? 0, color: colorOf(pen)?.hex ?? '#f4f4f2' }, (pts) => {
            const frame = shapeNotes().frame; if (!frame) return;
            if (strokes.length >= 60) { showError(t('cookie.limit')); return; }
            const share = (v: number): number => Math.round(v * 10000) / 10000;
            strokes.push({
                c: pen, w: Number(($('cookie-width') as HTMLInputElement).value), t: form.querySelector<HTMLInputElement>('input[name="cookie-nib"]:checked')?.value ?? 'round',
                p: thinned(pts).map(([x, y]) => [share((x - frame[0]) / frame[2]), share((y - frame[1]) / frame[2])]),
            });
            renderPen(); void refresh().then(commit);
        });
    };
    if (cookie) {
        const drawBtn = $('cookie-draw');
        drawBtn.onclick = () => {
            drawing = !drawing;
            drawBtn.setAttribute('aria-pressed', drawing ? 'true' : 'false');
            drawBtn.querySelector('span')!.textContent = t(drawing ? 'cookie.draw.on' : 'cookie.draw');
            armDraw(); placeEyelet();
        };
        $('cookie-pen').onclick = async () => {
            lastColorTarget = () => (code) => { pen = code; penChosen = true; renderPen(); armDraw(); };
            const picked = await pickColor(pen);
            if (picked) { pen = picked; penChosen = true; rememberColor(picked); renderPen(); armDraw(); }
        };
        // the pen's own controls are not part of the design: they must not start a new preview
        $('cookie-width').addEventListener('input', (e) => { e.stopPropagation(); renderPen(); });
        form.querySelectorAll<HTMLInputElement>('input[name="cookie-nib"]').forEach((r) => r.addEventListener('input', (e) => e.stopPropagation()));
        $('cookie-undo').onclick = () => { if (strokes.pop()) { renderPen(); void refresh().then(commit); } };
        $('cookie-clear').onclick = () => { if (strokes.length) { strokes.length = 0; renderPen(); void refresh().then(commit); } };
        renderPen();
    }

    // fields and flags that belong to one choice only ("data-when=style=desk,wedge") fold away for the other choices;
    // the key may be a flag too ("data-when=mount=on")
    const applyWhen = (): void => {
        form.querySelectorAll<HTMLElement>('[data-when]').forEach((el) => {
            const [key, list] = (el.dataset.when ?? '').split('=');
            const flag = form.querySelector<HTMLInputElement>(`[data-flag="${key}"]`);
            const current = form.querySelector<HTMLInputElement>(`[data-choice="${key}"]:checked`)?.value ?? (flag ? (flag.checked ? 'on' : 'off') : '');
            const off = !list.split(',').includes(current);
            el.classList.toggle('is-off', off);
            el.toggleAttribute('inert', off);
        });
        setHandles();
    };
    applyWhen();
    // a standard picked by name (an M10 thread) writes its numbers into the fields; a number edited by hand means "custom"
    const fills = cfg.fills ?? {};
    form.addEventListener('input', (e) => {
        const el = e.target as HTMLInputElement;
        if (el.dataset.choice && fills[el.dataset.choice]?.[el.value]) applyValues(fills[el.dataset.choice][el.value]);
        // what changes the colours the picture is read in also empties what was said about them
        if (shape && (['colors_n', 'bg_strength', 'contrast', 'brightness', 'saturation'].includes(el.dataset.param ?? '') || el.dataset.flag === 'remove_bg')) { forgetColours(); adjustThumb(); }
        if (el.dataset.param) {
            syncRange(el);
            Object.entries(fills).forEach(([choice, table]) => {
                if (!Object.values(table).some((v) => el.dataset.param! in v)) return;
                const radios = form.querySelectorAll<HTMLInputElement>(`[data-choice="${choice}"]`);
                if (radios.length && !radios[0].checked) radios[0].checked = true;
            });
        }
    });
    form.addEventListener('input', (e) => { if ((e.target as HTMLElement).closest('#holes')) return; applyWhen(); soon(); renderPrice(); });
    form.addEventListener('submit', (e) => e.preventDefault());

    // a click on a piece in the viewer: the panel jumps to what belongs to it
    viewer.onPiecePicked((index, piece) => {
        viewer.select(index);
        stage.note(piece && viewer.getPieces().length > 1 ? stage.t('toolpage.status.piece', { name: partLabel(piece.name) }) : null);
        if (!piece) return;
        if (cfg.kind === 'modular') { stage.reveal('size', document.getElementById('bin-grid')); return; }
        if (compose) {
            // the piece is a band of the print; its layer is the one of that number among the shown ones
            const shown = layers.map((l, i) => (l.hidden ? -1 : i)).filter((i) => i >= 0);
            const i = shown[Number(piece.name.replace('layer_', '')) - 1];
            if (i === undefined) return;
            viewer.select(-1);
            if (i !== chosen) { chosen = i; renderLayers(); placeFrame(); }
            return;
        }
        const row = document.querySelector<HTMLElement>(`#tool-parts [data-part="${piece.name}"]`) ?? document.querySelector<HTMLElement>('#tool-parts [data-part]');
        if (row) { lastColorTarget = () => (code) => setPartColor(row.dataset.part!, code); stage.reveal('colors', row); } else stage.reveal('colors');
    });

    // the design is saved (our own geometry, free) and opens in the calculation; "download" opens the printer picker there
    const save = async (download: boolean, slicer = ''): Promise<void> => {
        if (!valid) return;
        const label = stage.el('tool-go-label').textContent ?? '';
        stage.go({ disabled: true, ...(download ? {} : { label: t('param.creating') }) });
        try {
            const res = await fetch(cfg.create, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ kind: cfg.kind, params: params() }) });
            if (!res.ok) { showError(await errorOf(res)); stage.go({ disabled: false, label }); return; }
            const body = await res.json();
            // what the colours of the parts are travels on as a note: the farm and a printer read it with the order
            const rows = partRows();
            const colourNote = shape ? rows.filter((p) => shapeCode(p)).map((p) => `${partLabel(p)}: ${colorName(shapeCode(p)!)}`).join('; ')
                : rows.length > 1 ? rows.filter((p) => partColors[p]).map((p) => `${partLabel(p)}: ${colorName(partColors[p])}`).join('; ') : '';
            const note = [lastMeta ? bomText(lastMeta).join('; ') : '', colourNote ? `${stage.t('toolpage.color.note')}: ${colourNote}` : ''].filter(Boolean).join(' | ');
            const plate = params().plate_color;      // a two-colour design: the plate is "the colour", the second one travels with the design
            const q = new URLSearchParams({
                ...(note ? { note: note.slice(0, 900) } : {}), open: body.file.uuid, material: ($('param-material') as HTMLSelectElement).value, quantity: ($('param-qty') as HTMLInputElement).value || '1',
                color: typeof plate === 'string' ? colorName(plate) : ($('param-color') as HTMLInputElement).value, ...(download ? { download: '1' } : {}), ...(slicer ? { slicer } : {}),
            });
            location.href = `${cfg.home}?${q}`;
        } catch {
            showError(t('param.failed')); stage.go({ disabled: false, label });
        }
    };
    stage.go({ run: () => save(false), disabled: true });
    const project = document.getElementById('param-3mf') as HTMLButtonElement | null;
    if (project) project.onclick = () => { void save(true); };

    // what undo, redo and "restore my last settings" carry: the whole form
    const track = (offerSaved: boolean): void => {
        commit = stage.track({
            read: () => ({ ...params(), holes: holes.map((h) => ({ ...h })), bins: bins.map((b) => ({ ...b })), merge: merge.map((pair) => [...pair]), order: [...order], strokes: strokes.map((s) => ({ ...s, p: s.p.map((pt) => [...pt]) })), artwork: artwork ?? '', _artwork_name: artworkShown?.name ?? '', part_colors: { ...partColors },
                _material: ($('param-material') as HTMLSelectElement).value, _qty: ($('param-qty') as HTMLInputElement).value }),
            write: (s) => {
                applyValues(s);
                if (typeof s._material === 'string') ($('param-material') as HTMLSelectElement).value = s._material;
                if (typeof s._qty === 'string') ($('param-qty') as HTMLInputElement).value = s._qty;
                void refresh();
            },
        }, offerSaved);
    };
    $('param-material').addEventListener('change', () => { renderPrice(); commit(); });
    $('param-qty').addEventListener('input', () => { renderPrice(); commit(); });

    // reopen a stored design ("edit" from the calculator): same numbers, same artwork
    if (cfg.from && /^[0-9a-f-]{36}$/.test(cfg.from)) {
        fetch(`${cfg.files}/${cfg.from}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : null))
            .then((b) => { if (b?.file?.tool?.kind === cfg.kind) applyValues(b.file.tool.params); })
            .catch(() => undefined)
            .finally(() => { track(false); void refresh(); });
    } else {
        const qs = new URLSearchParams(location.search);
        const preset = qs.get('preset') ?? cfg.preset;      // asked for in the address, or the one this page of the tool starts with
        if (preset && cfg.presets[preset]) {
            applyValues(cfg.presets[preset]);
            form.querySelectorAll('[data-preset]').forEach((o) => o.classList.toggle('chip-on', (o as HTMLElement).dataset.preset === preset));
            applyWhen();
        }
        const typed: Record<string, string> = {};
        form.querySelectorAll<HTMLInputElement>('[data-text]').forEach((i) => { const v = qs.get(i.dataset.text!); if (v) typed[i.dataset.text!] = v.slice(0, i.maxLength > 0 ? i.maxLength : 40); });
        if (Object.keys(typed).length) applyValues(typed);
        // a tool that needs a picture opens with one of ours, so the first thing a visitor sees is a finished thing
        // (a name typed in the address stands instead of the picture, unless the tool puts it under the picture)
        if ((cfg.captioned || !Object.keys(typed).length) && cfg.sample && !artwork && (shape ? !preset : preset === cfg.preset)) setArtwork({ ref: cfg.sample, name: '', url: null });
        track(!preset && !Object.keys(typed).length);
        void refresh();
    }
}
