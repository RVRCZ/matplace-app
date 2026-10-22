<?php

namespace Tests\Feature;

use App\Models\FarmPrinter;
use App\Models\ModelFile;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * Very fine meshes (docs/AL.md §10.11): the pages say a big model takes minutes, and a model beyond what thinning can
 * help is refused on upload with the advice to thin it at home – on the calculator and on the farm alike.
 */
final class BigMeshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
    }

    private function cube(): UploadedFile
    {
        $path = sys_get_temp_dir().'/mp_big_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);

        return new UploadedFile($path, 'cube.stl', null, null, true);
    }

    public function test_a_model_of_many_triangles_is_announced_as_big_on_the_calculator_and_the_order_page(): void
    {
        config(['engines.mesh.decimate_above' => 5]);   // a cube has 12
        $user = User::factory()->create();
        $up = $this->actingAs($user)->postJson('/api/uploads', ['file' => $this->cube()])->assertCreated();
        $this->assertTrue($up->json('file.big_model'));
        $this->assertSame(12, $up->json('file.triangles'));
        $calc = $this->postJson('/api/calculations', ['file' => $up->json('file.uuid'), 'material' => 'PLA'])->assertCreated();
        $this->assertTrue($calc->json('calculation.file.big_model'));

        $this->seed(FarmSeeder::class);
        FarmPrinter::query()->update(['last_seen_at' => now()]);
        $r = $this->actingAs($user)->postJson('/farm/orders', ['file' => $up->json('file.uuid')])->assertCreated();
        $state = $this->actingAs($user)->getJson(str_replace(url('/'), '', $r->json('url')).'/status')->assertOk()->json();
        $this->assertSame(__('farm.order.big_model', ['n' => '0,0']), $state['big_model'], 'twelve triangles are 0.0 million, said in the page\'s language');

        config(['engines.mesh.decimate_above' => 400000]);
        $this->assertNull($this->actingAs($user)->getJson(str_replace(url('/'), '', $r->json('url')).'/status')->assertOk()->json('big_model'));
    }

    public function test_a_model_beyond_the_limit_is_refused_on_upload_with_the_advice_to_thin_it(): void
    {
        config(['engines.mesh.max_triangles' => 10]);   // a cube has 12
        $user = User::factory()->create();
        $up = $this->actingAs($user)->postJson('/api/uploads', ['file' => $this->cube()])->assertCreated();
        $file = ModelFile::where('uuid', $up->json('file.uuid'))->firstOrFail();
        $this->assertSame(ModelFile::STATUS_FAILED, $file->status);
        $this->assertSame('too_many_triangles', $file->error);
        $this->assertNull($file->stl_path, 'nothing of it is kept for slicing');
        // the calculator: the file's error is what the page reads (calc.status.too_many_triangles)
        $calc = $this->postJson('/api/calculations', ['file' => $file->uuid, 'material' => 'PLA'])->assertCreated();
        $this->assertSame('failed', $calc->json('calculation.status'));
        $this->assertSame('too_many_triangles', $calc->json('calculation.file.error'));
        // the farm: refused with the same advice, not "not ready"
        $this->seed(FarmSeeder::class);
        $this->actingAs($user)->postJson('/farm/orders', ['file' => $file->uuid])->assertStatus(422)->assertJsonPath('error', 'too_many_triangles')
            ->assertJsonPath('message', __('farm.refuse.too_many_triangles', ['max' => '0']));
    }
}
