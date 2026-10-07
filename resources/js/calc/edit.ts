/**
 * Editing a model file, as a module of the tool page: upload a model (or arrive from the calculator with ?from=uuid),
 * see it, set the tool, let the server make the new file in its queue, and get the result on the stage: every piece
 * in its own colour, the sizes, the price, the downloads and what the tool did. The tools of this page: the split
 * (bed, planes, joints), the hollow (wall, drain holes), life size (height, hollow, split); scale shares it.
 */
import type { BufferGeometry } from 'three';
import type { Piece } from './viewer';
import type { Stage, MenuItem, PriceConfig } from './tool_page';
import { loadGeometryFromUrl } from './loaders';
import { estimate, price, range } from './rough';
import { price as priceText } from '../site/money';
import { FileInfo } from './api';

interface Cfg { op: string; upload: string; files: string; parts: string; home: string; from: string | null; formats: string[]; maxMb: number; beds: Record<string, number[] | null>; margin: number; farmMargin: number; config: PriceConfig & { bed_mm: { x: number; y: number; z: number } }; i18n: Record<string, string> }
interface ColorInfo { key?: number; part?: string; name: string; hex: string; extruder: number | null; sources?: string[]; triangles: number; share: number; kind?: string; bodies?: number }
interface Analysis { bbox: { x: number; y: number; z: number }; fits: boolean; planes: Record<'x' | 'y' | 'z', number[]> | null; pieces: number | null; too_many: boolean; repaired: boolean; factor?: number; scaled?: number[]; hollow?: boolean; wall?: number; colors?: ColorInfo[]; has_colors?: boolean; not_3mf?: boolean; split_triangles?: number; girth?: number; target?: number; already_hollow?: boolean }
interface MapPiece { n: number; part: string; cell: number[]; lo: number[]; hi: number[]; volume_mm3: number; down: [string, number] | null; number_at: number[] | null }
interface Hollow { wall: number; pitch: number; cavity_mm3: number; saved_g: number; drains: number; drain_at: number[][]; drain_d: number }
interface Report { op: string; stage?: string; planes?: Record<string, number[]>; joint?: string; pins?: number; keys?: number; pieces?: number; cells?: number[]; model?: number[]; warnings?: string[]; map?: MapPiece[]; too_big?: string[]; repaired?: boolean; parts?: string[]; pieces_tris?: Piece[]; volume_in_mm3?: number; hollow?: Hollow | null; factor?: number; scaled?: number[]; grams?: number; rows?: number; cols?: number; lock?: string; knob?: number; clearance?: number; frame?: { rim: number; base: number; height: number } | null; cavity?: string; cavity_mm?: Record<string, number>; min_wall?: number; floor?: number; grow_to?: number; bottle?: number[]; neck?: { d: number; h: number }; cut_mm?: number; wall?: number; cork?: { h: number }; label?: { w: number; h: number; text: string }; axis?: string; segments?: number; ball_d?: number; joined?: number; cuts?: number[]; colors?: ColorInfo[]; depth?: number; shells?: number; inlays?: number; bases?: number; split_triangles?: number; dish?: number[]; pocket?: number[]; footprint?: number[]; drain?: string; drains?: number; girth_inner?: number; target?: number; hollowed?: boolean; already_hollow?: boolean; opened?: boolean; windows?: unknown[]; straps?: number; groove?: number[]; slider?: number[]; play?: number; detents?: number; knob?: boolean }
type Edited = FileInfo & { edit?: Report | null; tool?: { kind: string; params: Record<string, unknown> & { each?: number[][]; parts?: string[] }; url: string } | null };

// the pieces in calm colours that read as different, the pins and keys grey
const PIECES = ['#5B7FB5', '#C98F5A', '#5A9A6C', '#8E6FB0', '#C45A5A', '#B8A14A', '#4FA3A8', '#A86A8E'];

export function bootEdit(stage: Stage): void {
    const cfg = (window as unknown as { MP_EDIT?: Cfg }).MP_EDIT;
    const input = document.getElementById('edit-file') as HTMLInputElement | null;
    const form = document.getElementById('edit-form') as HTMLFormElement | null;
    if (!cfg || !input || !form) return;
    const t = (k: string, r: Record<string, string | number> = {}) => Object.entries(r).reduce((s, [a, b]) => s.split(`:${a}`).join(String(b)), cfg.i18n[`edit.${cfg.op}.${k}`] ?? cfg.i18n[k] ?? stage.t(k));
    const $ = <T extends HTMLElement>(id: string) => document.getElementById(id) as T;
    const nf = stage.nf; const viewer = stage.viewer;
    const esc = (s: string): string => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '"': '&quot;', '>': '&gt;' }[c] as string));
    // a colour of a 3MF: the material's name, else the filament's number, else the hex
    const colorName = (c: ColorInfo): string => c.name || (c.extruder ? t('filament', { n: c.extruder }) : c.hex);
    const swatch = (c: ColorInfo): string => `<span class="inline-flex items-center gap-1 rounded-full border border-line bg-white px-2 py-0.5 text-xs text-ink"><span class="inline-block h-3 w-3 rounded-full border border-line" style="background:${esc(c.hex)}"></span>${esc(colorName(c))} · ${nf.format(c.share)} %</span>`;
    const status = $('edit-status'); const go = $('edit-go') as HTMLButtonElement; const source = $('edit-source');
    const result = $('edit-result'); const wait = $('edit-wait'); const analysisEl = $('edit-analysis');
    const withPlanes = cfg.op === 'split';
    const withAnalysis = cfg.op === 'split' || cfg.op === 'life_size' || cfg.op === 'colors' || cfg.op === 'wearable';
    let model: FileInfo | null = null; let modelGeom: BufferGeometry | null = null;
    let made: Edited | null = null;
    let analysis: Analysis | null = null;
    let planes: Record<'x' | 'y' | 'z', number[]> | null = null;      // the planes as the visitor has them (null: as the tool plans them)
    let asked = 0; let polling = 0; let timer = 0;
    const say = (k: string | null, p: Record<string, string | number> = {}) => { status.textContent = k ? t(k, p) : ''; status.classList.toggle('hidden', !k); };
    const headers = { Accept: 'application/json' };
    let commit: () => void = () => undefined;

    // the windows of a wearable: four rows of the page as one JSON list in a hidden text field
    const windowRows = (): HTMLElement[] => Array.from(form.querySelectorAll<HTMLElement>('[data-window]'));
    const syncWindows = (): void => {
        const field = form.querySelector<HTMLInputElement>('[data-text="windows"]');
        if (!field) return;
        const list = windowRows().filter((row) => row.querySelector<HTMLInputElement>('[data-win="on"]')?.checked).map((row) => {
            const v = (k: string) => row.querySelector<HTMLInputElement | HTMLSelectElement>(`[data-win="${k}"]`)?.value ?? '';
            return { side: v('side'), shape: v('shape'), w: Number(v('w')), h: Number(v('h')), dx: Number(v('dx')), dy: Number(v('dy')) };
        });
        field.value = JSON.stringify(list);
    };
    const applyWindows = (json: string): void => {
        let list: Record<string, unknown>[] = [];
        try { list = JSON.parse(json || '[]') as Record<string, unknown>[]; } catch { list = []; }
        windowRows().forEach((row, i) => {
            const w = list[i];
            const on = row.querySelector<HTMLInputElement>('[data-win="on"]'); if (on) on.checked = !!w;
            if (!w) return;
            (['side', 'shape', 'w', 'h', 'dx', 'dy'] as const).forEach((k) => { const el = row.querySelector<HTMLInputElement | HTMLSelectElement>(`[data-win="${k}"]`); if (el && w[k] !== undefined) el.value = String(w[k]); });
        });
    };
    const settings = (): Record<string, unknown> => {
        syncWindows();
        const p: Record<string, unknown> = {};
        form.querySelectorAll<HTMLInputElement>('[data-param]').forEach((i) => { p[i.dataset.param!] = Number(i.value); });
        form.querySelectorAll<HTMLInputElement>('[data-flag]').forEach((i) => { p[i.dataset.flag!] = i.checked; });
        form.querySelectorAll<HTMLInputElement>('[data-choice]:checked').forEach((i) => { p[i.dataset.choice!] = i.value; });
        form.querySelectorAll<HTMLInputElement>('[data-text]').forEach((i) => { p[i.dataset.text!] = i.value; });
        if (planes) p.planes = planes;
        return p;
    };
    const applyWhen = (): void => {
        const p = settings();
        form.querySelectorAll<HTMLElement>('[data-when]').forEach((el) => {
            const [key, list] = el.dataset.when!.split('=');
            const now = typeof p[key] === 'boolean' ? (p[key] ? 'on' : 'off') : String(p[key] ?? '');
            el.classList.toggle('hidden', !list.split(',').includes(now));
        });
        const hint = document.getElementById('edit-joint-hint');
        if (hint) hint.textContent = cfg.i18n[`edit.${cfg.op}.joint.${p.joint}`] ?? '';
    };
    const syncRange = (num: HTMLInputElement): void => { const r = form.querySelector<HTMLInputElement>(`[data-range="${num.dataset.param}"]`); if (r) r.value = num.value; };

    // ── the planes: sliders for each one the tool plans, the viewer shows them on the model ─────────
    const allPlanes = (): { axis: 'x' | 'y' | 'z'; at: number }[] => {
        const now = planes ?? analysis?.planes; if (!now) return [];
        return (['x', 'y', 'z'] as const).flatMap((axis) => now[axis].map((at) => ({ axis, at })));
    };
    const showPlanes = (): void => {
        if (!withPlanes) return;
        const list = $('edit-planes'); const box = $('edit-planes-box'); const reset = $('edit-planes-reset');
        const now = planes ?? analysis?.planes ?? null;
        list.innerHTML = '';
        if (!now || !model?.bbox || made) { box.classList.add('hidden'); viewer.setPlanes(null); return; }
        const all: { axis: 'x' | 'y' | 'z'; at: number }[] = [];
        (['x', 'y', 'z'] as const).forEach((axis) => {
            const size = model!.bbox![axis];
            now[axis].forEach((at, i) => {
                all.push({ axis, at });
                const row = document.createElement('div');
                row.className = 'tool-num';
                row.innerHTML = `<label class="text-sm font-medium text-ink">${esc(t(`plane.${axis}`, { n: i + 1 }))}</label>
                    <div class="mt-1 flex items-center gap-3"><input type="range" min="2" max="${(size - 2).toFixed(1)}" step="0.5" value="${at}" class="min-w-0 flex-1 accent-ink" aria-label="${esc(t(`plane.${axis}`, { n: i + 1 }))}"><span class="num w-20 text-right text-sm text-muted">${nf.format(at)} mm</span></div>`;
                const rangeEl = row.querySelector('input')!; const out = row.querySelector('span')!;
                rangeEl.addEventListener('input', () => {
                    planes = planes ?? JSON.parse(JSON.stringify(now));
                    planes![axis][i] = Number(rangeEl.value);
                    out.textContent = `${nf.format(Number(rangeEl.value))} mm`;
                    viewer.setPlanes(allPlanes());
                    reset.classList.remove('hidden');
                    commit();
                });
                list.appendChild(row);
            });
        });
        box.classList.toggle('hidden', all.length === 0);
        reset.classList.toggle('hidden', !planes);
        viewer.setPlanes(all);
    };
    const resetBtn = document.getElementById('edit-planes-reset');
    if (resetBtn) resetBtn.onclick = () => { planes = null; void analyse(); commit(); };

    /** Asks what the tool would do with this model and these settings: the planes, how many pieces, the scaled size. */
    const analyse = async (): Promise<void> => {
        if (!model || !withAnalysis) return;
        const mine = ++asked;
        analysisEl.classList.remove('hidden'); analysisEl.textContent = t('analysing');
        try {
            const res = await fetch(`${cfg.files}/${model.uuid}/edit/analysis`, { method: 'POST', credentials: 'same-origin', headers: { ...headers, 'Content-Type': 'application/json' }, body: JSON.stringify({ op: cfg.op, ...settings() }) });
            if (mine !== asked) return;
            if (res.status === 503) { say('unavailable'); return; }
            if (!res.ok) throw new Error('analysis');
            analysis = (await res.json()).analysis as Analysis;
            analysisEl.classList.remove('text-warn');
            if (analysis.too_many) { analysisEl.textContent = t('too_many'); analysisEl.classList.add('text-warn'); go.disabled = true; showPlanes(); return; }
            const cuts = analysis.planes ? Object.values(analysis.planes).reduce((n, l) => n + l.length, 0) : 0;
            if (cfg.op === 'colors') {
                // the colours the file holds, with their shares; one colour is nothing to split
                const list = analysis.colors ?? [];
                const ok = list.length > 1;
                analysisEl.innerHTML = ok ? `${esc(t('found', { n: list.length }))}<span class="mt-1 flex flex-wrap gap-1.5">${list.map(swatch).join('')}</span>${analysis.split_triangles ? `<span class="mt-1 block text-muted">${esc(t('majority', { n: analysis.split_triangles }))}</span>` : ''}` : esc(t(analysis.not_3mf ? 'not_3mf' : 'none'));
                analysisEl.classList.toggle('text-warn', !ok);
                go.disabled = !ok;
                return;
            }
            if (cfg.op === 'life_size' || cfg.op === 'wearable') {
                const s = analysis.scaled ?? [0, 0, 0];
                const dims = { x: nf.format(s[0]), y: nf.format(s[1]), z: nf.format(s[2]), f: nf.format(analysis.factor ?? 1), g: nf.format(analysis.girth ?? 0), t: nf.format(analysis.target ?? 0) };
                analysisEl.textContent = analysis.fits ? t('fits', dims) : t('plan', { ...dims, h: analysis.hollow ? t('plan.hollow', { w: nf.format(analysis.wall ?? 0) }) : t('plan.solid'), c: cuts, p: analysis.pieces ?? 0 });
                go.disabled = false;
            } else {
                analysisEl.textContent = analysis.fits ? t('fits') : t('plan', { c: cuts, p: analysis.pieces ?? 0 });
                go.disabled = analysis.fits;
            }
            showPlanes();
        } catch {
            if (mine !== asked) return;
            analysisEl.classList.add('hidden');
        }
    };
    const analyseSoon = (): void => { window.clearTimeout(timer); timer = window.setTimeout(() => { void analyse(); }, 300); };

    const haveModel = async (info: FileInfo): Promise<void> => {
        model = info; made = null; analysis = null; planes = null;
        source.textContent = `${info.name} · ${info.bbox ? [info.bbox.x, info.bbox.y, info.bbox.z].map((v) => Math.round(v)).join(' × ') + ' mm' : ''}`;
        source.classList.remove('hidden');
        say(null);
        result.classList.add('hidden'); wait.classList.remove('hidden'); $('edit-map').classList.add('hidden');
        stage.go({ disabled: true, href: null }); stage.downloads([]); stage.warnings({});
        stage.status({ bbox: info.bbox });
        if (info.stl_url) {
            try {
                modelGeom = await loadGeometryFromUrl(info.stl_url);
                stage.show(modelGeom.clone(), { kind: null });
            } catch { modelGeom = null; }
        }
        go.disabled = false;
        await analyse();
        stage.reveal('settings');
    };

    const upload = async (file: File): Promise<void> => {
        const ext = (file.name.split('.').pop() ?? '').toLowerCase();
        go.disabled = true; model = null;
        if (!cfg.formats.includes(ext)) { say('bad_format', { f: cfg.formats.join(', ').toUpperCase() }); return; }
        if (file.size > cfg.maxMb * 1024 * 1024) { say('too_big', { max: cfg.maxMb }); return; }
        say('uploading');
        stage.busy(true);
        try {
            const fd = new FormData(); fd.append('file', file);
            const up = await fetch(cfg.upload, { method: 'POST', credentials: 'same-origin', headers, body: fd });
            if (!up.ok) throw new Error('upload');
            say('processing');
            const info = await stage.untilReady((await up.json()).file);
            if (info.status !== 'ready') { say('model_failed'); return; }
            await haveModel(info);
        } catch {
            say('failed');
        } finally {
            stage.busy(false);
        }
    };

    /** Waits for the queue, saying which phase the tool is in (loading, repairing, cutting, joints, layout…). */
    const untilMade = async (info: Edited): Promise<Edited> => {
        const mine = ++polling;
        for (let i = 0; i < 400 && info.status !== 'ready' && info.status !== 'failed'; i++) {
            const phase = info.edit?.stage ?? 'queued';
            say(cfg.i18n[`edit.${cfg.op}.stage.${phase}`] ? `stage.${phase}` : 'working');
            await new Promise((r) => setTimeout(r, 1500));
            if (mine !== polling) return info;
            info = (await stage.fetchFile(info.uuid)) as Edited;
        }
        return info;
    };

    const partLabel = (part: string): string => {
        if (part === 'pins') return t('part.pins');
        if (part === 'keys') return t('part.keys');
        if (part === 'body') return t('part.body');
        if (part === 'frame') return t('part.frame');
        if (part === 'cork') return t('part.cork');
        if (part.startsWith('segment_')) return t('part.segment', { n: part.slice(8) });
        if (part.startsWith('color_')) { const c = made?.edit?.colors?.find((x) => x.part === part); return c ? colorName(c) : t('part.color', { n: part.slice(6) }); }
        if (part === 'label') return t('part.label');
        if (part.startsWith('piece_')) return t('part.piece', { n: part.slice(6) });
        return part;
    };

    /** The map of a split: for every level of cuts, the cells seen from above with the numbers of the pieces in them. */
    const renderMap = (r: Report): void => {
        const box = $('edit-map');
        if (!r.map?.length || !r.cells) { box.classList.add('hidden'); return; }
        const [kx, ky, kz] = r.cells;
        const levels: string[] = [];
        for (let z = 0; z < kz; z++) {
            let rows = '';
            for (let y = ky - 1; y >= 0; y--) {
                for (let x = 0; x < kx; x++) {
                    const pc = r.map.find((m) => m.cell[0] === x && m.cell[1] === y && m.cell[2] === z);
                    rows += pc ? `<span class="flex aspect-square min-h-9 items-center justify-center rounded-md text-sm font-bold text-white" style="background:${PIECES[(pc.n - 1) % PIECES.length]}">${pc.n}</span>` : '<span class="flex aspect-square min-h-9 items-center justify-center rounded-md border border-dashed border-slate-300"></span>';
                }
            }
            levels.push(`<div><div class="mb-1 text-xs text-muted">${kz > 1 ? esc(t('map.level', { n: z + 1 })) : ''}</div><div class="grid gap-1" style="grid-template-columns:repeat(${kx},minmax(0,2.5rem))">${rows}</div></div>`);
        }
        const pieces = cfg.op === 'puzzle' ? '' : r.map.map((m) => `<li><span class="inline-block h-3 w-3 rounded-sm align-middle" style="background:${PIECES[(m.n - 1) % PIECES.length]}"></span> <span class="font-medium text-ink">${esc(t('piece', { n: m.n }))}</span> <span class="text-muted">${esc(t('piece.size', { x: nf.format(m.hi[0] - m.lo[0]), y: nf.format(m.hi[1] - m.lo[1]), z: nf.format(m.hi[2] - m.lo[2]) }))}${m.down ? ` · ${esc(t(`piece.down.${m.down[0]}`))}` : ''}</span></li>`).join('');
        box.innerHTML = `<div class="font-semibold text-ink">${esc(t('map'))}</div><div class="mt-2 flex flex-wrap gap-4">${levels.join('')}</div><ul class="mt-3 space-y-1">${pieces}</ul><p class="mt-2 text-xs text-muted">${esc(t('glue'))}</p>`;
        box.classList.remove('hidden');
    };

    /** What a volume of PLA costs, the way the price card counts it (the lower end of the range). */
    const priceOf = (volume: number): number => {
        const c = stage.cfg.price; if (!c || !(volume > 0)) return 0;
        const code = c.default_material ?? c.materials[0]?.code ?? 'PLA';
        const density = c.materials.find((m) => m.code === code)?.density ?? 1.24;
        const est = estimate(c.rough, density, { volume_mm3: volume, area_mm2: 0 }, { material: code, quality: 'standard', infill: 15, supports: false, scale: 1, quantity: 1 });
        const totals = c.orientation_profiles.map((p) => price(c.round_to, p, est.grams, est.minutes, 1).total);
        return range(c.rough, c.round_to, totals, true)[0];
    };

    const showMade = async (file: Edited): Promise<void> => {
        made = file;
        const r = file.edit ?? { op: cfg.op };
        const lines: string[] = [];
        const cuts = Object.values(r.planes ?? {}).reduce((n, l) => n + l.length, 0);
        if (cfg.op === 'life_size' && r.scaled) {
            lines.push(t('report', { f: nf.format(r.factor ?? 1), x: nf.format(r.scaled[0]), y: nf.format(r.scaled[1]), z: nf.format(r.scaled[2]), g: nf.format(r.grams ?? 0) }));
            lines.push(r.hollow ? t('report.hollow', { w: nf.format(r.hollow.wall), s: nf.format(r.hollow.saved_g) }) : t('report.solid'));
        }
        if (cfg.op === 'wearable' && r.scaled) {
            lines.push(t('report', { f: nf.format(r.factor ?? 1), x: nf.format(r.scaled[0]), y: nf.format(r.scaled[1]), z: nf.format(r.scaled[2]), g: nf.format(r.girth_inner ?? 0), t: nf.format(r.target ?? 0), w: nf.format(r.grams ?? 0) }));
            lines.push(r.hollowed ? t('report.hollow', { w: nf.format(r.wall ?? 0) }) : r.already_hollow ? t('report.already') : t('report.solid'));
            lines.push(t('report.cuts', { n: (r.windows ?? []).length, s: r.straps ?? 0 }));
        }
        if (cfg.op === 'hollow' && r.hollow) {
            const saved = priceOf(r.volume_in_mm3 ?? 0) - priceOf((r.volume_in_mm3 ?? 0) - r.hollow.cavity_mm3);
            lines.push(t('report', { w: nf.format(r.hollow.wall), c: nf.format(Math.round(r.hollow.cavity_mm3 / 1000)), g: nf.format(r.hollow.saved_g), p: saved > 0 ? priceText(saved) : '—' }));
            lines.push(r.hollow.drains ? t('report.drains', { d: nf.format(r.hollow.drain_d), n: r.hollow.drains }) : t('report.none'));
            if ((r.warnings ?? []).includes('coarse_grid')) lines.push(t('report.coarse', { p: nf.format(r.hollow.pitch) }));
        }
        if (cfg.op === 'flexi_cut' && r.segments) {
            lines.push(t('report', { n: r.segments, a: String(r.axis ?? '').toUpperCase(), d: nf.format(r.ball_d ?? 0), p: nf.format(r.clearance ?? 0) }));
            lines.push((r.joined ?? 0) > 0 ? t('report.joints', { j: r.joined ?? 0, c: (r.segments ?? 1) - 1 }) : t('report.none'));
        }
        if (cfg.op === 'colors' && r.colors) {
            lines.push(t('report', { n: r.colors.length, d: nf.format(r.depth ?? 0) }));
            lines.push(t('report.parts', { s: r.shells ?? 0, i: r.inlays ?? 0, b: r.bases ?? 0 }));
            if (r.split_triangles) lines.push(t('majority', { n: r.split_triangles }));
        }
        if (cfg.op === 'soap' && r.dish) {
            lines.push(t('report', { x: nf.format(r.dish[0]), y: nf.format(r.dish[1]), z: nf.format(r.dish[2]), w: nf.format(r.pocket?.[0] ?? 0), d: nf.format(r.pocket?.[1] ?? 0), p: nf.format(r.clearance ?? 0) }));
            lines.push(t(`report.drain.${r.drain ?? 'none'}`, { n: r.drains ?? 0 }));
        }
        if (cfg.op === 'slider' && r.groove) {
            lines.push(t('report', { l: nf.format(r.groove[0]), w: nf.format(r.groove[1]), d: nf.format(r.groove[2]), s: nf.format(r.slider?.[0] ?? 0), p: nf.format(r.play ?? 0), a: String(r.axis ?? '').toUpperCase() }));
            lines.push(t(r.detents ? 'report.detents' : 'report.none', { n: r.detents ?? 0 }));
        }
        if (cfg.op === 'potion' && r.bottle) {
            lines.push(t('report', { x: nf.format(r.bottle[0]), y: nf.format(r.bottle[1]), z: nf.format(r.bottle[2]), d: nf.format(r.neck?.d ?? 0), h: nf.format(r.neck?.h ?? 0), w: nf.format(r.wall ?? 0) }));
            lines.push(r.hollow && r.hollow.cavity_mm3 > 0 ? t('report.hollow', { c: nf.format(Math.round(r.hollow.cavity_mm3 / 1000)) }) : t('report.solid'));
            lines.push(t('report.cork', { h: nf.format(r.cork?.h ?? 0) }));
            if (r.label) lines.push(t('report.label', { w: nf.format(r.label.w), h: nf.format(r.label.h), s: r.label.text }));
        }
        if (cfg.op === 'holder' && r.cavity_mm) {
            const c = r.cavity_mm;
            const size = c.w ? `${nf.format(c.w)} × ${nf.format(c.l)} × ${nf.format(c.depth)}` : `Ø ${nf.format(c.d2)}${Math.abs(c.d - c.d2) > 0.01 ? `→${nf.format(c.d)}` : ''} × ${nf.format(c.depth)}`;
            lines.push(t('report', { s: size, p: nf.format(r.clearance ?? 0), f: nf.format(r.factor ?? 1), x: nf.format(r.scaled?.[0] ?? 0), y: nf.format(r.scaled?.[1] ?? 0), z: nf.format(r.scaled?.[2] ?? 0) }));
            lines.push((r.min_wall ?? 0) >= 2 ? t('report.wall', { w: nf.format(r.min_wall ?? 0) }) : t('report.thin', { w: nf.format(r.min_wall ?? 0), h: nf.format(r.grow_to ?? 0) }));
            lines.push(t('report.floor', { f: nf.format(r.floor ?? 0) }));
        }
        if (cfg.op === 'puzzle') {
            lines.push(t('report', { c: r.cols ?? 0, r: r.rows ?? 0, n: r.pieces ?? 0, l: r.lock === 'pins' ? t('report.pins', { n: r.pins ?? 0 }) : t('report.tabs', { k: r.knob ?? 0, p: nf.format(r.clearance ?? 0) }) }));
            if (r.frame) lines.push(t('report.frame', { w: nf.format(r.frame.rim), b: nf.format(r.frame.base), h: nf.format(r.frame.height) }));
        }
        if (cfg.op === 'split' || ((cfg.op === 'life_size' || cfg.op === 'wearable') && (r.pieces ?? 1) > 1)) {
            lines.push(t('report', { n: r.pieces ?? 0, c: cuts }));
            if (r.pins) lines.push(t('report.pins', { n: r.pins }));
            if (r.keys) lines.push(t('report.keys', { n: r.keys }));
            if (!r.pins && !r.keys) lines.push(t('report.none'));
        } else if (cfg.op === 'life_size' || cfg.op === 'wearable') lines.push(t('report.one'));
        if (r.repaired) lines.push(t('report.repaired'));
        $('edit-report').innerHTML = lines.map((s) => `<p class="mt-2 first:mt-0">${esc(s)}</p>`).join('');
        if (cfg.op === 'colors' && r.colors) $('edit-report').insertAdjacentHTML('beforeend', `<p class="mt-2 flex flex-wrap gap-1.5">${r.colors.map(swatch).join('')}</p>`);
        const by: Record<string, string[]> = {};
        (r.warnings ?? []).filter((w) => !(cfg.op === 'hollow' && w === 'coarse_grid') && !(cfg.op === 'holder' && w === 'wall_thin')).forEach((w) => { (by.result = by.result ?? []).push(t(`warn.${w}`, { n: (r.too_big ?? []).map(partLabel).join(', ') })); });
        stage.warnings(by);
        result.classList.remove('hidden'); wait.classList.add('hidden');
        if (file.stl_url) {
            const geom = await loadGeometryFromUrl(file.stl_url);
            viewer.setSpreadAxis(null);
            viewer.setPlanes(null);
            stage.show(geom, { kind: null, pieces: r.pieces_tris ?? null });
            viewer.getPieces().forEach((p, i) => viewer.setPieceColor(i, p.name.startsWith('piece_') ? PIECES[(Number(p.name.slice(6)) - 1) % PIECES.length] : p.name.startsWith('segment_') ? PIECES[(Number(p.name.slice(8)) - 1) % PIECES.length] : p.name === 'body' ? null : p.name === 'frame' ? '#4A4A4F' : '#8C9199'));
            // the parts of a coloured 3MF in the file's own colours
            if (cfg.op === 'colors' && r.colors) { const hexOf: Record<string, string> = {}; r.colors.forEach((c) => { if (c.part) hexOf[c.part] = c.hex; }); viewer.getPieces().forEach((p, i) => { if (hexOf[p.name]) viewer.setPieceColor(i, hexOf[p.name]); }); }
            // a hollow model looks the same outside: the x-ray shows what was done
            if ((cfg.op === 'hollow' || cfg.op === 'holder') && $('tool-xray').getAttribute('aria-pressed') !== 'true') $('tool-xray').click();
        }
        const each = (file.tool?.params?.each ?? []) as number[][];
        stage.status({ bbox: file.bbox, pieces: (file.parts ?? []).length || 1, each: each.length > 1 ? each : undefined });
        if (stage.cfg.price && file.volume_mm3) stage.price(stage.cfg.price, { volume_mm3: file.volume_mm3, area_mm2: file.area_mm2 }, { supports: file.hints?.supports });
        renderMap(r);
        const open = `${cfg.home}?open=${file.uuid}&download=1`;
        const items: MenuItem[] = [
            { label: stage.t('toolpage.download.orca'), hint: stage.t('toolpage.download.project.hint'), icon: 'file-box', href: `${open}&slicer=orca` },
            { label: stage.t('toolpage.download.prusa'), hint: stage.t('toolpage.download.project.hint'), icon: 'file-box', href: `${open}&slicer=prusaslicer` },
        ];
        if (file.stl_url) items.push({ label: stage.t('toolpage.download.whole'), href: file.stl_url, download: `${file.name.replace(/\.[^.]+$/, '')}.stl` });
        // a flexi prints in one go, assembled: its segments are not offered one by one
        if ((file.parts ?? []).length > 1 && cfg.op !== 'flexi_cut') {
            items.push({ heading: stage.t('toolpage.download.parts') });
            (file.parts ?? []).forEach((p) => items.push({ label: stage.t('toolpage.download.part', { name: partLabel(p) }), href: `${cfg.parts}/${file.uuid}/${p}.stl` }));
        }
        stage.downloads(items);
        stage.go({ href: `${cfg.home}?open=${file.uuid}`, disabled: false });
        stage.reveal('result');
    };

    const build = async (): Promise<void> => {
        if (!model) return;
        go.disabled = true; result.classList.add('hidden'); wait.classList.remove('hidden'); $('edit-map').classList.add('hidden');
        say('working');
        stage.busy(true);
        try {
            const res = await fetch(`${cfg.files}/${model.uuid}/edit`, { method: 'POST', credentials: 'same-origin', headers: { ...headers, 'Content-Type': 'application/json' }, body: JSON.stringify({ op: cfg.op, ...settings() }) });
            if (res.status === 503) { say('unavailable'); go.disabled = false; return; }
            const answer = await res.json();
            if (!res.ok || !answer.file) throw new Error(answer.reason ?? answer.error ?? 'edit');
            const file = await untilMade(answer.file as Edited);
            if (file.status !== 'ready') {
                const code = String(file.error ?? '').split(':')[0];
                say(cfg.i18n[`edit.${cfg.op}.error.${code}`] ? `error.${code}` : 'error.edit_failed');
                return;
            }
            say(null);
            await showMade(file);
        } catch (e) {
            const code = String((e as Error).message ?? '').split(':')[0];
            say(cfg.i18n[`edit.${cfg.op}.error.${code}`] ? `error.${code}` : 'failed');
        } finally {
            stage.busy(false);
        }
        go.disabled = false;
    };

    /** Back to the model and the settings: the planes on the model again, the result put away. */
    const again = (): void => {
        made = null;
        if (modelGeom) stage.show(modelGeom.clone(), { kind: null });
        if ($('tool-xray').getAttribute('aria-pressed') === 'true') $('tool-xray').click();
        stage.status(model?.bbox ? { bbox: model.bbox } : null);
        stage.downloads([]); stage.go({ disabled: true, href: null }); stage.warnings({});
        result.classList.add('hidden'); wait.classList.remove('hidden'); $('edit-map').classList.add('hidden');
        showPlanes();
        go.disabled = !model;
        stage.reveal('settings');
    };

    stage.dropZone($('edit-drop'), input, (f) => { void upload(f); });
    form.onsubmit = (e) => { e.preventDefault(); void build(); };
    $('edit-again').onclick = again;
    form.querySelectorAll<HTMLInputElement>('[data-range]').forEach((r) => r.addEventListener('input', () => { const num = form.querySelector<HTMLInputElement>(`[data-param="${r.dataset.range}"]`)!; num.value = r.value; planes = null; analyseSoon(); commit(); }));
    form.querySelectorAll<HTMLInputElement>('[data-param]').forEach((i) => i.addEventListener('input', () => { syncRange(i); planes = null; analyseSoon(); commit(); }));
    form.querySelectorAll<HTMLInputElement>('[data-flag], [data-choice]').forEach((el) => el.addEventListener('change', () => { applyWhen(); if (el.dataset.choice === 'bed') planes = null; analyseSoon(); commit(); }));
    form.querySelectorAll<HTMLInputElement>('[data-text]').forEach((el) => el.addEventListener('input', () => commit()));
    form.querySelectorAll<HTMLInputElement | HTMLSelectElement>('[data-win]').forEach((el) => el.addEventListener(el instanceof HTMLSelectElement || el.type === 'checkbox' ? 'change' : 'input', () => { syncWindows(); analyseSoon(); commit(); }));
    // a body part chosen: its usual girth goes into the field as a start
    form.querySelectorAll<HTMLInputElement>('[data-choice="measure"]').forEach((el) => el.addEventListener('change', () => { const num = form.querySelector<HTMLInputElement>('[data-param="circumference"]'); if (el.dataset.preset && num) { num.value = el.dataset.preset; syncRange(num); } }));
    commit = stage.track({ read: () => settings(), write: (s) => { Object.entries(s).forEach(([k, v]) => {
        const num = form.querySelector<HTMLInputElement>(`[data-param="${k}"]`); if (num) { num.value = String(v); syncRange(num); }
        const flag = form.querySelector<HTMLInputElement>(`[data-flag="${k}"]`); if (flag) flag.checked = Boolean(v);
        const text = form.querySelector<HTMLInputElement>(`[data-text="${k}"]`); if (text) { text.value = String(v ?? ''); if (k === 'windows') applyWindows(text.value); }
        const choice = form.querySelector<HTMLInputElement>(`[data-choice="${k}"][value="${String(v)}"]`); if (choice) choice.checked = true;
    }); planes = (s.planes as Record<'x' | 'y' | 'z', number[]>) ?? null; applyWhen(); showPlanes(); } }, !cfg.from);
    applyWhen();

    // came from the calculator: the model is already there (a result of this tool opens its source again)
    if (cfg.from) {
        say('processing');
        stage.fetchFile(cfg.from).then((f) => stage.untilReady(f)).then(async (info) => {
            const edited = info as Edited;
            if (info.status !== 'ready') { say('model_failed'); return; }
            if (edited.edit?.source && edited.tool?.kind === cfg.op) {
                const src = await stage.untilReady(await stage.fetchFile(edited.edit.source as string));
                if (src.status === 'ready') { await haveModel(src); await showMade(edited); return; }
            }
            await haveModel(info);
        }).catch(() => say('failed'));
    }
}
