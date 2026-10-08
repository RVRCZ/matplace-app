/**
 * The selling and planning pages (session 4): what a print costs, what a sale leaves, a year's plan, where to sell.
 * The arithmetic mirrors App\Domain\Sell\* (the PHP side is the one the tests count on); the page computes as the
 * visitor types, shows amounts in the visitor's currency and keeps the settings in localStorage. The payload of a
 * page is window.MP_SELL (resources/views/tools/sell.blade.php).
 */
import { money, fromCzk, currency } from './money';
import { bootImage, bootListing, bootPhoto } from './studio';

interface Payload { tool: string; locale: string; home: string; tools: string; i18n: Record<string, string>; currency: string; rate: number; fields?: Record<string, number[]>; values?: Record<string, number>; money?: { keys: string[] };
    from?: { name: string; grams: number; hours: number; price: number | null; url: string } | null; profit?: string; plan?: string; cost?: string; vendors?: string; pdf?: string; plans?: string | null;
    platforms?: Record<string, Platform>; rates?: Record<string, number | string>; vat_pct?: number; seasons?: Record<string, number[]>; max_products?: number; start?: number }
interface Platform { listing: Amount | null; transaction_pct: number; payment_pct: number; payment_fixed: Amount | null; currency_pct: number; monthly: Amount | null; stall: boolean; as_of: string; source: string; note: string }
interface Amount { amount: number; currency: string }

const cfg = (): Payload | null => (window as unknown as { MP_SELL?: Payload }).MP_SELL ?? null;
const esc = (s: string): string => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '"': '&quot;', '>': '&gt;' }[c] as string));
const t = (p: Payload, key: string, r: Record<string, string | number> = {}): string => Object.entries(r).reduce((s, [a, b]) => s.split(`:${a}`).join(String(b)), p.i18n[`sell.${key}`] ?? key);
const nf = (locale: string, digits = 1): Intl.NumberFormat => new Intl.NumberFormat(locale, { maximumFractionDigits: digits });

/** The numbers of a form by data-param, within their limits. */
function readNumbers(form: HTMLFormElement, fields: Record<string, number[]> = {}): Record<string, number> {
    const out: Record<string, number> = {};
    form.querySelectorAll<HTMLInputElement>('[data-param]').forEach((el) => {
        const [min, max] = fields[el.dataset.param!] ?? [-Infinity, Infinity];
        const v = Number(el.value);
        out[el.dataset.param!] = Number.isFinite(v) ? Math.max(min, Math.min(max, v)) : min;
    });
    return out;
}

/** Settings survive a reload: saved on every change, put back on the next visit (the query string wins over them). */
function remember(key: string, form: HTMLFormElement, read: () => Record<string, unknown>, apply: (saved: Record<string, unknown>) => void, fresh: boolean): () => void {
    if (!fresh) {
        try { const raw = localStorage.getItem(key); if (raw) apply(JSON.parse(raw) as Record<string, unknown>); } catch { /* storage may be unavailable */ }
    }
    return () => { try { localStorage.setItem(key, JSON.stringify(read())); } catch { /* ignore */ } };
}

/** Section numbers and the step marked in the nav as the page scrolls (what tool_page.ts does for the tool page). */
function sections(): void {
    const nav = document.getElementById('sell-nav');
    if (!nav || !('IntersectionObserver' in window)) return;
    const mark = (id: string) => nav.querySelectorAll<HTMLElement>('[data-nav]').forEach((a) => { if (a.dataset.nav === id) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current'); });
    const seen = new Map<string, number>();
    const io = new IntersectionObserver((entries) => {
        entries.forEach((e) => seen.set((e.target as HTMLElement).dataset.section!, e.isIntersecting ? e.intersectionRatio : 0));
        const best = [...seen.entries()].sort((a, b) => b[1] - a[1])[0];
        if (best && best[1] > 0) mark(best[0]);
    }, { rootMargin: '-10% 0px -60% 0px', threshold: [0, 0.25, 0.5, 1] });
    document.querySelectorAll('[data-section]').forEach((s) => io.observe(s));
}

// ── cost ─────────────────────────────────────────────────────────────────────────────────────────────

export interface CostResult { material: number; energy: number; wear: number; scrap: number; labour: number; other: number; cost: number; margin: number; price: number; per_hour: number }

/** App\Domain\Sell\Cost::calculate, the same arithmetic. */
export function costOf(p: Record<string, number>): CostResult {
    const material = p.filament_kg * p.grams / 1000;
    const energy = p.watts * p.hours / 1000 * p.kwh;
    const wear = p.printer_hours > 0 ? p.printer_price / p.printer_hours * p.hours : 0;
    const machine = material + energy + wear;
    const share = Math.min(0.9, p.scrap_pct / 100);
    const scrap = share > 0 ? machine / (1 - share) - machine : 0;
    const labour = p.labour_rate * p.labour_minutes / 60;
    const cost = machine + scrap + labour + p.other;
    const margin = cost * p.margin_pct / 100;
    return { material, energy, wear, scrap, labour, other: p.other, cost, margin, price: cost + margin, per_hour: p.hours > 0 ? margin / p.hours : 0 };
}

function bootCost(p: Payload): void {
    const form = document.getElementById('sell-form') as HTMLFormElement | null;
    if (!form) return;
    const fields = p.fields ?? {};
    const moneyKeys = new Set(p.money?.keys ?? []);
    const euro = currency() === 'EUR';
    // the starting values are in crowns: a visitor paying in euros sees them in euros, and types euros
    const toView = (key: string, czk: number): number => moneyKeys.has(key) && euro ? Math.round(fromCzk(czk) * 100) / 100 : czk;
    const start: Record<string, number> = {};
    Object.entries(p.values ?? {}).forEach(([k, v]) => { start[k] = toView(k, v); });
    const set = (values: Record<string, unknown>): void => { Object.entries(values).forEach(([k, v]) => { const el = form.querySelector<HTMLInputElement>(`[data-param="${k}"]`); if (el && typeof v === 'number') el.value = String(v); }); };
    set(start);
    const fresh = !!p.from || new URLSearchParams(location.search).has('fresh');
    const save = remember(`sell.cost.${currency()}`, form, () => readNumbers(form, fields), (saved) => {
        // a calculation fills the grams and hours in: the rest (the printer, the work) is what was typed before
        const keep = p.from ? Object.fromEntries(Object.entries(saved).filter(([k]) => !['grams', 'hours'].includes(k))) : saved;
        set(keep);
    }, false);
    if (fresh) set(start);
    const rows = document.getElementById('cost-rows')!;
    const fmt = nf(p.locale);
    const render = (): void => {
        const r = costOf(readNumbers(form, fields));
        const line = (key: string, v: number, strong = false): string => `<tr${strong ? ' class="font-semibold text-ink"' : ''}><td class="py-1.5 pr-3 text-left">${esc(t(p, `cost.row.${key}`))}</td><td class="py-1.5 text-right">${esc(money(v))}</td></tr>`;
        rows.innerHTML = [line('material', r.material), line('energy', r.energy), line('wear', r.wear), line('scrap', r.scrap), line('labour', r.labour), line('other', r.other), line('cost', r.cost, true), line('margin', r.margin), line('price', r.price, true)].join('');
        document.getElementById('cost-total')!.textContent = money(r.cost);
        document.getElementById('cost-price')!.textContent = money(r.price);
        document.getElementById('cost-hour')!.textContent = t(p, 'cost.per_hour', { v: money(r.per_hour) });
        const diff = document.getElementById('cost-us-diff');
        if (diff && p.from?.price != null) {
            const us = fromCzk(p.from.price);
            diff.textContent = us <= r.cost ? t(p, 'cost.us.cheaper', { v: money(r.cost - us) }) : t(p, 'cost.us.dearer', { v: money(us - r.cost) });
        }
        const toProfit = document.getElementById('cost-to-profit') as HTMLAnchorElement | null;
        if (toProfit && p.profit) toProfit.href = `${p.profit}?cost=${encodeURIComponent(fmt.format(r.cost).replace(/\s/g, '').replace(',', '.'))}&price=${encodeURIComponent(fmt.format(r.price).replace(/\s/g, '').replace(',', '.'))}`;
        save();
    };
    form.addEventListener('input', render);
    document.getElementById('sell-reset')?.addEventListener('click', () => { set(start); render(); });
    render();
}

// ── profit ───────────────────────────────────────────────────────────────────────────────────────────

export interface ProfitIn { platform: string; vat: boolean; foreign: boolean; price: number; cost: number; ship_charged: number; ship_actual: number; discount_pct: number; monthly_pieces: number; fixed_monthly: number; stall_fee: number; stall_pieces: number }
export interface ProfitResult { revenue: number; discount: number; vat: number; fees: Record<string, number>; fees_total: number; shipping_loss: number; net: number; profit: number; margin_pct: number; markup_pct: number; break_even: number | null }

/** App\Domain\Sell\Profit::calculate in the visitor's currency: fixed fees quoted in other currencies come through the rates. */
export function profitOf(i: ProfitIn, f: Platform, toView: (a: Amount | null) => number, vatPct: number): ProfitResult {
    const discount = i.price * i.discount_pct / 100;
    const paid = i.price - discount;
    const gross = paid + i.ship_charged;
    const vat = i.vat ? gross - gross / (1 + vatPct / 100) : 0;
    const fees: Record<string, number> = {
        listing: toView(f.listing),
        transaction: gross * f.transaction_pct / 100,
        payment: gross * f.payment_pct / 100 + toView(f.payment_fixed),
        currency: i.foreign ? gross * f.currency_pct / 100 : 0,
        monthly: toView(f.monthly) / Math.max(1, i.monthly_pieces),
        stall: f.stall ? i.stall_fee / Math.max(1, i.stall_pieces) : 0,
    };
    const feesTotal = Object.values(fees).reduce((a, b) => a + b, 0);
    const net = gross - vat - feesTotal - i.ship_actual;
    const profit = net - i.cost;
    return { revenue: gross, discount, vat, fees, fees_total: feesTotal, shipping_loss: i.ship_actual - i.ship_charged, net, profit,
        margin_pct: paid > 0 ? profit / paid * 100 : 0, markup_pct: i.cost > 0 ? profit / i.cost * 100 : 0,
        break_even: i.fixed_monthly > 0 ? (profit > 0.004 ? Math.ceil(i.fixed_monthly / profit) : null) : 0 };
}

function bootProfit(p: Payload): void {
    const form = document.getElementById('sell-form') as HTMLFormElement | null;
    const platforms = p.platforms;
    if (!form || !platforms) return;
    const fields = p.fields ?? {};
    const rates = p.rates ?? {};
    const toView = (a: Amount | null): number => { if (!a) return 0; const czk = a.amount * (a.currency === 'CZK' ? 1 : Number(rates[a.currency] ?? 1)); return fromCzk(czk); };
    const euro = currency() === 'EUR';
    const moneyKeys = ['price', 'cost', 'ship_charged', 'ship_actual', 'fixed_monthly', 'stall_fee'];
    const start: Record<string, number> = {};
    Object.entries(p.values ?? {}).forEach(([k, v]) => { if (typeof v === 'number') start[k] = moneyKeys.includes(k) && euro ? Math.round(fromCzk(v) * 100) / 100 : v; });
    const read = (): ProfitIn => ({ ...readNumbers(form, fields), platform: form.querySelector<HTMLInputElement>('[data-choice="platform"]:checked')?.value ?? 'etsy',
        vat: form.querySelector<HTMLInputElement>('[data-flag="vat"]')?.checked ?? false, foreign: form.querySelector<HTMLInputElement>('[data-flag="foreign"]')?.checked ?? false } as ProfitIn);
    const set = (values: Record<string, unknown>): void => {
        Object.entries(values).forEach(([k, v]) => {
            const num = form.querySelector<HTMLInputElement>(`[data-param="${k}"]`); if (num && typeof v === 'number') num.value = String(v);
            const flag = form.querySelector<HTMLInputElement>(`[data-flag="${k}"]`); if (flag && typeof v === 'boolean') flag.checked = v;
            const choice = form.querySelector<HTMLInputElement>(`[data-choice="${k}"][value="${String(v)}"]`); if (choice) choice.checked = true;
        });
    };
    set(start);
    const query = new URLSearchParams(location.search);
    const given = ['cost', 'price'].filter((k) => query.has(k));
    const save = remember(`sell.profit.${currency()}`, form, () => read() as unknown as Record<string, unknown>, (saved) => set(Object.fromEntries(Object.entries(saved).filter(([k]) => !given.includes(k)))), false);
    const applyWhen = (): void => {
        const platform = read().platform;
        form.querySelectorAll<HTMLElement>('[data-when]').forEach((el) => { const [key, list] = el.dataset.when!.split('='); if (key === 'platform') el.classList.toggle('hidden', !list.split(',').includes(platform)); });
    };
    const rows = document.getElementById('profit-rows')!; const three = document.getElementById('profit-three')!;
    const pct = nf(p.locale, 1);
    const render = (): void => {
        const i = read();
        const f = platforms[i.platform];
        const r = profitOf(i, f, toView, p.vat_pct ?? 21);
        const line = (key: string, v: number, strong = false, minus = true): string => `<tr${strong ? ' class="font-semibold text-ink"' : ''}><td class="py-1.5 pr-3 text-left">${esc(t(p, `profit.row.${key}`))}</td><td class="py-1.5 text-right">${esc(money(minus && v > 0 ? -v : v, undefined, false))}</td></tr>`;
        const feeLines = (['listing', 'transaction', 'payment', 'currency', 'monthly', 'stall'] as const).filter((k) => r.fees[k] > 0.004).map((k) => line(k, r.fees[k]));
        rows.innerHTML = [line('revenue', r.revenue, false, false), ...(r.vat > 0.004 ? [line('vat', r.vat)] : []), ...feeLines, line('shipping', i.ship_actual), line('net', r.net, true, false), line('cost', i.cost), line('profit', r.profit, true, false)].join('');
        document.getElementById('profit-total')!.textContent = money(r.profit);
        document.getElementById('profit-margin')!.textContent = `${pct.format(r.margin_pct)} %`;
        document.getElementById('profit-net')!.textContent = money(r.net);
        const brk = document.getElementById('profit-break')!;
        brk.textContent = r.break_even === 0 ? t(p, 'profit.break.none') : r.break_even === null ? t(p, 'profit.break.never') : t(p, 'profit.break', { f: money(i.fixed_monthly), n: r.break_even });
        document.getElementById('profit-asof')!.textContent = t(p, 'profit.asof', { platform: t(p, `platform.${i.platform}`), date: f.as_of, source: f.source });
        document.getElementById('profit-platform-note')!.textContent = t(p, `platform.${i.platform}.note`);
        three.innerHTML = [0.9, 1, 1.1].map((k) => {
            const q = profitOf({ ...i, price: i.price * k }, f, toView, p.vat_pct ?? 21);
            return `<tr${k === 1 ? ' class="font-semibold text-ink"' : ''}><td class="py-1.5 pr-3 text-left">${esc(money(i.price * k))}</td><td class="py-1.5 pr-3 text-left">${esc(money(q.fees_total))}</td><td class="py-1.5 pr-3 text-left">${esc(money(q.profit))}</td><td class="py-1.5 text-left">${esc(pct.format(q.margin_pct))} %</td></tr>`;
        }).join('');
        const toPlan = document.getElementById('profit-to-plan') as HTMLAnchorElement | null;
        if (toPlan && p.plan) toPlan.href = `${p.plan}?cost=${encodeURIComponent(String(Math.round(i.cost * 100) / 100))}&price=${encodeURIComponent(String(Math.round(i.price * 100) / 100))}`;
        save();
    };
    form.addEventListener('input', () => { applyWhen(); render(); });
    form.addEventListener('change', () => { applyWhen(); render(); });
    document.getElementById('sell-reset')?.addEventListener('click', () => { set(start); applyWhen(); render(); });
    applyWhen();
    render();
}

// ── plan ─────────────────────────────────────────────────────────────────────────────────────────────

export interface PlanProduct { name: string; cost: number; price: number; qty: number; photo: boolean; listed: boolean }
export interface PlanIn { name: string; products: PlanProduct[]; fixed: number; season: string; months: number[]; start: number; channel: string; done: string[] }
export interface PlanMonth { month: number; revenue: number; variable: number; fixed: number; profit: number; cumulative: number }
export interface PlanStep { key: string; id: string; product: string | null; tool: string | null; done: boolean }
export interface PlanResult { months: PlanMonth[]; revenue: number; profit: number; break_even_month: number | null; pieces: number; steps: PlanStep[] }

/** App\Domain\Sell\Plan::calculate and ::steps, the same arithmetic. */
export function planOf(p: PlanIn): PlanResult {
    const months: PlanMonth[] = [];
    let cum = 0; let breakEven: number | null = null; let revenueTotal = 0; let profitTotal = 0; let pieces = 0;
    for (let i = 0; i < 12; i++) {
        const month = ((p.start - 1 + i) % 12) + 1;
        const k = p.months[month - 1] ?? 1;
        let revenue = 0; let variable = 0;
        p.products.forEach((prod) => { const qty = prod.qty * k; revenue += prod.price * qty; variable += prod.cost * qty; pieces += qty; });
        const profit = revenue - variable - p.fixed;
        cum += profit;
        if (breakEven === null && cum > 0.004) breakEven = i + 1;
        revenueTotal += revenue; profitTotal += profit;
        months.push({ month, revenue, variable, fixed: p.fixed, profit, cumulative: cum });
    }
    const steps: PlanStep[] = [];
    const add = (key: string, product: string | null = null, tool: string | null = null): void => { const id = key + (product !== null ? `:${product}` : ''); steps.push({ key, id, product, tool, done: p.done.includes(id) }); };
    if (!p.products.length) add('add_product');
    p.products.forEach((prod, i) => {
        const name = prod.name !== '' ? prod.name : `#${i + 1}`;
        if (prod.cost <= 0) add('cost', name, 'cost');
        if (prod.price <= 0) add('price', name, 'profit'); else if (prod.cost > 0 && prod.price < prod.cost) add('price_below_cost', name, 'profit');
        if (!prod.photo) add('photo', name);
        if (!prod.listed) add('list', name);
    });
    if (p.channel === '') add('channel', null, 'vendors');
    if (p.fixed <= 0) add('fixed');
    return { months, revenue: revenueTotal, profit: profitTotal, break_even_month: breakEven, pieces, steps };
}

function bootPlan(p: Payload): void {
    const form = document.getElementById('sell-form') as HTMLFormElement | null;
    if (!form) return;
    const seasons = p.seasons ?? { flat: [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1] };
    const maxProducts = p.max_products ?? 20;
    const list = document.getElementById('plan-products')!;
    const tpl = document.getElementById('plan-product-row') as HTMLTemplateElement;
    const fmt = nf(p.locale, 0);
    const monthName = (m: number): string => new Date(2026, m - 1, 1).toLocaleDateString(p.locale, { month: 'short' });
    let done: string[] = [];

    const addRow = (prod?: Partial<PlanProduct>): HTMLElement | null => {
        if (list.children.length >= maxProducts) return null;
        const row = (tpl.content.firstElementChild as HTMLElement).cloneNode(true) as HTMLElement;
        const set = (k: string, v: unknown) => { const el = row.querySelector<HTMLInputElement>(`[data-prod="${k}"]`); if (!el) return; if (el.type === 'checkbox') el.checked = !!v; else if (v !== undefined) el.value = String(v); };
        if (prod) Object.entries(prod).forEach(([k, v]) => set(k, v));
        row.querySelector('[data-remove]')!.addEventListener('click', () => { row.remove(); form.dispatchEvent(new Event('input', { bubbles: true })); });
        list.appendChild(row);
        return row;
    };
    const read = (): PlanIn => {
        const products: PlanProduct[] = Array.from(list.querySelectorAll<HTMLElement>('[data-product]')).map((row) => {
            const v = (k: string) => row.querySelector<HTMLInputElement>(`[data-prod="${k}"]`)!;
            return { name: v('name').value.trim().slice(0, 60), cost: Math.max(0, Number(v('cost').value) || 0), price: Math.max(0, Number(v('price').value) || 0), qty: Math.max(0, Number(v('qty').value) || 0), photo: v('photo').checked, listed: v('listed').checked };
        });
        const season = form.querySelector<HTMLInputElement>('[data-choice="season"]:checked')?.value ?? 'flat';
        const custom = Array.from(form.querySelectorAll<HTMLInputElement>('[data-month]')).sort((a, b) => Number(a.dataset.month) - Number(b.dataset.month)).map((el) => Math.max(0, Math.min(10, Number(el.value) || 0)));
        const months = season === 'custom' ? custom : (seasons[season] ?? seasons.flat);
        const text = (k: string) => (form.querySelector<HTMLInputElement | HTMLSelectElement>(`[data-param-text="${k}"]`)?.value ?? '').trim();
        return { name: text('name').slice(0, 80), products, fixed: Math.max(0, Number(form.querySelector<HTMLInputElement>('[data-param="fixed"]')?.value) || 0), season, months, start: Math.max(1, Math.min(12, Number(text('start')) || 1)), channel: text('channel'), done };
    };
    const apply = (s: Partial<PlanIn>): void => {
        list.innerHTML = '';
        (s.products ?? []).forEach((prod) => addRow(prod));
        const fixed = form.querySelector<HTMLInputElement>('[data-param="fixed"]'); if (fixed && typeof s.fixed === 'number') fixed.value = String(s.fixed);
        const setText = (k: string, v: unknown) => { const el = form.querySelector<HTMLInputElement | HTMLSelectElement>(`[data-param-text="${k}"]`); if (el && v !== undefined && v !== null) el.value = String(v); };
        setText('name', s.name); setText('channel', s.channel); setText('start', s.start);
        const season = form.querySelector<HTMLInputElement>(`[data-choice="season"][value="${s.season ?? 'flat'}"]`); if (season) season.checked = true;
        if (s.months && s.months.length === 12) form.querySelectorAll<HTMLInputElement>('[data-month]').forEach((el) => { el.value = String(s.months![Number(el.dataset.month) - 1]); });
        done = Array.isArray(s.done) ? s.done.filter((d) => typeof d === 'string') : [];
    };
    const applyWhen = (): void => {
        const season = form.querySelector<HTMLInputElement>('[data-choice="season"]:checked')?.value ?? 'flat';
        form.querySelectorAll<HTMLElement>('[data-when]').forEach((el) => { const [key, value] = el.dataset.when!.split('='); if (key === 'season') el.classList.toggle('hidden', season !== value); });
    };
    const save = remember('sell.plan', form, () => read() as unknown as Record<string, unknown>, (saved) => apply(saved as Partial<PlanIn>), false);
    // a cost and a price handed over: a product to start with
    const query = new URLSearchParams(location.search);
    if (query.has('price') || query.has('cost')) {
        const cost = Number(query.get('cost')) || 0; const price = Number(query.get('price')) || 0;
        const current = read();
        if (!current.products.some((x) => Math.abs(x.cost - cost) < 0.005 && Math.abs(x.price - price) < 0.005)) addRow({ name: '', cost, price, qty: 10, photo: false, listed: false });
    }
    if (!list.children.length) addRow({ name: '', cost: 0, price: 0, qty: 10, photo: false, listed: false });

    const chart = document.getElementById('plan-chart') as unknown as SVGSVGElement;
    const rows = document.getElementById('plan-rows')!; const stepsEl = document.getElementById('plan-steps')!;
    const drawChart = (r: PlanResult): void => {
        const W = 720, H = 260, left = 8, right = 8, top = 12, bottom = 28;
        const vals = r.months.flatMap((m) => [m.revenue, m.profit, m.cumulative]);
        const hi = Math.max(1, ...vals); const lo = Math.min(0, ...vals);
        const y = (v: number) => top + (hi - v) / (hi - lo || 1) * (H - top - bottom);
        const slot = (W - left - right) / 12;
        const parts: string[] = [];
        parts.push(`<line x1="${left}" x2="${W - right}" y1="${y(0)}" y2="${y(0)}" stroke="#b4aea6" stroke-width="1"/>`);
        r.months.forEach((m, i) => {
            const x = left + i * slot;
            const bw = slot * 0.3;
            parts.push(`<rect x="${x + slot * 0.15}" y="${Math.min(y(0), y(m.revenue))}" width="${bw}" height="${Math.abs(y(m.revenue) - y(0))}" rx="3" fill="#cfc9c0"/>`);
            parts.push(`<rect x="${x + slot * 0.5}" y="${Math.min(y(0), y(m.profit))}" width="${bw}" height="${Math.abs(y(m.profit) - y(0))}" rx="3" fill="${m.profit >= 0 ? '#54966f' : '#c45a5a'}"/>`);
            parts.push(`<text x="${x + slot / 2}" y="${H - 8}" text-anchor="middle" font-size="11" fill="#787e8a">${esc(monthName(m.month))}</text>`);
        });
        const line = r.months.map((m, i) => `${left + i * slot + slot / 2},${y(m.cumulative)}`).join(' ');
        parts.push(`<polyline points="${line}" fill="none" stroke="#e96e2c" stroke-width="3" stroke-linejoin="round"/>`);
        r.months.forEach((m, i) => parts.push(`<circle cx="${left + i * slot + slot / 2}" cy="${y(m.cumulative)}" r="4" fill="#fff" stroke="#e96e2c" stroke-width="2"/>`));
        chart.innerHTML = parts.join('');
    };
    const render = (): void => {
        const i = read();
        const r = planOf(i);
        document.getElementById('plan-profit')!.textContent = money(r.profit);
        document.getElementById('plan-revenue')!.textContent = money(r.revenue);
        document.getElementById('plan-pieces')!.textContent = fmt.format(r.pieces);
        document.getElementById('plan-break')!.textContent = r.break_even_month ? t(p, 'plan.break.month', { n: r.break_even_month }) : t(p, 'plan.break.never');
        drawChart(r);
        rows.innerHTML = r.months.map((m) => `<tr><td class="py-1 pr-3 text-left">${esc(monthName(m.month))}</td><td class="py-1 pr-3 text-right">${esc(money(m.revenue))}</td><td class="py-1 pr-3 text-right">${esc(money(m.variable + m.fixed))}</td><td class="py-1 pr-3 text-right ${m.profit < 0 ? 'text-danger' : ''}">${esc(money(m.profit))}</td><td class="py-1 text-right">${esc(money(m.cumulative))}</td></tr>`).join('');
        stepsEl.innerHTML = r.steps.length ? r.steps.map((s) => {
            const text = t(p, `plan.step.${s.key}`, { product: s.product ?? '' });
            const link = s.tool && (p as unknown as Record<string, string | undefined>)[s.tool] ? ` <a href="${esc((p as unknown as Record<string, string>)[s.tool])}" class="text-action-dark underline">→</a>` : '';
            return `<li><label class="flex items-start gap-2 ${s.done ? 'text-muted line-through' : 'text-ink'}"><input type="checkbox" data-step="${esc(s.id)}" class="mt-0.5 h-4 w-4 accent-ink" ${s.done ? 'checked' : ''}><span>${esc(text)}${link}</span></label></li>`;
        }).join('') : `<li class="text-muted">${esc(t(p, 'plan.steps.none'))}</li>`;
        stepsEl.querySelectorAll<HTMLInputElement>('[data-step]').forEach((cb) => cb.addEventListener('change', () => { const id = cb.dataset.step!; done = cb.checked ? [...done.filter((d) => d !== id), id] : done.filter((d) => d !== id); render(); }));
        save();
    };
    form.addEventListener('input', () => { applyWhen(); render(); });
    form.addEventListener('change', () => { applyWhen(); render(); });
    document.getElementById('plan-add')?.addEventListener('click', () => { addRow({ name: '', cost: 0, price: 0, qty: 10, photo: false, listed: false }); render(); });
    document.getElementById('sell-reset')?.addEventListener('click', () => { apply({ products: [{ name: '', cost: 0, price: 0, qty: 10, photo: false, listed: false }], fixed: 0, season: 'flat', months: seasons.flat, start: p.start ?? 1, channel: '', name: '', done: [] }); applyWhen(); render(); });
    // exports: CSV in the browser, PDF from the server with the plan as it is
    document.getElementById('plan-csv')?.addEventListener('click', () => {
        const r = planOf(read());
        const sep = p.locale === 'en' ? ',' : ';';
        const lines = [[t(p, 'plan.col.month'), t(p, 'plan.col.revenue'), t(p, 'plan.col.costs'), t(p, 'plan.col.profit'), t(p, 'plan.col.cumulative')].join(sep), ...r.months.map((m) => [monthName(m.month), m.revenue.toFixed(2), (m.variable + m.fixed).toFixed(2), m.profit.toFixed(2), m.cumulative.toFixed(2)].join(sep))];
        const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['\ufeff' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' })); a.download = 'plan.csv'; a.click(); URL.revokeObjectURL(a.href);
    });
    document.getElementById('plan-pdf')?.addEventListener('click', () => {
        const pdfForm = document.getElementById('plan-pdf-form') as HTMLFormElement | null;
        if (!pdfForm) return;
        pdfForm.querySelector<HTMLInputElement>('[name="plan"]')!.value = JSON.stringify(read());
        pdfForm.submit();
    });
    // the account's plans
    const saved = document.getElementById('plan-saved') as HTMLSelectElement | null;
    const msg = document.getElementById('plan-save-msg');
    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    const headers = { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token };
    let plans: { id: number; name: string; data: PlanIn }[] = [];
    const refreshSaved = async (): Promise<void> => {
        if (!saved || !p.plans) return;
        try {
            const res = await fetch(p.plans, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            plans = res.ok ? ((await res.json()).plans as typeof plans) : [];
        } catch { plans = []; }
        saved.innerHTML = `<option value="">${esc(t(p, 'plan.save.open'))}</option>` + plans.map((x) => `<option value="${x.id}">${esc(x.name)}</option>`).join('');
    };
    document.getElementById('plan-save')?.addEventListener('click', async () => {
        if (!p.plans || !msg) return;
        const data = read();
        const name = data.name || t(p, 'plan.f.name.placeholder');
        try {
            const res = await fetch(p.plans, { method: 'POST', credentials: 'same-origin', headers, body: JSON.stringify({ name, data }) });
            const body = await res.json();
            msg.textContent = res.ok ? t(p, 'plan.save.saved', { name }) : (body.message ?? t(p, 'plan.save.failed'));
            if (res.ok) await refreshSaved();
        } catch { msg.textContent = t(p, 'plan.save.failed'); }
    });
    saved?.addEventListener('change', () => {
        const plan = plans.find((x) => String(x.id) === saved.value);
        if (plan) { apply({ ...plan.data, name: plan.name }); applyWhen(); render(); }
    });
    void refreshSaved();
    applyWhen();
    render();
}

// ── vendors ──────────────────────────────────────────────────────────────────────────────────────────

interface EventRow { id: number; name: string; type: string; city: string; address: string | null; country: string; lat: number | null; lng: number | null; starts_on: string | null; ends_on: string | null; url: string | null; stall_fee: string | null; note: string | null; status: string; distance_km: number | null; saved: boolean; ics: string }
interface Pick { id: number; why: string; make: string; tool: string | null; tool_key: string | null }
interface LeafletLike { map: (el: HTMLElement, o?: unknown) => LeafletMap; tileLayer: (url: string, o: unknown) => { addTo: (m: LeafletMap) => unknown }; marker: (ll: [number, number], o?: unknown) => LeafletMarker; circle: (ll: [number, number], o: unknown) => LeafletLayer; latLngBounds: (pts: [number, number][]) => unknown; divIcon: (o: unknown) => unknown }
interface LeafletMap { setView: (ll: [number, number], z: number) => LeafletMap; fitBounds: (b: unknown, o?: unknown) => void; removeLayer: (l: LeafletLayer) => void }
interface LeafletLayer { addTo: (m: LeafletMap) => LeafletLayer; remove: () => void }
interface LeafletMarker extends LeafletLayer { bindPopup: (html: string) => LeafletMarker }

function bootVendors(p: Payload & { search?: string; fit?: string; save?: string | null; saved?: EventRow[] }): void {
    const form = document.getElementById('sell-form') as HTMLFormElement | null;
    if (!form || !p.search) return;
    const city = document.getElementById('vendors-city') as HTMLInputElement;
    const country = document.getElementById('vendors-country') as HTMLSelectElement;
    const msg = document.getElementById('vendors-msg')!; const list = document.getElementById('vendors-list')!; const title = document.getElementById('vendors-title')!;
    const picksEl = document.getElementById('vendors-picks')!; const fitBtn = document.getElementById('vendors-fit') as HTMLButtonElement; const fitMsg = document.getElementById('vendors-fit-msg')!;
    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    const date = (iso: string | null): string => (iso ? new Date(iso + 'T00:00:00').toLocaleDateString(p.locale, { day: 'numeric', month: 'short', year: 'numeric' }) : '');
    let found: EventRow[] = []; let place: { lat: number; lng: number } | null = null; let tab: 'found' | 'saved' = 'found';
    const saved = new Map<number, EventRow>(); (p.saved ?? []).forEach((e) => saved.set(e.id, e));
    const settings = () => ({ city: city.value, country: country.value, radius: form.querySelector<HTMLInputElement>('[name="radius"]:checked')?.value ?? '50', days: form.querySelector<HTMLInputElement>('[name="days"]:checked')?.value ?? '90', types: Array.from(form.querySelectorAll<HTMLInputElement>('[name="types"]:checked')).map((c) => c.value) });
    const save = remember('sell.vendors', form, () => settings(), (s) => {
        if (typeof s.city === 'string') city.value = s.city; if (typeof s.country === 'string') country.value = s.country;
        const pick = (name: string, v: unknown) => { const el = form.querySelector<HTMLInputElement>(`[name="${name}"][value="${String(v)}"]`); if (el) el.checked = true; };
        pick('radius', s.radius); pick('days', s.days);
        if (Array.isArray(s.types)) form.querySelectorAll<HTMLInputElement>('[name="types"]').forEach((c) => { c.checked = (s.types as string[]).includes(c.value); });
    }, false);

    // the map: Leaflet from the CDN (deferred), the OpenStreetMap tiles; markers for the events, a circle for the radius
    let map: LeafletMap | null = null; let layers: LeafletLayer[] = [];
    const leaflet = (): LeafletLike | null => (window as unknown as { L?: LeafletLike }).L ?? null;
    const drawMap = (rows: EventRow[], centre: { lat: number; lng: number } | null, radiusKm: number): void => {
        const L = leaflet(); const el = document.getElementById('vendors-map');
        if (!L || !el) return;
        if (!map) { map = L.map(el, { scrollWheelZoom: false }).setView([49.8, 15.5], 7); L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap' }).addTo(map); }
        layers.forEach((l) => l.remove()); layers = [];
        const pts: [number, number][] = [];
        if (centre) { layers.push(L.circle([centre.lat, centre.lng], { radius: radiusKm * 1000, color: '#5c80b8', weight: 1, fillOpacity: 0.06 }).addTo(map)); pts.push([centre.lat, centre.lng]); }
        rows.forEach((e) => {
            if (e.lat === null || e.lng === null) return;
            const m = L.marker([e.lat, e.lng]).bindPopup(`<strong>${esc(e.name)}</strong><br>${esc(date(e.starts_on))}${e.ends_on && e.ends_on !== e.starts_on ? ' – ' + esc(date(e.ends_on)) : ''}<br>${esc(e.city)}`);
            layers.push(m.addTo(map!)); pts.push([e.lat, e.lng]);
        });
        if (pts.length) map.fitBounds(L.latLngBounds(pts), { padding: [24, 24], maxZoom: 11 });
    };
    const item = (e: EventRow): string => {
        const when = e.starts_on ? `${date(e.starts_on)}${e.ends_on && e.ends_on !== e.starts_on ? ' – ' + date(e.ends_on) : ''}` : '';
        const links = [e.url ? `<a href="${esc(e.url)}" target="_blank" rel="noopener" class="underline">${esc(t(p, 'vendors.item.web'))}</a>` : '', `<a href="${esc(e.ics)}" class="underline">${esc(t(p, 'vendors.item.ics'))}</a>`,
            p.save ? `<button type="button" data-save="${e.id}" class="${saved.has(e.id) ? 'font-medium text-ok' : 'underline'}">${esc(t(p, saved.has(e.id) ? 'vendors.item.unsave' : 'vendors.item.save'))}</button>` : ''].filter(Boolean).join(' · ');
        return `<li class="py-2" data-event="${e.id}"><div class="flex flex-wrap items-baseline justify-between gap-x-3"><span class="font-medium text-ink">${esc(e.name)}</span><span class="num text-xs text-muted">${e.distance_km !== null ? esc(t(p, 'vendors.item.distance', { km: nf(p.locale, 0).format(e.distance_km) })) : ''}</span></div>
            <div class="text-xs text-muted">${esc(t(p, `vendors.type.${e.type}`))} · ${esc(e.city)}${when ? ' · ' + esc(when) : ''}${e.status === 'verify' ? ` · <span class="text-warn">${esc(t(p, 'vendors.item.verify'))}</span>` : ''}${e.stall_fee ? ' · ' + esc(t(p, 'vendors.item.fee', { fee: e.stall_fee })) : ''}</div>
            ${e.note ? `<div class="mt-0.5 text-xs text-muted">${esc(e.note)}</div>` : ''}<div class="mt-1 text-xs">${links}</div></li>`;
    };
    const render = (): void => {
        const rows = tab === 'found' ? found : Array.from(saved.values());
        list.innerHTML = rows.length ? rows.map(item).join('') : `<li class="py-2 text-sm text-muted">${esc(t(p, tab === 'found' ? 'vendors.list.empty' : 'vendors.saved.empty'))}</li>`;
        document.getElementById('vendors-saved-count')!.textContent = String(saved.size);
        document.querySelectorAll<HTMLButtonElement>('[data-tab]').forEach((b) => { const on = b.dataset.tab === tab; b.classList.toggle('chip-on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
        list.querySelectorAll<HTMLButtonElement>('[data-save]').forEach((b) => b.addEventListener('click', async () => {
            if (!p.save) return;
            try {
                const res = await fetch(p.save, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify({ event: Number(b.dataset.save) }) });
                if (!res.ok) return;
                const body = (await res.json()) as { saved: boolean; event: EventRow };
                if (body.saved) saved.set(body.event.id, body.event); else saved.delete(body.event.id);
                found = found.map((e) => (e.id === body.event.id ? { ...e, saved: body.saved } : e));
                render();
            } catch { /* leave as it was */ }
        }));
        fitBtn.disabled = found.length === 0;
        drawMap(tab === 'found' ? found : rows, tab === 'found' ? place : null, Number(settings().radius));
    };
    document.querySelectorAll<HTMLButtonElement>('[data-tab]').forEach((b) => b.addEventListener('click', () => { tab = b.dataset.tab as 'found' | 'saved'; render(); }));
    form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const s = settings();
        if (s.city.trim().length < 2) { city.focus(); return; }
        msg.textContent = t(p, 'vendors.msg.searching'); save();
        const q = new URLSearchParams({ city: s.city.trim(), country: s.country, radius: s.radius, days: s.days }); s.types.forEach((x) => q.append('types[]', x));
        try {
            const res = await fetch(`${p.search}?${q.toString()}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const body = await res.json();
            if (res.status === 422 && body.error === 'no_place') { msg.textContent = t(p, 'vendors.msg.no_place'); return; }
            if (!res.ok) throw new Error('search');
            found = body.events as EventRow[]; place = body.place; tab = 'found'; picksEl.classList.add('hidden');
            title.textContent = found.length ? t(p, 'vendors.msg.found', { n: found.length, r: body.radius, city: body.place.city }) : t(p, 'vendors.msg.none');
            msg.textContent = '';
            render();
        } catch { msg.textContent = t(p, 'vendors.msg.failed'); }
    });
    fitBtn.addEventListener('click', async () => {
        const make = (document.getElementById('vendors-make') as HTMLTextAreaElement).value.trim();
        if (!found.length) { fitMsg.textContent = t(p, 'vendors.fit.first'); return; }
        if (make.length < 3) { (document.getElementById('vendors-make') as HTMLTextAreaElement).focus(); return; }
        fitBtn.disabled = true; fitMsg.textContent = t(p, 'vendors.msg.searching');
        try {
            const res = await fetch(p.fit!, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify({ make, events: found.slice(0, 60).map((e) => e.id) }) });
            const body = await res.json();
            if (res.status === 429) { fitMsg.textContent = t(p, 'vendors.fit.limit'); return; }
            if (res.status === 503) { fitMsg.textContent = t(p, 'vendors.fit.unavailable'); return; }
            if (!res.ok) throw new Error('fit');
            const picks = body.picks as Pick[];
            fitMsg.textContent = '';
            picksEl.classList.remove('hidden');
            picksEl.innerHTML = `<div class="font-semibold text-ink">${esc(t(p, 'vendors.fit.picks'))}</div>` + (picks.length ? `<ol class="mt-2 list-decimal space-y-2 pl-5">${picks.map((k) => { const e = found.find((x) => x.id === k.id); return `<li><span class="font-medium text-ink">${esc(e?.name ?? '')}</span><span class="block text-muted">${esc(k.why)}</span><span class="block">${esc(t(p, 'vendors.fit.make_it', { make: k.make }))}${k.tool ? ` <a href="${esc(k.tool)}" class="text-action-dark underline">${esc(t(p, 'vendors.fit.tool'))} →</a>` : ''}</span></li>`; }).join('')}</ol>` : `<p class="mt-1 text-muted">${esc(t(p, 'vendors.fit.none'))}</p>`);
        } catch { fitMsg.textContent = t(p, 'vendors.msg.failed'); } finally { fitBtn.disabled = found.length === 0; }
    });
    render();
    if (city.value.trim().length >= 2) form.requestSubmit();
}

// ── boot ─────────────────────────────────────────────────────────────────────────────────────────────

export function bootSell(): void {
    const page = document.getElementById('sell-page');
    const p = cfg();
    if (!page || !p) return;
    sections();
    const modules: Record<string, (p: Payload) => void> = { cost: bootCost, profit: bootProfit, plan: bootPlan, vendors: bootVendors, image: bootImage, listing: bootListing, photo: bootPhoto };
    modules[page.dataset.sell ?? '']?.(p);
}
