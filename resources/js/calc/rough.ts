/**
 * Instant estimate in the browser. Mirrors app/Domain/Calculation/RoughEstimator.php and PriceEngine.php.
 * Constants come from window.MP_CONFIG (config/pricing.php + materials). Keep both sides in sync.
 */
export interface RoughConfig {
    perimeters: number;
    line_width_mm: number;
    shell_fraction_fallback: number;
    support_factor: number;
    minutes_per_gram: number;
    overhead_minutes: number;
    quality_time_factor: Record<string, number>;
    quality_layer_mm: Record<string, number>;
    range_low: number;
    range_high: number;
}

export interface Profile {
    key: string;
    hourly_rate: number;
    price_per_gram: number;
    setup_fee: number;
    margin_pct: number;
    min_price: number;
    lead_time_days: number;
    qty_discounts?: { from: number; pct: number }[];
}

export interface Params {
    material: string;
    quality: string;
    infill: number;
    supports: boolean | null;
    scale: number;
    quantity: number;
    vase?: boolean;
}

export interface Geometry {
    volume_mm3: number;
    area_mm2: number | null;
}

export interface Estimate {
    grams: number;
    minutes: number;
}

export interface Breakdown {
    profile: string;
    unit: { material: number; time: number; royalty: number };
    setup: number;
    subtotal: number;
    discount: number;
    margin: number;
    total: number;
    lead_time_days: number;
}

export function estimate(cfg: RoughConfig, density: number, g: Geometry, p: Params): Estimate {
    const s = p.scale;
    const vol = Math.max(0, g.volume_mm3) * s * s * s;
    const area = g.area_mm2 != null ? Math.max(0, g.area_mm2) * s * s : null;
    const perimeters = p.vase ? 1 : cfg.perimeters;
    const shell = area != null ? Math.min(vol, area * perimeters * cfg.line_width_mm) : vol * cfg.shell_fraction_fallback;
    const inner = Math.max(0, vol - shell);
    const infill = p.vase ? 0 : Math.max(0, Math.min(100, p.infill)) / 100;
    const material = shell + inner * infill;
    let grams = (material / 1000) * density;
    if (p.supports) grams *= cfg.support_factor;
    const tf = cfg.quality_time_factor[p.quality] ?? 1;
    const minutes = Math.max(1, Math.round(grams * cfg.minutes_per_gram * tf + cfg.overhead_minutes));
    return { grams: Math.round(grams * 10) / 10, minutes };
}

export function price(roundTo: number, prof: Profile, grams: number, minutes: number, quantity: number, royalty = 0): Breakdown {
    const qty = Math.max(1, quantity);
    const unitMaterial = grams * prof.price_per_gram;
    const unitTime = (minutes / 60) * prof.hourly_rate;
    const unit = unitMaterial + unitTime + Math.max(0, royalty);
    const subtotal = unit * qty + prof.setup_fee;
    let pct = 0;
    for (const d of prof.qty_discounts ?? []) if (qty >= d.from) pct = Math.max(pct, d.pct);
    const discount = (subtotal * pct) / 100;
    const margin = ((subtotal - discount) * prof.margin_pct) / 100;
    const raw = subtotal - discount + margin;
    const step = Math.max(1, roundTo);
    const total = Math.max(prof.min_price, Math.ceil(raw / step) * step);
    return {
        profile: prof.key,
        unit: { material: unitMaterial, time: unitTime, royalty: Math.max(0, royalty) },
        setup: prof.setup_fee,
        subtotal,
        discount,
        margin,
        total,
        lead_time_days: prof.lead_time_days,
    };
}

export function range(cfg: RoughConfig, roundTo: number, totals: number[], rough: boolean): [number, number] {
    if (!totals.length) return [0, 0];
    let min = Math.min(...totals);
    let max = Math.max(...totals);
    if (rough) {
        const step = Math.max(1, roundTo);
        min = Math.floor((min * cfg.range_low) / step) * step;
        max = Math.ceil((max * cfg.range_high) / step) * step;
    }
    return [min, max];
}
