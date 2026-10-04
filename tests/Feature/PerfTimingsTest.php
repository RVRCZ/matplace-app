<?php

namespace Tests\Feature;

use App\Domain\Stats\Speed;
use App\Models\Calculation;
use App\Models\FarmOrder;
use App\Models\ModelFile;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/** Every file, calculation and farm order keeps how long its steps took; the report reads them back. */
class PerfTimingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
    }

    private function upload(?User $user = null): string
    {
        $path = sys_get_temp_dir().'/mp_perf_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);
        $req = $user ? $this->actingAs($user) : $this;

        return $req->postJson('/api/uploads', ['file' => new UploadedFile($path, 'cube.stl', null, null, true)])->json('file.uuid');
    }

    public function test_file_and_calculation_keep_their_step_times(): void
    {
        $uuid = $this->upload();
        $file = ModelFile::where('uuid', $uuid)->firstOrFail();
        foreach (['queue_s', 'convert_s', 'analyse_s', 'started', 'finished'] as $key) {
            $this->assertArrayHasKey($key, $file->timings, $key);
        }
        $this->assertSame(12, $file->timings['triangles']);

        $token = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA'])->json('calculation.token');
        $t = Calculation::where('token', $token)->value('timings');
        $t = is_array($t) ? $t : json_decode($t, true);
        foreach (['wait_s', 'queue_s', 'slice_s', 'price_s', 'total_s', 'started', 'finished', 'print_minutes'] as $key) {
            $this->assertArrayHasKey($key, $t, $key);
        }
        $this->assertArrayNotHasKey('timings', Calculation::where('token', $token)->first()->slicer, 'the timings are not part of the result');

        // the same parameters again: served from the earlier calculation, and the report counts it
        $again = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA'])->json('calculation.token');
        $this->assertSame(['cache' => 'calculation'], Calculation::where('token', $again)->first()->timings);
        $s = app(Speed::class)->summary(7);
        $this->assertSame(1, $s['calc_cache_hits']);
        $this->assertSame(1, $s['groups']['calculations']['n']);
        $this->assertSame(1, $s['groups']['files']['n']);
    }

    public function test_farm_order_keeps_its_step_times(): void
    {
        $this->seed(FarmSeeder::class);
        $user = User::factory()->create();
        $uuid = $this->upload($user);
        $url = $this->actingAs($user)->postJson('/farm/orders', ['file' => $uuid])->json('url');
        $order = FarmOrder::where('token', basename($url))->firstOrFail();
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status);
        foreach (['queue_s', 'wait_s', 'prepare_s', 'slice_s', 'post_s', 'price_s', 'total_s'] as $key) {
            $this->assertArrayHasKey($key, $order->timings, $key);
        }
        $this->assertFalse($order->timings['reslice']);
        $this->assertFalse($order->timings['prepare_cached']);

        $s = app(Speed::class)->summary(7);
        $this->assertSame(1, $s['groups']['farm_first']['n']);
        $this->assertArrayHasKey('to_sliced', $s['groups']['farm_first']['phases']);
        $this->artisan('matplace:perf-report', ['--days' => 7])->expectsOutputToContain('Farm orders, first slice')->assertSuccessful();

        $admin = User::factory()->create();
        $admin->roles()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get('/admin/stats/speed')->assertOk()->assertSee('oprava + natočení')->assertSee('Rychlost');
    }

    public function test_percentiles_and_the_busiest_moment(): void
    {
        $this->assertSame(5.0, Speed::percentile([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], 50));
        $this->assertSame(9.0, Speed::percentile([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], 90));
        $this->assertSame(7.0, Speed::percentile([7], 90));
        // three jobs overlap between 5 and 6, the fourth starts as the first ends
        $this->assertSame(3, Speed::peak([
            ['started' => 0, 'finished' => 6], ['started' => 2, 'finished' => 8], ['started' => 5, 'finished' => 9], ['started' => 9, 'finished' => 12],
        ]));
    }
}
