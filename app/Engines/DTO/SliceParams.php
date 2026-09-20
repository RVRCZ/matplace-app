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
    ) {}

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
        return [
            'material' => $this->materialCode,
            'quality' => $this->quality,
            'infill' => $this->infillPercent,
            'supports' => $this->supports,
            'scale' => $this->scale,
            'vase' => $this->vaseMode,
            'tree' => $this->treeSupports,
        ];
    }

    /** Stable hash used for result caching (same file + same params = same result). */
    public function cacheKey(): string
    {
        return sha1(json_encode($this->toArray()));
    }
}
