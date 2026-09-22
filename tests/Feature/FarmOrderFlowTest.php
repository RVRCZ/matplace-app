<?php

namespace Tests\Feature;

use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\Wallet;
use App\Engines\Mesh\StlFile;
use App\Mail\FarmAdminAlert;
use App\Mail\FarmOrderStatus;
use App\Models\FarmAgent;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrintJob;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/** Rent a printer, end to end: upload → slice → credit → pay → queue → agent prints → done. Fake slicer, fake gateway, sync queue. */
class FarmOrderFlowTest extends TestCase
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

    private function upload(float $size = 20.0, ?callable $writer = null): string
    {
        $path = sys_get_temp_dir().'/mp_farm_'.uniqid().'.stl';
        $writer ? $writer($path) : MeshFixtures::cubeStl($path, $size);

        return $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated()->json('file.uuid');
    }

    private function order(float $size = 20.0, array $extra = []): FarmOrder
    {
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $this->upload($size)] + $extra)->assertCreated();

        return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
    }

    private function credit(int $amount): void
    {
        $this->actingAs($this->user)->post('/account/credit', ['amount' => $amount])->assertRedirect();
        $payment = Payment::latest('id')->firstOrFail();
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
    }

    private function pay(FarmOrder $order, array $over = [])
    {
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->json();

        return $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", $over + [
            'slot' => $state['colors'][0]['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $state['colors'][0]['total'],
        ]);
    }

    private function agentPrinter(): array
    {
        [$agent, $token] = FarmAgent::issue('test agent');
        $printer = FarmPrinter::firstOrFail();
        $printer->update(['mode' => FarmPrinter::MODE_AGENT, 'farm_agent_id' => $agent->id]);

        return [$printer, ['Authorization' => 'Bearer '.$token]];
    }

    private function sync(array $headers, string $state = 'idle', ?array $job = null)
    {
        return $this->postJson('/api/agent/sync', ['version' => 'test', 'printers' => [['key' => 'kobra-s1-01', 'state' => $state, 'telemetry' => ['extruder' => 215.2, 'bed' => 55], 'job' => $job]]], $headers);
    }

    public function test_guests_cannot_rent_a_printer(): void
    {
        $this->get('/farm')->assertRedirect('/login');
        $this->postJson('/farm/orders', ['file' => '00000000-0000-0000-0000-000000000000'])->assertUnauthorized();
    }

    public function test_order_is_sliced_priced_and_reproducible(): void
    {
        $order = $this->order();

        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status);
        $this->assertGreaterThan(0, $order->est_minutes);
        $this->assertGreaterThan(0, $order->est_grams);
        $this->assertGreaterThan(0, $order->est_meters);
        $this->assertGreaterThanOrEqual(99 * 1.21, $order->price_total, 'minimum price with VAT');
        Storage::disk('farm')->assertExists($order->gcode_path);
        Storage::disk('farm')->assertExists($order->print_stl_path);
        $this->assertSame(hash_file('sha256', $order->absoluteGcodePath()), $order->gcode_sha256);
        // everything needed to slice the same thing again
        $this->assertSame(0.2, $order->slice_params['layer_mm']);
        $this->assertSame(15, $order->slice_params['infill_percent']);
        $this->assertSame(['0x0', '250x0', '250x250', '0x250'], $order->slice_params['overrides']['machine']['printable_area']);
        $this->assertSame('0', $order->slice_params['overrides']['process']['enable_prime_tower']);
        $this->assertArrayHasKey('machine', $order->slice_params['profile_hashes']);
    }

    public function test_presets_map_to_layer_and_infill_and_a_reslice_changes_the_price_inputs(): void
    {
        $order = $this->order(20, ['quality' => 'fine', 'strength' => 'high']);
        $this->assertSame(0.12, $order->slice_params['layer_mm']);
        $this->assertSame(30, $order->slice_params['infill_percent']);

        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", ['quality' => 'draft', 'strength' => 'low'])->assertOk();
        $order->refresh();
        $this->assertSame(0.28, $order->slice_params['layer_mm']);
        $this->assertSame(10, $order->slice_params['infill_percent']);
    }

    public function test_model_bigger_than_the_plate_is_refused_with_a_reason(): void
    {
        $order = $this->order(300);
        $this->assertSame(FarmOrder::STATUS_FAILED, $order->status);
        $this->assertSame('exceeds_bed', $order->error);
        $this->assertNull($order->gcode_path);
    }

    public function test_model_with_a_hole_is_refused_when_it_cannot_be_repaired(): void
    {
        $uuid = $this->upload(writer: function (string $path) {
            MeshFixtures::cubeStl($path.'.full', 20);
            $fh = StlFile::beginBinary($path);
            $n = 0;
            foreach (StlFile::triangles($path.'.full') as $i => [$a, $b, $c]) {
                if ($i >= 2) {   // drop the two bottom triangles
                    StlFile::writeTriangle($fh, $a, $b, $c);
                    $n++;
                }
            }
            StlFile::endBinary($fh, $n);
        });
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $uuid])->assertCreated();
        $order = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();

        $this->assertSame('not_watertight', $order->error);   // the PHP fallback checks but cannot repair
        $this->assertSame(4, $order->check['errors'][0]['data']['open_edges']);
    }

    public function test_paying_needs_credit_and_the_webhook_is_the_only_way_to_get_it(): void
    {
        $order = $this->order();

        $this->pay($order)->assertStatus(402)->assertJsonPath('error', 'credit');
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->refresh()->status);

        // a forged webhook credits nothing
        $this->actingAs($this->user)->post('/account/credit', ['amount' => 500]);
        $ref = Payment::latest('id')->value('gateway_ref');
        $this->postJson('/webhooks/payments/fake', ['ref' => $ref, 'paid' => true], ['X-Fake-Signature' => 'nope'])->assertStatus(400);
        $this->assertSame(0.0, app(Wallet::class)->balance($this->user));

        $this->postJson('/webhooks/payments/fake', ['ref' => $ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $this->postJson('/webhooks/payments/fake', ['ref' => $ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $this->assertSame(500.0, app(Wallet::class)->balance($this->user));
    }

    public function test_terms_must_be_accepted_and_a_changed_price_is_never_charged_silently(): void
    {
        $order = $this->order();
        $this->credit(1000);

        $this->pay($order, ['terms' => false])->assertStatus(422);
        $this->pay($order, ['expected_total' => 1])->assertStatus(422)->assertJsonPath('error', 'price_changed');
        $this->assertSame(1000.0, app(Wallet::class)->balance($this->user));
    }

    public function test_manual_printer_order_is_paid_queued_and_the_operator_is_told(): void
    {
        $order = $this->order();
        $this->credit(1000);

        $this->pay($order)->assertOk()->assertJsonPath('status', 'queued');
        $order->refresh();
        $this->assertMatchesRegularExpression('/^F\d{2}-000001$/', $order->number);
        $this->assertNotNull($order->terms_accepted_at);
        $this->assertSame(round(1000 - $order->price_total, 2), app(Wallet::class)->balance($this->user));
        Mail::assertQueued(FarmAdminAlert::class);
        Mail::assertQueued(FarmOrderStatus::class, fn ($m) => $m->status === 'queued');

        // the customer changes their mind before the print: everything comes back
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertSame(1000.0, app(Wallet::class)->balance($this->user));
    }

    public function test_approval_mode_holds_the_order_until_an_admin_lets_it_through(): void
    {
        app(FarmSettings::class)->set('require_approval', true);
        $order = $this->order();
        $this->credit(1000);
        $this->pay($order)->assertOk()->assertJsonPath('status', 'paid');

        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin)->post("/admin/farm/orders/{$order->token}/approve")->assertRedirect();
        $this->assertSame(FarmOrder::STATUS_QUEUED, $order->refresh()->status);
    }

    public function test_agent_prints_only_after_the_plate_was_declared_clear_and_reports_back(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        $this->sync($auth)->assertOk();                         // printer online before the customer comes
        $order = $this->order();
        $this->credit(1000);
        $this->pay($order)->assertOk()->assertJsonPath('status', 'queued');

        // plate not confirmed: nothing starts, however idle the printer is
        $this->sync($auth)->assertOk()->assertJsonCount(0, 'commands');
        $this->assertSame(0, FarmPrintJob::count());

        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin)->post("/admin/farm/printers/{$printer->id}/bed", ['clear' => 1])->assertRedirect();
        $this->assertFalse($printer->refresh()->bed_clear, 'starting consumes the confirmation');

        $cmd = $this->sync($auth)->assertOk()->assertJsonCount(1, 'commands')->json('commands.0');
        $this->assertSame('start', $cmd['type']);
        $job = FarmPrintJob::findOrFail($cmd['job_id']);
        $this->assertSame('sent', $job->status);

        $gcode = $this->get($cmd['payload']['gcode_url'], $auth)->assertOk();
        // the customer's colour sits in slot 3: the agent gets the copy that selects it, with its own checksum
        $this->assertSame(2, $cmd['payload']['slot']);
        $this->assertSame(hash('sha256', $gcode->streamedContent()), $gcode->headers->get('X-Content-Sha256'));
        $this->assertStringContainsString("
T2 ; slot chosen by matplace farm
", $gcode->streamedContent());
        $this->postJson("/api/agent/commands/{$cmd['id']}/result", ['ok' => true], $auth)->assertOk();

        $this->sync($auth, 'printing', ['id' => $job->id, 'status' => 'printing', 'progress' => 42.5, 'print_duration' => 600, 'filament_used' => 1200])->assertOk();
        $this->assertSame(FarmOrder::STATUS_PRINTING, $order->refresh()->status);
        $this->assertSame(42.5, $job->refresh()->progress);

        $this->sync($auth, 'idle', ['id' => $job->id, 'status' => 'done', 'progress' => 100, 'print_duration' => 2400, 'filament_used' => 3600])->assertOk();
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_DONE, $order->status);
        $this->assertSame(40, $order->actual_minutes);
        $this->assertEqualsWithDelta(10.7, $order->actual_grams, 0.1);   // 3.6 m of 1.75 mm PLA
        $this->assertSame('agent', $order->actual_source);
        $this->assertDatabaseHas('credit_transactions', ['farm_order_id' => $order->id, 'type' => 'capture']);
        $this->assertEqualsWithDelta(900 - $order->actual_grams, $order->slot->refresh()->remaining_g, 0.01);   // the seeded spool holds 900 g

        // the plate is full now: the next order waits for a person
        $this->sync($auth)->assertJsonCount(0, 'commands');
    }

    public function test_failed_print_returns_the_credit_and_alerts_the_admin(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        $this->sync($auth);
        $order = $this->order();
        $this->credit(1000);
        $this->pay($order);
        $printer->update(['bed_clear' => true]);
        $cmd = $this->sync($auth)->json('commands.0');

        $this->sync($auth, 'error', ['id' => $cmd['job_id'], 'status' => 'failed', 'message' => 'Heater extruder not heating']);
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_FAILED, $order->status);
        $this->assertSame('print_failed', $order->error);
        $this->assertSame(1000.0, app(Wallet::class)->balance($this->user));
        Mail::assertQueued(FarmAdminAlert::class);
    }

    public function test_agent_api_needs_a_valid_token_and_sees_only_its_own_printers(): void
    {
        $this->postJson('/api/agent/sync', ['printers' => []])->assertUnauthorized();
        $this->postJson('/api/agent/sync', ['printers' => []], ['Authorization' => 'Bearer mpa_wrong'])->assertUnauthorized();

        [, $token] = FarmAgent::issue('stranger');   // a valid agent that owns no printer
        $this->sync(['Authorization' => 'Bearer '.$token])->assertOk()->assertJsonCount(0, 'printers');
        $this->assertNull(FarmPrinter::firstOrFail()->last_seen_at);
    }

    public function test_silent_agent_turns_the_printer_offline_and_the_job_unknown(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        $this->sync($auth);
        $order = $this->order();
        $this->credit(1000);
        $this->pay($order);
        $printer->update(['bed_clear' => true]);
        $cmd = $this->sync($auth)->json('commands.0');
        $this->sync($auth, 'printing', ['id' => $cmd['job_id'], 'status' => 'printing', 'progress' => 10]);

        $this->travel(5)->minutes();
        $this->artisan('farm:watch')->assertSuccessful();

        $this->assertFalse($printer->refresh()->isOnline());
        $this->assertSame('unknown', FarmPrintJob::findOrFail($cmd['job_id'])->status);
        $this->assertSame(FarmOrder::STATUS_PRINTING, $order->refresh()->status, 'nobody knows yet: the order is not failed by a lost connection alone');
        Mail::assertQueued(FarmAdminAlert::class, fn ($m) => str_contains($m->subjectLine, 'farm.admin.mail.offline') || str_contains($m->subjectLine, $printer->name));
    }

    public function test_daily_limit_stops_further_slicing(): void
    {
        app(FarmSettings::class)->set('daily_slices_per_user', 1);
        $this->order();
        $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $this->upload()])->assertStatus(422)->assertJsonPath('error', 'daily_limit');
    }

    public function test_somebody_elses_order_does_not_exist_for_me(): void
    {
        $order = $this->order();
        $this->actingAs(User::factory()->create())->getJson("/farm/orders/{$order->token}/status")->assertNotFound();
    }
}
