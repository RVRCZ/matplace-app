<?php

namespace App\Engines\DTO;

final class ConvertOptions
{
    public function __construct(
        public readonly float $linearDeflection = 0.05, // mm, tessellation tolerance for CAD formats
        public readonly float $angularDeflection = 0.3, // rad
        public readonly string $units = 'mm',
    ) {}
}
