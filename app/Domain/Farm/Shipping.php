<?php

namespace App\Domain\Farm;

use App\Models\FarmOrder;
use App\Support\Countries;
use App\Support\Money;

/**
 * How a finished print gets to the customer and what that costs. A parcel goes by Packeta to a pickup point or
 * to the door, inside the EU only; the prices are the table in config/farm.php (`shipping`): zone × kind × weight
 * band, in both currencies. Pickup in person is free, and offered only while the admin has it switched on
 * (`delivery_modes`): the farm needs a place to hand prints over for that.
 */
final class Shipping
{
    public const PICKUP = 'pickup';

    public const POINT = 'packeta_point';

    public const HOME = 'packeta_home';

    /** delivery → the column of the price table */
    public const KINDS = [self::POINT => 'point', self::HOME => 'home'];

    public function __construct(private readonly CarrierBook $book, private readonly FarmSettings $settings) {}

    /** @return list<string> the kinds of delivery the farm offers right now (admin's setting) */
    public function modes(): array
    {
        return array_values(array_intersect([self::POINT, self::HOME, self::PICKUP], (array) $this->settings->get('delivery_modes'))) ?: [self::POINT, self::HOME];
    }

    /** The customer can come for the print. */
    public function pickup(): bool
    {
        return in_array(self::PICKUP, $this->modes(), true);
    }

    public function zoneOf(?string $country): ?string
    {
        $country = strtoupper((string) $country);
        foreach ((array) config('farm.shipping.zones') as $zone => $def) {
            if (in_array($country, (array) $def['countries'], true)) {
                return (string) $zone;
            }
        }

        return null;
    }

    /** What the parcel weighs: the whole print (every piece) plus the box, rounded up to the step. */
    public function parcelGrams(FarmOrder $order): int
    {
        $step = max(1, (int) config('farm.shipping.weight_step_g', 50));
        $grams = (float) ($order->actual_grams ?: $order->est_grams) + (float) config('farm.shipping.packaging_g', 60);

        return (int) (ceil($grams / $step) * $step);
    }

    /** One piece is longer than a parcel may be: it cannot be sent (only picked up in person, when that is offered). */
    public function tooBig(FarmOrder $order): bool
    {
        $dims = (array) ($order->check['piece_dims'] ?? $order->check['dims'] ?? []);
        $sides = array_filter([(float) ($dims['x'] ?? 0), (float) ($dims['y'] ?? 0), (float) ($dims['z'] ?? 0)]);
        if (! $sides) {
            return false;
        }

        return max($sides) > (float) config('farm.shipping.limits.longest_mm', 700) || array_sum($sides) > (float) config('farm.shipping.limits.sum_mm', 1200);
    }

    /**
     * The price of delivery with VAT; null = this kind is not offered for that country or weight.
     * Pickup in person costs nothing (whether it is offered at all is modes()).
     */
    public function price(string $delivery, ?string $country, int $grams, string $currency): ?Money
    {
        if ($delivery === self::PICKUP) {
            return new Money(0, $currency);
        }
        $kind = self::KINDS[$delivery] ?? null;
        $country = strtoupper((string) $country);
        $zone = $this->zoneOf($country);
        if ($kind === null || $zone === null) {
            return null;
        }
        $only = config('farm.shipping.only')[$country] ?? null;
        $base = config("farm.shipping.zones.{$zone}.{$kind}.{$currency}");
        if (($only !== null && $only !== $kind) || $base === null) {
            return null;
        }
        // somebody has to carry it there
        if ($kind === 'home' ? $this->book->home($country) === null : ! $this->book->hasPoints($country)) {
            return null;
        }
        foreach ((array) config('farm.shipping.bands') as $band) {
            if ($grams <= (int) $band['up_to_g'] && (! isset($band['zones']) || in_array($zone, (array) $band['zones'], true))) {
                return new Money((float) $base + (float) ($band[$currency] ?? 0), $currency);
            }
        }

        return null;   // heavier than the last band this zone may use
    }

    /**
     * Everything the order page needs to offer delivery: per country its name, the price of each kind (null = not
     * offered) and what the pickup point picker may show there.
     *
     * @return array{weight_g: int, too_big: bool, modes: list<string>, countries: array<string, array{name: string, point: ?float, home: ?float, vendors: list<array<string, string>>}>}
     */
    public function offer(FarmOrder $order, string $currency): array
    {
        $grams = $this->parcelGrams($order);
        $tooBig = $this->tooBig($order);
        $modes = $this->modes();
        $countries = [];
        foreach (Countries::names($this->countries()) as $code => $name) {
            $point = $tooBig || ! in_array(self::POINT, $modes, true) ? null : $this->price(self::POINT, $code, $grams, $currency)?->amount;
            $home = $tooBig || ! in_array(self::HOME, $modes, true) ? null : $this->price(self::HOME, $code, $grams, $currency)?->amount;
            if ($point === null && $home === null) {
                continue;
            }
            $countries[$code] = ['name' => $name, 'point' => $point, 'home' => $home, 'vendors' => $point !== null ? $this->vendors($code) : []];
        }

        return ['weight_g' => $grams, 'too_big' => $tooBig, 'modes' => $modes, 'countries' => $countries];
    }

    /** @return list<string> every country of the price table */
    public function countries(): array
    {
        return array_values(array_unique(array_merge(...array_values(array_map(fn ($z) => (array) $z['countries'], (array) config('farm.shipping.zones'))))));
    }

    /**
     * What the picker shows in a country: Packeta's own points and boxes, or the points of the partner carrier.
     *
     * @return list<array<string, string>>
     */
    public function vendors(string $country): array
    {
        if ($this->book->internal($country)) {
            return [['country' => strtolower($country)], ['country' => strtolower($country), 'group' => 'zbox']];
        }

        return array_map(fn (int $id) => ['carrierId' => (string) $id], $this->book->points($country));
    }

    /** VAT of the print by where it goes: the destination's rate once it is written in config/farm.php, else the farm's own. */
    public function vatPercent(?string $country): float
    {
        $country = strtoupper((string) ($country ?: 'CZ'));
        $rates = (array) config('farm.vat_rate');
        $rate = $rates[$country] ?? $rates[(string) $this->zoneOf($country)] ?? null;

        return $rate !== null ? (float) $rate : (float) $this->settings->get('vat_percent');
    }

    /**
     * The destination as the order keeps it, checked: a country we send to, a pickup point of that country, a carrier
     * that goes there. Returns the address to store.
     *
     * @param  array<string, mixed>  $input  the customer's form: name, phone, country, street, city, zip, point{id, name, country, carrier_id}
     * @return array<string, mixed>|null null for pickup in person
     *
     * @throws FarmRefusal delivery | delivery_too_big | delivery_country | delivery_point | delivery_address
     */
    public function destination(string $delivery, array $input, FarmOrder $order, string $currency): ?array
    {
        if (! in_array($delivery, $this->modes(), true)) {
            throw new FarmRefusal('delivery');
        }
        if ($delivery === self::PICKUP) {
            return null;
        }
        if ($this->tooBig($order)) {
            throw new FarmRefusal('delivery_too_big');
        }
        $country = strtoupper(trim((string) ($input['country'] ?? '')));
        if ($this->price($delivery, $country, $this->parcelGrams($order), $currency) === null) {
            throw new FarmRefusal('delivery_country');
        }
        $name = trim((string) ($input['name'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        if ($name === '') {
            throw new FarmRefusal('delivery_address');
        }

        if ($delivery === self::HOME) {
            $address = array_map(fn ($k) => trim((string) ($input[$k] ?? '')), ['street' => 'street', 'city' => 'city', 'zip' => 'zip']);
            // the courier calls before coming: no phone, no delivery
            if (in_array('', $address, true) || $phone === '') {
                throw new FarmRefusal('delivery_address');
            }

            return ['name' => $name, 'phone' => $phone, 'country' => $country] + $address + ['carrier_id' => $this->book->home($country)];
        }

        $point = (array) ($input['point'] ?? []);
        $id = trim((string) ($point['id'] ?? ''));
        $carrier = (int) ($point['carrier_id'] ?? 0);
        // the point has to lie in the country the price was quoted for, and belong to a carrier that goes there
        if ($id === '' || strtoupper((string) ($point['country'] ?? '')) !== $country
            || ($carrier ? ! $this->book->carries($carrier, $country, 'point') : ! $this->book->internal($country))) {
            throw new FarmRefusal('delivery_point');
        }

        return ['name' => $name, 'phone' => $phone, 'country' => $country, 'pickup_point_id' => $id,
            'pickup_point_name' => mb_substr(trim((string) ($point['name'] ?? '')), 0, 200) ?: $id, 'carrier_id' => $carrier ?: null];
    }
}
