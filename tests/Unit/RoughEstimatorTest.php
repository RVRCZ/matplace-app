<?php

namespace Tests\Unit;

use App\Domain\Calculation\MaterialCatalog;
use App\Domain\Calculation\RoughEstimator;
use PHPUnit\Framework\TestCase;

/**
 * Reference vectors for the rough estimate. The TypeScript mirror (resources/js/calc/rough.ts)
 * must produce the same numbers for the same inputs — update both when constants change.
 */
class RoughEstimatorTest extends TestCase
{
    private function estimator(): RoughEstimator
    {
        $pricing = require __DIR__.'/../../config/pricing.php';
        $materials = require __DIR__.'/../../config/materials.php';

        return new RoughEstimator($pricing['rough'], new MaterialCatalog($materials));
    }

    public function test_cube_20mm_pla_standard(): void
    {
        // volume 8000 mm³, area 2400 mm²: shell = 2400×2×0.4 = 1920, inner = 6080 × 15 % = 912 → 2832 mm³ × 1.24 = 3.51 g
        $r = $this->estimator()->estimate(8000, 2400, 'PLA', 'standard', 15, false, 1.0);
        $this->assertSame(3.5, $r['grams']);
        $this->assertSame((int) round(3.51168 * 5.5 + 10), $r['minutes']); // 29
        $this->assertSame(29, $r['minutes']);
    }

    public function test_scale_and_supports(): void
    {
        $r1 = $this->estimator()->estimate(8000, 2400, 'PLA', 'standard', 15, false, 1.0);
        $r2 = $this->estimator()->estimate(8000, 2400, 'PLA', 'standard', 15, false, 2.0);
        $this->assertEqualsWithDelta(64000.0, $r2['volume_mm3'], 0.1);
        $this->assertGreaterThan($r1['grams'] * 4, $r2['grams']); // shells scale ², infill ³ → between 4× and 8×
        $this->assertLessThan($r1['grams'] * 8, $r2['grams']);

        $sup = $this->estimator()->estimate(8000, 2400, 'PLA', 'standard', 15, true, 1.0);
        $this->assertEqualsWithDelta($r1['grams'] * 1.12, $sup['grams'], 0.1);
    }

    public function test_quality_changes_time_not_material(): void
    {
        $d = $this->estimator()->estimate(8000, 2400, 'PLA', 'draft', 15);
        $f = $this->estimator()->estimate(8000, 2400, 'PLA', 'fine', 15);
        $this->assertSame($d['grams'], $f['grams']);
        $this->assertGreaterThan($d['minutes'], $f['minutes']);
    }

    public function test_fallback_without_area(): void
    {
        $r = $this->estimator()->estimate(8000, null, 'PETG', 'standard', 100);
        $this->assertEqualsWithDelta(8000 / 1000 * 1.27, $r['grams'], 0.1); // 100 % infill = full volume
    }

    public function test_vase_mode_single_wall_no_infill(): void
    {
        $r = $this->estimator()->estimate(8000, 2400, 'PLA', 'standard', 50, false, 1.0, true);
        $this->assertEqualsWithDelta(2400 * 0.4 / 1000 * 1.24, $r['grams'], 0.1);
    }
}
