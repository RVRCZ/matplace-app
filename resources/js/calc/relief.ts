/**
 * Lithophane / relief from a photo, as a module of the tool page: photo + shape + size + light → the model on the stage
 * (viewer, size, rough price) → the calculator, the farm or a download. The photo itself is never stored. Three things
 * to make: a photo panel (a plate in one of seven shapes, with a frame, a hole or an eyelet, a stand), a lamp (the
 * photo round a tube with a floor for the socket) and a relief for the wall. The photo's sliders show on the small
 * picture at once; the model follows when it is built. The lithophane previews backlit (thin = bright) or as a surface.
 */
import type { Stage } from './tool_page';
import { FileInfo } from './api';
import { pickArtwork } from './artwork';
import { loadGeometryFromUrl } from './loaders';

interface ReliefCfg { url: string; home: string; files: string; i18n: Record<string, string> }
type Make = 'panel' | 'lamp' | 'wall';

/** "?from=<uuid>": put the saved settings of an earlier design back into the form. */
async function restoreForm(form: HTMLFormElement, filesUrl: string): Promise<boolean> {
    const from = new URLSearchParams(location.search).get('from');
    if (!from || !/^[0-9a-f-]{36}$/.test(from)) return false;
    try {
        const res = await fetch(`${filesUrl}/${from}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const p = ((await res.json()).file?.tool?.params ?? null) as Record<string, unknown> | null;
        if (!p) return false;
        writeForm(form, p);
        return true;
    } catch { return false; }
}

function writeForm(form: HTMLFormElement, p: Record<string, unknown>): void {
    Object.entries(p).forEach(([k, v]) => {
        form.querySelectorAll<HTMLInputElement | HTMLSelectElement>(`[name="${k}"]`).forEach((el) => {
            if (el instanceof HTMLInputElement && el.type === 'checkbox') el.checked = Boolean(v);
            else if (el instanceof HTMLInputElement && el.type === 'radio') el.checked = el.value === String(v);
            else if (!(el instanceof HTMLInputElement && el.type === 'file')) el.value = typeof v === 'boolean' ? (v ? '2' : '0') : String(v ?? '');
            el.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });
    // what was made follows from the shape and the mode; the shape chips mirror the hidden shape field
    const shape = String(p.shape ?? 'rect');
    const make = form.querySelector<HTMLInputElement>(`[name="make"][value="${shape === 'cylinder' ? 'lamp' : p.mode === 'relief' ? 'wall' : 'panel'}"]`);
    if (make) make.checked = true;
    const chip = form.querySelector<HTMLInputElement>(`[name="shape-pick"][value="${shape}"]`);
    if (chip) chip.checked = true;
    form.dispatchEvent(new Event('change', { bubbles: true }));
}

function readForm(form: HTMLFormElement): Record<string, unknown> {
    const out: Record<string, unknown> = {};
    form.querySelectorAll<HTMLInputElement>('input[name]').forEach((el) => {
        if (el.type === 'file' || el.name === 'shape-pick') return;
        if (el.type === 'checkbox') out[el.name] = el.checked;
        else if (el.type === 'radio') { if (el.checked) out[el.name] = el.value; } else out[el.name] = el.value;
    });
    return out;
}

export function bootRelief(stage: Stage): void {
    const form = document.getElementById('relief-form') as HTMLFormElement | null;
    const cfg = (window as unknown as { MP_RELIEF?: ReliefCfg }).MP_RELIEF;
    if (!form || !cfg) return;
    const $ = <T extends HTMLElement>(id: string) => document.getElementById(id) as T;
    const t = (k: string, r: Record<string, string | number> = {}) => Object.entries(r).reduce((s, [a, b]) => s.split(`:${a}`).join(String(b)), cfg.i18n[k] ?? k);
    const nf = stage.nf;
    const photo = $('relief-photo') as HTMLInputElement;
    const preview = $('relief-preview') as HTMLImageElement;
    const pick = $('relief-pick');
    const width = $('relief-w') as HTMLInputElement; const height = $('relief-h') as HTMLInputElement; const frame = $('relief-f') as HTMLInputElement;
    const msg = $('relief-msg');
    const btn = form.querySelector('button.btn-ink') as HTMLButtonElement;
    const shapeField = form.querySelector<HTMLInputElement>('[name="shape"]')!; const modeField = form.querySelector<HTMLInputElement>('[name="mode"]')!;
    let ratio = 0.75;                                    // the photo's height / width
    let made: FileInfo | null = null;
    let lit = true;

    const make = (): Make => (form.querySelector<HTMLInputElement>('[name="make"]:checked')?.value ?? 'panel') as Make;
    const shapePick = (): string => form.querySelector<HTMLInputElement>('[name="shape-pick"]:checked')?.value ?? 'rect';
    const num = (name: string): number => Number(form.querySelector<HTMLInputElement>(`[name="${name}"]`)?.value ?? 0);

    /** What is made decides the fields shown: a panel has shapes, hanging and a stand; a lamp has a socket. */
    const apply = (): void => {
        const m = make();
        modeField.value = m === 'wall' ? 'relief' : 'lithophane';
        shapeField.value = m === 'lamp' ? 'cylinder' : shapePick();
        form.querySelectorAll<HTMLElement>('[data-relief]').forEach((el) => el.classList.toggle('hidden', !el.dataset.relief!.split(' ').includes(m)));
        $('relief-silhouette').classList.toggle('hidden', m === 'lamp' || shapePick() !== 'custom');
        width.max = m === 'lamp' ? '400' : '250';
        // a circle is as high as wide: nothing to set
        const round = m !== 'lamp' && shapePick() === 'circle';
        height.closest('label')!.classList.toggle('hidden', round);
        $('relief-ratio').closest('p')!.classList.toggle('hidden', round);
        $('relief-w-val').textContent = `${width.value} mm`;
        $('relief-h-val').textContent = Number(height.value) > 0 ? `${height.value} mm` : t('height_auto');
        $('relief-f-val').textContent = `${nf.format(Number(frame.value))} mm`;
        form.querySelectorAll<HTMLInputElement>('input[type="range"][data-unit]').forEach((r) => {
            const out = form.querySelector<HTMLElement>(`[data-val="${r.name}"]`);
            if (out) out.textContent = `${nf.format(Number(r.value))}${r.dataset.unit ?? ''}`;
        });
        const lo = num('min_thickness'); const hi = Math.max(lo + 0.4, num('max_thickness'));
        $('relief-shades').textContent = t('shades', { n: Math.round((hi - lo) / 0.2) + 1, a: nf.format(lo), b: nf.format(hi) });
        adjustThumb();
    };
    /** The photo's own sliders show on the small picture at once (the midtones only roughly, as brightness). */
    const adjustThumb = (): void => {
        const g = num('gamma'); const inv = form.querySelector<HTMLInputElement>('[name="invert"]')?.checked;
        preview.style.filter = `contrast(${100 + num('contrast')}%) brightness(${100 + num('brightness') + (g - 1) * 40}%)${inv ? ' invert(1)' : ''}`;
    };

    const showPhoto = (f: File | undefined): void => {
        if (!f) return;
        if (!f.type.startsWith('image/')) { msg.textContent = cfg.i18n.not_image; photo.value = ''; return; }
        msg.textContent = '';
        preview.src = URL.createObjectURL(f);
        preview.onload = () => { ratio = preview.naturalWidth ? preview.naturalHeight / preview.naturalWidth : 0.75; apply(); };
        preview.hidden = false; preview.classList.remove('hidden');
        pick.classList.add('hidden');
    };
    photo.onchange = () => showPhoto(photo.files?.[0]);
    stage.dropZone($('relief-drop'), photo, (f) => { photo.files = (() => { const dt = new DataTransfer(); dt.items.add(f); return dt.files; })(); showPhoto(f); });

    // the silhouette of a custom shape: a picture of the library or an upload, by its reference
    const artOpen = document.getElementById('relief-artwork-open');
    if (artOpen) artOpen.onclick = async () => {
        const picked = await pickArtwork();
        if (!picked) return;
        form.querySelector<HTMLInputElement>('[name="silhouette"]')!.value = picked.ref;
        const lib = /^lib:(.+)$/.exec(picked.ref); const own = /^[0-9a-f-]{36}$/.test(picked.ref);
        const url = lib ? `${stage.cfg.artwork.library}/${lib[1]}.svg` : own ? `${stage.cfg.artwork.file}/${picked.ref}` : picked.url;
        const thumb = $('relief-artwork-thumb');
        thumb.innerHTML = url ? `<img src="${url.replace(/"/g, '&quot;')}" alt="" class="max-h-full max-w-full object-contain">` : '';
        thumb.classList.toggle('hidden', !url); thumb.classList.toggle('flex', !!url);
        $('relief-artwork-state').textContent = stage.t('toolpage.artwork.used', { name: picked.name });
        commit();
    };
    $('relief-ratio').onclick = () => { height.value = '0'; apply(); commit(); };

    const commit = stage.track({ read: () => readForm(form), write: (s) => writeForm(form, s) }, !new URLSearchParams(location.search).get('from'));
    form.addEventListener('input', apply);
    form.addEventListener('change', () => { apply(); commit(); });
    apply();
    void restoreForm(form, cfg.files).then((ok) => { if (ok) msg.textContent = cfg.i18n.photo_again; });

    // backlit or surface: the same model painted two ways
    $('relief-backlit').querySelectorAll<HTMLButtonElement>('[data-lit]').forEach((b) => b.addEventListener('click', async () => {
        lit = b.dataset.lit === '1';
        $('relief-backlit').querySelectorAll('[data-lit]').forEach((o) => { o.setAttribute('aria-pressed', o === b ? 'true' : 'false'); o.classList.toggle('chip-on', o === b); });
        if (made?.stl_url) stage.show(await loadGeometryFromUrl(made.stl_url), { kind: lit ? 'lithophane' : 'relief' });
    }));

    form.onsubmit = async (e) => {
        e.preventDefault();
        if (!photo.files?.[0]) { photo.click(); return; }
        if (make() !== 'lamp' && shapePick() === 'custom' && !form.querySelector<HTMLInputElement>('[name="silhouette"]')!.value) { msg.textContent = t('silhouette'); return; }
        btn.disabled = true;
        msg.textContent = cfg.i18n.working;
        stage.busy(true); stage.error(null);
        const fd = new FormData(form);
        fd.delete('shape-pick'); fd.delete('make');
        fd.set('invert', fd.has('invert') ? '1' : '0');
        fd.set('stand', fd.has('stand') && make() !== 'lamp' ? '1' : '0');
        try {
            const res = await fetch(cfg.url, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body: fd });
            const body = await res.json();
            if (!res.ok) throw new Error(cfg.i18n[String(body.reason ?? '')] ?? body.message ?? 'relief');
            const file = await stage.untilReady(body.file as FileInfo);
            if (file.status !== 'ready') throw new Error('relief');
            made = file;
            const backlit = file.kind === 'lithophane' && make() !== 'lamp';
            // the model stays on this page: seen from all sides, with its size and a first price; the calculator is one click on
            await stage.fileResult(file, { kind: make() === 'lamp' ? 'lamp' : backlit && lit ? 'lithophane' : 'relief' });
            const r = (file.tool?.params ?? {}) as { report?: { width: number; height: number; thickness: number; min_thickness: number; shades: number; diameter?: number; circumference?: number } };
            if (r.report) stage.note(make() === 'lamp' ? t('fact_lamp', { d: nf.format(r.report.diameter ?? 0), h: nf.format(r.report.height), c: nf.format(r.report.circumference ?? 0), a: nf.format(r.report.min_thickness), t: nf.format(r.report.thickness), s: r.report.shades }) : t('fact', { w: nf.format(r.report.width), h: nf.format(r.report.height), t: nf.format(r.report.thickness), s: r.report.shades }));
            $('relief-backlit').classList.toggle('hidden', !backlit);
            msg.textContent = make() === 'lamp' && file.kind === 'lithophane' ? t('backlit_lamp') : '';
            stage.reveal('print');
        } catch (err) {
            msg.textContent = (err as Error).message && (err as Error).message !== 'relief' ? (err as Error).message : cfg.i18n.failed;
        } finally {
            btn.disabled = false;
            stage.busy(false);
        }
    };
}
