<?php

namespace App\Domain\Farm;

use App\Domain\Calculation\MaterialCatalog;
use App\Domain\Calculation\PricingProfile;
use App\Models\FarmMaterial;
use App\Models\FarmPrinter;

/**
 * The farm's price list in the shape the calculator understands, so the calculator and the tool pages show the farm's
 * price (one number) instead of the printers' marketplace lists. Built from the farm settings and the kind really
 * loaded in a printer that matches the calculator's material – the cheapest loaded kind when none does, and the label
 * says so; the printer's time factor and VAT (as a margin on the sum) are included, so the number matches what /farm
 * charges to the crown for that kind.
 */
final class FarmPricingProfile
{
    public function __construct(private readonly FarmSettings $settings, private readonly OrderService $orders, private readonly MaterialCatalog $materials) {}

    /**
     * @param  string|null  $materialCode  the calculator's material (PLA, PETG, ASA, TPU…): priced in the farm's kind of that
     *                                     code when one is loaded (PLA counts the whole PLA family, PLA+ included), else in the
     *                                     cheapest loaded kind, which the label then names as such
     */
    public function profile(?string $materialCode = null): ?PricingProfile
    {
        $loaded = $this->orders->loadedMaterials();   // the cheapest first
        $wanted = $materialCode !== null && trim($materialCode) !== '' ? strtoupper(trim($materialCode)) : null;
        $material = ($wanted !== null ? $loaded->first(fn (FarmMaterial $m) => self::matches($m, $wanted)) : null) ?? $loaded->first();
        $printer = $material ? $this->orders->printerFor($material) : FarmPrinter::where('enabled', true)->first();
        if (! $material || ! $printer) {
            return null;
        }
        $exact = $wanted === null || self::matches($material, $wanted);
        // the slicer weighed the model in the material the customer chose; the farm charges the grams of the kind it really
        // prints with, so a lighter material priced in a heavier kind is scaled by the two densities (ASA at 1.07 g/cm³
        // printed in PLA+ at 1.24 weighs 16 % more – before this, ASA came out cheaper than PLA+, 18 Oct 2026)
        $ratio = $exact || (float) $material->density <= 0 ? 1.0 : (float) $material->density / max(0.5, $this->materials->density($wanted));

        return PricingProfile::fromArray([
            'key' => 'farm',
            'label' => __($exact ? 'farm.price_label' : 'farm.price_label_cheapest', ['material' => $material->label()]),
            'hourly_rate' => $printer->hourly_rate ?? (float) $this->settings->get('hourly_rate'),
            'price_per_gram' => (float) $material->price_per_gram * $printer->weight_factor * $ratio,
            'setup_fee' => (float) $this->settings->get('fixed_fee'),
            'margin_pct' => (float) $this->settings->get('vat_percent'),
            'min_price' => round((float) $this->settings->get('min_price') * (1 + (float) $this->settings->get('vat_percent') / 100)),
            'lead_time_days' => 2,
            'time_factor' => $printer->time_factor,
        ]);
    }

    /** Is this kind what the calculator's material means? The same code, or any kind of the PLA family for "PLA". */
    private static function matches(FarmMaterial $kind, string $wanted): bool
    {
        $code = strtoupper((string) $kind->code);

        return $code === $wanted || ($wanted === 'PLA' && str_starts_with($code, 'PLA'));
    }

    /** @return array<string,mixed>|null the same profile for the browser's instant estimate */
    public function toArray(?string $materialCode = null): ?array
    {
        $p = $this->profile($materialCode);

        return $p ? [
            'key' => $p->key, 'label' => $p->label, 'hourly_rate' => $p->hourlyRate, 'price_per_gram' => $p->pricePerGram, 'setup_fee' => $p->setupFee,
            'margin_pct' => $p->marginPct, 'min_price' => $p->minPrice, 'lead_time_days' => $p->leadTimeDays, 'time_factor' => $p->timeFactor,
        ] : null;
    }
}
