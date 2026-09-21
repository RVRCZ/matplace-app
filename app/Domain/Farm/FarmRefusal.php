<?php

namespace App\Domain\Farm;

/** The farm said no, for a reason the customer can be told: the code maps to the text `farm.refuse.<code>`. */
final class FarmRefusal extends \RuntimeException
{
    /** @param array<string,mixed> $data */
    public function __construct(public readonly string $reason, public readonly array $data = [])
    {
        parent::__construct('Farm refused: '.$reason);
    }

    public function text(): string
    {
        return __('farm.refuse.'.$this->reason, $this->data);
    }
}
