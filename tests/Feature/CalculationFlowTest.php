<?php

namespace Tests\Feature;

use App\Models\AnonymousSession;
use App\Models\Calculation;
use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/** Upload → process → calculation → precise result, with the fake slicer and a sync queue. */
class CalculationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
    }

    private function cube(string $ext = 'stl'): UploadedFile
    {
        $path = sys_get_temp_dir().'/mp_cube_'.uniqid().'.'.$ext;
        $ext === '3mf' ? MeshFixtures::cube3mf($path, 10) : MeshFixtures::cubeStl($path, 20);

        return new UploadedFile($path, 'cube.'.$ext, null, null, true);
    }

    public function test_home_renders_without_account(): void
    {
        $r = $this->get('/');
        $r->assertOk()->assertSee('matplace')->assertCookie(AnonymousSession::COOKIE);
        $this->assertSame(1, AnonymousSession::count());
    }

    public function test_upload_and_precise_calculation(): void
    {
        $up = $this->postJson('/api/uploads', ['file' => $this->cube()]);
        $up->assertCreated();
        $uuid = $up->json('file.uuid');

        $file = ModelFile::where('uuid', $uuid)->firstOrFail();
        $this->assertSame(ModelFile::STATUS_READY, $file->status);       // sync queue processed it
        $this->assertEqualsWithDelta(8000.0, $file->volume_mm3, 0.01);
        $this->assertNotNull($file->stl_path);
        $this->assertNotNull($file->anonymous_session_id);

        $calc = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA', 'quality' => 'standard', 'infill' => 15, 'quantity' => 2]);
        $calc->assertCreated();
        $token = $calc->json('calculation.token');
        $this->assertSame('done', $calc->json('calculation.status'));
        $this->assertSame('fake', Calculation::where('token', $token)->value('slicer_engine'));
        $this->assertGreaterThan(0, $calc->json('calculation.slicer.grams'));
        $this->assertCount(3, $calc->json('calculation.prices'));
        $this->assertSame(2, $calc->json('calculation.prices.0.quantity'));
        $this->assertNotNull($calc->json('calculation.rough.price_min'));

        // share page and public API work without any cookie
        $this->flushSession();
        $this->get('/c/'.$token)->assertOk()->assertSee('cube.stl');
        $this->getJson('/api/calculations/'.$token)->assertOk()->assertJsonPath('calculation.status', 'done');
        $this->get('/api/files/'.$uuid.'/model.stl')->assertOk()->assertHeader('Content-Type', 'model/stl');
    }

    public function test_same_params_reuse_cached_slice(): void
    {
        $uuid = $this->postJson('/api/uploads', ['file' => $this->cube()])->json('file.uuid');
        $a = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA'])->json('calculation');
        $b = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA'])->json('calculation');
        $this->assertNotSame($a['token'], $b['token']);
        $this->assertSame($a['slicer']['grams'], $b['slicer']['grams']);
        $this->assertSame(2, Calculation::count());
    }

    public function test_3mf_is_converted_to_stl(): void
    {
        $up = $this->postJson('/api/uploads', ['file' => $this->cube('3mf')]);
        $up->assertCreated();
        $file = ModelFile::where('uuid', $up->json('file.uuid'))->firstOrFail();
        $this->assertSame(ModelFile::STATUS_READY, $file->status);
        $this->assertEqualsWithDelta(8000.0, $file->volume_mm3, 0.01); // 10 mm cube × build transform 2
    }

    public function test_non_sliceable_material_is_estimate_only(): void
    {
        $uuid = $this->postJson('/api/uploads', ['file' => $this->cube()])->json('file.uuid');
        $c = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'RESIN'])->json('calculation');
        $this->assertSame('done', $c['status']);
        $this->assertNull($c['slicer']);
        $this->assertNotNull($c['rough']['price_min']);
    }

    public function test_rejects_unknown_format_and_bad_scale(): void
    {
        $path = sys_get_temp_dir().'/mp_'.uniqid().'.exe';
        file_put_contents($path, 'x');
        $this->postJson('/api/uploads', ['file' => new UploadedFile($path, 'x.exe', null, null, true)])->assertStatus(422);

        $uuid = $this->postJson('/api/uploads', ['file' => $this->cube()])->json('file.uuid');
        $this->postJson('/api/calculations', ['file' => $uuid, 'scale' => 99])->assertStatus(422);
    }
}
