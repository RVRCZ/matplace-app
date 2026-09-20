<?php

namespace App\Domain\Generation;

class QuotaExceeded extends \RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $limit)
    {
        parent::__construct($reason);
    }
}
