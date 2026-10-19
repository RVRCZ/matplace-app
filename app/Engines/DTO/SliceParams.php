<?php

namespace App\Engines\DTO;

/** Parameters of one slicing run. Material and quality codes map to engine profiles via config/engines.php. */
final class SliceParams
{
    public const QUALITIES = ['draft', 'standard', 'fine'];

    public function __construct(
        public readonly string $materialCode,
        public readonly string $quality = 'standard',
        public readonly int $infillPercent = 15,
        public readonly ?bool $supports = null, // null = auto (try without, retry with)
        public readonly float $scale = 1.0,
        public readonly bool $vaseMode = false,
        public readonly bool $treeSupports = false, // set by the pipeline for organic (generated) models, not by the customer
        // Print farm: the printer and material rows carry their own profile files and overrides (real plate size, no
        // prime tower…). Empty = the shared calculator profiles from config/engines.php, exactly as before.
        public readonly array $profiles = [],      // machine | process | filament => profile file name
        public readonly array $overrides = [],     // machine | process | filament => [setting => value]
        public readonly bool $keepGcode = false,   // the G-code is the product, not a by-product of the estimate
        // One plate, several filaments (the farm, docs/AL.md §10): a 3MF project with an object per part, each
        // object told its filament, and a filament profile per filament. Empty = one filament, the mesh as given.
        public readonly array $filaments = [],     // list of [profile => file, overrides => [setting => value], volume_mm3 => float]
        public readonly ?string $project = null,   // the 3MF the slicer loads instead of the mesh
    ) {}

    public function withFarmProfile(array $profiles, array $overrides): self
    {
        return new self($this->materialCode, $this->quality, $this->infillPercent, $this->supports, $this->scale, $this->vaseMode, $this->treeSupports, $profiles, $overrides, true, $this->filaments, $this->project);
    }

    /**
     * The same run over a 3MF of several parts, one filament each (the process overrides on top are what a plate with
     * filament changes needs: the prime tower).
     *
     * @param  list<array{profile: string, overrides: array<string, mixed>, volume_mm3?: float}>  $filaments  by extruder, first = T0
     * @param  array<string, mixed>  $process  settings added to the process overrides
     */
    public function withFilaments(array $filaments, string $project, array $process = []): self
    {
        $overrides = $this->overrides;
        $overrides['process'] = $process + (array) ($overrides['process'] ?? []);

        return new self($this->materialCode, $this->quality, $this->infillPercent, $this->supports, $this->scale, $this->vaseMode, $this->treeSupports, $this->profiles, $overrides, true, array_values($filaments), $project);
    }

    public static function fromArray(array $a): self
    {
        $supports = $a['supports'] ?? null;
        if ($supports === 'auto' || $supports === '') {
            $supports = null;
        }

        return new self(
            materialCode: strtoupper((string) ($a['material'] ?? 'PLA')),
            quality: in_array($a['quality'] ?? 'standard', self::QUALITIES, true) ? ($a['quality'] ?? 'standard') : 'standard',
            infillPercent: max(0, min(100, (int) ($a['infill'] ?? 15))),
            supports: $supports === null ? null : (bool) $supports,
            scale: max(0.1, min(10.0, (float) ($a['scale'] ?? 1.0))),
            vaseMode: (bool) ($a['vase'] ?? false),
            treeSupports: (bool) ($a['tree'] ?? false),
        );
    }

    public function toArray(): array
    {
        $a = [
            'material' => $this->materialCode,
            'quality' => $this->quality,
            'infill' => $this->infillPercent,
            'supports' => $this->supports,
            'scale' => $this->scale,
            'vase' => $this->vaseMode,
            'tree' => $this->treeSupports,
        ];
        // only when used, so cache keys of ordinary calculations stay what they were
        if ($this->profiles || $this->overrides) {
            $a += ['profiles' => $this->profiles, 'overrides' => $this->overrides];
        }
        if ($this->filaments) {
            $a += ['filaments' => $this->filaments, 'project' => $this->project];
        }

        return $a;
    }

    /** Stable hash used for result caching (same file + same params = same result). */
    public function cacheKey(): string
    {
        return sha1(json_encode($this->toArray()));
    }
}
