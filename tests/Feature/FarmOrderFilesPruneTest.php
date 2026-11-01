<?php

namespace Tests\Feature;

use App\Domain\Farm\OrderFlow;
use App\Domain\Farm\Wallet;
use App\Models\Calculation;
use App\Models\CatalogModel;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrintJob;
use App\Models\FarmVideo;
use App\Models\GenerationRequest;
use App\Models\Inquiry;
use App\Models\ModelFile;
use App\Models\Payment;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/** The print files of closed orders in matplace:prune (docs/AL.md §16): by the state of the order, what people see stays. */
class FarmOrderFilesPruneTest extends TestCase
{
    use RefreshDatabase;

    private const GOES = ['print.gcode', 'print.slot0-215-55.gcode', 'print.slot1-215-55.tl-abc123.gcode', 'print.hot.slot2-215-55.gcode', 'rest.gcode', 'part-1.gcode',
        'print.stl', 'plate.stl', 'part-1.stl', 'part-1-plate.stl', 'plate-part-1.stl', 'rest.stl', 'print.stl.stage', 'plate.3mf', 'plate-rest.3mf', 'supports.bin',
        'frames/1.000.jpg', 'frames_layer/1.000.jpg'];

    private const STAYS = ['timelapse.mp4', 'short.mp4', 'wide.mp4', 'snapshot.jpg', 'snapshot_mail.jpg', 'frames.txt', 'report.json', 'photos/print-source.jpg', 'something-else.bin'];

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $this->user = User::factory()->create(['email' => 'customer@example.com']);
    }

    /** A row with every kind of file and the last change of state the given number of days ago. */
    private function orderWithFiles(string $status, int $daysAgo, array $over = []): FarmOrder
    {
        $this->travelTo(now()->subDays($daysAgo));
        $order = FarmOrder::factory()->create(['status' => $status, 'number' => 'F26-'.uniqid()] + $over);
        $order->forceFill([
            'print_stl_path' => $order->dir().'/print.stl', 'gcode_path' => $order->dir().'/print.gcode', 'gcode_sha256' => str_repeat('a', 64), 'rest_gcode_path' => $order->dir().'/rest.gcode',
            'timelapse_path' => $order->dir().'/timelapse.mp4', 'timelapse_short_path' => $order->dir().'/short.mp4', 'timelapse_wide_path' => $order->dir().'/wide.mp4',
        ])->save();
        $order->events()->create(['from' => FarmOrder::STATUS_QUEUED, 'to' => $status, 'actor' => 'system']);
        $this->travelBack();
        foreach ([...self::GOES, ...self::STAYS] as $name) {
            Storage::disk('farm')->put($order->dir().'/'.$name, str_repeat('x', 1024));
        }

        return $order;
    }

    private function assertUntouched(FarmOrder $order): void
    {
        foreach ([...self::GOES, ...self::STAYS] as $name) {
            Storage::disk('farm')->assertExists($order->dir().'/'.$name);
        }
        $fresh = $order->fresh();
        $this->assertSame($order->print_stl_path, $fresh->print_stl_path);
        $this->assertSame($order->gcode_path, $fresh->gcode_path);
        $this->assertNull($fresh->files_pruned_at);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);

        return $admin;
    }

    /** A real order of the customer: uploaded, sliced, paid. */
    private function paidOrder(): FarmOrder
    {
        $path = sys_get_temp_dir().'/mp_farm_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20.0);
        $uuid = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated()->json('file.uuid');
        $url = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $uuid])->assertCreated()->json('url');
        $order = FarmOrder::where('token', basename($url))->firstOrFail();
        $this->actingAs($this->user)->post('/account/credit', ['amount' => 2000])->assertRedirect();
        $this->postJson('/webhooks/payments/fake', ['ref' => Payment::latest('id')->firstOrFail()->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $this->user->refresh();
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->json();
        $color = collect($state['colors'])->firstWhere('slot', $state['slot']) ?? $state['colors'][0];
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $color['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $color['total']])->assertOk();

        return $order->fresh();
    }

    public function test_the_files_of_an_order_closed_for_a_week_go_and_what_people_see_stays(): void
    {
        $closed = $this->orderWithFiles(FarmOrder::STATUS_HANDED_OVER, 8);
        $recent = $this->orderWithFiles(FarmOrder::STATUS_HANDED_OVER, 3);
        $printing = $this->orderWithFiles(FarmOrder::STATUS_PRINTING, 20);

        $this->artisan('matplace:prune')->expectsOutputToContain('farm order files removed: 1 orders, 0.0 MB')->assertSuccessful();

        foreach (self::GOES as $name) {
            Storage::disk('farm')->assertMissing($closed->dir().'/'.$name);
        }
        Storage::disk('farm')->assertMissing($closed->dir().'/frames');
        foreach (self::STAYS as $name) {
            Storage::disk('farm')->assertExists($closed->dir().'/'.$name);
        }
        $fresh = $closed->fresh();
        $this->assertNull($fresh->print_stl_path);
        $this->assertNull($fresh->gcode_path);
        $this->assertNull($fresh->gcode_sha256);
        $this->assertNull($fresh->rest_gcode_path);
        $this->assertSame($closed->dir().'/timelapse.mp4', $fresh->timelapse_path);
        $this->assertSame($closed->dir().'/short.mp4', $fresh->timelapse_short_path);
        $this->assertTrue($fresh->filesPruned());

        $this->assertUntouched($recent);
        $this->assertUntouched($printing);

        // the next night has nothing to say about it
        $this->artisan('matplace:prune')->expectsOutputToContain('farm order files removed: 0 orders')->assertSuccessful();
    }

    public function test_every_closed_state_goes_and_no_open_state_does(): void
    {
        foreach ([FarmOrder::STATUS_HANDED_OVER, FarmOrder::STATUS_CANCELLED, FarmOrder::STATUS_FAILED, FarmOrder::STATUS_DONE] as $status) {
            $closed[$status] = $this->orderWithFiles($status, 8);
        }
        foreach ([FarmOrder::STATUS_UPLOADED, FarmOrder::STATUS_SLICED, FarmOrder::STATUS_PAID, FarmOrder::STATUS_QUEUED, FarmOrder::STATUS_PRINTING] as $status) {
            $open[$status] = $this->orderWithFiles($status, 20);
        }

        Artisan::call('matplace:prune');

        foreach ($closed as $status => $order) {
            $this->assertTrue($order->fresh()->filesPruned(), $status);
            Storage::disk('farm')->assertMissing($order->dir().'/print.gcode');
        }
        foreach ($open as $order) {
            $this->assertUntouched($order);
        }
    }

    public function test_the_dry_run_names_the_orders_and_deletes_nothing(): void
    {
        $closed = $this->orderWithFiles(FarmOrder::STATUS_CANCELLED, 8);

        $this->artisan('matplace:prune', ['--dry-run' => true])
            ->expectsOutputToContain($closed->number.' cancelled: 0.0 MB')
            ->expectsOutputToContain('farm order files removed: 1 orders')
            ->assertSuccessful();

        $this->assertUntouched($closed);
    }

    public function test_a_job_still_on_a_printer_holds_the_files_whatever_the_order_says(): void
    {
        $printer = FarmPrinter::firstOrFail();
        foreach (FarmPrintJob::ACTIVE as $status) {
            $order = $this->orderWithFiles(FarmOrder::STATUS_FAILED, 20);
            FarmPrintJob::create(['farm_order_id' => $order->id, 'farm_printer_id' => $printer->id, 'slot' => 0, 'plate' => 1, 'status' => $status]);
            $held[$status] = $order;
        }
        $over = $this->orderWithFiles(FarmOrder::STATUS_FAILED, 20);
        FarmPrintJob::create(['farm_order_id' => $over->id, 'farm_printer_id' => $printer->id, 'slot' => 0, 'plate' => 1, 'status' => FarmPrintJob::STATUS_FAILED]);

        Artisan::call('matplace:prune');

        foreach ($held as $order) {
            $this->assertUntouched($order);
        }
        $this->assertTrue($over->fresh()->filesPruned());
    }

    public function test_the_clock_is_the_last_change_of_state_not_a_note_or_an_old_finish(): void
    {
        // finished long ago, then found failed three days ago: still in the operator's hands
        $failed = $this->orderWithFiles(FarmOrder::STATUS_FAILED, 3, ['finished_at' => now()->subDays(30)]);
        // something written on the order yesterday is no change of state: the week still counts
        $noted = $this->orderWithFiles(FarmOrder::STATUS_HANDED_OVER, 8);
        $noted->events()->create(['from' => $noted->status, 'to' => $noted->status, 'actor' => 'system', 'note' => 'kept 50 for 50 % printed']);
        $noted->forceFill(['quality_note' => 'nice', 'updated_at' => now()->subDay()])->save();
        // no history at all (nothing in the code closes an order that way): updated_at decides
        $silent = $this->orderWithFiles(FarmOrder::STATUS_CANCELLED, 8);
        $silent->events()->delete();
        $silentRecent = $this->orderWithFiles(FarmOrder::STATUS_CANCELLED, 8);
        $silentRecent->events()->delete();
        $silentRecent->forceFill(['updated_at' => now()->subDay()])->save();

        Artisan::call('matplace:prune');

        $this->assertUntouched($failed);
        $this->assertTrue($noted->fresh()->filesPruned());
        $this->assertTrue($silent->fresh()->filesPruned());
        $this->assertUntouched($silentRecent);
    }

    public function test_pruning_does_not_bring_the_order_back_to_the_list_of_things_to_do(): void
    {
        $order = $this->orderWithFiles(FarmOrder::STATUS_DONE, 8, ['paid_at' => now()->subDays(9)]);
        $order->forceFill(['attention_cleared_at' => now()->subDays(8), 'updated_at' => now()->subDays(8)])->save();
        $before = $order->fresh()->updated_at->timestamp;

        Artisan::call('matplace:prune');

        $this->assertTrue($order->fresh()->filesPruned());
        $this->assertSame($before, $order->fresh()->updated_at->timestamp);
    }

    public function test_the_parts_of_an_order_stay_without_their_files(): void
    {
        $order = $this->orderWithFiles(FarmOrder::STATUS_HANDED_OVER, 8, ['by_parts' => true]);
        $order->forceFill(['part_plates' => [
            ['part' => 'body', 'slot_id' => 1, 'color_id' => 2, 'copies' => 1, 'minutes' => 30, 'grams' => 12, 'stl_path' => $order->dir().'/part-1.stl', 'gcode_path' => $order->dir().'/part-1.gcode', 'sha256' => 'abc'],
        ]])->save();

        Artisan::call('matplace:prune');

        $this->assertSame([['part' => 'body', 'slot_id' => 1, 'color_id' => 2, 'copies' => 1, 'minutes' => 30, 'grams' => 12]], $order->fresh()->partPlates());
    }

    public function test_the_frames_of_a_short_waiting_for_the_admin_stay_for_a_new_build(): void
    {
        $order = $this->orderWithFiles(FarmOrder::STATUS_HANDED_OVER, 8);
        FarmVideo::factory()->create(['farm_order_id' => $order->id, 'kind' => FarmVideo::KIND_SHORT, 'status' => FarmVideo::STATUS_PENDING]);
        $decided = $this->orderWithFiles(FarmOrder::STATUS_HANDED_OVER, 8);
        FarmVideo::factory()->create(['farm_order_id' => $decided->id, 'kind' => FarmVideo::KIND_SHORT, 'status' => FarmVideo::STATUS_PUBLISHED]);

        Artisan::call('matplace:prune');

        Storage::disk('farm')->assertMissing($order->dir().'/print.gcode');
        Storage::disk('farm')->assertExists($order->dir().'/frames/1.000.jpg');
        Storage::disk('farm')->assertExists($order->dir().'/frames_layer/1.000.jpg');
        Storage::disk('farm')->assertMissing($decided->dir().'/frames/1.000.jpg');
    }

    public function test_the_order_page_falls_back_to_the_customers_file_without_supports(): void
    {
        $order = $this->paidOrder();
        $flow = app(OrderFlow::class);
        foreach ([FarmOrder::STATUS_QUEUED, FarmOrder::STATUS_PRINTING, FarmOrder::STATUS_DONE, FarmOrder::STATUS_HANDED_OVER] as $to) {
            $order = $order->fresh();
            if ($order->canMoveTo($to)) {
                $flow->move($order, $to, 'admin');
            }
        }
        $order->printJobs()->update(['status' => FarmPrintJob::STATUS_DONE]);
        Storage::disk('farm')->put($order->dir().'/supports.bin', 'lines');
        $this->assertSame(FarmOrder::STATUS_HANDED_OVER, $order->fresh()->status);
        Storage::disk('farm')->assertExists($order->fresh()->print_stl_path);

        $this->travel(8)->days();
        Artisan::call('matplace:prune');
        $this->assertTrue($order->fresh()->filesPruned());

        $this->actingAs($this->user)->get("/farm/orders/{$order->token}")->assertOk();
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $this->assertStringContainsString("/farm/orders/{$order->token}/model.stl", $state['model_url']);
        $this->assertNull($state['supports_url']);

        // the address serves the customer's own STL
        $served = $this->actingAs($this->user)->get("/farm/orders/{$order->token}/model.stl")->assertOk();
        $this->assertSame(realpath($order->modelFile->absoluteStlPath()), realpath($served->baseResponse->getFile()->getPathname()));
        $this->actingAs($this->user)->get("/farm/orders/{$order->token}/supports.bin")->assertNotFound();
    }

    public function test_a_model_the_customer_deleted_leaves_a_working_page_without_a_preview(): void
    {
        $order = $this->orderWithFiles(FarmOrder::STATUS_HANDED_OVER, 8, ['user_id' => $this->user->id, 'paid_at' => now()->subDays(9)]);
        $order->modelFile->forceFill(['stl_path' => null])->save();

        Artisan::call('matplace:prune');

        $this->actingAs($this->user)->get("/farm/orders/{$order->token}")->assertOk();
        $this->assertNull($this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json('model_url'));
        $this->actingAs($this->user)->get("/farm/orders/{$order->token}/model.stl")->assertNotFound();
    }

    public function test_print_again_builds_a_new_order_from_the_customers_file(): void
    {
        $order = $this->paidOrder();
        $flow = app(OrderFlow::class);
        foreach ([FarmOrder::STATUS_PRINTING, FarmOrder::STATUS_DONE, FarmOrder::STATUS_HANDED_OVER] as $to) {
            $order = $order->fresh();
            if ($order->canMoveTo($to)) {
                $flow->move($order, $to, 'admin');
            }
        }
        $order->printJobs()->update(['status' => FarmPrintJob::STATUS_DONE]);
        $this->travel(8)->days();
        Artisan::call('matplace:prune');
        $this->assertTrue($order->fresh()->filesPruned());

        $r = $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/repeat")->assertCreated();
        $new = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();

        $this->assertNotSame($order->id, $new->id);
        $this->assertNotNull($new->gcode_path);
        Storage::disk('farm')->assertExists($new->gcode_path);
        $this->assertSame($order->model_file_id, $new->model_file_id);
    }

    public function test_a_failed_order_without_its_gcode_cannot_stop_the_queue_and_a_new_slice_brings_it_back(): void
    {
        $admin = $this->admin();
        $order = $this->paidOrder();
        app(OrderFlow::class)->fail($order->fresh(), 'operator', 'admin');
        $order = $order->fresh();
        $this->assertSame(FarmOrder::STATUS_FAILED, $order->status);
        $order->printJobs()->update(['status' => FarmPrintJob::STATUS_FAILED]);
        $held = app(Wallet::class)->balance($this->user)->amount;

        $this->travel(8)->days();
        Artisan::call('matplace:prune');
        $order = $order->fresh();
        $this->assertTrue($order->filesPruned());
        $this->assertFalse($order->canMoveTo(FarmOrder::STATUS_QUEUED));
        $this->assertTrue($order->canMoveTo(FarmOrder::STATUS_CANCELLED));

        // the admin's page says what happened, there is no link and no way into the queue
        $page = $this->actingAs($admin)->get("/admin/farm/orders/{$order->token}")->assertOk();
        $page->assertSee('data-files-pruned', false)->assertSee(__('farm.admin.files_pruned_again'));
        $page->assertDontSee(route('admin.farm.orders.gcode', $order), false);
        $page->assertDontSee('<option value="queued"', false);
        $this->actingAs($admin)->get("/admin/farm/orders/{$order->token}/print.gcode")->assertNotFound();
        $this->actingAs($admin)->post("/admin/farm/orders/{$order->token}/status", ['to' => FarmOrder::STATUS_QUEUED])->assertSessionHas('error');
        $this->assertSame(FarmOrder::STATUS_FAILED, $order->fresh()->status);

        // slicing again makes it an order like any other
        $this->actingAs($admin)->post("/admin/farm/orders/{$order->token}/overrides", ['overrides' => ''])->assertSessionHas('status');
        $order = $order->fresh();
        $this->assertNotNull($order->gcode_path);
        Storage::disk('farm')->assertExists($order->gcode_path);
        $this->assertFalse($order->filesPruned());
        $this->assertSame($held, app(Wallet::class)->balance($this->user)->amount);
    }

    public function test_a_page_of_an_order_by_parts_without_files_lists_the_parts_without_links(): void
    {
        $admin = $this->admin();
        $order = $this->orderWithFiles(FarmOrder::STATUS_HANDED_OVER, 8, ['by_parts' => true]);
        $order->forceFill(['part_plates' => [
            ['part' => 'body', 'slot_id' => 0, 'color_id' => 0, 'copies' => 1, 'stl_path' => $order->dir().'/part-1.stl', 'gcode_path' => $order->dir().'/part-1.gcode'],
            ['part' => 'lid', 'slot_id' => 0, 'color_id' => 0, 'copies' => 1, 'stl_path' => $order->dir().'/part-2.stl', 'gcode_path' => $order->dir().'/part-2.gcode'],
        ]])->save();
        Storage::disk('farm')->put($order->dir().'/part-2.gcode', 'G1');
        $this->actingAs($admin)->get("/admin/farm/orders/{$order->token}")->assertOk()->assertSee(route('admin.farm.orders.gcode', [$order, 'plate' => 2]), false);

        Artisan::call('matplace:prune');

        $page = $this->actingAs($admin)->get("/admin/farm/orders/{$order->token}")->assertOk();
        $page->assertDontSee(route('admin.farm.orders.gcode', [$order, 'plate' => 2]), false);
        $page->assertSee('data-files-pruned', false);
        $page->assertDontSee(__('farm.admin.files_pruned_again'));
    }

    public function test_files_named_in_the_columns_but_already_gone_leave_working_pages(): void
    {
        // what the one-off clean-up of 1 Nov 2026 left on production: the prepared STL stays, G-code and supports went
        $order = $this->orderWithFiles(FarmOrder::STATUS_HANDED_OVER, 3, ['user_id' => $this->user->id, 'paid_at' => now()->subDays(9), 'by_parts' => true]);
        $order->forceFill(['part_plates' => [
            ['part' => 'body', 'slot_id' => 0, 'color_id' => 0, 'copies' => 1, 'stl_path' => $order->dir().'/plate-part-1.stl', 'gcode_path' => $order->dir().'/part-1.gcode'],
            ['part' => 'lid', 'slot_id' => 0, 'color_id' => 0, 'copies' => 1, 'stl_path' => $order->dir().'/part-2.stl', 'gcode_path' => $order->dir().'/part-2.gcode'],
        ]])->save();
        $disk = Storage::disk('farm');
        $disk->put($order->dir().'/part-2.stl', 'solid');
        foreach (['print.gcode', 'rest.gcode', 'part-1.gcode', 'supports.bin', 'plate-part-1.stl'] as $name) {
            $disk->delete($order->dir().'/'.$name);
        }
        $order = $order->fresh();

        $this->assertNull($order->absoluteGcodePath());
        $this->assertNull($order->absoluteGcodePath(1));
        $this->assertNull($order->absoluteSupportsPath());
        $this->assertNotNull($order->absolutePrintStlPath());
        $this->assertTrue($order->filesPruned());

        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $this->assertNull($state['supports_url']);
        $this->assertNull($state['parts'][0]['model_url'], 'a plate whose STL is gone gets no address the viewer would fail on');
        $this->assertNotNull($state['parts'][1]['model_url']);
        $this->actingAs($this->user)->get("/farm/orders/{$order->token}")->assertOk();
        $this->actingAs($this->user)->get("/farm/orders/{$order->token}/model.stl")->assertOk();
        $this->actingAs($this->user)->get('/account/orders')->assertOk();

        $admin = $this->admin();
        $page = $this->actingAs($admin)->get("/admin/farm/orders/{$order->token}")->assertOk();
        $page->assertSee('data-files-pruned', false);
        $page->assertDontSee(route('admin.farm.orders.gcode', $order), false);
        $this->actingAs($admin)->get("/admin/farm/orders/{$order->token}/print.gcode")->assertNotFound();

        // a failed order in that state stays out of the queue too
        $order->forceFill(['status' => FarmOrder::STATUS_FAILED])->save();
        $this->assertFalse($order->fresh()->canMoveTo(FarmOrder::STATUS_QUEUED));

        // the first run of the rule puts the columns right, once the week is over
        $this->travel(8)->days();
        $this->artisan('matplace:prune')->expectsOutputToContain('farm order files removed: 1 orders')->assertSuccessful();
        $this->assertNull($order->fresh()->print_stl_path);
        $this->assertNull($order->fresh()->gcode_path);
    }

    /** A model file with a folder of its own, this many days old. */
    private function modelFile(int $days, ?User $owner = null): ModelFile
    {
        $file = ModelFile::factory()->create(['owner_user_id' => $owner?->id, 'created_at' => now()->subDays($days)]);
        Storage::disk('models')->put($file->dir().'/original.stl', str_repeat('x', 2048));
        Storage::disk('models')->put($file->dir().'/parts/body.stl', str_repeat('x', 1024));

        return $file;
    }

    private function calculation(ModelFile $file, string $token): Calculation
    {
        return Calculation::forceCreate(['token' => $token, 'model_file_id' => $file->id, 'params' => [], 'params_hash' => md5($token), 'status' => Calculation::STATUS_DONE]);
    }

    private function assertGone(ModelFile $file): void
    {
        $this->assertNull(ModelFile::find($file->id), 'row of '.$file->id);
        Storage::disk('models')->assertMissing($file->dir().'/original.stl');
        Storage::disk('models')->assertMissing($file->dir().'/parts/body.stl');
    }

    private function assertKept(ModelFile $file, string $why): void
    {
        $this->assertNotNull(ModelFile::find($file->id), $why);
        Storage::disk('models')->assertExists($file->dir().'/original.stl');
    }

    public function test_a_file_nothing_holds_goes_after_thirty_days_with_or_without_an_account(): void
    {
        $anonymous = $this->modelFile(31);
        $owned = $this->modelFile(31, $this->user);
        $this->calculation($owned, 'owned31days');
        $young = $this->modelFile(20, $this->user);
        $youngAnonymous = $this->modelFile(20);

        $this->artisan('matplace:prune')->expectsOutputToContain('model files nothing holds removed: with an account 1, without 1')->assertSuccessful();

        $this->assertGone($anonymous);
        $this->assertGone($owned);
        $this->assertNull(Calculation::where('token', 'owned31days')->first(), 'the calculation goes with its file');
        $this->assertKept($young, 'twenty days');
        $this->assertKept($youngAnonymous, 'twenty days');
    }

    public function test_anything_that_refers_to_a_file_holds_it_whatever_its_age(): void
    {
        $sliced = $this->modelFile(31, $this->user);
        FarmOrder::factory()->create(['model_file_id' => $sliced->id, 'user_id' => $this->user->id, 'status' => FarmOrder::STATUS_SLICED]);
        $cancelled = $this->modelFile(31, $this->user);
        FarmOrder::factory()->create(['model_file_id' => $cancelled->id, 'user_id' => $this->user->id, 'status' => FarmOrder::STATUS_CANCELLED]);
        $anonymousSliced = $this->modelFile(31);
        FarmOrder::factory()->create(['model_file_id' => $anonymousSliced->id, 'user_id' => null, 'status' => FarmOrder::STATUS_SLICED, 'created_at' => now()]);
        $catalog = $this->modelFile(31);
        CatalogModel::create(['model_file_id' => $catalog->id, 'slug' => 'stand', 'title' => 'Stand', 'description' => ['en' => 'A stand.'], 'source_locale' => 'en', 'source' => 'printables', 'external_url' => 'https://www.printables.com/model/1', 'license' => 'cc_by', 'license_restricted' => false, 'author_name' => 'jana', 'visible' => true, 'view_count' => 1, 'max_mm' => 120]);
        $inquiry = $this->modelFile(31, $this->user);
        Inquiry::forceCreate(['token' => 'inq1', 'model_file_id' => $inquiry->id, 'contact_email' => 'a@example.com', 'material_code' => 'PLA']);
        $byCalculation = $this->modelFile(31, $this->user);
        Inquiry::forceCreate(['token' => 'inq2', 'calculation_id' => $this->calculation($byCalculation, 'inquired1')->id, 'contact_email' => 'a@example.com', 'material_code' => 'PLA']);
        $generated = $this->modelFile(31, $this->user);
        GenerationRequest::create(['token' => 'gen1', 'type' => 'image', 'status' => 'done', 'result_model_file_id' => $generated->id]);
        $article = $this->modelFile(31, $this->user);
        $this->calculation($article, 'inarticle01');
        $link = $this->modelFile(31);
        $this->calculation($link, 'linked00001');
        Post::create(['slug' => 'clanek', 'title' => ['cs' => 'Článek'], 'body' => ['cs' => "Text\n\n[calc: inarticle01]\n\nA [odkaz](https://matplace.com/c/linked00001)."], 'format' => Post::FORMAT_MARKDOWN, 'published_at' => now()]);
        $free = $this->modelFile(31, $this->user);

        $this->artisan('matplace:prune')->assertSuccessful();

        foreach (['sliced', 'cancelled', 'anonymousSliced', 'catalog', 'inquiry', 'byCalculation', 'generated', 'article', 'link'] as $why) {
            $this->assertKept($$why, $why);
        }
        $this->assertGone($free);
    }

    public function test_a_file_without_an_account_still_goes_with_what_nobody_paid_for_and_its_folder(): void
    {
        $file = $this->modelFile(31);
        $order = FarmOrder::factory()->create(['model_file_id' => $file->id, 'user_id' => null, 'status' => FarmOrder::STATUS_CANCELLED, 'paid_at' => null]);
        Storage::disk('farm')->put($order->dir().'/print.stl', 'x');
        $generated = $this->modelFile(31);
        GenerationRequest::create(['token' => 'gen2', 'type' => 'image', 'status' => 'done', 'result_model_file_id' => $generated->id]);
        $paid = $this->modelFile(31);
        FarmOrder::factory()->create(['model_file_id' => $paid->id, 'user_id' => null, 'status' => FarmOrder::STATUS_CANCELLED, 'paid_at' => now()->subDays(20)]);

        $this->artisan('matplace:prune')->assertSuccessful();

        $this->assertGone($file);
        $this->assertNull(FarmOrder::find($order->id));
        Storage::disk('farm')->assertMissing($order->dir().'/print.stl');
        $this->assertGone($generated);
        $this->assertKept($paid, 'paid once');
    }

    public function test_the_dry_run_counts_files_with_and_without_an_account_and_deletes_nothing(): void
    {
        $anonymous = $this->modelFile(31);
        $owned = $this->modelFile(31, $this->user);
        $another = $this->modelFile(40, $this->user);

        $this->artisan('matplace:prune', ['--dry-run' => true])
            ->expectsOutputToContain('model files nothing holds: with an account 2 (0.00 GB), without 1 (0.00 GB)')
            ->assertSuccessful();

        foreach ([$anonymous, $owned, $another] as $file) {
            $this->assertKept($file, 'a dry run');
        }
    }

    public function test_people_are_told_files_go_and_a_gone_calculation_says_why(): void
    {
        $this->actingAs($this->user)->get('/account/models')->assertOk()->assertSee('Soubory bez objednávky mažeme po 30 dnech.');

        $file = $this->modelFile(31, $this->user);
        $this->calculation($file, 'sharedlink01');
        $this->artisan('matplace:prune')->assertSuccessful();

        $this->get('/c/sharedlink01')->assertStatus(410)->assertSee('Tahle kalkulace už tu není')->assertSee('mažeme po 30 dnech');
        $this->get('/en/c/sharedlink01')->assertStatus(410)->assertSee('This calculation is no longer here');
    }
}
