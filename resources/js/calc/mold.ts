/**
 * Casting mold, as a module of the tool page: upload a model (or arrive from the calculator with ?from=uuid), see it
 * with the places a printed mold would hold on to painted red, choose the kind of mold, the number of parts, wall and
 * split; the server builds the parts; then the mold on the stage, what the tool measured, the casting with filled
 * undercuts as one more view, and one click on to the price.
 */
import type { BufferGeometry } from 'three';
import type { FacePaint } from './viewer';
import type { Stage } from './tool_page';
import { loadGeometryFromUrl } from './loaders';
import { FileInfo } from './api';

interface MoldCfg { upload: string; files: string; home: string; from: string | null; formats: string[]; maxMb: number }
interface Analysis { parts: number; undercut_pct: number; verdict: string; options: Record<string, number>; faces: number; flags: string }
type Report = NonNullable<FileInfo['mold']>;
type Words = (k: string, p?: Record<string, string | number>) => string;
type Rgb = [number, number, number];
type View = 'model' | 'mold' | 'cast';

const dict = () => (window as unknown as { MP_I18N?: Record<string, string> }).MP_I18N ?? {};
const tr: Words = (k, p = {}) => Object.entries(p).reduce((s, [a, b]) => s.replace(`:${a}`, String(b)), dict()[k] ?? k);

// the pieces of the mold in calm colours, what no piece lets go of in red, material added to the casting in orange
// (the viewer's lights are strong and multiply these: they are kept deep, as the colours of plates are)
const PIECES: Rgb[] = [[0.27, 0.4, 0.6], [0.6, 0.47, 0.2], [0.25, 0.5, 0.3], [0.46, 0.33, 0.58]];
const HIDDEN: Rgb = [0.72, 0.03, 0.03];
const ADDED: Rgb = [0.8, 0.36, 0.02];

const bytes = (base64: string): Uint8Array => Uint8Array.from(atob(base64), (c) => c.charCodeAt(0));

/** What the tool measured, in sentences: the same on the tool page and in the calculation. */
export function moldReport(m: Report, t: Words): { text: string; warn: boolean }[] {
    const nf = new Intl.NumberFormat(document.documentElement.lang || 'cs', { maximumFractionDigits: 1 });
    const size = { w: nf.format(m.box[0]), d: nf.format(m.box[1]), h: nf.format(m.box[2]), ml: nf.format(m.resin_ml), wall: m.wall };
    const pct = { pct: nf.format(m.undercut_pct) };
    const silicone = m.type === 'silicone';
    const verdict = m.verdict ?? (m.warnings.includes('undercuts') ? 'flexible' : 'rigid');
    return [
        { text: silicone ? t('mold.report.silicone', { ...size, sil: nf.format(m.silicone_ml ?? 0) }) : t('mold.report', size), warn: false },
        !silicone && (m.parts ?? 2) > 2 ? { text: t('mold.report.parts', { n: m.parts ?? 2 }), warn: false } : null,
        !silicone && m.fill ? { text: t('mold.report.filled', { ml: nf.format(m.added_ml ?? 0), before: nf.format(m.undercut_before_pct ?? 0), after: nf.format(m.undercut_pct) }), warn: false } : null,
        silicone ? { text: t('mold.report.silicone.how'), warn: false } : { text: t(`mold.verdict.${verdict}`, pct), warn: verdict !== 'rigid' },
        !silicone && m.axis === 'angle' ? { text: t('mold.report.angle', { deg: m.angle_deg ?? 0 }), warn: false } : null,
        m.warnings.includes('large_mold') ? { text: t('mold.report.large'), warn: false } : null,
    ].filter((l): l is { text: string; warn: boolean } => !!l);
}

export function bootMoldPage(stage: Stage): void {
    const cfg = (window as unknown as { MP_MOLD?: MoldCfg }).MP_MOLD;
    const input = document.getElementById('mold-file') as HTMLInputElement | null;
    if (!cfg || !input) return;
    const $ = <T extends HTMLElement>(id: string) => document.getElementById(id) as T;
    const status = $('mold-status'); const result = $('mold-result'); const go = $('mold-go') as HTMLButtonElement;
    const source = $('mold-source'); const wait = $('mold-wait');
    const type = $('mold-type') as HTMLSelectElement; const toSilicone = $('mold-to-silicone') as HTMLButtonElement;
    const parts = $('mold-parts') as HTMLSelectElement; const axis = $('mold-axis') as HTMLSelectElement; const split = $('mold-split') as HTMLSelectElement;
    const fill = $('mold-fill') as HTMLInputElement; const cast = $('mold-cast');
    const nf = new Intl.NumberFormat(document.documentElement.lang || 'cs', { maximumFractionDigits: 1 });
    let model: FileInfo | null = null;
    let mold: FileInfo | null = null;
    // the three things the stage can show, each loaded once: the model with what holds painted on it, the mold, the casting
    const shown: Partial<Record<View, { geom: BufferGeometry; faces: FacePaint | null; kind: string | null }>> = {};
    let view: View = 'model';
    let asked = 0;
    const say = (k: string | null, p: Record<string, string | number> = {}) => { status.textContent = k ? tr(k, p) : ''; status.classList.toggle('hidden', !k); };
    const headers = { Accept: 'application/json' };

    const division = () => ({ parts: Number(parts.value), axis: parts.value === '2' ? axis.value : 'auto', split: parts.value === '2' && split.value ? Number(split.value) : null });
    // the number of parts, the plane and the filling belong to the printed mold only; the plane to two parts only
    const applyType = (): void => {
        document.querySelectorAll<HTMLElement>('[data-mold-rigid]').forEach((e) => e.classList.toggle('hidden', type.value !== 'rigid'));
        document.querySelectorAll<HTMLElement>('[data-mold-two]').forEach((e) => e.classList.toggle('hidden', type.value !== 'rigid' || parts.value !== '2'));
    };

    /** Model · mold · casting: buttons over the viewer for what there is to see. */
    const renderViews = (): void => {
        const box = document.getElementById('param-views'); if (!box) return;
        const names: Record<View, string> = { model: tr('mold.page.model'), mold: tr('tools.mold.title'), cast: tr('mold.page.cast.title') };
        const have = (['model', 'mold', 'cast'] as View[]).filter((v) => shown[v]);
        box.innerHTML = '';
        if (have.length < 2) return;
        have.forEach((v) => {
            const b = document.createElement('button');
            b.type = 'button'; b.className = `chip !py-1 text-xs shadow-sm ${v === view ? 'chip-on' : ''}`; b.textContent = names[v];
            b.setAttribute('aria-pressed', v === view ? 'true' : 'false');
            b.onclick = () => show(v);
            box.appendChild(b);
        });
    };
    const show = (v: View): void => {
        const s = shown[v]; if (!s) return;
        view = v;
        // a copy: the viewer may rework the geometry it is given
        stage.show(s.geom.clone(), { kind: s.kind, faces: s.faces });
        renderViews();
    };

    /** Asks what a printed mold of this division would hold on to and paints it on the model. */
    const analyse = async (): Promise<void> => {
        if (!model || !shown.model) return;
        const mine = ++asked;
        const box = $('mold-analysis'); const text = $('mold-analysis-text');
        box.classList.remove('hidden');
        text.textContent = tr('mold.page.analysing');
        $('mold-options').textContent = '';
        try {
            const res = await fetch(`${cfg.files}/${model.uuid}/mold/analysis`, { method: 'POST', credentials: 'same-origin', headers: { ...headers, 'Content-Type': 'application/json' }, body: JSON.stringify(division()) });
            if (mine !== asked) return;                       // the visitor has chosen something else meanwhile
            if (!res.ok) throw new Error('analysis');
            const a = (await res.json()).analysis as Analysis;
            const flags = bytes(a.flags);
            shown.model.faces = { flags, color: (f) => (f & 8 ? HIDDEN : PIECES[f & 3]) };
            if (view === 'model') stage.viewer.paint(shown.model.faces);
            text.textContent = a.undercut_pct > 0 ? tr(`mold.page.analysis.${a.verdict}`, { pct: nf.format(a.undercut_pct) }) : tr('mold.page.analysis.none');
            text.classList.toggle('text-warn', a.verdict !== 'rigid');
            text.classList.toggle('font-medium', a.verdict !== 'rigid');
            $('mold-options').textContent = tr('mold.page.options', { a: nf.format(a.options['2'] ?? 0), b: nf.format(a.options['3'] ?? 0), c: nf.format(a.options['4'] ?? 0) });
        } catch {
            if (mine !== asked) return;
            shown.model.faces = null;
            if (view === 'model') stage.viewer.paint(null);
            box.classList.add('hidden');
        }
    };

    const haveModel = async (info: FileInfo): Promise<void> => {
        model = info; mold = null;
        delete shown.mold; delete shown.cast;
        source.textContent = `${info.name} · ${info.bbox ? [info.bbox.x, info.bbox.y, info.bbox.z].map((v) => Math.round(v)).join(' × ') + ' mm' : ''}`;
        source.classList.remove('hidden');
        go.disabled = false;
        say(null);
        stage.status({ bbox: info.bbox });
        if (info.stl_url) {
            try {
                $('mold-analysis').classList.add('hidden');
                // kind null: the triangles have to stay as the file has them, they are painted one by one
                shown.model = { geom: await loadGeometryFromUrl(info.stl_url), faces: null, kind: null };
                show('model');
                void analyse();
            } catch {
                delete shown.model;                           // the mold can still be made without the picture
            }
        }
        stage.reveal('settings');
    };

    const upload = async (file: File): Promise<void> => {
        const ext = (file.name.split('.').pop() ?? '').toLowerCase();
        result.classList.add('hidden'); wait.classList.remove('hidden'); cast.classList.add('hidden'); go.disabled = true; model = null;
        stage.go({ disabled: true, href: null }); stage.downloads([]);
        if (!cfg.formats.includes(ext)) { say('mold.page.bad_format', { f: cfg.formats.join(', ').toUpperCase() }); return; }
        if (file.size > cfg.maxMb * 1024 * 1024) { say('mold.page.too_big', { max: cfg.maxMb }); return; }
        say('mold.page.uploading');
        stage.busy(true);
        try {
            const fd = new FormData(); fd.append('file', file);
            const up = await fetch(cfg.upload, { method: 'POST', credentials: 'same-origin', headers, body: fd });
            if (!up.ok) throw new Error('upload');
            say('mold.page.processing');
            const info = await stage.untilReady((await up.json()).file);
            if (info.status !== 'ready') { say('mold.page.model_failed'); return; }
            await haveModel(info);
        } catch {
            say('mold.page.failed');
        } finally {
            stage.busy(false);
        }
    };

    /** The casting of a mold with filled undercuts: the model with the added material in orange. */
    const loadCast = async (m: Report): Promise<void> => {
        cast.classList.add('hidden');
        delete shown.cast;
        if (!m.fill || !m.cast_url || !m.cast_flags_url) return;
        try {
            const [geom, flags] = await Promise.all([
                loadGeometryFromUrl(m.cast_url),
                fetch(m.cast_flags_url, { credentials: 'same-origin' }).then((r) => (r.ok ? r.arrayBuffer() : Promise.reject(new Error('flags')))),
            ]);
            const marks = new Uint8Array(flags);
            shown.cast = { geom, faces: { flags: marks, color: (f) => (f ? ADDED : PIECES[0]) }, kind: null };
            cast.classList.remove('hidden');
            $('mold-cast-text').textContent = tr('mold.page.cast', { ml: nf.format(m.added_ml ?? 0) });
        } catch {
            cast.classList.add('hidden');
        }
    };

    const build = async (): Promise<void> => {
        if (!model) return;
        go.disabled = true; result.classList.add('hidden'); wait.classList.remove('hidden'); cast.classList.add('hidden');
        say(fill.checked && type.value === 'rigid' ? 'mold.page.building.fill' : 'mold.page.building');
        stage.busy(true);
        try {
            const body = { type: type.value, wall: Number(($('mold-wall') as HTMLSelectElement).value), fill: fill.checked && type.value === 'rigid', ...division() };
            const res = await fetch(`${cfg.files}/${model.uuid}/mold`, { method: 'POST', credentials: 'same-origin', headers: { ...headers, 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
            if (res.status === 503) { say('mold.unavailable'); go.disabled = false; return; }
            const answer = await res.json();
            if (!res.ok || !answer.file) throw new Error(answer.error ?? 'mold');
            const made = await stage.untilReady(answer.file as FileInfo);
            if (made.status !== 'ready' || !made.mold) throw new Error('mold');
            mold = made;
            const m = made.mold;
            const lines = moldReport(m, tr).map((l) => (l.warn ? `<span class="font-medium text-warn">${l.text}</span>` : l.text));
            lines.push(`<span class="text-muted">${tr(m.type === 'silicone' ? 'calc.tip.mold.silicone' : (m.parts ?? 2) > 2 ? 'calc.tip.mold.parts' : 'calc.tip.mold')}</span>`);
            $('mold-report').innerHTML = lines.map((s) => `<p class="mt-2 first:mt-0">${s}</p>`).join('');
            // a shape no printed mold lets go of: one click makes the mold for silicone instead
            toSilicone.classList.toggle('hidden', m.type === 'silicone' || m.verdict !== 'silicone');
            say(null);
            await loadCast(m);
            result.classList.remove('hidden'); wait.classList.add('hidden');
            if (made.stl_url) shown.mold = { geom: await loadGeometryFromUrl(made.stl_url), faces: null, kind: made.kind ?? null };
            // the status line, the price, the downloads and the main action are the mold's; the viewer shows it first
            await stage.fileResult({ ...made, stl_url: null });
            if (shown.mold) show('mold');
            if (made.stl_url) stage.fileDownloads(made);
            stage.reveal('result');
        } catch {
            say('mold.page.failed');
        } finally {
            stage.busy(false);
        }
        go.disabled = false;
    };

    stage.dropZone($('mold-drop'), input, (f) => { void upload(f); });
    ($('mold-form') as HTMLFormElement).onsubmit = (e) => { e.preventDefault(); void build(); };
    type.onchange = applyType;
    [parts, axis, split].forEach((el) => { el.onchange = () => { applyType(); void analyse(); }; });
    toSilicone.onclick = () => { type.value = 'silicone'; applyType(); void build(); };
    applyType();
    void mold;

    // came from the calculator: the model is already there
    if (cfg.from) {
        say('mold.page.processing');
        stage.fetchFile(cfg.from).then((f) => stage.untilReady(f)).then((info) => (info.status === 'ready' && info.kind !== 'mold' ? haveModel(info) : say('mold.page.model_failed'))).catch(() => say('mold.page.failed'));
    }
}
