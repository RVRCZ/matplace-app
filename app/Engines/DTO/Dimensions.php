<?php

namespace App\Engines\DTO;

/** Axis-aligned bounding box in millimetres. */
final class Dimensions
{
    public function __construct(
        public readonly float $x,
        public readonly float $y,
        public readonly float $z,
    ) {}

    public function max(): float
    {
        return max($this->x, $this->y, $this->z);
    }

    /** Scaled copy (uniform scale factor). */
    public function scaled(float $scale): self
    {
        return new self($this->x * $scale, $this->y * $scale, $this->z * $scale);
    }

    /** True when the box fits the given bed in any axis permutation (rotation allowed). */
    public function fits(float $bedX, float $bedY, float $bedZ): bool
    {
        $d = [$this->x, $this->y, $this->z];
        sort($d);
        $b = [$bedX, $bedY, $bedZ];
        sort($b);

        return $d[0] <= $b[0] && $d[1] <= $b[1] && $d[2] <= $b[2];
    }

    public function toArray(): array
    {
        return ['x' => round($this->x, 2), 'y' => round($this->y, 2), 'z' => round($this->z, 2)];
    }

    public static function fromArray(array $a): self
    {
        return new self((float) ($a['x'] ?? 0), (float) ($a['y'] ?? 0), (float) ($a['z'] ?? 0));
    }
}
