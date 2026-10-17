<?php

namespace Tests\Unit;

use App\Domain\Farm\Footprint;
use App\Domain\Farm\PlateLayout;
use App\Engines\DTO\Dimensions;
use App\Engines\Farm\PhpPrintPreparer;
use App\Engines\Mesh\StlFile;
use PHPUnit\Framework\TestCase;

/**
 * Copies laid by their shadow: never fewer than the grid, a third more for slopes and corners, no two pieces closer
 * than the gap, all inside the usable plate, the same answer every time.
 */
class PlateLayoutTest extends TestCase
{
    /** Usable plates: S1 250 and Max 420 minus the farm's 2 mm margin on each side. */
    private const PLATES = ['S1' => 246.0, 'Max' => 416.0];

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/plate-layout-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    /** @return array<string, list<array{0: float, 1: float}>> */
    private static function shapes(): array
    {
        $circle = [];
        for ($k = 0; $k < 96; $k++) {
            $circle[] = [25 + 25 * cos(2 * M_PI * $k / 96), 25 + 25 * sin(2 * M_PI * $k / 96)];
        }

        return [
            'triangle' => [[0, 0], [40, 0], [20, 60]],                                  // a sign 40 × 60
            'ell' => [[0, 0], [60, 0], [60, 20], [20, 20], [20, 60], [0, 60]],          // 60 × 60, arm 20
            'circle' => $circle,                                                        // Ø 50
            'rect' => [[0, 0], [30, 0], [30, 80], [0, 80]],                             // 30 × 80
        ];
    }

    private static function dims(Footprint $f, float $z = 3.0): Dimensions
    {
        [$x0, $y0, $x1, $y1] = $f->bounds();

        return new Dimensions($x1 - $x0, $y1 - $y0, $z);
    }

    public function test_the_shadow_never_lays_fewer_than_the_grid_and_slopes_and_corners_win_a_fifth(): void
    {
        // the least gain each shape must show over the grid (L on the Max: 6 rows of 7, the 20 mm left over at the
        // side and 31 mm at the back hold no 60 mm piece, so 42 against 36)
        $least = ['triangle' => ['S1' => 1.2, 'Max' => 1.2], 'ell' => ['S1' => 1.2, 'Max' => 1.15], 'circle' => ['S1' => 1.0, 'Max' => 1.0], 'rect' => ['S1' => 1.0, 'Max' => 1.0]];
        foreach (self::PLATES as $plate => $side) {
            $usable = new Dimensions($side, $side, 250);
            foreach (self::shapes() as $name => $pts) {
                $shadow = Footprint::fromPoints($pts);
                $d = self::dims($shadow);
                $grid = PlateLayout::capacity($d, $usable)['max'];
                $plan = PlateLayout::plan($shadow, $d, $usable);
                $this->assertGreaterThanOrEqual($grid, $plan['max'], "$name on $plate");
                $this->assertGreaterThanOrEqual($least[$name][$plate] * $grid, $plan['max'], "$name on $plate: {$plan['max']} against the grid's $grid");
                if ($plan['method'] === 'rows') {
                    $this->assertCount($plan['max'], $plan['placements'], "$name on $plate");
                    $this->assertApartAndInside($shadow, $plan['placements'], $usable, "$name on $plate");
                }
                $this->assertSame($plan, PlateLayout::plan($shadow, $d, $usable), "$name on $plate: the same layout again");
            }
        }
    }

    public function test_a_rectangle_keeps_exactly_the_grid(): void
    {
        $shadow = Footprint::fromPoints(self::shapes()['rect']);
        foreach (self::PLATES as $side) {
            $usable = new Dimensions($side, $side, 250);
            $plan = PlateLayout::plan($shadow, self::dims($shadow), $usable);
            $this->assertSame('grid', $plan['method']);
            $this->assertSame(PlateLayout::capacity(self::dims($shadow), $usable), array_diff_key($plan, ['method' => 1]));
        }
        // no shadow (a model prepared before the shadows) and a shadow filling 85 % of its rectangle: the grid too
        $usable = new Dimensions(246, 246, 250);
        $this->assertSame('grid', PlateLayout::plan(null, new Dimensions(40, 60, 3), $usable)['method']);
        $nearly = Footprint::fromPoints([[0, 0], [40, 0], [40, 54], [36, 60], [0, 60]]);
        $this->assertGreaterThan(PlateLayout::FILL_GRID, $nearly->fill());
        $this->assertSame('grid', PlateLayout::plan($nearly, self::dims($nearly), $usable)['method']);
    }

    public function test_fewer_copies_than_fit_are_laid_compactly_and_apart(): void
    {
        $usable = new Dimensions(246, 246, 250);
        foreach (['triangle', 'ell', 'circle'] as $name) {
            $shadow = Footprint::fromPoints(self::shapes()[$name]);
            foreach ([2, 7, 10] as $copies) {
                $plan = PlateLayout::plan($shadow, self::dims($shadow), $usable, $copies);
                if ($plan['method'] !== 'rows') {
                    continue;
                }
                $this->assertCount($copies, $plan['placements'], "$copies × $name");
                $this->assertApartAndInside($shadow, $plan['placements'], $usable, "$copies × $name");
                $all = PlateLayout::plan($shadow, self::dims($shadow), $usable);
                $this->assertSame($all['max'], $plan['max'], 'max is what fits, whatever was asked for');
            }
        }
    }

    public function test_the_copies_are_written_where_the_rows_put_them(): void
    {
        $src = $this->dir.'/triangle.stl';
        self::prism($src, self::shapes()['triangle'], 3.0);
        $piece = (new PhpPrintPreparer)->prepare($src, $this->dir.'/piece.stl', 1.0, new Dimensions(250, 250, 250));
        $this->assertNotNull($piece->footprint, 'the fallback preparer measures the shadow too');
        $this->assertSame([[0.0, 0.0], [40.0, 0.0], [20.0, 60.0]], $piece->footprint);

        $usable = new Dimensions(246, 246, 250);
        $capacity = PlateLayout::capacityOf($piece, $usable);
        $this->assertSame('rows', $capacity['method']);
        $this->assertGreaterThan(PlateLayout::capacity($piece->bbox, $usable)['max'], $capacity['max']);

        $copies = $capacity['max'];
        $plate = PlateLayout::replicate($piece, $this->dir.'/plate.stl', $copies, $usable);
        $this->assertNotNull($plate);
        $this->assertSame(8 * $copies, $plate->triangles);
        $this->assertSame('rows', $plate->orientation['layout']);
        $this->assertSame($copies, $plate->orientation['copies']);
        $this->assertLessThanOrEqual(246.001, $plate->bbox->x);
        $this->assertLessThanOrEqual(246.001, $plate->bbox->y);
        $this->assertEqualsWithDelta(3.0, $plate->bbox->z, 1e-4);
        [$lo, $hi] = StlFile::bounds($plate->path);
        $this->assertEqualsWithDelta(0.0, $lo[0], 0.01);
        $this->assertEqualsWithDelta(0.0, $lo[1], 0.01);
        $this->assertEqualsWithDelta(0.0, $lo[2], 1e-6);
        $this->assertEqualsWithDelta($copies * 40 * 60 / 2 * 3, StlFile::stats($plate->path)->volumeMm3, 1.0, 'every copy whole, none mirrored');

        // the written pieces are as far apart as the plan says: the shadow of every copy from the file itself
        $shadows = [];
        $tris = iterator_to_array(StlFile::triangles($plate->path), false);
        foreach (array_chunk($tris, 8) as $copy) {
            $shadows[] = Footprint::fromPoints(array_merge(...array_map(fn ($t) => [[$t[0][0], $t[0][1]], [$t[1][0], $t[1][1]], [$t[2][0], $t[2][1]]], $copy)), 3)->points;
        }
        $this->assertCount($copies, $shadows);
        $this->assertMinimumDistance($shadows, PlateLayout::GAP_MM - 0.01, 'written triangles');

        // more than fit: no plate
        $this->assertNull(PlateLayout::replicate($piece, $this->dir.'/too_many.stl', $copies + 1, $usable));
    }

    public function test_a_round_piece_on_the_big_plate_is_laid_within_a_second(): void
    {
        $shadow = Footprint::fromPoints(self::shapes()['circle']);
        $usable = new Dimensions(416, 416, 250);
        $t = microtime(true);
        PlateLayout::plan($shadow, self::dims($shadow), $usable);
        PlateLayout::plan($shadow, self::dims($shadow), $usable, PlateLayout::MAX_COPIES);
        $this->assertLessThan(1.0, microtime(true) - $t);
    }

    public function test_the_shadow_is_a_convex_hull_of_at_most_64_corners_never_inside_the_piece(): void
    {
        $pts = [];
        for ($k = 0; $k < 500; $k++) {
            $pts[] = [30 * cos(2 * M_PI * $k / 500), 20 * sin(2 * M_PI * $k / 500)];
        }
        $pts[] = [0, 0];
        $f = Footprint::fromPoints($pts);
        $this->assertLessThanOrEqual(Footprint::MAX_POINTS, count($f->points));
        // every input point inside the shadow, or outside by less than the 0.1 mm rounding
        $n = count($f->points);
        foreach ($pts as $p) {
            for ($k = 0; $k < $n; $k++) {
                [$a, $b] = [$f->points[$k], $f->points[($k + 1) % $n]];
                $side = (($b[0] - $a[0]) * ($p[1] - $a[1]) - ($b[1] - $a[1]) * ($p[0] - $a[0])) / hypot($b[0] - $a[0], $b[1] - $a[1]);
                $this->assertGreaterThan(-0.08, $side, 'point '.json_encode($p));
            }
        }
        $this->assertEqualsWithDelta(M_PI * 30 * 20, $f->area(), 0.01 * M_PI * 30 * 20);
        $this->assertNull(Footprint::fromArray([[0, 0], [1, 1]]));
        $this->assertNull(Footprint::fromArray('nope'));
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** @param list<array{0: int, 1: float, 2: float}> $placements */
    private function assertApartAndInside(Footprint $shadow, array $placements, Dimensions $usable, string $what): void
    {
        $polys = [];
        foreach ($placements as [$turn, $tx, $ty]) {
            $p = Footprint::moved(Footprint::turned($shadow->points, $turn), $tx, $ty);
            [$x0, $y0, $x1, $y1] = Footprint::box($p);
            $this->assertGreaterThanOrEqual(-1e-6, $x0, $what);
            $this->assertGreaterThanOrEqual(-1e-6, $y0, $what);
            $this->assertLessThanOrEqual($usable->x + 1e-6, $x1, $what);
            $this->assertLessThanOrEqual($usable->y + 1e-6, $y1, $what);
            $polys[] = $p;
        }
        $this->assertMinimumDistance($polys, PlateLayout::GAP_MM - 0.01, $what);
    }

    private function assertMinimumDistance(array $polys, float $least, string $what): void
    {
        $n = count($polys);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                // rectangles further apart than `least` hold pieces further apart too
                [$a0, $b0, $a1, $b1] = Footprint::box($polys[$i]);
                [$c0, $d0, $c1, $d1] = Footprint::box($polys[$j]);
                if (max($c0 - $a1, $a0 - $c1, $d0 - $b1, $b0 - $d1) >= $least) {
                    continue;
                }
                $d = self::distance($polys[$i], $polys[$j]);
                $this->assertGreaterThanOrEqual($least, $d, "$what: pieces $i and $j are ".round($d, 3).' mm apart');
            }
        }
    }

    /** Distance between two convex polygons (0 when they overlap). */
    private static function distance(array $a, array $b): float
    {
        if (Footprint::overlap($a, $b, 0.0)) {
            return 0.0;
        }
        $best = INF;
        foreach ([[$a, $b], [$b, $a]] as [$p, $q]) {
            $m = count($q);
            foreach ($p as $v) {
                for ($k = 0; $k < $m; $k++) {
                    $best = min($best, self::toSegment($v, $q[$k], $q[($k + 1) % $m]));
                }
            }
        }

        return $best;
    }

    private static function toSegment(array $p, array $a, array $b): float
    {
        $dx = $b[0] - $a[0];
        $dy = $b[1] - $a[1];
        $len = $dx * $dx + $dy * $dy;
        $t = $len > 0 ? max(0.0, min(1.0, (($p[0] - $a[0]) * $dx + ($p[1] - $a[1]) * $dy) / $len)) : 0.0;

        return hypot($p[0] - $a[0] - $t * $dx, $p[1] - $a[1] - $t * $dy);
    }

    /** A convex outline extruded to a closed prism (fan caps): 2 (n - 2) + 2 n triangles. */
    public static function prism(string $path, array $outline, float $height): void
    {
        $fh = StlFile::beginBinary($path);
        $n = count($outline);
        $count = 0;
        for ($k = 1; $k < $n - 1; $k++) {
            $a = $outline[0];
            $b = $outline[$k];
            $c = $outline[$k + 1];
            StlFile::writeTriangle($fh, [$a[0], $a[1], 0], [$c[0], $c[1], 0], [$b[0], $b[1], 0]);
            StlFile::writeTriangle($fh, [$a[0], $a[1], $height], [$b[0], $b[1], $height], [$c[0], $c[1], $height]);
            $count += 2;
        }
        for ($k = 0; $k < $n; $k++) {
            $a = $outline[$k];
            $b = $outline[($k + 1) % $n];
            StlFile::writeTriangle($fh, [$a[0], $a[1], 0], [$b[0], $b[1], 0], [$b[0], $b[1], $height]);
            StlFile::writeTriangle($fh, [$a[0], $a[1], 0], [$b[0], $b[1], $height], [$a[0], $a[1], $height]);
            $count += 2;
        }
        StlFile::endBinary($fh, $count);
    }
}
