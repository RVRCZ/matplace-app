/**
 * Model check before printing: the same report on the calculator and on the "Check my model" page.
 * Errors and advice are kept apart; every line says what it means for the print. It never promises printability.
 */
import type { Stage } from './tool_page';

export interface CheckItem { level: 'error' | 'advice' | 'ok'; code: string; params: Record<string, string> }
export interface CheckReport { status: string; items: CheckItem[] }

const dict = () => (window as unknown as { MP_I18N?: Record<string, string> }).MP_I18N ?? {};
const tr = (k: string, p: Record<string, string> = {}) => Object.entries(p).reduce((s, [a, b]) => s.replace(`:${a}`, b), dict()[k] ?? k);
const esc = (s: string) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c] as string));
const icon = (name: string): string => `<svg class="inline-block h-4 w-4 shrink-0 align-[-3px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-${name}"/></svg>`;

export function renderCheck(box: HTMLElement | null, report: CheckReport | null | undefined): void {
    if (!box) return;
    if (!report || report.status === 'pending' || !report.items.length) { box.classList.add('hidden'); box.innerHTML = ''; return; }
    const group = (level: CheckItem['level'], title: string, cls: string, glyph: string) => {
        const items = report.items.filter((i) => i.level === level);
        if (!items.length) return '';
        return `<div class="mt-3"><h3 class="text-sm font-bold ${cls}">${icon(glyph)} ${esc(title)}</h3><ul class="mt-1 space-y-2">${items.map((i) => `<li class="text-sm"><span class="font-semibold text-ink">${esc(tr(`check.${i.code}`, i.params))}</span>${level === 'ok' ? '' : `<br><span class="text-muted">${esc(tr(`check.${i.code}.impact`, i.params))}</span>`}</li>`).join('')}</ul></div>`;
    };
    const head = { error: ['check.head.error', 'text-red-800'], advice: ['check.head.advice', 'text-amber-800'], ok: ['check.head.ok', 'text-ok'] }[report.status as 'error' | 'advice' | 'ok'] ?? ['check.head.ok', 'text-ok'];
    box.innerHTML = `<h2 class="font-bold ${head[1]}">${esc(tr(head[0]))}</h2>`
        + group('error', tr('check.group.error'), 'text-red-800', 'x')
        + group('advice', tr('check.group.advice'), 'text-amber-800', 'triangle-alert')
        + group('ok', tr('check.group.ok'), 'text-ok', 'check')
        + `<p class="mt-3 text-xs text-muted">${esc(tr('check.disclaimer'))}</p>`;
    box.classList.remove('hidden');
}

interface CheckCfg { upload: string; files: string; home: string; formats: string[]; maxMb: number }

/** /tools/check as a module of the tool page: upload → wait for processing → the model on the stage + the report. */
export function bootCheckPage(stage: Stage): void {
    const cfg = (window as unknown as { MP_CHECK?: CheckCfg }).MP_CHECK;
    const input = document.getElementById('check-file') as HTMLInputElement | null;
    if (!cfg || !input) return;
    const status = document.getElementById('check-status') as HTMLElement;
    const result = document.getElementById('check-result') as HTMLElement;
    const wait = document.getElementById('check-wait') as HTMLElement;
    const say = (k: string | null, p: Record<string, string> = {}) => { status.textContent = k ? tr(k, p) : ''; status.classList.toggle('hidden', !k); };

    const run = async (file: File): Promise<void> => {
        const ext = (file.name.split('.').pop() ?? '').toLowerCase();
        result.classList.add('hidden'); wait.classList.remove('hidden');
        if (!cfg.formats.includes(ext)) { say('check.page.bad_format', { f: cfg.formats.join(', ').toUpperCase() }); return; }
        if (file.size > cfg.maxMb * 1024 * 1024) { say('check.page.too_big', { max: String(cfg.maxMb) }); return; }
        say('check.page.uploading');
        stage.busy(true);
        try {
            const fd = new FormData(); fd.append('file', file);
            const up = await fetch(cfg.upload, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body: fd });
            if (!up.ok) throw new Error('upload');
            say('check.page.checking');
            const info = await stage.untilReady((await up.json()).file, 80);
            if (info.status !== 'ready') { say('check.page.failed'); return; }
            say(null);
            result.classList.remove('hidden'); wait.classList.add('hidden');
            renderCheck(document.getElementById('check-report'), info.check);
            await stage.fileResult(info);
            stage.reveal('result');
        } catch {
            say('check.page.failed');
        } finally {
            stage.busy(false);
        }
    };
    stage.dropZone(document.getElementById('check-drop'), input, (f) => { void run(f); });
}
