/**
 * The studio pages of session 4: a picture from a description (/tools/image), the texts of a listing
 * (/tools/listing) and a product photo with its background taken off and put on a backdrop (/tools/photo). They
 * share the layout and the payload of the selling pages (window.MP_SELL, resources/views/tools/sell.blade.php) and
 * are started from sell.ts. The photo page composes and saves the pictures in the browser: the server only cuts.
 */
interface Base { locale: string; i18n: Record<string, string>; available?: boolean }

const esc = (s: string): string => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '"': '&quot;', '>': '&gt;' }[c] as string));
const t = (p: Base, key: string, r: Record<string, string | number> = {}): string => Object.entries(r).reduce((s, [a, b]) => s.split(`:${a}`).join(String(b)), p.i18n[`sell.${key}`] ?? key);
const csrf = (): string => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
const picked = (form: HTMLFormElement, name: string, fallback: string): string => form.querySelector<HTMLInputElement>(`[name="${name}"]:checked`)?.value ?? fallback;
const errorOf = async (res: Response): Promise<string> => { try { return String(((await res.json()) as { error?: string }).error ?? ''); } catch { return ''; } };

async function copy(text: string, button: HTMLElement, p: Base): Promise<void> {
    try { await navigator.clipboard.writeText(text); } catch {
        const area = document.createElement('textarea'); area.value = text; document.body.appendChild(area); area.select(); document.execCommand('copy'); area.remove();
    }
    const label = button.textContent; button.textContent = t(p, 'listing.copied'); setTimeout(() => { button.textContent = label; }, 1500);
}

/** Settings of a page remembered in this browser (the chips and the texts named), applied back on the next visit. */
function remember(key: string, form: HTMLFormElement, names: string[]): void {
    try {
        const saved = JSON.parse(localStorage.getItem(key) ?? '{}') as Record<string, string | string[]>;
        names.forEach((n) => {
            const v = saved[n]; if (v === undefined) return;
            form.querySelectorAll<HTMLInputElement>(`[name="${n}"]`).forEach((el) => {
                if (el.type === 'radio') el.checked = el.value === v; else if (el.type === 'checkbox') el.checked = Array.isArray(v) && v.includes(el.value); else if (typeof v === 'string') el.value = v;
            });
        });
    } catch { /* nothing remembered */ }
    form.addEventListener('change', () => {
        const out: Record<string, string | string[]> = {};
        names.forEach((n) => {
            const els = Array.from(form.querySelectorAll<HTMLInputElement>(`[name="${n}"]`)); if (!els.length) return;
            if (els[0].type === 'checkbox') out[n] = els.filter((e) => e.checked).map((e) => e.value); else if (els[0].type === 'radio') out[n] = els.find((e) => e.checked)?.value ?? ''; else out[n] = els[0].value;
        });
        try { localStorage.setItem(key, JSON.stringify(out)); } catch { /* full or forbidden */ }
    });
}

// ── a picture from a description ──────────────────────────────────────────────────────────────────

export function bootImage(p: Base & { make?: string; left?: number | null }): void {
    const form = document.getElementById('sell-form') as HTMLFormElement | null;
    if (!form || !p.make) return;
    const prompt = document.getElementById('image-prompt') as HTMLTextAreaElement;
    const go = document.getElementById('image-go') as HTMLButtonElement;
    const leftEl = document.getElementById('image-left')!; const msg = document.getElementById('image-msg')!; const stage = document.getElementById('image-stage')!;
    const actions = document.getElementById('image-actions')!; const download = document.getElementById('image-download') as HTMLAnchorElement;
    const use = document.getElementById('image-use')!; const history = document.getElementById('image-history')!; const grid = document.getElementById('image-history-grid')!;
    const made: { url: string; name: string; prompt: string }[] = [];
    let busy = false;
    const showLeft = (left: number | null | undefined): void => {
        leftEl.textContent = left === null ? t(p, 'image.left.site') : left === undefined ? '' : left <= 0 ? t(p, 'image.left.none') : t(p, 'image.left', { n: left });
        go.disabled = busy || !p.available || left === null || (left !== undefined && left <= 0);
    };
    remember('studio.image', form, ['style', 'size']);
    showLeft(p.left);
    const show = (url: string, name: string, text: string): void => {
        stage.innerHTML = `<img src="${esc(url)}" alt="${esc(text)}" class="max-h-full max-w-full object-contain">`;
        download.href = url; download.setAttribute('download', name);
        actions.classList.remove('hidden'); actions.classList.add('flex'); use.classList.remove('hidden');
    };
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = prompt.value.trim();
        if (text.length < 3) { msg.textContent = t(p, 'image.f.prompt.short'); prompt.focus(); return; }
        busy = true; go.disabled = true; msg.textContent = '';
        stage.innerHTML = `<span class="animate-pulse">${esc(t(p, 'image.busy'))}</span>`;
        try {
            const res = await fetch(p.make!, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ prompt: text, style: picked(form, 'style', 'silhouette'), size: picked(form, 'size', 'square') }) });
            if (!res.ok) {
                const err = await errorOf(res);
                msg.textContent = t(p, err === 'daily_limit' ? 'image.left.none' : err === 'site_limit' ? 'image.left.site' : err === 'unavailable' ? 'image.unavailable' : 'image.failed');
                stage.innerHTML = esc(t(p, made.length ? 'image.failed' : 'image.empty'));
                busy = false; showLeft(err === 'daily_limit' ? 0 : err === 'site_limit' ? null : undefined);
                return;
            }
            const body = (await res.json()) as { url: string; name: string; left: number | null };
            made.unshift({ url: body.url, name: body.name, prompt: text });
            show(body.url, body.name, text);
            if (made.length > 1) {
                history.classList.remove('hidden');
                grid.innerHTML = made.map((m, i) => `<button type="button" data-made="${i}" class="aspect-square overflow-hidden rounded-lg border border-line bg-white" title="${esc(m.prompt)}"><img src="${esc(m.url)}" alt="" class="h-full w-full object-contain"></button>`).join('');
                grid.querySelectorAll<HTMLButtonElement>('[data-made]').forEach((b) => b.addEventListener('click', () => { const m = made[Number(b.dataset.made)]; if (m) show(m.url, m.name, m.prompt); }));
            }
            busy = false; showLeft(body.left);
        } catch {
            msg.textContent = t(p, 'image.failed'); stage.innerHTML = esc(t(p, 'image.empty')); busy = false; showLeft(undefined);
        }
    });
    document.getElementById('image-again')?.addEventListener('click', () => { prompt.focus(); prompt.select(); });
}

// ── the texts of a listing ─────────────────────────────────────────────────────────────────────────

interface Listing { title: string; description: string; tags: string[]; materials: string[]; keywords: string[]; alt: string; platform: string; left: number | null }

export function bootListing(p: Base & { write?: string; left?: number | null; limits?: Record<string, { title: number; tags: number; tag: number }> }): void {
    const form = document.getElementById('sell-form') as HTMLFormElement | null;
    if (!form || !p.write) return;
    const what = document.getElementById('listing-what') as HTMLTextAreaElement;
    const go = document.getElementById('listing-go') as HTMLButtonElement;
    const leftEl = document.getElementById('listing-left')!; const msg = document.getElementById('listing-msg')!;
    const empty = document.getElementById('listing-empty')!; const result = document.getElementById('listing-result')!; const copyAll = document.getElementById('listing-copy-all')!;
    let busy = false; let last: Listing | null = null;
    const showLeft = (left: number | null | undefined): void => {
        leftEl.textContent = left === null ? t(p, 'listing.left.site') : left === undefined ? '' : left <= 0 ? t(p, 'listing.left.none') : t(p, 'listing.left', { n: left });
        go.disabled = busy || !p.available || left === null || (left !== undefined && left <= 0);
    };
    remember('studio.listing', form, ['platform', 'language', 'tone', 'materials']);
    showLeft(p.left);
    const block = (key: string, text: string, extra = ''): string => `<div><div class="flex items-baseline justify-between gap-2"><div class="text-xs font-medium uppercase tracking-wide text-muted">${esc(t(p, `listing.r.${key}`))}${extra}</div><button type="button" data-copy="${key}" class="text-xs underline">${esc(t(p, 'listing.copy'))}</button></div><div class="mt-1 whitespace-pre-wrap text-ink" data-text="${key}">${esc(text)}</div></div>`;
    const chips = (key: string, items: string[]): string => `<div><div class="flex items-baseline justify-between gap-2"><div class="text-xs font-medium uppercase tracking-wide text-muted">${esc(t(p, `listing.r.${key}`))} <span class="num font-normal">(${items.length})</span></div><button type="button" data-copy="${key}" class="text-xs underline">${esc(t(p, 'listing.copy'))}</button></div><div class="mt-1 flex flex-wrap gap-1" data-text="${key}">${items.map((i) => `<span class="rounded-md bg-page px-2 py-0.5 text-xs text-ink">${esc(i)}</span>`).join('')}</div></div>`;
    const textOf = (l: Listing, key: string): string => ({ title: l.title, description: l.description, tags: l.tags.join(', '), materials: l.materials.join(', '), keywords: l.keywords.join(', '), alt: l.alt }[key] ?? '');
    const render = (l: Listing): void => {
        const lim = p.limits?.[l.platform];
        result.innerHTML = [
            block('title', l.title, lim ? ` <span class="num font-normal">${l.title.length}/${lim.title}</span>` : ''),
            block('description', l.description),
            chips('tags', l.tags), chips('materials', l.materials), chips('keywords', l.keywords),
            l.alt ? block('alt', l.alt) : '',
        ].join('');
        result.querySelectorAll<HTMLButtonElement>('[data-copy]').forEach((b) => b.addEventListener('click', () => void copy(textOf(l, b.dataset.copy!), b, p)));
        empty.classList.add('hidden'); result.classList.remove('hidden'); copyAll.classList.remove('hidden');
    };
    copyAll.addEventListener('click', () => { if (last) void copy(['title', 'description', 'tags', 'materials', 'keywords', 'alt'].map((k) => `${t(p, `listing.r.${k}`)}:\n${textOf(last!, k)}`).join('\n\n'), copyAll, p); });
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (what.value.trim().length < 3) { msg.textContent = t(p, 'listing.f.what.short'); what.focus(); return; }
        const fd = new FormData();
        fd.append('what', what.value.trim());
        form.querySelectorAll<HTMLInputElement>('[name="materials"]:checked').forEach((c) => fd.append('materials[]', c.value));
        ['size', 'colours', 'audience'].forEach((n) => { const v = form.querySelector<HTMLInputElement>(`[name="${n}"]`)?.value.trim(); if (v) fd.append(n, v); });
        fd.append('platform', picked(form, 'platform', 'etsy')); fd.append('language', picked(form, 'language', p.locale)); fd.append('tone', picked(form, 'tone', 'plain'));
        const photo = (document.getElementById('listing-photo') as HTMLInputElement | null)?.files?.[0];
        if (photo) { if (photo.size > 12 * 1024 * 1024) { msg.textContent = t(p, 'listing.f.photo.big'); return; } fd.append('photo', photo); }
        busy = true; go.disabled = true; msg.textContent = t(p, 'listing.busy');
        try {
            const res = await fetch(p.write!, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: fd });
            if (!res.ok) {
                const err = await errorOf(res);
                msg.textContent = t(p, err === 'daily_limit' ? 'listing.left.none' : err === 'site_limit' ? 'listing.left.site' : err === 'unavailable' ? 'listing.unavailable' : 'listing.failed');
                busy = false; showLeft(err === 'daily_limit' ? 0 : err === 'site_limit' ? null : undefined);
                return;
            }
            last = (await res.json()) as Listing;
            render(last); msg.textContent = ''; busy = false; showLeft(last.left);
        } catch { msg.textContent = t(p, 'listing.failed'); busy = false; showLeft(undefined); }
    });
}

// ── a product photo on a backdrop ─────────────────────────────────────────────────────────────────

interface Shot { name: string; url: string; img: HTMLImageElement | null; status: 'cutting' | 'ready' | 'failed'; error: string }
interface Look { backdrop: string; shadow: string; fill: number; position: string }

export function bootPhoto(p: Base & { cut?: string; max_photos?: number; max_mb?: number; textures?: Record<string, string> }): void {
    const form = document.getElementById('sell-form') as HTMLFormElement | null;
    if (!form || !p.cut) return;
    const input = document.getElementById('photo-input') as HTMLInputElement; const drop = document.getElementById('photo-drop')!;
    const status = document.getElementById('photo-status')!; const msg = document.getElementById('photo-msg')!;
    const stageEmpty = document.getElementById('photo-empty')!; const canvas = document.getElementById('photo-canvas') as HTMLCanvasElement; const thumbs = document.getElementById('photo-thumbs')!;
    const dl = document.getElementById('photo-download') as HTMLButtonElement; const dlAll = document.getElementById('photo-download-all') as HTMLButtonElement;
    const fillRange = document.getElementById('photo-fill') as HTMLInputElement; const fillValue = document.getElementById('photo-fill-v')!;
    const maxPhotos = p.max_photos ?? 6; const maxBytes = (p.max_mb ?? 12) * 1024 * 1024;
    const shots: Shot[] = []; let current = -1; let queue = Promise.resolve();
    const textures = new Map<string, Promise<HTMLImageElement | null>>();
    remember('studio.photo', form, ['backdrop', 'shadow', 'fill', 'position', 'out_size', 'format']);
    fillValue.textContent = `${fillRange.value} %`;

    const look = (): Look => ({ backdrop: picked(form, 'backdrop', 'white'), shadow: picked(form, 'shadow', 'soft'), fill: Number(fillRange.value) || 80, position: picked(form, 'position', 'centre') });
    const texture = (key: string): Promise<HTMLImageElement | null> => {
        const url = p.textures?.[key]; if (!url) return Promise.resolve(null);
        if (!textures.has(key)) textures.set(key, new Promise((resolve) => { const im = new Image(); im.onload = () => resolve(im); im.onerror = () => resolve(null); im.src = url; }));
        return textures.get(key)!;
    };
    const load = (url: string): Promise<HTMLImageElement> => new Promise((resolve, reject) => { const im = new Image(); im.onload = () => resolve(im); im.onerror = () => reject(new Error('load')); im.src = url; });

    /** The composition at one size: the backdrop, a soft shadow under the thing, the thing scaled to the fill. */
    const draw = async (target: HTMLCanvasElement, shot: Shot, size: number, l: Look, opaque: boolean): Promise<void> => {
        const ctx = target.getContext('2d'); if (!ctx || !shot.img) return;
        target.width = size; target.height = size;
        ctx.clearRect(0, 0, size, size);
        if (l.backdrop === 'white' || (l.backdrop === 'transparent' && opaque)) { ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, size, size); }
        else if (l.backdrop === 'gradient') { const g = ctx.createRadialGradient(size / 2, size * 0.4, size * 0.1, size / 2, size / 2, size * 0.8); g.addColorStop(0, '#fbfbfa'); g.addColorStop(1, '#d6d3cd'); ctx.fillStyle = g; ctx.fillRect(0, 0, size, size); }
        else if (l.backdrop !== 'transparent') { const im = await texture(l.backdrop); if (im) ctx.drawImage(im, 0, 0, size, size); else { ctx.fillStyle = '#f0ede8'; ctx.fillRect(0, 0, size, size); } }
        const w = shot.img.naturalWidth; const h = shot.img.naturalHeight;
        const k = (size * l.fill / 100) / Math.max(w, h); const dw = w * k; const dh = h * k;
        const x = (size - dw) / 2; const y = l.position === 'bottom' ? size * 0.93 - dh : (size - dh) / 2;
        if (l.shadow !== 'none') {
            const strong = l.shadow === 'strong';
            const cx = x + dw / 2; const cy = y + dh - dh * 0.02; const rx = dw * (strong ? 0.5 : 0.46); const ry = Math.max(size * 0.01, dw * (strong ? 0.09 : 0.06));
            const g = ctx.createRadialGradient(cx, cy, 0, cx, cy, rx);
            g.addColorStop(0, `rgba(20,16,10,${strong ? 0.5 : 0.32})`); g.addColorStop(0.6, `rgba(20,16,10,${strong ? 0.22 : 0.12})`); g.addColorStop(1, 'rgba(20,16,10,0)');
            ctx.save(); ctx.translate(cx, cy); ctx.scale(1, ry / rx); ctx.translate(-cx, -cy); ctx.fillStyle = g; ctx.beginPath(); ctx.arc(cx, cy, rx, 0, Math.PI * 2); ctx.fill(); ctx.restore();
        }
        ctx.drawImage(shot.img, x, y, dw, dh);
    };
    const preview = (): void => {
        const shot = shots[current];
        const ready = !!shot && shot.status === 'ready';
        stageEmpty.classList.toggle('hidden', ready); canvas.classList.toggle('hidden', !ready);
        dl.disabled = !ready; dlAll.disabled = !shots.some((s) => s.status === 'ready');
        if (ready) void draw(canvas, shot, 1000, look(), false);
    };
    const renderThumbs = (): void => {
        thumbs.classList.toggle('hidden', shots.length === 0); thumbs.classList.toggle('grid', shots.length > 0);
        thumbs.innerHTML = shots.map((s, i) => `<li class="relative"><button type="button" data-pick="${i}" class="block aspect-square w-full overflow-hidden rounded-lg border ${i === current ? 'border-ink' : 'border-line'} bg-[repeating-conic-gradient(#eee_0_25%,#fff_0_50%)] bg-[length:16px_16px]" title="${esc(s.name)}">${s.status === 'ready' ? `<img src="${esc(s.url)}" alt="" class="h-full w-full object-contain">` : `<span class="flex h-full items-center justify-center p-1 text-[10px] ${s.status === 'failed' ? 'text-warn' : 'animate-pulse text-muted'}">${esc(s.status === 'failed' ? s.error : t(p, 'photo.busy'))}</span>`}</button><button type="button" data-drop="${i}" class="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-ink text-xs text-white" aria-label="${esc(t(p, 'photo.remove'))}">×</button></li>`).join('');
        thumbs.querySelectorAll<HTMLButtonElement>('[data-pick]').forEach((b) => b.addEventListener('click', () => { current = Number(b.dataset.pick); renderThumbs(); preview(); }));
        thumbs.querySelectorAll<HTMLButtonElement>('[data-drop]').forEach((b) => b.addEventListener('click', () => { shots.splice(Number(b.dataset.drop), 1); current = Math.min(current, shots.length - 1); if (current < 0 && shots.length) current = 0; renderThumbs(); preview(); showStatus(); }));
        const done = shots.filter((s) => s.status === 'ready').length;
        status.textContent = shots.length ? t(p, 'photo.status', { done, total: shots.length }) : '';
    };
    const showStatus = (): void => { renderThumbs(); };

    const cut = (file: File, shot: Shot): Promise<void> => (async () => {
        const fd = new FormData(); fd.append('photo', file, file.name);
        try {
            const res = await fetch(p.cut!, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: fd });
            if (!res.ok) {
                const err = await errorOf(res);
                shot.status = 'failed'; shot.error = t(p, err === 'nothing_found' ? 'photo.failed.nothing' : err === 'unavailable' ? 'photo.unavailable' : res.status === 422 ? 'photo.failed.file' : 'photo.failed');
            } else {
                const body = (await res.json()) as { url: string };
                shot.url = body.url; shot.img = await load(body.url); shot.status = 'ready';
                if (current < 0 || shots[current]?.status !== 'ready') current = shots.indexOf(shot);
            }
        } catch { shot.status = 'failed'; shot.error = t(p, 'photo.failed'); }
        renderThumbs(); preview();
    })();
    const add = (files: FileList | File[]): void => {
        msg.textContent = '';
        const room = maxPhotos - shots.length;
        const list = Array.from(files).filter((f) => f.type.startsWith('image/') || /\.(heic|heif)$/i.test(f.name));
        if (list.length > room) msg.textContent = t(p, 'photo.too_many', { n: maxPhotos });
        list.slice(0, Math.max(0, room)).forEach((file) => {
            if (file.size > maxBytes) { msg.textContent = t(p, 'photo.too_big', { mb: p.max_mb ?? 12 }); return; }
            const shot: Shot = { name: file.name, url: '', img: null, status: 'cutting', error: '' };
            shots.push(shot);
            queue = queue.then(() => cut(file, shot));
        });
        if (current < 0 && shots.length) current = 0;
        renderThumbs(); preview();
    };
    input.addEventListener('change', () => { if (input.files) add(input.files); input.value = ''; });
    ['dragenter', 'dragover'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.add('border-ink'); }));
    ['dragleave', 'drop'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.remove('border-ink'); }));
    drop.addEventListener('drop', (e) => { if (input.disabled) return; const files = (e as DragEvent).dataTransfer?.files; if (files) add(files); });
    form.addEventListener('input', () => { fillValue.textContent = `${fillRange.value} %`; preview(); });
    form.addEventListener('change', preview);

    const save = async (shot: Shot): Promise<void> => {
        const size = Number(picked(form, 'out_size', '2000')) || 2000; const png = picked(form, 'format', 'jpg') === 'png';
        const off = document.createElement('canvas');
        await draw(off, shot, size, look(), !png);
        const blob = await new Promise<Blob | null>((resolve) => off.toBlob(resolve, png ? 'image/png' : 'image/jpeg', 0.92));
        if (!blob) return;
        const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = `${shot.name.replace(/\.[^.]+$/, '')}-matplace.${png ? 'png' : 'jpg'}`;
        document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 2000);
    };
    dl.addEventListener('click', () => { const s = shots[current]; if (s && s.status === 'ready') void save(s); });
    dlAll.addEventListener('click', async () => { for (const s of shots) { if (s.status === 'ready') { await save(s); await new Promise((r) => setTimeout(r, 400)); } } });
}
