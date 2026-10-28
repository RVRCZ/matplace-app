/**
 * Figure / bust from a photo, and a pet figurine, as a module of the tool page: consent, upload, progress, then the
 * model on the stage (viewer, size, rough price) and one click on to the calculator, where the base of a bust can still
 * be changed. A running or finished generation has an address (?generation=<token>): the page opened by it shows the
 * same choices and the model, so the visitor may leave while the model is being made.
 *
 * What only a pet figurine has (its page gives the elements, this script skips what is not there): the thinnest place
 * of the figure with a warning under 2.5 mm, and another base under the same figure without a new generation.
 * While a model is being made the viewer shows the logo of dots (particles.ts) with the phase and the percent.
 */
import { focusCalm } from '../site/sticky';
import type { Stage } from './tool_page';
import { FileInfo } from './api';
import { startParticles, viewerBox, type Particles } from './particles';
import { shrinkPhoto, overLimit } from './photo';

interface FigureCfg { generate: string; show: string; home: string; files?: string; maxMb: number; register: string | null; i18n: Record<string, string> }
interface Generation { token: string; status: string; progress: number; file: FileInfo | null; target_mm?: number | null; options?: Record<string, string | number> | null }

export function bootFigure(stage: Stage): void {
    const form = document.getElementById('figure-form') as HTMLFormElement | null;
    const cfg = (window as unknown as { MP_FIGURE?: FigureCfg }).MP_FIGURE;
    if (!form || !cfg) return;
    const t = (k: string, r: Record<string, string | number> = {}) => Object.entries(r).reduce((s, [a, b]) => s.replace(`:${a}`, String(b)), cfg.i18n[k] ?? k);
    // a text only some pages bring (the pet's own words); the other is what every page has
    const say = (k: string, other: string, r: Record<string, string | number> = {}) => t(cfg.i18n[k] ? k : other, r);
    const $ = <T extends HTMLElement>(id: string) => document.getElementById(id) as T;
    const photo = $('figure-photo') as HTMLInputElement;
    const size = $('figure-size') as HTMLInputElement;
    const msg = $('figure-msg');
    const bar = $('figure-bar');
    const btn = $('figure-submit') as HTMLButtonElement;
    const viewsMsg = $('figure-views-msg');
    let made: FileInfo | null = null;                  // the figure on the stage, once there is one

    const sized = (): void => { $('figure-size-val').textContent = `${size.value} mm`; };
    size.oninput = sized;

    // every photo (front and the optional sides) can be picked, replaced and taken away again
    const inputs = Array.from(form.querySelectorAll<HTMLInputElement>('input[data-view]'));
    const part = <T extends HTMLElement>(attr: string, view: string) => form.querySelector(`[${attr}="${view}"]`) as T | null;
    const show = (input: HTMLInputElement) => {
        const view = input.dataset.view ?? '';
        const f = input.files?.[0];
        const img = part<HTMLImageElement>('data-view-preview', view);
        if (img) {
            if (img.src.startsWith('blob:')) URL.revokeObjectURL(img.src);
            if (f) img.src = URL.createObjectURL(f); else img.removeAttribute('src');
            img.classList.toggle('hidden', !f);
        }
        part('data-view-remove', view)?.classList.toggle('hidden', !f);
        dropped();
    };
    // the photos picked in the empty viewer (tools/_figure_drop.blade.php): the same inputs, small pictures of them there,
    // each with a cross that takes it away again (as the cross in the panel does), and the model made from there
    const drop = document.getElementById('figure-drop');
    const create = document.getElementById('figure-create') as HTMLButtonElement | null;
    const dropMsg = document.getElementById('figure-drop-msg');
    const sayInDrop = (text: string): void => { if (!dropMsg) return; dropMsg.textContent = text; dropMsg.classList.toggle('hidden', text === ''); };
    const dropped = (): void => {
        const thumbs = document.getElementById('figure-drop-thumbs');
        if (!drop || !thumbs) return;
        const chosen = inputs.filter((i) => i.files?.length);
        thumbs.innerHTML = '';
        chosen.forEach((i) => {
            const view = i.dataset.view ?? '';
            const src = part<HTMLImageElement>('data-view-preview', view)?.getAttribute('src');
            if (!src) return;
            const box = document.createElement('span');
            box.className = 'relative flex flex-col items-center gap-0.5 text-xs text-muted';
            const img = document.createElement('img');
            img.src = src; img.alt = ''; img.className = 'h-12 w-12 rounded-lg object-cover';
            const x = document.createElement('button');
            x.type = 'button'; x.dataset.dropRemove = view;
            x.className = 'absolute -right-2 -top-2 inline-flex h-7 w-7 items-center justify-center rounded-full border border-line bg-card text-ink shadow-sm hover:border-ink';
            x.setAttribute('aria-label', `${t('figure.remove')}: ${t(`figure.view.${view}`)}`); x.title = t('figure.remove');
            x.innerHTML = '<svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-x"/></svg>';
            x.addEventListener('click', (e) => { e.stopPropagation(); clear(view); mark(view, false); calm(); msg.textContent = ''; sayInDrop(''); });
            box.append(img, x, t(`figure.view.${view}`));
            thumbs.appendChild(box);
        });
        thumbs.classList.toggle('hidden', chosen.length === 0); thumbs.classList.toggle('flex', chosen.length > 0);
        // without the front photo it asks for one again, and there is nothing to make yet
        const front = Boolean(photo.files?.length);
        const title = drop.querySelector('[data-drop-title]');
        if (title) title.textContent = t(front ? 'photo.add.more' : 'photo.add');
        drop.querySelector('[data-drop-hint]')?.classList.toggle('hidden', front);     // room for the rest once the front photo is there
        if (create) create.disabled = !front;
        // the consent, the limit and the button only once there is a photo to make the model from
        const go = document.getElementById('figure-drop-go');
        go?.classList.toggle('hidden', !front); go?.classList.toggle('flex', front);
    };
    // a tap asks for the front first, then for the sides still free; a drop fills them in the same order
    const free = (): HTMLInputElement => inputs.find((i) => !i.files?.length) ?? inputs[0];
    const put = (input: HTMLInputElement, f: File): void => {
        const dt = new DataTransfer(); dt.items.add(f); input.files = dt.files;
        input.dispatchEvent(new Event('change'));
    };
    if (drop) {
        document.getElementById('figure-drop-pick')?.addEventListener('click', () => free().click());
        const area = document.getElementById('tool-empty') ?? drop;
        area.addEventListener('dragover', (e) => { e.preventDefault(); drop.classList.add('border-ink'); });
        area.addEventListener('dragleave', () => drop.classList.remove('border-ink'));
        area.addEventListener('drop', (e) => {
            e.preventDefault(); drop.classList.remove('border-ink');
            [...(e.dataTransfer?.files ?? [])].filter((f) => f.type.startsWith('image/')).slice(0, inputs.length).forEach((f) => put(free(), f));
        });
        // the consent there is the panel's one, mirrored both ways (only the panel's travels with the form)
        const mine = $('figure-consent') as HTMLInputElement;
        const theirs = document.getElementById('figure-drop-consent') as HTMLInputElement | null;
        if (theirs) {
            theirs.checked = mine.checked;
            theirs.addEventListener('change', () => { mine.checked = theirs.checked; if (theirs.checked) sayInDrop(''); });
            mine.addEventListener('change', () => { theirs.checked = mine.checked; });
        }
        // "Create" there: the same submit as the button in the panel; a missing consent is said right there
        create?.addEventListener('click', () => {
            if (!photo.files?.length) { free().click(); return; }
            if (!mine.checked) { sayInDrop(t('figure.need_consent')); focusCalm(theirs); return; }
            sayInDrop('');
            form.requestSubmit();
        });
    }
    const mark = (view: string, bad: boolean) => {
        const box = part('data-view-box', view);
        box?.classList.toggle('border-red-600', bad);
        box?.classList.toggle('bg-red-50', bad);
    };
    const clear = (view: string) => {
        const input = inputs.find((i) => i.dataset.view === view);
        if (!input) return;
        input.value = '';
        show(input);
    };
    const calm = () => { viewsMsg.textContent = ''; viewsMsg.classList.add('hidden'); };
    inputs.forEach((input) => {
        const view = input.dataset.view ?? '';
        input.onchange = () => { mark(view, false); calm(); msg.textContent = ''; show(input); };
        const remove = part<HTMLButtonElement>('data-view-remove', view);
        if (remove) remove.onclick = () => { clear(view); mark(view, false); calm(); msg.textContent = ''; };
    });
    // The browser may keep the chosen files over a reload or a step back (photos taken straight by the camera
    // exist nowhere else). What the form would send must be on the screen, with its remove button.
    const sync = () => inputs.forEach(show);
    sync();
    window.addEventListener('pageshow', sync);
    window.addEventListener('load', sync);

    // the logo of dots over the viewer while the model is on its way; it scatters on an error, fades when the model comes
    let dots: Particles | null = null;
    const idle = (text: string): void => {
        msg.textContent = text; btn.disabled = false; $('figure-progress').classList.add('hidden'); bar.style.width = '0';
        void dots?.fail(); dots = null;
    };
    const working = (text: string): void => {
        btn.disabled = true; $('figure-progress').classList.remove('hidden'); msg.textContent = text;
        const box = viewerBox();
        if (box) { dots ??= startParticles(box); dots.say(text); }
    };
    const fetchGeneration = async (token: string): Promise<Generation | null> => {
        const res = await fetch(`${cfg.show}/${token}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        return res.ok ? (await res.json()).generation as Generation : null;
    };

    /** A pet figurine says how thin its thinnest leg or tail is: a warning in the step of the style, or a calm line. */
    const measured = (file: FileInfo): void => {
        if (form.dataset.kind !== 'pet') return;
        const pet = file.generation?.pet ?? null;
        const calmLine = document.getElementById('figure-thin-ok');
        const thin = pet?.thinnest_mm ?? null;
        const risky = pet !== null && thin !== null && thin < pet.safe_mm;
        stage.warnings(risky ? { style: [t('figure.thin', { mm: stage.nf.format(thin!) })] } : {});
        if (calmLine) {
            calmLine.textContent = pet !== null && thin !== null && !risky ? t('figure.thin.ok', { mm: stage.nf.format(thin) }) : '';
            calmLine.classList.toggle('hidden', calmLine.textContent === '');
        }
        document.getElementById('figure-rebase-box')?.classList.toggle('hidden', pet === null);
    };

    /** The finished model on the stage: turn it, see its size and a first price; the calculator is one click on. */
    const present = async (file: FileInfo, done: string): Promise<void> => {
        bar.style.width = '100%';
        msg.textContent = done;
        const ready = await stage.untilReady(file);
        if (ready.status !== 'ready') return idle(say('figure.failed', 'figure.failed'));
        // the model is downloaded and drawn under the dots (seconds for a big mesh); they fade only when it is there
        dots?.say(done, 100);
        try { await stage.fileResult(ready); } catch { return idle(t('figure.failed')); }
        await dots?.finish(); dots = null;
        made = ready;
        measured(ready);
        btn.disabled = false; $('figure-progress').classList.add('hidden');
        stage.revealResult();
    };

    /** Waits for a generation to end and shows its model. */
    const follow = async (first: Generation, note = ''): Promise<void> => {
        let g: Generation | null = first;
        if (g && g.status !== 'done' && g.status !== 'failed') notifyBox(g.token);
        while (g && g.status !== 'done' && g.status !== 'failed') {
            bar.style.width = `${Math.max(5, g.progress)}%`;
            dots?.say(msg.textContent ?? '', g.progress);
            await new Promise((r) => setTimeout(r, 3000));
            g = await fetchGeneration(g.token);
        }
        notifyBox(null);
        // a failed model leaves the address: a reload starts from the form (kept), not from the failure
        if (!g || g.status === 'failed' || !g.file) { address(null); return idle(say('figure.failed', 'figure.failed')); }
        await present(g.file, note ? `${note} ${t('figure.done')}` : t('figure.done'));
    };

    /** The choices of a generation put back into the form (the page opened by the address of a figurine). */
    const fill = (g: Generation): void => {
        const o = g.options ?? {};
        const pick = (name: string, value: unknown): void => {
            const radio = value === undefined || value === null ? null : form.querySelector<HTMLInputElement>(`input[type=radio][name="${name}"][value="${String(value)}"]`);
            if (radio) { radio.checked = true; radio.dispatchEvent(new Event('change', { bubbles: true })); }
        };
        pick('kind', o.kind); pick('style', o.style); pick('roughness', o.roughness); pick('pedestal', o.pedestal);
        const write = (name: string, value: unknown): void => { const el = form.querySelector<HTMLInputElement>(`input[name="${name}"]`); if (el && typeof value === 'string') el.value = value; };
        write('pedestal_name', o.pedestal_name); write('pedestal_dedication', o.pedestal_dedication);
        const side = form.querySelector<HTMLSelectElement>('select[name="name_side"]');
        if (side && typeof o.name_side === 'string') side.value = o.name_side;
        if (g.target_mm) { size.value = String(g.target_mm); sized(); }
        form.dispatchEvent(new Event('figure:filled'));
    };

    // "send me the result": the e-mail form shows while the model is being made, with the token the mail will link to
    const notify = document.getElementById('figure-notify') as HTMLFormElement | null;
    let notifyToken: string | null = null;
    const notifyBox = (token: string | null): void => { notifyToken = token; if (notify) notify.hidden = token === null; };
    if (notify) {
        notify.onsubmit = async (e) => {
            e.preventDefault();
            if (!notifyToken) return;
            const email = (notify.querySelector('input[type=email]') as HTMLInputElement).value.trim();
            const out = notify.querySelector('[data-notify-msg]') as HTMLElement;
            const send = notify.querySelector('button') as HTMLButtonElement;
            try {
                const res = await fetch(`${cfg.show}/${notifyToken}/notify`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ email }) });
                out.textContent = t(res.ok ? 'figure.notify.sent' : 'figure.notify.failed');
                if (res.ok) send.disabled = true;
            } catch { out.textContent = t('figure.notify.failed'); }
        };
    }

    const address = (token: string | null): void => {
        const url = new URL(location.href);
        if (token) url.searchParams.set('generation', token); else url.searchParams.delete('generation');
        history.replaceState(null, '', url);
    };

    form.onsubmit = async (e) => {
        e.preventDefault();
        sync();
        const file = photo.files?.[0];
        if (!file) { msg.textContent = t('figure.need_photo'); return; }
        if (!($('figure-consent') as HTMLInputElement).checked) { msg.textContent = t('figure.need_consent'); return; }
        calm();
        inputs.forEach((i) => mark(i.dataset.view ?? '', false));
        // the photos go as 2 000 px at most (a phone's 8 to 12 MB would be slow and over the limit); what is still too big is said now
        const data = new FormData(form);
        for (const input of inputs) {
            const f = input.files?.[0];
            if (!f) continue;
            const sent = await shrinkPhoto(f);
            if (overLimit(sent, cfg.maxMb)) { mark(input.dataset.view ?? '', true); msg.textContent = t('photo.too_big'); return; }
            data.set(input.name, sent);
        }
        working(t('figure.generating'));
        // on a phone the panel is under the viewer: the page goes up to where the model is being made
        stage.revealStage();
        try {
            const res = await fetch(cfg.generate, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body: data });
            const body = await res.json();
            if (res.status === 429) {
                idle(t(body.error === 'global_limit' ? 'figure.global_limit' : 'figure.limit', { n: body.limit, m: body.login_limit }));
                // a visitor without an account gets more models with one: the way there is in the message itself
                if (body.error !== 'global_limit' && cfg.register) {
                    const a = document.createElement('a');
                    a.href = cfg.register; a.className = 'font-medium text-ink underline'; a.textContent = t('figure.limit.register');
                    msg.append(' ', a);
                }
                return;
            }
            // the server's own refusal of a field (a photo too big, not a picture) is a sentence in the page's language
            if (res.status === 422 && body.error !== 'photo_rejected' && typeof body.message === 'string') return idle(body.message);
            if (res.status === 422 && body.error === 'photo_rejected') {
                // only the front photo stops the work; it leaves the form, so the next try does not send it again
                clear('front');
                mark('front', true);
                return idle(say(`figure.rejected.${String(body.reason ?? '')}`, 'figure.rejected'));
            }
            if (!res.ok) throw new Error(body.message ?? 'generate');
            // refused sides were left out: the model is made from the rest, and the visitor is told which ones
            const skipped: string[] = Array.isArray(body.skipped_views) ? body.skipped_views.map(String) : [];
            const note = skipped.map((view) => t('figure.skipped_view', { view: t(`figure.view.${view}`) })).join(' ');
            skipped.forEach((view) => { clear(view); mark(view, true); });
            if (note) { viewsMsg.textContent = note; viewsMsg.classList.remove('hidden'); msg.textContent = `${note} ${t('figure.generating')}`; }
            // from now on the model has an address: the visitor may leave and come back by it
            address(body.generation.token);
            await follow(body.generation as Generation, note);
        } catch {
            idle(t('figure.failed'));
        }
    };

    // another base or another name under the figure that is on the stage: a few seconds of geometry, no new generation
    const rebase = document.getElementById('figure-rebase') as HTMLButtonElement | null;
    if (rebase && cfg.files) {
        rebase.onclick = async () => {
            if (!made) return;
            const data = new FormData(form);
            rebase.disabled = true;
            working(t('figure.rebase.working'));
            bar.style.width = '60%';
            try {
                const res = await fetch(`${cfg.files}/${made.uuid}/pedestal`, {
                    method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ type: data.get('pedestal'), name: data.get('pedestal_name'), dedication: data.get('pedestal_dedication'), name_side: data.get('name_side') }),
                });
                if (!res.ok) throw new Error('pedestal');
                await present((await res.json()).file as FileInfo, t('figure.done'));
            } catch {
                idle(t('figure.rebase.failed'));
            } finally {
                rebase.disabled = false;
            }
        };
    }

    // the choices (not the photos, not the consent) are kept like every tool's settings: a reload or the step back from
    // the calculator finds them (docs/AH.md §2); a page opened by the address of a figurine takes them from the figurine
    const token = new URLSearchParams(location.search).get('generation');
    const choices = (): Record<string, string> => {
        const out: Record<string, string> = {};
        new FormData(form).forEach((v, k) => { if (typeof v === 'string' && k !== 'consent') out[k] = v; });
        return out;
    };
    const commit = stage.track({
        read: choices,
        write: (s) => {
            Object.entries(s).forEach(([k, v]) => {
                const radio = form.querySelector<HTMLInputElement>(`input[type=radio][name="${k}"][value="${String(v)}"]`);
                if (radio) { radio.checked = true; radio.dispatchEvent(new Event('change', { bubbles: true })); return; }
                const el = form.querySelector<HTMLInputElement | HTMLSelectElement>(`[name="${k}"]:not([type=file]):not([type=radio]):not([type=hidden])`);
                if (el) el.value = String(v);
            });
            sized();
            form.dispatchEvent(new Event('figure:filled'));
        },
    }, !token, !token);
    form.addEventListener('change', (e) => { if (!(e.target as HTMLInputElement).matches('[type=file], [name=consent]')) commit(); });

    // opened by the address of a figurine: the same choices, and the model as soon as it is there
    if (token && /^[a-z0-9]{6,40}$/.test(token)) {
        void (async () => {
            working(say('figure.back', 'figure.generating'));
            try {
                const g = await fetchGeneration(token);
                if (!g) { address(null); return idle(say('figure.gone', 'figure.failed')); }
                fill(g);
                await follow(g);
            } catch {
                idle(t('figure.failed'));
            }
        })();
    }
}
