<?php

namespace Tests\Feature;

use App\Domain\Farm\FarmSettings;
use App\Engines\Contracts\Slicer;
use App\Engines\DTO\Dimensions;
use App\Engines\DTO\SliceParams;
use App\Engines\DTO\SliceResult;
use App\Jobs\PrepareFarmOrder;
use App\Mail\FarmAdminAlert;
use App\Models\Calculation;
use App\Models\FarmOrder;
use App\Models\FarmOrderEvent;
use App\Models\Payment;
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
 * A big model's order waits for the customer's choices before it is sliced (TODO #41, docs/AL.md §11): the rough
 * price until then, one button for the exact one, a change during the slice starts it over; a small model is sliced
 * at once as before. Fake slicer, sync queue; a cube counts as big once the threshold is five triangles.
 */
final class FarmBigModelFlowTest extends TestCase
{
    use RefreshDatabase;

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

    private function upload(float $size = 20.0): string
    {
        $path = sys_get_temp_dir().'/mp_bigflow_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, $size);

        return $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'dragon.stl', null, null, true)])->assertCreated()->json('file.uuid');
    }

    private function order(array $body): FarmOrder
    {
        $r = $this->actingAs($this->user)->postJson('/farm/orders', $body)->assertCreated();

        return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
    }

    private function state(FarmOrder $order): array
    {
        return $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
    }

    /** The choices as the page sends them: what the order has, with the colour the page ticks first. */
    private function choices(FarmOrder $order, array $over = []): array
    {
        $state = $this->state($order);
        $color = collect($state['colors'])->firstWhere('sliced', true) ?? $state['colors'][0] ?? null;

        return $over + ['quality' => $state['quality'], 'strength' => $state['strength'], 'unit' => $state['unit'], 'copies' => $state['copies'], 'supports' => $state['supports_mode'], 'slot' => $color['slot'] ?? null];
    }

    private function credit(int $amount): void
    {
        $this->actingAs($this->user)->post('/account/credit', ['amount' => $amount])->assertRedirect();
        $payment = Payment::latest('id')->firstOrFail();
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $this->user->refresh();
    }

    public function test_a_big_model_waits_for_the_choices_and_is_sliced_only_on_the_button(): void
    {
        config(['engines.mesh.decimate_above' => 5]);   // a cube has 12 triangles
        $order = $this->order(['file' => $this->upload()]);

        // made, but nothing ran: no slice, no price, no stage, no mail to the operator
        $this->assertSame(FarmOrder::STATUS_AWAITING_CHOICES, $order->status);
        $this->assertTrue($order->big_model);
        $this->assertNull($order->stage);
        $this->assertNull($order->est_minutes);
        $this->assertNull($order->gcode_path);
        $this->assertNull($order->timings);
        $this->assertSame(0, $order->slice_run);
        $this->assertSame(120, $order->slice_estimate_s, 'no calculation measured it: the floor of two minutes for twelve triangles');
        $this->assertStringContainsString('big model (0.0 M triangles)', (string) $order->events()->first()->note);
        Mail::assertNothingQueued();

        // the page: the colours on offer with the rates to estimate from, what the estimate is made of, no price yet
        $state = $this->state($order);
        $this->assertSame('awaiting_choices', $state['status']);
        $this->assertSame(__('farm.status.awaiting_choices'), $state['status_text']);
        $this->assertTrue($state['big']);
        $this->assertTrue($state['choosing']);
        $this->assertSame(2, $state['slice_minutes']);
        $this->assertNull($state['minutes']);
        $this->assertNull($state['shipping']);
        $this->assertNotEmpty($state['colors']);
        $this->assertNull($state['colors'][0]['price']);
        $this->assertSame(['price_per_gram', 'density', 'hourly_rate', 'time_factor', 'weight_factor'], array_keys($state['colors'][0]['rates']));
        $this->assertTrue(collect($state['colors'])->contains('sliced', true), 'the colour the order started with is ticked first');
        $this->assertEqualsWithDelta(8000.0, $state['estimate']['volume_mm3'], 1.0);
        $this->assertSame(['perimeters', 'line_width_mm', 'shell_fraction_fallback', 'support_factor', 'minutes_per_gram', 'overhead_minutes', 'quality_time_factor', 'quality_layer_mm', 'range'], array_keys($state['estimate']['rough']));
        $this->assertSame(['low' => 10, 'standard' => 15, 'high' => 30], $state['estimate']['strengths']);
        $this->assertSame(['fixed_fee', 'min_price', 'vat_percent', 'rounding'], array_keys($state['estimate']['rates']));
        $this->assertTrue($state['can_cancel']);
        // the operator's page names the state; "my prints" lists it among the open ones
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin)->get(route('admin.farm.orders.show', $order))->assertOk()->assertSee(__('farm.status.awaiting_choices'));
        $this->actingAs($this->user)->get('/account/orders?status=open')->assertOk()->assertSee(__('farm.status.awaiting_choices'));
        foreach (['cs', 'en', 'es'] as $lang) {
            $this->actingAs($this->user)->get($this->localized("/farm/orders/{$order->token}", $lang))->assertOk()->assertSee('farm-slice-start')->assertSee('farm-estimate-price')->assertDontSee('farm.order.slice_start":"farm.order', false);
        }

        // the button: the choices made on the page start the slice (sync queue: it is done when the answer comes)
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", $this->choices($order, ['quality' => 'fine']))->assertOk()->assertJsonPath('status', 'sliced')->assertJsonPath('quality', 'fine');
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status);
        $this->assertSame(1, $order->slice_run);
        $this->assertSame('fine', $order->slice_params['quality']);
        $this->assertNotNull($order->est_minutes);
        $this->assertSame(1, $order->timings['run']);
        $this->assertSame([[null, 'awaiting_choices'], ['awaiting_choices', 'uploaded'], ['uploaded', 'sliced']], $order->events->map(fn ($e) => [$e->from, $e->to])->all());
        $this->assertSame('slice started by the customer', $order->events[1]->note);
        $state = $this->state($order);
        $this->assertTrue($state['big']);
        $this->assertNotNull($state['colors'][0]['price'], 'sliced: the price of every colour as before');
        Mail::assertNothingQueued();

        // and the order is paid like any other
        $this->credit(1000);
        $color = collect($state['colors'])->firstWhere('sliced', true);
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $color['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $color['total']])->assertOk();
        $this->assertSame(FarmOrder::STATUS_QUEUED, $order->fresh()->status);
        Mail::assertQueued(FarmAdminAlert::class);
    }

    public function test_a_calculation_that_took_over_a_minute_to_slice_makes_the_model_big(): void
    {
        $uuid = $this->upload();
        $token = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA'])->assertCreated()->json('calculation.token');
        $calc = Calculation::where('token', $token)->firstOrFail();
        $this->assertSame(Calculation::STATUS_DONE, $calc->status);
        $this->assertLessThan(60, $calc->timings['slice_s']);

        // a quick slice on the calculator: the order from it is sliced at once, as before
        $quick = $this->order(['calc' => $token]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $quick->status);
        $this->assertFalse($quick->big_model);
        $this->assertNull($quick->slice_estimate_s);
        $this->assertNull($quick->events()->first()->note);

        // the same calculation remembered as a slow one: the order waits, and promises the measured time with a margin
        $calc->forceFill(['timings' => ['slice_s' => 230.4] + $calc->timings])->save();
        $slow = $this->order(['calc' => $token]);
        $this->assertSame(FarmOrder::STATUS_AWAITING_CHOICES, $slow->status);
        $this->assertSame((int) ceil(230.4 * 1.2 + 15), $slow->slice_estimate_s);
        $this->assertSame(5, $this->state($slow)['slice_minutes']);
        $this->assertStringContainsString('calculation sliced in 230 s', (string) $slow->events()->first()->note);

        // the same file without the calculation at hand (the start page): the last slice of the file counts
        $byFile = $this->order(['file' => $uuid]);
        $this->assertSame(FarmOrder::STATUS_AWAITING_CHOICES, $byFile->status);

        // the threshold is a setting, the same one the first page uses
        config(['engines.mesh.big_slice_s' => 300]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $this->order(['file' => $uuid])->status);
    }

    public function test_a_change_of_the_choices_while_a_big_model_is_being_sliced_starts_the_slice_over(): void
    {
        config(['engines.mesh.decimate_above' => 5]);
        $order = $this->order(['file' => $this->upload()]);
        Queue::fake();

        // the button: the first run is queued, the order is being prepared
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", $this->choices($order, ['quality' => 'fine']))->assertOk()->assertJsonPath('status', 'uploaded')->assertJsonPath('slice_run', 1)->assertJsonPath('big', true)->assertJsonPath('choosing', true);
        Queue::assertPushed(PrepareFarmOrder::class, fn (PrepareFarmOrder $j) => $j->orderId === $order->id && $j->run === 1);
        $first = Queue::pushed(PrepareFarmOrder::class)->last();
        $state = $this->state($order);
        $this->assertNotEmpty($state['colors'], 'the colours stay on offer while a big model is sliced');
        $this->assertNotNull($state['estimate']);

        // the customer changes the quality meanwhile: the running slice is overtaken, a second run is queued
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", $this->choices($order, ['quality' => 'draft']))->assertOk()->assertJsonPath('status', 'uploaded')->assertJsonPath('slice_run', 2)->assertJsonPath('quality', 'draft');
        $second = Queue::pushed(PrepareFarmOrder::class)->last();
        $this->assertSame(2, $second->run);
        $this->assertSame('choices changed while slicing: run 2 replaces run 1', FarmOrderEvent::where('farm_order_id', $order->id)->latest('id')->first()->note);

        // the first run finds itself overtaken and leaves the order alone
        app()->call([$first, 'handle']);
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_UPLOADED, $order->status);
        $this->assertNull($order->gcode_path);
        $this->assertNull($order->timings);

        // a run overtaken while the slicer works (the customer changes a choice during the minutes of Orca): its
        // result goes nowhere – no price, no "sliced" for choices nobody wants
        $inner = app(Slicer::class);
        $this->app->instance(Slicer::class, new class($inner, $order->id) implements Slicer
        {
            public function __construct(private Slicer $inner, private int $orderId) {}

            public function name(): string
            {
                return $this->inner->name();
            }

            public function supportedFormats(): array
            {
                return $this->inner->supportedFormats();
            }

            public function measure(string $meshPath): Dimensions
            {
                return $this->inner->measure($meshPath);
            }

            public function slice(string $meshPath, SliceParams $params): SliceResult
            {
                FarmOrder::whereKey($this->orderId)->increment('slice_run');   // run 3 asked for while run 2 slices

                return $this->inner->slice($meshPath, $params);
            }
        });
        app()->call([$second, 'handle']);
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_UPLOADED, $order->status);
        $this->assertSame(3, $order->slice_run);
        $this->assertNull($order->est_minutes);
        $this->assertNull($order->timings, 'an overtaken run leaves the timings to the run that replaced it');
        $this->app->instance(Slicer::class, $inner);

        // the current run (3) does the slice for the choices as they are now
        app()->call([new PrepareFarmOrder($order->id, 3), 'handle']);
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status);
        $this->assertSame('draft', $order->slice_params['quality']);
        $this->assertSame(3, $order->timings['run']);
        $this->assertNotNull($order->est_minutes);

        // sliced: a change waits for the button as always (the order is no longer being sliced)
        $this->assertFalse($order->sliceRestartable());
        // a small model being sliced cannot be restarted: the choices wait for the button
        $small = FarmOrder::create(['token' => str_repeat('s', 32), 'user_id' => $this->user->id, 'model_file_id' => $order->model_file_id, 'status' => FarmOrder::STATUS_UPLOADED, 'stage' => 'slicing', 'farm_printer_id' => $order->farm_printer_id, 'farm_material_id' => $order->farm_material_id]);
        $this->actingAs($this->user)->postJson("/farm/orders/{$small->token}/reslice", ['quality' => 'fine', 'strength' => 'standard'])->assertStatus(422)->assertJsonPath('error', 'locked');
    }

    public function test_a_small_model_is_sliced_at_once_as_before(): void
    {
        $order = $this->order(['file' => $this->upload()]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status);
        $this->assertFalse($order->big_model);
        $this->assertSame(0, $order->slice_run);
        $this->assertSame([[null, 'uploaded'], ['uploaded', 'sliced']], $order->events->map(fn ($e) => [$e->from, $e->to])->all());
        $state = $this->state($order);
        $this->assertFalse($state['big']);
        $this->assertTrue($state['choosing']);
        $this->assertNull($state['estimate']);
        $this->assertNull($state['slice_minutes']);
        $this->assertNotNull($state['colors'][0]['price']);
        // a recalculation runs the ordinary way and counts the runs
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", $this->choices($order, ['quality' => 'draft']))->assertOk()->assertJsonPath('status', 'sliced')->assertJsonPath('slice_run', 1);
    }

    public function test_a_waiting_order_is_closed_after_a_week_and_a_closed_farm_does_not_start_the_slice(): void
    {
        config(['engines.mesh.decimate_above' => 5]);
        $old = $this->order(['file' => $this->upload()]);
        $fresh = $this->order(['file' => $this->upload()]);
        $old->forceFill(['created_at' => now()->subDays(8)])->save();

        $this->artisan('matplace:prune', ['--dry-run' => true])->expectsOutputToContain('farm orders waiting for choices cancelled: 1')->assertSuccessful();
        $this->assertSame(FarmOrder::STATUS_AWAITING_CHOICES, $old->fresh()->status, 'a dry run only counts');
        $this->artisan('matplace:prune')->expectsOutputToContain('farm orders waiting for choices cancelled: 1')->assertSuccessful();
        $this->assertSame(FarmOrder::STATUS_CANCELLED, $old->fresh()->status);
        $this->assertSame('no choices within 7 days', $old->events()->reorder()->latest('id')->first()->note);
        $this->assertSame(FarmOrder::STATUS_AWAITING_CHOICES, $fresh->fresh()->status);
        Mail::assertNothingQueued();

        // the farm closed while the order waited: a customer cannot start the slice, an admin can
        app(FarmSettings::class)->set('farm_open', false);
        config(['farm.open' => false]);
        $this->actingAs($this->user)->postJson("/farm/orders/{$fresh->token}/reslice", $this->choices($fresh))->assertStatus(422)->assertJsonPath('error', 'closed');
        $this->assertSame(FarmOrder::STATUS_AWAITING_CHOICES, $fresh->fresh()->status);
        $this->user->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($this->user->fresh())->postJson("/farm/orders/{$fresh->token}/reslice", $this->choices($fresh))->assertOk()->assertJsonPath('status', 'sliced');

        // the customer may walk away from a waiting order
        config(['farm.open' => true]);
        $another = $this->order(['file' => $this->upload()]);
        $this->actingAs($this->user)->postJson("/farm/orders/{$another->token}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');
    }
}
