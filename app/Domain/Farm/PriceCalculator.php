<?php

namespace App\Domain\Farm;

/**
 * Farm price. Pure arithmetic, no database:
 *
 *   net = hours × time_factor × hourly_rate + grams × weight_factor × price_per_gram + fixed_fee
 *   net = max(net, min_price)          total = round_up(net × (1 + VAT), rounding) + shipping
 *
 * The returned breakdown carries every input, so a stored order explains its own price even after the rates change.
 */
final class PriceCalculator
{
    /**
     * @param  array{hourly_rate: float, price_per_gram: float, fixed_fee: float, min_price: float, vat_percent: float, rounding: float, time_factor?: float, weight_factor?: float, shipping?: float, currency?: string}  $rates
     * @return array<string,mixed>
     */
    public function price(int $minutes, float $grams, array $rates): array
    {
        $timeFactor = (float) ($rates['time_factor'] ?? 1.0);
        $weightFactor = (float) ($rates['weight_factor'] ?? 1.0);
        $hours = max(0, $minutes) / 60 * $timeFactor;
        $billedGrams = max(0.0, $grams) * $weightFactor;

        $time = $hours * (float) $rates['hourly_rate'];
        $material = $billedGrams * (float) $rates['price_per_gram'];
        $fixed = (float) $rates['fixed_fee'];
        $sum = $time + $material + $fixed;

        $min = (float) $rates['min_price'];
        $net = max($sum, $min);
        $vatPercent = max(0.0, (float) $rates['vat_percent']);
        $gross = $net * (1 + $vatPercent / 100);
        $rounded = self::roundUp($gross, (float) $rates['rounding']);
        $shipping = max(0.0, (float) ($rates['shipping'] ?? 0));

        return [
            'currency' => $rates['currency'] ?? 'CZK',
            'inputs' => [
                'minutes' => $minutes, 'grams' => round($grams, 1), 'time_factor' => $timeFactor, 'weight_factor' => $weightFactor,
                'hourly_rate' => (float) $rates['hourly_rate'], 'price_per_gram' => (float) $rates['price_per_gram'],
                'fixed_fee' => $fixed, 'min_price' => $min, 'vat_percent' => $vatPercent, 'rounding' => (float) $rates['rounding'],
            ],
            'billed_hours' => round($hours, 3),
            'billed_grams' => round($billedGrams, 1),
            'time' => round($time, 2),
            'material' => round($material, 2),
            'fixed' => round($fixed, 2),
            'min_price_applied' => $sum < $min,
            'net' => round($net, 2),
            'vat' => round($rounded - $net, 2),          // after rounding, so net + vat = print price exactly
            'print_total' => $rounded,
            'rounding_added' => round($rounded - $gross, 2),
            'shipping' => round($shipping, 2),
            'total' => round($rounded + $shipping, 2),
        ];
    }

    /** Round up to a multiple of $step (1 = whole crowns, 5, 10…); 0 keeps two decimals. */
    public static function roundUp(float $value, float $step): float
    {
        if ($step <= 0) {
            return round($value, 2);
        }

        // the epsilon keeps 120.00000001 from becoming 121
        return round(ceil(round($value / $step, 6)) * $step, 2);
    }
}
