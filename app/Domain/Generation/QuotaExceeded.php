<?php

namespace App\Domain\Generation;

class QuotaExceeded extends \RuntimeException
{
    /** $price and $missing are in the currency of the account that would pay. */
    public function __construct(public readonly string $reason, public readonly int $limit, public readonly float $price = 0.0, public readonly float $missing = 0.0, public readonly string $currency = 'CZK')
    {
        parent::__construct($reason);
    }
}
