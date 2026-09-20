/**
 * "I have a printer, download": pick your printer once, get a slicer project (3MF) with the model placed and the
 * settings filled in; the plain STL stays one click away. The choice is remembered in this browser.
 */
import { FileInfo } from './api';

interface PrinterItem { id: string; model: string; slicer?: string; bed: { x: number; y: number; z: number }; materials: string[] }
interface VendorGroup { vendor: string; printers: PrinterItem[] }
export interface DownloadParams { material: string; quality: string; infill: number; supports: boolean | null; scale: number }

const routes = () => (window as unknown as { MP_ROUTES: Record<string, string> }).MP_ROUTES;
const KEY = 'mp_printer';
let vendors: VendorGroup[] | null = null;
let current: { file: FileInfo; params: () => DownloadParams; dims: () => { x: number; y: number; z: number } | null } | null = null;

const el = <T extends HTMLElement>(id: string) => document.getElementById(id) as T | null;

function remembered(): { vendor: string; id: string } | null {
    try { return JSON.parse(localStorage.getItem(KEY) ?? 'null'); } catch { return null; }
}

async function loadPrinters(): Promise<VendorGroup[]> {
    if (vendors) return vendors;
    const res = await fetch(routes().printers, { headers: { Accept: 'application/json' } });
    vendors = res.ok ? ((await res.json()).vendors as VendorGroup[]) : [];
    return vendors;
}

function selected(): PrinterItem | null {
    const v = el<HTMLSelectElement>('dl-vendor')?.value; const id = el<HTMLSelectElement>('dl-model')?.value;
    return vendors?.find((g) => g.vendor === v)?.printers.find((p) => p.id === id) ?? null;
}

function fillModels(): void {
    const vendorSel = el<HTMLSelectElement>('dl-vendor')!; const modelSel = el<HTMLSelectElement>('dl-model')!;
    const group = vendors?.find((g) => g.vendor === vendorSel.value);
    modelSel.innerHTML = (group?.printers ?? []).map((p) => `<option value="${p.id}">${p.model}</option>`).join('');
    const mem = remembered();
    if (mem && group?.printers.some((p) => p.id === mem.id)) modelSel.value = mem.id;
    refresh();
}

/** Link, remembered choice and the "does it fit" / "material" notes follow the selection and the sliders. */
export function refresh(): void {
    const a = el<HTMLAnchorElement>('dl-project'); const note = el('dl-note');
    if (!a || !current) return;
    const p = selected();
    if (!p) { a.setAttribute('aria-disabled', 'true'); a.removeAttribute('href'); return; }
    try { localStorage.setItem(KEY, JSON.stringify({ vendor: el<HTMLSelectElement>('dl-vendor')!.value, id: p.id })); } catch { /* private mode */ }
    const q = current.params();
    const qs = new URLSearchParams({ printer: p.id, material: q.material, quality: q.quality, infill: String(q.infill), supports: q.supports === null ? 'auto' : q.supports ? '1' : '0', scale: String(q.scale) });
    a.href = `${routes().files}/${current.file.uuid}/project.3mf?${qs}`;
    a.setAttribute('aria-disabled', 'false');
    const how = el('dl-how');
    if (how) how.textContent = (p.slicer === 'prusaslicer' ? how.dataset.prusa : how.dataset.orca) ?? '';
    if (note) {
        const d = current.dims(); const msgs: string[] = [];
        const dims = d ? [d.x, d.y].sort((m, n) => n - m) : null; const bed = [p.bed.x, p.bed.y].sort((m, n) => n - m);
        if (d && dims && (dims[0] > bed[0] || dims[1] > bed[1] || d.z > p.bed.z)) msgs.push(note.dataset.tooBig!.replace(':bed', `${p.bed.x} × ${p.bed.y} × ${p.bed.z} mm`));
        if (!p.materials.includes(q.material)) msgs.push(note.dataset.noMaterial!.replace(':m', q.material));
        note.textContent = msgs.join(' ');
        note.classList.toggle('hidden', msgs.length === 0);
    }
}

export function bootDownload(): void {
    const btn = el<HTMLButtonElement>('cta-download'); const panel = el('download-panel');
    if (!btn || !panel) return;
    btn.onclick = async () => {
        if (btn.getAttribute('aria-disabled') === 'true') return;
        panel.classList.toggle('hidden');
        if (panel.classList.contains('hidden')) return;
        const list = await loadPrinters();
        const vendorSel = el<HTMLSelectElement>('dl-vendor')!;
        if (!list.length) { el('dl-picker')?.classList.add('hidden'); return; }
        if (!vendorSel.options.length) {
            vendorSel.innerHTML = list.map((g) => `<option>${g.vendor}</option>`).join('');
            const mem = remembered();
            if (mem && list.some((g) => g.vendor === mem.vendor)) vendorSel.value = mem.vendor;
            vendorSel.onchange = fillModels;
            el<HTMLSelectElement>('dl-model')!.onchange = refresh;
        }
        fillModels();
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };
}

/** Called by the calculator when a processed file is available. */
export function setDownload(file: FileInfo | null, params: () => DownloadParams, dims: () => { x: number; y: number; z: number } | null): void {
    const btn = el('cta-download'); const stl = el<HTMLAnchorElement>('dl-stl');
    if (!btn || !file?.stl_url) return;
    current = { file, params, dims };
    btn.setAttribute('aria-disabled', 'false');
    if (stl) { stl.href = file.stl_url; stl.setAttribute('download', file.name.replace(/\.[^.]+$/, '') + '.stl'); }
    // a box with a lid: each part on its own
    const parts = el('dl-parts');
    if (parts) {
        const i18n = (window as unknown as { MP_I18N?: Record<string, string> }).MP_I18N ?? {};
        parts.classList.toggle('hidden', !(file.parts ?? []).length);
        parts.innerHTML = (file.parts ?? []).length ? `${i18n['download.parts'] ?? ''}: ` + (file.parts ?? []).map((p) => `<a class="text-teal-700 underline" href="${routes().paramPart}/${file.uuid}/${p}.stl">${i18n[`param.part.${p}`] ?? p}</a>`).join(' · ') : '';
    }
    refresh();
}
