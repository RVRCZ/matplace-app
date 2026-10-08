<?php

namespace App\Domain\Sell;

/**
 * What a piece costs to print at home, as pure arithmetic the page mirrors in resources/js/site/sell.ts
 * (the tests count on this side). Inputs in one currency, whatever it is; the answer is in the same one.
 *
 * material = filament price per kg × grams / 1000; energy = watts × hours / 1000 × price per kWh;
 * wear = printer price / printer hours × hours; the three are spent on failed prints too, so they are divided by
 * (1 − scrap); labour and other costs are per good piece; price = cost × (1 + margin).
 */
final class Cost
{
    /** field → [min, max, default] (the default is the config's); the page keeps the same limits */
    public const FIELDS = [
        'filament_kg' => [0, 20000, 600], 'grams' => [0, 20000, 40], 'hours' => [0, 1000, 2.5], 'watts' => [0, 3000, 120], 'kwh' => [0, 100, 6.5],
        'printer_price' => [0, 1000000, 12000], 'printer_hours' => [1, 100000, 5000], 'scrap_pct' => [0, 90, 5], 'labour_rate' => [0, 100000, 250], 'labour_minutes' => [0, 10000, 10],
        'other' => [0, 100000, 0], 'margin_pct' => [0, 1000, 40],
    ];

    /** The inputs within their limits, missing ones at their defaults (from config/sell.php when it has them). */
    public static function clean(array $in): array
    {
        $out = [];
        foreach (self::FIELDS as $key => [$min, $max, $default]) {
            $v = isset($in[$key]) && is_numeric($in[$key]) ? (float) $in[$key] : (float) config('sell.cost.'.$key, $default);
            $out[$key] = max($min, min($max, $v));
        }

        return $out;
    }

    /**
     * @return array{material: float, energy: float, wear: float, scrap: float, labour: float, other: float, cost: float, price: float, margin: float, per_hour: float}
     */
    public static function calculate(array $in): array
    {
        $p = self::clean($in);
        $material = $p['filament_kg'] * $p['grams'] / 1000;
        $energy = $p['watts'] * $p['hours'] / 1000 * $p['kwh'];
        $wear = $p['printer_hours'] > 0 ? $p['printer_price'] / $p['printer_hours'] * $p['hours'] : 0.0;
        $machine = $material + $energy + $wear;
        $share = min(0.9, $p['scrap_pct'] / 100);
        $scrap = $share > 0 ? $machine / (1 - $share) - $machine : 0.0;     // what the failed prints add to every good piece
        $labour = $p['labour_rate'] * $p['labour_minutes'] / 60;
        $cost = $machine + $scrap + $labour + $p['other'];
        $margin = $cost * $p['margin_pct'] / 100;
        $price = $cost + $margin;

        return [
            'material' => round($material, 2), 'energy' => round($energy, 2), 'wear' => round($wear, 2), 'scrap' => round($scrap, 2), 'labour' => round($labour, 2), 'other' => round($p['other'], 2),
            'cost' => round($cost, 2), 'margin' => round($margin, 2), 'price' => round($price, 2),
            // what an hour of the printer earns at that price, the figure to compare printers and prices by
            'per_hour' => $p['hours'] > 0 ? round($margin / $p['hours'], 2) : 0.0,
        ];
    }
}
