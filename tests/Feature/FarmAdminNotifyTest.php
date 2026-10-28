<?php

namespace Tests\Feature;

use App\Domain\Farm\AgentService;
use App\Domain\Farm\FarmSettings;
use App\Mail\FarmAdminAlert;
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
 * The operator's mails (Roman, 18 Oct 2026: "I need to know that somebody prints something, that something finished
 * or went wrong"): one mail per event – paid, printing (an agent's first plate), done (the last plate, with the
 * camera's picture), a failure or the customer's cancellation – each switchable in the farm's settings, none without
 * an operator's address. Fake mail throughout; nothing real goes out.
 */
class FarmAdminNotifyTest extends TestCase
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
        config(['farm.settings.admin_email' => 'farm@example.com']);
        $this->user = User::factory()->create(['email' => 'customer@example.com', 'name' => 'Jana Nováková']);
    }

    private function order(): FarmOrder
    {
        $path = sys_get_temp_dir().'/mp_notify_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);
        $uuid = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated()->json('file.uuid');

        return FarmOrder::where('token', basename($this->actingAs($this->user)->postJson('/farm/orders', ['file' => $uuid])->assertCreated()->json('url')))->firstOrFail();
    }

    private function credit(int $amount): void
    {
        $this->actingAs($this->user)->post('/account/credit', ['amount' => $amount])->assertRedirect();
        $payment = Payment::latest('id')->firstOrFail();
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $this->user->refresh();
    }

    private function pay(FarmOrder $order): FarmOrder
    {
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->json();
        $color = collect($state['colors'])->firstWhere('slot', $state['slot']) ?? $state['colors'][0];
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $color['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $color['total']])->assertOk();

        return $order->refresh();
    }

    /** @return array{0: FarmPrinter, 1: array<string,string>, 2: FarmAgent} the seeded S1 run by an agent, the agent's headers */
    private function agentPrinter(): array
    {
        [$agent, $token] = FarmAgent::issue('test agent');
        $printer = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $printer->update(['mode' => FarmPrinter::MODE_AGENT, 'farm_agent_id' => $agent->id]);

        return [$printer, ['Authorization' => 'Bearer '.$token], $agent];
    }

    private function sync(array $headers, string $state = 'idle', ?array $job = null)
    {
        return $this->postJson('/api/agent/sync', ['version' => 'test', 'printers' => [['key' => 'kobra-s1-01', 'state' => $state, 'telemetry' => ['extruder' => 215.2], 'job' => $job]]], $headers);
    }

    /** The job the agent is told to start once the operator confirmed the plate. */
    private function startJob(FarmPrinter $printer, array $auth): array
    {
        // refresh first: the test's instance still remembers the plate as clear after a start consumed it, and update() writes only what changed
        $printer->refresh()->update(['bed_clear' => true]);

        return $this->sync($auth)->json('commands.0');
    }

    /** @return list<FarmAdminAlert> */
    private function alerts(?string $kind = null): array
    {
        return Mail::queued(FarmAdminAlert::class)->filter(fn (FarmAdminAlert $m) => $kind === null || $m->kind === $kind)->values()->all();
    }

    private function assertLine(FarmAdminAlert $mail, string $needle): void
    {
        $this->assertTrue(collect($mail->lines)->contains(fn ($l) => str_contains($l, $needle)), "no line with '{$needle}' in: ".implode(' | ', $mail->lines));
    }

    public function test_a_payment_mails_the_operator_once_with_who_what_where_and_the_queue(): void
    {
        $order = $this->order();
        $this->credit(1000);
        Mail::assertNothingQueued();
        $order = $this->pay($order);

        // a manual machine: the one "paid" mail also says the operator starts the print; no second mail about that
        $this->assertCount(1, $this->alerts());
        $mail = $this->alerts('paid')[0];
        $this->assertSame('Nová zakázka '.$order->number.': zaplaceno', $mail->subjectLine);
        $this->assertSame(route('admin.farm.orders.show', $order), $mail->url);
        $this->assertLine($mail, 'Jana Nováková (customer@example.com)');
        $this->assertLine($mail, 'Model: part.stl');
        $this->assertLine($mail, 'Materiál a barva: '.$order->spoolText());
        $this->assertLine($mail, 'Tiskárna: '.$order->printer->name.' ('.$order->printer->shortCode().'), ruční režim');
        $this->assertLine($mail, 'Kusy: 1');
        $this->assertLine($mail, 'Cena: '.$order->total()->format());
        $this->assertLine($mail, 'Doprava: Osobní odběr');
        $this->assertLine($mail, 'Fronta: tiskárna v ručním režimu');
        $this->assertLine($mail, 'Tisk spustí obsluha');
        $this->assertNull($mail->attachment);
    }

    public function test_an_agent_print_is_told_once_when_it_starts_and_once_when_it_is_done_with_the_picture(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        $this->sync($auth);
        $order = $this->order();
        $this->credit(1000);
        $order = $this->pay($order);
        $paid = $this->alerts('paid');
        $this->assertCount(1, $paid);
        $this->assertLine($paid[0], 'přes agenta');
        $this->assertLine($paid[0], 'Fronta:');

        $cmd = $this->startJob($printer, $auth);
        $this->assertSame([], $this->alerts('printing'), 'a start on its way is not a print yet');
        // the agent reports every few seconds: the first report moves the order, the next ones say nothing new
        foreach ([1, 5, 12] as $progress) {
            $this->sync($auth, 'printing', ['id' => $cmd['job_id'], 'status' => 'printing', 'progress' => $progress]);
        }
        $this->assertSame(FarmOrder::STATUS_PRINTING, $order->refresh()->status);
        $printing = $this->alerts('printing');
        $this->assertCount(1, $printing);
        $this->assertSame($order->number.' se tiskne na '.$printer->name, $printing[0]->subjectLine);
        $this->assertLine($printing[0], 'Odhad dotištění: za ');
        $this->assertLine($printing[0], 'Materiál a barva: '.$order->spoolText());

        // the camera's last picture of the job rides along with "done"
        $job = FarmPrintJob::findOrFail($cmd['job_id']);
        $rel = $order->dir().'/snapshot.jpg';
        Storage::disk('farm')->put($rel, self::jpeg(64, 48));
        $job->update(['snapshot_path' => $rel, 'snapshot_at' => now()]);
        $this->sync($auth, 'idle', ['id' => $cmd['job_id'], 'status' => 'done', 'progress' => 100, 'print_duration' => 6420, 'filament_used' => 5000]);
        $this->assertSame(FarmOrder::STATUS_DONE, $order->refresh()->status);
        $done = $this->alerts('done');
        $this->assertCount(1, $done);
        $this->assertSame($order->number.' dotištěno ('.$printer->name.', 1 h 47 min)', $done[0]->subjectLine);
        $this->assertLine($done[0], 'Doba tisku: 1 h 47 min');
        $this->assertLine($done[0], 'Filament: '.round((float) $order->actual_grams).' g');
        $this->assertFalse($order->stats_estimated, 'numbers that came from the printer are no estimate');
        $this->assertLine($done[0], 'vyzvedne osobně');
        $this->assertLine($done[0], 'snímek z kamery');
        $this->assertSame(Storage::disk('farm')->path($rel), $done[0]->attachment);
        $this->assertCount(1, $done[0]->attachments());
        $this->assertCount(3, $this->alerts(), 'paid, printing, done – nothing else');
    }

    public function test_a_big_picture_is_shrunk_for_the_mail(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        $this->sync($auth);
        $order = $this->order();
        $this->credit(1000);
        $order = $this->pay($order);
        $cmd = $this->startJob($printer, $auth);
        $this->sync($auth, 'printing', ['id' => $cmd['job_id'], 'status' => 'printing', 'progress' => 50]);
        $rel = $order->dir().'/snapshot.jpg';
        Storage::disk('farm')->put($rel, self::jpeg(1600, 1200, busy: true));
        $this->assertGreaterThan(300 * 1024, Storage::disk('farm')->size($rel), 'the picture of this test is bigger than the mail takes');
        FarmPrintJob::findOrFail($cmd['job_id'])->update(['snapshot_path' => $rel]);

        $this->sync($auth, 'idle', ['id' => $cmd['job_id'], 'status' => 'done', 'progress' => 100, 'print_duration' => 600]);
        $done = $this->alerts('done');
        $this->assertCount(1, $done);
        $this->assertSame(Storage::disk('farm')->path($order->dir().'/snapshot_mail.jpg'), $done[0]->attachment);
        $this->assertLessThanOrEqual(300 * 1024, filesize($done[0]->attachment));
        $this->assertSame(1280, getimagesize($done[0]->attachment)[0]);
    }

    public function test_an_order_of_two_plates_says_printing_at_the_first_and_done_after_the_last(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        $this->sync($auth);
        $order = $this->order();
        $this->credit(1000);
        $order = $this->pay($order);
        $order->forceFill(['plates' => 2])->save();

        $first = $this->startJob($printer, $auth);
        $this->sync($auth, 'printing', ['id' => $first['job_id'], 'status' => 'printing', 'progress' => 3]);
        $this->assertCount(1, $this->alerts('printing'));
        $this->assertLine($this->alerts('printing')[0], 'Podložka 1 z 2');
        $this->sync($auth, 'idle', ['id' => $first['job_id'], 'status' => 'done', 'progress' => 100, 'print_duration' => 1800]);
        $this->assertSame([FarmOrder::STATUS_QUEUED, 1], [$order->refresh()->status, $order->plates_done]);
        $this->assertSame([], $this->alerts('done'), 'one plate of two is not a finished print');

        $second = $this->startJob($printer, $auth);
        $this->assertNotSame($first['job_id'], $second['job_id']);
        $this->sync($auth, 'printing', ['id' => $second['job_id'], 'status' => 'printing', 'progress' => 3]);
        $this->assertCount(1, $this->alerts('printing'), 'the second plate starting is not news');
        $this->sync($auth, 'idle', ['id' => $second['job_id'], 'status' => 'done', 'progress' => 100, 'print_duration' => 1800]);
        $this->assertSame(FarmOrder::STATUS_DONE, $order->refresh()->status);
        $this->assertCount(1, $this->alerts('done'));
        $this->assertStringContainsString('1 h 0 min', $this->alerts('done')[0]->subjectLine);
    }

    public function test_a_failed_print_a_failed_start_and_a_customer_s_cancellation_are_problems_told_once(): void
    {
        // a failure that ends the order at once: the one automatic reprint is FarmGuestCheckoutTest's (docs/AO.md §2)
        config(['farm.payments.auto_reprint' => false]);
        [$printer, $auth, $agent] = $this->agentPrinter();
        $this->sync($auth);
        $order = $this->order();
        $this->credit(2000);
        $order = $this->pay($order);
        $cmd = $this->startJob($printer, $auth);
        $this->sync($auth, 'printing', ['id' => $cmd['job_id'], 'status' => 'printing', 'progress' => 5]);
        $this->sync($auth, 'error', ['id' => $cmd['job_id'], 'status' => 'failed', 'progress' => 5, 'message' => 'Heater extruder not heating']);
        $this->sync($auth, 'error', ['id' => $cmd['job_id'], 'status' => 'failed', 'progress' => 5, 'message' => 'Heater extruder not heating']);
        $this->assertSame(FarmOrder::STATUS_FAILED, $order->refresh()->status);
        $failed = $this->alerts('problem');
        $this->assertCount(1, $failed, 'a failure said twice is one mail');
        $this->assertSame($order->number.': tisk selhal (print_failed)', $failed[0]->subjectLine);
        $this->assertLine($failed[0], 'Co: tiskárna nahlásila selhání tisku (Heater extruder not heating)');
        $this->assertLine($failed[0], 'Kde: '.$printer->name.', vytištěno 5 %');
        $this->assertLine($failed[0], 'Co dál: kredit se zákazníkovi vrátil');

        // a start the agent could not carry out: the subject names the order
        $printer->refresh()->update(['bed_clear' => true, 'state' => FarmPrinter::STATE_IDLE]);
        $again = $this->order();
        $again = $this->pay($again);
        $cmd = $this->sync($auth)->json('commands.0');
        $this->assertSame($again->id, FarmPrintJob::findOrFail($cmd['job_id'])->farm_order_id);
        app(AgentService::class)->commandResult($agent, FarmCommand::findOrFail($cmd['id']), false, 'upload refused by the printer');
        $this->assertCount(2, $this->alerts('problem'));
        $this->assertSame($again->number.': start na '.$printer->name.' selhal', $this->alerts('problem')[1]->subjectLine);
        $this->assertLine($this->alerts('problem')[1], 'Co: upload refused by the printer');

        // the customer gives an order up before it printed: what came back, whether it had printed
        $third = $this->order();
        $third = $this->pay($third);
        $this->actingAs($this->user)->postJson("/farm/orders/{$third->token}/cancel")->assertOk();
        $this->assertSame(FarmOrder::STATUS_CANCELLED, $third->refresh()->status);
        $this->assertCount(3, $this->alerts('problem'));
        $cancelled = $this->alerts('problem')[2];
        $this->assertSame($third->number.' zrušil zákazník', $cancelled->subjectLine);
        $this->assertLine($cancelled, 'Vráceno zákazníkovi: '.$third->total()->format());
        $this->assertLine($cancelled, 'Tisklo se: ne');
    }

    /**
     * The Kobra X after a 15-hour dragon (job 154, 23 Oct 2026) said "done" with 0 s and 0 mm. The job gets its duration
     * from its own clock (pauses taken off) and the filament from the slice, both marked as estimates, so the mail, the
     * admin's page and the statistics do not show 0 min / 0 g – and the command does the same for a job closed before.
     */
    public function test_a_print_the_printer_gave_no_numbers_for_gets_them_estimated_and_marked(): void
    {
        // a failure that ends the order at once: the one automatic reprint is FarmGuestCheckoutTest's (docs/AO.md §2)
        config(['farm.payments.auto_reprint' => false]);
        [$printer, $auth] = $this->agentPrinter();
        $this->sync($auth);
        $this->credit(2000);
        $order = $this->pay($this->order());
        $cmd = $this->startJob($printer, $auth);
        $this->travelTo(now()->startOfMinute());
        $this->sync($auth, 'printing', ['id' => $cmd['job_id'], 'status' => 'printing', 'progress' => 1]);
        $this->travel(30)->minutes();
        $this->sync($auth, 'printing', ['id' => $cmd['job_id'], 'status' => 'paused', 'progress' => 40]);
        $this->travel(10)->minutes();
        $this->sync($auth, 'printing', ['id' => $cmd['job_id'], 'status' => 'printing', 'progress' => 41]);
        $this->travel(80)->minutes();
        $this->sync($auth, 'idle', ['id' => $cmd['job_id'], 'status' => 'done', 'progress' => 100, 'print_duration' => 0, 'filament_used' => 0]);
        $job = FarmPrintJob::findOrFail($cmd['job_id']);
        $this->assertTrue($job->stats_estimated);
        $this->assertSame(600, $job->paused_s);
        $this->assertSame(110 * 60, $job->print_duration_s, 'two hours on the clock less the ten minutes paused');
        $this->assertGreaterThan(0, $job->filament_used_mm);
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_DONE, $order->status);
        $this->assertSame(110, $order->actual_minutes);
        $this->assertEqualsWithDelta((float) $order->est_grams, (float) $order->actual_grams, 0.2, 'the filament of the slice');
        $this->assertTrue($order->stats_estimated);
        $this->assertSame('agent', $order->actual_source);
        $done = $this->alerts('done');
        $this->assertCount(1, $done);
        $this->assertSame($order->number.' dotištěno ('.$printer->name.', 1 h 50 min)', $done[0]->subjectLine);
        $this->assertLine($done[0], 'Doba tisku: 1 h 50 min (odhad)');
        $this->assertLine($done[0], 'Filament: '.round((float) $order->est_grams).' g (odhad)');
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin)->get(route('admin.farm.orders.show', $order))->assertOk()->assertSee(__('farm.admin.estimated_stats'));

        // a job closed before this change (job 154 as it stands): the command fills it the same way
        $job->update(['print_duration_s' => 0, 'filament_used_mm' => 0, 'stats_estimated' => false]);
        $order->forceFill(['actual_minutes' => null, 'actual_grams' => null, 'stats_estimated' => false])->save();
        $this->artisan('farm:job-stats', ['--job' => $job->id])->assertSuccessful();
        $this->assertSame(110 * 60, $job->refresh()->print_duration_s);
        $this->assertTrue($job->stats_estimated);
        $this->assertSame(110, $order->refresh()->actual_minutes);
        $this->assertTrue($order->stats_estimated);

        // a print that fails without numbers keeps at least how long it ran; what it used nobody knows
        $this->travelBack();
        $second = $this->pay($this->order());
        $cmd2 = $this->startJob($printer, $auth);
        $this->travelTo(now()->startOfMinute());
        $this->sync($auth, 'printing', ['id' => $cmd2['job_id'], 'status' => 'printing', 'progress' => 5]);
        $this->travel(20)->minutes();
        $this->sync($auth, 'idle', ['id' => $cmd2['job_id'], 'status' => 'failed', 'progress' => 30, 'print_duration' => 0, 'message' => 'thermal runaway']);
        $failed = FarmPrintJob::findOrFail($cmd2['job_id']);
        $this->assertSame(FarmPrintJob::STATUS_FAILED, $failed->status);
        $this->assertSame(20 * 60, $failed->print_duration_s);
        $this->assertNull($failed->filament_used_mm);
        $this->assertTrue($failed->stats_estimated);
        $this->assertSame(FarmOrder::STATUS_FAILED, $second->refresh()->status);
        $this->travelBack();
    }

    public function test_switched_off_kinds_and_an_empty_address_send_nothing(): void
    {
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin)->get('/admin/farm/settings')->assertOk()->assertSee('id="farm-notify"', false)
            ->assertSee('name="notify_paid" value="1" checked', false)->assertSee('name="notify_done" value="1" checked', false);

        // the admin unticks "paid": the form saves the four switches like any other box
        $settings = app(FarmSettings::class)->all();
        $form = ['topup_amounts' => implode(', ', $settings['topup_amounts']), 'qualities' => json_encode($settings['qualities']), 'strengths' => json_encode($settings['strengths']), 'delivery_modes' => $settings['delivery_modes']]
            + array_intersect_key($settings, array_flip(['hourly_rate', 'vat_percent', 'rounding', 'max_upload_mb', 'daily_slices_per_user', 'min_model_mm', 'bed_margin_mm', 'fixed_fee', 'min_price', 'topup_min', 'topup_max', 'generation_price', 'changeover_minutes', 'offline_after_seconds', 'terms_version', 'admin_email']));
        $this->actingAs($admin)->post('/admin/farm/settings', $form + ['notify_printing' => 1, 'notify_done' => 1, 'notify_problem' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->app->forgetScopedInstances();
        $this->assertFalse(app(FarmSettings::class)->get('notify_paid'));
        $this->assertTrue(app(FarmSettings::class)->get('notify_done'));
        $this->actingAs($admin)->get('/admin/farm/settings')->assertOk()->assertDontSee('name="notify_paid" value="1" checked', false);

        // paid is off: the payment itself is quiet, but a manual machine still asks for its start (that is "printing")
        $order = $this->order();
        $this->credit(1000);
        $order = $this->pay($order);
        $this->assertSame([], $this->alerts('paid'));
        $this->assertCount(1, $this->alerts('printing'));
        $this->assertSame($order->number.' čeká na ruční spuštění ('.$order->printer->name.')', $this->alerts('printing')[0]->subjectLine);

        // no address: nothing at all, whatever happens
        app(FarmSettings::class)->set('admin_email', '');
        $this->app->forgetScopedInstances();
        $quiet = $this->order();
        $this->pay($quiet);
        $this->actingAs($this->user)->postJson("/farm/orders/{$quiet->token}/cancel")->assertOk();
        $this->assertCount(1, $this->alerts(), 'still only the one mail from before');
    }

    /** A JPEG made on the spot: a plain one, or a busy one (random blocks) that does not fit the mail's limit. */
    private static function jpeg(int $w, int $h, bool $busy = false): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, 40, 90, 160));
        if ($busy) {
            // random blocks: 1600 × 1200 of them is about 500 kB, and 1280 px wide at quality 70 about 220 kB
            mt_srand(7);
            for ($i = 0; $i < 3000; $i++) {
                $c = imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
                $x = mt_rand(0, $w - 1);
                $y = mt_rand(0, $h - 1);
                imagefilledrectangle($im, $x, $y, $x + mt_rand(2, 40), $y + mt_rand(2, 40), $c);
            }
        }
        ob_start();
        imagejpeg($im, null, 90);
        imagedestroy($im);

        return (string) ob_get_clean();
    }
}
