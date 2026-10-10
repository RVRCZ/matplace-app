/**
 * Figure / bust from a photo, and a pet figurine, as a module of the tool page: consent, upload, progress, then the
 * model on the stage (viewer, size, rough price) and one click on to the calculator, where the base of a bust can still
 * be changed. A running or finished generation has an address (?generation=<token>): the page opened by it shows the
 * same choices and the model, so the visitor may leave while the model is being made.
 *
 * What only a pet figurine has (its page gives the elements, this script skips what is not there): the thinnest place
 * of the figure with a warning under 2.5 mm, and another base under the same figure without a new generation.
 */
import type { Stage } from './tool_page';
import { FileInfo } from './api';

interface FigureCfg { generate: string; show: string; home: string; files?: string; i18n: Record<string, string> }
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
    };
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

    const idle = (text: string): void => { msg.textContent = text; btn.disabled = false; $('figure-progress').classList.add('hidden'); bar.style.width = '0'; };
    const working = (text: string): void => { btn.disabled = true; $('figure-progress').classList.remove('hidden'); msg.textContent = text; };
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
        await stage.fileResult(ready);
        made = ready;
        measured(ready);
        btn.disabled = false; $('figure-progress').classList.add('hidden');
        stage.reveal('print');
    };

    /** Waits for a generation to end and shows its model. */
    const follow = async (first: Generation, note = ''): Promise<void> => {
        let g: Generation | null = first;
        while (g && g.status !== 'done' && g.status !== 'failed') {
            bar.style.width = `${Math.max(5, g.progress)}%`;
            await new Promise((r) => setTimeout(r, 3000));
            g = await fetchGeneration(g.token);
        }
        if (!g || g.status === 'failed' || !g.file) return idle(t('figure.failed'));
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
        working(t('figure.generating'));
        const data = new FormData(form);
        try {
            const res = await fetch(cfg.generate, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body: data });
            const body = await res.json();
            if (res.status === 429) return idle(t(body.error === 'global_limit' ? 'figure.global_limit' : 'figure.limit', { n: body.limit, m: body.login_limit }));
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

    // opened by the address of a figurine: the same choices, and the model as soon as it is there
    const token = new URLSearchParams(location.search).get('generation');
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
