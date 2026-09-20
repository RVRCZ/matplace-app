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
    const btn = form.querySelector('button') as HTMLButtonElement;

    size.oninput = () => { $('figure-size-val').textContent = `${size.value} mm`; };
    photo.onchange = () => {
        const f = photo.files?.[0];
        const img = $('figure-preview') as HTMLImageElement;
        if (f) { img.src = URL.createObjectURL(f); img.classList.remove('hidden'); }
    };

    form.onsubmit = async (e) => {
        e.preventDefault();
        const file = photo.files?.[0];
        if (!file) { msg.textContent = t('figure.need_photo'); return; }
        if (!($('figure-consent') as HTMLInputElement).checked) { msg.textContent = t('figure.need_consent'); return; }
        btn.disabled = true;
        $('figure-progress').classList.remove('hidden');
        msg.textContent = t('figure.generating');
        const data = new FormData(form);
        try {
            const res = await fetch(cfg.generate, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body: data });
            const body = await res.json();
            if (res.status === 429) { msg.textContent = t(body.error === 'global_limit' ? 'figure.global_limit' : 'figure.limit', { n: body.limit, m: body.login_limit }); btn.disabled = false; return; }
            if (res.status === 422 && body.error === 'photo_rejected') { msg.textContent = t('figure.rejected'); btn.disabled = false; return; }
            if (!res.ok) throw new Error(body.message ?? 'generate');
            let g = body.generation;
            while (g.status !== 'done' && g.status !== 'failed') {
                bar.style.width = `${Math.max(5, g.progress)}%`;
                await new Promise((r) => setTimeout(r, 3000));
                g = (await (await fetch(`${cfg.show}/${g.token}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })).json()).generation;
            }
            if (g.status === 'failed' || !g.file) { msg.textContent = t('figure.failed'); btn.disabled = false; return; }
            bar.style.width = '100%';
            msg.textContent = t('figure.done');
            location.href = `${cfg.home}?open=${g.file.uuid}`;
        } catch {
            msg.textContent = t('figure.failed');
            btn.disabled = false;
        }
    };
}
