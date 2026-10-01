/**
 * Small behaviours of the designer's pages and of forms in general:
 *  - [data-copy]           copies its value, shows data-copied for a moment
 *  - [data-toggle="id"]    a checkbox shows / hides the element with that id
 *  - [data-dropzone]       a label around a file input: drag and drop, shows the chosen file's name
 *  - [data-import-form]    counts the ticked models, "select the new ones" up to the limit
 *  - [data-import-status]  polls the progress of an import every 3 s
 *  - [data-reload-in]      reloads the page after N seconds (a file being checked)
 */
function copyButtons(): void {
    document.addEventListener('click', async (e) => {
        const button = e.target instanceof Element ? e.target.closest<HTMLElement>('[data-copy]') : null;
        if (!button) return;
        const text = button.dataset.copy ?? '';
        try {
            await navigator.clipboard.writeText(text);
        } catch {
            const area = document.createElement('textarea');
            area.value = text;
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            area.remove();
        }
        const label = button.textContent;
        button.textContent = button.dataset.copied ?? '✓';
        setTimeout(() => { button.textContent = label; }, 1500);
    });
}

function toggles(): void {
    document.querySelectorAll<HTMLInputElement>('input[data-toggle]').forEach((box) => {
        const target = document.getElementById(box.dataset.toggle as string);
        if (!target) return;
        box.addEventListener('change', () => target.classList.toggle('hidden', !box.checked));
    });
}

function dropzones(): void {
    document.querySelectorAll<HTMLElement>('[data-dropzone]').forEach((zone) => {
        const input = zone.querySelector<HTMLInputElement>('input[type=file]');
        const label = zone.querySelector<HTMLElement>('[data-dropzone-label]');
        if (!input) return;
        const show = (): void => { if (label) label.textContent = input.files?.[0]?.name ?? label.dataset.empty ?? ''; };
        input.addEventListener('change', show);
        ['dragenter', 'dragover'].forEach((type) => zone.addEventListener(type, (e) => { e.preventDefault(); zone.classList.add('border-action'); }));
        ['dragleave', 'drop'].forEach((type) => zone.addEventListener(type, (e) => { e.preventDefault(); zone.classList.remove('border-action'); }));
        zone.addEventListener('drop', (e) => {
            const files = (e as DragEvent).dataTransfer?.files;
            if (!files?.length) return;
            input.files = files;
            show();
        });
    });
}

function importForm(): void {
    const form = document.querySelector<HTMLFormElement>('[data-import-form]');
    if (!form) return;
    const items = (): HTMLInputElement[] => Array.from(form.querySelectorAll<HTMLInputElement>('[data-import-item]:not(:disabled)'));
    const count = form.querySelector<HTMLElement>('[data-import-count]');
    const refresh = (): void => { if (count) count.textContent = String(items().filter((i) => i.checked).length); };
    form.addEventListener('change', refresh);
    form.querySelector<HTMLElement>('[data-import-all]')?.addEventListener('click', (e) => {
        const max = Number((e.currentTarget as HTMLElement).dataset.max ?? 100);
        items().forEach((item, i) => { item.checked = i < max; });
        refresh();
    });
    form.querySelector<HTMLElement>('[data-import-none]')?.addEventListener('click', () => { items().forEach((item) => { item.checked = false; }); refresh(); });
    refresh();
}

interface ImportProgress { status: string; total: number; done: number; failed: number; items: { title?: string | null; url?: string | null; state: string; note?: string | null }[] }

function importStatus(): void {
    const box = document.querySelector<HTMLElement>('[data-import-status]');
    if (!box || box.dataset.done === '1') return;
    const states = JSON.parse(box.dataset.states ?? '{}') as Record<string, string>;
    const notes = JSON.parse(box.dataset.notes ?? '{}') as Record<string, string>;
    const tone: Record<string, string> = { waiting: 'text-muted', imported: 'text-ok', skipped: 'text-slate-600', failed: 'text-red-700' };
    const draw = (p: ImportProgress): void => {
        const finished = p.done + p.failed;
        const numbers = box.querySelector('[data-import-numbers]');
        if (numbers) numbers.textContent = `${finished} / ${p.total}`;
        const bar = box.querySelector<HTMLElement>('[data-import-bar]');
        if (bar) bar.style.width = `${p.total ? Math.round((100 * finished) / p.total) : 100}%`;
        const list = box.querySelector('[data-import-items]');
        if (list) {
            list.replaceChildren(...p.items.map((item) => {
                const li = document.createElement('li');
                li.className = 'flex items-center justify-between gap-3 py-2';
                const name = document.createElement('span');
                name.className = 'min-w-0 truncate';
                name.textContent = item.title ?? item.url ?? '…';
                const state = document.createElement('span');
                state.className = `shrink-0 text-xs font-semibold ${tone[item.state] ?? ''}`;
                state.textContent = (states[item.state] ?? item.state) + (item.note ? ` · ${notes[item.note] ?? item.note}` : '');
                li.append(name, state);
                return li;
            }));
        }
        box.querySelector('[data-import-finished]')?.classList.toggle('hidden', p.status !== 'done');
    };
    const tick = async (): Promise<void> => {
        try {
            const res = await fetch(box.dataset.importStatus as string, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (res.ok) {
                const p = (await res.json()) as ImportProgress;
                draw(p);
                if (p.status === 'done') return;
            }
        } catch { /* try again */ }
        setTimeout(tick, 3000);
    };
    void tick();
}

export function bootDesigner(): void {
    copyButtons();
    toggles();
    dropzones();
    importForm();
    importStatus();
    const reload = document.querySelector<HTMLElement>('[data-reload-in]');
    if (reload) setTimeout(() => location.reload(), Number(reload.dataset.reloadIn ?? 4) * 1000);
}
