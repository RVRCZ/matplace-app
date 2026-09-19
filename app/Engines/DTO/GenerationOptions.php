<?php

namespace App\Engines\DTO;

final class GenerationOptions
{
    public function __construct(
        public readonly string $quality = 'draft',   // draft = fast/cheap preview, refined = slower
        public readonly ?float $targetSizeMm = null, // hint for scaling the result
        public readonly string $outputFormat = 'stl',
    ) {}
}
