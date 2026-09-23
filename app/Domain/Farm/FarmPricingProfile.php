<?php

namespace App\Domain\Farm;

use App\Domain\Calculation\PricingProfile;
use App\Models\FarmPrinter;

/**
 * The farm's price list in the shape the calculator understands, so the calculator and the tool pages show the farm's
 * price (one number) instead of the printers' marketplace lists. Built from the farm settings and the cheapest material
 * kind that is really loaded in a printer; the printer's time factor and VAT (as a margin on the sum) are included, so
 * the number matches what /farm charges to the crown for that kind.
 */
final class FarmPricingProfile
{
    public function __construct(private readonly FarmSettings $settings, private readonly OrderService $orders) {}

    public function profile(): ?PricingProfile
    {
        $material = $this->orders->loadedMaterials()->first();
        $printer = $material ? $this->orders->printerFor($material) : FarmPrinter::where('enabled', true)->first();
        if (! $material || ! $printer) {
            return null;
        }

        return PricingProfile::fromArray([
            'key' => 'farm',
            'label' => __('farm.price_label', ['material' => $material->label()]),
            'hourly_rate' => $printer->hourly_rate ?? (float) $this->settings->get('hourly_rate'),
            'price_per_gram' => (float) $material->price_per_gram * $printer->weight_factor,
            'setup_fee' => (float) $this->settings->get('fixed_fee'),
            'margin_pct' => (float) $this->settings->get('vat_percent'),
            'min_price' => round((float) $this->settings->get('min_price') * (1 + (float) $this->settings->get('vat_percent') / 100)),
            'lead_time_days' => 2,
            'time_factor' => $printer->time_factor,
        ]);
    }

    /** @return array<string,mixed>|null the same profile for the browser's instant estimate */
    public function toArray(): ?array
    {
        $p = $this->profile();

        return $p ? [
            'key' => $p->key, 'label' => $p->label, 'hourly_rate' => $p->hourlyRate, 'price_per_gram' => $p->pricePerGram, 'setup_fee' => $p->setupFee,
            'margin_pct' => $p->marginPct, 'min_price' => $p->minPrice, 'lead_time_days' => $p->leadTimeDays, 'time_factor' => $p->timeFactor,
        ] : null;
    }
}
