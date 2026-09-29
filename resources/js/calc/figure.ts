/** Tools → figure / bust from a photo: consent, upload, progress, then the model opens in the calculator. */

interface FigureCfg { generate: string; show: string; home: string; i18n: Record<string, string> }

export function bootFigure(): void {
    const form = document.getElementById('figure-form') as HTMLFormElement | null;
    const cfg = (window as unknown as { MP_FIGURE?: FigureCfg }).MP_FIGURE;
    if (!form || !cfg) return;
    const t = (k: string, r: Record<string, string | number> = {}) => Object.entries(r).reduce((s, [a, b]) => s.replace(`:${a}`, String(b)), cfg.i18n[k] ?? k);
    const $ = <T extends HTMLElement>(id: string) => document.getElementById(id) as T;
    const photo = $('figure-photo') as HTMLInputElement;
    const size = $('figure-size') as HTMLInputElement;
    const msg = $('figure-msg');
    const bar = $('figure-bar');
    const btn = $('figure-submit') as HTMLButtonElement;
    const viewsMsg = $('figure-views-msg');

    size.oninput = () => { $('figure-size-val').textContent = `${size.value} mm`; };

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

    form.onsubmit = async (e) => {
        e.preventDefault();
        sync();
        const file = photo.files?.[0];
        if (!file) { msg.textContent = t('figure.need_photo'); return; }
        if (!($('figure-consent') as HTMLInputElement).checked) { msg.textContent = t('figure.need_consent'); return; }
        const idle = (text: string) => { msg.textContent = text; btn.disabled = false; $('figure-progress').classList.add('hidden'); bar.style.width = '0'; };
        btn.disabled = true;
        calm();
        inputs.forEach((i) => mark(i.dataset.view ?? '', false));
        $('figure-progress').classList.remove('hidden');
        msg.textContent = t('figure.generating');
        const data = new FormData(form);
        try {
            const res = await fetch(cfg.generate, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body: data });
            const body = await res.json();
            if (res.status === 429) return idle(t(body.error === 'global_limit' ? 'figure.global_limit' : 'figure.limit', { n: body.limit, m: body.login_limit }));
            if (res.status === 422 && body.error === 'photo_rejected') {
                // only the front photo stops the work; it leaves the form, so the next try does not send it again
                clear('front');
                mark('front', true);
                return idle(t('figure.rejected'));
            }
            if (!res.ok) throw new Error(body.message ?? 'generate');
            // refused sides were left out: the model is made from the rest, and the visitor is told which ones
            const skipped: string[] = Array.isArray(body.skipped_views) ? body.skipped_views.map(String) : [];
            const note = skipped.map((view) => t('figure.skipped_view', { view: t(`figure.view.${view}`) })).join(' ');
            skipped.forEach((view) => { clear(view); mark(view, true); });
            if (note) { viewsMsg.textContent = note; viewsMsg.classList.remove('hidden'); msg.textContent = `${note} ${t('figure.generating')}`; }
            let g = body.generation;
            while (g.status !== 'done' && g.status !== 'failed') {
                bar.style.width = `${Math.max(5, g.progress)}%`;
                await new Promise((r) => setTimeout(r, 3000));
                g = (await (await fetch(`${cfg.show}/${g.token}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })).json()).generation;
            }
            if (g.status === 'failed' || !g.file) return idle(t('figure.failed'));
            bar.style.width = '100%';
            msg.textContent = note ? `${note} ${t('figure.done')}` : t('figure.done');
            if (note) await new Promise((r) => setTimeout(r, 2500)); // time to read it before the model opens
            location.href = `${cfg.home}?open=${g.file.uuid}`;
        } catch {
            idle(t('figure.failed'));
        }
    };
}
