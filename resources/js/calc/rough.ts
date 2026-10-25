/**
 * Instant estimate in the browser. Mirrors app/Domain/Calculation/RoughEstimator.php, PriceEngine.php and ShapeRange.php.
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
    range: RangeConfig;
}

/** How far the slice may land from the estimate, by the shape (config/pricing.php rough.range, docs/M.md §6). */
export interface RangeConfig {
    height_mm: [number, number];
    fill_g_cm3: [number, number];
    small_minutes: [number, number];
    overhang_share: [number, number];
    triangles: [number, number];
    time: RangeTerms;
    price: RangeTerms;
}

export interface RangeTerms { high: number; height: number; thin: number; supports: number; auto: number; low: number; small: number; low_triangles?: number }

/** What the estimate cannot see and the range follows: null = unknown (counted on the wide side). */
export interface Shape {
    height_mm: number | null;
    fill_g_cm3: number | null;
    overhang_share: number | null;
    /** a surface of hundreds of thousands of triangles never prints faster than the estimate: the lower bound rises (docs/M.md §6.6) */
    triangles?: number | null;
}

/** [low, high] as factors of the estimate's minutes and of the price counted from it. */
export interface RangeFactors { time: [number, number]; price: [number, number] }

export interface Profile {
    key: string;
    hourly_rate: number;
    price_per_gram: number;
    setup_fee: number;
    margin_pct: number;
    min_price: number;
    lead_time_days: number;
    qty_discounts?: { from: number; pct: number }[];
    time_factor?: number;
    /** the farm's list for one calculator material (its kind of that material, or the cheapest kind weighed as that kind); absent on marketplace lists */
    material?: string;
    label?: string;
}

/**
 * The lists that price a material: the farm sends one list per material (price per gram already in the kind it would
 * print with), so the instant estimate and the exact price count with the same kind; lists without a material (the
 * marketplace's) apply to every material. A material no list names (resin on an FDM farm) gets none.
 */
export function profilesFor(profiles: Profile[], material: string): Profile[] {
    const own = profiles.filter((p) => p.material);
    if (!own.length) return profiles;
    const code = material.toUpperCase();
    return own.filter((p) => (p.material ?? '').toUpperCase() === code);
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
    const unitTime = ((minutes * (prof.time_factor ?? 1)) / 60) * prof.hourly_rate;
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

/** The shape the range follows, from the model's box as it prints, the estimated grams and the browser's overhang measure. */
export function shapeOf(bbox: { x: number; y: number; z: number } | null, scale: number, grams: number, overhangShare: number | null, triangles: number | null = null): Shape {
    const mm3 = bbox ? bbox.x * bbox.y * bbox.z * scale * scale * scale : 0;
    return {
        height_mm: bbox ? bbox.z * scale : null,
        fill_g_cm3: mm3 > 0 ? grams / (mm3 / 1000) : null,
        overhang_share: overhangShare,
        triangles,
    };
}

/** 0 up to the first edge, 1 from the second, straight between. */
function ramp(v: number, edges: [number, number]): number {
    const [a, b] = edges;
    if (b <= a) return v >= b ? 1 : 0;
    return Math.max(0, Math.min(1, (v - a) / (b - a)));
}

/**
 * How far the slice may land from the estimate (ShapeRange.php): thin walls, lattices and long thin parts (little
 * material in a big box), height, supports and overhangs raise the upper bound; a small print lowers the lower one, a
 * surface of hundreds of thousands of triangles raises it (such a model never prints faster than the estimate).
 * Unknown height or fill count on the wide side; unknown triangles leave the lower bound where a plain mesh has it.
 */
export function rangeFactors(cfg: RangeConfig, shape: Shape, supports: boolean | null, minutes: number): RangeFactors {
    const height = shape.height_mm === null ? 1 : ramp(shape.height_mm, cfg.height_mm);
    const thin = shape.fill_g_cm3 === null ? 1 : 1 - ramp(shape.fill_g_cm3, cfg.fill_g_cm3);
    const small = 1 - ramp(minutes, cfg.small_minutes);
    const overhang = shape.overhang_share === null ? 0 : ramp(shape.overhang_share, cfg.overhang_share);
    const fine = shape.triangles == null || shape.triangles <= 0 || !cfg.triangles ? 0 : ramp(shape.triangles, cfg.triangles);
    const one = (c: RangeTerms): [number, number] => {
        const s = supports === true ? c.supports : supports === false ? 1 : c.auto + (c.supports - c.auto) * overhang;
        return [
            Math.round(c.low * (1 + (c.low_triangles ?? 0) * fine) * (1 - c.small * small) * 1e4) / 1e4,
            Math.round(c.high * (1 + c.height * height) * (1 + c.thin * thin) * s * 1e4) / 1e4,
        ];
    };
    return { time: one(cfg.time), price: one(cfg.price) };
}

/** [min, max] over the lists' totals; with the factors of an estimate the ends move out by them and land on the rounding step. */
export function range(roundTo: number, totals: number[], factors: [number, number] | null): [number, number] {
    if (!totals.length) return [0, 0];
    let min = Math.min(...totals);
    let max = Math.max(...totals);
    if (factors) {
        const step = Math.max(1, roundTo);
        min = Math.floor((min * factors[0]) / step) * step;
        max = Math.ceil((max * factors[1]) / step) * step;
    }
    return [min, max];
}
