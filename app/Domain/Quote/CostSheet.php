<?php

namespace App\Domain\Quote;

/**
 * The printer's own sums behind a quote. Never shown to the customer.
 *
 *   production = material + machine hours × machine rate
 *   reserve    = production × failure % (failed prints are paid for by the ones that succeed)
 *   cost       = production + reserve + preparation + hand work
 *   price      = MARKUP (přirážka): cost × (1 + p)        p is a share of the COST
 *                MARGIN (marže):    cost ÷ (1 − p)        p is a share of the PRICE
 *   suggested  = max(minimum order price, price rounded up)
 *   final      = the printer's own number if they typed one, else suggested
 *
 * 25 % markup and 20 % margin are the same price; the sheet always reports both so nobody has to guess which one is meant.
 */
final class CostSheet
{
    public const MODE_MARKUP = 'markup';

    public const MODE_MARGIN = 'margin';

    /** @return array<string, float|int|string|null> cleaned inputs */
    public static function inputs(array $a): array
    {
        $n = fn (string $k, float $min, float $max, float $d = 0.0) => max($min, min($max, is_numeric($a[$k] ?? null) ? (float) $a[$k] : $d));
        $mode = ($a['mode'] ?? self::MODE_MARKUP) === self::MODE_MARGIN ? self::MODE_MARGIN : self::MODE_MARKUP;

        return [
            'quantity' => (int) $n('quantity', 1, 100000, 1),
            'material_cost' => round($n('material_cost', 0, 10000000), 2),
            'machine_hours' => round($n('machine_hours', 0, 100000), 2),
            'machine_rate' => round($n('machine_rate', 0, 100000), 2),
            'setup_cost' => round($n('setup_cost', 0, 10000000), 2),
            'labour_minutes' => round($n('labour_minutes', 0, 100000), 1),
            'labour_rate' => round($n('labour_rate', 0, 100000), 2),
            'failure_pct' => round($n('failure_pct', 0, 100), 1),
            'mode' => $mode,
            // a margin of 100 % would mean an infinite price
            'pct' => round($n('pct', 0, $mode === self::MODE_MARGIN ? 95 : 1000), 1),
            'min_price' => round($n('min_price', 0, 10000000), 2),
            'final_price' => isset($a['final_price']) && $a['final_price'] !== '' && is_numeric($a['final_price']) ? round(max(0, min(100000000, (float) $a['final_price'])), 2) : null,
        ];
    }

    /** @return array<string, mixed> inputs + every computed step */
    public static function compute(array $raw, int $roundTo = 1): array
    {
        $i = self::inputs($raw);
        $machine = $i['machine_hours'] * $i['machine_rate'];
        $production = $i['material_cost'] + $machine;
        $reserve = $production * $i['failure_pct'] / 100;
        $labour = $i['labour_minutes'] / 60 * $i['labour_rate'];
        $cost = $production + $reserve + $i['setup_cost'] + $labour;

        $p = $i['pct'] / 100;
        $price = $i['mode'] === self::MODE_MARGIN ? $cost / (1 - $p) : $cost * (1 + $p);
        $step = max(1, $roundTo);
        $rounded = ceil(round($price, 4) / $step) * $step;
        $suggested = max($i['min_price'], $rounded);
        $final = $i['final_price'] ?? $suggested;
        $profit = $final - $cost;

        return $i + [
            'machine_cost' => round($machine, 2),
            'reserve' => round($reserve, 2),
            'labour_cost' => round($labour, 2),
            'cost' => round($cost, 2),
            'price_before_minimum' => round($rounded, 2),
            'minimum_applied' => $i['min_price'] > $rounded,
            'suggested' => round($suggested, 2),
            'final' => round($final, 2),
            'overridden' => $i['final_price'] !== null && abs($i['final_price'] - $suggested) >= 0.005,
            'profit' => round($profit, 2),
            // the same result said both ways
            'markup_pct' => $cost > 0 ? round($profit / $cost * 100, 1) : null,
            'margin_pct' => $final > 0 ? round($profit / $final * 100, 1) : null,
            'unit_price' => round($final / $i['quantity'], 2),
        ];
    }
}
