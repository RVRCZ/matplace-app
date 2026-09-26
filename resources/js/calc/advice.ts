/**
 * "How do I print this?" under the model check: on request, the server measures the model and an AI turns the numbers
 * into a few plain tips (orientation, supports, adhesion, thin walls, strength). One question per model and settings.
 */
interface AdviceItem { topic: string; level: 'important' | 'tip' | 'fine'; title: string; text: string }
interface AdviceState { token: string; status: 'running' | 'done' | 'failed'; advice: { summary: string; items: AdviceItem[] } | null }

const dict = () => (window as unknown as { MP_I18N?: Record<string, string> }).MP_I18N ?? {};
const tr = (k: string) => dict()[k] ?? k;
const esc = (s: string) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c] as string));
const base = () => (window as unknown as { MP_ROUTES?: { files: string } }).MP_ROUTES?.files ?? '/api/files';

let shownFor = '';

export function renderAdvice(box: HTMLElement | null, fileUuid: string | undefined, calcToken: string | undefined): void {
    if (!box || !fileUuid) return;
    const key = `${fileUuid}:${calcToken ?? ''}`;
    if (shownFor === key) return;          // a recalculation with the same settings keeps what is shown
    shownFor = key;
    box.innerHTML = `<div class="mt-3 rounded-xl border border-line bg-white p-3">
        <p class="font-bold text-ink">${esc(tr('advice.title'))}</p>
        <p class="mt-1 text-xs text-muted">${esc(tr('advice.lead'))}</p>
        <button type="button" data-advice-go class="btn-quiet mt-2 !min-h-0 !py-1.5 text-sm">✨ ${esc(tr('advice.button'))}</button>
        <div data-advice-out class="mt-2" aria-live="polite"></div>
    </div>`;
    const go = box.querySelector<HTMLButtonElement>('[data-advice-go]')!;
    const out = box.querySelector<HTMLElement>('[data-advice-out]')!;
    const say = (t: string, cls = 'text-muted') => { out.innerHTML = `<p class="text-sm ${cls}">${esc(t)}</p>`; };

    const show = (s: AdviceState) => {
        if (s.status === 'failed') { say(tr('advice.failed'), 'text-red-800'); go.disabled = false; return true; }
        if (s.status !== 'done' || !s.advice) return false;
        const badge = { important: 'bg-red-50 text-red-800', tip: 'bg-amber-50 text-amber-900', fine: 'bg-emerald-50 text-emerald-800' };
        out.innerHTML = `<p class="text-sm font-semibold text-ink">${esc(s.advice.summary)}</p>
            <ul class="mt-2 space-y-2">${s.advice.items.map((i) => `<li class="text-sm"><span class="mr-1 rounded px-1.5 py-0.5 text-[11px] font-semibold ${badge[i.level] ?? ''}">${esc(tr(`advice.level.${i.level}`))}</span><span class="font-semibold text-ink">${esc(i.title)}</span><br><span class="text-muted">${esc(i.text)}</span></li>`).join('')}</ul>
            <p class="mt-2 text-xs text-muted">${esc(tr('advice.disclaimer'))}</p>`;
        go.hidden = true;
        return true;
    };

    go.addEventListener('click', async () => {
        go.disabled = true;
        say(tr('advice.working'));
        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
        try {
            const r = await fetch(`${base()}/${fileUuid}/advice`, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ calculation: calcToken ?? null }),
            });
            const j = await r.json().catch(() => ({}));
            if (r.status === 429) { say(tr('advice.daily_limit'), 'text-amber-900'); return; }
            if (!r.ok) { say(tr(r.status === 503 ? 'advice.unavailable' : 'advice.failed'), 'text-red-800'); go.disabled = false; return; }
            if (show(j as AdviceState)) return;
            const started = Date.now();
            const tick = async () => {
                if (shownFor !== key) return;                       // another model opened meanwhile
                const s = await fetch(`/api/advice/${(j as AdviceState).token}`, { headers: { Accept: 'application/json' } }).then((x) => x.json()).catch(() => null);
                if (s && show(s as AdviceState)) return;
                if (Date.now() - started > 240000) { say(tr('advice.failed'), 'text-red-800'); go.disabled = false; return; }
                setTimeout(tick, 3000);
            };
            setTimeout(tick, 3000);
        } catch {
            say(tr('advice.failed'), 'text-red-800');
            go.disabled = false;
        }
    });
}
