/** Tools → lithophane / relief from a photo: photo + a few options → model → opens in the calculator. */

interface ReliefCfg { url: string; home: string; files: string; i18n: { working: string; failed: string; not_image: string; photo_again: string } }

/** "?from=<uuid>": put the saved settings of an earlier design back into the form. */
async function restoreForm(form: HTMLFormElement, filesUrl: string): Promise<boolean> {
    const from = new URLSearchParams(location.search).get('from');
    if (!from || !/^[0-9a-f-]{36}$/.test(from)) return false;
    try {
        const res = await fetch(`${filesUrl}/${from}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const p = ((await res.json()).file?.tool?.params ?? null) as Record<string, unknown> | null;
        if (!p) return false;
        Object.entries(p).forEach(([k, v]) => {
            form.querySelectorAll<HTMLInputElement | HTMLSelectElement>(`[name="${k}"]`).forEach((el) => {
                if (el instanceof HTMLInputElement && el.type === 'checkbox') el.checked = Boolean(v);
                else if (el instanceof HTMLInputElement && el.type === 'radio') el.checked = el.value === String(v);
                else if (!(el instanceof HTMLInputElement && el.type === 'file')) el.value = String(v);
                el.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });
        return true;
    } catch { return false; }
}

export function bootRelief(): void {
    const form = document.getElementById('relief-form') as HTMLFormElement | null;
    const cfg = (window as unknown as { MP_RELIEF?: ReliefCfg }).MP_RELIEF;
    if (!form || !cfg) return;
    const photo = document.getElementById('relief-photo') as HTMLInputElement;
    const preview = document.getElementById('relief-preview') as HTMLImageElement;
    const pick = document.getElementById('relief-pick') as HTMLElement;
    const width = document.getElementById('relief-w') as HTMLInputElement;
    const msg = document.getElementById('relief-msg') as HTMLElement;
    const btn = form.querySelector('button') as HTMLButtonElement;

    width.oninput = () => { (document.getElementById('relief-w-val') as HTMLElement).textContent = `${width.value} mm`; };
    photo.onchange = () => {
        const f = photo.files?.[0];
        if (!f) return;
        if (!f.type.startsWith('image/')) { msg.textContent = cfg.i18n.not_image; photo.value = ''; return; }
        msg.textContent = '';
        preview.src = URL.createObjectURL(f);
        preview.hidden = false; preview.classList.remove('hidden');
        pick.classList.add('hidden');
    };

    void restoreForm(form, cfg.files).then((ok) => { if (ok) msg.textContent = cfg.i18n.photo_again; });

    form.onsubmit = async (e) => {
        e.preventDefault();
        if (!photo.files?.[0]) { photo.click(); return; }
        btn.disabled = true;
        msg.textContent = cfg.i18n.working;
        const fd = new FormData(form);
        fd.set('frame', fd.has('frame') ? '1' : '0');
        fd.set('invert', fd.has('invert') ? '1' : '0');
        fd.set('stand', fd.has('stand') ? '1' : '0');
        try {
            const res = await fetch(cfg.url, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body: fd });
            const body = await res.json();
            if (!res.ok) throw new Error(body.message ?? 'relief');
            location.href = `${cfg.home}?open=${body.file.uuid}`;
        } catch {
            msg.textContent = cfg.i18n.failed;
            btn.disabled = false;
        }
    };
}
