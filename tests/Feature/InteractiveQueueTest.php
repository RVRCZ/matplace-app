<?php

namespace Tests\Feature;

use App\Jobs\PrepareFarmOrder;
use App\Jobs\ProcessModelFile;
use App\Jobs\SliceCalculation;
use App\Models\Calculation;
use App\Models\FarmOrder;
use App\Models\ModelFile;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * What a customer waits for has its own queue, and a calculation or a farm order made while the file is still being
 * processed starts the moment the file is ready, instead of asking every three seconds.
 */
class InteractiveQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
    }

    private function upload(?User $user = null): ModelFile
    {
        $path = sys_get_temp_dir().'/mp_iq_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);
        $uuid = ($user ? $this->actingAs($user) : $this)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'cube.stl', null, null, true)])->json('file.uuid');

        return ModelFile::where('uuid', $uuid)->firstOrFail();
    }

    private function work(object $job): void
    {
        app()->call([$job, 'handle']);
    }

    public function test_a_calculation_starts_when_its_file_is_ready(): void
    {
        Queue::fake();
        $file = $this->upload();
        Queue::assertPushedOn('interactive', ProcessModelFile::class);
        $this->assertSame(ModelFile::STATUS_UPLOADED, $file->status);

        $token = $this->postJson('/api/calculations', ['file' => $file->uuid, 'material' => 'PLA'])->json('calculation.token');
        $calc = Calculation::where('token', $token)->firstOrFail();
        Queue::assertPushedOn('interactive', SliceCalculation::class);

        // its own job comes first: the file is not ready, it neither slices nor asks again
        $early = Queue::pushed(SliceCalculation::class)->first();
        $this->work($early);
        $this->assertSame(Calculation::STATUS_QUEUED, $calc->fresh()->status);
        $this->assertNull($early->job, 'not released back to the queue');

        // the file is processed: it starts the waiting calculation
        $this->work(Queue::pushed(ProcessModelFile::class)->first());
        Queue::assertPushed(SliceCalculation::class, 2);
        $this->work(Queue::pushed(SliceCalculation::class)->last());
        $this->assertSame(Calculation::STATUS_DONE, $calc->fresh()->status);

        // a late second copy does nothing: the calculation is done
        $slicer = $calc->fresh()->slicer;
        $this->work(new SliceCalculation($calc->id));
        $this->assertSame($slicer, $calc->fresh()->slicer);
    }

    public function test_two_copies_slice_once(): void
    {
        $file = $this->upload();   // sync queue: ready
        Queue::fake();
        $token = $this->postJson('/api/calculations', ['file' => $file->uuid, 'material' => 'PETG'])->json('calculation.token');
        $calc = Calculation::where('token', $token)->firstOrFail();

        // another worker has claimed it a moment ago: this copy leaves it alone
        Calculation::whereKey($calc->id)->update(['status' => Calculation::STATUS_SLICING, 'updated_at' => now()]);
        $this->work(new SliceCalculation($calc->id));
        $this->assertSame(Calculation::STATUS_SLICING, $calc->fresh()->status);

        // a worker killed in the middle long ago: the slice is claimed again
        Calculation::whereKey($calc->id)->update(['updated_at' => now()->subMinutes(10)]);
        $this->work(new SliceCalculation($calc->id));
        $this->assertSame(Calculation::STATUS_DONE, $calc->fresh()->status);
    }

    public function test_a_farm_order_starts_when_its_file_is_ready_and_fails_with_it(): void
    {
        // an order is only made over a processed file; here its file is being processed again (the safety net)
        $this->seed(FarmSeeder::class);
        $user = User::factory()->create();
        $file = $this->upload($user);
        $order = FarmOrder::where('token', basename($this->actingAs($user)->postJson('/farm/orders', ['file' => $file->uuid])->json('url')))->firstOrFail();
        Queue::fake();
        $order->forceFill(['status' => FarmOrder::STATUS_UPLOADED, 'slice_params' => null])->save();
        $file->forceFill(['status' => ModelFile::STATUS_PROCESSING])->save();

        $this->work(new PrepareFarmOrder($order->id));
        $this->assertSame(FarmOrder::STATUS_UPLOADED, $order->fresh()->status);
        Queue::assertNothingPushed();

        $this->work(new ProcessModelFile($file->id));
        Queue::assertPushedOn('interactive', PrepareFarmOrder::class);
        $this->work(Queue::pushed(PrepareFarmOrder::class)->last());
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->fresh()->status);

        // a file that cannot be read fails the order waiting for it
        $order->fresh()->forceFill(['status' => FarmOrder::STATUS_UPLOADED])->save();
        $file->fresh()->forceFill(['status' => ModelFile::STATUS_PROCESSING])->save();
        Storage::disk('models')->delete($file->storage_path);
        $this->work(new ProcessModelFile($file->id));
        $this->assertSame(ModelFile::STATUS_FAILED, $file->fresh()->status);
        $this->work(Queue::pushed(PrepareFarmOrder::class)->last());
        $this->assertSame(FarmOrder::STATUS_FAILED, $order->fresh()->status);
    }
}
