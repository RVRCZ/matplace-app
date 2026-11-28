/**
 * /admin/farm/activity (App\Domain\Stats\ActivityStream): the list redraws in place every few seconds while the tab
 * is looked at, and the lines that came since the page was opened are lit for a while, so a new step stands out.
 */
export function bootAdminActivity(): void {
    const box = document.getElementById('admin-activity');
    if (!box) return;
    const seen = new Set(Array.from(box.querySelectorAll<HTMLElement>('[data-line]')).map((li) => li.dataset.line!));
    const refresh = async (): Promise<void> => {
        if (document.hidden) return;
        try {
            const r = await fetch(box.dataset.refresh!, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
            if (!r.ok) return;
            const fresh = new DOMParser().parseFromString(await r.text(), 'text/html').getElementById('admin-activity');
            if (!fresh) return;
            box.innerHTML = fresh.innerHTML;
            box.querySelectorAll<HTMLElement>('[data-line]').forEach((li) => {
                if (seen.has(li.dataset.line!)) return;
                seen.add(li.dataset.line!);
                li.classList.add('bg-yellow-50');
                window.setTimeout(() => li.classList.remove('bg-yellow-50'), 60_000);
            });
        } catch {
            // offline for a moment: the next tick tries again
        }
    };
    window.setInterval(() => void refresh(), Number(box.dataset.every || '20') * 1000);
}
