/**
 * The cookie bar is fixed to the bottom of the window: while it shows, the page keeps its height free under its end and
 * a field the keyboard or a Tab moves to stops above it (--consent-h, used in app.css).
 */
export function bootBar(): void {
    const bar = document.getElementById('consent');
    if (!bar || typeof ResizeObserver === 'undefined') return;
    const root = document.documentElement;
    const set = () => root.style.setProperty('--consent-h', `${bar.classList.contains('hidden') ? 0 : Math.ceil(bar.getBoundingClientRect().height)}px`);
    new ResizeObserver(set).observe(bar);
    new MutationObserver(set).observe(bar, { attributes: true, attributeFilter: ['class'] });
    set();
}
