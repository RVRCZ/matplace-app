<?php

namespace Tests\Unit;

use App\Engines\Mesh\MeshDecimator;
use App\Engines\Mesh\StlFile;
use App\Engines\Repair\ScriptRunner;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * A very fine mesh is thinned before it is sliced (docs/AL.md §10.11): the count that decides, and what the slicer
 * gets when the thinning works and when it does not.
 */
final class MeshDecimatorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/mp_decimate_'.uniqid();
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

    /** A stand-in for the Python script: what it would print, or what it would throw. */
    private function runner(\Closure $answer, ?array &$calls = null): ScriptRunner
    {
        return new class($answer, $calls) implements ScriptRunner
        {
            public function __construct(private \Closure $answer, private ?array &$calls) {}

            public function runScript(string $script, array $args, int $timeout = 90): array
            {
                $this->calls[] = [$script, $args, $timeout];

                return ($this->answer)($args);
            }
        };
    }

    public function test_the_triangle_count_comes_from_the_header_of_a_binary_stl_and_is_counted_in_an_ascii_one(): void
    {
        MeshFixtures::cubeStl($this->dir.'/cube.stl', 10);
        $this->assertSame(12, StlFile::triangleCount($this->dir.'/cube.stl'));
        file_put_contents($this->dir.'/tri.stl', "solid tri\n facet normal 0 0 1\n  outer loop\n   vertex 0 0 0\n   vertex 1 0 0\n   vertex 0 1 0\n  endloop\n endfacet\n facet normal 0 0 1\n  outer loop\n   vertex 1 0 0\n   vertex 1 1 0\n   vertex 0 1 0\n  endloop\n endfacet\nendsolid tri\n");
        $this->assertSame(2, StlFile::triangleCount($this->dir.'/tri.stl'));
    }

    public function test_only_a_mesh_above_the_threshold_is_thinned_and_the_slicer_gets_the_thinned_copy(): void
    {
        MeshFixtures::cubeStl($this->dir.'/cube.stl', 10);
        $calls = [];
        $python = $this->runner(function () {
            MeshFixtures::cubeStl($this->dir.'/thin.stl', 10);

            return ['ok' => true, 'before' => 12, 'after' => 8, 'deviation_mm' => 0.01, 'watertight' => true, 'seconds' => 0.4];
        }, $calls);
        $d = new MeshDecimator($python, ['decimate_above' => 10, 'decimate_target' => 8, 'max_deviation_mm' => 0.05, 'decimate_timeout' => 240]);
        $this->assertTrue($d->wants($this->dir.'/cube.stl'), '12 triangles are above a threshold of 10');
        $r = $d->decimate($this->dir.'/cube.stl', $this->dir.'/thin.stl');
        $this->assertSame([['decimate_tool.py', [$this->dir.'/cube.stl', $this->dir.'/thin.stl', '8', '0.05', '0.15'], 240]], $calls, 'the script gets the target and the limits from the config (the peak three times the limit when unset)');
        $this->assertTrue($r['applied']);
        $this->assertSame([12, 8, 0.01], [$r['before'], $r['after'], $r['deviation_mm']]);
        $this->assertFileExists($this->dir.'/thin.stl');

        $small = new MeshDecimator($python, ['decimate_above' => 100, 'decimate_target' => 50, 'max_deviation_mm' => 0.05, 'decimate_timeout' => 240]);
        $this->assertFalse($small->wants($this->dir.'/cube.stl'), 'a mesh under the threshold is sliced as it is');
        $off = new MeshDecimator($python, ['decimate_above' => 0, 'decimate_target' => 50, 'max_deviation_mm' => 0.05, 'decimate_timeout' => 240]);
        $this->assertFalse($off->wants($this->dir.'/cube.stl'), 'a threshold of 0 switches the thinning off');
    }

    public function test_a_mesh_the_script_cannot_thin_within_the_deviation_is_sliced_as_it_is(): void
    {
        MeshFixtures::cubeStl($this->dir.'/cube.stl', 10);
        $python = $this->runner(function () {
            file_put_contents($this->dir.'/thin.stl', 'half-written');

            return ['ok' => false, 'reason' => 'deviation', 'before' => 12, 'tried' => ['after' => 8, 'deviation_mm' => 0.3]];
        });
        $d = new MeshDecimator($python, ['decimate_above' => 10, 'decimate_target' => 8, 'max_deviation_mm' => 0.05, 'decimate_timeout' => 240]);
        $r = $d->decimate($this->dir.'/cube.stl', $this->dir.'/thin.stl');
        $this->assertFalse($r['applied']);
        $this->assertSame('deviation', $r['reason']);
        $this->assertFileDoesNotExist($this->dir.'/thin.stl', 'nothing half-written is left for the slicer');

        $broken = $this->runner(fn () => throw new \RuntimeException('python gone'));
        $r = (new MeshDecimator($broken, ['decimate_above' => 10, 'decimate_target' => 8, 'max_deviation_mm' => 0.05, 'decimate_timeout' => 240]))->decimate($this->dir.'/cube.stl', $this->dir.'/thin.stl');
        $this->assertSame(['applied' => false, 'reason' => 'error'], ['applied' => $r['applied'], 'reason' => $r['reason']]);
    }

    public function test_the_script_thins_a_fine_sphere_and_keeps_its_shape(): void
    {
        $python = config('engines.python.bin') ?: (PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3');
        $probe = shell_exec(escapeshellcmd($python).' -c "import trimesh, fast_simplification, numpy; print(1)" 2>&1');
        if (trim((string) $probe) !== '1') {
            $this->markTestSkipped('Python with trimesh and fast_simplification is not installed.');
        }
        // an icosphere of 327 680 triangles, radius 30 mm
        shell_exec(escapeshellcmd($python).' -c "import trimesh; trimesh.creation.icosphere(subdivisions=7, radius=30).export(r\''.$this->dir.'/sphere.stl\')"');
        $this->assertSame(327680, StlFile::triangleCount($this->dir.'/sphere.stl'));
        $out = shell_exec(escapeshellcmd($python).' '.escapeshellarg(base_path('engines/python/decimate_tool.py')).' '.escapeshellarg($this->dir.'/sphere.stl').' '.escapeshellarg($this->dir.'/thin.stl').' 40000 0.05 0.15 5000');
        $r = json_decode((string) $out, true);
        $this->assertTrue($r['ok'] ?? false, (string) $out);
        $this->assertLessThanOrEqual(40000 * 1.05, $r['after']);
        $this->assertLessThanOrEqual(0.05, $r['deviation_mm']);
        $this->assertLessThanOrEqual(0.15, $r['peak_mm']);
        $this->assertTrue($r['watertight']);
        $this->assertSame($r['after'], StlFile::triangleCount($this->dir.'/thin.stl'));
    }
}
