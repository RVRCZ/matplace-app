<?php

namespace App\Engines\DTO;

/** A model made ready for the plate: repaired when needed, in millimetres, oriented, sitting on Z = 0. */
final class PreparedMesh
{
    /**
     * @param  bool|null  $watertight  null = could not be determined (no topology engine for this mesh size)
     * @param  array<string,mixed>  $orientation  rotation matrix (row-major 3×3), method, overhang area before/after, base area
     */
    public function __construct(
        public readonly string $path,
        public readonly ?bool $watertight,
        public readonly bool $wasWatertight,
        public readonly bool $repaired,
        public readonly int $openEdges,
        public readonly Dimensions $bbox,
        public readonly float $volumeMm3,
        public readonly float $areaMm2,
        public readonly int $triangles,
        public readonly int $shells,
        public readonly array $orientation,
        public readonly string $engine,
    ) {}

    public function toArray(): array
    {
        return [
            'watertight' => $this->watertight, 'was_watertight' => $this->wasWatertight, 'repaired' => $this->repaired,
            'open_edges' => $this->openEdges, 'bbox' => $this->bbox->toArray(), 'volume_mm3' => round($this->volumeMm3, 2),
            'area_mm2' => round($this->areaMm2, 2), 'triangles' => $this->triangles, 'shells' => $this->shells,
            'orientation' => $this->orientation, 'engine' => $this->engine,
        ];
    }
}
