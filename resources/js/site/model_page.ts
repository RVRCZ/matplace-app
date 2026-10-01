/**
 * A model's page: the price follows the material and the number of pieces (asked from the server, the same
 * formula an order uses), the order link carries them on, and the small pictures switch the big one.
 */
interface Quote { available: boolean; total?: number; royalty?: number; copies?: number; material?: string; total_text?: string; royalty_text?: string }

function gallery(): void {
    document.querySelectorAll<HTMLElement>('[data-gallery]').forEach((box) => {
        const main = box.querySelector<HTMLImageElement>('[data-gallery-main]');
        if (!main) return;
        box.querySelectorAll<HTMLElement>('[data-gallery-thumb]').forEach((thumb) => {
            thumb.addEventListener('click', () => { main.src = thumb.dataset.galleryThumb as string; });
        });
    });
}

function quote(): void {
    const box = document.querySelector<HTMLElement>('[data-quote]');
    const material = box?.querySelector<HTMLSelectElement>('[data-quote-material]');
    const copies = box?.querySelector<HTMLInputElement>('[data-quote-copies]');
    if (!box || !material || !copies) return;
    let request = 0;
    const refresh = async (): Promise<void> => {
        const n = Math.max(1, Math.min(64, Math.round(Number(copies.value) || 1)));
        const query = new URLSearchParams({ copies: String(n), material: material.value });
        const go = box.querySelector<HTMLAnchorElement>('[data-quote-go]');
        if (go) {
            const url = new URL(box.dataset.order as string, location.origin);
            if (n > 1) url.searchParams.set('copies', String(n));
            go.href = url.toString();
        }
        const mine = ++request;
        try {
            const res = await fetch(`${box.dataset.quote}?${query}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok || mine !== request) return;
            const q = (await res.json()) as Quote;
            if (!q.available) return;
            const total = box.querySelector('[data-quote-total]');
            if (total) total.textContent = q.total_text ?? '';
            const royalty = box.querySelector('[data-quote-royalty]');
            if (royalty) royalty.textContent = q.royalty_text ?? '';
            box.querySelector('[data-quote-royalty-line]')?.classList.toggle('hidden', !(q.royalty && q.royalty > 0));
        } catch { /* the price shown stays */ }
    };
    material.addEventListener('change', refresh);
    copies.addEventListener('input', refresh);
}

export function bootModelPage(): void {
    gallery();
    quote();
}
