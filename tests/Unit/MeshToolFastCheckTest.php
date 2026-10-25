<?php

namespace Tests\Unit;

use App\Engines\Mesh\StlFile;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * The check after an upload measures a big binary STL with numpy alone (mesh_tool.py `check <in> [fast_above]`,
 * docs/AL.md §11): the same figures as the trimesh path, in a fraction of the time. Runs only where the engine's
 * Python with numpy and trimesh is installed, like the decimation test.
 */
final class MeshToolFastCheckTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/mp_fastcheck_'.uniqid();
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

    private function python(): string
    {
        $python = config('engines.python.bin') ?: (PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3');
        $probe = shell_exec(escapeshellcmd($python).' -c "import trimesh, numpy, scipy; print(1)" 2>&1');
        if (trim((string) $probe) !== '1') {
            $this->markTestSkipped('Python with trimesh, numpy and scipy is not installed.');
        }

        return $python;
    }

    /** @return array<string, mixed> */
    private function check(string $python, string $path, ?int $above): array
    {
        $out = shell_exec(escapeshellcmd($python).' '.escapeshellarg(base_path('engines/python/mesh_tool.py')).' check '.escapeshellarg($path).($above === null ? '' : ' '.$above));
        $r = json_decode((string) $out, true);
        $this->assertIsArray($r, (string) $out);
        $this->assertTrue($r['ok'] ?? false, (string) $out);

        return $r;
    }

    public function test_the_numpy_check_of_a_binary_stl_agrees_with_trimesh_and_is_only_taken_above_the_threshold(): void
    {
        $python = $this->python();
        MeshFixtures::cubeStl($this->dir.'/cube.stl', 20);
        $slow = $this->check($python, $this->dir.'/cube.stl', null);     // twelve triangles: the ordinary path
        $this->assertArrayNotHasKey('fast', $slow);
        $fast = $this->check($python, $this->dir.'/cube.stl', 1);
        $this->assertTrue($fast['fast']);
        foreach (['watertight', 'triangles', 'shells', 'flipped_normals', 'issues'] as $key) {
            $this->assertSame($slow[$key], $fast[$key], $key);
        }
        $this->assertTrue($fast['watertight']);
        $this->assertEqualsWithDelta(8000.0, $fast['volume_mm3'], 0.01);
        $this->assertEqualsWithDelta(2400.0, $fast['area_mm2'], 0.01);
        $this->assertEqualsWithDelta($slow['volume_mm3'], $fast['volume_mm3'], 0.01);
        $this->assertEqualsWithDelta($slow['area_mm2'], $fast['area_mm2'], 0.01);
        $this->assertSame([20.0, 20.0, 20.0], array_map(fn ($v) => round($v, 3), array_values($fast['bbox'])));

        // two bodies, one of them open: counted and said the way trimesh says it
        MeshFixtures::cubeStl($this->dir.'/a.stl', 10);
        $open = $this->dir.'/open.stl';
        $fh = StlFile::beginBinary($open);
        $n = 0;
        foreach (StlFile::triangles($this->dir.'/a.stl') as $i => $t) {
            if ($i === 0) {
                continue;   // one face missing: a hole
            }
            StlFile::writeTriangle($fh, $t[0], $t[1], $t[2]);
            $n++;
        }
        StlFile::endBinary($fh, $n);
        StlFile::translate($this->dir.'/a.stl', $this->dir.'/b.stl', [30, 0, 0]);
        StlFile::concat([$open, $this->dir.'/b.stl'], $this->dir.'/two.stl');
        $slow = $this->check($python, $this->dir.'/two.stl', null);
        $fast = $this->check($python, $this->dir.'/two.stl', 1);
        $this->assertFalse($fast['watertight']);
        $this->assertSame(2, $fast['shells']);
        $this->assertSame(['multiple_shells', 'not_watertight'], $fast['issues']);
        $this->assertSame($slow['issues'], $fast['issues']);
        $this->assertSame($slow['shells'], $fast['shells']);
        $this->assertSame(23, $fast['triangles']);

        // an ASCII file has no header to read: the ordinary path, whatever the threshold
        MeshFixtures::cubeStlAscii($this->dir.'/ascii.stl', 20);
        $this->assertArrayNotHasKey('fast', $this->check($python, $this->dir.'/ascii.stl', 1));
    }
}
