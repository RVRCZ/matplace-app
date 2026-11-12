<?php

namespace Tests\Feature;

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
 * A cancel is a finished matter only when the printer says so (agent 0.2.13, docs/AM.md §13; F26-000158 on U2,
 * 11 Nov 2026: the order was "cancelled" on the web while the printer sat paused with its file for fifteen minutes and
 * nobody saw it). An order the customer cancelled shows in the admin as waiting for the printer until the agent
 * answers, and as "the printer did not cancel" when the agent could not end the print; the operator is mailed, the
 * cancel can be sent again from the desk, and the agent's technical lines about a pause and a cancel are worded in
 * plain Czech and English. Nothing new is stored: the state is read from the cancel command and the printer.
 */
class FarmCancelConfirmTest extends TestCase
{
    use RefreshDatabase;

    private const STUCK = "cancel not confirmed: cancel was answered ok, but the printer is still paused after 60 s and after SDCARD_RESET_FILE; stop the print on the printer's screen";

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
        $path = sys_get_temp_dir().'/mp_cancel_'.uniqid().'.stl';
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

    private function sync(array $headers, string $state = 'idle', ?array $job = null)
    {
        return $this->postJson('/api/agent/sync', ['version' => '0.2.13', 'printers' => [['key' => 'kobra-s1-01', 'state' => $state, 'telemetry' => ['extruder' => 215.2], 'job' => $job]]], $headers);
    }

    /** A paid order printing on the agent's printer. @return array{0: FarmOrder, 1: FarmPrintJob} */
    private function printing(FarmPrinter $printer, array $auth): array
    {
        $this->sync($auth);
        $order = $this->pay($this->order());
        $printer->refresh()->update(['bed_clear' => true]);
        $cmd = $this->sync($auth)->json('commands.0');
        $this->assertSame('start', $cmd['type']);
        $this->postJson("/api/agent/commands/{$cmd['id']}/result", ['ok' => true], $auth)->assertOk();
        $this->sync($auth, 'printing', ['id' => $cmd['job_id'], 'status' => 'printing', 'progress' => 0]);

        return [$order->refresh(), FarmPrintJob::findOrFail($cmd['job_id'])];
    }

    /** The customer cancels; the agent takes the cancel command while the printer sits paused. Returns the command's id. */
    private function customerCancels(FarmOrder $order, array $auth): int
    {
        $this->actingAs($this->user)->post("/farm/orders/{$order->token}/cancel")->assertRedirect();
        $this->assertSame(FarmOrder::STATUS_CANCELLED, $order->refresh()->status);
        $cmd = $this->sync($auth, 'paused')->json('commands.0');
        $this->assertSame('cancel', $cmd['type']);

        return (int) $cmd['id'];
    }

    /** @return list<FarmAdminAlert> */
    private function alerts(string $needle): array
    {
        return Mail::queued(FarmAdminAlert::class)->filter(fn (FarmAdminAlert $m) => str_contains($m->subjectLine, $needle))->values()->all();
    }

    public function test_a_cancelled_order_waits_for_the_printer_until_the_agent_confirms(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        [$order] = $this->printing($printer, $auth);

        $this->actingAs($this->user)->post("/farm/orders/{$order->token}/cancel")->assertRedirect();
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_CANCELLED, $order->status);
        // the command has not even reached the agent: cancelled on the web, not on the printer
        $this->assertSame(FarmCommand::CANCEL_WAITING, $order->cancelState());
        $this->actingAs($this->admin)->get(route('admin.farm.dashboard'))->assertOk()
            ->assertSee('Zrušeno, čeká na potvrzení tiskárny')->assertSee('data-cancel-state="waiting"', false)->assertSee($order->number);
        $this->actingAs($this->admin)->get(route('admin.farm.orders.show', $order))->assertOk()->assertSee('Zrušeno, čeká na potvrzení tiskárny');
        $this->actingAs($this->admin)->get(route('admin.farm.orders', ['status' => 'cancelled']))->assertOk()->assertSee('data-cancel-state="waiting"', false);

        // the agent has it and watches the printer: still waiting
        $id = $this->sync($auth, 'paused')->json('commands.0.id');
        $this->assertSame(FarmCommand::CANCEL_WAITING, $order->cancelState());

        // confirmed by the printer's state (here with the second step): a finished matter, nobody is mailed
        $this->postJson("/api/agent/commands/{$id}/result", ['ok' => true, 'message' => 'cancel was answered ok, but the printer stayed paused for 60 s; SDCARD_RESET_FILE ended the print'], $auth)->assertOk();
        $this->sync($auth, 'idle');
        $this->assertNull($order->cancelState());
        $this->assertNull($printer->refresh()->unconfirmedCancel());
        $this->actingAs($this->admin)->get(route('admin.farm.dashboard'))->assertOk()
            ->assertDontSee('data-cancel-state', false)
            ->assertSee('Tiskárna na zrušení odpověděla, ale tisk 60 s držela dál (pozastaveno). Ukončil ho až druhý krok (SDCARD_RESET_FILE).');
        $this->actingAs($this->admin)->get(route('admin.farm.orders.show', $order))->assertOk()->assertDontSee('data-cancel-state', false);
        $this->assertSame([], $this->alerts('nezrušila'));
    }

    public function test_a_cancel_the_printer_did_not_carry_out_is_shown_mailed_and_can_be_sent_again(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        [$order, $job] = $this->printing($printer, $auth);
        $id = $this->customerCancels($order, $auth);

        $this->postJson("/api/agent/commands/{$id}/result", ['ok' => false, 'message' => self::STUCK], $auth)->assertOk();
        $this->sync($auth, 'paused');

        // the operator hears once, with the order, plain words, the agent's own line and what to do
        $mails = $this->alerts('tisk nezrušila');
        $this->assertCount(1, $mails);
        $this->assertSame('problem', $mails[0]->kind);
        $this->assertStringContainsString($order->number, $mails[0]->subjectLine);
        $this->assertStringContainsString($printer->name, $mails[0]->subjectLine);
        $text = implode("\n", $mails[0]->lines);
        $this->assertStringContainsString('Tiskárna tisk nezrušila ani druhým krokem (SDCARD_RESET_FILE), zůstává ve stavu „pozastaveno“', $text);
        $this->assertStringContainsString('Agent: cancel not confirmed', $text);
        $this->assertStringContainsString('Zastavte tisk na displeji tiskárny', $text);

        // the admin: not a finished matter, on the printer's card, on the order and in the list
        $this->assertSame(FarmCommand::CANCEL_REFUSED, $order->cancelState());
        $this->actingAs($this->admin)->get(route('admin.farm.dashboard'))->assertOk()
            ->assertSee('data-cancel-state="refused"', false)->assertSee('Tiskárna tisk nezrušila')->assertSee('Zkusit zrušit znovu')
            ->assertDontSee('cancel not confirmed');
        $this->actingAs($this->admin)->get(route('admin.farm.orders.show', $order))->assertOk()
            ->assertSee('data-cancel-state="refused"', false)->assertSee('farma na ni nic dalšího nepošle');
        $this->actingAs($this->admin)->get(route('admin.farm.orders', ['status' => 'cancelled']))->assertOk()->assertSee('data-cancel-state="refused"', false);

        // the next paid order does not start on a printer that still holds the cancelled print, plate confirmed or not
        $next = $this->pay($this->order());
        $printer->refresh()->update(['bed_clear' => true]);
        $this->assertSame([], $this->sync($auth, 'paused')->json('commands'));
        $this->assertSame(FarmOrder::STATUS_QUEUED, $next->refresh()->status);

        // from the desk: the cancel again, for the same job, although the order is cancelled already
        $this->actingAs($this->admin)->post(route('admin.farm.printers.command', $printer), ['type' => 'cancel'])->assertRedirect();
        $again = FarmCommand::where('type', FarmCommand::TYPE_CANCEL)->latest('id')->firstOrFail();
        $this->assertNotSame($id, $again->id);
        $this->assertSame($job->id, $again->farm_print_job_id);
        $this->assertSame(FarmCommand::CANCEL_WAITING, $order->cancelState());
        $this->assertSame('cancel', $this->sync($auth, 'paused')->json('commands.0.type'));
        $this->postJson("/api/agent/commands/{$again->id}/result", ['ok' => true], $auth)->assertOk();
        $this->assertNull($order->cancelState());
        $this->assertCount(1, $this->alerts('tisk nezrušila'), 'the confirmed second try mails nobody');
    }

    public function test_the_warning_goes_away_when_the_printer_lets_go_and_never_outlives_a_new_start(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        [$order] = $this->printing($printer, $auth);
        $id = $this->customerCancels($order, $auth);
        $this->postJson("/api/agent/commands/{$id}/result", ['ok' => false, 'message' => self::STUCK], $auth)->assertOk();
        $this->sync($auth, 'paused');
        $this->assertSame(FarmCommand::CANCEL_REFUSED, $order->cancelState());

        // the agent loses the printer: still not a finished matter
        $printer->refresh()->update(['state' => FarmPrinter::STATE_UNKNOWN]);
        $this->assertSame(FarmCommand::CANCEL_REFUSED, $order->cancelState());

        // the operator stopped the print on the screen: the printer is idle, the warning is gone
        $this->sync($auth, 'idle');
        $this->assertNull($order->cancelState());
        $this->assertNull($printer->refresh()->unconfirmedCancel());

        // paused again by a print somebody started at the printer: a later start of ours closes the old cancel for good
        $printer->update(['state' => FarmPrinter::STATE_PAUSED]);
        $this->assertSame(FarmCommand::CANCEL_REFUSED, $order->cancelState());
        FarmCommand::create(['farm_printer_id' => $printer->id, 'type' => FarmCommand::TYPE_START, 'status' => 'done']);
        $this->assertNull($order->cancelState());

        // and an unanswered cancel older than a week is history, not a warning
        $old = FarmCommand::create(['farm_printer_id' => $printer->id, 'type' => FarmCommand::TYPE_CANCEL, 'status' => 'sent']);
        $this->assertSame(FarmCommand::CANCEL_WAITING, $old->cancelState());
        $old->forceFill(['created_at' => now()->subDays(8)])->save();
        $this->assertNull($old->refresh()->cancelState());
    }

    public function test_heaters_left_on_after_a_cancel_mail_the_operator_but_the_cancel_is_done(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        [$order] = $this->printing($printer, $auth);
        $id = $this->customerCancels($order, $auth);

        $this->postJson("/api/agent/commands/{$id}/result", ['ok' => true, 'message' => 'the heaters are still on (nozzle 220, bed 55); switch them off at the printer'], $auth)->assertOk();
        $this->sync($auth, 'idle');

        $this->assertNull($order->cancelState());
        $mails = $this->alerts('zapnuté topení');
        $this->assertCount(1, $mails);
        $text = implode("\n", $mails[0]->lines);
        $this->assertStringContainsString('Topení zůstává po zrušení zapnuté (tryska 220, podložka 55). Vypněte ho na tiskárně.', $text);
        $this->assertStringContainsString('Vypněte topení na displeji tiskárny', $text);
        $this->assertSame([], $this->alerts('tisk nezrušila'));

        // heaters the agent switched off itself are a note on the dashboard, not a mail
        $other = FarmCommand::create(['farm_printer_id' => $printer->id, 'type' => FarmCommand::TYPE_CANCEL, 'status' => 'sent']);
        $this->postJson("/api/agent/commands/{$other->id}/result", ['ok' => true, 'message' => 'the heaters were left on (nozzle 220, bed 55), switched off with TURN_OFF_HEATERS'], $auth)->assertOk();
        $this->assertCount(1, $this->alerts('zapnuté topení'));
        $this->assertSame('Topení zůstalo po zrušení zapnuté (tryska 220, podložka 55), agent ho vypnul.', $other->refresh()->resultText());
    }

    public function test_a_pause_the_printer_did_not_take_reads_in_plain_words_in_czech_and_english(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        [, $job] = $this->printing($printer, $auth);

        // while the printer is getting ready the agent refuses at once
        $this->actingAs($this->admin)->post(route('admin.farm.printers.command', $printer), ['type' => 'pause'])->assertRedirect();
        $id = $this->sync($auth, 'printing', ['id' => $job->id, 'status' => 'printing', 'progress' => 0])->json('commands.0.id');
        $this->postJson("/api/agent/commands/{$id}/result", ['ok' => false, 'message' => FarmCommand::PAUSE_WHILE_STARTING], $auth)->assertOk();
        $this->actingAs($this->admin)->get(route('admin.farm.dashboard'))->assertOk()
            ->assertSee('Tiskárna se připravuje (nahřívá a vyrovnává podložku), pauza půjde za chvíli, až začne první vrstva.')
            ->assertDontSee('getting ready');
        $this->assertSame('The printer is getting ready (heating and levelling), a pause will work in a moment, once the first layer starts.', FarmCommand::findOrFail($id)->resultText('en'));

        // the technical line of 11 Nov 2026 (command 374) is not what the operator reads
        $failed = new FarmCommand(['type' => FarmCommand::TYPE_PAUSE, 'result' => "/printer/print/pause: TimeoutError; the printer's state did not change in 60 s"]);
        $this->assertSame('Tiskárna pauzu nepřijala, její stav se 60 s nezměnil. Zkuste to za chvíli znovu.', $failed->resultText());
        $this->assertSame('The printer did not take the pause, its state did not change in 60 s. Try again in a moment.', $failed->resultText('en'));
        $resume = new FarmCommand(['type' => FarmCommand::TYPE_RESUME, 'result' => "nothing is paused; the printer's state did not change in 60 s"]);
        $this->assertSame('Tiskárna v tisku nepokračuje, její stav se 60 s nezměnil. Podívejte se na její displej.', $resume->resultText());

        // a failed pause is the operator's own click: no mail, the job goes on
        $this->assertSame([], $this->alerts('nezrušila'));
        $this->assertSame(FarmPrintJob::STATUS_PRINTING, $job->refresh()->status);
    }

    public function test_the_agents_cancel_sentences_in_the_operators_words(): void
    {
        $cancel = fn (string $result) => (new FarmCommand(['type' => FarmCommand::TYPE_CANCEL, 'result' => $result]))->resultText();

        $this->assertSame('Tiskárna tisk nezrušila, zůstává ve stavu „pozastaveno“. Zastavte tisk na displeji tiskárny.',
            $cancel("cancel not confirmed: cancel was answered ok, but the printer is still paused after 60 s and has no second way to end a print; stop it on the printer's screen"));
        $this->assertSame('Tiskárna tisk nezrušila ani druhým krokem (SDCARD_RESET_FILE), zůstává ve stavu „tiskne“. Zastavte tisk na displeji tiskárny.',
            $cancel("cancel not confirmed: cancel was answered ok, but the printer is still printing after 60 s and SDCARD_RESET_FILE was refused (/printer/gcode/script: HTTP 400 Unknown command); stop the print on the printer's screen"));
        $this->assertSame('Tiskárna tisk nezrušila a drží soubor, který není z farmy (benchy.gcode). Zastavte ho na displeji tiskárny.',
            $cancel("cancel not confirmed: cancel was answered ok, but the printer is still paused after 60 s with a file that is not the farm's (benchy.gcode); stop it on the printer's screen"));
        $this->assertSame('Tiskárna na zrušení neodpověděla a agent ji nevidí. Nikdo neví, jestli tisk skončil. Zkontrolujte tiskárnu.',
            $cancel('cancel not confirmed: cancel said: /printer/print/cancel: TimeoutError, but the printer has not answered for 60 s; nobody knows whether the print stopped'));
        $this->assertSame('Tiskárna na zrušení odpověděla, ale tisk 60 s držela dál (pozastaveno). Ukončil ho až druhý krok (SDCARD_RESET_FILE). Topení zůstalo po zrušení zapnuté (tryska 220, podložka 55), agent ho vypnul.',
            $cancel('cancel was answered ok, but the printer stayed paused for 60 s; SDCARD_RESET_FILE ended the print; the heaters were left on (nozzle 220, bed 55), switched off with TURN_OFF_HEATERS'));
        // a note that is none of these stays as the agent wrote it
        $this->assertSame("confirmed by the printer's state after 4 s; the call itself said: nothing to cancel", $cancel("confirmed by the printer's state after 4 s; the call itself said: nothing to cancel"));
    }
}
