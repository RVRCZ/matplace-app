<?php

namespace App\Domain\Catalog;

use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\OrderService;
use App\Domain\Farm\PriceCalculator;
use App\Models\DesignerModel;
use App\Models\FarmMaterial;
use Illuminate\Support\Collection;

/**
 * "What will it cost?" on a model's page, before anything is ordered. The card was sliced once when its file was
 * uploaded (size, grams, minutes of one piece); the farm's price formula turns that into the price of N pieces in a
 * material, and the designer's reward is added the same way an order adds it (per piece, capped at 30 %).
 *
 * It is a quote: the order itself is sliced again with the colour, quality and machine the customer picks,
 * and that price is the one charged.
 */
final class ModelPricing
{
    public function __construct(private readonly PriceCalculator $prices, private readonly FarmSettings $settings, private readonly OrderService $orders) {}

    /**
     * Materials the farm can print the model in right now, the cheapest first; null when nothing is loaded.
     *
     * @return Collection<int, FarmMaterial>
     */
    public function materials(): Collection
    {
        return $this->orders->loadedMaterials();
    }

    /**
     * @return array{available: bool, material?: string, copies?: int, print?: float, royalty_unit?: float, royalty?: float, total?: float, unit?: float, currency?: string, minutes?: int, grams?: float}
     */
    public function quote(DesignerModel $card, int $copies = 1, ?string $materialCode = null, ?int $customerId = null): array
    {
        $summary = (array) $card->slice_summary;
        $materials = $this->materials();
        $material = $materials->firstWhere('code', $materialCode) ?? $materials->first();
        if (! $material || empty($summary['minutes']) || empty($summary['grams'])) {
            return ['available' => false];
        }
        $copies = max(1, min(64, $copies));
        $price = $this->prices->price((int) $summary['minutes'] * $copies, (float) $summary['grams'] * $copies, [
            'hourly_rate' => (float) $this->settings->get('hourly_rate'),
            'price_per_gram' => (float) $material->price_per_gram,
            'fixed_fee' => (float) $this->settings->get('fixed_fee'),
            'min_price' => (float) $this->settings->get('min_price'),
            'vat_percent' => (float) $this->settings->get('vat_percent'),
            'rounding' => (float) $this->settings->get('rounding'),
            'currency' => (string) $this->settings->get('currency'),
        ]);
        $unit = OrderService::royaltyPerPiece($card, $customerId, (float) $price['print_total'], $copies);
        $royalty = round($unit * $copies, 2);

        return [
            'available' => true,
            'material' => $material->code,
            'copies' => $copies,
            'print' => (float) $price['print_total'],
            'royalty_unit' => $unit,
            'royalty' => $royalty,
            'total' => round((float) $price['print_total'] + $royalty, 2),
            'unit' => round(((float) $price['print_total'] + $royalty) / $copies, 2),
            'currency' => (string) $price['currency'],
            'minutes' => (int) $summary['minutes'] * $copies,
            'grams' => round((float) $summary['grams'] * $copies, 1),
        ];
    }
}
