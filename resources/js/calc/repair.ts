/**
 * Repair a model, as a module of the tool page: upload → wait for processing → the server repairs it → the repaired
 * model on the stage, what was wrong and what was done, the download, and on to the price. The original is never changed.
 */
import type { Stage } from './tool_page';
import { FileInfo } from './api';

interface RepairCfg { upload: string; files: string; home: string; formats: string[]; maxMb: number }
interface Facts { watertight: boolean; open_edges: number; non_manifold_edges: number; shells: number; flipped_normals: boolean; triangles: number }
interface Report { verdict: string; before: Facts; after: Facts; actions: Record<string, number | boolean> }

const dict = () => (window as unknown as { MP_I18N?: Record<string, string> }).MP_I18N ?? {};
const tr = (k: string, p: Record<string, string | number> = {}) => Object.entries(p).reduce((s, [a, b]) => s.replace(`:${a}`, String(b)), dict()[k] ?? k);
const esc = (s: string) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c] as string));

export function bootRepairPage(stage: Stage): void {
    const cfg = (window as unknown as { MP_REPAIR?: RepairCfg }).MP_REPAIR;
    const input = document.getElementById('repair-file') as HTMLInputElement | null;
    if (!cfg || !input) return;
    const $ = <T extends HTMLElement>(id: string) => document.getElementById(id) as T;
    const status = $('repair-status'); const result = $('repair-result'); const wait = $('repair-wait');
    const say = (k: string | null, p: Record<string, string | number> = {}) => { status.textContent = k ? tr(k, p) : ''; status.classList.toggle('hidden', !k); };
    const headers = { Accept: 'application/json' };
    const nf = new Intl.NumberFormat(document.documentElement.lang || 'cs');

    const show = async (file: FileInfo & { repair?: Report | null }): Promise<void> => {
        const r = file.repair;
        if (!r) return;
        $('repair-verdict').textContent = tr(`repair.verdict.${r.verdict}`);
        $('repair-verdict').className = `font-semibold ${r.verdict === 'repaired' || r.verdict === 'clean' ? 'text-ok' : 'text-warn'}`;
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
        $('repair-table').innerHTML = rows.map(([k, b, a, ok]) => `<tr class="border-t border-line"><td class="py-1 text-muted">${esc(tr(`repair.row.${k}`))}</td><td class="py-1">${esc(b)}</td><td class="py-1 font-semibold ${ok ? 'text-ok' : 'text-warn'}">${esc(a)}</td></tr>`).join('');
        const left = $('repair-left');
        const still = !r.after.watertight || r.after.flipped_normals;
        left.textContent = still ? tr('repair.left') : '';
        left.classList.toggle('hidden', !still);
        result.classList.remove('hidden'); wait.classList.add('hidden');
        // kind null: the repaired mesh is shown as it is, plain
        await stage.fileResult({ ...file, stl_url: `${cfg.files}/${file.uuid}/model.stl` }, { kind: null });
        stage.reveal('result');
    };

    const run = async (file: File): Promise<void> => {
        const ext = (file.name.split('.').pop() ?? '').toLowerCase();
        result.classList.add('hidden'); wait.classList.remove('hidden');
        if (!cfg.formats.includes(ext)) { say('repair.bad_format', { f: cfg.formats.join(', ').toUpperCase() }); return; }
        if (file.size > cfg.maxMb * 1024 * 1024) { say('repair.too_big', { max: cfg.maxMb }); return; }
        say('repair.uploading');
        stage.busy(true);
        try {
            const fd = new FormData(); fd.append('file', file);
            const up = await fetch(cfg.upload, { method: 'POST', credentials: 'same-origin', headers, body: fd });
            if (!up.ok) throw new Error('upload');
            say('repair.checking');
            const info = await stage.untilReady((await up.json()).file, 120);
            if (info.status !== 'ready') { say('repair.failed'); return; }
            say('repair.repairing');
            const res = await fetch(`${cfg.files}/${info.uuid}/repair`, { method: 'POST', credentials: 'same-origin', headers });
            if (res.status === 503) { say('repair.unavailable'); return; }
            if (!res.ok) { say('repair.failed'); return; }
            const fixed = await stage.untilReady((await res.json()).file, 120);
            if (fixed.status !== 'ready') { say('repair.failed'); return; }
            say(null);
            await show(fixed as FileInfo & { repair?: Report | null });
        } catch {
            say('repair.failed');
        } finally {
            stage.busy(false);
        }
    };

    stage.dropZone(document.getElementById('repair-drop'), input, (f) => { void run(f); input.value = ''; });
}
