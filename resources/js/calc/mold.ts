/**
 * Tools → casting mold: upload a model (or arrive from the calculator with ?from=uuid), choose wall and split,
 * the server builds both halves; then the viewer, what the tool measured, and one click on to the price.
 */
import { Viewer } from './viewer';
import { loadGeometryFromUrl } from './loaders';
import { FileInfo } from './api';

interface MoldCfg { upload: string; files: string; home: string; from: string | null; formats: string[]; maxMb: number }

const dict = () => (window as unknown as { MP_I18N?: Record<string, string> }).MP_I18N ?? {};
const tr = (k: string, p: Record<string, string | number> = {}) => Object.entries(p).reduce((s, [a, b]) => s.replace(`:${a}`, String(b)), dict()[k] ?? k);

export function bootMoldPage(): void {
    const cfg = (window as unknown as { MP_MOLD?: MoldCfg }).MP_MOLD;
    const input = document.getElementById('mold-file') as HTMLInputElement | null;
    if (!cfg || !input) return;
    const $ = <T extends HTMLElement>(id: string) => document.getElementById(id) as T;
    const status = $('mold-status'); const result = $('mold-result'); const go = $('mold-go') as HTMLButtonElement;
    const source = $('mold-source'); const open = $('mold-open') as HTMLAnchorElement; const canvas = $('mold-viewer') as HTMLCanvasElement;
    let viewer: Viewer | null = null;
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
    const haveModel = (info: FileInfo) => {
        model = info;
        source.textContent = `${info.name} · ${info.bbox ? [info.bbox.x, info.bbox.y, info.bbox.z].map((v) => Math.round(v)).join(' × ') + ' mm' : ''}`;
        source.classList.remove('hidden');
        go.disabled = false;
        say(null);
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
            haveModel(info);
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
            const res = await fetch(`${cfg.files}/${model.uuid}/mold`, { method: 'POST', credentials: 'same-origin', headers: { ...headers, 'Content-Type': 'application/json' }, body: JSON.stringify({ wall: Number(($('mold-wall') as HTMLSelectElement).value), axis: ($('mold-axis') as HTMLSelectElement).value, split: split ? Number(split) : null }) });
            if (res.status === 503) { say('mold.unavailable'); go.disabled = false; return; }
            const body = await res.json();
            if (!res.ok || !body.file) throw new Error(body.error ?? 'mold');
            const mold = await untilReady(body.file as FileInfo);
            if (mold.status !== 'ready' || !mold.mold) throw new Error('mold');
            const m = mold.mold;
            const nf = new Intl.NumberFormat(document.documentElement.lang || 'cs', { maximumFractionDigits: 1 });
            $('mold-report').innerHTML = [
                tr('mold.report', { w: nf.format(m.box[0]), d: nf.format(m.box[1]), h: nf.format(m.box[2]), ml: nf.format(m.resin_ml), wall: m.wall }),
                m.warnings.includes('undercuts') ? `<span class="text-amber-800">${tr('mold.report.undercuts', { pct: nf.format(m.undercut_pct) })}</span>` : '',
                m.warnings.includes('large_mold') ? tr('mold.report.large') : '',
                `<span class="text-muted">${tr('calc.tip.mold')}</span>`,
            ].filter(Boolean).map((s) => `<p class="mt-2 first:mt-0">${s}</p>`).join('');
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

    // came from the calculator: the model is already there
    if (cfg.from) {
        say('mold.page.processing');
        fetchFile(cfg.from).then(untilReady).then((info) => { if (info.status === 'ready' && info.kind !== 'mold') haveModel(info); else say('mold.page.model_failed'); }).catch(() => say('mold.page.failed'));
    }
}
