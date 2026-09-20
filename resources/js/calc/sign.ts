/** Tools → sign / name tag / keychain: form → parametric model → opens in the calculator. */

interface SignCfg { url: string; home: string; i18n: { working: string; failed: string } }

export function bootSign(): void {
    const form = document.getElementById('sign-form') as HTMLFormElement | null;
    const cfg = (window as unknown as { MP_SIGN?: SignCfg }).MP_SIGN;
    if (!form || !cfg) return;
    const th = document.getElementById('sign-th') as HTMLInputElement;
    const msg = document.getElementById('sign-msg') as HTMLElement;
    const btn = form.querySelector('button') as HTMLButtonElement;
    th.oninput = () => { (document.getElementById('sign-th-val') as HTMLElement).textContent = `${th.value} mm`; };

    form.onsubmit = async (e) => {
        e.preventDefault();
        btn.disabled = true;
        msg.textContent = cfg.i18n.working;
        const fd = new FormData(form);
        const data: Record<string, unknown> = {};
        fd.forEach((v, k) => { data[k] = v; });
        data.hole = fd.has('hole');
        data.border = fd.has('border');
        try {
            const res = await fetch(cfg.url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(data) });
            const body = await res.json();
            if (!res.ok) throw new Error(body.message ?? 'sign');
            location.href = `${cfg.home}?open=${body.file.uuid}`;
        } catch {
            msg.textContent = cfg.i18n.failed;
            btn.disabled = false;
        }
    };
}
