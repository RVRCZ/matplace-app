<?php

namespace App\Domain\Calculation;

/**
 * One printer's (or the platform's orientation) price list. Money in CZK.
 * The "five fields" from the vision: hourly rate, price per gram, setup fee, margin, lead time.
 * "More": express surcharge, quantity discounts, finishing (added later as extra line items).
 */
final class PricingProfile
{
    /** @param  array<int, array{from:int, pct:float}>  $qtyDiscounts */
    public function __construct(
        public readonly string $key,
        public readonly float $hourlyRate,
        public readonly float $pricePerGram,
        public readonly float $setupFee = 0.0,
        public readonly float $marginPct = 0.0,
        public readonly float $minPrice = 0.0,
        public readonly int $leadTimeDays = 5,
        public readonly float $expressPct = 0.0,
        public readonly array $qtyDiscounts = [],
        public readonly ?int $printerProfileId = null,
        public readonly ?string $label = null,
    ) {}

    public static function fromArray(array $a): self
    {
        return new self(
            key: (string) ($a['key'] ?? 'custom'),
            hourlyRate: (float) ($a['hourly_rate'] ?? 0),
            pricePerGram: (float) ($a['price_per_gram'] ?? 0),
            setupFee: (float) ($a['setup_fee'] ?? 0),
            marginPct: (float) ($a['margin_pct'] ?? 0),
            minPrice: (float) ($a['min_price'] ?? 0),
            leadTimeDays: (int) ($a['lead_time_days'] ?? 5),
            expressPct: (float) ($a['express_pct'] ?? 0),
            qtyDiscounts: array_values((array) ($a['qty_discounts'] ?? [])),
            printerProfileId: isset($a['printer_profile_id']) ? (int) $a['printer_profile_id'] : null,
            label: $a['label'] ?? null,
        );
    }

    public function discountPctFor(int $quantity): float
    {
        $pct = 0.0;
        foreach ($this->qtyDiscounts as $d) {
            if ($quantity >= (int) ($d['from'] ?? PHP_INT_MAX)) {
                $pct = max($pct, (float) ($d['pct'] ?? 0));
            }
        }

        return $pct;
    }
}
