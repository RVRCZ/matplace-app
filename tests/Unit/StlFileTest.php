<?php

namespace Tests\Unit;

use App\Engines\DTO\Dimensions;
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

    public function test_placed_copies_carry_unit_normals_so_the_slicer_takes_them_for_binary(): void
    {
        // admesh (OrcaSlicer) calls a file ASCII when no byte of the 128 after the header is above 127: zero normals
        // and round coordinates (a 40 mm cube placed around 210 mm, 13 Oct 2026) were read as text and refused
        $in = sys_get_temp_dir().'/mp_cube_in_'.uniqid().'.stl';
        $out = sys_get_temp_dir().'/mp_cube_out_'.uniqid().'.stl';
        StlFile::writeBox($in, 40, 40, 40);
        $size = StlFile::place($in, $out, 1.0, 210, 210);
        $this->assertEqualsWithDelta([40.0, 40.0, 40.0], $size, 1e-6);
        $bytes = (string) file_get_contents($out);
        $this->assertSame(84 + 12 * 50, strlen($bytes));
        $high = 0;
        foreach (str_split(substr($bytes, 80, 128)) as $ch) {
            if (ord($ch) > 127) {
                $high++;
            }
        }
        $this->assertGreaterThan(0, $high, 'a byte above 127 among the 128 after the header, or the slicer reads the file as text');
        // the first triangle's normal is a unit vector
        $n = array_values(unpack('f3', substr($bytes, 84, 12)));
        $this->assertEqualsWithDelta(1.0, sqrt($n[0] ** 2 + $n[1] ** 2 + $n[2] ** 2), 1e-5);
        [$lo, $hi] = StlFile::bounds($out);
        $this->assertEqualsWithDelta([190.0, 190.0, 0.0], $lo, 1e-4);
        $this->assertEqualsWithDelta([230.0, 230.0, 40.0], $hi, 1e-4);
        // a degenerate triangle gets no normal, nothing divides by zero
        $this->assertSame([0.0, 0.0, 0.0], StlFile::normal([0, 0, 0], [1, 1, 1], [2, 2, 2]));
        @unlink($in);
        @unlink($out);
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
        $d = new Dimensions(300, 20, 20);
        $this->assertTrue($d->fits(250, 250, 300));
        $this->assertFalse($d->fits(250, 250, 250));
    }
}
