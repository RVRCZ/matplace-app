<?php

namespace Tests\Feature;

use App\Domain\Calculation\CalculationService;
use App\Models\Calculation;
use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * A design of several parts larger than the plate (a filament picture of five plates and a frame, 232 × 551 mm) is not
 * sliced as one piece on the first page: the slicer would refuse it, the farm prints it by parts on the order page, so
 * the estimate stays and the page says why (docs/AN.md §17.2, variant b).
 */
final class ByPartsCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        config(['pricing.bed_mm' => ['x' => 250, 'y' => 250, 'z' => 250]]);
    }

    /** A processed design on the models disk (a cube stands in for the shape; the box says how big the design is). */
    private function design(array $bbox, array $parts): ModelFile
    {
        $uuid = (string) Str::uuid();
        $tmp = sys_get_temp_dir().'/mp_cube_'.uniqid().'.stl';
        MeshFixtures::cubeStl($tmp, 20);
        Storage::disk('models')->put("files/{$uuid}/original.stl", (string) file_get_contents($tmp));
        Storage::disk('models')->put("files/{$uuid}/model.stl", (string) file_get_contents($tmp));
        @unlink($tmp);

        return ModelFile::factory()->create([
            'uuid' => $uuid, 'storage_path' => "files/{$uuid}/original.stl", 'stl_path' => "files/{$uuid}/model.stl",
            'origin' => 'tool', 'origin_ref' => 'filament_art', 'tool_params' => ['parts' => $parts], 'bbox' => $bbox,
            'volume_mm3' => 60000, 'area_mm2' => 30000,
        ]);
    }

    public function test_a_design_of_parts_larger_than_the_plate_keeps_the_estimate_and_says_it_is_printed_by_parts(): void
    {
        $picture = $this->design(['x' => 232, 'y' => 551, 'z' => 25], ['plate_1', 'plate_2', 'plate_3', 'plate_4', 'plate_5', 'frame']);
        $this->assertSame(['plate_1', 'plate_2', 'plate_3', 'plate_4', 'plate_5', 'frame'], $picture->parts());
        $this->assertTrue(CalculationService::printedByParts($picture));
        $this->assertFalse(CalculationService::printedByParts($picture, 0.4), 'scaled down it fits the plate');

        $payload = ['material' => 'PLA', 'quality' => 'standard', 'infill' => 15, 'quantity' => 1];
        $r = $this->postJson('/api/calculations', ['file' => $picture->uuid] + $payload)->assertCreated();
        $r->assertJsonPath('calculation.status', Calculation::STATUS_ROUGH)->assertJsonPath('calculation.rough.by_parts', true);
        $this->assertNotNull($r->json('calculation.rough.grams'), 'the estimate is there');
        $token = $r->json('calculation.token');
        // nothing was queued: the answer stays the estimate however often the page asks
        $this->getJson('/api/calculations/'.$token)->assertOk()->assertJsonPath('calculation.status', Calculation::STATUS_ROUGH)->assertJsonPath('calculation.rough.by_parts', true);
        $this->assertSame(Calculation::STATUS_ROUGH, Calculation::where('token', $token)->value('status'));
        // the page's words for it travel with the calculator's texts
        $this->assertStringContainsString('po dílech', $this->calcConfig('/', ['Accept-Language' => 'cs'])['i18n']['calc.status.by_parts']);

        // the same design asked for as an estimate only (a big model's way) says the same
        $this->postJson('/api/calculations', ['file' => $picture->uuid, 'rough_only' => 1] + $payload)->assertCreated()->assertJsonPath('calculation.rough.by_parts', true);

        // a design of parts that fits the plate is sliced as before (the fake slicer answers at once)
        $small = $this->design(['x' => 120, 'y' => 90, 'z' => 25], ['plate_1', 'plate_2', 'frame']);
        $this->assertFalse(CalculationService::printedByParts($small));
        $done = $this->postJson('/api/calculations', ['file' => $small->uuid] + $payload)->assertCreated();
        $done->assertJsonPath('calculation.status', Calculation::STATUS_DONE);
        $this->assertArrayNotHasKey('by_parts', (array) $done->json('calculation.rough'));

        // one piece larger than the plate has no parts to print it by: sliced as before (the slicer, not this rule, says what it can do)
        $single = $this->design(['x' => 300, 'y' => 100, 'z' => 25], []);
        $this->assertFalse(CalculationService::printedByParts($single));
        $this->postJson('/api/calculations', ['file' => $single->uuid] + $payload)->assertCreated()->assertJsonPath('calculation.status', Calculation::STATUS_DONE);
    }
}
