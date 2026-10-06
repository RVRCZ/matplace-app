/**
 * Lithophane / relief from a photo, as a module of the tool page: photo + a few options → the model on the stage
 * (viewer, size, rough price) → the calculator, the farm or a download. The photo itself is never stored.
 */
import type { Stage } from './tool_page';
import { FileInfo } from './api';

interface ReliefCfg { url: string; home: string; files: string; i18n: { working: string; failed: string; not_image: string; photo_again: string } }

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
            else if (!(el instanceof HTMLInputElement && el.type === 'file')) el.value = String(v);
            el.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });
}

function readForm(form: HTMLFormElement): Record<string, unknown> {
    const out: Record<string, unknown> = {};
    form.querySelectorAll<HTMLInputElement>('input[name]').forEach((el) => {
        if (el.type === 'file') return;
        if (el.type === 'checkbox') out[el.name] = el.checked;
        else if (el.type === 'radio') { if (el.checked) out[el.name] = el.value; } else out[el.name] = el.value;
    });
    return out;
}

export function bootRelief(stage: Stage): void {
    const form = document.getElementById('relief-form') as HTMLFormElement | null;
    const cfg = (window as unknown as { MP_RELIEF?: ReliefCfg }).MP_RELIEF;
    if (!form || !cfg) return;
    const photo = document.getElementById('relief-photo') as HTMLInputElement;
    const preview = document.getElementById('relief-preview') as HTMLImageElement;
    const pick = document.getElementById('relief-pick') as HTMLElement;
    const width = document.getElementById('relief-w') as HTMLInputElement;
    const msg = document.getElementById('relief-msg') as HTMLElement;
    const btn = form.querySelector('button') as HTMLButtonElement;

    width.oninput = () => { (document.getElementById('relief-w-val') as HTMLElement).textContent = `${width.value} mm`; };
    const showPhoto = (f: File | undefined): void => {
        if (!f) return;
        if (!f.type.startsWith('image/')) { msg.textContent = cfg.i18n.not_image; photo.value = ''; return; }
        msg.textContent = '';
        preview.src = URL.createObjectURL(f);
        preview.hidden = false; preview.classList.remove('hidden');
        pick.classList.add('hidden');
    };
    photo.onchange = () => showPhoto(photo.files?.[0]);
    const drop = document.getElementById('relief-drop');
    if (drop) {
        drop.ondragover = (e) => { e.preventDefault(); drop.classList.add('border-ink'); };
        drop.ondragleave = () => drop.classList.remove('border-ink');
        drop.ondrop = (e) => { e.preventDefault(); drop.classList.remove('border-ink'); if (e.dataTransfer?.files?.length) { photo.files = e.dataTransfer.files; showPhoto(photo.files[0]); } };
    }

    const commit = stage.track({ read: () => readForm(form), write: (s) => writeForm(form, s) }, !new URLSearchParams(location.search).get('from'));
    form.addEventListener('change', () => commit());
    void restoreForm(form, cfg.files).then((ok) => { if (ok) msg.textContent = cfg.i18n.photo_again; });

    form.onsubmit = async (e) => {
        e.preventDefault();
        if (!photo.files?.[0]) { photo.click(); return; }
        btn.disabled = true;
        msg.textContent = cfg.i18n.working;
        stage.busy(true); stage.error(null);
        const fd = new FormData(form);
        fd.set('frame', fd.has('frame') ? '1' : '0');
        fd.set('invert', fd.has('invert') ? '1' : '0');
        fd.set('stand', fd.has('stand') ? '1' : '0');
        try {
            const res = await fetch(cfg.url, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body: fd });
            const body = await res.json();
            if (!res.ok) throw new Error(body.message ?? 'relief');
            const file = await stage.untilReady(body.file as FileInfo);
            if (file.status !== 'ready') throw new Error('relief');
            // the model stays on this page: seen from all sides, with its size and a first price; the calculator is one click on
            await stage.fileResult(file);
            msg.textContent = '';
            stage.reveal('print');
        } catch {
            msg.textContent = cfg.i18n.failed;
        } finally {
            btn.disabled = false;
            stage.busy(false);
        }
    };
}
