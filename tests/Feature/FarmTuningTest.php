<?php

namespace Tests\Feature;

use App\Domain\Farm\GcodeSlot;
use App\Domain\Farm\PrintProfile;
use App\Domain\Farm\ProfileLibrary;
use App\Domain\Farm\TestPrintService;
use App\Domain\Farm\TowerGcode;
use App\Domain\Farm\Wallet;
use App\Mail\FarmOrderStatus;
use App\Models\FarmColor;
use App\Models\FarmMaterial;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrinterMaterial;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * Filament tuning per machine: every printer × kind gets a row with the best known start, the row's settings reach
 * the slice, a spool pays for a re-slice when the machine's profile differs, test prints run without a customer.
 */
class FarmTuningTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $this->admin = User::factory()->create(['email' => 'admin@example.com']);
        $this->admin->setRole(User::ROLE_ADMIN, true);
    }

    public function test_seeding_gives_every_enabled_printer_and_kind_a_row_with_library_values(): void
    {
        $s1 = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $petg = FarmMaterial::where('code', 'PETG')->where('finish', 'solid')->firstOrFail();
        $enabledKinds = FarmMaterial::where('enabled', true)->count();
        $this->assertSame($enabledKinds, FarmPrinterMaterial::where('farm_printer_id', $s1->id)->whereNull('farm_color_id')->count());

        $row = FarmPrinterMaterial::where('farm_printer_id', $s1->id)->where('farm_material_id', $petg->id)->firstOrFail();
        $this->assertSame('library', $row->source);
        $this->assertSame(FarmPrinterMaterial::STATUS_UNTESTED, $row->status);
        $this->assertSame(['60'], $row->overrides['filament']['fan_max_speed']);
        $this->assertSame('30', $row->overrides['process']['bridge_speed']);
        // the kind states its temperatures itself: the row does not repeat them
        $this->assertArrayNotHasKey('nozzle_temp', $row->overrides);

        // the Kobra 3 Max inherits the S1 block and adds its own speeds
        $max = FarmPrinter::where('key', 'kobra-3-max-01')->firstOrFail();
        $rowMax = FarmPrinterMaterial::where('farm_printer_id', $max->id)->where('farm_material_id', $petg->id)->firstOrFail();
        $this->assertSame(['60'], $rowMax->overrides['filament']['fan_max_speed']);
        $pla = FarmMaterial::where('code', 'PLA')->where('finish', 'solid')->firstOrFail();
        $plaMax = FarmPrinterMaterial::where('farm_printer_id', $max->id)->where('farm_material_id', $pla->id)->firstOrFail();
        $this->assertSame('120', $plaMax->overrides['process']['outer_wall_speed']);

        // running it again changes nothing
        $this->assertSame(0, app(ProfileLibrary::class)->sync());
    }

    public function test_a_new_kind_inherits_a_tuned_row_of_the_same_printer_model_before_the_library(): void
    {
        $s1 = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $petg = FarmMaterial::where('code', 'PETG')->where('finish', 'solid')->firstOrFail();
        FarmPrinterMaterial::where('farm_printer_id', $s1->id)->where('farm_material_id', $petg->id)->firstOrFail()
            ->revise(['bed_temp' => 80, 'filament' => ['fan_max_speed' => ['45']]], 'test', 'tower', FarmPrinterMaterial::STATUS_TUNED);

        // a second S1 joins the farm
        $this->actingAs($this->admin)->post('/admin/farm/printers/new', [
            'name' => 'Kobra S1 #2', 'model' => $s1->model, 'key' => 'kobra-s1-02', 'mode' => 'manual', 'enabled' => 1,
            'bed_x' => 250, 'bed_y' => 250, 'bed_z' => 250, 'nozzle_mm' => 0.4, 'machine_profile' => 'machine.json',
            'process_profiles' => json_encode($s1->process_profiles), 'time_factor' => 1, 'weight_factor' => 1,
        ])->assertRedirect('/admin/farm/printers');
        $second = FarmPrinter::where('key', 'kobra-s1-02')->firstOrFail();
        $row = FarmPrinterMaterial::where('farm_printer_id', $second->id)->where('farm_material_id', $petg->id)->firstOrFail();
        $this->assertSame('inherited', $row->source);
        $this->assertSame(80, $row->overrides['bed_temp']);
        $this->assertSame(['45'], $row->overrides['filament']['fan_max_speed']);
    }

    public function test_the_machine_row_and_the_spool_reach_the_slice_and_the_gcode_temperatures(): void
    {
        $s1 = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $white = $s1->slots()->where('slot', 2)->firstOrFail()->color;
        $kindRow = FarmPrinterMaterial::where('farm_printer_id', $s1->id)->where('farm_material_id', $white->farm_material_id)->whereNull('farm_color_id')->firstOrFail();
        $kindRow->revise(['nozzle_temp' => 222, 'process' => ['outer_wall_speed' => '90'], 'filament' => ['fan_max_speed' => ['80']]], 'manual');
        $white->update(['print_overrides' => ['bed_temp' => 62, 'filament' => ['fan_max_speed' => ['70']]]]);

        $p = PrintProfile::for($s1, $white->material, $white);
        $this->assertSame(['kind', 'printer_kind', 'spool'], $p->layers);
        $this->assertSame(['70'], $p->filament['fan_max_speed'], 'the spool is more specific than the kind on the machine');
        $this->assertSame('90', $p->process['outer_wall_speed']);
        $this->assertSame(222, $p->temps['nozzle']);
        $this->assertSame(62, $p->temps['bed']);
        $this->assertSame(['222'], $p->filament['nozzle_temperature']);

        // a spool row on this machine is the most specific of all
        FarmPrinterMaterial::create(['farm_printer_id' => $s1->id, 'farm_material_id' => $white->farm_material_id, 'farm_color_id' => $white->id, 'overrides' => ['nozzle_temp' => 218, 'process' => ['outer_wall_speed' => '70']]]);
        $p = PrintProfile::for($s1, $white->material, $white);
        $this->assertSame('70', $p->process['outer_wall_speed']);
        $this->assertSame(218, $p->temps['nozzle']);
        $this->assertSame(62, $p->temps['bed']);

        // …and a customer's order is sliced with it
        $path = sys_get_temp_dir().'/mp_tune_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);
        $user = User::factory()->create();
        $uuid = $this->actingAs($user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated()->json('file.uuid');
        $r = $this->actingAs($user)->postJson('/farm/orders', ['file' => $uuid])->assertCreated();
        $order = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status);
        // before a colour is chosen the order is sliced for the kind on this machine
        $this->assertSame('90', $order->slice_params['overrides']['process']['outer_wall_speed']);
        $this->assertSame(['80'], $order->slice_params['overrides']['filament']['fan_max_speed']);
        $this->assertNotEmpty($order->slice_params['profile_fingerprint']);
        $this->assertSame(['kind', 'printer_kind'], $order->slice_params['profile_layers']);

        // paying for the white spool: its settings differ from what was sliced → sliced again (sync queue), same price
        app(Wallet::class)->adjust($user, 1000, 'test', $this->admin->id);
        $state = $this->actingAs($user)->getJson("/farm/orders/{$order->token}/status")->json();
        $offer = collect($state['colors'])->firstWhere('slot', $order->slot?->id ?? $s1->slots()->where('slot', 2)->value('id'));
        $quoted = $order->price_total;
        $this->actingAs($user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $offer['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $offer['total']])->assertOk();
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_QUEUED, $order->status);
        $this->assertEqualsWithDelta($quoted, $order->price_total, 0.001);
        $this->assertSame('70', $order->slice_params['overrides']['process']['outer_wall_speed']);
        $this->assertSame(['70'], $order->slice_params['overrides']['filament']['fan_max_speed']);
        $this->assertSame(['kind', 'printer_kind', 'spool', 'printer_spool'], $order->slice_params['profile_layers']);
        $gcode = GcodeSlot::retarget("G9111 bedTemp=55 extruderTemp=220\nM117\nT0\nM104 S220\nM140 S55\n", 2, PrintProfile::tempsFor($order->fresh()));
        $this->assertStringContainsString('M104 S218', $gcode);
        $this->assertStringContainsString('M140 S62', $gcode);
    }

    public function test_a_tower_writes_one_temperature_per_floor_and_the_slot_rewrite_leaves_them_alone(): void
    {
        $gcode = "M104 S220\n;LAYER_CHANGE\n;Z:0.2\nG1 X1\n;LAYER_CHANGE\n;Z:9.8\nG1 X2\n;LAYER_CHANGE\n;Z:10.2\nG1 X3\n;LAYER_CHANGE\n;Z:20.2\nG1 X4\n;LAYER_CHANGE\n;Z:30.2\n";
        $r = TowerGcode::apply($gcode, 10.0, [225, 220, 215]);
        $this->assertSame(2, $r['floors_set'], 'three floors, two transitions; the third transition would leave the ladder');
        $this->assertStringContainsString(";Z:10.2\nM104 S220 ; matplace tower floor 2", $r['gcode']);
        $this->assertStringContainsString(";Z:20.2\nM104 S215 ; matplace tower floor 3", $r['gcode']);
        $this->assertStringNotContainsString(";Z:30.2\nM104", $r['gcode']);

        $slot = GcodeSlot::retarget($r['gcode'], 1, ['nozzle' => 225, 'nozzle_first' => 230, 'bed' => 60]);
        $this->assertStringContainsString("M104 S225\n", $slot, 'the sliced temperature becomes the bottom floor');
        $this->assertStringContainsString('M104 S220 ; matplace tower floor 2', $slot);
        $this->assertStringContainsString('M104 S215 ; matplace tower floor 3', $slot);
    }

    public function test_a_test_print_is_queued_without_a_customer_and_its_settings_can_be_adopted(): void
    {
        if (! app(TestPrintService::class)->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
        $s1 = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $s1->update(['bed_clear' => true]);
        $slot = $s1->slots()->where('slot', 2)->firstOrFail();
        $row = FarmPrinterMaterial::where('farm_printer_id', $s1->id)->where('farm_material_id', $slot->color->farm_material_id)->whereNull('farm_color_id')->firstOrFail();
        $this->actingAs($this->admin)->get('/admin/farm/tuning')->assertOk()->assertSee($row->label());
        $this->actingAs($this->admin)->get("/admin/farm/tuning/{$row->id}")->assertOk();

        // a wrong slot (another kind) is refused
        $petg = FarmColor::whereHas('material', fn ($q) => $q->where('code', 'PETG'))->firstOrFail();
        $s1->slots()->where('slot', 0)->update(['farm_color_id' => $petg->id, 'remaining_g' => 500, 'enabled' => true]);
        $this->actingAs($this->admin)->post("/admin/farm/tuning/{$row->id}/test", ['slot' => $s1->slots()->where('slot', 0)->value('id'), 'object' => 'quick'])->assertRedirect()->assertSessionHas('error');

        // temperature tower on the right spool, sync queue: built, sliced, queued
        $this->actingAs($this->admin)->post("/admin/farm/tuning/{$row->id}/test", ['slot' => $slot->id, 'object' => 'temp_tower', 'floors' => 5, 'step' => -5, 'nozzle_temp' => 220])->assertRedirect()->assertSessionMissing('error');
        $order = FarmOrder::where('kind', FarmOrder::KIND_TEST)->latest('id')->firstOrFail();
        $this->assertSame(FarmOrder::STATUS_QUEUED, $order->status);
        $this->assertStringStartsWith('T', $order->number);
        $this->assertNull($order->price_total);
        $this->assertSame($this->admin->id, $order->user_id);
        $this->assertSame($row->id, $order->farm_printer_material_id);
        $this->assertSame([230, 225, 220, 215, 210], $order->test_params['temps']);
        $this->assertSame(230, $order->test_params['candidate']['nozzle_temp'], 'the object is sliced at the bottom floor');
        $this->assertSame(['test_candidate'], $order->slice_params['profile_layers']);
        $this->assertSame(FarmPrinterMaterial::STATUS_TESTING, $row->fresh()->status);
        $this->assertSame(['x' => 31.0, 'y' => 30.0, 'z' => 50.0], array_map(fn ($v) => round($v, 1), $order->check['dims']));
        Mail::assertNotQueued(FarmOrderStatus::class);   // no customer to tell; the operator's "send it by hand" alert for a manual printer is fine

        // the operator sees it as a test on the order page and among the row's tests
        $this->actingAs($this->admin)->get("/admin/farm/orders/{$order->token}")->assertOk()->assertSee('Testovací tisk');
        $this->actingAs($this->admin)->get("/admin/farm/tuning/{$row->id}")->assertOk()->assertSee($order->number);
        // it never shows up in the admin's own customer list
        $this->actingAs($this->admin)->get('/farm/orders')->assertOk()->assertDontSee($order->number);

        // the test printed: the operator fills in what it showed, the advisor proposes, the proposal becomes a version
        $order->forceFill(['status' => FarmOrder::STATUS_DONE])->save();
        $this->actingAs($this->admin)->post("/admin/farm/tuning/{$row->id}/evaluate/{$order->token}", ['best_floor' => 3, 'stringing' => 2, 'bridge' => 'sag', 'score' => 3])->assertRedirect();
        $order->refresh();
        $this->assertSame(3, $order->quality_rating);
        $this->assertSame(2, $order->test_params['result']['stringing']);
        $advice = $order->test_params['advice'];
        $this->assertSame(215, $advice['overrides']['nozzle_temp'], 'floor 3 = 220, stringing −5');
        $this->assertSame('40', $advice['overrides']['process']['bridge_speed']);
        $this->actingAs($this->admin)->get("/admin/farm/tuning/{$row->id}")->assertOk()->assertSee('Návrh úprav')->assertSee('bridge_speed');
        $this->actingAs($this->admin)->post("/admin/farm/tuning/{$row->id}/apply/{$order->token}")->assertRedirect();
        $row->refresh();
        $this->assertSame(FarmPrinterMaterial::STATUS_TESTING, $row->status);
        $this->assertSame(2, $row->version);
        $this->assertSame('40', $row->overrides['process']['bridge_speed']);
        $this->assertArrayNotHasKey('nozzle_temp', $row->overrides, '215 is what the PLA+ kind says anyway: the row keeps only what differs');
        $this->assertSame(215, PrintProfile::for($s1, $row->material, $slot->color)->temps['nozzle']);

        // floor 3 (220) was best after all → the row is tuned with it
        $this->actingAs($this->admin)->post("/admin/farm/tuning/{$row->id}/adopt/{$order->token}", ['nozzle_temp' => 220, 'score' => 5, 'note' => 'patro 3'])->assertRedirect();
        $row->refresh();
        $this->assertSame(FarmPrinterMaterial::STATUS_TUNED, $row->status);
        $this->assertSame('test', $row->source);
        $this->assertSame(220, $row->overrides['nozzle_temp']);
        $this->assertSame(5, $row->score);
        $this->assertSame(3, $row->version);
        $this->assertCount(2, $row->history);
    }
}
