/**
 * The window that opens on the tools which take a picture (charm, key ring, ornament, coaster, ear rings, magnet, badge,
 * bag charm, relief, picture in filament): a big place to hand in the visitor's own picture before anything else, because
 * the way to it through the decoration panel took three clicks (Roman, 21 Nov 2026). Choose a file, drop one on it, paste
 * one from the clipboard or take a photo with the phone (<input capture>), say how many pieces, or close it: the tool
 * then works as it always did, with its own sample picture or text.
 *
 * The window only hands the file over; what is done with it is the tool's own (the modules pass `take`, or `artwork`
 * when the picture goes through the artwork upload and may be redrawn by the server, ArtworkCleaner). The classic cheap
 * clean-up happens here: the photo is turned the way the camera meant it and made at most 2 000 px (shrinkPhoto), so a
 * phone's 8 MB picture passes the 5 MB limit; the background, the colours and the smoothing are the tool's own sliders.
 *
 * Measured (POST /api/step, our statistics, CampaignFunnel::WINDOW): img_seen, img_pick, img_camera, img_close, img_qty.
 */
import type { Stage } from './tool_page';
import type { PickedArtwork } from './artwork';
import { shrinkPhoto, overLimit } from './photo';
import { icon } from '../site/icon';

export interface ImgWinCfg { clean: boolean; clean_url: string; max_mb: number }
export interface ImgWinOptions {
    stage: Stage;
    /** The tool takes the file itself (the relief's photo field); rejects with the words to show when it cannot. */
    take?: (file: File) => Promise<void>;
    /** The picture goes through the artwork upload; `apply` puts a picked (or cleaned) one into the tool. */
    artwork?: { upload: (file: File) => Promise<PickedArtwork>; apply: (picked: PickedArtwork, mode?: string) => Promise<void> | void };
    /**
     * Ways to use the picture (the medal: layered colours, or a relief like a lithophane). With them the picture is NOT put into
     * the tool at once: the window shows one button per way, and `apply` gets the one the visitor took.
     */
    modes?: { id: string; label: string }[];
}

const esc = (s: string): string => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c] as string));
const told = new Set<string>();

/** One measuring step a page (the same names as the tools' other steps: POST /api/step). */
export function windowStep(tool: string, name: string): void {
    if (told.has(name) || (navigator as Navigator & { webdriver?: boolean }).webdriver) return;
    told.add(name);
    const body = new URLSearchParams({ tool, step: name });
    try { if (navigator.sendBeacon?.('/api/step', body)) return; } catch { /* fall through to fetch */ }
    void fetch('/api/step', { method: 'POST', body, keepalive: true, credentials: 'same-origin' }).catch(() => undefined);
}

/**
 * Opens the window unless this visit already says what it wants (a stored design, a wish from the home page, texts in the
 * address): then there is nothing to ask. Does nothing on a tool the server did not list (cfg.imgwin).
 */
export function openImageWindow(o: ImgWinOptions): void {
    const cfg = (o.stage.cfg as unknown as { imgwin?: ImgWinCfg | null }).imgwin;
    if (!cfg || document.getElementById('imgwin') || (navigator as Navigator & { webdriver?: boolean }).webdriver) return;
    const t = o.stage.t;
    const tool = o.stage.cfg.tool;
    const qtyField = document.getElementById('param-qty') as HTMLInputElement | null;
    const touch = (navigator.maxTouchPoints ?? 0) > 0 || window.matchMedia('(pointer: coarse)').matches;

    const d = document.createElement('dialog');
    d.id = 'imgwin';
    d.className = 'tool-dialog';
    d.setAttribute('aria-labelledby', 'imgwin-title');
    d.innerHTML = `<div class="flex max-h-[inherit] flex-col">
        <div class="flex items-start justify-between gap-3 px-5 pt-4">
            <div><h2 id="imgwin-title" class="text-xl font-semibold">${esc(t('imgwin.title'))}</h2>
            <p class="mt-1 text-sm text-muted">${esc(t('imgwin.lead'))}</p></div>
            <button type="button" data-close class="tool-icon-btn shrink-0" aria-label="${esc(t('imgwin.close'))}">${icon('x')}</button>
        </div>
        <div class="overflow-y-auto px-5 py-4">
            <label data-drop class="flex min-h-[11rem] cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-line p-6 text-center hover:border-ink">
                ${icon('upload', 'h-9 w-9 text-muted')}
                <span class="text-base font-semibold">${esc(t('imgwin.drop'))}</span>
                <span class="text-sm text-muted">${esc(t('imgwin.formats'))}</span>
                <input data-file type="file" class="sr-only" accept="image/*,.svg">
            </label>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <button type="button" data-choose class="btn-primary gap-1.5">${icon('upload')}${esc(t('imgwin.choose'))}</button>
                <label data-camera class="btn-secondary cursor-pointer gap-1.5 ${touch ? '' : 'hidden'}">${icon('camera')}${esc(t('imgwin.camera'))}
                    <input data-shot type="file" class="sr-only" accept="image/*" capture="environment"></label>
                <span class="text-xs text-muted">${esc(t('imgwin.paste'))}</span>
            </div>
            <p class="mt-2 text-xs text-muted">${esc(t('imgwin.tip'))}</p>
            <div data-done class="mt-4 hidden items-center gap-3 rounded-xl border border-line p-3"><img data-thumb alt="" class="h-20 w-20 rounded-lg bg-white object-contain"><div class="flex flex-wrap gap-2">${cfg.clean ? `<button type="button" data-clean class="btn-secondary gap-1.5">${esc(t('imgwin.clean'))}</button>` : ''}<span data-modes class="contents"></span><button type="button" data-use class="btn-primary">${esc(t('imgwin.use'))}</button>${cfg.clean ? `<p class="w-full text-xs text-muted">${esc(t('imgwin.clean_hint'))}</p>` : ''}</div></div>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <label class="lbl !mb-0 flex items-center gap-2 text-sm font-medium">${esc(t('imgwin.qty'))}
                    <input data-qty type="number" inputmode="numeric" min="1" max="1000" value="${esc(qtyField?.value || '1')}" class="field !mt-0 block !w-24"></label>
            </div>
            <p data-msg class="mt-3 min-h-[1.25rem] text-sm text-muted" role="status" aria-live="polite"></p>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line px-5 py-3">
            <button type="button" data-close class="text-sm text-muted underline">${esc(t('imgwin.skip'))}</button>
        </div>
    </div>`;
    document.body.appendChild(d);

    const msg = d.querySelector<HTMLElement>('[data-msg]')!;
    const say = (text: string, bad = false): void => { msg.textContent = text; msg.className = `mt-3 min-h-[1.25rem] text-sm ${bad ? 'text-danger' : 'text-muted'}`; };
    let busy = false;
    let finished = false;
    let hasPicked = false;
    const finish = (): void => { finished = true; if (d.open) d.close(); d.remove(); document.removeEventListener('paste', onPaste); };

    /** The cleaned picture of the server: asked for until it is there (about half a minute), the original when it fails. */
    const cleaned = async (ref: string): Promise<string | null> => {
        try {
            const start = await fetch(cfg.clean_url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ artwork: ref }) });
            if (!start.ok) return null;
            const { task } = (await start.json()) as { task: string };
            for (let i = 0; i < 45; i++) {
                await new Promise((r) => window.setTimeout(r, 2500));
                if (finished) return null;
                const res = await fetch(`${cfg.clean_url}/${task}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                const body = (await res.json()) as { state: string; artwork?: string };
                if (body.state === 'done' && body.artwork) return body.artwork;
                if (body.state === 'failed') return null;
            }
        } catch { /* the original stays */ }
        return null;
    };

    /**
     * After the upload: the picture is in the tool already (no `modes`), the window staying for a button to have it redrawn clean
     * and one to go on; or (`modes`) one button per way to use it, the picture put into the tool only when one is taken.
     */
    const offerChoice = (first: PickedArtwork): void => {
        let picked = first;
        hasPicked = true;
        const box = d.querySelector<HTMLElement>('[data-done]')!;
        box.classList.remove('hidden'); box.classList.add('flex');
        const thumb = d.querySelector<HTMLImageElement>('[data-thumb]')!;
        const show = (): void => { thumb.src = picked.url ?? `${o.stage.cfg.artwork.file}/${picked.ref}`; };
        show();
        say('');
        const use = d.querySelector<HTMLElement>('[data-use]')!;
        const modes = d.querySelector<HTMLElement>('[data-modes]')!;
        if (o.modes) {
            use.classList.add('hidden');
            o.modes.forEach((m, i) => {
                const b = document.createElement('button');
                b.type = 'button'; b.className = i === 0 ? 'btn-primary' : 'btn-secondary'; b.textContent = m.label;
                b.onclick = async () => { b.disabled = true; try { await o.artwork!.apply(picked, m.id); finish(); } catch (e) { say((e as Error).message || t('imgwin.failed'), true); b.disabled = false; } };
                modes.appendChild(b);
            });
        } else {
            use.onclick = () => finish();
        }
        const clean = d.querySelector<HTMLButtonElement>('[data-clean]');
        if (clean) clean.onclick = async () => {
            clean.disabled = true; say(t('imgwin.cleaning'));
            const better = await cleaned(picked.ref);
            if (finished) return;
            if (!better) { say(t('imgwin.clean_failed'), true); clean.disabled = false; return; }
            picked = { ref: better, name: picked.name, url: null };
            if (o.modes) { show(); say(''); clean.disabled = false; return; }     // the way to use it is still to be taken
            await o.artwork!.apply(picked);
            finish();
        };
    };

    const handle = async (file: File | undefined | null, via: 'pick' | 'camera'): Promise<void> => {
        if (!file || busy) return;
        if (!file.type.startsWith('image/') && !/\.svg$/i.test(file.name)) { say(t('imgwin.not_image'), true); return; }
        busy = true;
        windowStep(tool, via === 'camera' ? 'img_camera' : 'img_pick');
        say(t('imgwin.sending'));
        try {
            // turned the way the camera meant, 2 000 px at most (a vector picture goes as it is)
            const sent = /\.svg$/i.test(file.name) || file.type === 'image/svg+xml' ? file : await shrinkPhoto(file);
            if (overLimit(sent, cfg.max_mb)) { say(t('imgwin.too_big', { mb: cfg.max_mb }), true); busy = false; return; }
            if (o.artwork) {
                const picked = await o.artwork.upload(sent);
                if (!o.modes) await o.artwork.apply(picked);
                // the redrawing is asked for with a button only, never by itself (it costs a picture task each time)
                if (o.modes || (cfg.clean && /^[0-9a-f-]{36}$/.test(picked.ref))) { offerChoice(picked); busy = false; return; }
            } else if (o.take) {
                await o.take(sent);
            }
            finish();
        } catch (e) {
            say((e as Error).message || t('imgwin.failed'), true);
            busy = false;
        }
    };

    const onPaste = (e: ClipboardEvent): void => {
        const file = [...(e.clipboardData?.files ?? [])].find((f) => f.type.startsWith('image/'));
        if (file) { e.preventDefault(); void handle(file, 'pick'); }
    };
    document.addEventListener('paste', onPaste);

    const drop = d.querySelector<HTMLElement>('[data-drop]')!;
    d.querySelector<HTMLInputElement>('[data-file]')!.onchange = (e) => { const i = e.target as HTMLInputElement; void handle(i.files?.[0], 'pick'); i.value = ''; };
    d.querySelector<HTMLInputElement>('[data-shot]')!.onchange = (e) => { const i = e.target as HTMLInputElement; void handle(i.files?.[0], 'camera'); i.value = ''; };
    d.querySelector<HTMLElement>('[data-choose]')!.onclick = () => d.querySelector<HTMLInputElement>('[data-file]')!.click();
    drop.ondragover = (e) => { e.preventDefault(); drop.classList.add('border-ink'); };
    drop.ondragleave = () => drop.classList.remove('border-ink');
    drop.ondrop = (e) => { e.preventDefault(); drop.classList.remove('border-ink'); void handle(e.dataTransfer?.files?.[0], 'pick'); };

    const qty = d.querySelector<HTMLInputElement>('[data-qty]')!;
    qty.onchange = () => {
        const n = Math.max(1, Math.min(1000, Math.round(Number(qty.value) || 1)));
        qty.value = String(n);
        if (qtyField) { qtyField.value = String(n); qtyField.dispatchEvent(new Event('input', { bubbles: true })); }
        windowStep(tool, 'img_qty');
    };

    // closed without a picture: the tool goes on with its own sample (the cross, the skip link, Esc, a click beside)
    const closed = (): void => { if (!finished) { if (!hasPicked) windowStep(tool, 'img_close'); finish(); } };
    d.addEventListener('cancel', (e) => { e.preventDefault(); closed(); });
    d.addEventListener('click', (e) => { if (e.target === d || (e.target as HTMLElement).closest('[data-close]')) closed(); });

    d.showModal();
    windowStep(tool, 'img_seen');
}
