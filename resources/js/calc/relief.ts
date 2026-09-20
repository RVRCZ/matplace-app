/** Tools → lithophane / relief from a photo: photo + a few options → model → opens in the calculator. */

interface ReliefCfg { url: string; home: string; i18n: { working: string; failed: string; not_image: string } }

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
