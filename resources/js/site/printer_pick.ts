/**
 * "Download for my printer" outside the calculator (my models, a model's page): any element with
 * data-pick-printer="<file uuid>" opens the dialog from partials/printer_pick.blade.php. The chosen printer is
 * remembered in the browser under the same key as in the calculator.
 *
 * Optional on the button: data-pick-name (shown in the dialog), data-pick-stl (plain STL link),
 * data-pick-query (extra query for the project, e.g. "material=PETG&quality=fine").
 */
interface PrinterItem { id: string; model: string; slicer?: string }
interface VendorGroup { vendor: string; printers: PrinterItem[] }

const KEY = 'mp_printer';
let vendors: VendorGroup[] | null = null;

const el = <T extends HTMLElement>(id: string) => document.getElementById(id) as T | null;

function remembered(): { vendor: string; id: string } | null {
    try { return JSON.parse(localStorage.getItem(KEY) ?? 'null'); } catch { return null; }
}

async function printers(url: string): Promise<VendorGroup[]> {
    if (vendors) return vendors;
    try {
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        vendors = res.ok ? ((await res.json()).vendors as VendorGroup[]) : [];
    } catch { vendors = []; }
    return vendors;
}

export function bootPrinterPick(): void {
    const dialog = el<HTMLDialogElement>('mp-pick');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    const vendorSel = el<HTMLSelectElement>('mp-pick-vendor')!;
    const modelSel = el<HTMLSelectElement>('mp-pick-model')!;
    const go = el<HTMLAnchorElement>('mp-pick-go')!;
    const how = el('mp-pick-how');
    let uuid = '';
    let extra = '';

    const refresh = (): void => {
        const printer = vendors?.find((g) => g.vendor === vendorSel.value)?.printers.find((p) => p.id === modelSel.value) ?? null;
        if (!printer || !uuid) { go.setAttribute('aria-disabled', 'true'); go.removeAttribute('href'); return; }
        try { localStorage.setItem(KEY, JSON.stringify({ vendor: vendorSel.value, id: printer.id })); } catch { /* private mode */ }
        const query = new URLSearchParams(extra);
        query.set('printer', printer.id);
        go.href = `${dialog.dataset.files}/${uuid}/project.3mf?${query}`;
        go.dataset.track = 'download';      // a link, not a fetch: the click itself tells the measuring script
        go.dataset.trackKind = '3mf';
        go.setAttribute('aria-disabled', 'false');
        if (how) how.textContent = (printer.slicer === 'prusaslicer' ? how.dataset.prusa : how.dataset.orca) ?? '';
    };
    const fillModels = (): void => {
        const group = vendors?.find((g) => g.vendor === vendorSel.value);
        modelSel.replaceChildren(...(group?.printers ?? []).map((p) => new Option(p.model, p.id)));
        const mem = remembered();
        if (mem && group?.printers.some((p) => p.id === mem.id)) modelSel.value = mem.id;
        refresh();
    };
    vendorSel.onchange = fillModels;
    modelSel.onchange = refresh;
    el('mp-pick-close')?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });

    document.addEventListener('click', async (e) => {
        const button = e.target instanceof Element ? e.target.closest<HTMLElement>('[data-pick-printer]') : null;
        if (!button) return;
        e.preventDefault();
        uuid = button.dataset.pickPrinter ?? '';
        extra = button.dataset.pickQuery ?? '';
        const name = el('mp-pick-name');
        if (name) name.textContent = button.dataset.pickName ?? '';
        const stl = el<HTMLAnchorElement>('mp-pick-stl');
        if (stl) {
            stl.classList.toggle('hidden', !button.dataset.pickStl);
            stl.href = button.dataset.pickStl ?? '#';
            stl.setAttribute('download', (button.dataset.pickName ?? 'model').replace(/\.[^.]+$/, '') + '.stl');
        }
        dialog.showModal();
        const list = await printers(dialog.dataset.printers as string);
        el('mp-pick-form')?.classList.toggle('hidden', !list.length);
        el('mp-pick-none')?.classList.toggle('hidden', list.length > 0);
        go.classList.toggle('hidden', !list.length);
        if (!list.length) return;
        if (!vendorSel.options.length) {
            vendorSel.replaceChildren(...list.map((g) => new Option(g.vendor, g.vendor)));
            const mem = remembered();
            if (mem && list.some((g) => g.vendor === mem.vendor)) vendorSel.value = mem.vendor;
        }
        fillModels();
    });
}
