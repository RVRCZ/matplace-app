/** Tools → sign / name tag / keychain: form → parametric model → opens in the calculator. */

interface SignCfg { url: string; home: string; files: string; i18n: { working: string; failed: string } }

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

export function bootSign(): void {
    const form = document.getElementById('sign-form') as HTMLFormElement | null;
    const cfg = (window as unknown as { MP_SIGN?: SignCfg }).MP_SIGN;
    if (!form || !cfg) return;
    const th = document.getElementById('sign-th') as HTMLInputElement;
    const msg = document.getElementById('sign-msg') as HTMLElement;
    const btn = form.querySelector('button') as HTMLButtonElement;
    th.oninput = () => { (document.getElementById('sign-th-val') as HTMLElement).textContent = `${th.value} mm`; };
    const rad = document.getElementById('sign-r') as HTMLInputElement | null;
    if (rad) rad.oninput = () => { (document.getElementById('sign-r-val') as HTMLElement).textContent = `${rad.value} mm`; };

    void restoreForm(form, cfg.files);

    form.onsubmit = async (e) => {
        e.preventDefault();
        btn.disabled = true;
        msg.textContent = cfg.i18n.working;
        const fd = new FormData(form);
        const data: Record<string, unknown> = {};
        fd.forEach((v, k) => { data[k] = v; });
        data.hole = fd.has('hole');
        data.border = fd.has('border');
        data.bevel = fd.has('bevel');
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
