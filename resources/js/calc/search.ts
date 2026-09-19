/** "Describe it" / "Take a photo": text or image → what it is, size + price range, ready-made models. */

interface Candidate {
    source: string; externalId: string; title: string; previewUrl: string | null; externalUrl: string | null;
    license: string | null; authorName: string | null; localModelId: number | null; score: number; fileAvailable: boolean;
}
interface DescribeResponse {
    token: string;
    description: { name: string; name_en: string; query: string; queries: string[]; category: string; bbox_mm: { x: number; y: number; z: number } | null; size_known: boolean; material: string; printable: boolean; notes: string };
    range: { grams: number; minutes: number; price_min: number; price_max: number } | null;
    results: Candidate[];
    generator: boolean;
}

const routes = () => (window as unknown as { MP_ROUTES: Record<string, string> }).MP_ROUTES;
const i18n = (window as unknown as { MP_I18N: Record<string, string> }).MP_I18N;
const t = (k: string, r: Record<string, string | number> = {}) => Object.entries(r).reduce((s, [a, b]) => s.replace(`:${a}`, String(b)), i18n[k] ?? k);
const $ = <T extends HTMLElement>(id: string) => document.getElementById(id) as T;
const fmt = new Intl.NumberFormat({ en: 'en-GB', es: 'es-ES' }[document.documentElement.lang] ?? 'cs-CZ', { maximumFractionDigits: 0 });

const SOURCE_LABEL: Record<string, string> = { local: 'matplace', printables: 'Printables', makerworld: 'MakerWorld' };

function card(c: Candidate): string {
    const img = c.previewUrl ? `<img src="${c.previewUrl}" alt="" loading="lazy" class="h-32 w-full object-cover">` : `<div class="flex h-32 items-center justify-center bg-slate-100 text-slate-400">—</div>`;
    const link = c.externalUrl ? `<a href="${c.externalUrl}" target="_blank" rel="noopener" class="mt-2 block rounded-lg border border-slate-300 px-2 py-1.5 text-center text-xs font-semibold text-slate-700 hover:bg-slate-50">${t('search.open_source', { s: SOURCE_LABEL[c.source] ?? c.source })}</a>` : '';
    return `<div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        ${img}
        <div class="p-2">
            <div class="truncate text-sm font-semibold" title="${c.title.replace(/"/g, '&quot;')}">${c.title}</div>
            <div class="truncate text-xs text-slate-500">${SOURCE_LABEL[c.source] ?? c.source}${c.authorName ? ' · ' + c.authorName : ''}${c.license ? ' · ' + c.license : ''}</div>
            ${link}
        </div></div>`;
}

function renderResults(results: Candidate[], query: string): void {
    $('search-results').innerHTML = results.length
        ? results.map(card).join('')
        : `<p class="col-span-full text-sm text-slate-500">${t('search.none')}</p>`;
    $('search-query').textContent = query;
    $('search-hint').classList.toggle('hidden', results.length === 0);
    $('search-section').classList.remove('hidden');
    $('search-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function setBusy(on: boolean, msg = ''): void {
    $('search-busy').classList.toggle('hidden', !on);
    $('search-busy-text').textContent = msg;
}

function showError(msg: string): void {
    const el = $('hero-error');
    el.textContent = msg;
    el.classList.remove('hidden');
}

async function searchText(q: string): Promise<void> {
    q = q.trim();
    if (q.length < 2) return;
    setBusy(true, t('search.searching'));
    $('describe-box').classList.add('hidden');
    try {
        const res = await fetch(routes().search, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ q }) });
        const body = await res.json();
        if (!res.ok) throw new Error(body.message ?? 'search');
        renderResults(body.results, body.query);
    } catch {
        showError(t('search.error'));
    } finally {
        setBusy(false);
    }
}

function renderDescription(d: DescribeResponse): void {
    const box = $('describe-box');
    const desc = d.description;
    const size = desc.bbox_mm ? `${desc.bbox_mm.x} × ${desc.bbox_mm.y} × ${desc.bbox_mm.z} mm${desc.size_known ? '' : ' (' + t('search.size_guess') + ')'}` : '—';
    const price = d.range ? `${fmt.format(d.range.price_min)} – ${fmt.format(d.range.price_max)} Kč` : '—';
    box.innerHTML = `
        <div class="grid gap-3 sm:grid-cols-[120px_1fr]">
            <img id="describe-photo" src="" alt="" class="hidden h-28 w-28 rounded-lg object-cover">
            <div>
                <div class="text-lg font-bold">${desc.name || desc.query}</div>
                <p class="text-sm text-slate-600">${desc.notes ?? ''}</p>
                <dl class="mt-2 grid grid-cols-3 gap-2 text-sm">
                    <div><dt class="text-slate-500">${t('calc.size')}</dt><dd class="font-semibold">${size}</dd></div>
                    <div><dt class="text-slate-500">${t('calc.material')}</dt><dd class="font-semibold">${desc.material}</dd></div>
                    <div><dt class="text-slate-500">${t('search.price_range')}</dt><dd class="font-semibold">${price}</dd></div>
                </dl>
                <p class="mt-1 text-xs text-slate-500">${t('search.range_hint')}</p>
                <div class="mt-3 flex flex-wrap gap-2 text-sm">
                    <button type="button" class="rounded-full bg-teal-600 px-4 py-2 font-semibold text-white" onclick="document.getElementById('file-input').click()">${t('search.have_file')}</button>
                    ${d.generator ? `<button type="button" id="cta-generate" class="rounded-full border border-teal-600 px-4 py-2 font-semibold text-teal-700">${t('search.generate')}</button>` : `<span class="rounded-full border border-slate-200 px-4 py-2 text-slate-400" title="${t('hero.soon')}">${t('search.generate')} (${t('hero.soon')})</span>`}
                    <span class="rounded-full border border-slate-200 px-4 py-2 text-slate-500">${t('search.designer_soon')}</span>
                </div>
            </div>
        </div>`;
    box.classList.remove('hidden');
}

async function describePhoto(file: File): Promise<void> {
    if (!file.type.startsWith('image/')) { showError(t('search.not_image')); return; }
    setBusy(true, t('search.identifying'));
    const form = new FormData();
    form.append('image', file, file.name || 'photo.jpg');
    try {
        const res = await fetch(routes().describe, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body: form });
        const body = await res.json();
        if (res.status === 429) { showError(t('search.daily_limit', { n: body.limit })); return; }
        if (!res.ok) throw new Error(body.error ?? body.message ?? 'describe');
        renderDescription(body as DescribeResponse);
        const img = document.getElementById('describe-photo') as HTMLImageElement | null;
        if (img) { img.src = URL.createObjectURL(file); img.classList.remove('hidden'); }
        renderResults(body.results, body.description.query);
    } catch {
        showError(t('search.error'));
    } finally {
        setBusy(false);
    }
}

export function bootSearch(): void {
    const form = document.getElementById('search-form') as HTMLFormElement | null;
    if (!form) return;
    const input = $('search-input') as HTMLInputElement;
    form.onsubmit = (e) => { e.preventDefault(); searchText(input.value); };
    const toggle = document.getElementById('hero-text-btn');
    if (toggle) toggle.onclick = () => { $('search-form').classList.remove('hidden'); input.focus(); };
    const photo = document.getElementById('photo-input') as HTMLInputElement | null;
    if (photo) photo.onchange = () => { if (photo.files?.[0]) describePhoto(photo.files[0]); photo.value = ''; };
    const photoBtn = document.getElementById('hero-photo-btn');
    if (photoBtn && photo) photoBtn.onclick = () => photo.click();
    document.querySelectorAll<HTMLElement>('[data-tile="idea"]').forEach((el) => el.onclick = (e) => { e.preventDefault(); $('search-form').classList.remove('hidden'); input.focus(); });
    document.querySelectorAll<HTMLElement>('[data-tile="broken"]').forEach((el) => el.onclick = (e) => { e.preventDefault(); photo?.click(); });
}
