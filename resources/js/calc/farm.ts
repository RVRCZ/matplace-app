/**
 * Print farm ("Rent a printer").
 *   bootFarmCta    calculator: the button takes the shared calculation (/c/{token} in the address) to /farm
 *   bootFarmStart  /farm: upload an STL when the customer did not come from the calculator
 *   bootFarmOrder  /farm/orders/{token}: preview of the print pose, presets, colours, pay, progress (polled)
 */
import { Viewer } from './viewer';
import { loadGeometryFromUrl } from './loaders';

interface Color { slot: number; name: string; kind?: string; hex: string; photo: string | null; enough: boolean; total: number; starts_now: boolean }
interface Price { time: number; material: number; fixed: number; min_price_applied: boolean; net: number; vat: number; shipping: number; total: number; print_total: number; inputs: { vat_percent: number } }
interface FarmState {
    token: string; number: string | null; status: string; status_text: string; stage: string | null; error: string | null; error_text: string | null;
    quality: string; strength: string; unit: string; unit_guess: { unit: string; confident: boolean } | null;
    dims: { x: number; y: number; z: number } | null; warnings: string[]; orientation_changed: boolean; supports: boolean;
    minutes: number | null; grams: number | null; meters: number | null; price: Price | null; total: number | null; shipping_price: number;
    colors: Color[]; color: { name: string; hex: string } | null; delivery: string; balance: number; model_url: string | null;
    queue: { start_in: number; finish_in: number; ahead: number; blocked: string | null } | null;
    print: { status: string; progress: number; snapshot_url: string | null; snapshot_at: string | null } | null;
    can_cancel: boolean; final: boolean;
}
interface FarmCfg { state: FarmState; routes: Record<string, string>; csrf: string; i18n: Record<string, string> }

const $ = <T extends HTMLElement = HTMLElement>(id: string) => document.getElementById(id) as T | null;
const show = (el: HTMLElement | null, on: boolean) => el?.classList.toggle('hidden', !on);
const esc = (s: string) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c] as string));
const money = (n: number) => Math.round(n).toLocaleString('cs-CZ');
const duration = (min: number) => (min >= 60 ? `${Math.floor(min / 60)} h ${min % 60} min` : `${min} min`);

export function bootFarmCta(): void {
    const btn = $<HTMLButtonElement>('cta-farm');
    if (!btn) return;
    btn.addEventListener('click', () => {
        // the calculator rewrites the address to /c/{token} once the calculation exists
        const m = location.pathname.match(/^\/c\/([A-Za-z0-9_-]+)/);
        if (!m) { show($('cta-farm-note'), true); return; }
        location.href = `${btn.dataset.url}?calc=${encodeURIComponent(m[1])}`;
    });
}

/** Admin order page: the model in its print pose, nothing else. */
export function bootFarmAdminViewer(): void {
    const canvas = $<HTMLCanvasElement>('admin-farm-viewer');
    if (!canvas?.dataset.model) return;
    const viewer = new Viewer(canvas);
    loadGeometryFromUrl(canvas.dataset.model).then((g) => viewer.setGeometry(g, 1, null)).catch(() => undefined);
}

export function bootFarmStart(): void {
    const cfg = (window as unknown as { MP_FARM_START?: { upload: string; files: string; maxMb: number; text: Record<string, string> } }).MP_FARM_START;
    const input = $<HTMLInputElement>('farm-upload');
    if (!cfg || !input) return;
    const status = $('farm-upload-status')!;
    const go = $<HTMLButtonElement>('farm-continue')!;
    const say = (t: string) => { status.textContent = t; show(status, t !== ''); };

    input.addEventListener('change', async () => {
        const file = input.files?.[0];
        go.disabled = true;
        if (!file) return;
        if (!file.name.toLowerCase().endsWith('.stl')) { say(cfg.text.badFormat); return; }
        if (file.size > cfg.maxMb * 1024 * 1024) { say(cfg.text.tooBig); return; }
        say(cfg.text.uploading);
        try {
            const fd = new FormData(); fd.append('file', file);
            const up = await fetch(cfg.upload, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body: fd });
            if (!up.ok) throw new Error('upload');
            let info = (await up.json()).file;
            say(cfg.text.processing);
            for (let i = 0; i < 80 && info.status !== 'ready' && info.status !== 'failed'; i++) {
                await new Promise((r) => setTimeout(r, 1500));
                info = (await (await fetch(`${cfg.files}/${info.uuid}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })).json()).file;
            }
            if (info.status !== 'ready') throw new Error('processing');
            $<HTMLInputElement>('farm-file')!.value = info.uuid;
            say(file.name);
            go.disabled = false;
        } catch {
            say(cfg.text.failed);
        }
    });
}

export function bootFarmOrder(): void {
    const cfg = (window as unknown as { MP_FARM?: FarmCfg }).MP_FARM;
    const canvas = $<HTMLCanvasElement>('farm-viewer');
    if (!cfg || !canvas) return;

    const tr = (k: string, p: Record<string, string | number> = {}) => Object.entries(p).reduce((s, [a, b]) => s.replace(`:${a}`, String(b)), cfg.i18n[k] ?? k);
    const viewer = new Viewer(canvas);
    let state = cfg.state;
    let shownModel = '';
    let picked: number | null = null;
    let delivery = state.delivery || 'pickup';
    let timer = 0;
    const wanted = { quality: state.quality, strength: state.strength, unit: state.unit };

    const post = async (url: string, body: unknown): Promise<{ ok: boolean; status: number; json: Record<string, unknown> }> => {
        const res = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf }, body: JSON.stringify(body) });
        return { ok: res.ok, status: res.status, json: await res.json().catch(() => ({})) };
    };

    const total = (): number | null => {
        const c = state.colors.find((x) => x.slot === picked);
        return c ? c.total + (delivery === 'shipping' ? state.shipping_price : 0) : state.total;
    };

    const renderColors = (): void => {
        const box = $('farm-colors')!;
        if (!state.colors.length) { box.innerHTML = `<p class="col-span-full text-sm text-slate-600">${esc(tr('farm.order.no_colors'))}</p>`; picked = null; return; }
        if (picked === null || !state.colors.some((c) => c.slot === picked && c.enough)) picked = state.colors.find((c) => c.enough)?.slot ?? null;
        box.innerHTML = state.colors.map((c) => `
            <button type="button" role="radio" aria-checked="${c.slot === picked}" data-slot="${c.slot}" ${c.enough ? '' : 'disabled'}
                class="flex items-center gap-2 rounded-xl border bg-white p-2 text-left text-sm disabled:opacity-50 ${c.slot === picked ? 'border-action ring-2 ring-action' : 'border-slate-300'}">
                ${c.photo ? `<img src="${esc(c.photo)}" alt="" class="h-10 w-10 rounded-lg object-cover">` : `<span class="h-10 w-10 shrink-0 rounded-lg border border-slate-200" style="background:${esc(c.hex)}"></span>`}
                <span><span class="font-semibold">${esc(c.name)}</span>${c.kind ? `<br><span class="text-xs text-slate-500">${esc(c.kind)}</span>` : ''}${c.enough ? '' : `<br><span class="text-xs text-amber-700">${esc(tr('farm.order.low_filament'))}</span>`}</span>
            </button>`).join('');
        box.querySelectorAll<HTMLButtonElement>('button[data-slot]').forEach((b) => b.addEventListener('click', () => { picked = Number(b.dataset.slot); render(); }));
        const c = state.colors.find((x) => x.slot === picked);
        $('farm-start-note')!.textContent = c ? tr(c.starts_now ? 'farm.order.starts_now' : 'farm.order.goes_to_queue') : '';
    };

    const renderBreakdown = (): void => {
        const p = state.price;
        const box = $('farm-breakdown')!;
        if (!p) { box.innerHTML = ''; return; }
        const ship = delivery === 'shipping' && state.status === 'sliced' ? state.shipping_price : p.shipping;
        const row = (k: string, v: number, strong = false) => `<div class="flex justify-between ${strong ? 'font-semibold' : ''}"><dt>${esc(k)}</dt><dd>${money(v)} Kč</dd></div>`;
        box.innerHTML = row(tr('farm.order.b_time'), p.time) + row(tr('farm.order.b_material'), p.material) + row(tr('farm.order.b_fixed'), p.fixed)
            + (p.min_price_applied ? `<div class="text-xs text-slate-500">${esc(tr('farm.order.b_min'))}</div>` : '')
            + row(tr('farm.order.b_net'), p.net) + row(tr('farm.order.b_vat', { p: p.inputs.vat_percent }), p.vat)
            + (ship > 0 ? row(tr('farm.order.b_shipping'), ship) : '') + row(tr('farm.order.b_total'), (total() ?? p.total), true);
    };

    const render = (): void => {
        const s = state;
        const working = s.status === 'uploaded';
        $('farm-status')!.textContent = working && s.stage ? tr(`farm.stage.${s.stage}`) : s.status_text;
        show($('farm-spinner'), working || s.status === 'printing');
        const num = $('farm-number')!; num.textContent = s.number ? `${s.number}${s.color ? ` · ${s.color.name}` : ''}` : ''; show(num, !!s.number);
        const err = $('farm-error')!; err.textContent = s.error_text ?? ''; show(err, s.status === 'failed' && !!s.error_text);
        $('farm-warnings')!.innerHTML = s.warnings.map((w) => `<li>⚠ ${esc(w)}</li>`).join('');
        $('farm-dims')!.textContent = s.dims ? `${s.dims.x.toFixed(1)} × ${s.dims.y.toFixed(1)} × ${s.dims.z.toFixed(1)} mm` : '';
        show($('farm-oriented'), s.orientation_changed && s.status !== 'failed');
        $('farm-balance')!.textContent = money(s.balance);

        const hasResult = s.minutes !== null && s.status !== 'uploaded' && !(s.status === 'failed' && !s.number);
        show($('farm-result'), hasResult);
        if (hasResult) {
            $('farm-price')!.textContent = total() !== null ? money(total()!) : '—';
            $('farm-time')!.textContent = duration(s.minutes!);
            $('farm-grams')!.textContent = `${s.grams} g${s.meters ? ` · ${s.meters} m` : ''}`;
            $('farm-supports')!.textContent = tr(s.supports ? 'farm.order.supports_yes' : 'farm.order.supports_no');
            renderBreakdown();
        }

        // presets stay editable until the order is paid; a failed check can be retried with other units
        const editable = s.status === 'sliced' || (s.status === 'failed' && !s.number);
        show($('farm-presets'), editable);
        document.querySelectorAll<HTMLElement>('#farm-presets [data-group]').forEach((g) => g.querySelectorAll<HTMLElement>('.seg').forEach((b) => b.classList.toggle('seg-on', b.dataset.value === wanted[g.dataset.group as 'quality' | 'strength'])));
        ($('farm-unit') as HTMLSelectElement).value = wanted.unit;
        const note = $('farm-unit-note')!;
        const guess = s.unit_guess;
        note.textContent = guess && guess.unit !== 'mm' ? (guess.confident && s.unit === guess.unit ? tr('farm.units.guess', { unit: tr(`farm.units.${guess.unit}`) }) : (!guess.confident && s.unit === 'mm' ? tr('farm.units.ask') : '')) : '';
        show(note, note.textContent !== '');
        show($('farm-reslice'), editable && (wanted.quality !== s.quality || wanted.strength !== s.strength || wanted.unit !== s.unit));

        show($('farm-pay'), s.status === 'sliced');
        if (s.status === 'sliced') {
            renderColors();
            document.querySelectorAll<HTMLElement>('#farm-delivery .seg').forEach((b) => b.classList.toggle('seg-on', b.dataset.value === delivery));
            const addr = $('farm-address')!; addr.classList.toggle('hidden', delivery !== 'shipping'); addr.classList.toggle('grid', delivery === 'shipping');
            ($('farm-pay-btn') as HTMLButtonElement).disabled = picked === null || !($('farm-terms') as HTMLInputElement).checked;
        }

        const print = s.print && ['sent', 'printing', 'paused', 'done', 'unknown'].includes(s.print.status) && ['queued', 'printing', 'done'].includes(s.status) ? s.print : null;
        show($('farm-print'), !!print);
        if (print) {
            $('farm-progress-val')!.textContent = `${Math.round(print.progress)} %`;
            $('farm-progress-bar')!.style.width = `${Math.min(100, print.progress)}%`;
            show($('farm-camera'), !!print.snapshot_url);
            if (print.snapshot_url) {
                const img = $<HTMLImageElement>('farm-camera-img')!;
                if (img.getAttribute('src') !== print.snapshot_url) img.src = print.snapshot_url;
                $('farm-camera-at')!.textContent = print.snapshot_at ? new Date(print.snapshot_at).toLocaleTimeString() : '';
            }
        }
        const q = $('farm-queue')!;
        if (s.queue && s.status !== 'printing') {
            const lines = [s.queue.ahead > 0 ? tr('farm.order.queue_ahead', { n: s.queue.ahead }) : '', tr('farm.order.queue_start', { time: duration(s.queue.start_in) }), tr('farm.order.queue_finish', { time: duration(s.queue.finish_in) })];
            if (s.queue.blocked) lines.unshift(tr(`farm.order.blocked_${s.queue.blocked}`));
            q.innerHTML = lines.filter(Boolean).map(esc).join('<br>');
        } else if (s.queue) {
            q.textContent = tr('farm.order.queue_finish', { time: duration(s.queue.finish_in) });
        }
        show(q, !!s.queue);
        show($('farm-cancel'), s.can_cancel);

        if (s.model_url && s.model_url !== shownModel) {
            shownModel = s.model_url;
            loadGeometryFromUrl(s.model_url).then((g) => viewer.setGeometry(g, 1, null)).catch(() => { shownModel = ''; });
        }
    };

    const poll = async (): Promise<void> => {
        window.clearTimeout(timer);
        if (state.final) return;
        try {
            const res = await fetch(cfg.routes.status, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (res.ok) { state = await res.json(); render(); }
        } catch { /* offline for a moment: try again */ }
        const fast = state.status === 'uploaded';
        const live = ['paid', 'queued', 'printing', 'sliced'].includes(state.status);
        if (fast || live) timer = window.setTimeout(poll, fast ? 2000 : state.status === 'sliced' ? 20000 : 8000);
    };

    document.querySelectorAll<HTMLElement>('#farm-presets [data-group] .seg').forEach((b) => b.addEventListener('click', () => {
        wanted[(b.parentElement as HTMLElement).dataset.group as 'quality' | 'strength'] = b.dataset.value!; render();
    }));
    $('farm-unit')?.addEventListener('change', (e) => { wanted.unit = (e.target as HTMLSelectElement).value; render(); });
    $('farm-presets')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const r = await post(cfg.routes.reslice, wanted);
        if (r.ok) { state = r.json as unknown as FarmState; picked = null; render(); poll(); } else { const err = $('farm-error')!; err.textContent = String(r.json.message ?? ''); show(err, true); }
    });
    document.querySelectorAll<HTMLElement>('#farm-delivery .seg').forEach((b) => b.addEventListener('click', () => { delivery = b.dataset.value!; render(); }));
    $('farm-terms')?.addEventListener('change', render);

    $('farm-pay')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = $<HTMLButtonElement>('farm-pay-btn')!;
        const errBox = $('farm-pay-error')!;
        const form = e.target as HTMLFormElement;
        const address: Record<string, string> = {};
        new FormData(form).forEach((v, k) => { const m = k.match(/^address\[(\w+)\]$/); if (m) address[m[1]] = String(v); });
        btn.disabled = true; btn.textContent = tr('farm.order.paying'); show(errBox, false); show($('farm-topup'), false);
        const r = await post(cfg.routes.pay, {
            slot: picked, delivery, terms: ($('farm-terms') as HTMLInputElement).checked, expected_total: total(),
            note: (form.elements.namedItem('note') as HTMLTextAreaElement).value, address: delivery === 'shipping' ? address : null,
        });
        btn.textContent = tr('farm.order.pay');
        if (r.ok) { state = r.json as unknown as FarmState; render(); poll(); return; }
        errBox.textContent = String(r.json.message ?? (r.json.errors ? Object.values(r.json.errors as Record<string, string[]>)[0][0] : ''));
        show(errBox, true);
        if (r.status === 402 && r.json.topup_url) { const a = $<HTMLAnchorElement>('farm-topup')!; a.href = String(r.json.topup_url); show(a, true); }
        if (r.json.error === 'price_changed' || r.json.error === 'color_gone' || r.json.error === 'filament_low') await poll();
        render();
    });

    $('farm-cancel')?.addEventListener('click', async () => {
        if (!window.confirm(tr('farm.order.cancel_confirm'))) return;
        const r = await post(cfg.routes.cancel, {});
        if (r.ok) { state = r.json as unknown as FarmState; render(); } else { const err = $('farm-error')!; err.textContent = String(r.json.message ?? ''); show(err, true); }
    });

    render();
    poll();
}
