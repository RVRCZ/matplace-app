/** "Describe it" / "Take a photo": text or image → what it is, size + price range, ready-made models, rough generated model. */
import { openFile } from './calculator';

interface Candidate {
    source: string; externalId: string; title: string; previewUrl: string | null; externalUrl: string | null;
    license: string | null; authorName: string | null; localModelId: number | null; score: number; fileAvailable: boolean; origin?: string | null;
}
interface DescribeResponse {
    token: string;
    description: { name: string; name_en: string; query: string; queries: string[]; category: string; bbox_mm: { x: number; y: number; z: number } | null; size_known: boolean; material: string; printable: boolean; notes: string };
    range: { grams: number; minutes: number; price_min: number; price_max: number } | null;
    results: Candidate[];
    generator: boolean;
}

const routes = () => (window as unknown as { MP_ROUTES: Record<string, string> }).MP_ROUTES;
const i18n = (window as unknown as { MP_I18N?: Record<string, string> }).MP_I18N ?? {};
const t = (k: string, r: Record<string, string | number> = {}) => Object.entries(r).reduce((s, [a, b]) => s.replace(`:${a}`, String(b)), i18n[k] ?? k);
const $ = <T extends HTMLElement>(id: string) => document.getElementById(id) as T;
const fmt = new Intl.NumberFormat({ en: 'en-GB', es: 'es-ES' }[document.documentElement.lang] ?? 'cs-CZ', { maximumFractionDigits: 0 });

const SOURCE_LABEL: Record<string, string> = { local: 'matplace', printables: 'Printables', makerworld: 'MakerWorld', makeronline: 'MakerOnline', cults3d: 'Cults3D', thingiverse: 'Thingiverse', thangs: 'Thangs' };
/** The site a card's link opens. Entries of our own index point to other sites, so they are named after those. */
const siteOf = (c: Candidate): string => { const k = c.origin ?? c.source; return SOURCE_LABEL[k] ?? k; };

function card(c: Candidate): string {
    const img = c.previewUrl ? `<img src="${c.previewUrl}" alt="" loading="lazy" class="h-32 w-full object-cover">` : `<div class="flex h-32 items-center justify-center bg-slate-100 text-slate-400">—</div>`;
    const link = c.externalUrl ? `<a href="${c.externalUrl}" target="_blank" rel="noopener" class="mt-2 block rounded-lg border border-slate-300 px-2 py-1.5 text-center text-xs font-semibold text-slate-700 hover:bg-slate-50">${t('search.open_source', { s: siteOf(c) })} ↗</a>` : '';
    return `<div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        ${img}
        <div class="p-2">
            <div class="truncate text-sm font-semibold" title="${c.title.replace(/"/g, '&quot;')}">${c.title}</div>
            <div class="truncate text-xs text-slate-500">${siteOf(c)}${c.authorName ? ' · ' + c.authorName : ''}${c.license ? ' · ' + c.license : ''}</div>
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
        const cfg = (window as unknown as { MP_CONFIG: { generator: boolean } }).MP_CONFIG;
        if (cfg.generator) {
            const box = $('describe-box');
            box.innerHTML = `<div class="text-sm text-slate-600">${t('search.gen_text_hint')}</div><div class="mt-2 flex flex-wrap items-center gap-2">${generateControls(80)}</div>`;
            box.classList.remove('hidden');
            bindGenerate({ prompt: q });
        }
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
                    <button type="button" class="rounded-full bg-action px-4 py-2 font-semibold text-white" onclick="document.getElementById('file-input').click()">${t('search.have_file')}</button>
                    ${d.generator ? generateControls(desc.bbox_mm ? Math.max(desc.bbox_mm.x, desc.bbox_mm.y, desc.bbox_mm.z) : 80) : `<span class="rounded-full border border-slate-200 px-4 py-2 text-slate-400" title="${t('hero.soon')}">${t('search.generate')} (${t('hero.soon')})</span>`}
                    <span class="rounded-full border border-slate-200 px-4 py-2 text-slate-500">${t('search.designer_soon')}</span>
                </div>
            </div>
        </div>`;
    box.classList.remove('hidden');
    bindGenerate({ describe: d.token });
}

/** Size input + button + progress bar. The size is the largest side of the real object in mm. */
function generateControls(defaultMm: number): string {
    return `<span class="inline-flex items-center gap-2">
        <label class="text-xs text-slate-500">${t('search.gen_size')} <input id="gen-target" type="number" min="5" max="1000" value="${Math.round(defaultMm)}" class="w-20 rounded-lg border border-slate-300 px-2 py-1.5 text-sm"> mm</label>
        <button type="button" id="cta-generate" class="rounded-full border border-action px-4 py-2 font-semibold text-action-dark">${t('search.generate')}</button>
    </span>
    <div id="gen-progress" class="hidden w-full"><div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-200"><div id="gen-bar" class="h-2 w-0 bg-action transition-all"></div></div><p id="gen-text" class="mt-1 text-xs text-slate-500"></p></div>`;
}

function bindGenerate(payload: Record<string, unknown>): void {
    const btn = document.getElementById('cta-generate') as HTMLButtonElement | null;
    if (!btn) return;
    btn.onclick = async () => {
        btn.disabled = true;
        const target = Number((document.getElementById('gen-target') as HTMLInputElement | null)?.value || 80);
        const prog = $('gen-progress'); const bar = $('gen-bar'); const txt = $('gen-text');
        prog.classList.remove('hidden'); txt.textContent = t('search.generating');
        try {
            const res = await fetch(routes().generate, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ ...payload, target_mm: target }) });
            const body = await res.json();
            if (res.status === 429) {
                if (body.error === 'credit' && body.topup_url) { txt.innerHTML = `${t('search.gen_credit', { n: body.price, m: body.missing })} <a class="underline" href="${body.topup_url}">${t('search.gen_topup')}</a>`; }
                else txt.textContent = t(body.error === 'global_limit' ? 'search.gen_global_limit' : 'search.gen_daily_limit', { n: body.limit, m: body.login_limit });
                btn.disabled = false; return;
            }
            if (!res.ok) throw new Error(body.error ?? 'generate');
            let g = body.generation;
            while (g.status !== 'done' && g.status !== 'failed') {
                bar.style.width = `${Math.max(5, g.progress)}%`;
                await new Promise((r) => setTimeout(r, 3000));
                const r2 = await fetch(`${routes().generateShow}/${g.token}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                g = (await r2.json()).generation;
            }
            if (g.status === 'failed' || !g.file) { txt.textContent = t('search.gen_failed'); btn.disabled = false; return; }
            bar.style.width = '100%';
            txt.textContent = t('search.gen_done');
            await openFile(g.file.uuid);
        } catch {
            txt.textContent = t('search.gen_failed'); btn.disabled = false;
        }
    };
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
