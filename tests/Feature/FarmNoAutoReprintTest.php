<?php

namespace Tests\Feature;

use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\PrinterStatus;
use App\Domain\Farm\Wallet;
use App\Mail\FarmAdminAlert;
use App\Mail\FarmOrderStatus;
use App\Models\FarmAgent;
use App\Models\FarmCommand;
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

/**
 * After a print is stopped, nothing prints again by itself (docs/AL.md §25; F26-000162 on U2, 13 Nov 2026: the print
 * was stopped on the printer's screen, the plate cleared and released, and the order started again on its own).
 *
 *  - a print that ends on the printer unfinished (stopped there, the print cancelled from the printer's card, the
 *    printer's own failure) leaves the paid order "stopped": the money stays held, the customer is told it does not
 *    print, the operator is mailed, and no release of the plate starts it;
 *  - "Vytisknout znovu" starts it, "Zrušit zakázku" gives all the money back;
 *  - the one automatic reprint that is left (the printer's failure before the first layer) is a switch of the farm's
 *    settings, off by default;
 *  - a printer's message is shown only while its last report says it;
 *  - one resume is enough: while the agent carries a pause or a resume out, no second one can be sent.
 */
class FarmNoAutoReprintTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        config(['farm.settings.admin_email' => 'farm@example.com']);
        app()->setLocale('cs');
        $this->user = User::factory()->create(['email' => 'customer@example.com', 'name' => 'Jana Nováková']);
        $this->admin = User::factory()->create();
        $this->admin->setRole(User::ROLE_ADMIN, true);
    }

    private function order(): FarmOrder
    {
        $path = sys_get_temp_dir().'/mp_nostop_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);
        $uuid = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated()->json('file.uuid');

        return FarmOrder::where('token', basename($this->actingAs($this->user)->postJson('/farm/orders', ['file' => $uuid])->assertCreated()->json('url')))->firstOrFail();
    }

    private function pay(FarmOrder $order): FarmOrder
    {
        $this->actingAs($this->user)->post('/account/credit', ['amount' => 1000])->assertRedirect();
        $payment = Payment::latest('id')->firstOrFail();
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->json();
        $color = collect($state['colors'])->firstWhere('slot', $state['slot']) ?? $state['colors'][0];
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $color['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $color['total']])->assertOk();

        return $order->refresh();
    }

    /** @return array{0: FarmPrinter, 1: array<string,string>} */
    private function agentPrinter(): array
    {
        [$agent, $token] = FarmAgent::issue('test agent');
        $printer = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $printer->update(['mode' => FarmPrinter::MODE_AGENT, 'farm_agent_id' => $agent->id]);

        return [$printer, ['Authorization' => 'Bearer '.$token]];
    }

    private function sync(array $headers, string $state = 'idle', ?array $job = null, array $telemetry = ['extruder' => 215.2])
    {
        return $this->postJson('/api/agent/sync', ['version' => '0.2.15', 'printers' => [['key' => 'kobra-s1-01', 'state' => $state, 'telemetry' => $telemetry, 'job' => $job]]], $headers);
    }

    /** A paid order printing on the agent's printer. @return array{0: FarmOrder, 1: FarmPrintJob} */
    private function printing(FarmPrinter $printer, array $auth): array
    {
        $this->sync($auth);
        $order = $this->pay($this->order());
        $printer->refresh()->update(['bed_clear' => true]);
        $cmd = $this->sync($auth)->json('commands.0');
        $this->assertSame('start', $cmd['type']);
        $this->assertSame($order->number, $cmd['payload']['order']);
        $this->postJson("/api/agent/commands/{$cmd['id']}/result", ['ok' => true], $auth)->assertOk();
        $this->sync($auth, 'printing', ['id' => $cmd['job_id'], 'status' => 'printing', 'progress' => 0]);
        $this->assertSame(FarmOrder::STATUS_PRINTING, $order->refresh()->status);

        return [$order, FarmPrintJob::findOrFail($cmd['job_id'])];
    }

    /** The print is stopped on the printer's screen at 12 %: the agent reports the job as cancelled, the printer idle. */
    private function stoppedAtThePrinter(FarmPrinter $printer, array $auth): array
    {
        [$order, $job] = $this->printing($printer, $auth);
        $this->sync($auth, 'printing', ['id' => $job->id, 'status' => 'printing', 'progress' => 12]);
        $this->sync($auth, 'idle', ['id' => $job->id, 'status' => 'cancelled', 'progress' => 12, 'message' => '']);

        return [$order->refresh(), $job->refresh()];
    }

    private function balance(): float
    {
        return app(Wallet::class)->balance($this->user->refresh())->amount;
    }

    private function starts(): int
    {
        return FarmCommand::where('type', FarmCommand::TYPE_START)->count();
    }

    /** @return list<FarmAdminAlert> */
    private function alerts(string $needle): array
    {
        return Mail::queued(FarmAdminAlert::class)->filter(fn (FarmAdminAlert $m) => str_contains($m->subjectLine, $needle))->values()->all();
    }

    private function statusMails(string $status): int
    {
        return Mail::queued(FarmOrderStatus::class)->filter(fn (FarmOrderStatus $m) => $m->status === $status && $m->hasTo('customer@example.com'))->count();
    }

    // ── 1: stopped at the printer → no start, whatever happens to the plate ──────────────────────────────────────────

    public function test_a_print_stopped_at_the_printer_waits_for_the_operator_and_no_plate_release_starts_it(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        [$order, $job] = $this->stoppedAtThePrinter($printer, $auth);
        $held = $this->balance();

        $this->assertSame(FarmOrder::STATUS_STOPPED, $order->status);
        $this->assertSame('print_cancelled', $order->error);
        $this->assertSame(0, (int) $order->reprints);
        $this->assertSame(FarmPrintJob::STATUS_CANCELLED, $job->status);
        $notes = $order->events->pluck('note')->filter()->implode(' | ');
        $this->assertStringNotContainsString('automatic reprint', $notes);
        $this->assertSame([FarmOrder::STATUS_PRINTING, FarmOrder::STATUS_STOPPED], [$order->events->last()->from, $order->events->last()->to]);
        // the money is neither spent nor given back
        $this->assertLessThan(1000.0, $held);
        $this->assertSame($held, $this->balance());
        // the operator is mailed once (kind "problem"), the customer is told the truth and nothing about a refund
        $this->assertCount(1, $this->alerts('tisk zastaven, čeká na váš pokyn'));
        $this->assertStringContainsString('sama znovu nespustí', implode("\n", $this->alerts('tisk zastaven')[0]->lines));
        $this->assertSame(1, $this->statusMails(FarmOrder::STATUS_STOPPED));
        $this->assertSame(0, $this->statusMails(FarmOrder::STATUS_FAILED));
        $this->assertSame(0, $this->statusMails(FarmOrderStatus::REPRINT));
        $mail = (new FarmOrderStatus($order, FarmOrder::STATUS_STOPPED))->render();
        $this->assertStringContainsString('se zastavil', $mail);
        $this->assertStringNotContainsString('vrátili', $mail);
        $this->assertStringNotContainsString('tiskne se', mb_strtolower($mail));

        // the plate is cleared and released in the admin, exactly as on 13 Nov: nothing starts
        $before = $this->starts();
        $this->actingAs($this->admin)->postJson(route('admin.farm.printers.bed', $printer), ['clear' => 1])->assertOk();
        $this->assertTrue($printer->refresh()->bed_clear);
        $this->assertSame([], $this->sync($auth)->json('commands'));
        $this->assertSame([], $this->sync($auth)->json('commands'));
        // and neither does the confirmation made on the printer itself (telemetry.bed_cleared_at)
        $printer->update(['bed_clear' => false]);
        $this->travel(2)->minutes();
        $this->assertSame([], $this->sync($auth, 'idle', null, ['bed_cleared_at' => now()->timestamp])->json('commands'));
        $this->assertTrue($printer->refresh()->bed_clear);
        $this->assertSame([], $this->sync($auth)->json('commands'));
        $this->assertSame($before, $this->starts());
        $this->assertSame(FarmOrder::STATUS_STOPPED, $order->refresh()->status);
        $this->assertFalse($order->printJobs()->whereIn('status', FarmPrintJob::ACTIVE)->exists());

        // the customer's page: stopped, with the sentence, no times, nothing that says it prints
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $this->assertSame('stopped', $state['status']);
        $this->assertSame('Tisk zastaven, čeká na pokyn obsluhy', $state['status_text']);
        $this->assertStringContainsString('teď se netiskne', $state['stopped']);
        $this->assertNull($state['queue']);
        $this->assertNull($state['error_text'], 'the texts of a failure promise a refund that has not happened');
        $this->assertTrue($state['can_cancel']);
        $this->assertFalse($state['final']);
        $this->actingAs($this->user)->get("/farm/orders/{$order->token}")->assertOk();
        $this->actingAs($this->user)->get(route('account.orders', ['status' => 'open']))->assertOk()->assertSee($order->number);

        // the admin: the order's page with the two buttons, the list "Čeká na vás", the printer's row and its detail
        $this->actingAs($this->admin)->get(route('admin.farm.orders.show', $order))->assertOk()
            ->assertSee('data-stopped', false)->assertSee('Tisk zastaven, čeká na váš pokyn')->assertSee('Vytisknout znovu')->assertSee('Zrušit zakázku')
            ->assertSee(route('admin.farm.orders.reprint', $order), false);
        $this->actingAs($this->admin)->get(route('admin.farm.dashboard'))->assertOk()
            ->assertSee('data-stopped-order="'.$order->number.'"', false)->assertSee('Tisk zastaven, čeká na pokyn obsluhy');
        $this->actingAs($this->admin)->get(route('admin.farm.printers.show', $printer))->assertOk()->assertSee('data-stopped-order="'.$order->number.'"', false);
        $this->actingAs($this->admin)->get(route('admin.farm.orders', ['status' => 'stopped']))->assertOk()->assertSee($order->number);
    }

    public function test_the_next_order_in_the_queue_prints_and_the_stopped_one_stays(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        [$stopped] = $this->stoppedAtThePrinter($printer, $auth);
        // another paid order of the same printer starts once the plate is confirmed: the queue goes on, the stopped order is not in it
        [$next] = $this->printing($printer, $auth);
        $this->assertNotSame($stopped->id, $next->id);
        $this->assertSame(FarmOrder::STATUS_STOPPED, $stopped->refresh()->status);
        $this->assertSame(1, $stopped->printJobs()->count());
    }

    public function test_a_print_cancelled_from_the_printers_card_stops_the_order_too(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        [$order, $job] = $this->printing($printer, $auth);
        // "Zrušit tisk" on the printer's card cancels the print, not the order
        $this->actingAs($this->admin)->postJson(route('admin.farm.printers.command', $printer), ['type' => 'cancel'])->assertOk();
        $cmd = $this->sync($auth, 'printing', ['id' => $job->id, 'status' => 'printing', 'progress' => 30])->json('commands.0');
        $this->assertSame('cancel', $cmd['type']);
        $this->postJson("/api/agent/commands/{$cmd['id']}/result", ['ok' => true], $auth)->assertOk();
        $this->sync($auth, 'idle', ['id' => $job->id, 'status' => 'cancelled', 'progress' => 30, 'message' => "cancelled on the farm's command"]);

        $this->assertSame(FarmOrder::STATUS_STOPPED, $order->refresh()->status);
        $printer->refresh()->update(['bed_clear' => true]);
        $this->assertSame([], $this->sync($auth)->json('commands'));
        $this->assertSame(1, $this->starts());
    }

    // ── 2: the admin's word starts it ────────────────────────────────────────────────────────────────────────────────

    public function test_print_again_starts_the_stopped_order_on_the_admins_word(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        [$order] = $this->stoppedAtThePrinter($printer, $auth);
        $held = $this->balance();
        // not the customer's button
        $this->assertContains($this->actingAs($this->user)->post(route('admin.farm.orders.reprint', $order))->status(), [302, 403, 404]);
        $this->assertSame(FarmOrder::STATUS_STOPPED, $order->refresh()->status);

        // the plate is not confirmed yet: back in the queue, nothing starts until it is
        $this->assertFalse($printer->refresh()->bed_clear);
        $this->actingAs($this->admin)->post(route('admin.farm.orders.reprint', $order))->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, 'je zpět ve frontě'));
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_QUEUED, $order->status);
        $this->assertSame(1, (int) $order->reprints);
        $this->assertNull($order->error);
        $this->assertStringContainsString('reprint 1 on the word of the operator', $order->events->pluck('note')->filter()->implode(' | '));
        $this->assertSame('admin', $order->events->last()->actor);
        $this->assertSame($held, $this->balance(), 'the money stays held');
        $this->assertSame(1, $this->statusMails(FarmOrderStatus::REPRINT));
        $this->assertSame([], $this->sync($auth)->json('commands'));

        $this->actingAs($this->admin)->postJson(route('admin.farm.printers.bed', $printer), ['clear' => 1])->assertOk();
        $cmd = $this->sync($auth)->json('commands.0');
        $this->assertSame('start', $cmd['type']);
        $this->assertSame($order->number, $cmd['payload']['order']);
        $this->assertSame(2, $order->printJobs()->count());
        $this->postJson("/api/agent/commands/{$cmd['id']}/result", ['ok' => true], $auth)->assertOk();
        $this->sync($auth, 'printing', ['id' => $cmd['job_id'], 'status' => 'printing', 'progress' => 1]);
        $this->assertSame(FarmOrder::STATUS_PRINTING, $order->refresh()->status);
        // printed to the end: paid as any other order
        $this->sync($auth, 'idle', ['id' => $cmd['job_id'], 'status' => 'done', 'progress' => 100, 'print_duration' => 1800, 'filament_used' => 2000]);
        $this->assertSame(FarmOrder::STATUS_DONE, $order->refresh()->status);
        $this->assertSame($held, $this->balance());
    }

    public function test_print_again_starts_at_once_on_a_free_printer_with_the_plate_confirmed(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        [$order] = $this->stoppedAtThePrinter($printer, $auth);
        $this->actingAs($this->admin)->postJson(route('admin.farm.printers.bed', $printer), ['clear' => 1])->assertOk();
        $this->assertSame(1, $this->starts());

        $this->actingAs($this->admin)->post(route('admin.farm.orders.reprint', $order))->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, 'se znovu tiskne'));
        $this->assertSame(2, $this->starts());
        $this->assertSame('start', $this->sync($auth)->json('commands.0.type'));
        // a second press does nothing: the order is not stopped any more
        $this->actingAs($this->admin)->post(route('admin.farm.orders.reprint', $order))->assertRedirect()->assertSessionHas('error');
        $this->assertSame(1, (int) $order->refresh()->reprints);
    }

    public function test_an_order_stopped_twice_waits_twice(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        [$order] = $this->stoppedAtThePrinter($printer, $auth);
        $printer->refresh()->update(['bed_clear' => true]);
        $this->actingAs($this->admin)->post(route('admin.farm.orders.reprint', $order))->assertRedirect();
        $cmd = $this->sync($auth)->json('commands.0');
        $this->postJson("/api/agent/commands/{$cmd['id']}/result", ['ok' => true], $auth)->assertOk();
        $this->sync($auth, 'printing', ['id' => $cmd['job_id'], 'status' => 'printing', 'progress' => 5]);
        $this->sync($auth, 'idle', ['id' => $cmd['job_id'], 'status' => 'cancelled', 'progress' => 5, 'message' => '']);

        $this->assertSame(FarmOrder::STATUS_STOPPED, $order->refresh()->status);
        $this->assertSame(2, $this->statusMails(FarmOrder::STATUS_STOPPED));
        $printer->refresh()->update(['bed_clear' => true]);
        $this->assertSame([], $this->sync($auth)->json('commands'));
    }

    // ── 3: cancelling the stopped order ──────────────────────────────────────────────────────────────────────────────

    public function test_cancel_the_order_gives_all_the_money_back(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        [$order] = $this->stoppedAtThePrinter($printer, $auth);
        $this->assertLessThan(1000.0, $this->balance());

        $this->actingAs($this->admin)->post(route('admin.farm.orders.status', $order), ['to' => 'cancelled'])->assertRedirect();
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_CANCELLED, $order->status);
        $this->assertSame(1000.0, $this->balance(), 'nothing is kept for the printed part: the farm stopped the print');
        $this->assertArrayNotHasKey('charged_on_cancel', (array) $order->price);
        $this->assertSame(1, $this->statusMails(FarmOrder::STATUS_CANCELLED));
        // the print had ended already: no cancel goes to the printer, nothing starts
        $this->assertSame(0, FarmCommand::where('type', FarmCommand::TYPE_CANCEL)->count());
        $printer->refresh()->update(['bed_clear' => true]);
        $this->assertSame([], $this->sync($auth)->json('commands'));
        $this->actingAs($this->admin)->get(route('admin.farm.orders.show', $order))->assertOk()->assertDontSee('data-stopped', false);
    }

    public function test_the_customer_can_cancel_a_stopped_order_and_gets_everything_back(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        [$order] = $this->stoppedAtThePrinter($printer, $auth);

        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertSame(1000.0, $this->balance());
        $this->assertCount(1, $this->alerts($order->number.' zrušil zákazník'));
    }

    // ── 4: the printer's own failure, and the switch ─────────────────────────────────────────────────────────────────

    public function test_a_failure_of_the_printer_waits_for_the_operator_by_default(): void
    {
        $this->assertFalse((bool) app(FarmSettings::class)->get('auto_reprint_start'), 'off unless the admin switches it on');
        [$printer, $auth] = $this->agentPrinter();
        [$order, $job] = $this->printing($printer, $auth);
        $held = $this->balance();
        // before the first per cent, with the printer's reason: believed at once
        $this->sync($auth, 'error', ['id' => $job->id, 'status' => 'failed', 'progress' => 0, 'message' => 'MCU shutdown: heater fault']);

        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_STOPPED, $order->status);
        $this->assertSame('print_failed', $order->error);
        $this->assertSame('MCU shutdown: heater fault', $order->error_detail);
        $this->assertSame(0, (int) $order->reprints);
        $this->assertSame($held, $this->balance());
        $this->assertSame(FarmPrintJob::STATUS_FAILED, $job->refresh()->status);
        $this->assertStringContainsString('heater fault', implode("\n", $this->alerts('tisk zastaven')[0]->lines));
        $this->sync($auth);
        $printer->refresh()->update(['bed_clear' => true]);
        $this->assertSame([], $this->sync($auth)->json('commands'));
    }

    public function test_with_the_switch_on_a_failure_before_the_first_layer_goes_back_to_the_queue_once(): void
    {
        config(['farm.settings.auto_reprint_start' => true]);
        [$printer, $auth] = $this->agentPrinter();
        [$order, $job] = $this->printing($printer, $auth);
        $held = $this->balance();
        $this->sync($auth, 'error', ['id' => $job->id, 'status' => 'failed', 'progress' => 0, 'message' => 'MCU shutdown: heater fault']);

        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_QUEUED, $order->status);
        $this->assertSame(1, (int) $order->reprints);
        $this->assertStringContainsString('automatic reprint 1 after print_failed', $order->events->pluck('note')->filter()->implode(' | '));
        $this->assertSame($held, $this->balance());
        $this->assertSame(1, $this->statusMails(FarmOrderStatus::REPRINT));
        $this->assertSame(0, $this->statusMails(FarmOrder::STATUS_FAILED));
        // it starts like any queued order: only once the plate is confirmed
        $this->assertSame([], $this->sync($auth)->json('commands'));
        $printer->refresh()->update(['bed_clear' => true]);
        $cmd = $this->sync($auth)->json('commands.0');
        $this->assertSame('start', $cmd['type']);
        $this->postJson("/api/agent/commands/{$cmd['id']}/result", ['ok' => true], $auth)->assertOk();
        $this->sync($auth, 'printing', ['id' => $cmd['job_id'], 'status' => 'printing', 'progress' => 0]);
        // the second failure waits for the operator: once is once
        $this->sync($auth, 'error', ['id' => $cmd['job_id'], 'status' => 'failed', 'progress' => 0, 'message' => 'MCU shutdown: heater fault']);
        $this->assertSame(FarmOrder::STATUS_STOPPED, $order->refresh()->status);
        $this->assertSame(1, (int) $order->reprints);
        $this->assertSame($held, $this->balance());
    }

    public function test_the_switch_never_covers_a_stop_by_a_person_nor_a_failure_in_the_middle_of_a_print(): void
    {
        config(['farm.settings.auto_reprint_start' => true]);
        [$printer, $auth] = $this->agentPrinter();

        // stopped at the printer before the first per cent: a person's decision
        [$order, $job] = $this->printing($printer, $auth);
        $this->sync($auth, 'idle', ['id' => $job->id, 'status' => 'cancelled', 'progress' => 0, 'message' => '']);
        $this->assertSame(FarmOrder::STATUS_STOPPED, $order->refresh()->status);
        $this->assertSame(0, (int) $order->reprints);

        // the printer fails at 40 %: plastic is on the plate, the operator decides
        [$second, $job2] = $this->printing($printer, $auth);
        $this->sync($auth, 'printing', ['id' => $job2->id, 'status' => 'printing', 'progress' => 40]);
        $this->sync($auth, 'error', ['id' => $job2->id, 'status' => 'failed', 'progress' => 40, 'message' => 'filament hub feed filament timeout']);
        $this->assertSame(FarmOrder::STATUS_STOPPED, $second->refresh()->status);
        $this->assertSame(0, (int) $second->reprints);
    }

    public function test_the_hard_switch_in_the_configuration_wins_over_the_setting(): void
    {
        config(['farm.settings.auto_reprint_start' => true, 'farm.payments.auto_reprint' => false]);
        [$printer, $auth] = $this->agentPrinter();
        [$order, $job] = $this->printing($printer, $auth);
        $this->sync($auth, 'error', ['id' => $job->id, 'status' => 'failed', 'progress' => 0, 'message' => 'MCU shutdown: heater fault']);
        $this->assertSame(FarmOrder::STATUS_STOPPED, $order->refresh()->status);
    }

    public function test_the_switch_is_a_box_of_the_farms_settings(): void
    {
        $settings = app(FarmSettings::class)->all();
        $form = ['topup_amounts' => implode(', ', $settings['topup_amounts']), 'qualities' => json_encode($settings['qualities']), 'strengths' => json_encode($settings['strengths']), 'delivery_modes' => $settings['delivery_modes'], 'farm_open' => 1]
            + array_intersect_key($settings, array_flip(['hourly_rate', 'vat_percent', 'rounding', 'max_upload_mb', 'daily_slices_per_user', 'min_model_mm', 'bed_margin_mm', 'fixed_fee', 'min_price', 'topup_min', 'topup_max', 'generation_price', 'changeover_minutes', 'offline_after_seconds', 'terms_version', 'admin_email', 'shorts_publish_hour']));
        $this->actingAs($this->admin)->get('/admin/farm/settings')->assertOk()->assertSee('name="auto_reprint_start"', false)->assertDontSee('name="auto_reprint_start" value="1" checked', false);

        $this->actingAs($this->admin)->post('/admin/farm/settings', $form + ['auto_reprint_start' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(app(FarmSettings::class)->get('auto_reprint_start'));
        $this->actingAs($this->admin)->post('/admin/farm/settings', $form)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse(app(FarmSettings::class)->get('auto_reprint_start'));
    }

    // ── 5: what the operator does by hand ────────────────────────────────────────────────────────────────────────────

    public function test_the_operators_failed_ends_the_order_with_the_money_back_and_no_reprint(): void
    {
        config(['farm.settings.auto_reprint_start' => true]);
        [$printer, $auth] = $this->agentPrinter();
        [$order] = $this->printing($printer, $auth);

        $this->actingAs($this->admin)->post(route('admin.farm.orders.status', $order), ['to' => 'failed', 'note' => 'ucpaná tryska'])->assertRedirect();
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_FAILED, $order->status);
        $this->assertSame('operator', $order->error);
        $this->assertSame(0, (int) $order->reprints);
        $this->assertSame(1000.0, $this->balance());
        $this->assertStringNotContainsString('automatic reprint', $order->events->pluck('note')->filter()->implode(' | '));
    }

    public function test_the_operator_can_stop_an_order_by_hand_and_the_printer_gets_a_cancel(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        [$order, $job] = $this->printing($printer, $auth);
        $held = $this->balance();
        $this->actingAs($this->admin)->get(route('admin.farm.orders.show', $order))->assertOk()->assertSee('<option value="stopped">', false);

        $this->actingAs($this->admin)->post(route('admin.farm.orders.status', $order), ['to' => 'stopped', 'note' => 'špatná barva v ACE'])->assertRedirect();
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_STOPPED, $order->status);
        $this->assertSame('operator', $order->error);
        $this->assertSame('špatná barva v ACE', $order->error_detail);
        $this->assertSame($held, $this->balance());
        $this->assertSame(FarmPrintJob::STATUS_CANCELLED, $job->refresh()->status);
        // the machine is told to let go of the print
        $cmd = $this->sync($auth, 'printing', ['id' => $job->id, 'status' => 'printing', 'progress' => 3])->json('commands.0');
        $this->assertSame('cancel', $cmd['type']);
        $this->assertSame($job->id, $cmd['job_id']);
        // the agent's late word about the closed job changes nothing
        $this->sync($auth, 'idle', ['id' => $job->id, 'status' => 'cancelled', 'progress' => 3]);
        $this->assertSame(FarmOrder::STATUS_STOPPED, $order->refresh()->status);
        // the status form takes the order back to the queue the same way the button does
        $this->actingAs($this->admin)->post(route('admin.farm.orders.status', $order), ['to' => 'queued'])->assertRedirect();
        $this->assertSame(FarmOrder::STATUS_QUEUED, $order->refresh()->status);
        $this->assertSame(1, (int) $order->reprints);
    }

    public function test_a_test_print_of_the_farm_still_fails_as_before(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        [$order, $job] = $this->printing($printer, $auth);
        // the farm's own print: nobody's money, nothing to decide
        $order->forceFill(['kind' => FarmOrder::KIND_TEST])->save();
        $this->sync($auth, 'idle', ['id' => $job->id, 'status' => 'cancelled', 'progress' => 12, 'message' => '']);
        $this->assertSame(FarmOrder::STATUS_FAILED, $order->refresh()->status);
    }

    // ── 6: the printer's message is shown only while it is said ──────────────────────────────────────────────────────

    public function test_a_printers_message_lives_only_as_long_as_its_reports_say_it(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        $words = 'ACE busy · ACE slot 2 unwinding';

        // said: stored with its time, a reason on the overview, amber on the printer's card
        $this->sync($auth, 'idle', null, ['extruder' => 30.0, 'message' => $words]);
        $printer->refresh();
        $this->assertSame($words, $printer->message);
        $this->assertNotNull($printer->message_at);
        $this->assertSame('fresh', PrinterStatus::messageKind($printer));
        $this->assertSame(PrinterStatus::PROBLEM, PrinterStatus::of($printer)['key']);
        $this->actingAs($this->admin)->get(route('admin.farm.printers.show', $printer))->assertOk()->assertSee('data-printer-message="fresh"', false)->assertSee($words);

        // said again and again for hours on a printer that neither pauses nor fails: the time stays the first one, the
        // printer is not a problem any more, the card keeps the words in grey with "since"
        $this->travel(20)->hours();
        $first = $printer->message_at->toIso8601String();
        $this->sync($auth, 'idle', null, ['extruder' => 30.0, 'message' => $words]);
        $printer->refresh();
        $this->assertSame($first, $printer->message_at->toIso8601String());
        $this->assertTrue(PrinterStatus::messageLive($printer));
        $this->assertFalse(PrinterStatus::messageFresh($printer));
        $this->assertSame('lasting', PrinterStatus::messageKind($printer));
        $this->assertNotSame(PrinterStatus::PROBLEM, PrinterStatus::of($printer)['key']);
        $this->actingAs($this->admin)->get(route('admin.farm.dashboard'))->assertOk()->assertDontSee($words);
        $this->actingAs($this->admin)->get(route('admin.farm.printers.show', $printer))->assertOk()->assertSee('data-printer-message="lasting"', false)->assertSee('trvá od');
        // ... unless the printer is paused: then the words are the reason of a state that lasts
        $this->sync($auth, 'paused', null, ['extruder' => 30.0, 'message' => $words]);
        $this->assertSame('fresh', PrinterStatus::messageKind($printer->refresh()));
        $this->assertSame($words, PrinterStatus::of($printer)['reason']);

        // a report without the words wipes them: nothing old stays on the card
        $this->sync($auth, 'idle', null, ['extruder' => 30.0]);
        $printer->refresh();
        $this->assertNull($printer->message);
        $this->assertNull($printer->message_at);
        $this->assertNull(PrinterStatus::messageKind($printer));
        $this->actingAs($this->admin)->get(route('admin.farm.printers.show', $printer))->assertOk()->assertDontSee('data-printer-message', false)->assertDontSee($words);

        // new words while it prints, gone with the next state: "waits for a touch" does not outlive the touch
        $touch = 'the printer waits for a touch on its screen (tools mapping, a filament question or an error)';
        $this->sync($auth, 'paused', null, ['message' => $touch]);
        $this->assertSame($touch, $printer->refresh()->message);
        $this->sync($auth, 'printing', null, ['extruder' => 215.0]);
        $this->assertNull($printer->refresh()->message);

        // a printer the agent cannot reach is not asked: its last words stay, marked as the last ones
        $this->sync($auth, 'error', null, ['message' => 'MCU shutdown']);
        $this->sync($auth, 'offline', null, []);
        $printer->refresh();
        $this->assertSame('MCU shutdown', $printer->message);
        $this->assertSame('last', PrinterStatus::messageKind($printer));
        $this->actingAs($this->admin)->get(route('admin.farm.printers.show', $printer))->assertOk()->assertSee('data-printer-message="last"', false);
    }

    public function test_the_reason_of_a_pause_stays_with_the_job_after_the_printer_stops_saying_it(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        [, $job] = $this->printing($printer, $auth);
        $reason = 'last command FEED_FILAMENT ID=0 INDEX=3 · error: filament hub feed filament timeout';
        $this->sync($auth, 'paused', ['id' => $job->id, 'status' => 'paused', 'progress' => 4, 'message' => $reason], ['message' => $reason]);
        $this->assertSame($reason, $printer->refresh()->message);
        $this->sync($auth, 'printing', ['id' => $job->id, 'status' => 'printing', 'progress' => 5, 'message' => ''], ['extruder' => 215.0]);

        $this->assertNull($printer->refresh()->message, 'the printer says nothing now');
        $this->assertSame($reason, $job->refresh()->messageHistory()[0]['m'], 'the history of the print keeps it');
    }

    // ── 7: one resume is enough ──────────────────────────────────────────────────────────────────────────────────────

    public function test_while_a_resume_is_carried_out_no_second_one_can_be_sent_and_the_card_says_so(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        [, $job] = $this->printing($printer, $auth);
        $this->sync($auth, 'paused', ['id' => $job->id, 'status' => 'paused', 'progress' => 10]);
        $url = route('admin.farm.printers.command', $printer);

        $this->actingAs($this->admin)->postJson($url, ['type' => 'resume'])->assertOk();
        // the second and the third press of 13 Nov
        $this->actingAs($this->admin)->postJson($url, ['type' => 'resume'])->assertStatus(422)->assertJsonPath('ok', false)->assertJsonPath('message', __('farm.admin.control.busy_resume'));
        $this->actingAs($this->admin)->postJson($url, ['type' => 'pause'])->assertStatus(422);
        $this->assertSame(1, FarmCommand::whereIn('type', ['pause', 'resume'])->count());
        $this->actingAs($this->admin)->get(route('admin.farm.printers.show', $printer))->assertOk()
            ->assertSee('data-control-in-flight="resume"', false)->assertSee('Pokračuji…')->assertSee('value="resume" class="btn-quiet text-sm" disabled', false);

        // the agent has it and waits for the printer (up to five minutes with 0.2.15): still under way
        $cmd = $this->sync($auth, 'paused', ['id' => $job->id, 'status' => 'paused', 'progress' => 10])->json('commands.0');
        $this->assertSame('resume', $cmd['type']);
        $this->travel(4)->minutes();
        $this->sync($auth, 'paused', ['id' => $job->id, 'status' => 'paused', 'progress' => 10]);
        $this->actingAs($this->admin)->postJson($url, ['type' => 'resume'])->assertStatus(422);

        // confirmed by the printer's state: the lock is gone and the result is said in Czech
        $this->postJson("/api/agent/commands/{$cmd['id']}/result", ['ok' => true, 'message' => "confirmed by the printer's state after 185 s; the call itself said: /printer/print/resume: TimeoutError"], $auth)->assertOk();
        $this->sync($auth, 'printing', ['id' => $job->id, 'status' => 'printing', 'progress' => 10]);
        $this->assertNull(FarmCommand::controlInFlight($printer));
        $this->actingAs($this->admin)->get(route('admin.farm.printers.show', $printer))->assertOk()
            ->assertDontSee('data-control-in-flight', false)->assertSee('tiskárna to potvrdila svým stavem po 185 s')->assertDontSee('TimeoutError');
        $this->actingAs($this->admin)->postJson($url, ['type' => 'pause'])->assertOk();
    }

    public function test_a_cancel_always_goes_through_and_an_unanswered_resume_stops_blocking_after_a_while(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        [, $job] = $this->printing($printer, $auth);
        $this->sync($auth, 'paused', ['id' => $job->id, 'status' => 'paused', 'progress' => 10]);
        $url = route('admin.farm.printers.command', $printer);

        $this->actingAs($this->admin)->postJson($url, ['type' => 'resume'])->assertOk();
        $resume = FarmCommand::where('type', 'resume')->firstOrFail();
        // an agent that never answers (it died in the middle): after ten minutes a new command may be sent
        $this->travel(FarmCommand::CONTROL_WAIT_MINUTES + 1)->minutes();
        $this->assertNull(FarmCommand::controlInFlight($printer));
        $this->travelBack();
        $this->assertNotNull(FarmCommand::controlInFlight($printer));

        // a cancel is never held back, and it ends the waiting (agent 0.2.15 gives the resume up at once)
        $this->actingAs($this->admin)->postJson($url, ['type' => 'cancel'])->assertOk();
        $this->assertNull(FarmCommand::controlInFlight($printer));
        $resume->update(['status' => 'failed', 'result' => '/printer/print/resume: TimeoutError; a cancel came after 42 s; the wait for the printer was given up']);
        $this->assertSame('Čekání na tiskárnu skončilo po 42 s, protože přišlo zrušení tisku.', $resume->resultText('cs'));
        $resume->update(['result' => "/printer/print/resume: TimeoutError; the printer's state did not change in 300 s"]);
        $this->assertStringContainsString('300 s', $resume->resultText('cs'));
        $this->assertStringNotContainsString('TimeoutError', $resume->resultText('cs'));
    }

    public function test_the_new_texts_exist_in_three_languages(): void
    {
        foreach (array_keys(json_decode((string) file_get_contents(base_path('lang/src/no_auto_reprint.json')), true)) as $key) {
            foreach (['cs', 'en', 'es'] as $locale) {
                $this->assertNotSame($key, __($key, [], $locale), "{$key} in {$locale}");
            }
        }
        $this->assertSame('Print stopped, waiting for the operator', __('farm.status.stopped', [], 'en'));
    }
}
