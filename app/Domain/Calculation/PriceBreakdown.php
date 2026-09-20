<?php

namespace App\Domain\Calculation;

/** Result of PriceEngine::price(). All amounts in CZK; unit_* are per piece, total is for the whole order. */
final class PriceBreakdown
{
    public function __construct(
        public readonly string $profileKey,
        public readonly int $quantity,
        public readonly float $unitMaterial,
        public readonly float $unitTime,
        public readonly float $unitRoyalty,
        public readonly float $setup,
        public readonly float $subtotal,
        public readonly float $discountPct,
        public readonly float $discount,
        public readonly float $marginPct,
        public readonly float $margin,
        public readonly float $total,
        public readonly int $leadTimeDays,
        public readonly ?int $printerProfileId = null,
        public readonly ?string $label = null,
        public readonly ?int $minutes = null, // print time per piece on this printer
    ) {}

    public function toArray(): array
    {
        return [
            'profile' => $this->profileKey,
            'label' => $this->label,
            'printer_profile_id' => $this->printerProfileId,
            'quantity' => $this->quantity,
            'unit' => [
                'material' => round($this->unitMaterial, 2),
                'time' => round($this->unitTime, 2),
                'royalty' => round($this->unitRoyalty, 2),
            ],
            'setup' => round($this->setup, 2),
            'subtotal' => round($this->subtotal, 2),
            'discount_pct' => $this->discountPct,
            'discount' => round($this->discount, 2),
            'margin_pct' => $this->marginPct,
            'margin' => round($this->margin, 2),
            'total' => $this->total,
            'lead_time_days' => $this->leadTimeDays,
            'minutes' => $this->minutes,
        ];
    }
}
