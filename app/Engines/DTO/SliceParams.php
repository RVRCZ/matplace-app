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
    ) {}

    public function withFarmProfile(array $profiles, array $overrides): self
    {
        return new self($this->materialCode, $this->quality, $this->infillPercent, $this->supports, $this->scale, $this->vaseMode, $this->treeSupports, $profiles, $overrides, true);
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

        return $a;
    }

    /** Stable hash used for result caching (same file + same params = same result). */
    public function cacheKey(): string
    {
        return sha1(json_encode($this->toArray()));
    }
}
