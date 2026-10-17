<?php

namespace Tests\Feature;

use App\Domain\Farm\Footprint;
use App\Domain\Farm\PlateLayout;
use App\Engines\DTO\Dimensions;
use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Unit\PlateLayoutTest;

/** POST /api/calc/plate: the calculator's "N pieces fit on one plate" by the model's shadow, as the farm lays it. */
class PlateEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const TRIANGLE = [[0, 0], [40, 0], [20, 60]];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        config(['farm.enabled' => false, 'pricing.bed_mm' => ['x' => 250, 'y' => 250, 'z' => 250]]);   // no edge kept: the plate is 250 × 250
    }

    private function triangleFile(string $origin = 'tool'): ModelFile
    {
        $uuid = (string) Str::uuid();
        Storage::disk('models')->makeDirectory('files/'.$uuid);
        PlateLayoutTest::prism(Storage::disk('models')->path('files/'.$uuid.'/model.stl'), self::TRIANGLE, 3.0);

        return ModelFile::create(['uuid' => $uuid, 'original_name' => 'sign.stl', 'ext' => 'stl', 'mime' => 'model/stl', 'size_bytes' => 1,
            'sha256' => str_repeat('b', 64), 'storage_path' => 'files/'.$uuid.'/model.stl', 'stl_path' => 'files/'.$uuid.'/model.stl',
            'origin' => $origin, 'status' => ModelFile::STATUS_READY, 'bbox' => ['x' => 40, 'y' => 60, 'z' => 3]]);
    }

    public function test_a_triangular_sign_fits_more_than_its_rectangles_and_the_answer_is_kept(): void
    {
        $file = $this->triangleFile();
        $usable = new Dimensions(250, 250, 250);
        $expected = PlateLayout::plan(Footprint::fromPoints(self::TRIANGLE), new Dimensions(40, 60, 3), $usable)['max'];
        $grid = PlateLayout::capacity(new Dimensions(40, 60, 3), $usable)['max'];
        $this->assertGreaterThan($grid, $expected);

        $first = $this->postJson('/api/calc/plate', ['file' => $file->uuid])->assertOk()
            ->assertJsonPath('max', $expected)->assertJsonPath('perPlate', 1)->assertJsonPath('plates', 1)->assertJsonPath('layout.method', 'rows')->json();

        // more than a plate: full plates, the count per plate is what fits
        $this->postJson('/api/calc/plate', ['file' => $file->uuid, 'quantity' => $expected + 1])->assertOk()
            ->assertJsonPath('perPlate', $expected)->assertJsonPath('plates', 2)->assertJsonCount($expected, 'layout.placements');

        // half the size: the shadow scales with it (no new reading of the file: it is gone, the shadow is kept)
        Storage::disk('models')->delete($file->stl_path);
        $this->assertSame($first, $this->postJson('/api/calc/plate', ['file' => $file->uuid])->assertOk()->json());
        $half = PlateLayout::plan(Footprint::fromPoints(self::TRIANGLE)->scaled(0.5), new Dimensions(20, 30, 1.5), $usable)['max'];
        $this->postJson('/api/calc/plate', ['file' => $file->uuid, 'scale' => 0.5])->assertOk()->assertJsonPath('max', $half);
    }

    public function test_a_box_keeps_the_rectangles_count(): void
    {
        $this->postJson('/api/calc/plate', ['dims' => ['x' => 40, 'y' => 40, 'z' => 40], 'footprint' => [[0, 0], [40, 0], [40, 40], [0, 40]]])->assertOk()
            ->assertJsonPath('max', 25)->assertJsonPath('layout.method', 'grid');   // (250 + 5) / 45 = 5 a side
        $this->postJson('/api/calc/plate', ['dims' => ['x' => 40, 'y' => 40, 'z' => 400]])->assertOk()->assertJsonPath('max', 0)->assertJsonPath('plates', 0);
    }

    public function test_unknown_unready_and_withheld_files_are_refused(): void
    {
        $this->postJson('/api/calc/plate', ['file' => (string) Str::uuid()])->assertNotFound();
        $this->postJson('/api/calc/plate', [])->assertUnprocessable();

        $waiting = $this->triangleFile();
        $waiting->update(['status' => ModelFile::STATUS_UPLOADED]);
        $this->postJson('/api/calc/plate', ['file' => $waiting->uuid])->assertNotFound();

        // a designer's model that is not offered for download is not measured for strangers either
        $portfolio = $this->triangleFile('portfolio');
        $this->postJson('/api/calc/plate', ['file' => $portfolio->uuid])->assertForbidden();
    }
}
