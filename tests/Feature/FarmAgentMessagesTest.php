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
 * Agent 0.2.9 on the server side (docs/AM.md §10): the printer's words stay with the job and the printer, a pause
 * mails the operator once with the reason (or "the printer gave none"), the operator's confirmation at the printer
 * clears the plate, and the printer's files are listed, fetched, deleted and tidied from the admin without ever
 * touching the running job's file. Fake mail and fake storage throughout.
 */
class FarmAgentMessagesTest extends TestCase
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
        $this->user = User::factory()->create(['email' => 'customer@example.com', 'name' => 'Jana Nováková']);
        $this->admin = User::factory()->create();
        $this->admin->setRole(User::ROLE_ADMIN, true);
    }

    private function order(): FarmOrder
    {
        $path = sys_get_temp_dir().'/mp_msg_'.uniqid().'.stl';
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

    /** @return array{0: FarmPrinter, 1: array<string,string>, 2: FarmAgent} */
    private function agentPrinter(): array
    {
        [$agent, $token] = FarmAgent::issue('test agent');
        $printer = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $printer->update(['mode' => FarmPrinter::MODE_AGENT, 'farm_agent_id' => $agent->id]);

        return [$printer, ['Authorization' => 'Bearer '.$token], $agent];
    }

    private function sync(array $headers, string $state = 'idle', ?array $job = null, array $telemetry = ['extruder' => 215.2])
    {
        return $this->postJson('/api/agent/sync', ['version' => '0.2.9', 'printers' => [['key' => 'kobra-s1-01', 'state' => $state, 'telemetry' => $telemetry, 'job' => $job]]], $headers);
    }

    /** A paid order on the agent's printer, started: the job the agent reports about. */
    private function startedJob(FarmPrinter $printer, array $auth): FarmPrintJob
    {
        $this->sync($auth);
        $order = $this->order();
        $this->credit(1000);
        $this->pay($order);
        $printer->refresh()->update(['bed_clear' => true]);
        $cmd = $this->sync($auth)->json('commands.0');
        $this->assertSame('start', $cmd['type']);
        $this->sync($auth, 'printing', ['id' => $cmd['job_id'], 'status' => 'printing', 'progress' => 0]);

        return FarmPrintJob::findOrFail($cmd['job_id']);
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

    private const REASON = 'last command FEED_FILAMENT ID=0 INDEX=3 LENGTH=80 SPEED=25 · error: typ = WebRequestError, code = 10011710, message = filament hub feed filament timeout';

    public function test_a_pause_mails_the_operator_once_with_the_printers_words_which_stay_with_the_job(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        $job = $this->startedJob($printer, $auth);
        $order = $job->order;
        Mail::fake();

        // the agent repeats the pause and its reason every few seconds: one mail, one line of history
        foreach ([1, 2, 3] as $i) {
            $this->sync($auth, 'paused', ['id' => $job->id, 'status' => 'paused', 'progress' => 0, 'message' => self::REASON], ['extruder' => 140.0, 'message' => self::REASON]);
        }
        $job->refresh();
        $this->assertSame(FarmPrintJob::STATUS_PAUSED, $job->status);
        $this->assertNotNull($job->paused_at);
        $this->assertSame(self::REASON, $job->message);
        $this->assertNotNull($job->message_at);
        $this->assertCount(1, $job->messages, 'the same words in a row are one entry');
        $this->assertSame(self::REASON, $job->messages[0]['m']);
        $paused = $this->alerts('paused');
        $this->assertCount(1, $paused);
        $this->assertSame($order->number.': '.$printer->name.' pozastavila tisk', $paused[0]->subjectLine);
        $this->assertSame(route('admin.farm.orders.show', $order), $paused[0]->url);
        $this->assertLine($paused[0], 'Hláška tiskárny: last command FEED_FILAMENT');
        $this->assertLine($paused[0], 'Kde: '.$printer->name);
        $this->assertLine($paused[0], 'Co dál:');
        $this->assertCount(1, $this->alerts(), 'nothing else went out');

        // the printer itself remembers its last words, with the time, even when a later report says nothing
        $printer->refresh();
        $this->assertSame(self::REASON, $printer->message);
        $this->assertNotNull($printer->message_at);
        $this->assertSame(self::REASON, $printer->telemetry['message'], 'the long line is not cut to the telemetry width');

        // the admin resumed, the printer prints on and says nothing: the history keeps the reason
        $this->sync($auth, 'printing', ['id' => $job->id, 'status' => 'printing', 'progress' => 2, 'message' => ''], ['extruder' => 215.0]);
        $job->refresh();
        $this->assertSame(FarmPrintJob::STATUS_PRINTING, $job->status);
        $this->assertNull($job->paused_at);
        $this->assertCount(1, $job->messages);
        $this->assertSame(self::REASON, $printer->refresh()->message, 'an empty report does not wipe the last words');

        // the second pause of the same print (B3 on F26-000108 paused again after the resume) is news again, with its own words
        $this->sync($auth, 'paused', ['id' => $job->id, 'status' => 'paused', 'progress' => 2, 'message' => 'error: typ = WebRequestError, code = 10011719, message = Filament is tangled'], ['message' => 'error: typ = WebRequestError, code = 10011719, message = Filament is tangled']);
        $this->sync($auth, 'paused', ['id' => $job->id, 'status' => 'paused', 'progress' => 2, 'message' => 'error: typ = WebRequestError, code = 10011719, message = Filament is tangled']);
        $this->assertCount(2, $this->alerts('paused'));
        $this->assertLine($this->alerts('paused')[1], 'Filament is tangled');
        $job->refresh();
        $this->assertCount(2, $job->messages);
        $this->assertSame('Filament is tangled', substr($job->messageHistory()[0]['m'], -19), 'newest first');

        // the dashboard shows the printer's words and the job's last words, the order page the whole history
        $html = (string) $this->actingAs($this->admin)->get('/admin/farm')->assertOk()->getContent();
        $this->assertStringContainsString('data-printer-message', $html);
        $this->assertStringContainsString('Filament is tangled', $html);
        $this->assertStringContainsString('data-job-messages', $html);
        $this->assertStringContainsString('filament hub feed filament timeout', $html);
        $page = (string) $this->actingAs($this->admin)->get(route('admin.farm.orders.show', $order))->assertOk()->getContent();
        $this->assertStringContainsString('data-job-messages="'.$job->id.'"', $page);
        $this->assertStringContainsString('filament hub feed filament timeout', $page);
        $this->assertStringContainsString('Filament is tangled', $page);
    }

    public function test_a_pause_without_words_says_the_printer_gave_no_reason_and_the_switch_turns_the_mail_off(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        $job = $this->startedJob($printer, $auth);
        Mail::fake();
        $this->sync($auth, 'paused', ['id' => $job->id, 'status' => 'paused', 'progress' => 0, 'message' => '']);
        $this->sync($auth, 'paused', ['id' => $job->id, 'status' => 'paused', 'progress' => 0, 'message' => '']);
        $paused = $this->alerts('paused');
        $this->assertCount(1, $paused);
        $this->assertLine($paused[0], 'Hláška tiskárny: '.AgentService::NO_REASON);
        $this->assertNull($job->refresh()->messages, 'nothing to remember');

        // switched off: a new pause of another job is quiet; the settings page offers the switch
        Mail::fake();
        config(['farm.settings.notify_paused' => false]);
        $this->sync($auth, 'printing', ['id' => $job->id, 'status' => 'printing', 'progress' => 1]);
        $this->sync($auth, 'paused', ['id' => $job->id, 'status' => 'paused', 'progress' => 1, 'message' => 'x']);
        $this->assertSame([], $this->alerts('paused'));
        $this->actingAs($this->admin)->get('/admin/farm/settings')->assertOk()->assertSee('name="notify_paused"', false);
        $settings = app(FarmSettings::class)->all();
        $this->assertArrayHasKey('notify_paused', $settings);
    }

    public function test_the_history_keeps_the_last_twenty_messages(): void
    {
        $job = new FarmPrintJob;
        foreach (range(1, 25) as $i) {
            $job->noteMessage("message $i");
            $job->noteMessage("message $i");
        }
        $all = $job->messages;
        $this->assertCount(FarmPrintJob::MESSAGES_KEPT, $all);
        $this->assertSame('message 25', end($all)['m']);
        $this->assertSame('message 6', $all[0]['m']);
        $this->assertSame('message 25', $job->messageHistory()[0]['m']);
        $job->noteMessage('');
        $job->noteMessage(null);
        $this->assertCount(FarmPrintJob::MESSAGES_KEPT, $job->messages, 'empty words are no entry');
    }

    public function test_the_operators_confirmation_at_the_printer_clears_the_plate_and_the_queue_goes_on(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        $this->sync($auth);
        $order = $this->order();
        $this->credit(1000);
        $order = $this->pay($order);
        $this->assertSame(FarmOrder::STATUS_QUEUED, $order->status);
        $this->assertFalse($printer->refresh()->bed_clear);
        $this->assertSame([], $this->sync($auth)->json('commands'), 'the plate is not confirmed: nothing starts');

        // a stale moment (the agent restarted with an old memory) changes nothing
        $this->sync($auth, 'idle', null, ['bed_cleared_at' => now()->subHours(7)->timestamp]);
        $this->assertFalse($printer->refresh()->bed_clear);

        // the reset of the finished print at the printer: the plate is clear, the queued order starts in the same round
        $at = now()->subSeconds(5)->timestamp;
        $answer = $this->sync($auth, 'idle', null, ['bed_cleared_at' => $at]);
        $this->assertSame('start', $answer->json('commands.0.type'));
        $printer->refresh();
        $this->assertFalse($printer->bed_clear, 'the start used the confirmation up');
        $this->assertSame($at, $printer->bed_cleared_at->timestamp);
        $job = FarmPrintJob::findOrFail($answer->json('commands.0.job_id'));

        // the agent repeats the same moment for a minute: not a new confirmation (a job is on the machine anyway)
        $this->sync($auth, 'printing', ['id' => $job->id, 'status' => 'printing', 'progress' => 1], ['bed_cleared_at' => $at]);
        $this->assertFalse($printer->refresh()->bed_clear);
        $this->sync($auth, 'printing', ['id' => $job->id, 'status' => 'printing', 'progress' => 1], ['bed_cleared_at' => $at + 30]);
        $this->assertFalse($printer->refresh()->bed_clear, 'a print runs: no confirmation while it does');
        $this->sync($auth, 'idle', ['id' => $job->id, 'status' => 'done', 'progress' => 100, 'print_duration' => 600, 'filament_used' => 500]);
        $this->assertSame(FarmOrder::STATUS_DONE, $order->refresh()->status);
        $this->sync($auth, 'idle', null, ['bed_cleared_at' => $at + 700]);
        $this->assertTrue($printer->refresh()->bed_clear, 'the next confirmation, after the print, clears the plate again');
        $this->actingAs($this->admin)->get('/admin/farm')->assertOk()->assertSee($printer->bed_cleared_at->format('j. n. H:i'));
    }

    public function test_the_file_list_is_kept_and_the_admins_commands_never_touch_the_running_job(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        $job = $this->startedJob($printer, $auth);
        $running = 'mp'.$job->id.'-'.$job->remote_filename;
        $listing = ['files' => [
            ['name' => $running, 'size' => 3_667_786, 'modified' => 1791632678],
            ['name' => 'mp3-matplace-F26-000003.gcode', 'size' => 1_048_576, 'modified' => 1791000000],
            ['name' => '3DBenchy_PLA_0.2_1h2m.gcode', 'size' => 1_153_356, 'modified' => 1790022115],
            ['name' => '../evil.gcode', 'size' => 1, 'modified' => 1],
        ], 'free' => 2967470080, 'total' => 6228017152];
        $this->postJson("/api/agent/printers/{$printer->key}/files", $listing, $auth)->assertOk();
        $this->postJson("/api/agent/printers/{$printer->key}/files", $listing, ['Authorization' => 'Bearer mpa_wrong'])->assertStatus(401);
        $this->postJson('/api/agent/printers/kobra-s1-02/files', $listing, $auth)->assertNotFound();
        $printer->refresh();
        $this->assertNotNull($printer->files_at);
        $this->assertCount(3, $printer->files['files'], 'a name with a path in it is dropped');
        $this->assertSame(2967470080, $printer->files['free']);

        $page = (string) $this->actingAs($this->admin)->get(route('admin.farm.printers.edit', $printer))->assertOk()->getContent();
        $this->assertStringContainsString('Soubory na tiskárně', $page);
        $this->assertStringContainsString('3DBenchy_PLA_0.2_1h2m.gcode', $page);
        $this->assertStringContainsString('volné místo 2,76 GB z 5,80 GB', $page);
        $this->assertStringContainsString('běžící úloha', $page);
        $this->assertStringContainsString('Uklidit disk (1)', $page, 'one file of ours is not in use: mp3');
        $this->assertStringContainsString('Model z G‑kódu', $page);

        // the admin asks for a fresh list, a fetch, a delete and a cleanup: commands for the agent, in order
        $this->actingAs($this->admin)->post(route('admin.farm.printers.files.list', $printer))->assertRedirect()->assertSessionHas('status');
        $this->actingAs($this->admin)->post(route('admin.farm.printers.files.fetch', $printer), ['name' => '3DBenchy_PLA_0.2_1h2m.gcode'])->assertRedirect()->assertSessionHas('status');
        $this->actingAs($this->admin)->post(route('admin.farm.printers.files.delete', $printer), ['name' => $running])->assertRedirect()->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('admin.farm.printers.files.delete', $printer), ['name' => 'not-listed.gcode'])->assertRedirect()->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('admin.farm.printers.files.delete', $printer), ['name' => '3DBenchy_PLA_0.2_1h2m.gcode'])->assertRedirect()->assertSessionHas('status');
        $this->actingAs($this->admin)->post(route('admin.farm.printers.files.cleanup', $printer))->assertRedirect()->assertSessionHas('status');
        $commands = $this->sync($auth, 'printing', ['id' => $job->id, 'status' => 'printing', 'progress' => 3])->json('commands');
        $this->assertSame(['files', 'fetch', 'delete', 'cleanup'], array_column($commands, 'type'));
        $this->assertSame(['name' => '3DBenchy_PLA_0.2_1h2m.gcode'], $commands[1]['payload']);
        $this->assertSame(['name' => '3DBenchy_PLA_0.2_1h2m.gcode'], $commands[2]['payload']);
        $this->assertSame(['keep' => [$running]], $commands[3]['payload']);
        $this->assertNull(FarmCommand::where('type', 'delete')->where('payload->name', $running)->first(), 'the running job\'s file never becomes a delete');

        // the agent answers the cleanup with what went, how much came free and the fresh list
        $this->postJson("/api/agent/commands/{$commands[3]['id']}/result", ['ok' => true, 'message' => 'deleted 1 files, 1 MB freed'], $auth)->assertOk();
        $this->postJson("/api/agent/printers/{$printer->key}/files", ['files' => [$listing['files'][0]], 'free' => 2968470080, 'total' => 6228017152,
            'deleted' => [['name' => 'mp3-matplace-F26-000003.gcode', 'size' => 1_048_576]], 'freed' => 1_048_576], $auth)->assertOk();
        $page = (string) $this->actingAs($this->admin)->get(route('admin.farm.printers.edit', $printer))->assertOk()->getContent();
        $this->assertStringContainsString('smazáno 1 souborů, uvolněno 1,0 MB', $page);
        $this->assertStringContainsString('Smazáno: mp3-matplace-F26-000003.gcode', $page);
        $this->assertStringContainsString('deleted 1 files, 1 MB freed', $page, 'the command\'s result is on the page');
        $this->assertStringNotContainsString('3DBenchy_PLA_0.2_1h2m.gcode</td>', $page);
        $this->actingAs($this->admin)->post(route('admin.farm.printers.files.cleanup', $printer))->assertRedirect()->assertSessionHas('error', __('farm.admin.files.cleanup_none'));

        // a manual printer has no files to read from here
        $printer->update(['mode' => FarmPrinter::MODE_MANUAL]);
        $this->actingAs($this->admin)->post(route('admin.farm.printers.files.list', $printer))->assertRedirect()->assertSessionHas('error');
        $this->actingAs($this->user)->post(route('admin.farm.printers.files.list', $printer))->assertRedirect();
    }

    public function test_a_fetched_file_lands_in_the_printers_folder_and_bad_names_or_sizes_are_refused(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        $this->sync($auth);
        // the agent asks for JSON (server.py sends Accept: application/json), so a refusal is a 422, not a redirect
        $json = $auth + ['Accept' => 'application/json'];
        $upload = fn (string $name, int $kb = 12) => $this->post("/api/agent/printers/{$printer->key}/files/upload", ['file' => UploadedFile::fake()->create('upload.bin', $kb), 'name' => $name], $json);
        $upload('mp5-matplace-F26-000005.gcode')->assertOk()->assertJsonPath('name', 'mp5-matplace-F26-000005.gcode')->assertJsonPath('size', 12 * 1024);
        Storage::disk('farm')->assertExists('printers/'.$printer->id.'/files/mp5-matplace-F26-000005.gcode');
        $upload('../../.env')->assertStatus(422);
        $upload('sub/dir.gcode')->assertStatus(422);
        $upload('too-big.gcode', 300 * 1024 + 1)->assertStatus(422);
        $upload('just-right.gcode', 300 * 1024)->assertOk();
        $this->post("/api/agent/printers/{$printer->key}/files/upload", ['file' => UploadedFile::fake()->create('x.bin', 1), 'name' => 'x.gcode'], ['Authorization' => 'Bearer mpa_wrong', 'Accept' => 'application/json'])->assertStatus(401);
        $this->post("/api/agent/printers/{$printer->key}/files/upload", ['name' => 'x.gcode'], $json)->assertStatus(422);

        // the copies are on the printer's page, downloadable to the admin's computer, and removable from the server
        $page = (string) $this->actingAs($this->admin)->get(route('admin.farm.printers.edit', $printer))->assertOk()->getContent();
        $this->assertStringContainsString('Kopie na serveru', $page);
        $this->assertStringContainsString('mp5-matplace-F26-000005.gcode', $page);
        $this->actingAs($this->admin)->get(route('admin.farm.printers.files.download', [$printer, 'mp5-matplace-F26-000005.gcode']))->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=mp5-matplace-F26-000005.gcode');
        $this->actingAs($this->admin)->get(route('admin.farm.printers.files.download', [$printer, 'missing.gcode']))->assertNotFound();
        $this->actingAs($this->user)->get(route('admin.farm.printers.files.download', [$printer, 'mp5-matplace-F26-000005.gcode']))->assertRedirect();
        $this->actingAs($this->admin)->post(route('admin.farm.printers.files.forget', $printer), ['name' => 'mp5-matplace-F26-000005.gcode'])->assertRedirect()->assertSessionHas('status');
        Storage::disk('farm')->assertMissing('printers/'.$printer->id.'/files/mp5-matplace-F26-000005.gcode');
    }

    public function test_a_start_refused_for_an_empty_slot_shows_on_the_dashboard_as_the_last_command(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        $this->sync($auth);
        $order = $this->order();
        $this->credit(1000);
        $this->pay($order);
        $printer->refresh()->update(['bed_clear' => true]);
        $cmd = $this->sync($auth)->json('commands.0');
        Mail::fake();
        $this->postJson("/api/agent/commands/{$cmd['id']}/result", ['ok' => false, 'message' => 'slot 4 is empty: load a spool into it and confirm the plate again'], $auth)->assertOk();
        $this->assertCount(1, $this->alerts('problem'), 'the start_failed mail as before');
        $this->assertSame(FarmOrder::STATUS_QUEUED, $order->refresh()->status);
        $html = (string) $this->actingAs($this->admin)->get('/admin/farm')->assertOk()->getContent();
        $this->assertStringContainsString('data-last-command', $html);
        $this->assertStringContainsString('start tisku · selhal', $html);
        $this->assertStringContainsString('slot 4 is empty', $html);
    }
}
