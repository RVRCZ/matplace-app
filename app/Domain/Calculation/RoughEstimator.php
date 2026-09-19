<?php

namespace App\Domain\Calculation;

/**
 * Sub-second estimate of filament weight and print time from geometry only (no slicer).
 * The same formula lives in resources/js/calc/rough.ts; constants come from config/pricing.php and are
 * exposed to the browser through /api/config. Keep both in sync (see tests/Unit/RoughEstimatorTest).
 */
final class RoughEstimator
{
    public function __construct(
        private readonly array $rough,
        private readonly MaterialCatalog $materials,
    ) {}

    /**
     * @return array{grams: float, minutes: int, volume_mm3: float, material_mm3: float, shell_mm3: float}
     */
    public function estimate(
        float $volumeMm3,
        ?float $areaMm2,
        string $materialCode,
        string $quality = 'standard',
        int $infillPercent = 15,
        bool $supports = false,
        float $scale = 1.0,
        bool $vaseMode = false,
    ): array {
        $vol = max(0.0, $volumeMm3) * ($scale ** 3);
        $area = $areaMm2 !== null ? max(0.0, $areaMm2) * ($scale ** 2) : null;

        $lineWidth = (float) $this->rough['line_width_mm'];
        $perimeters = $vaseMode ? 1 : (int) $this->rough['perimeters'];

        if ($area !== null) {
            // walls: surface × perimeters × line width, never more than the whole body
            $shell = min($vol, $area * $perimeters * $lineWidth);
        } else {
            $shell = $vol * (float) $this->rough['shell_fraction_fallback'];
        }
        $inner = max(0.0, $vol - $shell);
        $infill = $vaseMode ? 0.0 : max(0, min(100, $infillPercent)) / 100.0;
        $material = $shell + $inner * $infill;

        $grams = $material / 1000.0 * $this->materials->density($materialCode);
        if ($supports) {
            $grams *= (float) $this->rough['support_factor'];
        }

        $timeFactor = (float) ($this->rough['quality_time_factor'][$quality] ?? 1.0);
        $minutes = (int) round($grams * (float) $this->rough['minutes_per_gram'] * $timeFactor + (float) $this->rough['overhead_minutes']);

        return [
            'grams' => round($grams, 1),
            'minutes' => max(1, $minutes),
            'volume_mm3' => round($vol, 1),
            'material_mm3' => round($material, 1),
            'shell_mm3' => round($shell, 1),
        ];
    }
}
