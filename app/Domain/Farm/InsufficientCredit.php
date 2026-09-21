<?php

namespace App\Domain\Farm;

final class InsufficientCredit extends \RuntimeException
{
    public function __construct(public readonly float $balance, public readonly float $needed)
    {
        parent::__construct('Insufficient credit.');
    }

    public function missing(): float
    {
        return round(max(0, $this->needed - $this->balance), 2);
    }
}
