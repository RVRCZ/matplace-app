<?php

namespace Tests\Feature;

use App\Domain\Farm\ModelValidator;
use App\Engines\DTO\Dimensions;
use App\Engines\Farm\PythonPrintPreparer;
use App\Engines\Repair\PythonTool;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * The repair before a farm print never holds the order up (docs/AL.md §18): pymeshfix took four minutes over a part whose
 * only fault was two edges shared by four faces (order 277, 1 Nov 2026), and the server killed the preparation at 300 s.
 */
class FarmPrepareRepairTest extends TestCase
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
        putenv('MP_MESHFIX_SECONDS');
        putenv('MP_MESHFIX_DELAY');
        parent::tearDown();
    }

    /** The twelve outward triangles of a box, `$skip` of them left out (indexes into the list). */
    private function box(float $x0, float $y0, float $z0, float $d, array $skip = []): array
    {
        $c = fn (int $i) => [$x0 + ($i & 1) * $d, $y0 + ($i >> 1 & 1) * $d, $z0 + ($i >> 2 & 1) * $d];
        $quads = [[0, 2, 3, 1], [4, 5, 7, 6], [0, 1, 5, 4], [2, 6, 7, 3], [0, 4, 6, 2], [1, 3, 7, 5]];   // bottom, top, front, back, left, right
        $tris = [];
        foreach ($quads as [$a, $b, $e, $f]) {
            $tris[] = [$c($a), $c($b), $c($e)];
            $tris[] = [$c($a), $c($e), $c($f)];
        }

        return array_values(array_diff_key($tris, array_flip($skip)));
    }

    /**
     * The outer surface of 10 mm cubes at the given grid cells: the walls between two cubes left out. Two cubes meeting
     * at an edge only give that edge four faces; a ring keeps it all one body, so the repair cannot just part them.
     *
     * @param  array<int, array{0: int, 1: int, 2: int}>  $cells
     */
    private function cubes(array $cells): array
    {
        $near = [[0, 0, -1], [0, 0, 1], [0, -1, 0], [0, 1, 0], [-1, 0, 0], [1, 0, 0]];   // in box()'s order of walls
        $filled = array_flip(array_map(fn ($c) => implode(',', $c), $cells));
        $tris = [];
        foreach ($cells as [$x, $y, $z]) {
            $skip = [];
            foreach ($near as $q => [$dx, $dy, $dz]) {
                if (isset($filled[($x + $dx).','.($y + $dy).','.($z + $dz)])) {
                    array_push($skip, 2 * $q, 2 * $q + 1);
                }
            }
            $tris = array_merge($tris, $this->box($x * 10, $y * 10, $z * 10, 10, $skip));
        }

        return $tris;
    }

    private function stl(array $tris): string
    {
        $base = tempnam(sys_get_temp_dir(), 'mend');
        array_push($this->files, $base, $base.'.stl', $base.'.out.stl', $base.'.out.stl.stage');
        $body = "solid t\n";
        foreach ($tris as $t) {
            $body .= "facet normal 0 0 0\nouter loop\n";
            foreach ($t as $v) {
                $body .= sprintf("vertex %.5F %.5F %.5F\n", ...$v);
            }
            $body .= "endloop\nendfacet\n";
        }
        file_put_contents($base.'.stl', $body."endsolid t\n");

        return $base.'.stl';
    }

    private function prepare(string $src): array
    {
        return app(PythonTool::class)->runScript('farm_tool.py', ['prepare', $src, $src.'.out.stl', '1', '250', '250', '250', '40', 'keep'], 120);
    }

    private function hasMeshfix(): bool
    {
        return Process::run([config('engines.python.bin'), '-c', 'import pymeshfix'])->successful();
    }

    public function test_a_closed_mesh_with_edges_of_four_faces_goes_on_unrepaired_at_once(): void
    {
        // a ring of seven cubes whose two ends touch at one edge only: nothing open, four faces at that edge, one body, like
        // part 1 of order 277 (two such edges in 62 000 faces)
        $src = $this->stl($this->cubes([[0, 0, 0], [0, 0, -1], [1, 0, -1], [2, 0, -1], [2, 0, 0], [2, 0, 1], [1, 0, 1]]));
        $started = microtime(true);
        $r = $this->prepare($src);
        $this->assertLessThan(20, microtime(true) - $started);

        $this->assertTrue($r['ok'], json_encode($r));
        $this->assertFalse($r['watertight']);
        $this->assertFalse($r['repaired']);
        $this->assertSame(['reason' => 'no_holes', 'holes' => 0, 'non_manifold_edges' => 1], $r['unrepaired']);
        $this->assertSame(0, $r['holes']);
        $this->assertSame(1, $r['shells']);
        $this->assertEqualsWithDelta(7000.0, $r['volume_mm3'], 1.0, 'a surface without an opening keeps its true volume');

        // the farm takes it with a word on the order page, where a mesh with a hole is refused
        $mesh = app(PythonPrintPreparer::class)->prepare($src, $src.'.out.stl', 1.0, new Dimensions(250, 250, 250), true);
        $this->assertSame(0, $mesh->holes);
        $this->assertSame('no_holes', $mesh->orientation['unrepaired']['reason']);
        $verdict = ModelValidator::judge($mesh, new Dimensions(250, 250, 250), ['min_model_mm' => 1.0, 'bed_margin_mm' => 2.0]);
        $this->assertTrue($verdict['ok'], json_encode($verdict));
        $this->assertSame(['code' => 'non_manifold', 'data' => ['edges' => 1]], $verdict['warnings'][0]);
        $this->assertStringContainsString('hranách modelu (1)', __('farm.warn.non_manifold', $verdict['warnings'][0]['data']));
    }

    public function test_a_repair_that_takes_too_long_is_stopped_and_the_mesh_goes_on_as_it_is(): void
    {
        if (! $this->hasMeshfix()) {
            $this->markTestSkipped('pymeshfix is not installed: there is nothing slow to stop.');
        }
        // the top and the front of a cube missing: a hole of six edges, too big for trimesh, so pymeshfix gets it
        $src = $this->stl($this->box(0, 0, 0, 20, [2, 3, 4, 5]));
        putenv('MP_MESHFIX_SECONDS=2');
        putenv('MP_MESHFIX_DELAY=60');
        $started = microtime(true);
        $r = $this->prepare($src);
        $this->assertLessThan(20, microtime(true) - $started, 'stopped after its two seconds, not after the made-up minute');

        $this->assertTrue($r['ok'], json_encode($r));
        $this->assertFalse($r['repaired']);
        $this->assertSame('timeout', $r['unrepaired']['reason']);
        $this->assertSame(6, $r['holes']);
        $mesh = app(PythonPrintPreparer::class)->prepare($src, $src.'.out.stl', 1.0, new Dimensions(250, 250, 250), true);
        $this->assertSame('not_watertight', ModelValidator::judge($mesh, new Dimensions(250, 250, 250), ['min_model_mm' => 1.0, 'bed_margin_mm' => 2.0])['errors'][0]['code'], 'a hole is still refused');

        // without the made-up slowness the same hole is closed
        putenv('MP_MESHFIX_DELAY');
        putenv('MP_MESHFIX_SECONDS');
        $r = $this->prepare($src);
        $this->assertTrue($r['repaired'], json_encode($r));
        $this->assertSame('pymeshfix', $r['repair_method']);
        $this->assertNull($r['unrepaired']);
    }

    public function test_the_repair_tool_says_when_it_stopped_the_full_repair(): void
    {
        if (! $this->hasMeshfix()) {
            $this->markTestSkipped('pymeshfix is not installed: there is nothing slow to stop.');
        }
        $src = $this->stl($this->box(0, 0, 0, 20, [2, 3, 4, 5]));
        putenv('MP_MESHFIX_SECONDS=2');
        putenv('MP_MESHFIX_DELAY=60');
        $started = microtime(true);
        $r = app(PythonTool::class)->runScript('repair_tool.py', [$src, $src.'.out.stl'], 120);
        $this->assertLessThan(20, microtime(true) - $started);
        $this->assertTrue($r['ok'], json_encode($r));
        $this->assertSame(1, $r['actions']['meshfix_stopped']);
        $this->assertSame('unchanged', $r['verdict']);
        $this->assertFalse($r['after']['watertight']);
    }
}
