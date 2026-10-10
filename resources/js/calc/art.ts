/**
 * Filament art, as a module of the tool page: a picture in the colours of filaments as a thing for the wall. One print
 * with the colours as steps, or a layered picture of one plate per colour in a frame. The form, the preview (the exact
 * solid from the server, every plate a piece in its filament), the list of colours, the guide back to front, and the
 * downloads; the viewer, the status line and the price card are the page's (tool_page.ts).
 */
import { STLLoader } from 'three/examples/jsm/loaders/STLLoader.js';
import type { Piece } from './viewer';
import type { Stage, MenuItem, PriceConfig } from './tool_page';
import { colorOf, spoolCode, paintSwatch, pickColor, rememberColor, materialLabel } from './colors';
import { pickArtwork, PickedArtwork } from './artwork';
import { icon } from '../site/icon';
import { FileInfo, modelMeta } from './api';

interface Cfg { preview: string; create: string; zip: string; home: string; files: string; parts: string; from: string | null; sample: string | null; config: PriceConfig & { currency: string }; warnAt: Record<string, string>; i18n: Record<string, string> }
interface ArtColor { part: string; index: number; rgb: string; code: string; hex: string; share: number; area_mm2: number }
interface GuidePlate { part: string; index: number; code: string; hex: string; rgb: string; share: number; z: number; posts: [number, number][]; post_d: number; svg: string; own_svg: string }
interface Notes {
    mode: 'stack' | 'layered'; shape: string; frame: string; warnings: string[]; found: number; wanted: number; colors: ArtColor[]; paint: Record<string, string>; parts: string[];
    body_color?: { code: string; hex: string }; frame_color?: { code: string; hex: string }; color_changes?: { z: number }[]; filaments: number; outer: number[]; each: number[][]; picture: number[];
    guide?: GuidePlate[]; plates?: number; depth?: number; frame_outer?: number[]; frame_depth?: number; merged_mm2?: number; thin_pct?: number;
}
interface Meta { bbox: { x: number; y: number; z: number }; volume_mm3: number; area_mm2: number; notes: Notes; parts?: Piece[] }

export function bootArt(stage: Stage): void {
    const cfg = (window as unknown as { MP_ART?: Cfg }).MP_ART;
    const form = document.getElementById('art-form') as HTMLFormElement | null;
    if (!cfg || !form) return;
    const t = (k: string, r: Record<string, string | number> = {}) => Object.entries(r).reduce((s, [a, b]) => s.split(`:${a}`).join(String(b)), cfg.i18n[k] ?? stage.t(k));
    const $ = <T extends HTMLElement>(id: string) => document.getElementById(id) as T;
    const nf = stage.nf; const viewer = stage.viewer;
    const esc = (s: string): string => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c] as string));
    let seq = 0; let timer = 0; let lastMeta: Meta | null = null; let valid = false;
    let artwork: string | null = null; let artworkName = '';
    let merge: number[][] = []; let order: number[] = [];
    const partColors: Record<string, string> = {};
    let commit: () => void = () => undefined;
    const notes = (): Notes => (lastMeta?.notes ?? { mode: 'layered', colors: [], paint: {}, parts: [], warnings: [], filaments: 1, outer: [], each: [], picture: [] }) as Notes;

    const params = (): Record<string, unknown> => {
        const p: Record<string, unknown> = {};
        form.querySelectorAll<HTMLInputElement>('[data-param]').forEach((i) => { p[i.dataset.param!] = Number(i.value); });
        form.querySelectorAll<HTMLInputElement>('[data-flag]').forEach((i) => { p[i.dataset.flag!] = i.checked; });
        form.querySelectorAll<HTMLInputElement>('[data-choice]:checked').forEach((i) => { p[i.dataset.choice!] = i.value; });
        if (artwork) p.artwork = artwork;
        if (merge.length) p.merge = merge;
        if (order.length) p.order = order;
        if (Object.keys(partColors).length) p.part_colors = { ...partColors };
        return p;
    };
    const mode = (): string => String(params().mode ?? 'layered');

    /** Fields that belong to one choice only (data-when="frame=round,square", "flat=off") show for it alone. */
    const applyWhen = (): void => {
        const p = params();
        form.querySelectorAll<HTMLElement>('[data-when]').forEach((el) => {
            const [key, list] = el.dataset.when!.split('=');
            const now = typeof p[key] === 'boolean' ? (p[key] ? 'on' : 'off') : String(p[key] ?? '');
            el.classList.toggle('hidden', !list.split(',').includes(now));
        });
    };

    const showError = (msg: string | null): void => { stage.error(msg); stage.go({ disabled: !!msg || !valid }); };
    const post = (url: string, body: Record<string, unknown>) => fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body) });
    const firstError = (b: { message?: string; errors?: Record<string, string[]> }, status = 0) => (status === 429 ? t('param.too_fast') : Object.values(b.errors ?? {})[0]?.[0] ?? b.message ?? t('param.failed'));
    const errorOf = async (res: Response): Promise<string> => { try { return firstError(await res.json(), res.status); } catch { return firstError({}, res.status); } };

    const partLabel = (part: string): string => {
        if (part === 'body') return t('edit.art.part.body');
        if (part === 'frame') return t('edit.art.part.frame');
        if (part.startsWith('plate_')) return t('edit.art.part.plate', { n: part.slice(6) });
        if (part.startsWith('color_')) return t('edit.art.part.color', { n: part.slice(6) });
        return part;
    };
    /** The filament of a part: the visitor's own choice, else the one the tool matched. */
    const codeOf = (part: string): string | null => {
        if (partColors[part]) return partColors[part];
        const n = notes();
        if (part === 'body') return n.body_color?.code ?? null;
        if (part === 'frame') return n.frame_color?.code ?? null;
        return n.colors.find((c) => c.part === part)?.code ?? null;
    };

    const paintParts = (): void => {
        const paint = notes().paint;
        viewer.getPieces().forEach((piece, i) => {
            const own = partColors[piece.name] ? colorOf(partColors[piece.name])?.hex : null;
            viewer.setPieceColor(i, own ?? paint[piece.name] ?? null);
        });
    };

    /**
     * The colours as a list, the front plate first and the back one (or the base) last, each with the filament it is
     * printed from (a click opens the colour window), the colour in the picture and its share; a colour can be moved
     * forward or back and joined with the one behind it.
     */
    const renderColors = (): void => {
        const box = $('tool-parts'); const n = notes();
        const colors = n.colors;
        box.innerHTML = '';
        const rows = [...(n.frame_color ? ['frame'] : []), ...colors.map((c) => c.part).reverse(), ...(n.body_color ? ['body'] : [])];
        rows.forEach((part) => {
            const c = colors.find((x) => x.part === part);
            const pos = c ? colors.indexOf(c) : -1;
            const code = codeOf(part); const spool = colorOf(code);
            const row = document.createElement('div');
            row.className = 'flex items-center gap-3 rounded-lg';
            const fact = c ? `<span class="inline-block h-3 w-3 shrink-0 rounded-full border border-line" style="background:${c.rgb}" title="${t('edit.colors.picture')}"></span><span class="font-normal text-muted">${t('edit.colors.share', { p: nf.format(Math.round(c.share * 1000) / 10) })}</span>` : '';
            const move = (act: string, label: string, name: string, off: boolean): string => `<button type="button" data-act="${act}" class="chip !min-h-8 !px-2 !py-1" aria-label="${label}" title="${label}" ${off ? 'disabled' : ''}>${icon(name, 'h-3.5 w-3.5')}</button>`;
            const tools = c && colors.length > 1 ? `<span class="flex shrink-0 gap-1">${move('up', t('edit.colors.up'), 'arrow-up', pos === colors.length - 1)}${move('down', t('edit.colors.down'), 'arrow-down', pos === 0)}${move('merge', pos > 0 ? t('edit.colors.merge.into', { n: colors[pos - 1].index }) : t('edit.colors.merge'), 'layers', pos === 0)}</span>` : '';
            row.innerHTML = `<button type="button" class="tool-swatch" aria-label="${esc(partLabel(part))}: ${esc(stage.t('toolpage.color.pick'))}"></button>
                <span class="min-w-0 flex-1 text-sm"><span class="flex items-center gap-1.5 font-medium text-ink">${esc(partLabel(part))}${fact}</span><span class="block truncate text-muted">${spool ? `${esc(spool.name)} · ${esc(materialLabel(spool))}` : esc(stage.t('toolpage.color.pick'))}</span></span>${tools}`;
            const swatch = row.querySelector<HTMLElement>('.tool-swatch')!;
            paintSwatch(swatch, code, c?.hex ?? null);
            swatch.onclick = async () => { const picked = await pickColor(code); if (picked) { rememberColor(picked); partColors[part] = picked; void refresh().then(commit); } };
            row.querySelectorAll<HTMLButtonElement>('[data-act]').forEach((b) => {
                b.onclick = () => {
                    const now = colors.map((x) => x.index);
                    if (b.dataset.act === 'merge') { merge = [...merge, [c!.index, colors[pos - 1].index]]; delete partColors[part]; } else {
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
            undo.type = 'button'; undo.className = 'text-left text-sm text-muted underline'; undo.textContent = t('edit.colors.split');
            undo.onclick = () => { merge = []; order = []; void refresh().then(commit); };
            box.appendChild(undo);
        }
        $('art-found').textContent = n.found && n.wanted && n.found < n.wanted ? t('edit.art.found', { n: n.found, w: n.wanted }) : '';
        const swaps = (n.color_changes ?? []).length;
        $('art-print').textContent = n.mode === 'layered' ? t('edit.art.print.layered', { n: n.plates ?? colors.length }) : swaps > 1 ? t('edit.art.print.stack', { n: swaps }) : swaps ? t('edit.art.print.stack1') : t('edit.art.print.one');
    };

    /** The guide of a layered picture: the plates back to front, each drawn, with its filament and its spacers. */
    const renderGuide = (): void => {
        const box = $('art-guide'); const n = notes();
        if (!n.guide?.length) { box.classList.add('hidden'); return; }
        const [pw, ph] = n.picture;
        const plate = (g: GuidePlate, i: number): string => {
            const depth = i === 0 ? t('edit.art.guide.back') : i === n.guide!.length - 1 ? t('edit.art.guide.front') : '';
            const spool = colorOf(g.code);
            const posts = g.posts.length ? t('edit.art.guide.posts', { n: g.posts.length, d: nf.format(g.post_d) }) : i < n.guide!.length - 1 ? t('edit.art.guide.flat') : '';
            const dots = g.posts.map(([x, y]) => `<circle cx="${x}" cy="${-y}" r="${g.post_d / 2}" fill="#C94714"/>`).join('');
            return `<li class="flex items-center gap-3"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-ink text-xs font-bold text-white">${i + 1}</span>
                <svg viewBox="-2 ${-ph - 2} ${pw + 4} ${ph + 4}" class="h-16 w-16 shrink-0 rounded-lg border border-line bg-white" aria-hidden="true"><path d="${g.svg}" fill="${g.hex}" fill-rule="evenodd" stroke="#172B4D" stroke-width="0.4"/>${dots}</svg>
                <span class="min-w-0 text-sm"><span class="block font-medium text-ink">${esc(partLabel(g.part))}${depth ? ` <span class="font-normal text-muted">· ${esc(depth)}</span>` : ''}</span>
                <span class="block truncate text-muted">${spool ? `${esc(spool.name)} · ${esc(materialLabel(spool))}` : esc(g.hex)}${posts ? ` · ${esc(posts)}` : ''}</span></span></li>`;
        };
        const frame = n.frame !== 'none' && n.frame_outer ? `<li class="flex items-center gap-3"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-ink text-xs font-bold text-white">0</span><span class="text-sm"><span class="block font-medium text-ink">${esc(t('edit.art.part.frame'))}</span><span class="block text-muted">${esc(t('edit.art.guide.frame', { w: nf.format(n.frame_outer[0]), h: nf.format(n.frame_outer[1]), d: nf.format(n.frame_depth ?? 0) }))}${params().led ? ` · ${esc(t('edit.art.guide.led'))}` : ''}</span></span></li>` : '';
        box.innerHTML = `<div class="font-semibold text-ink">${esc(t('edit.art.guide'))}</div><p class="mt-1 text-muted">${esc(t('edit.art.guide.hint'))}</p><ol class="mt-3 space-y-2">${frame}${n.guide.map(plate).join('')}</ol><p class="mt-2 text-xs text-muted">${esc(t('edit.art.guide.glue'))}</p>`;
        box.classList.remove('hidden');
    };

    const renderDims = (): void => {
        const n = notes(); const el = $('art-dims');
        const rows: [string, string][] = [];
        if (n.mode === 'layered' && n.depth) rows.push([t('edit.art.depth'), `${nf.format(n.depth)} mm`]);
        if (n.frame_outer) rows.push([t('edit.art.frame.size'), `${n.frame_outer.map((v) => nf.format(v)).join(' × ')} × ${nf.format(n.frame_depth ?? 0)} mm`]);
        el.innerHTML = rows.map(([k, v]) => `<div class="flex justify-between gap-3"><dt class="text-muted">${esc(k)}</dt><dd class="font-medium text-ink">${esc(v)}</dd></div>`).join('');
        el.classList.toggle('hidden', !rows.length); el.classList.toggle('grid', rows.length > 0);
    };

    const renderWarnings = (): void => {
        const by: Record<string, string[]> = {};
        const add = (section: string, line: string): void => { (by[section] = by[section] ?? []).push(line); };
        const n = notes();
        n.warnings.forEach((w) => add(cfg.warnAt[w] ?? 'size', t(`edit.warn.${w}`, { n: n.thin_pct ?? 0, mm2: nf.format(n.merged_mm2 ?? 0) })));
        if (Object.values(partColors).some((code) => { const c = colorOf(code); return c !== null && !c.in_stock; })) add('colors', stage.t('toolpage.color.out'));
        stage.warnings(by);
    };

    const renderStatus = (): void => {
        if (!lastMeta) { stage.status(null); return; }
        const n = notes(); const o = n.outer.length ? n.outer : [lastMeta.bbox.x, lastMeta.bbox.y, lastMeta.bbox.z];
        stage.status({ bbox: { x: o[0], y: o[1], z: o[2] }, pieces: n.parts.length, colors: n.filaments, each: n.each.length > 1 ? n.each : undefined });
    };

    const renderPrice = (): void => {
        if (!lastMeta) return;
        // for the material and the number of pieces of the page's last step; the stage counts again when they change
        stage.price(cfg.config, { volume_mm3: lastMeta.volume_mm3, area_mm2: lastMeta.area_mm2 }, { subText: (g, time, q) => t('param.estimate', { g: nf.format(g), t: time, q }) });
    };

    const refresh = async (): Promise<void> => {
        if (!artwork) { valid = false; stage.go({ disabled: true }); stage.downloads([]); return; }
        const mine = ++seq;
        stage.busy(true);
        try {
            const res = await post(cfg.preview, { params: params(), view: 'use' });
            if (mine !== seq) return;
            if (!res.ok) { valid = false; showError(await errorOf(res)); return; }
            lastMeta = await modelMeta<Meta>(res);
            viewer.setSpreadAxis(mode() === 'layered' ? 'z' : null);
            stage.show(new STLLoader().parse(await res.arrayBuffer()), { kind: 'filament_art', pieces: lastMeta?.parts ?? null });
            valid = true; showError(null);
            paintParts(); renderColors(); renderGuide(); renderDims(); renderWarnings(); renderStatus(); renderPrice(); renderDownloads();
            stage.go({ disabled: false, run: () => save() });
        } catch {
            if (mine === seq) { valid = false; showError(t('param.failed')); }
        } finally {
            if (mine === seq) stage.busy(false);
        }
    };
    const soon = (ms = 350): void => { window.clearTimeout(timer); timer = window.setTimeout(() => { void refresh().then(commit); }, ms); };

    /** Saves the design as a model file and goes on to the calculator (the exact price, the farm, the project for a slicer). */
    const save = async (slicer: string | null = null): Promise<void> => {
        if (!valid) return;
        stage.busy(true); stage.error(null);
        try {
            const res = await post(cfg.create, { params: params() });
            if (!res.ok) { showError(await errorOf(res)); return; }
            const file = (await res.json()).file as FileInfo;
            location.href = stage.ordered(`${cfg.home}?open=${file.uuid}${slicer ? `&download=1&slicer=${slicer}` : ''}`);
        } catch {
            showError(t('param.failed'));
        } finally {
            stage.busy(false);
        }
    };
    const downloadWhole = async (): Promise<void> => {
        const res = await post(cfg.preview, { params: params(), view: 'print' });
        if (!res.ok) { showError(await errorOf(res)); return; }
        stage.save(await res.blob(), 'filament-art.stl');
    };
    const downloadZip = async (): Promise<void> => {
        const res = await post(cfg.zip, { params: params() });
        if (!res.ok) { showError(await errorOf(res)); return; }
        stage.save(await res.blob(), 'filament-art.zip');
    };
    /** The project with colours first, then every plate as its own STL in one archive, then the whole set laid out. */
    const renderDownloads = (): void => {
        const items: MenuItem[] = [
            { label: stage.t('toolpage.download.orca'), hint: stage.t('toolpage.download.project.hint'), icon: 'file-box', run: () => save('orca') },
            { label: stage.t('toolpage.download.prusa'), hint: stage.t('toolpage.download.project.hint'), icon: 'file-box', run: () => save('prusaslicer') },
            { label: t('edit.download.plates'), icon: 'package', run: downloadZip },
            { label: stage.t('toolpage.download.whole'), run: downloadWhole },
        ];
        stage.downloads(valid ? items : []);
    };

    // ── the picture: upload, the library, own pictures ──────────────────────────────────────────────
    const adjustThumb = (): void => {
        const img = document.querySelector<HTMLImageElement>('#art-artwork-thumb img'); if (!img) return;
        const v = (k: string): number => Number(form.querySelector<HTMLInputElement>(`[data-param="${k}"]`)?.value ?? 100);
        img.style.filter = `contrast(${v('contrast')}%) brightness(${v('brightness')}%) saturate(${v('saturation')}%)`;
    };
    const setArtwork = (picked: PickedArtwork | null): void => {
        if ((picked?.ref ?? null) !== artwork) { merge = []; order = []; Object.keys(partColors).forEach((p) => { if (p !== 'frame') delete partColors[p]; }); }
        artwork = picked?.ref ?? null; artworkName = picked?.name ?? '';
        const lib = /^lib:(.+)$/.exec(picked?.ref ?? ''); const own = /^[0-9a-f-]{36}$/.test(picked?.ref ?? '');
        const url = picked ? (lib ? `${stage.cfg.artwork.library}/${lib[1]}.svg` : own ? `${stage.cfg.artwork.file}/${picked.ref}` : picked.url) : null;
        const state = $('art-artwork-state'); const thumb = $('art-artwork-thumb'); const open = $('art-artwork-open');
        const label = open.lastChild; if (label) label.textContent = stage.t(picked ? 'toolpage.artwork.change' : 'toolpage.artwork.choose');
        thumb.innerHTML = url ? `<img src="${url.replace(/"/g, '&quot;')}" alt="" class="max-h-full max-w-full object-contain">` : '';
        thumb.classList.toggle('hidden', !url); thumb.classList.toggle('flex', !!url);
        adjustThumb();
        state.textContent = picked ? stage.t('toolpage.artwork.used', { name: picked.name }) : '';
    };
    $('art-artwork-open').onclick = async () => { const picked = await pickArtwork(); if (picked) { setArtwork(picked); void refresh().then(commit); } };

    // ── the form ─────────────────────────────────────────────────────────────────────────────────────
    const syncRange = (num: HTMLInputElement): void => { const r = form.querySelector<HTMLInputElement>(`[data-range="${num.dataset.param}"]`); if (r) r.value = num.value; };
    form.querySelectorAll<HTMLInputElement>('[data-range]').forEach((r) => r.addEventListener('input', () => { const num = form.querySelector<HTMLInputElement>(`[data-param="${r.dataset.range}"]`)!; num.value = r.value; adjustThumb(); soon(); }));
    form.querySelectorAll<HTMLInputElement>('[data-param]').forEach((i) => i.addEventListener('input', () => { syncRange(i); adjustThumb(); soon(); }));
    form.querySelectorAll<HTMLInputElement>('[data-flag], [data-choice]').forEach((i) => i.addEventListener('change', () => { applyWhen(); soon(100); }));

    const applyValues = (set: Record<string, unknown>): void => {
        Object.entries(set).forEach(([k, v]) => {
            const num = form.querySelector<HTMLInputElement>(`[data-param="${k}"]`); if (num) { num.value = String(v); syncRange(num); }
            const flag = form.querySelector<HTMLInputElement>(`[data-flag="${k}"]`); if (flag) flag.checked = Boolean(v);
            const choice = form.querySelector<HTMLInputElement>(`[data-choice="${k}"][value="${String(v)}"]`); if (choice) choice.checked = true;
        });
        if ('artwork' in set) setArtwork(typeof set.artwork === 'string' && set.artwork ? { ref: set.artwork, name: (set._artwork_name as string) ?? artworkName, url: null } : null);
        if ('merge' in set) merge = Array.isArray(set.merge) ? (set.merge as number[][]).map((pair) => [Number(pair[0]), Number(pair[1])]) : [];
        if ('order' in set) order = Array.isArray(set.order) ? (set.order as number[]).map(Number) : [];
        if (set.part_colors && typeof set.part_colors === 'object') {
            Object.keys(partColors).forEach((p) => delete partColors[p]);
            Object.entries(set.part_colors as Record<string, unknown>).forEach(([part, c]) => { const code = typeof c === 'string' ? c : (c as { code?: string } | null)?.code; if (code) partColors[part] = spoolCode(code); });
        }
        applyWhen();
    };
    commit = stage.track({ read: () => ({ ...params(), _artwork_name: artworkName }), write: (s) => { applyValues(s); void refresh(); } }, !cfg.from);
    applyWhen();

    // a stored design opens again with its settings; a new visitor starts with a picture of the library
    (async () => {
        if (cfg.from) {
            try {
                const file = await stage.fetchFile(cfg.from);
                if (file.tool?.params) { applyValues(file.tool.params); void refresh(); return; }
            } catch { /* a design that is gone: start fresh */ }
        }
        if (cfg.sample) { setArtwork({ ref: cfg.sample, name: '', url: null }); void refresh(); }
    })();
}
