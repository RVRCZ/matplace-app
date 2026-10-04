<?php

namespace Tests\Feature;

use App\Domain\Calculation\RoughEstimator;
use App\Engines\Contracts\Slicer;
use App\Engines\DTO\Dimensions;
use App\Engines\DTO\SliceParams;
use App\Engines\DTO\SliceResult;
use App\Engines\Slicer\CachedSlicer;
use App\Engines\Slicer\FakeSlicer;
use App\Models\Calculation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/** The same mesh, parameters, profiles and slicer are sliced once; the result is handed out again as it was. */
class SliceCacheTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/mp_slicecache_'.uniqid();
        File::ensureDirectoryExists($this->dir.'/profiles');
        File::put($this->dir.'/profiles/process_standard.json', '{"layer_height":"0.2"}');
        config(['engines.orca.work_dir' => $this->dir.'/work']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function counting(): Slicer
    {
        return new class(new FakeSlicer(app(RoughEstimator::class))) implements Slicer
        {
            public int $calls = 0;

            public function __construct(private readonly FakeSlicer $fake) {}

            public function slice(string $meshPath, SliceParams $params): SliceResult
            {
                $this->calls++;

                return $this->fake->slice($meshPath, $params)->withTimings(['slice1_s' => 12.5, 'double' => true]);
            }

            public function measure(string $meshPath): Dimensions
            {
                return $this->fake->measure($meshPath);
            }

            public function supportedFormats(): array
            {
                return ['stl'];
            }

            public function name(): string
            {
                return 'counting';
            }
        };
    }

    public function test_the_same_input_is_sliced_once_and_handed_out_the_same(): void
    {
        $inner = $this->counting();
        $cached = new CachedSlicer($inner, $this->dir.'/cache', $this->dir.'/work/gcode', [$this->dir.'/profiles'], null);
        $mesh = $this->dir.'/a.stl';
        MeshFixtures::cubeStl($mesh, 20);
        $farm = (new SliceParams('PLA', 'fine', 20, null, treeSupports: true))->withFarmProfile(['machine' => 'machine_x.json'], ['process' => ['layer_height' => '0.12']]);

        $a = $cached->slice($mesh, $farm);
        $b = $cached->slice($mesh, $farm);
        $this->assertSame(1, $inner->calls);
        $this->assertFalse($a->timings['slice_cached']);
        $this->assertTrue($b->timings['slice_cached']);
        $this->assertSame(12.5, $b->timings['made_slice1_s'], 'a hit still says what the slice it copies took');
        $this->assertNotSame($a->gcodePath, $b->gcodePath, 'every caller gets a G-code of its own');
        $this->assertFileEquals($a->gcodePath, $b->gcodePath);
        $this->assertSame(array_diff_key($a->toArray(), ['gcode_path' => 1]), array_diff_key($b->toArray(), ['gcode_path' => 1]));
        $this->assertSame([$a->dims->x, $a->dims->y, $a->dims->z, $a->grams, $a->meters], [$b->dims->x, $b->dims->y, $b->dims->z, $b->grams, $b->meters]);

        // anything the G-code depends on is another slice
        $cached->slice($mesh, (new SliceParams('PLA', 'fine', 25, null, treeSupports: true))->withFarmProfile(['machine' => 'machine_x.json'], ['process' => ['layer_height' => '0.12']]));
        $this->assertSame(2, $inner->calls, 'another infill');
        $cached->slice($mesh, (new SliceParams('PLA', 'fine', 20, null, treeSupports: true))->withFarmProfile(['machine' => 'machine_x.json'], ['process' => ['layer_height' => '0.16']]));
        $this->assertSame(3, $inner->calls, 'another override');
        File::put($this->dir.'/profiles/process_standard.json', '{"layer_height":"0.24"}');
        $cached->slice($mesh, $farm);
        $this->assertSame(4, $inner->calls, 'a profile file changed');
        MeshFixtures::cubeStl($mesh, 21);
        $cached->slice($mesh, $farm);
        $this->assertSame(5, $inner->calls, 'another mesh');

        // the stored G-code was pruned: sliced again rather than handed out without it
        $cached->slice($mesh, $farm);
        $this->assertSame(5, $inner->calls);
        foreach (File::glob($this->dir.'/cache/*.gcode') as $g) {
            @unlink($g);
        }
        $cached->slice($mesh, $farm);
        $this->assertSame(6, $inner->calls);
    }

    public function test_a_calculation_for_another_quantity_reuses_the_slice(): void
    {
        Storage::fake('models');
        $path = sys_get_temp_dir().'/mp_qty_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);
        $uuid = $this->postJson('/api/uploads', ['file' => new UploadedFile($path, 'cube.stl', null, null, true)])->json('file.uuid');
        $one = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA', 'quantity' => 1])->json('calculation');
        $five = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA', 'quantity' => 5])->json('calculation');
        $this->assertSame(['cache' => 'calculation'], Calculation::where('token', $five['token'])->first()->timings, 'the quantity is not a parameter of the slice');
        $this->assertSame($one['slicer']['grams'], $five['slicer']['grams']);
        $this->assertSame(5, $five['prices'][0]['quantity']);

        $other = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA', 'quantity' => 5, 'infill' => 40])->json('calculation');
        $this->assertNull(Calculation::where('token', $other['token'])->first()->timings['cache'] ?? null, 'another infill is sliced');
        $off = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA', 'supports' => false])->json('calculation');
        $this->assertNull(Calculation::where('token', $off['token'])->first()->timings['cache'] ?? null, 'supports off is not supports on auto');
    }
}
