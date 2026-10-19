<?php

namespace App\Engines\DTO;

final class SliceResult
{
    /**
     * @param  string[]  $warnings  machine-readable codes: overhang, exceeds_bed, thin_walls…
     * @param  array<string,mixed>  $raw  engine specific details (engine name, profile, timings)
     * @param  list<float>  $filamentGrams  grams per filament (T0, T1…) of a plate with several filaments; [] for one
     * @param  int  $changes  how many times the machine changes the filament on that plate
     */
    public function __construct(
        public readonly float $grams,
        public readonly int $minutes,
        public readonly Dimensions $dims,
        public readonly bool $supportsUsed,
        public readonly ?string $gcodePath = null,
        public readonly array $warnings = [],
        public readonly array $raw = [],
        public readonly ?float $meters = null,
        public readonly array $minutesByMode = [],   // normal | silent | sport → minutes, when the machine has speed modes
        public readonly ?int $layers = null,
        public readonly array $timings = [],         // seconds per step of the engine (mesh_s, slice1_s, slice2_s, parse_s…), not part of the result
        public readonly array $filamentGrams = [],
        public readonly int $changes = 0,
    ) {}

    /** The same result with the engine's timings added (a cached result says how it was served). */
    public function withTimings(array $timings): self
    {
        return new self($this->grams, $this->minutes, $this->dims, $this->supportsUsed, $this->gcodePath, $this->warnings, $this->raw, $this->meters, $this->minutesByMode, $this->layers, $timings + $this->timings, $this->filamentGrams, $this->changes);
    }

    public function toArray(): array
    {
        return [
            'grams' => round($this->grams, 1),
            'meters' => $this->meters === null ? null : round($this->meters, 2),
            'minutes' => $this->minutes,
            'minutes_by_mode' => $this->minutesByMode,
            'layers' => $this->layers,
            'dims' => $this->dims->toArray(),
            'supports_used' => $this->supportsUsed,
            'gcode_path' => $this->gcodePath,
            'warnings' => $this->warnings,
            'raw' => $this->raw,
        ] + ($this->filamentGrams ? ['filament_grams' => array_map(fn ($g) => round((float) $g, 1), $this->filamentGrams), 'changes' => $this->changes] : []);
    }
}
