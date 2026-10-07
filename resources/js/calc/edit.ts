/**
 * Editing a model file, as a module of the tool page: upload a model (or arrive from the calculator with ?from=uuid),
 * see it, set the tool (a split: the bed, the planes, the joints), let the server make the new file in its queue,
 * and get the result on the stage: every piece in its own colour, the sizes, the price, the downloads and a map of
 * where each piece sits. The first tool here is the split; others of the kind (scale…) share this page.
 */
import type { BufferGeometry } from 'three';
import type { Piece } from './viewer';
import type { Stage, MenuItem, PriceConfig } from './tool_page';
import { loadGeometryFromUrl } from './loaders';
import { FileInfo } from './api';

interface Cfg { op: string; upload: string; files: string; parts: string; home: string; from: string | null; formats: string[]; maxMb: number; beds: Record<string, number[] | null>; margin: number; farmMargin: number; config: PriceConfig & { bed_mm: { x: number; y: number; z: number } }; i18n: Record<string, string> }
interface Analysis { bbox: { x: number; y: number; z: number }; fits: boolean; planes: Record<'x' | 'y' | 'z', number[]> | null; pieces: number | null; too_many: boolean; repaired: boolean }
interface MapPiece { n: number; part: string; cell: number[]; lo: number[]; hi: number[]; volume_mm3: number; down: [string, number] | null; number_at: number[] | null }
interface Report { op: string; stage?: string; planes?: Record<string, number[]>; joint?: string; pins?: number; keys?: number; pieces?: number; cells?: number[]; model?: number[]; warnings?: string[]; map?: MapPiece[]; too_big?: string[]; repaired?: boolean; parts?: string[]; pieces_tris?: Piece[] }
type Edited = FileInfo & { edit?: Report | null; tool?: { kind: string; params: Record<string, unknown> & { each?: number[][]; parts?: string[] } ; url: string } | null };

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
    const status = $('edit-status'); const go = $('edit-go') as HTMLButtonElement; const source = $('edit-source');
    const result = $('edit-result'); const wait = $('edit-wait'); const analysisEl = $('edit-analysis');
    let model: FileInfo | null = null; let modelGeom: BufferGeometry | null = null;
    let made: Edited | null = null;
    let analysis: Analysis | null = null;
    let planes: Record<'x' | 'y' | 'z', number[]> | null = null;      // the planes as the visitor has them (null: as the tool plans them)
    let asked = 0; let polling = 0;
    const say = (k: string | null, p: Record<string, string | number> = {}) => { status.textContent = k ? t(k, p) : ''; status.classList.toggle('hidden', !k); };
    const headers = { Accept: 'application/json' };
    let commit: () => void = () => undefined;

    const settings = (): Record<string, unknown> => {
        const p: Record<string, unknown> = {};
        form.querySelectorAll<HTMLInputElement>('[data-param]').forEach((i) => { p[i.dataset.param!] = Number(i.value); });
        form.querySelectorAll<HTMLInputElement>('[data-flag]').forEach((i) => { p[i.dataset.flag!] = i.checked; });
        form.querySelectorAll<HTMLInputElement>('[data-choice]:checked').forEach((i) => { p[i.dataset.choice!] = i.value; });
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
    /** The usable bed the pieces are cut for, in mm (the preset less the margins, or the visitor's own numbers). */
    const bedNow = (): [number, number, number] => {
        const p = settings();
        const preset = cfg.beds[String(p.bed ?? 'farm')];
        if (!preset) return [Number(p.bed_x) - 2 * cfg.margin, Number(p.bed_y) - 2 * cfg.margin, Number(p.bed_z)];
        const margin = p.bed === 'farm' ? Math.max(cfg.margin, cfg.farmMargin) : cfg.margin;
        const b = p.bed === 'farm' ? [cfg.config.bed_mm.x, cfg.config.bed_mm.y, cfg.config.bed_mm.z] : preset;
        return [b[0] - 2 * margin, b[1] - 2 * margin, b[2]];
    };

    // ── the planes: sliders for each one the tool plans, the viewer shows them on the model ─────────
    const showPlanes = (): void => {
        const list = $('edit-planes'); const box = $('edit-planes-box'); const reset = $('edit-planes-reset');
        const now = planes ?? analysis?.planes ?? null;
        list.innerHTML = '';
        if (!now || !model?.bbox) { box.classList.add('hidden'); viewer.setPlanes(null); return; }
        const all: { axis: 'x' | 'y' | 'z'; at: number }[] = [];
        (['x', 'y', 'z'] as const).forEach((axis) => {
            const size = model!.bbox![axis];
            now[axis].forEach((at, i) => {
                all.push({ axis, at });
                const row = document.createElement('div');
                row.className = 'tool-num';
                row.innerHTML = `<label class="text-sm font-medium text-ink">${esc(t(`plane.${axis}`, { n: i + 1 }))}</label>
                    <div class="mt-1 flex items-center gap-3"><input type="range" min="2" max="${(size - 2).toFixed(1)}" step="0.5" value="${at}" class="min-w-0 flex-1 accent-ink" aria-label="${esc(t(`plane.${axis}`, { n: i + 1 }))}"><span class="num w-20 text-right text-sm text-muted">${nf.format(at)} mm</span></div>`;
                const range = row.querySelector('input')!; const out = row.querySelector('span')!;
                range.addEventListener('input', () => {
                    planes = planes ?? JSON.parse(JSON.stringify(now));
                    planes![axis][i] = Number(range.value);
                    out.textContent = `${nf.format(Number(range.value))} mm`;
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
    const allPlanes = (): { axis: 'x' | 'y' | 'z'; at: number }[] => {
        const now = planes ?? analysis?.planes; if (!now) return [];
        return (['x', 'y', 'z'] as const).flatMap((axis) => now[axis].map((at) => ({ axis, at })));
    };
    $('edit-planes-reset').onclick = () => { planes = null; void analyse(); commit(); };

    /** Asks what the tool would do with this model and these settings: the planes, how many pieces. */
    const analyse = async (): Promise<void> => {
        if (!model || cfg.op !== 'split') return;
        const mine = ++asked;
        analysisEl.classList.remove('hidden'); analysisEl.textContent = t('analysing');
        try {
            const res = await fetch(`${cfg.files}/${model.uuid}/edit/analysis`, { method: 'POST', credentials: 'same-origin', headers: { ...headers, 'Content-Type': 'application/json' }, body: JSON.stringify({ op: cfg.op, ...settings() }) });
            if (mine !== asked) return;
            if (res.status === 503) { say('unavailable'); return; }
            if (!res.ok) throw new Error('analysis');
            analysis = (await res.json()).analysis as Analysis;
            if (analysis.too_many) { analysisEl.textContent = t('too_many'); analysisEl.classList.add('text-warn'); go.disabled = true; showPlanes(); return; }
            analysisEl.classList.remove('text-warn');
            const cuts = analysis.planes ? Object.values(analysis.planes).reduce((n, l) => n + l.length, 0) : 0;
            analysisEl.textContent = analysis.fits ? t('fits') : t('plan', { c: cuts, p: analysis.pieces ?? 0 });
            go.disabled = analysis.fits;
            showPlanes();
        } catch {
            if (mine !== asked) return;
            analysisEl.classList.add('hidden');
        }
    };

    const haveModel = async (info: FileInfo): Promise<void> => {
        model = info; made = null; analysis = null; planes = null;
        source.textContent = `${info.name} · ${info.bbox ? [info.bbox.x, info.bbox.y, info.bbox.z].map((v) => Math.round(v)).join(' × ') + ' mm' : ''}`;
        source.classList.remove('hidden');
        say(null);
        result.classList.add('hidden'); wait.classList.remove('hidden'); $('edit-map').classList.add('hidden');
        stage.go({ disabled: true, href: null }); stage.downloads([]);
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

    /** Waits for the queue, saying which phase the tool is in (loading, repairing, cutting, joints, layout). */
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
                let cells = '';
                for (let x = 0; x < kx; x++) {
                    const pc = r.map.find((m) => m.cell[0] === x && m.cell[1] === y && m.cell[2] === z);
                    cells += pc ? `<span class="flex aspect-square min-h-9 items-center justify-center rounded-md text-sm font-bold text-white" style="background:${PIECES[(pc.n - 1) % PIECES.length]}">${pc.n}</span>` : '<span class="flex aspect-square min-h-9 items-center justify-center rounded-md border border-dashed border-slate-300"></span>';
                }
                rows += cells;
            }
            levels.push(`<div><div class="mb-1 text-xs text-muted">${kz > 1 ? esc(t('map.level', { n: z + 1 })) : ''}</div><div class="grid gap-1" style="grid-template-columns:repeat(${kx},minmax(0,2.5rem))">${rows}</div></div>`);
        }
        const pieces = r.map.map((m) => `<li><span class="inline-block h-3 w-3 rounded-sm align-middle" style="background:${PIECES[(m.n - 1) % PIECES.length]}"></span> <span class="font-medium text-ink">${esc(t('piece', { n: m.n }))}</span> <span class="text-muted">${esc(t('piece.size', { x: nf.format(m.hi[0] - m.lo[0]), y: nf.format(m.hi[1] - m.lo[1]), z: nf.format(m.hi[2] - m.lo[2]) }))}${m.down ? ` · ${esc(t(`piece.down.${m.down[0]}`))}` : ''}</span></li>`).join('');
        box.innerHTML = `<div class="font-semibold text-ink">${esc(t('map'))}</div><div class="mt-2 flex flex-wrap gap-4">${levels.join('')}</div><ul class="mt-3 space-y-1">${pieces}</ul><p class="mt-2 text-xs text-muted">${esc(t('glue'))}</p>`;
        box.classList.remove('hidden');
    };

    const showMade = async (file: Edited): Promise<void> => {
        made = file;
        const r = file.edit ?? { op: cfg.op };
        const lines: string[] = [];
        if (cfg.op === 'split') {
            lines.push(t('report', { n: r.pieces ?? 0, c: Object.values(r.planes ?? {}).reduce((n, l) => n + l.length, 0) }));
            if (r.pins) lines.push(t('report.pins', { n: r.pins }));
            if (r.keys) lines.push(t('report.keys', { n: r.keys }));
            if (!r.pins && !r.keys) lines.push(t('report.none'));
            if (r.repaired) lines.push(t('report.repaired'));
        }
        $('edit-report').innerHTML = lines.map((s) => `<p class="mt-2 first:mt-0">${esc(s)}</p>`).join('');
        const by: Record<string, string[]> = {};
        (r.warnings ?? []).forEach((w) => { (by.result = by.result ?? []).push(t(`warn.${w}`, { n: (r.too_big ?? []).map(partLabel).join(', ') })); });
        stage.warnings(by);
        result.classList.remove('hidden'); wait.classList.add('hidden');
        if (file.stl_url) {
            const geom = await loadGeometryFromUrl(file.stl_url);
            const pieces = r.pieces_tris ?? null;
            viewer.setSpreadAxis(null);
            stage.show(geom, { kind: null, pieces });
            viewer.getPieces().forEach((p, i) => viewer.setPieceColor(i, p.name.startsWith('piece_') ? PIECES[(Number(p.name.slice(6)) - 1) % PIECES.length] : '#8C9199'));
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
        if ((file.parts ?? []).length) {
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
        stage.status(model?.bbox ? { bbox: model.bbox } : null);
        stage.downloads([]); stage.go({ disabled: true, href: null }); stage.warnings({});
        result.classList.add('hidden'); wait.classList.remove('hidden'); $('edit-map').classList.add('hidden');
        showPlanes();
        stage.reveal('settings');
    };

    stage.dropZone($('edit-drop'), input, (f) => { void upload(f); });
    form.onsubmit = (e) => { e.preventDefault(); void build(); };
    $('edit-again').onclick = again;
    form.querySelectorAll<HTMLInputElement>('[data-flag], [data-choice], [data-param]').forEach((el) => el.addEventListener('change', () => { applyWhen(); if (el.dataset.choice === 'bed' || el.dataset.param) planes = null; void analyse(); commit(); }));
    commit = stage.track({ read: () => settings(), write: (s) => { Object.entries(s).forEach(([k, v]) => {
        const num = form.querySelector<HTMLInputElement>(`[data-param="${k}"]`); if (num) num.value = String(v);
        const flag = form.querySelector<HTMLInputElement>(`[data-flag="${k}"]`); if (flag) flag.checked = Boolean(v);
        const choice = form.querySelector<HTMLInputElement>(`[data-choice="${k}"][value="${String(v)}"]`); if (choice) choice.checked = true;
    }); planes = (s.planes as Record<'x' | 'y' | 'z', number[]>) ?? null; applyWhen(); showPlanes(); } }, !cfg.from);
    applyWhen();
    void made;

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
