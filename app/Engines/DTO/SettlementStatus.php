<?php

namespace App\Engines\DTO;

final class SettlementStatus
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const CONFIRMED = 'confirmed';

    public const DISPUTED = 'disputed';

    public function __construct(
        public readonly string $state,
        public readonly ?string $note = null,
    ) {}

    /** Files may be handed over in these states; manual QR trusts pending, a gateway does not. */
    public function allowsRelease(bool $trustPending): bool
    {
        return $this->state === self::PAID || $this->state === self::CONFIRMED
            || ($trustPending && $this->state === self::PENDING);
    }
}
