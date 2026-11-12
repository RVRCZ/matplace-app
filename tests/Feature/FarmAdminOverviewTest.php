<?php

namespace Tests\Feature;

use App\Domain\Farm\PrinterStatus;
use App\Models\Farm;
use App\Models\FarmAgent;
use App\Models\FarmColor;
use App\Models\FarmCommand;
use App\Models\FarmMaterial;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrinterMaterial;
use App\Models\FarmPrintJob;
use App\Models\ModelFile;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The admin's short list "Tiskárny a fronta" (printers by farm, one row each), the printer's detail and the filament's
 * detail (docs/AQ.md).
 */
class FarmAdminOverviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $admin;

    private FarmAgent $agent;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Storage::fake('public');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $this->user = User::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->setRole(User::ROLE_ADMIN, true);
        $this->agent = FarmAgent::create(['name' => 'Agent1 S1', 'token_hash' => hash('sha256', 'secret')]);
    }

    /** A seeded printer run by the agent, heard from just now, free, plate not cleared. */
    private function printer(string $key = 'kobra-s1-01', array $attributes = []): FarmPrinter
    {
        $printer = FarmPrinter::where('key', $key)->firstOrFail();
        $printer->update($attributes + ['mode' => FarmPrinter::MODE_AGENT, 'farm_agent_id' => $this->agent->id, 'state' => FarmPrinter::STATE_IDLE, 'last_seen_at' => now(), 'bed_clear' => false]);

        return $printer->refresh();
    }

    private function order(FarmPrinter $printer, string $status, string $number, array $attributes = []): FarmOrder
    {
        $file = ModelFile::create([
            'uuid' => (string) Str::uuid(), 'owner_user_id' => $this->user->id, 'original_name' => 'cube.stl', 'ext' => 'stl', 'size_bytes' => 684,
            'sha256' => str_repeat('0', 64), 'storage_path' => 'files/x/original.stl', 'stl_path' => 'files/x/model.stl', 'status' => ModelFile::STATUS_READY,
        ]);

        return FarmOrder::create($attributes + [
            'token' => Str::random(32), 'number' => $number, 'user_id' => $this->user->id, 'model_file_id' => $file->id, 'status' => $status,
            'farm_material_id' => FarmMaterial::firstOrFail()->id, 'farm_printer_id' => $printer->id, 'est_minutes' => 60, 'paid_at' => now(),
        ]);
    }

    /** The printer's row of the overview, cut out of the page. */
    private function row(FarmPrinter $printer): string
    {
        $html = (string) $this->actingAs($this->admin)->get('/admin/farm')->assertOk()->getContent();
        $this->assertSame(1, preg_match('/<li[^>]*data-printer-row="'.$printer->id.'".*?<\/li>/s', $html, $m), 'the row of printer '.$printer->id);

        return $m[0];
    }

    public function test_the_overview_lists_printers_by_farm_and_leaves_the_details_to_the_printer_page(): void
    {
        $u = Farm::where('code', 'U')->firstOrFail();
        $b = Farm::where('code', 'B')->firstOrFail();
        $u->update(['street' => 'Útušice 12', 'zip' => '332 09']);
        $first = $this->printer('kobra-s1-01', ['farm_id' => $b->id, 'snapshot_path' => 'printers/1/snap.jpg', 'snapshot_at' => now(), 'telemetry' => ['extruder' => 215.3]]);
        $second = $this->printer('kobra-3-max-01', ['farm_id' => $u->id]);
        $third = FarmPrinter::where('key', 'kobra-s1-02')->firstOrFail();   // no farm

        $html = (string) $this->actingAs($this->admin)->get('/admin/farm')->assertOk()->getContent();

        // farm U (sort 10) before farm B (sort 20), the printers without a farm last; the farm's address in its heading
        preg_match_all('/data-farm-group="([^"]*)"/', $html, $groups);
        $this->assertSame(['U', 'B', ''], $groups[1]);
        $this->assertStringContainsString('Farma U · Útušice', $html);
        $this->assertStringContainsString('Útušice 12, 332 09 Útušice', $html);
        $this->assertStringContainsString(__('farm.admin.farms.unassigned_group'), $html);
        $this->assertLessThan(strpos($html, 'data-printer-row="'.$first->id.'"'), strpos($html, 'data-printer-row="'.$second->id.'"'));
        $this->assertLessThan(strpos($html, 'data-printer-row="'.$third->id.'"'), strpos($html, 'data-printer-row="'.$first->id.'"'));

        // every row leads to the printer's detail; camera, telemetry and the commands are not on the list any more
        $this->assertStringContainsString(route('admin.farm.printers.show', $first), $this->row($first));
        $this->assertStringNotContainsString(route('admin.farm.printers.snapshot', $first), $html);
        $this->assertStringNotContainsString(__('farm.admin.light_on'), $html);
        $this->assertStringNotContainsString('extruder', $html);
        // the page keeps redrawing itself the way it did
        $this->assertStringContainsString('id="farm-dashboard" data-refresh="'.route('admin.farm.dashboard').'" data-every="30"', $html);

        $this->actingAs($this->user)->get('/admin/farm')->assertRedirect();
    }

    public function test_the_status_word_is_derived_from_what_the_farm_knows(): void
    {
        $p = $this->printer();
        $status = fn (?FarmPrintJob $job = null, ?FarmOrder $running = null) => PrinterStatus::of($p->refresh(), $job, $running);

        // free: the plate decides between "clear the plate" and standby
        $this->assertSame(PrinterStatus::BED, $status()['key']);
        $this->assertStringContainsString('data-status="bed"', $this->row($p));
        $p->update(['bed_clear' => true]);
        $this->assertSame(PrinterStatus::STANDBY, $status()['key']);
        $this->assertStringContainsString(__('farm.admin.ov.status.standby'), $this->row($p));

        // silent for longer than the farm's limit, or switched off
        $p->update(['last_seen_at' => now()->subHour()]);
        $this->assertSame(PrinterStatus::OFFLINE, $status()['key']);
        $p->update(['enabled' => false]);
        $this->assertSame(PrinterStatus::DISABLED, $status()['key']);
        $p->update(['enabled' => true, 'last_seen_at' => now()]);

        // a job of ours prints (also while it is only being sent)
        $order = $this->order($p, FarmOrder::STATUS_PRINTING, 'F26-000101', ['started_at' => now()]);
        $job = FarmPrintJob::create(['farm_order_id' => $order->id, 'farm_printer_id' => $p->id, 'slot' => 0, 'status' => FarmPrintJob::STATUS_SENT, 'progress' => 0]);
        $p->update(['bed_clear' => false]);
        $this->assertSame(PrinterStatus::PRINTING, $status($job)['key']);
        $job->update(['status' => FarmPrintJob::STATUS_PRINTING, 'progress' => 40]);
        $p->update(['state' => FarmPrinter::STATE_PRINTING]);
        $this->assertSame(PrinterStatus::PRINTING, $status($job)['key']);
        $this->assertTrue($status($job)['busy']);
        // what the printer says while it prints is a note, not a problem
        $p->update(['message' => 'Filament loaded', 'message_at' => now(), 'telemetry' => ['message' => 'Filament loaded']]);
        $this->assertSame(PrinterStatus::PRINTING, $status($job)['key']);

        // a paused print is a problem (Roman, 12 Nov 2026); the printer's own words are the reason when it has any
        $p->update(['state' => FarmPrinter::STATE_PAUSED, 'message' => null, 'telemetry' => []]);
        $job->update(['status' => FarmPrintJob::STATUS_PAUSED]);
        $this->assertSame([PrinterStatus::PROBLEM, __('farm.admin.ov.reason.paused')], [$status($job)['key'], $status($job)['reason']]);
        $p->update(['message' => 'Filament runout, slot 2', 'message_at' => now(), 'telemetry' => ['message' => 'Filament runout, slot 2']]);
        $this->assertSame('Filament runout, slot 2', $status($job)['reason']);
        $row = $this->row($p);
        $this->assertStringContainsString('data-status="problem"', $row);
        $this->assertStringContainsString('Filament runout, slot 2', $row);

        // a cancel the job outlived: failed at once, or not confirmed within the grace time
        $p->update(['state' => FarmPrinter::STATE_PRINTING, 'message' => null, 'telemetry' => []]);
        $job->update(['status' => FarmPrintJob::STATUS_PRINTING]);
        $cancel = FarmCommand::create(['farm_printer_id' => $p->id, 'farm_print_job_id' => $job->id, 'type' => FarmCommand::TYPE_CANCEL, 'created_by' => $this->admin->id]);
        $this->assertSame(PrinterStatus::PRINTING, $status($job)['key']);   // just sent
        $this->travel(PrinterStatus::CANCEL_GRACE_S + 5)->seconds();
        $p->update(['last_seen_at' => now()]);
        $this->assertSame([PrinterStatus::PROBLEM, __('farm.admin.ov.reason.cancel')], [$status($job)['key'], $status($job)['reason']]);
        $this->travelBack();
        $p->update(['last_seen_at' => now()]);
        $cancel->update(['status' => 'failed']);
        $this->assertSame(PrinterStatus::PROBLEM, $status($job)['key']);
        $cancel->delete();

        // the printer's error, with what it said; an unknown state
        $job->update(['status' => FarmPrintJob::STATUS_FAILED, 'finished_at' => now()]);
        $p->update(['state' => FarmPrinter::STATE_ERROR, 'message' => 'nozzle must be hot first']);
        $this->assertSame([PrinterStatus::PROBLEM, 'nozzle must be hot first'], [$status()['key'], $status()['reason']]);
        $p->update(['state' => FarmPrinter::STATE_UNKNOWN, 'message' => null]);
        $this->assertSame([PrinterStatus::PROBLEM, __('farm.admin.ov.reason.unknown')], [$status()['key'], $status()['reason']]);

        // a manual printer has no telemetry: its order in "printing" is what runs on it
        $manual = FarmPrinter::where('key', 'kobra-3-max-01')->firstOrFail();
        $this->assertSame(PrinterStatus::BED, PrinterStatus::of($manual)['key']);
        $running = $this->order($manual, FarmOrder::STATUS_PRINTING, 'F26-000102', ['started_at' => now()]);
        $this->assertSame(PrinterStatus::PRINTING, PrinterStatus::of($manual, null, $running)['key']);
        $this->assertStringContainsString('data-status="printing"', $this->row($manual));
    }

    public function test_one_plate_button_turns_the_mark_over_and_is_not_offered_while_a_print_runs(): void
    {
        $p = $this->printer();

        // blocked → the button releases the plate
        $row = $this->row($p);
        $this->assertStringContainsString('data-bed="blocked"', $row);
        $this->assertStringContainsString('name="clear" value="1"', $row);
        $this->assertStringContainsString(__('farm.admin.ov.bed_release'), $row);
        $this->assertStringNotContainsString('name="clear" value="0"', $row);
        $this->actingAs($this->admin)->postJson(route('admin.farm.printers.bed', $p), ['clear' => 1])->assertOk()->assertJson(['ok' => true]);
        $this->assertTrue($p->fresh()->bed_clear);

        // clear → the same place blocks it again
        $row = $this->row($p);
        $this->assertStringContainsString('data-bed="clear"', $row);
        $this->assertStringContainsString('name="clear" value="0"', $row);
        $this->assertStringContainsString(__('farm.admin.ov.bed_block'), $row);
        $this->assertStringNotContainsString('name="clear" value="1"', $row);
        $this->actingAs($this->admin)->postJson(route('admin.farm.printers.bed', $p), ['clear' => 0])->assertOk()->assertJson(['ok' => true]);
        $this->assertFalse($p->fresh()->bed_clear);

        // a running print: no button at all, and the request itself still refuses
        $order = $this->order($p, FarmOrder::STATUS_PRINTING, 'F26-000111', ['started_at' => now()]);
        FarmPrintJob::create(['farm_order_id' => $order->id, 'farm_printer_id' => $p->id, 'slot' => 0, 'status' => FarmPrintJob::STATUS_PRINTING, 'progress' => 10]);
        $p->update(['state' => FarmPrinter::STATE_PRINTING]);
        $row = $this->row($p);
        $this->assertStringContainsString('data-bed="busy"', $row);
        $this->assertStringNotContainsString('name="clear"', $row);
        $this->actingAs($this->admin)->postJson(route('admin.farm.printers.bed', $p), ['clear' => 1])->assertStatus(422);

        $this->actingAs($this->user)->post(route('admin.farm.printers.bed', $p), ['clear' => 1])->assertRedirect();
        $this->assertFalse($p->fresh()->bed_clear);
    }

    public function test_a_row_names_the_order_that_prints_or_the_last_one_printed_and_the_next_one_waiting(): void
    {
        $p = $this->printer();

        // nothing yet
        $row = $this->row($p);
        $this->assertStringContainsString(__('farm.admin.ov.no_order'), $row);
        $this->assertStringContainsString(__('farm.admin.ov.next_none'), $row);

        // the last one printed, with the hour it ended; an older one is not it
        $this->order($p, FarmOrder::STATUS_HANDED_OVER, 'F26-000120', ['finished_at' => now()->subDays(2)]);
        $last = $this->order($p, FarmOrder::STATUS_DONE, 'F26-000121', ['finished_at' => now()->subMinutes(5)]);
        $row = $this->row($p);
        $this->assertStringContainsString(__('farm.admin.ov.last'), $row);
        $this->assertStringContainsString(route('admin.farm.orders.show', $last), $row);
        $this->assertStringContainsString(__('farm.admin.ov.finished_at', ['time' => $last->finished_at->format('H:i')]), $row);
        $this->assertStringNotContainsString('F26-000120', $row);

        // the queue: the first order by its turn, and how many more wait
        $next = $this->order($p, FarmOrder::STATUS_QUEUED, 'F26-000123', ['queued_at' => now()->subMinutes(30)]);
        $this->order($p, FarmOrder::STATUS_QUEUED, 'F26-000124', ['queued_at' => now()->subMinutes(20)]);
        $this->order($p, FarmOrder::STATUS_QUEUED, 'F26-000125', ['queued_at' => now()->subMinutes(10)]);
        $row = $this->row($p);
        $this->assertStringContainsString(route('admin.farm.orders.show', $next), $row);
        $this->assertStringContainsString(__('farm.admin.ov.next_more', ['n' => 2]), $row);
        $this->assertStringNotContainsString('F26-000124', $row);

        // a running print: its number, the progress, about when it ends (60 min × 1.07, 40 % done → 39 min); the last one steps back
        $running = $this->order($p, FarmOrder::STATUS_PRINTING, 'F26-000122', ['started_at' => now()]);
        FarmPrintJob::create(['farm_order_id' => $running->id, 'farm_printer_id' => $p->id, 'slot' => 0, 'status' => FarmPrintJob::STATUS_PRINTING, 'progress' => 40]);
        $p->update(['state' => FarmPrinter::STATE_PRINTING]);
        $this->travelTo(now()->startOfDay()->addHours(10));
        $p->update(['last_seen_at' => now()]);
        $row = $this->row($p);
        $this->assertStringContainsString(route('admin.farm.orders.show', $running), $row);
        $this->assertStringContainsString('40 %', $row);
        $this->assertStringContainsString(__('farm.admin.ov.done_at', ['time' => '10:39']), $row);
        $this->assertStringNotContainsString('F26-000121', $row);
        $this->assertStringContainsString('F26-000123', $row);
    }

    public function test_the_printer_detail_carries_what_left_the_overview_and_its_filaments(): void
    {
        $white = FarmColor::where('name', 'bílá')->firstOrFail();
        $black = FarmColor::where('enabled', true)->where('id', '!=', $white->id)->firstOrFail();
        $p = $this->printer('kobra-s1-01', ['state' => FarmPrinter::STATE_ERROR, 'message' => 'nozzle must be hot first', 'message_at' => now(),
            'telemetry' => ['extruder' => 215.3], 'snapshot_path' => 'printers/1/snap.jpg', 'snapshot_at' => now(), 'files' => ['at' => now()->toIso8601String(), 'files' => []]]);
        $queued = $this->order($p, FarmOrder::STATUS_QUEUED, 'F26-000130', ['queued_at' => now()]);

        $html = (string) $this->actingAs($this->admin)->get(route('admin.farm.printers.show', $p))->assertOk()->getContent();
        // the live half: status, telemetry, the printer's message, camera, the commands, the queue; redrawn from its own address
        $this->assertStringContainsString('data-printer-status="problem"', $html);
        $this->assertStringContainsString('id="farm-dashboard" data-refresh="'.route('admin.farm.printers.show', $p).'"', $html);
        $this->assertStringContainsString('nozzle must be hot first', $html);
        $this->assertStringContainsString('extruder', $html);
        $this->assertStringContainsString(route('admin.farm.printers.snapshot', $p), $html);
        foreach (['reset_error', 'light_on', 'dry_start', 'bed_clear_do', 'queue'] as $key) {
            $this->assertStringContainsString(e(__('farm.admin.'.$key)), $html, $key);
        }
        $this->assertStringContainsString($queued->number, $html);
        // the files on its disk moved here from the settings page
        $this->assertStringContainsString('id="files"', $html);
        $this->assertStringContainsString(__('farm.admin.files.title'), $html);
        $this->actingAs($this->admin)->get(route('admin.farm.printers.edit', $p))->assertOk()->assertDontSee('id="files"', false)->assertSee(route('admin.farm.printers.show', $p));
        $this->actingAs($this->admin)->post(route('admin.farm.printers.files.list', $p))->assertRedirect(route('admin.farm.printers.show', $p).'#files');
        // only this printer
        $this->assertStringNotContainsString('Kobra 3 Max #1', $html);

        // its filaments: every position, the loaded one leads to the filament's detail
        $this->assertStringContainsString('id="slots"', $html);
        $this->assertStringContainsString('name="slots['.$p->id.'][3][color]"', $html);
        $this->assertStringContainsString('href="'.route('admin.farm.colors.show', $white).'" class="font-semibold underline" data-filament-link="'.$white->id.'"', $html);

        // saving them is the save of "Založení cívek" for this one machine, and comes back here
        $this->actingAs($this->admin)->post(route('admin.farm.printers.load.save'), ['back_printer' => $p->id, 'slots' => [$p->id => [
            0 => ['color' => $black->id, 'remaining_g' => 750, 'enabled' => 1],
            2 => ['color' => $white->id, 'remaining_g' => 400, 'enabled' => 1],
        ]]])->assertRedirect(route('admin.farm.printers.show', $p).'#slots')->assertSessionHas('status');
        $slots = $p->slots()->get()->keyBy('slot');
        $this->assertSame([$black->id, 750.0, true], [$slots[0]->farm_color_id, $slots[0]->remaining_g, $slots[0]->enabled]);
        $this->assertSame(400.0, $slots[2]->remaining_g);
        // without the mark it returns to the list of printers, as before
        $this->actingAs($this->admin)->post(route('admin.farm.printers.load.save'), ['slots' => [$p->id => [0 => ['color' => $black->id, 'remaining_g' => 700, 'enabled' => 1]]]])
            ->assertRedirect(route('admin.farm.printers'));

        $this->actingAs($this->user)->get(route('admin.farm.printers.show', $p))->assertRedirect();
        $this->get(route('admin.farm.printers.show', $p))->assertRedirect();
    }

    public function test_the_filament_detail_saves_the_spools_own_values_and_points_at_its_kind(): void
    {
        $white = FarmColor::where('name', 'bílá')->firstOrFail();
        $kind = $white->material;
        $kind->update(['nozzle_temp' => 215, 'nozzle_temp_first' => 220, 'bed_temp' => 60, 'price_per_gram' => 1.5, 'notes' => 'suché skladování']);
        $p = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $row = FarmPrinterMaterial::firstOrCreate(['farm_printer_id' => $p->id, 'farm_material_id' => $kind->id, 'farm_color_id' => null]);

        $html = (string) $this->actingAs($this->admin)->get(route('admin.farm.colors.show', $white))->assertOk()->getContent();
        $this->assertStringContainsString(e($white->fullLabel()), $html);
        // where it sits, with the way back to that printer
        $this->assertStringContainsString(route('admin.farm.printers.show', $p).'#slots', $html);
        // the kind's values are read here and edited at the kind
        $this->assertStringContainsString('href="'.route('admin.farm.materials').'#kind-'.$kind->id.'" class="text-sm font-semibold underline" data-kind-link', $html);
        $this->assertStringContainsString('suché skladování', $html);
        $this->assertStringContainsString('215 °C', $html);
        $this->assertStringNotContainsString('name="price_per_gram"', $html);
        $this->assertStringNotContainsString('name="filament_profile"', $html);
        // what the machines add: the rows of "Ladění", and a row of its own on the printer that holds it
        $this->assertStringContainsString(route('admin.farm.tuning.edit', $row), $html);
        $this->assertStringContainsString(route('admin.farm.tuning.spool'), $html);
        // the kind's temperatures are what it prints with until it gets its own
        $this->assertStringContainsString(e(__('farm.admin.fd.effective', ['nozzle' => 215, 'first' => 220, 'bed' => 60])), $html);

        // its own values: the temperatures go into print_overrides next to the other settings, the save returns here
        $data = ['back' => 'filament', 'farm_material_id' => $kind->id, 'name' => 'bílá', 'name_en' => 'white', 'code' => $white->code, 'hex' => '#fafafa', 'in_stock' => 1, 'enabled' => 1,
            'temps' => ['nozzle_temp' => 225, 'nozzle_temp_first' => '', 'bed_temp' => 65], 'print_overrides' => '{"filament": {"filament_max_volumetric_speed": ["12"]}}', 'test_notes' => 'věž teplot: 225 nejlepší'];
        $this->actingAs($this->admin)->post(route('admin.farm.colors.update', $white), $data)->assertRedirect(route('admin.farm.colors.show', $white))->assertSessionHas('status');
        $white->refresh();
        $this->assertSame(['filament' => ['filament_max_volumetric_speed' => ['12']], 'nozzle_temp' => 225, 'bed_temp' => 65], $white->print_overrides);
        $this->assertSame(['#fafafa', 'white', 'věž teplot: 225 nejlepší', true], [$white->hex, $white->name_en, $white->test_notes, $white->enabled]);
        $this->assertSame(['nozzle' => 225, 'nozzle_first' => 220, 'bed' => 65], $white->temps());
        $html = (string) $this->actingAs($this->admin)->get(route('admin.farm.colors.show', $white))->assertOk()->getContent();
        $this->assertStringContainsString('name="temps[nozzle_temp]" min="150" max="350" value="225"', $html);
        $this->assertStringContainsString(e(__('farm.admin.fd.effective', ['nozzle' => 225, 'first' => 220, 'bed' => 65])), $html);

        // emptied fields give the temperatures back to the kind; nothing else of the kind was touched
        $this->actingAs($this->admin)->post(route('admin.farm.colors.update', $white), ['temps' => ['nozzle_temp' => '', 'nozzle_temp_first' => '', 'bed_temp' => ''], 'print_overrides' => ''] + $data)
            ->assertRedirect(route('admin.farm.colors.show', $white));
        $this->assertNull($white->fresh()->print_overrides);
        $this->assertSame([215, 1.5], [$kind->fresh()->nozzle_temp, $kind->fresh()->price_per_gram]);
        // a temperature out of range is refused
        $this->actingAs($this->admin)->post(route('admin.farm.colors.update', $white), ['temps' => ['nozzle_temp' => 900]] + $data)->assertSessionHasErrors('temps.nozzle_temp');
        // the row in "Materiály a barvy" saves as it always did (no temps field, back to that page)
        $this->actingAs($this->admin)->post(route('admin.farm.colors.update', $white), ['farm_material_id' => $kind->id, 'name' => 'bílá', 'hex' => '#ffffff', 'enabled' => 1, 'print_overrides' => '{"nozzle_temp": 230}'])
            ->assertRedirect(route('admin.farm.materials').'#kind-'.$kind->id);
        $this->assertSame(['nozzle_temp' => 230], $white->fresh()->print_overrides);

        // administrators only: neither the page nor the save
        $this->actingAs($this->user)->get(route('admin.farm.colors.show', $white))->assertRedirect();
        $this->get(route('admin.farm.colors.show', $white))->assertRedirect();
        $this->actingAs($this->user)->post(route('admin.farm.colors.update', $white), ['temps' => ['nozzle_temp' => 200]] + $data)->assertRedirect();
        $this->assertSame(['nozzle_temp' => 230], $white->fresh()->print_overrides);
        $this->actingAs($this->admin)->get('/admin/farm/colors/999999')->assertNotFound();
    }

    public function test_farms_are_places_with_an_address_and_printers_are_assigned_to_them(): void
    {
        // the migration seeds the three farms, street and postcode left for the admin
        $this->assertSame(['U' => 'Útušice', 'B' => 'Plzeň (Bory)', 'S' => 'Stod'], Farm::orderBy('sort')->pluck('city', 'code')->all());
        $this->assertNull(Farm::where('code', 'S')->value('street'));

        $page = $this->actingAs($this->admin)->get(route('admin.farm.farms'))->assertOk();
        $page->assertSee('Farma S · Stod')->assertSee(__('farm.admin.farms.new'));
        $this->actingAs($this->user)->get(route('admin.farm.farms'))->assertRedirect();

        // the admin fills in an address, adds a farm
        $s = Farm::where('code', 'S')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.farm.farms.update', $s), ['code' => 's', 'name' => 'Farma S', 'street' => 'Nádražní 1', 'city' => 'Stod', 'zip' => '333 01', 'country' => 'cz', 'sort' => 30, 'enabled' => 1, 'note' => 'jedna Kobra S1'])
            ->assertRedirect(route('admin.farm.farms'));
        $s->refresh();
        $this->assertSame(['S', 'Nádražní 1', 'CZ', 'Nádražní 1, 333 01 Stod'], [$s->code, $s->street, $s->country, $s->addressLine()]);
        $this->actingAs($this->admin)->post(route('admin.farm.farms.create'), ['code' => 'K', 'name' => 'Farma K', 'city' => 'Klatovy', 'enabled' => 1])->assertRedirect(route('admin.farm.farms'));
        $this->assertSame(100, Farm::where('code', 'K')->firstOrFail()->sort);
        $this->actingAs($this->admin)->post(route('admin.farm.farms.create'), ['code' => 'U', 'name' => 'Druhá U'])->assertSessionHasErrors('code');
        $this->actingAs($this->user)->post(route('admin.farm.farms.update', $s), ['code' => 'S', 'name' => 'Cizí'])->assertRedirect();
        $this->assertSame('Farma S', $s->fresh()->name);

        // a printer gets its farm in its settings; the form offers the farms
        $p = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $this->actingAs($this->admin)->get(route('admin.farm.printers.edit', $p))->assertOk()->assertSee('name="farm_id"', false)->assertSee('Farma S · Stod');
        $form = ['name' => $p->name, 'model' => $p->model, 'key' => $p->key, 'mode' => 'manual', 'enabled' => 1, 'bed_x' => 250, 'bed_y' => 250, 'bed_z' => 250, 'nozzle_mm' => 0.4,
            'machine_profile' => 'machine.json', 'process_profiles' => json_encode($p->process_profiles), 'time_factor' => 1.07, 'weight_factor' => 1];
        $this->actingAs($this->admin)->post(route('admin.farm.printers.update', $p), $form + ['farm_id' => $s->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($s->id, $p->fresh()->farm_id);
        $this->assertSame('S', $p->fresh()->farm->code);
        // a form without the field (an older page, another caller) leaves the farm alone; an empty choice takes it away
        $this->actingAs($this->admin)->post(route('admin.farm.printers.update', $p), $form)->assertSessionHasNoErrors();
        $this->assertSame($s->id, $p->fresh()->farm_id);
        $this->actingAs($this->admin)->post(route('admin.farm.printers.update', $p), $form + ['farm_id' => ''])->assertSessionHasNoErrors();
        $this->assertNull($p->fresh()->farm_id);
    }

    public function test_the_migration_assigns_printers_by_the_agent_that_runs_them_and_never_by_their_code(): void
    {
        $farmB = FarmAgent::create(['name' => 'Farma B', 'token_hash' => hash('sha256', 'b')]);
        $other = FarmAgent::create(['name' => 'Agent Stod', 'token_hash' => hash('sha256', 's')]);
        // code U3, but run by the agent of farm B: it stands at farm B
        $onB = $this->printer('kobra-s1-01', ['farm_agent_id' => $farmB->id, 'code' => 'U3']);
        $onU = $this->printer('kobra-3-max-01', ['farm_agent_id' => $this->agent->id, 'code' => 'B7']);
        $unknown = $this->printer('kobra-s1-02', ['farm_agent_id' => $other->id]);
        $manual = FarmPrinter::create(['name' => 'Ruční', 'model' => 'X', 'key' => 'manual-01', 'mode' => FarmPrinter::MODE_MANUAL, 'bed_x' => 200, 'bed_y' => 200, 'bed_z' => 200,
            'nozzle_mm' => 0.4, 'machine_profile' => 'machine.json', 'process_profiles' => ['standard' => 'process_standard.json'], 'time_factor' => 1, 'weight_factor' => 1, 'enabled' => true]);

        $migration = require database_path('migrations/2026_11_12_100000_create_farms.php');
        $migration->down();
        $migration->up();

        $this->assertSame('B', $onB->fresh()->farm?->code);
        $this->assertSame('U', $onU->fresh()->farm?->code);
        $this->assertNull($unknown->fresh()->farm_id);
        $this->assertNull($manual->fresh()->farm_id);
        $this->assertSame(3, Farm::count());
    }
}
