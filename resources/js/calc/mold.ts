/**
 * Tools → casting mold: upload a model (or arrive from the calculator with ?from=uuid), see it with the places a
 * printed mold would hold on to painted red, choose the kind of mold, the number of parts, wall and split; the server
 * builds the parts; then the viewer, what the tool measured, the casting with filled undercuts to look at, and one
 * click on to the price.
 */
import { Viewer, FacePaint } from './viewer';
import { loadGeometryFromUrl } from './loaders';
import { FileInfo } from './api';

interface MoldCfg { upload: string; files: string; home: string; from: string | null; formats: string[]; maxMb: number }
interface Analysis { parts: number; undercut_pct: number; verdict: string; options: Record<string, number>; faces: number; flags: string }
type Report = NonNullable<FileInfo['mold']>;
type Words = (k: string, p?: Record<string, string | number>) => string;
type Rgb = [number, number, number];

const dict = () => (window as unknown as { MP_I18N?: Record<string, string> }).MP_I18N ?? {};
const tr: Words = (k, p = {}) => Object.entries(p).reduce((s, [a, b]) => s.replace(`:${a}`, String(b)), dict()[k] ?? k);

// the pieces of the mold in calm colours, what no piece lets go of in red, material added to the casting in orange
const PIECES: Rgb[] = [[0.51, 0.65, 0.83], [0.89, 0.79, 0.54], [0.62, 0.8, 0.62], [0.76, 0.66, 0.84]];
const HIDDEN: Rgb = [0.84, 0.16, 0.16];
const ADDED: Rgb = [0.9, 0.55, 0.16];

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

export function bootMoldPage(): void {
    const cfg = (window as unknown as { MP_MOLD?: MoldCfg }).MP_MOLD;
    const input = document.getElementById('mold-file') as HTMLInputElement | null;
    if (!cfg || !input) return;
    const $ = <T extends HTMLElement>(id: string) => document.getElementById(id) as T;
    const status = $('mold-status'); const result = $('mold-result'); const go = $('mold-go') as HTMLButtonElement;
    const source = $('mold-source'); const open = $('mold-open') as HTMLAnchorElement; const canvas = $('mold-viewer') as HTMLCanvasElement;
    const type = $('mold-type') as HTMLSelectElement; const toSilicone = $('mold-to-silicone') as HTMLButtonElement;
    const parts = $('mold-parts') as HTMLSelectElement; const axis = $('mold-axis') as HTMLSelectElement; const split = $('mold-split') as HTMLSelectElement;
    const fill = $('mold-fill') as HTMLInputElement; const cast = $('mold-cast');
    const nf = new Intl.NumberFormat(document.documentElement.lang || 'cs', { maximumFractionDigits: 1 });
    let viewer: Viewer | null = null;
    let preview: Viewer | null = null;
    let castViewer: Viewer | null = null;
    let model: FileInfo | null = null;
    let asked = 0;
    const say = (k: string | null, p: Record<string, string | number> = {}) => { status.textContent = k ? tr(k, p) : ''; status.classList.toggle('hidden', !k); };
    const headers = { Accept: 'application/json' };

    const fetchFile = async (uuid: string): Promise<FileInfo> => (await (await fetch(`${cfg.files}/${uuid}`, { credentials: 'same-origin', headers })).json()).file;
    const untilReady = async (info: FileInfo): Promise<FileInfo> => {
        for (let i = 0; i < 200 && info.status !== 'ready' && info.status !== 'failed'; i++) {
            await new Promise((r) => setTimeout(r, 1500));
            info = await fetchFile(info.uuid);
        }
        return info;
    };
    const division = () => ({ parts: Number(parts.value), axis: parts.value === '2' ? axis.value : 'auto', split: parts.value === '2' && split.value ? Number(split.value) : null });
    // the number of parts, the plane and the filling belong to the printed mold only; the plane to two parts only
    const applyType = (): void => {
        document.querySelectorAll<HTMLElement>('[data-mold-rigid]').forEach((e) => e.classList.toggle('hidden', type.value !== 'rigid'));
        document.querySelectorAll<HTMLElement>('[data-mold-two]').forEach((e) => e.classList.toggle('hidden', type.value !== 'rigid' || parts.value !== '2'));
    };

    /** Asks what a printed mold of this division would hold on to and paints it on the model. */
    const analyse = async (): Promise<void> => {
        if (!model || !preview) return;
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
            const paint: FacePaint = { flags: bytes(a.flags), color: (f) => (f & 8 ? HIDDEN : PIECES[f & 3]) };
            preview.paint(paint);
            text.textContent = a.undercut_pct > 0 ? tr(`mold.page.analysis.${a.verdict}`, { pct: nf.format(a.undercut_pct) }) : tr('mold.page.analysis.none');
            text.classList.toggle('text-amber-800', a.verdict !== 'rigid');
            text.classList.toggle('font-semibold', a.verdict !== 'rigid');
            $('mold-options').textContent = tr('mold.page.options', { a: nf.format(a.options['2'] ?? 0), b: nf.format(a.options['3'] ?? 0), c: nf.format(a.options['4'] ?? 0) });
        } catch {
            if (mine !== asked) return;
            preview.paint(null);
            box.classList.add('hidden');
        }
    };

    const haveModel = async (info: FileInfo): Promise<void> => {
        model = info;
        source.textContent = `${info.name} · ${info.bbox ? [info.bbox.x, info.bbox.y, info.bbox.z].map((v) => Math.round(v)).join(' × ') + ' mm' : ''}`;
        source.classList.remove('hidden');
        go.disabled = false;
        say(null);
        if (info.stl_url) {
            try {
                $('mold-model').classList.remove('hidden');
                $('mold-analysis').classList.add('hidden');
                preview = preview ?? new Viewer($('mold-model-viewer') as HTMLCanvasElement);
                // kind null: the triangles have to stay as the file has them, they are painted one by one
                preview.setGeometry(await loadGeometryFromUrl(info.stl_url), 1, null);
                analyse();
            } catch {
                $('mold-model').classList.add('hidden');       // the mold can still be made without the picture
            }
        }
    };

    const upload = async (file: File): Promise<void> => {
        const ext = (file.name.split('.').pop() ?? '').toLowerCase();
        result.classList.add('hidden'); cast.classList.add('hidden'); go.disabled = true; model = null;
        if (!cfg.formats.includes(ext)) { say('mold.page.bad_format', { f: cfg.formats.join(', ').toUpperCase() }); return; }
        if (file.size > cfg.maxMb * 1024 * 1024) { say('mold.page.too_big', { max: cfg.maxMb }); return; }
        say('mold.page.uploading');
        try {
            const fd = new FormData(); fd.append('file', file);
            const up = await fetch(cfg.upload, { method: 'POST', credentials: 'same-origin', headers, body: fd });
            if (!up.ok) throw new Error('upload');
            say('mold.page.processing');
            const info = await untilReady((await up.json()).file);
            if (info.status !== 'ready') { say('mold.page.model_failed'); return; }
            await haveModel(info);
        } catch {
            say('mold.page.failed');
        }
    };

    /** The casting of a mold with filled undercuts: the model with the added material in orange. */
    const showCast = async (m: Report): Promise<void> => {
        cast.classList.add('hidden');
        if (!m.fill || !m.cast_url || !m.cast_flags_url) return;
        try {
            const [geom, flags] = await Promise.all([
                loadGeometryFromUrl(m.cast_url),
                fetch(m.cast_flags_url, { credentials: 'same-origin' }).then((r) => (r.ok ? r.arrayBuffer() : Promise.reject(new Error('flags')))),
            ]);
            cast.classList.remove('hidden');
            castViewer = castViewer ?? new Viewer($('mold-cast-viewer') as HTMLCanvasElement);
            castViewer.setGeometry(geom, 1, null, null, { flags: new Uint8Array(flags), color: (f) => (f ? ADDED : PIECES[0]) });
            $('mold-cast-text').textContent = tr('mold.page.cast', { ml: nf.format(m.added_ml ?? 0) });
        } catch {
            cast.classList.add('hidden');
        }
    };

    const build = async (): Promise<void> => {
        if (!model) return;
        go.disabled = true; result.classList.add('hidden'); cast.classList.add('hidden');
        say(fill.checked && type.value === 'rigid' ? 'mold.page.building.fill' : 'mold.page.building');
        try {
            const body = { type: type.value, wall: Number(($('mold-wall') as HTMLSelectElement).value), fill: fill.checked && type.value === 'rigid', ...division() };
            const res = await fetch(`${cfg.files}/${model.uuid}/mold`, { method: 'POST', credentials: 'same-origin', headers: { ...headers, 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
            if (res.status === 503) { say('mold.unavailable'); go.disabled = false; return; }
            const answer = await res.json();
            if (!res.ok || !answer.file) throw new Error(answer.error ?? 'mold');
            const mold = await untilReady(answer.file as FileInfo);
            if (mold.status !== 'ready' || !mold.mold) throw new Error('mold');
            const m = mold.mold;
            const lines = moldReport(m, tr).map((l) => (l.warn ? `<span class="font-semibold text-amber-800">${l.text}</span>` : l.text));
            lines.push(`<span class="text-muted">${tr(m.type === 'silicone' ? 'calc.tip.mold.silicone' : (m.parts ?? 2) > 2 ? 'calc.tip.mold.parts' : 'calc.tip.mold')}</span>`);
            $('mold-report').innerHTML = lines.map((s) => `<p class="mt-2 first:mt-0">${s}</p>`).join('');
            // a shape no printed mold lets go of: one click makes the mold for silicone instead
            toSilicone.classList.toggle('hidden', m.type === 'silicone' || m.verdict !== 'silicone');
            open.href = `${cfg.home}?open=${mold.uuid}`;
            say(null);
            await showCast(m);
            result.classList.remove('hidden');
            if (mold.stl_url) {
                viewer = viewer ?? new Viewer(canvas);
                viewer.setGeometry(await loadGeometryFromUrl(mold.stl_url), 1, mold.kind ?? null);
            }
            (m.fill ? cast : result).scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch {
            say('mold.page.failed');
        }
        go.disabled = false;
    };

    input.onchange = () => { if (input.files?.[0]) upload(input.files[0]); };
    const drop = $('mold-drop');
    drop.ondragover = (e) => { e.preventDefault(); drop.classList.add('border-action'); };
    drop.ondragleave = () => drop.classList.remove('border-action');
    drop.ondrop = (e) => { e.preventDefault(); drop.classList.remove('border-action'); const f = e.dataTransfer?.files?.[0]; if (f) upload(f); };
    ($('mold-form') as HTMLFormElement).onsubmit = (e) => { e.preventDefault(); build(); };
    type.onchange = applyType;
    [parts, axis, split].forEach((el) => { el.onchange = () => { applyType(); analyse(); }; });
    toSilicone.onclick = () => { type.value = 'silicone'; applyType(); build(); };
    applyType();

    // came from the calculator: the model is already there
    if (cfg.from) {
        say('mold.page.processing');
        fetchFile(cfg.from).then(untilReady).then((info) => (info.status === 'ready' && info.kind !== 'mold' ? haveModel(info) : say('mold.page.model_failed'))).catch(() => say('mold.page.failed'));
    }
}
