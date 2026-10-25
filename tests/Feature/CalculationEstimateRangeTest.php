<?php

namespace Tests\Feature;

use App\Models\Calculation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * The first page of the calculator (TODO #41, docs/M.md §6): the estimate carries its range by shape, a big model gets
 * the estimate and no slice, and the threshold counts triangles or the last slice's time.
 */
class CalculationEstimateRangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
    }

    private function upload(): string
    {
        $path = sys_get_temp_dir().'/mp_cube_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);

        return $this->postJson('/api/uploads', ['file' => new UploadedFile($path, 'cube.stl', null, null, true)])->assertCreated()->json('file.uuid');
    }

    public function test_the_estimate_carries_its_range_by_shape(): void
    {
        $uuid = $this->upload();
        $c = $this->postJson('/api/calculations', [
            'file' => $uuid, 'material' => 'PLA',
            'geometry' => ['volume_mm3' => 8000, 'area_mm2' => 2400, 'bbox' => ['x' => 20, 'y' => 20, 'z' => 20], 'overhang_share' => 0.1],
        ])->assertCreated()->json('calculation');
        $rough = $c['rough'];
        $this->assertGreaterThanOrEqual($rough['minutes_min'], $rough['minutes']);
        $this->assertLessThanOrEqual($rough['minutes_max'], $rough['minutes']);
        $this->assertLessThan($rough['minutes_max'], $rough['minutes_min']);
        $this->assertEqualsWithDelta(20.0, $rough['shape']['height_mm'], 1e-9);                        // the file's own box, measured on upload
        $this->assertEqualsWithDelta(3.5 / 8, $rough['shape']['fill_g_cm3'], 0.01);  // 3.5 g in 8 cm³
        $this->assertSame(0.1, $rough['shape']['overhang_share']);                    // what the browser measured
        $this->assertSame(['time', 'price'], array_keys($rough['range']));
        $this->assertLessThan(1.0, $rough['range']['price'][0]);
        $this->assertGreaterThan(1.0, $rough['range']['price'][1]);
        // the exact price (the fake slicer lands next to the estimate) sits inside the range shown before it
        $this->assertSame('done', $c['status']);
        foreach ($c['prices'] as $p) {
            $this->assertGreaterThanOrEqual($rough['price_min'], $p['total']);
            $this->assertLessThanOrEqual($rough['price_max'], $p['total']);
        }
    }

    public function test_millions_of_triangles_make_a_model_big_and_the_estimate_is_its_answer(): void
    {
        config(['engines.mesh.decimate_above' => 1]);   // a twelve-triangle cube counts as big
        $uuid = $this->upload();
        $this->getJson('/api/files/'.$uuid)->assertOk()->assertJsonPath('file.big_model', true);
        $c = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA', 'rough_only' => 1])->assertCreated()->json('calculation');
        $this->assertSame('rough', $c['status']);
        $this->assertNull($c['slicer']);
        $this->assertNull($c['prices']);
        $this->assertNotNull($c['rough']['price_min']);
        $this->assertNotNull($c['rough']['minutes_max']);
        $this->assertNull(Calculation::where('token', $c['token'])->value('slicer_engine'));
        // the share link and the API say the same, nothing is waiting in a queue
        $this->getJson('/api/calculations/'.$c['token'])->assertOk()->assertJsonPath('calculation.status', 'rough');
        $this->get('/c/'.$c['token'])->assertOk()->assertSee('cube.stl');
    }

    public function test_a_slow_last_slice_makes_the_model_big_and_a_finished_slice_still_answers(): void
    {
        $uuid = $this->upload();
        $this->getJson('/api/files/'.$uuid)->assertJsonPath('file.big_model', false);
        $first = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA'])->json('calculation');
        $this->assertSame('done', $first['status']);
        Calculation::where('token', $first['token'])->update(['timings' => json_encode(['slice_s' => 70.5])]);
        $this->getJson('/api/files/'.$uuid)->assertJsonPath('file.big_model', true);
        // the same settings: the finished slice is reused and the answer is exact
        $same = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA', 'rough_only' => 1])->json('calculation');
        $this->assertSame('done', $same['status']);
        // other settings: the estimate alone, nothing queued
        $other = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA', 'quality' => 'fine', 'rough_only' => 1])->json('calculation');
        $this->assertSame('rough', $other['status']);
        $this->assertNull($other['slicer']);
        // the rule switched off
        config(['engines.mesh.big_slice_s' => 0]);
        $this->getJson('/api/files/'.$uuid)->assertJsonPath('file.big_model', false);
    }

    public function test_the_browser_gets_the_range_constants(): void
    {
        $this->getJson(route('api.config'))->assertOk()
            ->assertJsonPath('rough.range.time.high', 1.25)
            ->assertJsonPath('rough.range.price.supports', 1.4)
            ->assertJsonPath('rough.range.height_mm', [15, 100]);
    }
}
