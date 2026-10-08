<?php

namespace App\Domain\Sell;

/**
 * A year of selling: products (cost, price, pieces a month), fixed costs a month, a seasonality (a multiplier per
 * month) → revenue, costs and profit month by month, the cumulative profit and the month it turns positive. And the
 * steps still to take, read off what the plan is missing. Pure arithmetic mirrored in resources/js/site/sell.ts.
 */
final class Plan
{
    public const MAX_PRODUCTS = 20;

    /** The plan as the page sends it, only what the maths knows and within limits. */
    public static function clean(array $in): array
    {
        $products = [];
        foreach (array_slice(array_values((array) ($in['products'] ?? [])), 0, self::MAX_PRODUCTS) as $p) {
            if (! is_array($p)) {
                continue;
            }
            $n = fn (string $k, float $lo, float $hi): float => max($lo, min($hi, is_numeric($p[$k] ?? null) ? (float) $p[$k] : 0.0));
            $products[] = ['name' => mb_substr(trim((string) ($p['name'] ?? '')), 0, 60), 'cost' => $n('cost', 0, 10000000), 'price' => $n('price', 0, 10000000), 'qty' => $n('qty', 0, 1000000),
                'photo' => filter_var($p['photo'] ?? false, FILTER_VALIDATE_BOOLEAN), 'listed' => filter_var($p['listed'] ?? false, FILTER_VALIDATE_BOOLEAN)];
        }
        $seasons = (array) config('sell.seasons');
        $season = $in['season'] ?? 'flat';
        $months = is_array($in['months'] ?? null) ? array_values($in['months']) : null;
        if ($months === null || count($months) !== 12) {
            $months = $seasons[$season] ?? $seasons['flat'];
            $season = isset($seasons[$season]) ? $season : 'flat';
        } else {
            $months = array_map(fn ($m) => max(0.0, min(10.0, is_numeric($m) ? (float) $m : 1.0)), $months);
            $season = 'custom';
        }

        return [
            'name' => mb_substr(trim((string) ($in['name'] ?? '')), 0, 80),
            'products' => $products,
            'fixed' => max(0.0, min(100000000.0, is_numeric($in['fixed'] ?? null) ? (float) $in['fixed'] : 0.0)),
            'season' => $season, 'months' => $months,
            'start' => max(1, min(12, (int) ($in['start'] ?? (int) date('n')))),
            'channel' => mb_substr(trim((string) ($in['channel'] ?? '')), 0, 40),
            'done' => array_values(array_filter((array) ($in['done'] ?? []), 'is_string')),
        ];
    }

    /**
     * @return array{months: list<array{month: int, revenue: float, variable: float, fixed: float, profit: float, cumulative: float}>, revenue: float, profit: float, break_even_month: int|null, pieces: float, steps: list<array{key: string, product: string|null, tool: string|null, done: bool}>}
     */
    public static function calculate(array $in): array
    {
        $p = self::clean($in);
        $rows = [];
        $cum = 0.0;
        $breakEven = null;
        $revenueTotal = $profitTotal = $pieces = 0.0;
        for ($i = 0; $i < 12; $i++) {
            $month = ($p['start'] - 1 + $i) % 12 + 1;
            $k = $p['months'][$month - 1];
            $revenue = $variable = 0.0;
            foreach ($p['products'] as $prod) {
                $qty = $prod['qty'] * $k;
                $revenue += $prod['price'] * $qty;
                $variable += $prod['cost'] * $qty;
                $pieces += $qty;
            }
            $profit = $revenue - $variable - $p['fixed'];
            $cum += $profit;
            if ($breakEven === null && $cum > 0.004 && $i > 0 || ($breakEven === null && $i === 0 && $cum > 0.004)) {
                $breakEven = $i + 1;
            }
            $revenueTotal += $revenue;
            $profitTotal += $profit;
            $rows[] = ['month' => $month, 'revenue' => round($revenue, 2), 'variable' => round($variable, 2), 'fixed' => round($p['fixed'], 2), 'profit' => round($profit, 2), 'cumulative' => round($cum, 2)];
        }

        return ['months' => $rows, 'revenue' => round($revenueTotal, 2), 'profit' => round($profitTotal, 2), 'break_even_month' => $breakEven, 'pieces' => round($pieces, 1), 'steps' => self::steps($p)];
    }

    /**
     * What is still to do this week, read off the plan: a product without a cost or a price, without a photo, not
     * listed yet, no channel chosen, a price under the cost. Keys are texts in lang/<locale>/sell.php (`plan.step.*`).
     *
     * @return list<array{key: string, product: string|null, tool: string|null, done: bool}>
     */
    public static function steps(array $clean): array
    {
        $steps = [];
        $done = $clean['done'];
        $add = function (string $key, ?string $product = null, ?string $tool = null) use (&$steps, $done): void {
            $id = $key.($product !== null ? ':'.$product : '');
            $steps[] = ['key' => $key, 'id' => $id, 'product' => $product, 'tool' => $tool, 'done' => in_array($id, $done, true)];
        };
        if (! $clean['products']) {
            $add('add_product');
        }
        foreach ($clean['products'] as $i => $prod) {
            $name = $prod['name'] !== '' ? $prod['name'] : '#'.($i + 1);
            if ($prod['cost'] <= 0) {
                $add('cost', $name, 'cost');
            }
            if ($prod['price'] <= 0) {
                $add('price', $name, 'profit');
            } elseif ($prod['cost'] > 0 && $prod['price'] < $prod['cost']) {
                $add('price_below_cost', $name, 'profit');
            }
            if (! $prod['photo']) {
                $add('photo', $name);
            }
            if (! $prod['listed']) {
                $add('list', $name);
            }
        }
        if ($clean['channel'] === '') {
            $add('channel', null, 'vendors');
        }
        if ($clean['fixed'] <= 0) {
            $add('fixed');
        }

        return $steps;
    }
}
