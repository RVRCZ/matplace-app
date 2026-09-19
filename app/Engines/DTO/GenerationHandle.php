<?php

namespace App\Engines\DTO;

final class GenerationHandle
{
    public function __construct(
        public readonly string $engine,
        public readonly string $externalId,
        public readonly array $meta = [],
    ) {}
}
