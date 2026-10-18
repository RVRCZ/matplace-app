/** The header's menu (a <details>): it closes on a click elsewhere and on Escape, like any menu. */
export function bootMenu(): void {
    const menus = [...document.querySelectorAll<HTMLDetailsElement>('details[data-menu]')];
    if (!menus.length) return;
    document.addEventListener('click', (e) => menus.forEach((m) => { if (m.open && !m.contains(e.target as Node)) m.open = false; }));
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        menus.filter((m) => m.open).forEach((m) => { m.open = false; m.querySelector('summary')?.focus(); });
    });
}
