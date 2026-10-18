<?php

namespace App\Domain\Catalog;

use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\OrderService;
use App\Domain\Farm\PriceCalculator;
use App\Models\CatalogModel;
use App\Models\DesignerModel;
use App\Models\FarmMaterial;
use App\Support\Currency;
use App\Support\Money;
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
    /** @var Collection<int, FarmMaterial>|null what is loaded, read once per request (a sitemap asks for thousands of quotes) */
    private ?Collection $loaded = null;

    public function __construct(private readonly PriceCalculator $prices, private readonly FarmSettings $settings, private readonly OrderService $orders) {}

    /**
     * Materials the farm can print the model in right now, the cheapest first; empty when nothing is loaded.
     *
     * @return Collection<int, FarmMaterial>
     */
    public function materials(): Collection
    {
        return $this->loaded ??= $this->orders->loadedMaterials();
    }

    /** Is any kind loaded at all, so that a price can be named? */
    public function anyMaterial(): bool
    {
        return $this->materials()->isNotEmpty();
    }

    /**
     * The price of one piece of an inspiration model, as far as it can be named: from its author's printable card
     * (sliced here) when there is one, else from the grams and minutes the old site's slicer left on the row, in the
     * cheapest kind loaded. `available` false when neither exists – the page then names no price and the model is not
     * one of those worth indexing (config/catalog.php `index_requires`).
     *
     * @return array{available: bool, basis?: string, material?: string, total?: float, total_czk?: float, currency?: string, total_text?: string, minutes?: int, grams?: float}
     */
    public function quoteCatalog(CatalogModel $model, ?DesignerModel $card = null, ?string $currency = null): array
    {
        $card ??= $model->printableCard();
        if ($card && ! empty($card->slice_summary['minutes']) && ! empty($card->slice_summary['grams'])) {
            $quote = $this->quote($card, 1, null, null, $currency);
            if ($quote['available']) {
                return $quote + ['basis' => 'card', 'total_czk' => Money::of((float) $quote['total'], (string) $quote['currency'])->to(Money::CZK)->amount];
            }
        }
        $material = $this->materials()->first();
        if (! $material || (int) $model->est_minutes <= 0 || (float) $model->est_grams <= 0) {
            return ['available' => false];
        }
        $currency ??= Currency::current();
        $price = $this->prices->price((int) $model->est_minutes, (float) $model->est_grams, [
            'hourly_rate' => (float) $this->settings->get('hourly_rate'),
            'price_per_gram' => (float) $material->price_per_gram,
            'fixed_fee' => (float) $this->settings->get('fixed_fee'),
            'min_price' => (float) $this->settings->get('min_price'),
            'vat_percent' => (float) $this->settings->get('vat_percent'),
            'rounding' => (float) $this->settings->get('rounding'),
            'currency' => Money::CZK,
        ]);
        $total = Money::czk((float) $price['print_total'])->to($currency);

        return [
            'available' => true, 'basis' => 'estimate', 'material' => $material->code,
            'total' => $total->amount, 'total_czk' => (float) $price['print_total'], 'currency' => $currency, 'total_text' => $total->format(),
            'minutes' => (int) $model->est_minutes, 'grams' => (float) $model->est_grams,
        ];
    }

    /**
     * The amounts are in the currency the visitor sees prices in (print and reward converted the way an order
     * converts them); `royalty_unit` stays in crowns, as the designer set it.
     *
     * @return array{available: bool, material?: string, copies?: int, print?: float, royalty_unit?: float, royalty?: float, total?: float, unit?: float, currency?: string, total_text?: string, royalty_text?: string, minutes?: int, grams?: float}
     */
    public function quote(DesignerModel $card, int $copies = 1, ?string $materialCode = null, ?int $customerId = null, ?string $currency = null): array
    {
        $currency ??= Currency::current();
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
            'currency' => Money::CZK,
        ]);
        $unit = OrderService::royaltyPerPiece($card, $customerId, (float) $price['print_total'], $copies);
        $print = Money::czk((float) $price['print_total'])->to($currency);
        $royalty = Money::czk($unit * $copies)->to($currency);
        $total = $print->plus($royalty);

        return [
            'available' => true,
            'material' => $material->code,
            'copies' => $copies,
            'print' => $print->amount,
            'royalty_unit' => $unit,
            'royalty' => $royalty->amount,
            'total' => $total->amount,
            'unit' => round($total->amount / $copies, 2),
            'currency' => $currency,
            'total_text' => $total->format(),
            'royalty_text' => $royalty->format(),
            'minutes' => (int) $summary['minutes'] * $copies,
            'grams' => round((float) $summary['grams'] * $copies, 1),
        ];
    }
}
