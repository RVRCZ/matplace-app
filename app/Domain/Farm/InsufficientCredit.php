<?php

namespace App\Domain\Farm;

use App\Support\Money;

final class InsufficientCredit extends \RuntimeException
{
    public function __construct(public readonly float $balance, public readonly float $needed, public readonly string $currency = 'CZK')
    {
        parent::__construct('Insufficient credit.');
    }

    public function missing(): float
    {
        return round(max(0, $this->needed - $this->balance), 2);
    }

    public function missingMoney(): Money
    {
        return new Money($this->missing(), $this->currency);
    }
}
