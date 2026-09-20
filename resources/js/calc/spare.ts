/** Spare part inquiry: photos (with thumbnails) + description → POST multipart → the customer's inquiry page. */

interface SpareCfg { url: string; i18n: { sending: string; failed: string; too_many: string } }

export function bootSpare(): void {
    const form = document.getElementById('spare-form') as HTMLFormElement | null;
    const cfg = (window as unknown as { MP_SPARE?: SpareCfg }).MP_SPARE;
    if (!form || !cfg) return;
    const photos = document.getElementById('spare-photos') as HTMLInputElement;
    const thumbs = document.getElementById('spare-thumbs') as HTMLElement;
    const err = document.getElementById('spare-error') as HTMLElement;
    const btn = form.querySelector('button') as HTMLButtonElement;
    const label = btn.textContent;
    const fail = (msg: string | null) => { err.textContent = msg ?? ''; err.classList.toggle('hidden', !msg); };

    photos.onchange = () => {
        thumbs.innerHTML = '';
        const files = Array.from(photos.files ?? []);
        if (files.length > 5) { fail(cfg.i18n.too_many); photos.value = ''; return; }
        fail(null);
        files.forEach((f) => {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(f); img.alt = ''; img.className = 'aspect-square w-full rounded-lg object-cover';
            thumbs.appendChild(img);
        });
    };

    form.onsubmit = async (e) => {
        e.preventDefault();
        if (!form.reportValidity()) return;
        btn.disabled = true; btn.textContent = cfg.i18n.sending; fail(null);
        try {
            const res = await fetch(cfg.url, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body: new FormData(form) });
            const body = await res.json();
            if (!res.ok) {
                const first = Object.values((body.errors ?? {}) as Record<string, string[]>)[0]?.[0];
                fail(first ?? body.message ?? cfg.i18n.failed);
                btn.disabled = false; btn.textContent = label;
                return;
            }
            location.href = body.inquiry.url;
        } catch {
            fail(cfg.i18n.failed); btn.disabled = false; btn.textContent = label;
        }
    };
}
