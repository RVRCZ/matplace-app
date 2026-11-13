/**
 * The home page's router (TODO #78, docs/AN.md §22): a photo, a 3D file or words go to POST /intake, the assistant's
 * decision comes back and is shown as a short answer with its ways on: the tool with the photo, the calculator with
 * the file, or an e-mail field for a person to take it over. Texts come from the form's data-i18n (lang router.*);
 * the server's own sentences (422) are shown as they are.
 *
 * The first screen "Co chcete vyrobit?" (docs/AN.md §23): the field for words comes first; a photo is added from the
 * field's own row, so is dictation where the browser can do it; the examples under the field write a sample wish into
 * it (nothing is sent). What the visitor did before sending is counted (POST /intake/event: the page shown, an example
 * clicked, a photo added, dictation used). The form carries the page's stamp and an unseen field against robots.
 *
 * One press ends on a design (docs/AN.md §24): the answer's `go` is the page that opens by itself, with no card in
 * between: the tool with the wish in its form, the free design in the calculator, the calculator with the file. A
 * card is shown only where there is nothing to open (a part for a person, a held wish, the free designs used up) and
 * on the way back from a tool or a design (`/?wish=<token>`, "I wanted something else"): the words are in the field
 * again, under it the other tools, "try a free design" and, as the visitor's own choice, the request to a designer.
 */
import { shrinkPhoto, overLimit } from '../calc/photo';

type Words = Record<string, string>;
interface Decision {
    token: string; route: 'tool' | 'shape' | 'human' | 'file' | 'design' | null; held?: 'held' | 'price' | null; tool_key: string | null; tool: string | null; summary: string;
    params: Record<string, unknown>; questions: string[]; alternatives?: string[]; wants_shape: boolean; has_photo: boolean; rerouted?: boolean; hours: number;
    file: { uuid: string; name: string } | null;
    // one press ends on a design (docs/AN.md §24): `go` is the page that opens by itself (the tool with the wish filled in, the
    // free design, the calculator with the file); `again` marks the way back ("I wanted something else"), with the words and
    // the other ways: the tools that would also do, a free design, a request to a person
    go?: string | null; again?: boolean; text?: string | null; unsure?: boolean; others?: { key: string; title: string; url: string }[]; design_note?: string | null;
    // the free designs have counts (docs/AN.md §24.4): `login` = a visitor without an account used theirs, signing in gives more;
    // `pay_design` = an account used its free ones and a price is set: the same press, paid from the credit, the button names the price
    login?: string | null;
    actions: { tool: string | null; calc: string | null; request: string | null; reroute?: string | null; design?: string | null; start_design?: string | null;
        pay_design?: { url: string; price: string; topup: string | null } | null };
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
    const box = document.getElementById('intake-box') as HTMLElement;
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
    // the example the visitor clicked last (its code and its words), and whether they were already told to change it
    let example: { code: string; fill: string } | null = null;
    let nudged = false;

    /** What the visitor did before sending, for our own numbers: the type and a short code, never a text (docs/AN.md §23.4). */
    const told = new Set<string>();
    const track = (type: 'view' | 'chip' | 'photo' | 'voice' | 'again', code = '', once = true): void => {
        const key = `${type}:${code}`;
        if (once && told.has(key)) return;
        told.add(key);
        const url = form.dataset.eventUrl;
        if (!url) return;
        void fetch(url, { method: 'POST', credentials: 'same-origin', keepalive: true, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ type, code }) }).catch(() => undefined);
    };
    // the first screen was shown (not the calculator opened with a file of the address)
    if (!new URLSearchParams(location.search).has('open')) track('view');

    const say = (message: string): void => { error.textContent = message; error.classList.toggle('hidden', message === ''); };
    const show = (kind: 'photo' | 'model', file: File): void => {
        picked = { kind, file };
        preview.classList.remove('hidden'); preview.classList.add('flex');
        fileName.textContent = file.name;
        fileKind.textContent = t(kind === 'photo' ? 'router.kind.photo' : 'router.kind.file');
        if (thumb.src.startsWith('blob:')) URL.revokeObjectURL(thumb.src);
        if (kind === 'photo') { thumb.src = URL.createObjectURL(file); thumb.classList.remove('hidden'); track('photo'); } else { thumb.removeAttribute('src'); thumb.classList.add('hidden'); }
        say('');
    };
    const clear = (): void => {
        picked = null; photo.value = ''; model.value = '';
        preview.classList.add('hidden'); preview.classList.remove('flex');
        if (thumb.src.startsWith('blob:')) URL.revokeObjectURL(thumb.src);
        thumb.removeAttribute('src');
    };
    photo.addEventListener('change', () => { const f = photo.files?.[0]; if (f) { model.value = ''; show('photo', f); } });
    model.addEventListener('change', () => { const f = model.files?.[0]; if (f) { photo.value = ''; show('model', f); } });
    document.getElementById('intake-clear')?.addEventListener('click', clear);
    // a file dropped anywhere on the card: a picture is a photo, anything else a model
    form.addEventListener('dragover', (e) => { e.preventDefault(); box.classList.add('border-action'); });
    form.addEventListener('dragleave', () => box.classList.remove('border-action'));
    form.addEventListener('drop', (e) => {
        e.preventDefault(); box.classList.remove('border-action');
        const f = e.dataTransfer?.files?.[0];
        if (f) show(f.type.startsWith('image/') || /\.(heic|heif)$/i.test(f.name) ? 'photo' : 'model', f);
    });
    // the examples fill the words in; the visitor changes what differs
    form.querySelectorAll<HTMLButtonElement>('[data-fill]').forEach((chip) => chip.addEventListener('click', () => {
        text.value = chip.dataset.fill ?? '';
        example = chip.dataset.hint ? { code: chip.dataset.hint, fill: text.value } : null;
        nudged = false;
        hint.textContent = t('router.submit.hint'); hint.classList.remove('font-semibold', 'text-ink');
        text.focus();
        if (chip.dataset.hint) track('chip', chip.dataset.hint, false);
    }));
    dictation();

    const busy = (on: boolean): void => {
        submit.disabled = on;
        submit.setAttribute('aria-busy', on ? 'true' : 'false');
        hint.textContent = on ? t('router.sending') : t('router.submit.hint');
    };
    /** The design opens by itself: the tool with the wish filled in, the free design, the calculator with the file. The button stays busy until the page goes. */
    let leaving = false;
    const open = (d: Decision): boolean => {
        if (!d.go) return false;
        leaving = true;
        submit.disabled = true; submit.setAttribute('aria-busy', 'true');
        hint.textContent = d.route === 'design' ? t('router.opening.design') : d.route === 'file' ? t('router.opening.file') : t('router.opening', { tool: d.tool ?? '' });
        hint.classList.add('font-semibold', 'text-ink');
        location.assign(d.go);
        return true;
    };
    // back from the page that opened (the browser's own Back keeps this page as it was): the form works again
    window.addEventListener('pageshow', (e) => { if (e.persisted && leaving) { leaving = false; hint.classList.remove('font-semibold', 'text-ink'); busy(false); } });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        say('');
        const typed = text.value.trim();
        if (!picked && typed === '') { say(t('router.need_input')); text.focus(); return; }
        // an example sent as it stands: asked once to make it one's own (a name, a size, the colours); the second press sends it
        if (!picked && example && typed === example.fill.trim() && !nudged) {
            nudged = true;
            hint.textContent = t('router.example_nudge'); hint.classList.add('font-semibold', 'text-ink');
            text.focus();
            return;
        }
        hint.classList.remove('font-semibold', 'text-ink');
        if (picked?.kind === 'photo' && overLimit(picked.file, maxMb)) { say(t('photo.too_big', { mb: maxMb })); return; }
        busy(true);
        const body = new FormData();
        if (typed !== '') body.append('text', typed);
        // against robots (IntakeSpam): the stamp of the page and the field no person fills, as they are in the form
        body.append('started', (document.getElementById('intake-started') as HTMLInputElement | null)?.value ?? '');
        const trap = document.getElementById('intake-trap') as HTMLInputElement | null;
        if (trap) body.append(trap.name, trap.value);
        if (example) body.append('hint', example.code);
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
            // no card in between: the design opens; a card only where there is nothing to open (a person, a held wish)
            if (open(answer as Decision)) return;
            render(answer as Decision);
        } catch {
            say(t('router.error'));
        } finally {
            if (!leaving) busy(false);
        }
    });

    // the way back from a tool or a design ("I wanted something else", `/?wish=<token>`): the words are in the field again,
    // under it the other ways: the tools that would also do, a free design, a request to a designer
    const wish = new URLSearchParams(location.search);
    if (/^[A-Za-z0-9]{24}$/.test(wish.get('wish') ?? '') && !wish.has('design') && form.dataset.showUrl) {
        void fetch(form.dataset.showUrl.replace('__TOKEN__', wish.get('wish')!), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : null))
            .then((d: Decision | null) => {
                if (!d?.token) return;
                if (d.text && text.value.trim() === '') text.value = d.text;
                render(d);
                track('again');
            })
            .catch(() => undefined);
        history.replaceState(null, '', location.pathname);
    }

    // an answer handed in from outside (the review script, a test): shown like the server's
    form.addEventListener('intake:answer', (e) => render((e as CustomEvent<Decision>).detail));

    /** The assistant's answer: a tag, the sentence, the questions, the ways on. */
    function render(d: Decision): void {
        assist.innerHTML = '';
        assist.classList.remove('hidden');
        const tag = d.held ? t(d.held === 'price' ? 'router.tag.price' : 'router.tag.held')
            : d.again ? t('router.tag.again')
                : d.route === 'tool' ? t('router.tag.tool', { tool: d.tool ?? d.tool_key ?? '' }) : d.route === 'file' ? t('router.tag.file') : d.route === 'design' ? t('router.tag.free')
                    : d.wants_shape ? t(d.has_photo ? 'router.tag.shape' : 'router.tag.design') : t('router.tag.human');
        const bubble = document.createElement('div');
        bubble.className = 'rounded-2xl bg-studio p-4';
        bubble.setAttribute('role', 'status');
        let html = `<p class="text-xs font-semibold uppercase tracking-wide text-muted">${esc(t('router.assistant'))} · <span class="rounded-full bg-ok-soft px-2 py-0.5 text-ok normal-case tracking-normal">${esc(tag)}</span></p>`;
        html += `<p class="mt-2 text-ink">${esc(d.again ? t('router.again.lead') : d.summary)}</p>`;
        if (d.design_note) html += `<p class="mt-2 text-sm text-ink" data-design-note>${esc(d.design_note)}</p>`;
        if (d.questions?.length && !d.again) html += `<p class="mt-2 text-sm text-muted">${esc(t('router.questions'))} ${d.questions.map(esc).join(' · ')}</p>`;
        html += '<p data-assist-error class="mt-2 hidden rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger" role="alert"></p>';
        bubble.innerHTML = html;
        const acts = document.createElement('div');
        acts.className = 'mt-3 flex flex-wrap gap-2';
        // the ways on: the tool or the file where the page did not open it by itself, the other tools that would do, the free
        // design (the one on its way, or the press that starts one), and a person as the visitor's own choice
        if (d.route === 'tool' && d.actions.tool && !d.again) acts.appendChild(link(d.actions.tool, t(d.has_photo ? 'router.act.open_tool' : 'router.act.open_tool_text'), true));
        if (d.route === 'file' && d.actions.calc) acts.appendChild(link(d.actions.calc, t('router.act.open_calc'), true));
        (d.others ?? []).forEach((o) => { const a = link(o.url, o.title, false); a.dataset.other = o.key; acts.appendChild(a); });
        if (d.actions.design) acts.appendChild(link(d.actions.design, t('router.act.open_design'), !d.again));
        if (d.actions.start_design) {
            const b = button(t('router.act.free_design'), false, () => { void startDesign(d, bubble, b, d.actions.start_design!, false); });
            b.dataset.startDesign = '1';
            acts.appendChild(b);
        }
        if (d.actions.pay_design) {
            const paid = d.actions.pay_design;
            const b = button(t('router.act.pay_design', { price: paid.price }), false, () => { void startDesign(d, bubble, b, paid.url, true); });
            b.dataset.payDesign = '1';
            acts.appendChild(b);
            if (paid.topup) acts.appendChild(link(paid.topup, t('router.act.topup'), false));
        }
        if (d.login) { const a = link(d.login, t('router.act.login'), false); a.dataset.login = '1'; acts.appendChild(a); }
        if ((d.again || d.route === 'tool' || d.route === 'design') && d.actions.request) acts.appendChild(button(t('router.act.rather_human'), false, () => human(d, bubble)));
        if (acts.children.length) bubble.appendChild(acts);
        if (d.actions.start_design) bubble.insertAdjacentHTML('beforeend', `<p class="mt-2 text-xs text-muted">${esc(t('router.design.honest'))}</p>`);
        assist.appendChild(bubble);
        // a wish the rules held (an advertisement, a bare question about the price): the sentence alone, the field stays for another try
        // a person's own work (a part with dimensions, a wish nothing could be made of): the e-mail field right away
        if (!d.held && !d.again && d.route !== 'tool' && d.route !== 'file' && d.route !== 'design') human(d, bubble);
        bubble.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    /** "Try a free design": the generator is started by the visitor's own press; the design then opens like any other. */
    async function startDesign(d: Decision, bubble: HTMLElement, b: HTMLButtonElement, url: string, pay: boolean): Promise<void> {
        const err = bubble.querySelector<HTMLElement>('[data-assist-error]');
        const tell = (m: string): void => { if (err) { err.textContent = m; err.classList.toggle('hidden', m === ''); } };
        const label = b.textContent ?? '';
        tell('');
        b.disabled = true; b.textContent = t('router.design.starting'); b.setAttribute('aria-busy', 'true');
        try {
            const res = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ pay }) });
            const answer = await res.json().catch(() => ({})) as Partial<Decision> & { message?: string; errors?: Record<string, string[]> };
            if (res.status === 429) tell(t('router.too_many'));
            else if (!res.ok || !answer.token) tell(Object.values(answer.errors ?? {})[0]?.[0] ?? answer.message ?? t('router.error'));
            else if (open(answer as Decision)) return;
            else render(answer as Decision);
        } catch {
            tell(t('router.error'));
        }
        b.disabled = false; b.textContent = label; b.removeAttribute('aria-busy');
    }

    /**
     * "A person takes it over": the e-mail, the consent, one post. Next to it, where a person was offered, "try it now
     * without a designer": the decision is made again with a person ruled out and the bubble is replaced (round 2).
     */
    function human(d: Decision, after: HTMLElement): void {
        if (assist.querySelector('#intake-request') || !d.actions.request) return;
        const requestUrl = d.actions.request;
        const box = document.createElement('form');
        box.id = 'intake-request';
        box.className = 'mt-3 grid gap-3 rounded-2xl border border-line bg-card p-4';
        box.noValidate = true;
        const again = d.actions.reroute && !d.rerouted ? `<button type="button" data-reroute class="btn-secondary w-full sm:w-auto">${esc(t('router.act.without_designer'))}</button>` : '';
        box.innerHTML = `
            <div><p class="font-semibold text-ink">${esc(t('router.request.title'))}</p><p class="text-sm text-muted">${esc(t('router.request.lead', { hours: d.hours }))}</p></div>
            <label class="block text-sm font-semibold text-ink">${esc(words['auth.email'] ?? 'E-mail')}<input name="email" type="email" required autocomplete="email" inputmode="email" class="field"></label>
            <label class="flex items-start gap-3 text-sm text-ink"><input name="consent" type="checkbox" value="1" class="mt-0.5 h-5 w-5 shrink-0 accent-action"><span>${esc(t('router.request.consent'))}</span></label>
            <p data-request-error class="hidden rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger" role="alert"></p>
            <div class="flex flex-wrap gap-2"><button type="submit" class="btn-primary w-full sm:w-auto">${esc(t('router.act.send_request'))}</button>${again}</div>`;
        after.insertAdjacentElement('afterend', box);
        const err = box.querySelector<HTMLElement>('[data-request-error]')!;
        const tell = (m: string): void => { err.textContent = m; err.classList.toggle('hidden', m === ''); };
        box.querySelector<HTMLButtonElement>('[data-reroute]')?.addEventListener('click', async (e) => {
            const b = e.currentTarget as HTMLButtonElement;
            const go = box.querySelector<HTMLButtonElement>('button[type=submit]')!;
            tell('');
            b.disabled = true; go.disabled = true; b.textContent = t('router.act.rerouting'); b.setAttribute('aria-busy', 'true');
            try {
                const res = await fetch(d.actions.reroute!, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
                const answer = await res.json().catch(() => ({})) as Partial<Decision> & { message?: string; errors?: Record<string, string[]> };
                if (res.status === 429) { tell(t('router.too_many')); }
                else if (!res.ok || !answer.token) { tell(Object.values(answer.errors ?? {})[0]?.[0] ?? answer.message ?? t('router.error')); }
                else { render(answer as Decision); return; }
            } catch {
                tell(t('router.error'));
            }
            b.disabled = false; go.disabled = false; b.textContent = t('router.act.without_designer'); b.removeAttribute('aria-busy');
        });
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
                const res = await fetch(requestUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ email, consent: true }) });
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

    /**
     * Dictation where the browser can do it (SpeechRecognition; Chrome, Edge, Safari): the button appears, a press
     * starts listening in the page's language, what is heard is added to the field, another press (or silence) ends it.
     */
    function dictation(): void {
        const mic = document.getElementById('intake-mic') as HTMLButtonElement | null;
        const w = window as unknown as { SpeechRecognition?: new () => Recognition; webkitSpeechRecognition?: new () => Recognition };
        const Ctor = w.SpeechRecognition ?? w.webkitSpeechRecognition;
        if (!mic || !Ctor) return;
        mic.classList.remove('hidden'); mic.classList.add('inline-flex');
        const label = mic.querySelector<HTMLElement>('[data-mic-label]');
        let rec: Recognition | null = null;
        let base = '';
        const off = (): void => {
            rec = null;
            mic.setAttribute('aria-pressed', 'false'); mic.classList.remove('bg-action-soft', 'text-action-dark');
            if (label) label.textContent = t('router.mic');
        };
        mic.addEventListener('click', () => {
            if (rec) { rec.stop(); return; }
            try {
                rec = new Ctor();
                rec.lang = ({ cs: 'cs-CZ', en: 'en-US', es: 'es-ES' } as Record<string, string>)[document.documentElement.lang.slice(0, 2)] ?? 'cs-CZ';
                rec.interimResults = true;
                rec.continuous = false;
                base = text.value.trim();
                rec.onresult = (e) => {
                    let heard = '';
                    for (let i = 0; i < e.results.length; i++) heard += e.results[i][0].transcript;
                    text.value = `${base}${base && heard ? ' ' : ''}${heard}`.slice(0, text.maxLength > 0 ? text.maxLength : 2000);
                    example = null;
                };
                rec.onerror = (e) => { if (e.error === 'not-allowed' || e.error === 'service-not-allowed' || e.error === 'audio-capture') say(t('router.mic.denied')); };
                rec.onend = off;
                rec.start();
                say('');
                mic.setAttribute('aria-pressed', 'true'); mic.classList.add('bg-action-soft', 'text-action-dark');
                if (label) label.textContent = t('router.mic.on');
                track('voice');
            } catch {
                off();
                say(t('router.mic.denied'));
            }
        });
    }

    noteFromAddress();
}

/** The part of the browser's speech recognition the dictation uses (the type is not in every TypeScript lib). */
interface Recognition {
    lang: string; interimResults: boolean; continuous: boolean;
    onresult: ((e: { results: ArrayLike<ArrayLike<{ transcript: string }>> }) => void) | null;
    onerror: ((e: { error: string }) => void) | null;
    onend: (() => void) | null;
    start(): void; stop(): void;
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
