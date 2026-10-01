<?php

namespace App\Engines\Shipping;

use App\Domain\Farm\Shipping;
use App\Models\FarmOrder;

/**
 * What the carrier needs to know about the parcel of one order: who gets it, where it goes, what it weighs.
 * Built from the order alone, so the fake and the real client announce exactly the same thing.
 */
final class Parcel
{
    public function __construct(
        public readonly string $number,
        public readonly string $name,
        public readonly string $surname,
        public readonly string $email,
        public readonly string $phone,
        public readonly int $addressId,              // a pickup point of Packeta's own network, or the id of a partner carrier
        public readonly ?string $carrierPickupPoint, // the partner carrier's code of its pickup point
        public readonly ?string $street,
        public readonly ?string $houseNumber,
        public readonly ?string $city,
        public readonly ?string $zip,
        public readonly float $weightKg,
        public readonly float $value,
        public readonly string $currency,
        public readonly string $country,
        public readonly bool $external,              // carried by a partner, not by Packeta itself
    ) {}

    /** @throws ShippingFailed when the order holds no usable destination */
    public static function forOrder(FarmOrder $order): self
    {
        $to = (array) $order->shipping_address;
        $country = strtoupper((string) ($to['country'] ?? 'CZ'));
        $home = $order->delivery === Shipping::HOME;
        $carrier = (int) ($to['carrier_id'] ?? 0);
        if ($home) {
            $address = $carrier;
            $point = null;
        } else {
            // a partner's point: the carrier is the address and the point travels beside it
            $address = $carrier ?: (int) ($to['pickup_point_id'] ?? 0);
            $point = $carrier ? (string) ($to['pickup_point_id'] ?? '') : null;
        }
        if ($address <= 0 || ($carrier && ! $home && $point === '')) {
            throw new ShippingFailed('The order has no pickup point or carrier to send the parcel to.');
        }
        [$name, $surname] = self::splitName((string) ($to['name'] ?? '') ?: (string) $order->user?->recipientName());
        [$street, $house] = $home ? self::splitStreet((string) ($to['street'] ?? '')) : [null, null];

        return new self(
            number: (string) ($order->number ?: $order->token),
            name: $name,
            surname: $surname,
            email: (string) $order->user?->email,
            phone: (string) ($to['phone'] ?? '') ?: (string) $order->user?->phone,
            addressId: $address,
            carrierPickupPoint: $point,
            street: $street,
            houseNumber: $house,
            city: $home ? (string) ($to['city'] ?? '') : null,
            zip: $home ? (string) ($to['zip'] ?? '') : null,
            weightKg: round(app(Shipping::class)->parcelGrams($order) / 1000, 3),
            // what the parcel is insured for: the print itself, without the delivery
            value: round(max(0.0, (float) $order->price_total - (float) $order->shipping_price), 2),
            currency: (string) ($order->currency ?: 'CZK'),
            country: $country,
            external: $home ? ! in_array($country, (array) config('farm.packeta_internal', ['CZ', 'SK']), true) : (bool) $carrier,
        );
    }

    /** "Jan Amos Komenský" → ["Jan Amos", "Komenský"]; one word serves as both (the carrier wants both filled). */
    public static function splitName(string $full): array
    {
        $words = preg_split('/\s+/u', trim($full), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) < 2) {
            return [$words[0] ?? '-', $words[0] ?? '-'];
        }
        $surname = array_pop($words);

        return [implode(' ', $words), $surname];
    }

    /** "Dlouhá 12/3a" → ["Dlouhá", "12/3a"]; "Calle Mayor, 5" → ["Calle Mayor", "5"]; a street without a number keeps "-" . */
    public static function splitStreet(string $line): array
    {
        $line = trim($line);
        if (preg_match('/^(.*?)[\s,]+(\d+[0-9a-zA-Z\/\-]*)$/u', $line, $m)) {
            return [trim($m[1], ' ,'), $m[2]];
        }
        // "12 Rue de la Paix": the number comes first
        if (preg_match('/^(\d+[0-9a-zA-Z\/\-]*)[\s,]+(.+)$/u', $line, $m)) {
            return [trim($m[2]), $m[1]];
        }

        return [$line, '-'];
    }
}
