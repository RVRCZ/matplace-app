<?php

namespace App\Domain\Calculation;

/**
 * grams + minutes + quantity + pricing profile → price with a breakdown.
 *
 *   unit      = grams × price_per_gram + hours × time_factor × hourly_rate (+ royalty per piece)
 *   subtotal  = unit × qty + setup_fee
 *   discount  = subtotal × qty discount %
 *   margin    = (subtotal − discount) × margin %
 *   total     = max(min_price, ceil((subtotal − discount + margin) / round_to) × round_to)
 *
 * Deterministic and pure: same inputs → same output. Manual overrides in the printer calculator
 * replace a single line (e.g. unitTime) and re-run this method, so the sum stays consistent.
 */
final class PriceEngine
{
    public function __construct(private readonly array $pricing) {}

    public function price(
        float $grams,
        int $minutes,
        int $quantity,
        PricingProfile $profile,
        float $royaltyPerPiece = 0.0,
        ?float $overrideUnitMaterial = null,
        ?float $overrideUnitTime = null,
        ?float $overrideSetup = null,
    ): PriceBreakdown {
        $quantity = max(1, $quantity);
        $unitMaterial = $overrideUnitMaterial ?? $grams * $profile->pricePerGram;
        $printMinutes = (int) round($minutes * $profile->timeFactor);   // the slicer measures a fast reference printer
        $unitTime = $overrideUnitTime ?? ($printMinutes / 60.0) * $profile->hourlyRate;
        $setup = $overrideSetup ?? $profile->setupFee;
        $unit = $unitMaterial + $unitTime + max(0.0, $royaltyPerPiece);

        $subtotal = $unit * $quantity + $setup;
        $discountPct = $profile->discountPctFor($quantity);
        $discount = $subtotal * $discountPct / 100.0;
        $margin = ($subtotal - $discount) * $profile->marginPct / 100.0;
        $raw = $subtotal - $discount + $margin;

        $step = max(1, (int) ($this->pricing['round_to'] ?? 1));
        $total = max($profile->minPrice, ceil($raw / $step) * $step);

        return new PriceBreakdown(
            profileKey: $profile->key,
            quantity: $quantity,
            unitMaterial: $unitMaterial,
            unitTime: $unitTime,
            unitRoyalty: max(0.0, $royaltyPerPiece),
            setup: $setup,
            subtotal: $subtotal,
            discountPct: $discountPct,
            discount: $discount,
            marginPct: $profile->marginPct,
            margin: $margin,
            total: (float) $total,
            leadTimeDays: $profile->leadTimeDays,
            printerProfileId: $profile->printerProfileId,
            label: $profile->label,
            minutes: $printMinutes,
        );
    }

    /** Platform orientation profiles from config (used until real printer profiles exist). */
    public function orientationProfiles(): array
    {
        return array_map(fn ($p) => PricingProfile::fromArray($p), $this->pricing['orientation_profiles'] ?? []);
    }

    /**
     * Prices for every given profile, cheapest first.
     *
     * @param  PricingProfile[]  $profiles
     * @return PriceBreakdown[]
     */
    public function priceAll(float $grams, int $minutes, int $quantity, array $profiles, float $royaltyPerPiece = 0.0): array
    {
        $out = array_map(fn (PricingProfile $p) => $this->price($grams, $minutes, $quantity, $p, $royaltyPerPiece), $profiles);
        usort($out, fn (PriceBreakdown $a, PriceBreakdown $b) => $a->total <=> $b->total);

        return $out;
    }

    /** [min, max] over a list of breakdowns; rough estimates widen it by the configured range. */
    public function range(array $breakdowns, bool $rough = false): array
    {
        if (! $breakdowns) {
            return [0.0, 0.0];
        }
        $totals = array_map(fn (PriceBreakdown $b) => $b->total, $breakdowns);
        $min = min($totals);
        $max = max($totals);
        if ($rough) {
            $step = max(1, (int) ($this->pricing['round_to'] ?? 1));
            $min = floor($min * (float) $this->pricing['rough']['range_low'] / $step) * $step;
            $max = ceil($max * (float) $this->pricing['rough']['range_high'] / $step) * $step;
        }

        return [(float) $min, (float) $max];
    }
}
