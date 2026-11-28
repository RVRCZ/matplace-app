/**
 * The video studio (/admin/youtube/orders/{order}, docs/AL.md §13.10): the media list is dragged (or moved with the
 * arrows) into the order the videos show it, every switch and number is saved at once, and while the server builds the
 * videos again the page says so and reloads the players when the new files are there.
 */

function csrf(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

async function send(url: string, body: Record<string, unknown>): Promise<Response> {
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify(body),
    });
}

/** The ids of the list items as they stand now. */
export function orderOf(list: HTMLElement): number[] {
    return Array.from(list.querySelectorAll<HTMLElement>('[data-media-id]')).map((li) => Number(li.dataset.mediaId));
}

/** Moves an item one place up (-1) or down (+1); false when it is at the end already. */
export function moveItem(item: HTMLElement, step: number): boolean {
    const sibling = step < 0 ? item.previousElementSibling : item.nextElementSibling;
    if (!sibling) return false;
    if (step < 0) sibling.before(item);
    else sibling.after(item);
    return true;
}

let poll: number | undefined;

function watchState(): void {
    const badge = document.querySelector<HTMLElement>('[data-studio-state]');
    if (!badge?.dataset.stateUrl) return;
    const texts = JSON.parse(badge.dataset.texts ?? '{}') as Record<string, string>;
    const seen: Record<string, number | null> = {};
    const tick = async () => {
        try {
            const r = await fetch(badge.dataset.stateUrl ?? '', { headers: { Accept: 'application/json' } });
            const s = (await r.json()) as { state: string; at: string | null; errors: Record<string, string>; files: Record<string, number | null> };
            const errors = Object.values(s.errors ?? {});
            badge.textContent = (texts[s.state] ?? '') + (errors.length ? ' · ' + errors.join(' ') : '');
            badge.dataset.state = s.state;
            for (const [kind, time] of Object.entries(s.files ?? {})) {
                const player = document.querySelector<HTMLVideoElement>(`[data-studio-player="${kind}"]`);
                if (player && time && seen[kind] !== undefined && seen[kind] !== time) {
                    player.src = player.src.replace(/\?v=\d*/, '') + '?v=' + time;
                    player.load();
                }
                seen[kind] = time;
            }
            if (s.state === 'waiting' || s.state === 'building') {
                poll = window.setTimeout(tick, 4000);
            } else {
                poll = undefined;
            }
        } catch {
            poll = window.setTimeout(tick, 10000);
        }
    };
    if (poll) window.clearTimeout(poll);
    void tick();
}

function changed(): void {
    const badge = document.querySelector<HTMLElement>('[data-studio-state]');
    if (badge) {
        badge.textContent = JSON.parse(badge.dataset.texts ?? '{}').waiting ?? '';
        badge.dataset.state = 'waiting';
    }
    window.setTimeout(watchState, 1500);
}

export function bootVideoStudio(): void {
    const list = document.querySelector<HTMLElement>('[data-studio-media]');
    if (!list) return;
    watchState();
    if (list.hasAttribute('data-locked')) return;
    const saveOrder = async () => {
        await send(list.dataset.orderUrl ?? '', { ids: orderOf(list) });
        changed();
    };

    let dragged: HTMLElement | null = null;
    list.addEventListener('dragstart', (e) => {
        dragged = (e.target as HTMLElement).closest<HTMLElement>('[data-media-id]');
        if (dragged && e.dataTransfer) {
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', dragged.dataset.mediaId ?? '');
        }
        dragged?.classList.add('opacity-50');
    });
    list.addEventListener('dragover', (e) => {
        const over = (e.target as HTMLElement).closest<HTMLElement>('[data-media-id]');
        if (!dragged || !over || over === dragged) return;
        e.preventDefault();
        const box = over.getBoundingClientRect();
        if (e.clientY < box.top + box.height / 2) over.before(dragged);
        else over.after(dragged);
    });
    list.addEventListener('dragend', () => {
        dragged?.classList.remove('opacity-50');
        dragged = null;
        void saveOrder();
    });

    list.querySelectorAll<HTMLElement>('[data-media-id]').forEach((item) => {
        item.querySelectorAll<HTMLButtonElement>('[data-move]').forEach((button) => button.addEventListener('click', () => {
            if (moveItem(item, Number(button.dataset.move))) {
                button.focus();
                void saveOrder();
            }
        }));
        item.querySelectorAll<HTMLInputElement>('[data-media-field]').forEach((input) => input.addEventListener('change', async () => {
            const field = input.dataset.mediaField ?? '';
            const value = input.type === 'checkbox' ? input.checked : (input.value === '' ? null : Number(input.value));
            const r = await send(item.dataset.updateUrl ?? '', { [field]: value });
            if (!r.ok) {
                input.setCustomValidity('Neuloženo');
                input.reportValidity();
                return;
            }
            input.setCustomValidity('');
            if (field === 'included') {
                item.classList.toggle('opacity-70', !input.checked);
                item.classList.toggle('border-dashed', !input.checked);
            }
            changed();
        }));
        item.querySelector<HTMLButtonElement>('[data-media-delete]')?.addEventListener('click', async () => {
            if (!window.confirm('Smazat tuto položku z videí?')) return;
            const r = await send(item.dataset.deleteUrl ?? '', {});
            if (r.ok) {
                item.remove();
                changed();
            }
        });
    });
}
