<?php

namespace App\Domain\Generation;

class QuotaExceeded extends \RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $limit, public readonly float $price = 0.0, public readonly float $missing = 0.0)
    {
        parent::__construct($reason);
    }
}
