<?php

namespace App\Support;

/** Two currencies met where only one may be: an account's ledger, a sum. It must not happen; when it does, nothing is written. */
final class CurrencyMismatch extends \LogicException
{
    public function __construct(public readonly string $expected, public readonly string $given, ?string $where = null)
    {
        parent::__construct(trim("Currency {$given} where {$expected} is expected. ".(string) $where));
    }
}
