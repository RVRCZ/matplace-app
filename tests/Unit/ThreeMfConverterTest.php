<?php

namespace Tests\Unit;

use App\Engines\Converter\ThreeMfConverter;
use App\Engines\DTO\ConvertOptions;
use App\Engines\Exceptions\ConversionException;
use App\Engines\Mesh\StlFile;
use PHPUnit\Framework\TestCase;
use Tests\Support\MeshFixtures;

class ThreeMfConverterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/mp_3mf_'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        rmdir($this->dir);
    }

    public function test_applies_build_transform(): void
    {
        MeshFixtures::cube3mf($this->dir.'/cube.3mf', 10, '2 0 0 0 2 0 0 0 2 10 10 10');
        $c = new ThreeMfConverter;
        $this->assertTrue($c->supports('3mf', 'stl'));
        $r = $c->convert($this->dir.'/cube.3mf', 'stl', $this->dir.'/cube.stl', new ConvertOptions);

        $this->assertSame(12, $r->triangles);
        $stats = StlFile::stats($this->dir.'/cube.stl');
        $this->assertEqualsWithDelta(8000.0, $stats->volumeMm3, 0.01); // 10 mm cube scaled 2× = 20 mm
        $this->assertEqualsWithDelta(20.0, $stats->bbox->x, 0.001);
    }

    public function test_without_transform_keeps_size(): void
    {
        MeshFixtures::cube3mf($this->dir.'/cube.3mf', 10, null);
        (new ThreeMfConverter)->convert($this->dir.'/cube.3mf', 'stl', $this->dir.'/cube.stl', new ConvertOptions);
        $this->assertEqualsWithDelta(1000.0, StlFile::stats($this->dir.'/cube.stl')->volumeMm3, 0.01);
    }

    public function test_rejects_non_zip(): void
    {
        file_put_contents($this->dir.'/bad.3mf', 'nope');
        $this->expectException(ConversionException::class);
        (new ThreeMfConverter)->convert($this->dir.'/bad.3mf', 'stl', $this->dir.'/out.stl', new ConvertOptions);
    }
}
