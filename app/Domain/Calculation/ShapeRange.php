<?php

namespace App\Domain\Calculation;

/**
 * How far the slice may land from the estimate, by the shape of the model (TODO #41, docs/M.md §6).
 *
 * The estimate counts grams from volume and area and minutes from the grams. The slicer's time also depends on what
 * the grams cannot see: thin walls, lattices and long thin parts (little material in a big box = long paths), height
 * (many layers), supports, and overhangs the slicer bridges or holds up. Each of those raises the upper bound; a small
 * print lowers the lower bound (the fixed minutes of the estimate are too many for it). Calibrated on 400 calculations
 * against their slices (docs/data/odhad-vs-rez-2026-10-24.csv): 97 % of the prices and 96 % of the print times land
 * inside. The browser has the same formula in resources/js/calc/rough.ts (rangeFactors); keep both in sync
 * (tests/Unit/ShapeRangeTest, scripts/check_rough_range.mjs). `php artisan matplace:estimate-range` recounts the hits from a CSV.
 */
final class ShapeRange
{
    /** @param  array  $cfg  config('pricing.rough.range') */
    public function __construct(private readonly array $cfg) {}

    /**
     * @param  ?float  $heightMm  the model as it prints (scaled); null = unknown, counted as tall
     * @param  ?float  $fillGcm3  estimated grams per cm³ of the model's bounding box (scaled); null = unknown, counted as thin
     * @param  ?float  $overhangShare  share of the surface facing down steeper than 45° (the browser measures it); null = unknown
     * @param  ?bool  $supports  the customer's choice: on, off, null = the slicer decides
     * @param  int  $minutes  the estimated print time of one piece
     * @return array{time: array{float, float}, price: array{float, float}} [low, high] as factors of the estimate
     */
    public function factors(?float $heightMm, ?float $fillGcm3, ?float $overhangShare, ?bool $supports, int $minutes): array
    {
        $height = $heightMm === null ? 1.0 : self::ramp($heightMm, $this->cfg['height_mm']);
        $thin = $fillGcm3 === null ? 1.0 : 1 - self::ramp($fillGcm3, $this->cfg['fill_g_cm3']);
        $small = 1 - self::ramp((float) $minutes, $this->cfg['small_minutes']);
        $overhang = $overhangShare === null ? 0.0 : self::ramp($overhangShare, $this->cfg['overhang_share']);
        $out = [];
        foreach (['time', 'price'] as $k) {
            $c = $this->cfg[$k];
            // supports on: the factor measured on prints with supports; off: none; left to the slicer: the factor measured on
            // such prints, raised towards "on" the more of the surface hangs over (the farm may add supports after all)
            $s = $supports === true ? (float) $c['supports'] : ($supports === false ? 1.0 : (float) $c['auto'] + ((float) $c['supports'] - (float) $c['auto']) * $overhang);
            $out[$k] = [
                round((float) $c['low'] * (1 - (float) $c['small'] * $small), 4),
                round((float) $c['high'] * (1 + (float) $c['height'] * $height) * (1 + (float) $c['thin'] * $thin) * $s, 4),
            ];
        }

        return $out;
    }

    /** Grams per cm³ of the box the model fills, as it prints; null without a box. */
    public static function fill(float $grams, ?array $bbox, float $scale = 1.0): ?float
    {
        if (! $bbox) {
            return null;
        }
        $mm3 = (float) ($bbox['x'] ?? 0) * (float) ($bbox['y'] ?? 0) * (float) ($bbox['z'] ?? 0) * $scale ** 3;

        return $mm3 > 0 ? $grams / ($mm3 / 1000) : null;
    }

    /** 0 up to the first edge, 1 from the second, straight between. */
    private static function ramp(float $v, array $edges): float
    {
        [$a, $b] = [(float) $edges[0], (float) $edges[1]];
        if ($b <= $a) {
            return $v >= $b ? 1.0 : 0.0;
        }

        return max(0.0, min(1.0, ($v - $a) / ($b - $a)));
    }
}
