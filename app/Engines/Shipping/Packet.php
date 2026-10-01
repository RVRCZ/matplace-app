<?php

namespace App\Engines\Shipping;

/** A parcel the carrier accepted: its id (for labels) and the barcode the customer follows it by. */
final class Packet
{
    public function __construct(public readonly string $id, public readonly string $barcode) {}
}
