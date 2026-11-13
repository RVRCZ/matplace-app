<?php

namespace Tests\Feature;

use App\Domain\Farm\ColorLimit;
use App\Domain\Farm\FarmRefusal;
use App\Domain\Farm\GcodeSlot;
use App\Domain\Farm\OrderService;
use App\Domain\Farm\Palette;
use App\Domain\Farm\SpoolPositions;
use App\Models\FarmAgent;
use App\Models\FarmColor;
use App\Models\FarmMaterial;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrinterSlot;
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
 * A printer whose changer (ACE) was taken off (Roman, 13 Nov 2026, B6: a Kobra S1 run by the agent with one spool of
 * glow green led straight into the head; docs/AL.md §24). The admin's switch "changer attached" turned off leaves the
 * machine one position, the directly fed spool: the others keep their rows and come back with the switch, are not
 * offered and do not count as colours of the machine; no print of several colours goes there; a kind that breaks in
 * the changer (manual_only) may sit in the direct spool and prints by itself; the G-code copy selects no position and
 * the start tells the agent to check none.
 */
class FarmPrinterNoChangerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $user;

    /** a Kobra S1 run by the agent: black in position 1, white in position 3 */
    private FarmPrinter $printer;

    private array $auth;

    private FarmColor $white;

    private FarmColor $black;

    private FarmMaterial $glow;

    private FarmColor $glowColor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->setRole(User::ROLE_ADMIN, true);
        $this->user = User::factory()->create(['email' => 'customer@example.com']);
        [$agent, $token] = FarmAgent::issue('farm B');
        $this->auth = ['Authorization' => 'Bearer '.$token];
        $this->printer = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $this->printer->update(['mode' => FarmPrinter::MODE_AGENT, 'farm_agent_id' => $agent->id, 'last_seen_at' => now()]);
        FarmPrinter::where('id', '!=', $this->printer->id)->update(['enabled' => false]);
        $this->white = $this->slot(2)->color;
        $this->black = FarmColor::where('farm_material_id', $this->white->farm_material_id)->where('id', '!=', $this->white->id)->firstOrFail();
        $this->black->update(['enabled' => true, 'hex' => '#101012']);
        $this->slot(0)->update(['farm_color_id' => $this->black->id, 'remaining_g' => 900, 'enabled' => true]);
        $this->glow = FarmMaterial::where('finish', 'luminous')->firstOrFail();
        $this->glow->update(['manual_only' => true]);
        $this->glowColor = $this->glow->colors()->firstOrFail();
        $this->glowColor->update(['enabled' => true, 'hex' => '#B5F57A']);
    }

    private function slot(int $n): FarmPrinterSlot
    {
        return $this->printer->slots()->where('slot', $n)->firstOrFail();
    }

    private function rows(): array
    {
        return $this->printer->slots()->get()->map(fn (FarmPrinterSlot $s) => [$s->slot, $s->farm_color_id, $s->remaining_g, $s->enabled, $s->label])->all();
    }

    private function changer(bool $attached)
    {
        return $this->actingAs($this->admin)->post("/admin/farm/printers/{$this->printer->id}/changer", ['attached' => $attached ? 1 : 0])
            ->assertRedirect(route('admin.farm.printers.show', $this->printer).'#slots');
    }

    private function detail()
    {
        return $this->actingAs($this->admin)->get(route('admin.farm.printers.show', $this->printer))->assertOk();
    }

    private function order(): FarmOrder
    {
        $path = sys_get_temp_dir().'/mp_nochanger_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);
        $uuid = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated()->json('file.uuid');

        return FarmOrder::where('token', basename($this->actingAs($this->user)->postJson('/farm/orders', ['file' => $uuid])->assertCreated()->json('url')))->firstOrFail();
    }

    /** the colour ids on offer for an order, as the order page reads them */
    private function offered(FarmOrder $order): array
    {
        $slots = array_column($this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json('colors'), 'slot');

        return FarmPrinterSlot::whereIn('id', $slots)->pluck('farm_color_id')->map(fn ($id) => (int) $id)->all();
    }

    private function credit(int $amount): void
    {
        $this->actingAs($this->user)->post('/account/credit', ['amount' => $amount])->assertRedirect();
        $payment = Payment::latest('id')->firstOrFail();
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $this->user->refresh();
    }

    private function pay(FarmOrder $order, FarmPrinterSlot $slot)
    {
        $color = collect($this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->json('colors'))->firstWhere('slot', $slot->id);
        $this->assertNotNull($color, 'the spool is on offer');

        return $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $slot->id, 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $color['total']]);
    }

    private function sync(array $telemetry = [], string $state = 'idle', ?array $job = null)
    {
        return $this->postJson('/api/agent/sync', ['version' => '0.2.14', 'printers' => [['key' => $this->printer->key, 'state' => $state, 'telemetry' => ['extruder' => 25.0] + $telemetry, 'job' => $job]]], $this->auth)->assertOk();
    }

    public function test_one_click_leaves_the_direct_spool_and_gives_the_positions_back(): void
    {
        $before = $this->rows();
        $this->assertTrue($this->printer->hasDetachableChanger());
        $this->assertFalse($this->printer->withoutChanger(), 'attached until somebody says otherwise');
        $this->assertSame(4, $this->printer->colorLimit());
        $this->detail()->assertSee('data-changer="on"', false)->assertSee(__('farm.admin.changer.on'))->assertSee(__('farm.admin.changer.detach'))->assertDontSee('data-detached', false);
        $order = $this->order();
        $this->assertEqualsCanonicalizing([$this->black->id, $this->white->id], $this->offered($order));

        // off: one position, one colour, the other rows untouched and not on offer
        $this->changer(false)->assertSessionHas('status', __('farm.admin.changer.now_off', ['printer' => $this->printer->name]))->assertSessionMissing('error');
        $this->printer->refresh();
        $this->assertTrue($this->printer->withoutChanger());
        $this->assertFalse($this->printer->changesSpools());
        $this->assertSame($before, $this->rows(), 'no row was touched');
        $this->assertSame(1, $this->printer->colorLimit());
        $this->assertSame(1, ColorLimit::farm(), 'the only machine of the farm has one position now');
        $this->assertSame(1, $this->getJson('/api/config')->assertOk()->json('max_colors'));
        $this->assertSame([__('farm.position.direct'), 'ACE 2', 'ACE 3', 'ACE 4'], SpoolPositions::names($this->printer));
        $this->assertSame(__('farm.position.direct'), $this->slot(0)->positionLabel());
        $this->assertFalse($this->slot(0)->isDetached());
        $this->assertTrue($this->slot(2)->isDetached());
        $this->assertSame([$this->black->id], $this->offered($order));
        $this->assertSame([$this->black->id], app(OrderService::class)->offeredColors()->pluck('color.id')->all(), 'the start page says the same');
        $loaded = collect((new Palette)->all())->keyBy('code');
        if ($this->white->code && $loaded->has($this->white->code)) {
            $this->assertFalse($loaded[$this->white->code]['loaded'], 'the colour window does not call the white loaded');
            $this->assertTrue($loaded[$this->black->code]['loaded'] ?? true);
        }
        $this->assertEqualsCanonicalizing([$this->black->farm_material_id], app(OrderService::class)->loadedMaterials()->pluck('id')->all());

        // the admin sees the other positions greyed with the reason, and their fields are not sent
        $page = $this->detail()->assertSee('data-changer="off"', false)->assertSee(__('farm.admin.changer.off'))->assertSee(__('farm.admin.changer.attach'))
            ->assertSee(__('farm.position.direct'))->assertSee(__('farm.admin.changer.detached_row'))->assertSee(__('farm.admin.changer.short_off'));
        $this->assertSame(3, substr_count($page->getContent(), 'data-detached'));
        $this->assertSame(9, substr_count(strstr(strstr($page->getContent(), '<section id="slots"'), '</section>', true), ' disabled'), 'colour, grams and the tick of three positions');
        $this->actingAs($this->admin)->get('/admin/farm/printers/load')->assertOk()->assertSee(__('farm.admin.changer.short_off'))->assertSee('data-detached', false);
        $this->actingAs($this->admin)->get('/admin/farm/printers')->assertOk()->assertSee(__('farm.admin.changer.short_off'));
        $this->actingAs($this->admin)->get("/admin/farm/printers/{$this->printer->id}")->assertOk()->assertSee('data-changer="off"', false);
        // saving the filaments of the detail sends the direct spool only: the rest stays as it was
        $this->actingAs($this->admin)->post('/admin/farm/printers/load', ['back_printer' => $this->printer->id, 'slots' => [$this->printer->id => [0 => ['color' => $this->black->id, 'remaining_g' => 850, 'enabled' => 1]]]])->assertSessionHasNoErrors();
        $this->assertSame(850.0, $this->slot(0)->remaining_g);
        $this->assertSame(array_slice($before, 1), array_slice($this->rows(), 1));

        // on again: every position as it was, offered at once
        $this->changer(true)->assertSessionHas('status', __('farm.admin.changer.now_on', ['printer' => $this->printer->name]))->assertSessionMissing('error');
        $this->printer->refresh();
        $this->assertFalse($this->printer->withoutChanger());
        $this->assertSame(4, $this->printer->colorLimit());
        $this->assertSame(array_slice($before, 1), array_slice($this->rows(), 1));
        $this->assertEqualsCanonicalizing([$this->black->id, $this->white->id], $this->offered($order));
        $this->assertSame(['ACE 1', 'ACE 2', 'ACE 3', 'ACE 4'], SpoolPositions::names($this->printer));
        $this->detail()->assertSee('data-changer="on"', false)->assertDontSee('data-detached', false);
    }

    public function test_only_a_machine_whose_changer_comes_off_has_the_switch(): void
    {
        // the Kobra 3 Max feeds from an ACE like the S1
        $max = FarmPrinter::where('key', 'kobra-3-max-01')->firstOrFail();
        $this->assertTrue($max->hasDetachableChanger());
        $this->actingAs($this->admin)->get(route('admin.farm.printers.show', $max))->assertOk()->assertSee('data-changer="on"', false);

        // the Kobra X has its own rack, a tool changer its tools: no switch, and the address refuses
        foreach (['machine_kobrax.json', 'machine_coreone_indx8t.json'] as $profile) {
            $this->printer->update(['machine_profile' => $profile]);
            $this->assertFalse($this->printer->hasDetachableChanger(), $profile);
            $this->detail()->assertDontSee('data-changer', false);
            $this->changer(false)->assertSessionHas('error', __('farm.admin.changer.not_here', ['printer' => $this->printer->name]));
            $this->assertFalse($this->printer->fresh()->withoutChanger(), $profile);
        }

        // a machine switched off and then given such a profile in its settings gets its changer back
        $this->printer->update(['machine_profile' => 'machine.json']);
        $this->changer(false);
        $this->assertTrue($this->printer->fresh()->withoutChanger());
        $form = $this->printer->only(['name', 'model', 'key', 'mode', 'farm_agent_id', 'bed_x', 'bed_y', 'bed_z', 'nozzle_mm', 'time_factor', 'weight_factor'])
            + ['enabled' => 1, 'machine_profile' => 'machine_kobrax.json', 'process_profiles' => json_encode($this->printer->process_profiles)];
        $this->actingAs($this->admin)->post("/admin/farm/printers/{$this->printer->id}", $form)->assertRedirect('/admin/farm/printers')->assertSessionHasNoErrors();
        $this->assertFalse($this->printer->fresh()->withoutChanger());
    }

    public function test_a_glow_kind_prints_by_itself_from_the_direct_spool_and_not_from_the_changer(): void
    {
        $load = fn (int $slot) => $this->actingAs($this->admin)->from('/admin/farm/printers/load')->post('/admin/farm/printers/load', ['slots' => [$this->printer->id => [$slot => ['color' => $this->glowColor->id, 'remaining_g' => 800, 'enabled' => 1]]]]);
        $refused = __('farm.admin.manual_only_refused', ['color' => $this->glowColor->fullLabel(), 'printer' => $this->printer->name]);

        // with the changer on the machine: today's ban
        $load(0)->assertSessionHasErrors(["slots.{$this->printer->id}.0.color" => $refused]);
        $this->assertSame($this->black->id, $this->slot(0)->farm_color_id);
        $this->assertFalse(FarmPrinterSlot::kindFits($this->printer, $this->glowColor));

        // without it: into the direct spool yes, into a position of the changer still no
        $this->changer(false);
        $this->printer->refresh();
        $this->assertTrue(FarmPrinterSlot::kindFits($this->printer, $this->glowColor));
        $load(1)->assertSessionHasErrors(["slots.{$this->printer->id}.1.color" => $refused]);
        $load(0)->assertSessionHasNoErrors()->assertSessionMissing('error');
        $direct = $this->slot(0);
        $this->assertSame([$this->glowColor->id, true], [$direct->farm_color_id, $direct->enabled]);
        $this->assertTrue($direct->mayOffer());

        // offered, paid, and sent to the machine without a person: the start says "direct", the file selects no position
        $order = $this->order();
        $this->assertSame([$this->glowColor->id], $this->offered($order));
        $this->credit(1000);
        $this->pay($order, $direct)->assertOk();
        $order->refresh();
        $this->assertSame([$this->glowColor->id, $this->printer->id, FarmOrder::STATUS_QUEUED], [$order->farm_color_id, $order->farm_printer_id, $order->status]);
        $this->assertTrue($this->printer->canFeed($order));
        $this->sync()->assertJsonCount(0, 'commands');
        $this->actingAs($this->admin)->post("/admin/farm/printers/{$this->printer->id}/bed", ['clear' => 1])->assertRedirect();
        $cmd = $this->sync()->assertJsonCount(1, 'commands')->json('commands.0');
        $this->assertSame('start', $cmd['type']);
        $this->assertTrue($cmd['payload']['direct']);
        $this->assertSame(0, $cmd['payload']['slot']);
        $this->assertArrayNotHasKey('filaments', $cmd['payload']);
        $gcode = $this->get($cmd['payload']['gcode_url'], $this->auth)->assertOk();
        $text = $gcode->streamedContent();
        $this->assertSame(hash('sha256', $text), $gcode->headers->get('X-Content-Sha256'));
        $this->assertDoesNotMatchRegularExpression('/^T\d/m', $text, 'no spool position is selected');
        $this->assertStringNotContainsString('matplace slot', $text, 'and nothing is heated for a spool change');
        $this->assertMatchesRegularExpression('/^G9111 bedTemp=\d+ extruderTemp=\d+\r?\nM117\r?\nG1 /m', $text);
        // the admin's own download is the same file
        $this->assertSame($text, $this->actingAs($this->admin)->get("/admin/farm/orders/{$order->token}/print.gcode")->assertOk()->streamedContent());
        // should a firmware want its tool line after all: the config switch brings today's lines back
        config(['farm.direct_spool_tool_line' => true]);
        $this->assertStringContainsString("\nT0 ; slot chosen by matplace farm\n", $this->get($cmd['payload']['gcode_url'], $this->auth)->assertOk()->streamedContent());
        config(['farm.direct_spool_tool_line' => false]);

        $this->postJson("/api/agent/commands/{$cmd['id']}/result", ['ok' => true], $this->auth)->assertOk();
        $job = FarmPrintJob::findOrFail($cmd['job_id']);
        $this->sync([], 'printing', ['id' => $job->id, 'status' => 'printing', 'progress' => 50, 'print_duration' => 600, 'filament_used' => 1200]);
        $this->sync([], 'idle', ['id' => $job->id, 'status' => 'done', 'progress' => 100, 'print_duration' => 1200, 'filament_used' => 2400]);
        $this->assertSame(FarmOrder::STATUS_DONE, $order->refresh()->status);

        // the changer goes back on: the glow spool is a position of the changer again, switched off and named
        $this->changer(true)->assertSessionHas('error', __('farm.admin.manual_only_disabled', ['n' => 1, 'color' => $this->glowColor->fullLabel(), 'printer' => $this->printer->name]));
        $direct->refresh();
        $this->assertSame([$this->glowColor->id, false], [$direct->farm_color_id, $direct->enabled]);
        $this->assertSame([$this->white->id], $this->offered($this->order()));
    }

    public function test_no_print_of_several_colours_goes_to_a_machine_without_its_changer(): void
    {
        $orders = app(OrderService::class);
        $main = $this->slot(0);
        $this->assertSame([$this->white->id], $orders->secondSpools($main)->pluck('farm_color_id')->all());
        $this->assertCount(4, $orders->positionsOf($main));
        $this->assertCount(2, $orders->spoolsForParts($main, ['body', 'lid'], ['lid' => $this->white->id]));

        // an order paid for the white of position 3 while the changer was on
        $order = $this->order();
        $this->credit(1000);
        $this->pay($order, $this->slot(2))->assertOk();
        $order->refresh();
        $this->assertSame([2], $order->printSlots());

        $this->changer(false)->assertSessionHas('error', __('farm.admin.changer.orders_wait', ['orders' => $order->number]));
        $this->printer->refresh();
        $main = $this->slot(0);
        $this->assertSame([], $orders->secondSpools($main)->pluck('farm_color_id')->all());
        $this->assertSame([], $orders->secondSpoolsAcross($main)->pluck('farm_color_id')->all());
        $positions = $orders->positionsOf($main);
        $this->assertSame([[__('farm.position.direct'), true]], array_map(fn ($p) => [$p['label'], $p['main']], $positions), 'the chooser shows the one position');
        try {
            $orders->spoolsForParts($main, ['body', 'lid'], ['lid' => $this->white->id]);
            $this->fail('two colours on a machine with one spool');
        } catch (FarmRefusal $e) {
            $this->assertSame('too_many_colors', $e->reason);
            $this->assertSame(1, $e->data['n'] ?? null);
        }
        // a change by height finds no second spool: the print stays in its one colour
        $this->assertSame([], $orders->spoolsForChanges($main, [['z' => 2.0, 'hex' => '#ffffff', 'code' => null]], [$this->white->id]));

        // the order sliced for the changer waits; nothing prints the wrong colour from the direct spool
        $this->assertFalse($this->printer->canFeed($order));
        $this->assertSame([$order->id], $this->printer->ordersWaitingForChanger()->pluck('id')->all());
        $this->actingAs($this->admin)->post("/admin/farm/printers/{$this->printer->id}/bed", ['clear' => 1])->assertRedirect();
        $this->sync()->assertJsonCount(0, 'commands');
        $this->assertSame(0, FarmPrintJob::count());
        $this->detail()->assertSee('data-changer-waiting', false)->assertSee($order->number);
        // a new customer cannot pay for the white either
        $this->assertSame([$this->black->id], $this->offered($this->order()));

        // with the changer back it starts, from its own position
        $this->changer(true);
        $cmd = $this->sync()->assertJsonCount(1, 'commands')->json('commands.0');
        $this->assertSame(2, $cmd['payload']['slot']);
        $this->assertArrayNotHasKey('direct', $cmd['payload']);
        $this->assertStringContainsString("\nT2 ; slot chosen by matplace farm\n", $this->get($cmd['payload']['gcode_url'], $this->auth)->assertOk()->streamedContent());
    }

    public function test_what_the_printer_reports_is_shown_and_never_moves_the_switch(): void
    {
        // an agent that does not tell (before 0.2.14): nothing next to the switch
        $this->sync();
        $this->assertNull($this->printer->fresh()->reportedChanger());
        $this->detail()->assertDontSee('data-changer-reported', false);

        // the printer says an ACE answers and the switch says attached: shown, no warning
        $this->sync(['ace_units' => 1]);
        $this->assertTrue($this->printer->fresh()->reportedChanger());
        $this->detail()->assertSee('data-changer-reported="yes"', false)->assertSee(__('farm.admin.changer.reported_yes'))->assertDontSee('data-changer-mismatch', false)->assertDontSee('ace_units');

        // no ACE answers while the switch says attached: the admin is warned, the switch stays
        $this->sync(['ace_units' => 0]);
        $this->assertTrue($this->printer->fresh()->changerMismatch());
        $this->detail()->assertSee('data-changer-reported="no"', false)->assertSee('data-changer-mismatch', false)->assertSee(__('farm.admin.changer.reported_no'))->assertSee(__('farm.admin.changer.mismatch'));
        $this->assertFalse($this->printer->fresh()->withoutChanger(), 'the switch is a person\'s');

        // switched off by hand: the two agree; an ACE plugged back in without the switch warns the other way
        $this->changer(false);
        $this->assertFalse($this->printer->fresh()->changerMismatch());
        $this->detail()->assertDontSee('data-changer-mismatch', false);
        $this->sync(['ace_units' => 1]);
        $this->assertTrue($this->printer->fresh()->changerMismatch());
        $this->assertTrue($this->printer->fresh()->withoutChanger());
        $this->detail()->assertSee('data-changer-mismatch', false);
    }

    public function test_the_copy_for_a_machine_without_a_changer_selects_no_position(): void
    {
        $sliced = "; HEADER_BLOCK_START\n; HEADER_BLOCK_END\nG9111 bedTemp=55 extruderTemp=220\nM117\nT0\nG1 X10 Y10 F3000\n;LAYER_CHANGE\n;Z:0.2\n;HEIGHT:0.2\nM104 S215\nG1 X20 Y20 E1\nM140 S55\n";
        $direct = GcodeSlot::retarget($sliced, null, ['nozzle' => 230, 'nozzle_first' => 235, 'bed' => 65]);
        $this->assertSame("; HEADER_BLOCK_START\n; HEADER_BLOCK_END\nG9111 bedTemp=65 extruderTemp=235\nM117\nG1 X10 Y10 F3000\n;LAYER_CHANGE\n;Z:0.2\n;HEIGHT:0.2\nM104 S230\nG1 X20 Y20 E1\nM140 S65\n", $direct);
        // without temperatures of its own only the tool line goes; a file the slicer wrote without one stays as it is
        $this->assertSame(str_replace("T0\n", '', $sliced), GcodeSlot::retarget($sliced, null));
        $plain = str_replace("T0\n", '', $sliced);
        $this->assertSame($plain, GcodeSlot::retarget($plain, null));
        // the machine with a changer gets what it always got
        $this->assertStringContainsString("M104 S235 ; matplace slot: heat before the spool change\nM109 S235 ; matplace slot: wait for it\nT3 ; slot chosen by matplace farm\n", GcodeSlot::retarget($sliced, 3, ['nozzle' => 230, 'nozzle_first' => 235, 'bed' => 65]));

        // the copies do not share a file, and a change by height is not written into the direct one
        $path = sys_get_temp_dir().'/mp_direct_'.uniqid().'.gcode';
        file_put_contents($path, $sliced);
        $a = GcodeSlot::fileFor($path, null, ['nozzle' => 230, 'bed' => 65], [['slot' => 2, 'z' => 0.1]]);
        $b = GcodeSlot::fileFor($path, 0, ['nozzle' => 230, 'bed' => 65]);
        $this->assertNotSame($a, $b);
        $this->assertStringContainsString('.direct', basename($a));
        $this->assertDoesNotMatchRegularExpression('/^T\d/m', (string) file_get_contents($a));
        $this->assertMatchesRegularExpression('/^T0 ; slot chosen/m', (string) file_get_contents($b));
        @unlink($a);
        @unlink($b);
        @unlink($path);
    }

    public function test_every_text_is_said_in_the_three_languages(): void
    {
        foreach (['cs', 'en', 'es'] as $locale) {
            app()->setLocale($locale);
            foreach (['on', 'off', 'detach', 'attach', 'on_hint', 'off_hint', 'detached_row', 'short_off', 'reported_yes', 'reported_no', 'mismatch', 'now_on', 'now_off', 'not_here', 'orders_wait'] as $key) {
                $this->assertNotSame('farm.admin.changer.'.$key, __('farm.admin.changer.'.$key), "$locale $key");
            }
            $this->assertNotSame('farm.position.direct', __('farm.position.direct'), $locale);
            $this->assertStringContainsString('B6', __('farm.admin.changer.now_off', ['printer' => 'B6']));
            $this->assertStringContainsString('F26-000001', __('farm.admin.changer.orders_wait', ['orders' => 'F26-000001']));
        }
    }
}
