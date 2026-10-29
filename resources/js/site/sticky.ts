/**
 * Layouts without jumps (docs/AN.md §18, docs/AO.md §5), shared by the tool page, the calculation and the order:
 *  - revealIfHidden(): scroll only when the target is not in view, smoothly and only as far as needed, inside its
 *    scrolling ancestors and the window alike, above the bars fixed to the bottom;
 *  - focusCalm(): focus without the browser's own jump, then reveal the field the same way;
 *  - flash(): a ring that fades on an element whose content changed out of sight (a new price, a model ready);
 *  - fitWindow(): an element that fills the window under whatever stands above it (the tool page's grid: the site's
 *    header and the margins are not the same on every page, so the height is measured, not guessed);
 *  - mountActionBars(): every [data-action-bar] (components/action-bar.blade.php) mirrors the page's own button (label,
 *    disabled, aria-disabled, hidden) and price element, forwards the click, flashes when the price changes, and keeps
 *    --action-bar-h and body.has-action-bar right while it is displayed, so the page keeps room under its end (app.css).
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

/** The nearest ancestor that scrolls (a panel of options); null when only the window does. */
function scroller(el: Element): HTMLElement | null {
    for (let p = el.parentElement; p && p !== document.body; p = p.parentElement) {
        const o = getComputedStyle(p).overflowY;
        if ((o === 'auto' || o === 'scroll') && p.scrollHeight > p.clientHeight + 1) return p;
    }

    return null;
}

/**
 * Scrolls only when the element is not in view; returns whether it did. Inside a scrolling box the box moves (by the
 * least that shows the element, `start` puts it under the box's scroll-padding) and the window stays, unless the box
 * itself is off the screen; without a box the window moves the least that shows the element.
 */
export function revealIfHidden(el: Element | null, block: ScrollLogicalPosition = 'nearest'): boolean {
    if (!el) return false;
    const box = scroller(el);
    if (!box) {
        if (isInView(el)) return false;
        el.scrollIntoView({ behavior: 'smooth', block });

        return true;
    }
    const r = el.getBoundingClientRect();
    const b = box.getBoundingClientRect();
    const cs = getComputedStyle(box);
    const padTop = parseFloat(cs.scrollPaddingTop) || 0;
    const padBottom = parseFloat(cs.scrollPaddingBottom) || 0;
    const above = r.top < b.top + padTop - 1;
    const below = r.bottom > b.bottom - padBottom + 1;
    let moved = false;
    if (block === 'start' ? Math.abs(r.top - (b.top + padTop)) > 2 : above || below) {
        box.scrollBy({ top: block === 'start' || above ? r.top - (b.top + padTop) : r.bottom - (b.bottom - padBottom), behavior: 'smooth' });
        moved = true;
    }
    if (b.bottom < 120 || b.top > window.innerHeight - barsHeight() - 120) {   // the box itself is off the screen: the window brings it back
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        moved = true;
    }

    return moved;
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

/**
 * The element fills the window under what stands above it (.stick-fit in app.css reads --fit-top): measured where the
 * page is loaded and again when the window changes, with the page at its top. Nothing under lg (the class does nothing there).
 */
export function fitWindow(el: HTMLElement | null): void {
    if (!el) return;
    const set = () => {
        const top = Math.max(0, Math.round(el.getBoundingClientRect().top + window.scrollY));
        el.style.setProperty('--fit-top', `${top}px`);
    };
    set();
    window.addEventListener('resize', set);
}

/**
 * A scrolling box (from lg) whose content changes height above the control just used: the numbers that arrive after
 * "Přepočítat" grew the calculation's column by 32 px and moved the button under the cursor (docs/AN.md §20). The box
 * keeps that control where it was on the screen. Chrome's own anchoring picked the sticky card at the box's top and
 * made it worse (48 px), so it stays off (.stick-scroll) and this does the one thing needed. `skip`: a part of the box
 * that stays put anyway (a sticky card), whose controls are no anchor.
 */
export function holdOnResize(box: HTMLElement | null, skip: string | null = null): void {
    if (!box) return;
    let anchor: HTMLElement | null = null;
    let top = 0;
    const scrolls = () => box.scrollHeight > box.clientHeight && getComputedStyle(box).overflowY !== 'visible';
    // the control under the pointer, or reached by the keyboard
    const take = (e: Event) => {
        const el = (e.target as HTMLElement | null)?.closest?.<HTMLElement>('button, input, select, textarea, a, summary, label') ?? null;
        anchor = el && !(skip && el.closest(skip)) ? el : null;
        if (anchor) top = anchor.getBoundingClientRect().top;
    };
    box.addEventListener('pointerdown', take, true);
    box.addEventListener('focusin', take);
    box.addEventListener('scroll', () => { if (anchor) top = anchor.getBoundingClientRect().top; }, { passive: true });
    const watch = new ResizeObserver(() => {
        if (!anchor?.isConnected || !scrolls()) return;
        const moved = anchor.getBoundingClientRect().top - top;
        if (Math.abs(moved) >= 1) box.scrollTop += moved;
    });
    [...box.children].forEach((child) => watch.observe(child));
}

export function mountActionBars(): void {
    document.querySelectorAll<HTMLElement>('[data-action-bar]').forEach(mountActionBar);
}

function mountActionBar(bar: HTMLElement): void {
    const source = bar.dataset.for ? (document.getElementById(bar.dataset.for) as HTMLButtonElement | null) : null;
    const button = bar.querySelector<HTMLButtonElement>('[data-bar-button]');
    const root = document.documentElement;
    if (source && button) {
        // disabled only when the source is disabled; a source that is merely aria-disabled still takes the click (it says what is missing)
        const sync = () => {
            button.disabled = source.disabled;
            button.setAttribute('aria-disabled', source.getAttribute('aria-disabled') === 'true' ? 'true' : 'false');
            button.hidden = source.hidden || source.classList.contains('hidden');
            const label = bar.dataset.label ?? source.dataset.barLabel ?? source.textContent?.replace(/\s+/g, ' ').trim() ?? '';
            if (button.textContent !== label) button.textContent = label;
        };
        new MutationObserver(sync).observe(source, { attributes: true, childList: true, subtree: true, characterData: true });
        button.addEventListener('click', () => source.click());
        sync();
    }
    const mirror = (target: HTMLElement | null, origin: HTMLElement | null, suffix = '', flashOnChange = false) => {
        if (!target || !origin) return;
        let first = true;
        const copy = () => {
            const text = origin.textContent?.replace(/\s+/g, ' ').trim() ?? '';
            const empty = text === '' || text === '—' || text === '–' || origin.classList.contains('hidden') || origin.hidden;
            const next = empty ? '' : text + suffix;
            const changed = target.textContent !== next;
            target.textContent = next;
            target.hidden = empty;
            if (changed && !empty && !first && flashOnChange) flash(target);   // the badge of the bar: the number changed out of sight
            first = false;
        };
        new MutationObserver(copy).observe(origin, { childList: true, characterData: true, subtree: true, attributes: true });
        copy();
    };
    mirror(bar.querySelector<HTMLElement>('[data-bar-price]'), bar.dataset.priceFrom ? document.getElementById(bar.dataset.priceFrom) : null, bar.dataset.priceSuffix ?? '', true);
    mirror(bar.querySelector<HTMLElement>('[data-bar-sub]'), bar.dataset.subFrom ? document.getElementById(bar.dataset.subFrom) : null);
    // the page keeps room under its end while the bar is displayed (a phone; or every width when the page says so)
    const size = () => {
        const shown = !bar.hidden && getComputedStyle(bar).display !== 'none';
        root.style.setProperty('--action-bar-h', `${shown ? Math.ceil(bar.getBoundingClientRect().height) : 0}px`);
        document.body.classList.toggle('has-action-bar', shown);
    };
    if (typeof ResizeObserver !== 'undefined') new ResizeObserver(size).observe(bar);
    new MutationObserver(size).observe(bar, { attributes: true, attributeFilter: ['hidden', 'class'] });
    window.addEventListener('resize', size);
    size();
}
