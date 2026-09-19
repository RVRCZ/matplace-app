<?php

namespace Tests\Unit;

use App\Engines\Mesh\StlFile;
use PHPUnit\Framework\TestCase;
use Tests\Support\MeshFixtures;

class StlFileTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/mp_stl_'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        rmdir($this->dir);
    }

    public function test_binary_cube_stats(): void
    {
        MeshFixtures::cubeStl($this->dir.'/cube.stl', 20);
        $r = StlFile::stats($this->dir.'/cube.stl');

        $this->assertSame(12, $r->triangles);
        $this->assertEqualsWithDelta(8000.0, $r->volumeMm3, 0.01);
        $this->assertEqualsWithDelta(2400.0, $r->areaMm2, 0.01);
        $this->assertEqualsWithDelta(20.0, $r->bbox->x, 0.001);
        $this->assertFalse($r->flippedNormals);
        $this->assertSame([], $r->issues);
    }

    public function test_ascii_cube_gives_same_numbers(): void
    {
        MeshFixtures::cubeStlAscii($this->dir.'/cube_ascii.stl', 20);
        $this->assertFalse(StlFile::isBinary($this->dir.'/cube_ascii.stl'));
        $r = StlFile::stats($this->dir.'/cube_ascii.stl');

        $this->assertSame(12, $r->triangles);
        $this->assertEqualsWithDelta(8000.0, $r->volumeMm3, 0.01);
    }

    public function test_scale_writes_scaled_copy(): void
    {
        MeshFixtures::cubeStl($this->dir.'/cube.stl', 10);
        $n = StlFile::scale($this->dir.'/cube.stl', $this->dir.'/big.stl', 2.0);
        $r = StlFile::stats($this->dir.'/big.stl');

        $this->assertSame(12, $n);
        $this->assertEqualsWithDelta(8000.0, $r->volumeMm3, 0.01);
        $this->assertEqualsWithDelta(20.0, $r->bbox->z, 0.001);
    }

    public function test_dimensions_fit_allows_rotation(): void
    {
        $d = new \App\Engines\DTO\Dimensions(300, 20, 20);
        $this->assertTrue($d->fits(250, 250, 300));
        $this->assertFalse($d->fits(250, 250, 250));
    }
}
