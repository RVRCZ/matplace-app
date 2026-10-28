/**
 * Layouts without jumps (docs/AN.md §18, docs/AO.md §5), shared by the tool page, the calculation and the order:
 *  - revealIfHidden(): scroll only when the target is not in view, smoothly and only as far as needed, inside its
 *    scrolling ancestors and the window alike, above the bars fixed to the bottom;
 *  - focusCalm(): focus without the browser's own jump, then reveal the field the same way;
 *  - flash(): a ring that fades on an element whose content changed out of sight (a new price, a model ready);
 *  - mountActionBars(): every [data-action-bar] (components/action-bar.blade.php) mirrors the page's own button (label,
 *    disabled, hidden) and price element, forwards the click, and keeps --action-bar-h and body.has-action-bar right
 *    while it is displayed, so the page keeps room under its end (app.css).
 */

/** The bars fixed to the bottom of the window, in px: the cookie bar (site/bar.ts) and the action bar. */
function barsHeight(): number {
    const s = getComputedStyle(document.documentElement);

    return (parseFloat(s.getPropertyValue('--consent-h')) || 0) + (parseFloat(s.getPropertyValue('--action-bar-h')) || 0);
}

/** Whole and visible: in the window above the bottom bars, and inside every scrolling ancestor. */
export function isInView(el: Element, margin = 4): boolean {
    const r = el.getBoundingClientRect();
    if (r.width === 0 && r.height === 0) return false;
    if (r.top < margin || r.bottom > window.innerHeight - barsHeight() - margin) return false;
    for (let p = el.parentElement; p && p !== document.body; p = p.parentElement) {
        const o = getComputedStyle(p).overflowY;
        if ((o === 'auto' || o === 'scroll') && p.scrollHeight > p.clientHeight + 1) {
            const b = p.getBoundingClientRect();
            if (r.top < b.top + margin || r.bottom > b.bottom - margin) return false;
        }
    }

    return true;
}

/** Scrolls only when the element is not in view; returns whether it did. `nearest` moves the least that shows it. */
export function revealIfHidden(el: Element | null, block: ScrollLogicalPosition = 'nearest'): boolean {
    if (!el || isInView(el)) return false;
    el.scrollIntoView({ behavior: 'smooth', block });

    return true;
}

/** Focus without the browser's jump to the field, then the same calm reveal (a field that failed validation). */
export function focusCalm(el: HTMLElement | null): void {
    if (!el) return;
    el.focus({ preventScroll: true });
    revealIfHidden(el);
}

/** A ring that fades: something here changed while the eyes were elsewhere. Nothing moves. */
export function flash(el: Element | null): void {
    if (!el) return;
    el.classList.remove('flash-change');
    void (el as HTMLElement).offsetWidth;   // restart the animation when it is still running
    el.classList.add('flash-change');
    el.addEventListener('animationend', () => el.classList.remove('flash-change'), { once: true });
}

export function mountActionBars(): void {
    document.querySelectorAll<HTMLElement>('[data-action-bar]').forEach(mountActionBar);
}

function mountActionBar(bar: HTMLElement): void {
    const source = bar.dataset.for ? (document.getElementById(bar.dataset.for) as HTMLButtonElement | null) : null;
    const button = bar.querySelector<HTMLButtonElement>('[data-bar-button]');
    const root = document.documentElement;
    if (source && button) {
        const sync = () => {
            button.disabled = source.disabled || source.getAttribute('aria-disabled') === 'true';
            button.hidden = source.hidden || source.classList.contains('hidden');
            if (!bar.dataset.label) button.textContent = source.textContent?.replace(/\s+/g, ' ').trim() ?? '';
        };
        new MutationObserver(sync).observe(source, { attributes: true, childList: true, subtree: true, characterData: true });
        button.addEventListener('click', () => source.click());
        sync();
    }
    const mirror = (target: HTMLElement | null, origin: HTMLElement | null, suffix = '') => {
        if (!target || !origin) return;
        const copy = () => {
            const text = origin.textContent?.replace(/\s+/g, ' ').trim() ?? '';
            const empty = text === '' || text === '—' || text === '–' || origin.classList.contains('hidden') || origin.hidden;
            target.textContent = empty ? '' : text + suffix;
            target.hidden = empty;
        };
        new MutationObserver(copy).observe(origin, { childList: true, characterData: true, subtree: true, attributes: true });
        copy();
    };
    mirror(bar.querySelector<HTMLElement>('[data-bar-price]'), bar.dataset.priceFrom ? document.getElementById(bar.dataset.priceFrom) : null, bar.dataset.priceSuffix ?? '');
    mirror(bar.querySelector<HTMLElement>('[data-bar-sub]'), bar.dataset.subFrom ? document.getElementById(bar.dataset.subFrom) : null);
    // the page keeps room under its end while the bar is displayed (a phone; or every width when the page says so)
    const size = () => {
        const shown = !bar.hidden && getComputedStyle(bar).display !== 'none';
        root.style.setProperty('--action-bar-h', `${shown ? Math.ceil(bar.getBoundingClientRect().height) : 0}px`);
        document.body.classList.toggle('has-action-bar', shown);
    };
    if (typeof ResizeObserver !== 'undefined') new ResizeObserver(size).observe(bar);
    window.addEventListener('resize', size);
    size();
}
