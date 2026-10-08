<?php

namespace App\Domain\Sell;

/**
 * What is left of a sale on a platform: the price less the platform's cut, the payment, the shipping you pay, VAT
 * when you are registered, and the cost of the piece. Pure arithmetic mirrored in resources/js/site/sell.ts.
 * Fees come from config/sell.php (`platforms`), amounts in foreign currencies at config/sell.php `rates`. Everything
 * is counted in crowns; the page converts to and from what the visitor types.
 */
final class Profit
{
    public const FIELDS = [
        'price' => [0, 10000000, 350], 'cost' => [0, 10000000, 120], 'ship_charged' => [0, 100000, 89], 'ship_actual' => [0, 100000, 89], 'discount_pct' => [0, 100, 0],
        'monthly_pieces' => [1, 100000, 20], 'fixed_monthly' => [0, 10000000, 0], 'stall_fee' => [0, 1000000, 1500], 'stall_pieces' => [1, 10000, 15],
    ];

    public static function platforms(): array
    {
        return array_keys((array) config('sell.platforms'));
    }

    /** An amount quoted in some currency, in crowns. */
    public static function czk(?array $money): float
    {
        if (! $money) {
            return 0.0;
        }
        $rate = $money['currency'] === 'CZK' ? 1.0 : (float) config('sell.rates.'.$money['currency'], 1.0);

        return (float) $money['amount'] * $rate;
    }

    public static function clean(array $in): array
    {
        $out = ['platform' => in_array($in['platform'] ?? '', self::platforms(), true) ? $in['platform'] : 'etsy', 'vat' => filter_var($in['vat'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'foreign' => filter_var($in['foreign'] ?? false, FILTER_VALIDATE_BOOLEAN)];
        foreach (self::FIELDS as $key => [$min, $max, $default]) {
            $v = isset($in[$key]) && is_numeric($in[$key]) ? (float) $in[$key] : (float) $default;
            $out[$key] = max($min, min($max, $v));
        }

        return $out;
    }

    /**
     * @return array{platform: string, revenue: float, discount: float, vat: float, fees: array<string, float>, fees_total: float, shipping_loss: float, net: float, profit: float, margin_pct: float, markup_pct: float, break_even: int|null, as_of: string}
     */
    public static function calculate(array $in): array
    {
        $p = self::clean($in);
        $f = (array) config('sell.platforms.'.$p['platform']);
        $discount = $p['price'] * $p['discount_pct'] / 100;
        $paid = $p['price'] - $discount;                           // what the buyer pays for the goods
        $gross = $paid + $p['ship_charged'];                        // … with the shipping charged
        $vatPct = (float) config('sell.vat_pct', 21);
        $vat = $p['vat'] ? $gross - $gross / (1 + $vatPct / 100) : 0.0;
        $fees = [
            'listing' => round(self::czk($f['listing'] ?? null), 2),
            'transaction' => round($gross * (float) ($f['transaction_pct'] ?? 0) / 100, 2),
            'payment' => round($gross * (float) ($f['payment_pct'] ?? 0) / 100 + self::czk($f['payment_fixed'] ?? null), 2),
            'currency' => round($p['foreign'] ? $gross * (float) ($f['currency_pct'] ?? 0) / 100 : 0.0, 2),
            'monthly' => round(self::czk($f['monthly'] ?? null) / max(1.0, $p['monthly_pieces']), 2),
            'stall' => round(! empty($f['stall']) ? $p['stall_fee'] / max(1.0, $p['stall_pieces']) : 0.0, 2),
        ];
        $feesTotal = array_sum($fees);
        $shippingLoss = $p['ship_actual'] - $p['ship_charged'];
        $net = $gross - $vat - $feesTotal - $p['ship_actual'];      // what reaches your account after the parcel is paid
        $profit = $net - $p['cost'];
        $breakEven = $p['fixed_monthly'] > 0 ? ($profit > 0.004 ? (int) ceil($p['fixed_monthly'] / $profit) : null) : 0;

        return [
            'platform' => $p['platform'], 'revenue' => round($gross, 2), 'discount' => round($discount, 2), 'vat' => round($vat, 2), 'fees' => $fees, 'fees_total' => round($feesTotal, 2),
            'shipping_loss' => round($shippingLoss, 2), 'net' => round($net, 2), 'profit' => round($profit, 2),
            'margin_pct' => $paid > 0 ? round($profit / $paid * 100, 1) : 0.0, 'markup_pct' => $p['cost'] > 0 ? round($profit / $p['cost'] * 100, 1) : 0.0,
            'break_even' => $breakEven, 'as_of' => (string) ($f['as_of'] ?? ''),
        ];
    }
}
