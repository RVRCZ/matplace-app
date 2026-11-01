<?php

namespace Tests\Feature;

use App\Engines\Repair\PythonTool;
use Tests\TestCase;

/**
 * The pose the farm picks (engines/python/farm_tool.py, docs/AL.md §17): a flat face a hair above the lowest point lies on the
 * plate, a part standing on a real foot still turns over.
 */
class FarmOrientationTest extends TestCase
{
    /** @var array<int, string> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (! app(PythonTool::class)->available()) {
            $this->markTestSkipped('The farm tool needs Python with trimesh.');
        }
    }

    protected function tearDown(): void
    {
        array_map(fn ($f) => @unlink($f), $this->files);
        parent::tearDown();
    }

    /**
     * A prism: the outline in the x-z plane (counter-clockwise seen from -y, its first corner sees all the others), `$depth` long in y.
     *
     * @param  array<int, array{0: float, 1: float}>  $outline
     * @return array<int, array{0: array, 1: array, 2: array}>
     */
    private function prism(array $outline, float $depth, float $y = 0.0): array
    {
        $tris = [];
        $n = count($outline);
        foreach ($outline as $i => [$x, $z]) {
            [$x2, $z2] = $outline[($i + 1) % $n];
            $tris[] = [[$x, $y, $z], [$x2, $y + $depth, $z2], [$x2, $y, $z2]];
            $tris[] = [[$x, $y, $z], [$x, $y + $depth, $z], [$x2, $y + $depth, $z2]];
        }
        for ($i = 1; $i < $n - 1; $i++) {
            [$ax, $az] = $outline[0];
            [$bx, $bz] = $outline[$i];
            [$cx, $cz] = $outline[$i + 1];
            $tris[] = [[$ax, $y, $az], [$bx, $y, $bz], [$cx, $y, $cz]];
            $tris[] = [[$ax, $y + $depth, $az], [$cx, $y + $depth, $cz], [$bx, $y + $depth, $bz]];
        }

        return $tris;
    }

    /** A box, every wall its own: pieces of a model share no edges with it. */
    private function box(float $x0, float $y0, float $z0, float $dx, float $dy, float $dz): array
    {
        return $this->prism([[$x0, $z0], [$x0 + $dx, $z0], [$x0 + $dx, $z0 + $dz], [$x0, $z0 + $dz]], $dy, $y0);
    }

    /** A scraper: a plate 136 × 50 × 6.9 lying on its flat back, the sharpened edge 0.06 mm below the back. */
    private function scraper(): array
    {
        return $this->prism([[120.0, 0.06], [136.0, 0.0], [136.0, 0.4], [120.0, 6.9], [0.0, 6.9], [0.0, 0.06]], 50.0);
    }

    /** A plate 40 × 40 × 3 on a foot of 69 mm2, `$foot` mm below it (the cartridge clip in rate()'s comment). */
    private function onFoot(float $foot): array
    {
        $side = sqrt(69);
        $at = (40 - $side) / 2;

        return array_merge($this->box($at, $at, 0.0, $side, $side, $foot), $this->box(0.0, 0.0, $foot, 40.0, 40.0, 3.0));
    }

    /** Whole turns about an axis: the pose a file comes in. */
    private function turned(array $tris, string $axis, int $quarters): array
    {
        $turn = fn (array $v) => match ($axis) {
            'x' => [$v[0], -$v[2], $v[1]],
            'y' => [$v[2], $v[1], -$v[0]],
        };
        for ($i = 0; $i < $quarters; $i++) {
            $tris = array_map(fn ($t) => array_map($turn, $t), $tris);
        }

        return $tris;
    }

    private function prepare(array $tris): array
    {
        $base = tempnam(sys_get_temp_dir(), 'orient');
        $src = $base.'.stl';
        $out = $base.'.out.stl';
        array_push($this->files, $base, $src, $out);
        $body = "solid t\n";
        foreach ($tris as [$a, $b, $c]) {
            $body .= "facet normal 0 0 0\nouter loop\n";
            foreach ([$a, $b, $c] as $v) {
                $body .= sprintf("vertex %.5F %.5F %.5F\n", ...$v);
            }
            $body .= "endloop\nendfacet\n";
        }
        file_put_contents($src, $body."endsolid t\n");

        return app(PythonTool::class)->runScript('farm_tool.py', ['prepare', $src, $out, '1', '250', '250', '250', '40', 'turn'], 120);
    }

    public function test_a_blade_a_hair_below_the_flat_back_still_lies_on_the_back(): void
    {
        // already lying: it stays, the back counts as standing on the plate and nothing of it as overhang
        $flat = $this->prepare($this->scraper());
        $this->assertTrue($flat['ok'], json_encode($flat));
        $this->assertEqualsWithDelta(6.9, $flat['bbox']['z'], 0.05);
        $this->assertFalse($flat['orientation']['changed']);
        $this->assertGreaterThan(5000, $flat['orientation']['base_mm2'], 'the back of 120 × 50 and the blade');
        $this->assertLessThan(150, $flat['orientation']['overhang_mm2']);

        // standing on the edge, on the blade, on the end or on its face: it is laid down on the back
        foreach ([['x', 1], ['x', 3], ['y', 1], ['y', 3], ['x', 2]] as [$axis, $quarters]) {
            $r = $this->prepare($this->turned($this->scraper(), $axis, $quarters));
            $where = $axis.$quarters;
            $this->assertEqualsWithDelta(6.9, $r['bbox']['z'], 0.05, $where);
            $this->assertTrue($r['orientation']['changed'], $where);
            $this->assertGreaterThan(5000, $r['orientation']['base_mm2'], $where);
            $this->assertLessThan(150, $r['orientation']['overhang_mm2'], $where);
        }
    }

    public function test_a_part_on_a_small_foot_is_still_turned_over(): void
    {
        // a shadow of 1600 mm2 on a foot of 69: on its other side only the hidden joint of the two boxes looks down
        $r = $this->prepare($this->onFoot(10.0));
        $this->assertTrue($r['orientation']['changed']);
        $this->assertLessThan(100, $r['orientation']['overhang_mm2']);
        $this->assertGreaterThan(1500, $r['orientation']['base_mm2']);
        $this->assertGreaterThan(1500, $r['orientation']['overhang_mm2_before']);

        // half a millimetre is not a hair: the plate above such a foot hangs in the air, and it turns over too
        $r = $this->prepare($this->onFoot(0.5));
        $this->assertTrue($r['orientation']['changed']);
        $this->assertGreaterThan(1500, $r['orientation']['overhang_mm2_before']);
    }
}
