<?php

namespace Tests\Feature;

use App\Engines\Contracts\PrintPreparer;
use App\Engines\DTO\Dimensions;
use App\Engines\DTO\PreparedMesh;
use App\Engines\Farm\CachedPrintPreparer;
use App\Models\FarmOrder;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * A recalculation (another quality, strength, colour) slices again but never repairs and turns the model again;
 * while the customer waits, the order says which step is running.
 */
class FarmPreparedCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_same_model_scale_and_plate_are_prepared_once(): void
    {
        $dir = sys_get_temp_dir().'/mp_prepared_'.uniqid();
        $src = $dir.'/in.stl';
        File::ensureDirectoryExists($dir);
        MeshFixtures::cubeStl($src, 20);
        $inner = new class implements PrintPreparer
        {
            public int $calls = 0;

            public function name(): string
            {
                return 'counting';
            }

            public function prepare(string $stlPath, string $outPath, float $unitScale, Dimensions $bed, bool $keepPose = false): PreparedMesh
            {
                $this->calls++;
                File::copy($stlPath, $outPath);

                return new PreparedMesh($outPath, true, false, true, 0, new Dimensions(20, 20, 20), 8000.0, 2400.0, 12, 1, ['changed' => false, 'repair_method' => 'pymeshfix'], 'counting');
            }
        };
        $cached = new CachedPrintPreparer($inner, $dir.'/cache');
        $bed = new Dimensions(250, 250, 250);

        $a = $cached->prepare($src, $dir.'/a.stl', 1.0, $bed);
        $b = $cached->prepare($src, $dir.'/b.stl', 1.0, $bed);
        $this->assertSame(1, $inner->calls, 'the second order of the same model reuses the prepared STL');
        $this->assertFileEquals($dir.'/a.stl', $dir.'/b.stl');
        $this->assertSame($dir.'/b.stl', $b->path);
        $this->assertTrue($b->repaired, 'the report of the repair travels with it');
        $this->assertTrue($b->orientation['reused']);
        $this->assertSame($a->toArray()['bbox'], $b->toArray()['bbox']);

        // another size, another plate or a kept pose is another preparation
        $cached->prepare($src, $dir.'/c.stl', 2.0, $bed);
        $cached->prepare($src, $dir.'/d.stl', 1.0, new Dimensions(420, 420, 500));
        $cached->prepare($src, $dir.'/e.stl', 1.0, $bed, true);
        $this->assertSame(4, $inner->calls);
        File::deleteDirectory($dir);
    }

    public function test_the_order_tells_the_customer_which_step_is_running(): void
    {
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $user = User::factory()->create();
        $path = sys_get_temp_dir().'/mp_stage_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);
        $uuid = $this->actingAs($user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->json('file.uuid');
        $url = $this->actingAs($user)->postJson('/farm/orders', ['file' => $uuid])->json('url');
        $order = FarmOrder::where('token', basename($url))->firstOrFail();

        // the job ran inline: the order is sliced and the tool's running commentary is gone
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status);
        Storage::disk('farm')->assertMissing($order->dir().'/print.stl.stage');
        $this->actingAs($user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->assertJsonPath('stage', null)->assertJsonPath('stage_total', 5);

        // while it is being prepared, the tool's word is what the customer reads
        $order->forceFill(['status' => FarmOrder::STATUS_UPLOADED, 'stage' => 'checking'])->save();
        $this->actingAs($user)->getJson("/farm/orders/{$order->token}/status")->assertJsonPath('stage', 'loading')->assertJsonPath('stage_step', 1);
        Storage::disk('farm')->put($order->dir().'/print.stl.stage', 'repairing');
        $this->actingAs($user)->getJson("/farm/orders/{$order->token}/status")->assertJsonPath('stage', 'repairing')->assertJsonPath('stage_step', 2);
        $order->forceFill(['stage' => 'slicing'])->save();
        $this->actingAs($user)->getJson("/farm/orders/{$order->token}/status")->assertJsonPath('stage', 'slicing')->assertJsonPath('stage_step', 5);
        foreach (['cs', 'en', 'es'] as $lang) {
            $this->actingAs($user)->get("/farm/orders/{$order->token}?lang={$lang}")->assertOk()->assertSee('farm-progress-bar')->assertDontSee('farm.stage.repairing":"farm.stage', false);
        }
    }
}
