/**
 * Tools → casting mold: upload a model (or arrive from the calculator with ?from=uuid), see it, choose the kind of
 * mold, wall and split; the server builds the parts; then the viewer, what the tool measured, and one click on to the price.
 */
import { Viewer } from './viewer';
import { loadGeometryFromUrl } from './loaders';
import { FileInfo } from './api';

interface MoldCfg { upload: string; files: string; home: string; from: string | null; formats: string[]; maxMb: number }
type Report = NonNullable<FileInfo['mold']>;
type Words = (k: string, p?: Record<string, string | number>) => string;

const dict = () => (window as unknown as { MP_I18N?: Record<string, string> }).MP_I18N ?? {};
const tr: Words = (k, p = {}) => Object.entries(p).reduce((s, [a, b]) => s.replace(`:${a}`, String(b)), dict()[k] ?? k);

/** What the tool measured, in sentences: the same on the tool page and in the calculation. */
export function moldReport(m: Report, t: Words): { text: string; warn: boolean }[] {
    const nf = new Intl.NumberFormat(document.documentElement.lang || 'cs', { maximumFractionDigits: 1 });
    const size = { w: nf.format(m.box[0]), d: nf.format(m.box[1]), h: nf.format(m.box[2]), ml: nf.format(m.resin_ml), wall: m.wall };
    const pct = { pct: nf.format(m.undercut_pct) };
    const silicone = m.type === 'silicone';
    const verdict = m.verdict ?? (m.warnings.includes('undercuts') ? 'flexible' : 'rigid');
    return [
        { text: silicone ? t('mold.report.silicone', { ...size, sil: nf.format(m.silicone_ml ?? 0) }) : t('mold.report', size), warn: false },
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
    let viewer: Viewer | null = null;
    let preview: Viewer | null = null;
    let model: FileInfo | null = null;
    const say = (k: string | null, p: Record<string, string | number> = {}) => { status.textContent = k ? tr(k, p) : ''; status.classList.toggle('hidden', !k); };
    const headers = { Accept: 'application/json' };

    const fetchFile = async (uuid: string): Promise<FileInfo> => (await (await fetch(`${cfg.files}/${uuid}`, { credentials: 'same-origin', headers })).json()).file;
    const untilReady = async (info: FileInfo): Promise<FileInfo> => {
        for (let i = 0; i < 120 && info.status !== 'ready' && info.status !== 'failed'; i++) {
            await new Promise((r) => setTimeout(r, 1500));
            info = await fetchFile(info.uuid);
        }
        return info;
    };
    // the parting plane and the way of splitting belong to the printed mold only
    const applyType = (): void => document.querySelectorAll<HTMLElement>('[data-mold-rigid]').forEach((e) => e.classList.toggle('hidden', type.value !== 'rigid'));
    const haveModel = async (info: FileInfo): Promise<void> => {
        model = info;
        source.textContent = `${info.name} · ${info.bbox ? [info.bbox.x, info.bbox.y, info.bbox.z].map((v) => Math.round(v)).join(' × ') + ' mm' : ''}`;
        source.classList.remove('hidden');
        go.disabled = false;
        say(null);
        if (info.stl_url) {
            try {
                $('mold-model').classList.remove('hidden');
                preview = preview ?? new Viewer($('mold-model-viewer') as HTMLCanvasElement);
                preview.setGeometry(await loadGeometryFromUrl(info.stl_url), 1, info.kind ?? null);
            } catch {
                $('mold-model').classList.add('hidden');       // the mold can still be made without the picture
            }
        }
    };

    const upload = async (file: File): Promise<void> => {
        const ext = (file.name.split('.').pop() ?? '').toLowerCase();
        result.classList.add('hidden'); go.disabled = true; model = null;
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

    const build = async (): Promise<void> => {
        if (!model) return;
        go.disabled = true; result.classList.add('hidden');
        say('mold.page.building');
        const split = ($('mold-split') as HTMLSelectElement).value;
        try {
            const res = await fetch(`${cfg.files}/${model.uuid}/mold`, { method: 'POST', credentials: 'same-origin', headers: { ...headers, 'Content-Type': 'application/json' }, body: JSON.stringify({ type: type.value, wall: Number(($('mold-wall') as HTMLSelectElement).value), axis: ($('mold-axis') as HTMLSelectElement).value, split: split ? Number(split) : null }) });
            if (res.status === 503) { say('mold.unavailable'); go.disabled = false; return; }
            const body = await res.json();
            if (!res.ok || !body.file) throw new Error(body.error ?? 'mold');
            const mold = await untilReady(body.file as FileInfo);
            if (mold.status !== 'ready' || !mold.mold) throw new Error('mold');
            const m = mold.mold;
            const lines = moldReport(m, tr).map((l) => (l.warn ? `<span class="font-semibold text-amber-800">${l.text}</span>` : l.text));
            lines.push(`<span class="text-muted">${tr(m.type === 'silicone' ? 'calc.tip.mold.silicone' : 'calc.tip.mold')}</span>`);
            $('mold-report').innerHTML = lines.map((s) => `<p class="mt-2 first:mt-0">${s}</p>`).join('');
            // a shape no printed mold lets go of: one click makes the mold for silicone instead
            toSilicone.classList.toggle('hidden', m.type === 'silicone' || m.verdict !== 'silicone');
            open.href = `${cfg.home}?open=${mold.uuid}`;
            say(null);
            result.classList.remove('hidden');
            if (mold.stl_url) {
                viewer = viewer ?? new Viewer(canvas);
                viewer.setGeometry(await loadGeometryFromUrl(mold.stl_url), 1, mold.kind ?? null);
            }
            result.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
    toSilicone.onclick = () => { type.value = 'silicone'; applyType(); build(); };
    applyType();

    // came from the calculator: the model is already there
    if (cfg.from) {
        say('mold.page.processing');
        fetchFile(cfg.from).then(untilReady).then((info) => (info.status === 'ready' && info.kind !== 'mold' ? haveModel(info) : say('mold.page.model_failed'))).catch(() => say('mold.page.failed'));
    }
}
