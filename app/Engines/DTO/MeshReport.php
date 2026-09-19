<?php

namespace App\Engines\DTO;

/** Result of a mesh check. Units: millimetres. */
final class MeshReport
{
    /** @param  string[]  $issues  codes: not_watertight, multiple_shells, degenerate_faces, flipped_normals */
    public function __construct(
        public readonly bool $watertight,
        public readonly float $volumeMm3,
        public readonly float $areaMm2,
        public readonly Dimensions $bbox,
        public readonly int $triangles,
        public readonly int $shells = 1,
        public readonly bool $flippedNormals = false,
        public readonly array $issues = [],
        public readonly ?string $engine = null,
    ) {}

    public function toArray(): array
    {
        return [
            'watertight' => $this->watertight,
            'volume_mm3' => round($this->volumeMm3, 2),
            'area_mm2' => round($this->areaMm2, 2),
            'bbox' => $this->bbox->toArray(),
            'triangles' => $this->triangles,
            'shells' => $this->shells,
            'flipped_normals' => $this->flippedNormals,
            'issues' => $this->issues,
            'engine' => $this->engine,
        ];
    }

    public static function fromArray(array $a): self
    {
        return new self(
            watertight: (bool) ($a['watertight'] ?? false),
            volumeMm3: (float) ($a['volume_mm3'] ?? 0),
            areaMm2: (float) ($a['area_mm2'] ?? 0),
            bbox: Dimensions::fromArray($a['bbox'] ?? []),
            triangles: (int) ($a['triangles'] ?? 0),
            shells: (int) ($a['shells'] ?? 1),
            flippedNormals: (bool) ($a['flipped_normals'] ?? false),
            issues: (array) ($a['issues'] ?? []),
            engine: $a['engine'] ?? null,
        );
    }
}
