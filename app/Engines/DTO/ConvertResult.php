<?php

namespace App\Engines\DTO;

final class ConvertResult
{
    public function __construct(
        public readonly string $outPath,
        public readonly string $engine,
        public readonly int $triangles = 0,
        public readonly array $warnings = [],
    ) {}
}
