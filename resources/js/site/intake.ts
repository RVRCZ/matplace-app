/**
 * The home page's router (TODO #78, docs/AN.md §22): a photo, a 3D file or words go to POST /intake, the assistant's
 * decision comes back and is shown as a short answer with its ways on: the tool with the photo, the calculator with
 * the file, or an e-mail field for a person to take it over. Texts come from the form's data-i18n (lang router.*);
 * the server's own sentences (422) are shown as they are.
 */
import { shrinkPhoto, overLimit } from '../calc/photo';

type Words = Record<string, string>;
interface Decision {
    token: string; route: 'tool' | 'shape' | 'human' | 'file' | null; tool_key: string | null; tool: string | null; summary: string;
    params: Record<string, unknown>; questions: string[]; wants_shape: boolean; has_photo: boolean; hours: number;
    file: { uuid: string; name: string } | null;
    actions: { tool: string | null; calc: string | null; request: string };
}

const csrf = (): string => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
const esc = (s: string): string => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c] as string));

export function bootIntake(): void {
    const form = document.getElementById('intake-form') as HTMLFormElement | null;
    if (!form) { noteFromAddress(); return; }
    let words: Words = {};
    try { words = JSON.parse(form.dataset.i18n ?? '{}') as Words; } catch { /* the keys stand in for the texts */ }
    const t = (k: string, r: Record<string, string | number> = {}): string => Object.entries(r).reduce((s, [a, b]) => s.split(`:${a}`).join(String(b)), words[k] ?? k);
    const maxMb = Number(form.dataset.maxMb) || 15;

    const photo = document.getElementById('intake-photo') as HTMLInputElement;
    const model = document.getElementById('intake-model') as HTMLInputElement;
    const drop = document.getElementById('intake-drop') as HTMLElement;
    const empty = document.getElementById('intake-empty') as HTMLElement;
    const preview = document.getElementById('intake-preview') as HTMLElement;
    const thumb = document.getElementById('intake-thumb') as HTMLImageElement;
    const fileName = document.getElementById('intake-file-name') as HTMLElement;
    const fileKind = document.getElementById('intake-file-kind') as HTMLElement;
    const text = document.getElementById('intake-text') as HTMLTextAreaElement;
    const submit = document.getElementById('intake-submit') as HTMLButtonElement;
    const hint = document.getElementById('intake-hint') as HTMLElement;
    const error = document.getElementById('intake-error') as HTMLElement;
    const assist = document.getElementById('intake-assist') as HTMLElement;
    let picked: { kind: 'photo' | 'model'; file: File } | null = null;

    const say = (message: string): void => { error.textContent = message; error.classList.toggle('hidden', message === ''); };
    const show = (kind: 'photo' | 'model', file: File): void => {
        picked = { kind, file };
        empty.classList.add('hidden'); preview.classList.remove('hidden'); preview.classList.add('flex');
        drop.classList.remove('border-dashed'); drop.classList.add('border-solid', 'bg-card');
        fileName.textContent = file.name;
        fileKind.textContent = t(kind === 'photo' ? 'router.kind.photo' : 'router.kind.file');
        if (thumb.src.startsWith('blob:')) URL.revokeObjectURL(thumb.src);
        if (kind === 'photo') { thumb.src = URL.createObjectURL(file); thumb.classList.remove('hidden'); } else { thumb.removeAttribute('src'); thumb.classList.add('hidden'); }
        say('');
    };
    const clear = (): void => {
        picked = null; photo.value = ''; model.value = '';
        empty.classList.remove('hidden'); preview.classList.add('hidden'); preview.classList.remove('flex');
        drop.classList.add('border-dashed'); drop.classList.remove('border-solid', 'bg-card');
        if (thumb.src.startsWith('blob:')) URL.revokeObjectURL(thumb.src);
        thumb.removeAttribute('src');
    };
    photo.addEventListener('change', () => { const f = photo.files?.[0]; if (f) { model.value = ''; show('photo', f); } });
    model.addEventListener('change', () => { const f = model.files?.[0]; if (f) { photo.value = ''; show('model', f); } });
    document.getElementById('intake-clear')?.addEventListener('click', clear);
    // a file dropped anywhere on the card: a picture is a photo, anything else a model
    form.addEventListener('dragover', (e) => { e.preventDefault(); drop.classList.add('border-ink'); });
    form.addEventListener('dragleave', () => drop.classList.remove('border-ink'));
    form.addEventListener('drop', (e) => {
        e.preventDefault(); drop.classList.remove('border-ink');
        const f = e.dataTransfer?.files?.[0];
        if (f) show(f.type.startsWith('image/') || /\.(heic|heif)$/i.test(f.name) ? 'photo' : 'model', f);
    });
    // the examples fill the words in; the visitor changes what differs
    form.querySelectorAll<HTMLButtonElement>('[data-fill]').forEach((chip) => chip.addEventListener('click', () => { text.value = chip.dataset.fill ?? ''; text.focus(); }));

    const busy = (on: boolean): void => {
        submit.disabled = on;
        submit.setAttribute('aria-busy', on ? 'true' : 'false');
        hint.textContent = on ? t('router.sending') : t('router.submit.hint');
    };

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        say('');
        const typed = text.value.trim();
        if (!picked && typed === '') { say(t('router.need_input')); text.focus(); return; }
        if (picked?.kind === 'photo' && overLimit(picked.file, maxMb)) { say(t('photo.too_big', { mb: maxMb })); return; }
        busy(true);
        const body = new FormData();
        if (typed !== '') body.append('text', typed);
        try {
            if (picked?.kind === 'photo') {
                // a phone's 4 000 px photo goes as 2 000 px (calc/photo.ts); what the browser cannot draw (HEIC) goes as it is
                const sent = await shrinkPhoto(picked.file);
                body.append('photo', sent, sent.name || 'photo.jpg');
            } else if (picked?.kind === 'model') {
                body.append('model', picked.file, picked.file.name);
            }
            const res = await fetch(form.dataset.url ?? '/intake', { method: 'POST', credentials: 'same-origin', body, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
            const answer = await res.json().catch(() => ({})) as Partial<Decision> & { message?: string; errors?: Record<string, string[]> };
            if (res.status === 429) { say(t('router.too_many')); return; }
            if (res.status === 422) { say(Object.values(answer.errors ?? {})[0]?.[0] ?? answer.message ?? t('router.error')); return; }
            if (!res.ok || !answer.token) { say(t('router.error')); return; }
            render(answer as Decision);
        } catch {
            say(t('router.error'));
        } finally {
            busy(false);
        }
    });

    // an answer handed in from outside (the review script, a test): shown like the server's
    form.addEventListener('intake:answer', (e) => render((e as CustomEvent<Decision>).detail));

    /** The assistant's answer: a tag, the sentence, the questions, the ways on. */
    function render(d: Decision): void {
        assist.innerHTML = '';
        assist.classList.remove('hidden');
        const tag = d.route === 'tool' ? t('router.tag.tool', { tool: d.tool ?? d.tool_key ?? '' }) : d.route === 'file' ? t('router.tag.file') : d.wants_shape ? t('router.tag.shape') : t('router.tag.human');
        const bubble = document.createElement('div');
        bubble.className = 'rounded-2xl bg-studio p-4';
        bubble.setAttribute('role', 'status');
        let html = `<p class="text-xs font-semibold uppercase tracking-wide text-muted">${esc(t('router.assistant'))} · <span class="rounded-full bg-ok-soft px-2 py-0.5 text-ok normal-case tracking-normal">${esc(tag)}</span></p>`;
        html += `<p class="mt-2 text-ink">${esc(d.summary)}</p>`;
        if (d.questions?.length) html += `<p class="mt-2 text-sm text-muted">${esc(t('router.questions'))} ${d.questions.map(esc).join(' · ')}</p>`;
        bubble.innerHTML = html;
        const acts = document.createElement('div');
        acts.className = 'mt-3 flex flex-wrap gap-2';
        if (d.route === 'tool' && d.actions.tool) {
            acts.appendChild(link(d.actions.tool, t(d.has_photo ? 'router.act.open_tool' : 'router.act.open_tool_text'), true));
            acts.appendChild(button(t('router.act.rather_human'), false, () => human(d, bubble)));
        } else if (d.route === 'file' && d.actions.calc) {
            acts.appendChild(link(d.actions.calc, t('router.act.open_calc'), true));
        }
        if (acts.children.length) bubble.appendChild(acts);
        assist.appendChild(bubble);
        if (d.route !== 'tool' && d.route !== 'file') human(d, bubble);
        bubble.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    /** "A person takes it over": the e-mail, the consent, one post. */
    function human(d: Decision, after: HTMLElement): void {
        if (assist.querySelector('#intake-request')) return;
        const box = document.createElement('form');
        box.id = 'intake-request';
        box.className = 'mt-3 grid gap-3 rounded-2xl border border-line bg-card p-4';
        box.noValidate = true;
        box.innerHTML = `
            <div><p class="font-semibold text-ink">${esc(t('router.request.title'))}</p><p class="text-sm text-muted">${esc(t('router.request.lead', { hours: d.hours }))}</p></div>
            <label class="block text-sm font-semibold text-ink">${esc(words['auth.email'] ?? 'E-mail')}<input name="email" type="email" required autocomplete="email" inputmode="email" class="field"></label>
            <label class="flex items-start gap-3 text-sm text-ink"><input name="consent" type="checkbox" value="1" class="mt-0.5 h-5 w-5 shrink-0 accent-action"><span>${esc(t('router.request.consent'))}</span></label>
            <p data-request-error class="hidden rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger" role="alert"></p>
            <button type="submit" class="btn-primary w-full sm:w-auto">${esc(t('router.act.send_request'))}</button>`;
        after.insertAdjacentElement('afterend', box);
        const err = box.querySelector<HTMLElement>('[data-request-error]')!;
        const tell = (m: string): void => { err.textContent = m; err.classList.toggle('hidden', m === ''); };
        box.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = (box.querySelector<HTMLInputElement>('[name=email]')!).value.trim();
            const consent = (box.querySelector<HTMLInputElement>('[name=consent]')!).checked;
            if (!email || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) { tell(t('router.request.need_email')); return; }
            if (!consent) { tell(t('router.request.need_consent')); return; }
            tell('');
            const go = box.querySelector<HTMLButtonElement>('button[type=submit]')!;
            go.disabled = true; go.textContent = t('router.act.sending');
            try {
                const res = await fetch(d.actions.request, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ email, consent: true }) });
                const answer = await res.json().catch(() => ({})) as { message?: string; errors?: Record<string, string[]> };
                if (!res.ok) { tell(Object.values(answer.errors ?? {})[0]?.[0] ?? answer.message ?? t('router.request.failed')); go.disabled = false; go.textContent = t('router.act.send_request'); return; }
                box.innerHTML = `<p class="note-ok" role="status">${esc(answer.message ?? t('router.request.sent', { hours: d.hours, email }))}</p>`;
            } catch {
                tell(t('router.request.failed')); go.disabled = false; go.textContent = t('router.act.send_request');
            }
        });
        box.querySelector<HTMLInputElement>('[name=email]')?.focus();
    }

    function link(href: string, label: string, primary: boolean): HTMLAnchorElement {
        const a = document.createElement('a');
        a.href = href; a.textContent = label; a.className = primary ? 'btn-primary' : 'btn-secondary';
        return a;
    }
    function button(label: string, primary: boolean, run: () => void): HTMLButtonElement {
        const b = document.createElement('button');
        b.type = 'button'; b.textContent = label; b.className = primary ? 'btn-primary' : 'btn-secondary';
        b.addEventListener('click', run);
        return b;
    }

    noteFromAddress();
}

/**
 * The calculator opened with the router's file (`?open=<uuid>&note=<words>`, docs/AN.md §22 F): the words show under
 * the price as "your note" (the order's page gets them from the server). Read before the calculator cleans the address.
 */
function noteFromAddress(): void {
    const q = new URLSearchParams(location.search);
    const note = q.get('note');
    const box = document.getElementById('calc-note');
    if (!note || !q.get('open') || !box) return;
    const text = box.querySelector<HTMLElement>('[data-note-text]');
    if (text) text.textContent = note.slice(0, 2000);
    box.classList.remove('hidden');
}
