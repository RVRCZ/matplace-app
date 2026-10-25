<?php

namespace App\Support;

use App\Domain\Farm\FarmGate;
use App\Domain\Farm\Shipping;
use App\Models\User;

/**
 * One line under the calculator's price to orient by (sales path, docs/AN.md §3): what delivery adds, from the farm's
 * price table (config/farm.php `shipping`) in the visitor's currency, for the country the visitor most likely orders
 * to: the account's country, else crowns = Czechia, Spanish = Spain, else the cheapest zone abroad. "From": an estimate,
 * the exact delivery is chosen on the order page. Nothing while the farm is not offered to this visitor.
 */
final class ShippingHint
{
    public static function text(): ?string
    {
        $user = auth()->user() instanceof User ? auth()->user() : null;
        if (! FarmGate::offered($user)) {
            return null;
        }
        $currency = Currency::current();
        $zones = (array) config('farm.shipping.zones', []);
        $shipping = app(Shipping::class);
        $zone = $shipping->zoneOf($user?->country)
            ?? ($currency === Money::CZK ? 'CZ' : (app()->getLocale() === 'es' ? $shipping->zoneOf('ES') : null));
        $candidates = $zone !== null && isset($zones[$zone]) ? [$zones[$zone]] : array_values(array_filter($zones, fn ($def, $key) => $key !== 'CZ', ARRAY_FILTER_USE_BOTH));
        $from = null;
        foreach ($candidates as $def) {
            foreach (['point', 'home'] as $kind) {
                if (isset($def[$kind][$currency]) && ($from === null || (float) $def[$kind][$currency] < $from)) {
                    $from = (float) $def[$kind][$currency];
                }
            }
        }
        if ($from === null) {
            return null;
        }

        return __($shipping->pickup() ? 'calc.price.shipping_pickup' : 'calc.price.shipping', ['from' => Money::of($from, $currency)->format()]);
    }
}
