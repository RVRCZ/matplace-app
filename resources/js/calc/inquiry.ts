/** "Make it for me": small form under the price → POST /api/inquiries → customer page /i/{token}. */

const routes = () => (window as unknown as { MP_ROUTES: Record<string, string> }).MP_ROUTES;
const i18n = (window as unknown as { MP_I18N?: Record<string, string> }).MP_I18N ?? {};
const t = (k: string) => i18n[k] ?? k;

export function bootInquiry(getCalcToken: () => string | null): void {
    const btn = document.getElementById('cta-make') as HTMLButtonElement | null;
    const panel = document.getElementById('inquiry-panel');
    const form = document.getElementById('inquiry-form') as HTMLFormElement | null;
    if (!btn || !panel || !form) return;

    btn.onclick = () => {
        const token = getCalcToken();
        if (!token) { document.getElementById('make-note')?.classList.remove('hidden'); return; }
        panel.classList.remove('hidden');
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        (form.querySelector('[name=quantity]') as HTMLInputElement).value = (document.getElementById('quantity') as HTMLInputElement).value;
    };

    form.onsubmit = async (e) => {
        e.preventDefault();
        const token = getCalcToken();
        if (!token) return;
        const submit = form.querySelector('button[type=submit]') as HTMLButtonElement;
        submit.disabled = true;
        const err = document.getElementById('inquiry-error') as HTMLElement;
        err.classList.add('hidden');
        const data: Record<string, unknown> = { calculation: token };
        new FormData(form).forEach((v, k) => { if (v !== '') data[k] = v; });
        try {
            const res = await fetch(routes().inquiries, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(data) });
            const body = await res.json();
            if (!res.ok) { err.textContent = body.message ?? t('inquiry.error'); err.classList.remove('hidden'); submit.disabled = false; return; }
            location.href = body.inquiry.url;
        } catch {
            err.textContent = t('inquiry.error'); err.classList.remove('hidden'); submit.disabled = false;
        }
    };
}
