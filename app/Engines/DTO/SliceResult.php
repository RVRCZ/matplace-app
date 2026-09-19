<?php

namespace App\Engines\DTO;

final class SliceResult
{
    /**
     * @param  string[]  $warnings  machine-readable codes: overhang, exceeds_bed, thin_walls…
     * @param  array<string,mixed>  $raw  engine specific details (engine name, profile, timings)
     */
    public function __construct(
        public readonly float $grams,
        public readonly int $minutes,
        public readonly Dimensions $dims,
        public readonly bool $supportsUsed,
        public readonly ?string $gcodePath = null,
        public readonly array $warnings = [],
        public readonly array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'grams' => round($this->grams, 1),
            'minutes' => $this->minutes,
            'dims' => $this->dims->toArray(),
            'supports_used' => $this->supportsUsed,
            'gcode_path' => $this->gcodePath,
            'warnings' => $this->warnings,
            'raw' => $this->raw,
        ];
    }
}
