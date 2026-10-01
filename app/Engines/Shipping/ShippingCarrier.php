<?php

namespace App\Engines\Shipping;

use App\Models\FarmOrder;

/**
 * The company that carries finished prints to customers (Packeta). Everything that leaves the application towards
 * it goes through here; tests use FakeCarrier.
 */
interface ShippingCarrier
{
    /** Credentials are set: parcels can be created. */
    public function available(): bool;

    /**
     * Announce the parcel of a finished order. Abroad the same call goes to a partner carrier: the address is the
     * carrier's id, a partner's pickup point adds its own code.
     *
     * @throws ShippingFailed with the carrier's answer as the text
     */
    public function createPacket(FarmOrder $order): Packet;

    /**
     * The label to stick on the box, as a PDF.
     *
     * @param  bool  $carrierLabel  the parcel travels with a partner carrier: ask for the partner's own label first
     *
     * @throws ShippingFailed
     */
    public function labelPdf(string $packetId, bool $carrierLabel = false): string;

    /** Where the customer follows the parcel. */
    public function trackingUrl(string $barcode, ?string $locale = null): string;

    /**
     * Every carrier of the feed.
     *
     * @return list<array{id: int, country: string, type: string, name: string}> type = point | home
     *
     * @throws ShippingFailed
     */
    public function carriers(): array;

    /**
     * @return list<array{id: int, country: string, type: string, name: string}>
     *
     * @throws ShippingFailed
     */
    public function carriersForCountry(string $country): array;

    /**
     * Is the pickup point the browser sent really a point in this country that takes a parcel this heavy?
     * The picker runs in the customer's browser, so its answer is checked again here.
     *
     * @param  array{id: string, carrier_id?: string|null}  $point
     * @return string|null null = fine (or the check could not be reached: the order is not held up); else why not
     */
    public function refusePoint(array $point, string $country, float $kg): ?string;
}
