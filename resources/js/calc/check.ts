/**
 * Model check before printing: the same report on the calculator and on the "Check my model" page.
 * Errors and advice are kept apart; every line says what it means for the print. It never promises printability.
 */
import { Viewer } from './viewer';
import { loadGeometryFromUrl } from './loaders';

export interface CheckItem { level: 'error' | 'advice' | 'ok'; code: string; params: Record<string, string> }
export interface CheckReport { status: string; items: CheckItem[] }

const dict = () => (window as unknown as { MP_I18N?: Record<string, string> }).MP_I18N ?? {};
const tr = (k: string, p: Record<string, string> = {}) => Object.entries(p).reduce((s, [a, b]) => s.replace(`:${a}`, b), dict()[k] ?? k);
const esc = (s: string) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c] as string));

export function renderCheck(box: HTMLElement | null, report: CheckReport | null | undefined): void {
    if (!box) return;
    if (!report || report.status === 'pending' || !report.items.length) { box.classList.add('hidden'); box.innerHTML = ''; return; }
    const group = (level: CheckItem['level'], title: string, cls: string, icon: string) => {
        const items = report.items.filter((i) => i.level === level);
        if (!items.length) return '';
        return `<div class="mt-3"><h3 class="text-sm font-bold ${cls}">${icon} ${esc(title)}</h3><ul class="mt-1 space-y-2">${items.map((i) => `<li class="text-sm"><span class="font-semibold text-ink">${esc(tr(`check.${i.code}`, i.params))}</span>${level === 'ok' ? '' : `<br><span class="text-muted">${esc(tr(`check.${i.code}.impact`, i.params))}</span>`}</li>`).join('')}</ul></div>`;
    };
    const head = { error: ['check.head.error', 'text-red-800'], advice: ['check.head.advice', 'text-amber-800'], ok: ['check.head.ok', 'text-ok'] }[report.status as 'error' | 'advice' | 'ok'] ?? ['check.head.ok', 'text-ok'];
    box.innerHTML = `<h2 class="font-bold ${head[1]}">${esc(tr(head[0]))}</h2>`
        + group('error', tr('check.group.error'), 'text-red-800', '✕')
        + group('advice', tr('check.group.advice'), 'text-amber-800', '!')
        + group('ok', tr('check.group.ok'), 'text-ok', '✓')
        + `<p class="mt-3 text-xs text-muted">${esc(tr('check.disclaimer'))}</p>`;
    box.classList.remove('hidden');
}

interface CheckCfg { upload: string; files: string; home: string; formats: string[]; maxMb: number }

/** /tools/check: upload → wait for processing → viewer + report → on to the price. */
export function bootCheckPage(): void {
    const cfg = (window as unknown as { MP_CHECK?: CheckCfg }).MP_CHECK;
    const input = document.getElementById('check-file') as HTMLInputElement | null;
    if (!cfg || !input) return;
    const status = document.getElementById('check-status') as HTMLElement;
    const result = document.getElementById('check-result') as HTMLElement;
    const go = document.getElementById('check-go') as HTMLAnchorElement;
    const canvas = document.getElementById('check-viewer') as HTMLCanvasElement;
    let viewer: Viewer | null = null;
    const say = (k: string | null, p: Record<string, string> = {}) => { status.textContent = k ? tr(k, p) : ''; status.classList.toggle('hidden', !k); };

    const run = async (file: File): Promise<void> => {
        const ext = (file.name.split('.').pop() ?? '').toLowerCase();
        result.classList.add('hidden');
        if (!cfg.formats.includes(ext)) { say('check.page.bad_format', { f: cfg.formats.join(', ').toUpperCase() }); return; }
        if (file.size > cfg.maxMb * 1024 * 1024) { say('check.page.too_big', { max: String(cfg.maxMb) }); return; }
        say('check.page.uploading');
        try {
            const fd = new FormData(); fd.append('file', file);
            const up = await fetch(cfg.upload, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body: fd });
            if (!up.ok) throw new Error('upload');
            let info = (await up.json()).file;
            say('check.page.checking');
            for (let i = 0; i < 80 && info.status !== 'ready' && info.status !== 'failed'; i++) {
                await new Promise((r) => setTimeout(r, 1500));
                info = (await (await fetch(`${cfg.files}/${info.uuid}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })).json()).file;
            }
            if (info.status !== 'ready') { say('check.page.failed'); return; }
            say(null);
            result.classList.remove('hidden');
            renderCheck(document.getElementById('check-report'), info.check);
            go.href = `${cfg.home}?open=${info.uuid}`;
            if (info.stl_url) {
                viewer = viewer ?? new Viewer(canvas);
                viewer.setGeometry(await loadGeometryFromUrl(info.stl_url), 1, info.kind ?? null);
            }
            result.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch {
            say('check.page.failed');
        }
    };
    input.onchange = () => { if (input.files?.[0]) run(input.files[0]); };
    const drop = document.getElementById('check-drop');
    if (drop) {
        drop.ondragover = (e) => { e.preventDefault(); drop.classList.add('border-action'); };
        drop.ondragleave = () => drop.classList.remove('border-action');
        drop.ondrop = (e) => { e.preventDefault(); drop.classList.remove('border-action'); const f = e.dataTransfer?.files?.[0]; if (f) run(f); };
    }
}
