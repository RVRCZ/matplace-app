<?php

namespace App\Engines\DTO;

/** What the paying party has to do to settle a royalty. */
final class SettlementInstruction
{
    public function __construct(
        public readonly string $provider,
        public readonly string $kind,            // qr | redirect | none
        public readonly ?string $payload = null, // SPD string for QR, or URL for redirect
        public readonly ?string $reference = null,
        public readonly float $amount = 0.0,
        public readonly string $currency = 'CZK',
    ) {}
}
