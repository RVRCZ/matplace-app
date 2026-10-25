<?php

namespace Tests\Unit;

use App\Domain\Calculation\ShapeRange;
use PHPUnit\Framework\TestCase;

/**
 * Reference vectors for the range by shape (docs/M.md §6). The TypeScript mirror (resources/js/calc/rough.ts
 * rangeFactors) must give the same numbers for the same inputs: scripts/check_rough_range.mjs runs these vectors
 * against it — update both when the constants in config/pricing.php change.
 */
class ShapeRangeTest extends TestCase
{
    private function range(): ShapeRange
    {
        $pricing = require __DIR__.'/../../config/pricing.php';

        return new ShapeRange($pricing['rough']['range']);
    }

    /** @return array<string, array{array, array{time: array{float, float}, price: array{float, float}}}> */
    public static function vectors(): array
    {
        return [
            // height, fill g/cm³, overhang share, supports, minutes → [low, high] of the time and of the price
            'flat compact, no supports, 12 min' => [[10.0, 0.5, 0.0, false, 12], ['time' => [0.39, 1.25], 'price' => [0.49, 1.05]]],
            'tall thin, slicer decides, 200 min' => [[120.0, 0.03, 0.0, null, 200], ['time' => [0.78, 3.96], 'price' => [0.7, 1.5939]]],
            'half way up both ramps, hangs over, 22.5 min' => [[57.5, 0.1, 0.5, null, 22], ['time' => [0.572, 2.8875], 'price' => [0.588, 1.7364]]],
            'supports on' => [[50.0, 0.1, 0.1, true, 60], ['time' => [0.78, 2.8412], 'price' => [0.7, 1.7218]]],
            'nothing known of the shape' => [[null, null, null, null, 100], ['time' => [0.78, 3.96], 'price' => [0.7, 1.5939]]],
            'calculation 554 (150 × 90 × 100 mm, 62.7 g)' => [[100.0, 0.046, 0.2, null, 103], ['time' => [0.78, 3.879], 'price' => [0.7, 1.7905]]],
        ];
    }

    public function test_reference_vectors(): void
    {
        $r = $this->range();
        foreach (self::vectors() as $name => [$in, $expected]) {
            $this->assertSame($expected, $r->factors(...$in), $name);
        }
    }

    public function test_what_widens_and_what_lowers(): void
    {
        $r = $this->range();
        $flat = $r->factors(10.0, 0.5, 0.0, false, 60);
        $tall = $r->factors(150.0, 0.5, 0.0, false, 60);
        $thin = $r->factors(10.0, 0.02, 0.0, false, 60);
        $auto = $r->factors(10.0, 0.5, 0.0, null, 60);
        $hangs = $r->factors(10.0, 0.5, 0.5, null, 60);
        $supports = $r->factors(10.0, 0.5, 0.0, true, 60);
        $tiny = $r->factors(10.0, 0.5, 0.0, false, 10);
        foreach (['time', 'price'] as $k) {
            $this->assertGreaterThan($flat[$k][1], $tall[$k][1], "$k: height widens the top");
            $this->assertGreaterThan($flat[$k][1], $thin[$k][1], "$k: thinness widens the top");
            $this->assertGreaterThanOrEqual($auto[$k][1], $hangs[$k][1], "$k: overhangs with the slicer deciding never narrow");
            $this->assertGreaterThanOrEqual($flat[$k][1], $auto[$k][1], "$k: leaving supports to the slicer is wider than no supports");
            $this->assertGreaterThanOrEqual($auto[$k][1], $supports[$k][1], "$k: supports on is the widest supports term");
            $this->assertSame($flat[$k][0], $tall[$k][0], "$k: the shape leaves the bottom alone");
            $this->assertLessThan($flat[$k][0], $tiny[$k][0], "$k: a tiny print lowers the bottom");
            $this->assertLessThan(1.0, $flat[$k][0]);
            $this->assertGreaterThan(1.0, $flat[$k][1]);
        }
        // the price's supports term exceeds its auto term, so overhangs push the price's top up to "supports on"
        $this->assertSame($supports['price'][1], $hangs['price'][1]);
    }

    public function test_fill_of_the_box(): void
    {
        $this->assertEqualsWithDelta(0.5, ShapeRange::fill(4.0, ['x' => 20, 'y' => 20, 'z' => 20]), 1e-9);  // 4 g in 8 cm³
        $this->assertEqualsWithDelta(0.0625, ShapeRange::fill(4.0, ['x' => 20, 'y' => 20, 'z' => 20], 2.0), 1e-9);  // the box grows eightfold at scale 2
        $this->assertNull(ShapeRange::fill(4.0, null));
        $this->assertNull(ShapeRange::fill(4.0, ['x' => 0, 'y' => 20, 'z' => 20]));
    }
}
