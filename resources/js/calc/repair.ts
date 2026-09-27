/**
 * Tools → repair a model: upload → wait for processing → the server repairs it → the repaired model in the viewer,
 * what was wrong and what was done, download, and on to the price. The original is never changed.
 */
import { Viewer } from './viewer';
import { loadGeometryFromUrl } from './loaders';
import { FileInfo } from './api';

interface RepairCfg { upload: string; files: string; home: string; formats: string[]; maxMb: number }
interface Facts { watertight: boolean; open_edges: number; non_manifold_edges: number; shells: number; flipped_normals: boolean; triangles: number }
interface Report { verdict: string; before: Facts; after: Facts; actions: Record<string, number | boolean> }

const dict = () => (window as unknown as { MP_I18N?: Record<string, string> }).MP_I18N ?? {};
const tr = (k: string, p: Record<string, string | number> = {}) => Object.entries(p).reduce((s, [a, b]) => s.replace(`:${a}`, String(b)), dict()[k] ?? k);
const esc = (s: string) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c] as string));

export function bootRepairPage(): void {
    const cfg = (window as unknown as { MP_REPAIR?: RepairCfg }).MP_REPAIR;
    const input = document.getElementById('repair-file') as HTMLInputElement | null;
    if (!cfg || !input) return;
    const $ = <T extends HTMLElement>(id: string) => document.getElementById(id) as T;
    const status = $('repair-status'); const result = $('repair-result');
    let viewer: Viewer | null = null;
    const say = (k: string | null, p: Record<string, string | number> = {}) => { status.textContent = k ? tr(k, p) : ''; status.classList.toggle('hidden', !k); };
    const headers = { Accept: 'application/json' };
    const nf = new Intl.NumberFormat(document.documentElement.lang || 'cs');

    const untilReady = async (info: FileInfo): Promise<FileInfo> => {
        for (let i = 0; i < 120 && info.status !== 'ready' && info.status !== 'failed'; i++) {
            await new Promise((r) => setTimeout(r, 1500));
            info = (await (await fetch(`${cfg.files}/${info.uuid}`, { credentials: 'same-origin', headers })).json()).file;
        }
        return info;
    };

    const show = (file: FileInfo & { repair?: Report | null }): void => {
        const r = file.repair;
        if (!r) return;
        $('repair-verdict').textContent = tr(`repair.verdict.${r.verdict}`);
        $('repair-verdict').className = `font-bold ${r.verdict === 'repaired' || r.verdict === 'clean' ? 'text-ok' : 'text-amber-800'}`;
        const acts = Object.entries(r.actions).filter(([, v]) => v).map(([k, v]) => tr(`repair.act.${k}`, { n: typeof v === 'number' ? nf.format(v) : '' }));
        $('repair-actions').innerHTML = (acts.length ? acts : [tr('repair.act.none')]).map((a) => `<li>${esc(a)}</li>`).join('');
        const yes = (b: boolean) => tr(b ? 'repair.yes' : 'repair.no');
        const rows: [string, string, string, boolean][] = [
            ['watertight', yes(r.before.watertight), yes(r.after.watertight), r.after.watertight],
            ['open_edges', nf.format(r.before.open_edges), nf.format(r.after.open_edges), r.after.open_edges === 0],
            ['non_manifold_edges', nf.format(r.before.non_manifold_edges), nf.format(r.after.non_manifold_edges), r.after.non_manifold_edges === 0],
            ['flipped_normals', yes(r.before.flipped_normals), yes(r.after.flipped_normals), !r.after.flipped_normals],
            ['shells', nf.format(r.before.shells), nf.format(r.after.shells), true],
            ['triangles', nf.format(r.before.triangles), nf.format(r.after.triangles), true],
        ];
        $('repair-table').innerHTML = rows.map(([k, b, a, ok]) => `<tr class="border-t border-line"><td class="py-1 text-muted">${esc(tr(`repair.row.${k}`))}</td><td class="py-1">${esc(b)}</td><td class="py-1 font-semibold ${ok ? 'text-ok' : 'text-amber-800'}">${esc(a)}</td></tr>`).join('');
        const left = $('repair-left');
        const still = !r.after.watertight || r.after.flipped_normals;
        left.textContent = still ? tr('repair.left') : '';
        left.classList.toggle('hidden', !still);
        ($('repair-download') as HTMLAnchorElement).href = `${cfg.files}/${file.uuid}/model.stl`;
        ($('repair-download') as HTMLAnchorElement).setAttribute('download', file.name);
        ($('repair-go') as HTMLAnchorElement).href = `${cfg.home}?open=${file.uuid}`;
        result.classList.remove('hidden');
        if (file.stl_url) {
            viewer = viewer ?? new Viewer($('repair-viewer') as HTMLCanvasElement);
            loadGeometryFromUrl(file.stl_url).then((g) => viewer!.setGeometry(g, 1, null)).catch(() => undefined);
        }
        result.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const run = async (file: File): Promise<void> => {
        const ext = (file.name.split('.').pop() ?? '').toLowerCase();
        result.classList.add('hidden');
        if (!cfg.formats.includes(ext)) { say('repair.bad_format', { f: cfg.formats.join(', ').toUpperCase() }); return; }
        if (file.size > cfg.maxMb * 1024 * 1024) { say('repair.too_big', { max: cfg.maxMb }); return; }
        say('repair.uploading');
        try {
            const fd = new FormData(); fd.append('file', file);
            const up = await fetch(cfg.upload, { method: 'POST', credentials: 'same-origin', headers, body: fd });
            if (!up.ok) throw new Error('upload');
            say('repair.checking');
            const info = await untilReady((await up.json()).file);
            if (info.status !== 'ready') { say('repair.failed'); return; }
            say('repair.repairing');
            const res = await fetch(`${cfg.files}/${info.uuid}/repair`, { method: 'POST', credentials: 'same-origin', headers });
            if (res.status === 503) { say('repair.unavailable'); return; }
            if (!res.ok) { say('repair.failed'); return; }
            const fixed = await untilReady((await res.json()).file);
            if (fixed.status !== 'ready') { say('repair.failed'); return; }
            say(null);
            show(fixed as FileInfo & { repair?: Report | null });
        } catch {
            say('repair.failed');
        }
    };

    input.onchange = () => { if (input.files?.[0]) run(input.files[0]); input.value = ''; };
    const drop = document.getElementById('repair-drop');
    if (drop) {
        drop.ondragover = (e) => { e.preventDefault(); drop.classList.add('border-action'); };
        drop.ondragleave = () => drop.classList.remove('border-action');
        drop.ondrop = (e) => { e.preventDefault(); drop.classList.remove('border-action'); const f = e.dataTransfer?.files?.[0]; if (f) run(f); };
    }
}
