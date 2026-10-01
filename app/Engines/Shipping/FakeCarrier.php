<?php

namespace App\Engines\Shipping;

use App\Models\FarmOrder;

/**
 * Tests and local development without a Packeta account. It remembers every parcel it was asked for, can be told
 * to refuse the next one, and knows a handful of carriers so that delivery abroad can be tried end to end.
 */
final class FakeCarrier implements ShippingCarrier
{
    /** @var list<Parcel> */
    public static array $parcels = [];

    /** Set to a text: the next createPacket fails with it (as Packeta would answer). */
    public static ?string $refuse = null;

    /** Ids of pickup points the "validation" does not know. */
    public static array $unknownPoints = [];

    public static function reset(): void
    {
        self::$parcels = [];
        self::$refuse = null;
        self::$unknownPoints = [];
    }

    public function available(): bool
    {
        return true;
    }

    public function createPacket(FarmOrder $order): Packet
    {
        $parcel = Parcel::forOrder($order);
        if (self::$refuse !== null) {
            throw new ShippingFailed(self::$refuse);
        }
        self::$parcels[] = $parcel;
        $id = (string) (4150000000 + $order->id);

        return new Packet($id, 'Z'.$id);
    }

    public function labelPdf(string $packetId, bool $carrierLabel = false): string
    {
        return "%PDF-1.4\n% fake label for parcel ".ltrim($packetId, 'Zz').($carrierLabel ? ' (carrier)' : '')."\n%%EOF\n";
    }

    public function trackingUrl(string $barcode, ?string $locale = null): string
    {
        return 'https://tracking.packeta.com/'.(in_array($locale, ['cs', 'en', 'es'], true) ? $locale : 'en').'/?id='.rawurlencode($barcode);
    }

    public function carriers(): array
    {
        $row = fn (int $id, string $country, string $type, string $name) => ['id' => $id, 'country' => $country, 'type' => $type, 'name' => $name, 'max_kg' => 30.0, 'api_allowed' => true];

        return [
            $row(106, 'CZ', 'home', 'CZ Packeta Home HD'),
            $row(131, 'SK', 'home', 'SK Packeta Home HD'),
            $row(111, 'DE', 'home', 'DE Hermes HD'),
            $row(6373, 'DE', 'point', 'DE Hermes PP'),
            $row(80, 'AT', 'home', 'AT Austrian Post HD'),
            $row(9001, 'ES', 'home', 'ES Correos HD'),
            $row(9002, 'ES', 'home', 'ES MRW HD'),
            $row(9003, 'ES', 'point', 'ES MRW PP'),
            $row(9004, 'ES', 'point', 'ES Correos PP'),
            $row(9011, 'FR', 'home', 'FR Colis Privé HD'),
            $row(9012, 'FR', 'point', 'FR Mondial Relay PP'),
            $row(9021, 'PL', 'home', 'PL DPD HD'),
            $row(9031, 'HU', 'home', 'HU Packeta Home HD'),
        ];
    }

    public function carriersForCountry(string $country): array
    {
        return array_values(array_filter($this->carriers(), fn (array $c) => $c['country'] === strtoupper($country)));
    }

    public function refusePoint(array $point, string $country, float $kg): ?string
    {
        return in_array((string) $point['id'], self::$unknownPoints, true) ? 'NotFound' : null;
    }
}
