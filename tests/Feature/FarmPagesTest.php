<?php

namespace Tests\Feature;

use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\Wallet;
use App\Models\FarmAgent;
use App\Models\FarmColor;
use App\Models\FarmCommand;
use App\Models\FarmMaterial;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrintJob;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/** Every farm page renders, in every language, and the admin desk is closed to everybody but admins. */
class FarmPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $admin;

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
    }

    private function order(): FarmOrder
    {
        $path = sys_get_temp_dir().'/mp_farm_page_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);
        $uuid = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->json('file.uuid');
        $url = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $uuid])->json('url');

        return FarmOrder::where('token', basename($url))->firstOrFail();
    }

    public function test_customer_pages_render_in_all_languages(): void
    {
        $order = $this->order();
        foreach (['cs', 'en', 'es'] as $lang) {
            foreach (['/farm', '/farm/orders', "/farm/orders/{$order->token}", '/account/credit', '/farm/terms', '/account'] as $url) {
                $r = $this->actingAs($this->user)->get($url.'?lang='.$lang);
                $r->assertOk();
                $this->assertDoesNotMatchRegularExpression('/\bfarm\.[a-z_]+\.[a-z_.]+/', strip_tags(preg_replace('#<script.*?</script>#s', '', $r->getContent())), "untranslated key on {$url} ({$lang})");
            }
        }
        $this->actingAs($this->user)->get("/farm/orders/{$order->token}/model.stl")->assertOk();
    }

    public function test_calculator_offers_the_farm_and_the_start_page_takes_a_shared_calculation(): void
    {
        app(FarmSettings::class)->set('farm_public', true);
        config(['farm.public' => true]);
        $this->get('/')->assertOk()->assertSee('cta-farm');

        $order = $this->order();
        $calc = $this->actingAs($this->user)->postJson('/api/calculations', ['file' => $order->modelFile->uuid, 'material' => 'PLA', 'quality' => 'fine', 'infill' => 15])->json('calculation.token');
        $this->actingAs($this->user)->get('/farm?calc='.$calc)->assertOk()->assertSee('part.stl')->assertSee('value="fine" class="sr-only" checked', false);
    }

    public function test_until_the_farm_is_public_only_admins_see_the_button(): void
    {
        config(['farm.public' => false]);
        $this->get('/')->assertOk()->assertDontSee('id="cta-farm"', false);
        $this->actingAs($this->user)->get('/')->assertOk()->assertDontSee('id="cta-farm"', false);
        $this->user->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($this->user->fresh())->get('/')->assertOk()->assertSee('id="cta-farm"', false);
    }

    public function test_admin_pages_render_and_are_closed_to_others(): void
    {
        $order = $this->order();
        $printer = FarmPrinter::firstOrFail();
        $pages = ['/admin/farm', '/admin/farm/orders', "/admin/farm/orders/{$order->token}", '/admin/farm/printers', '/admin/farm/printers/new',
            "/admin/farm/printers/{$printer->id}", '/admin/farm/materials', '/admin/farm/settings', '/admin/farm/agents', '/admin/farm/credit?email='.$this->user->email];

        foreach ($pages as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
            $this->actingAs($this->user)->get($url)->assertRedirect();
        }
        $this->actingAs($this->admin)->get("/admin/farm/orders/{$order->token}/print.gcode")->assertOk();
        $this->actingAs($this->user)->get("/admin/farm/orders/{$order->token}/print.gcode")->assertRedirect();
    }

    public function test_admin_adds_petg_a_colour_a_second_printer_and_changes_prices_without_code(): void
    {
        $this->actingAs($this->admin)->post('/admin/farm/materials/new', ['code' => 'petg', 'finish' => 'silk', 'name' => 'PETG', 'filament_profile' => 'filament_petg.json', 'density' => 1.27, 'nozzle_temp' => 240, 'bed_temp' => 75, 'price_per_gram' => 1.5, 'enabled' => 1])->assertRedirect();
        $petg = FarmMaterial::where('code', 'PETG')->where('finish', 'silk')->firstOrFail();
        $this->assertSame(['240'], $petg->sliceOverrides()['nozzle_temperature']);

        $this->actingAs($this->admin)->post('/admin/farm/colors/new', ['farm_material_id' => $petg->id, 'name' => 'Transparent', 'hex' => '#DDEEFF', 'enabled' => 1, 'photo' => UploadedFile::fake()->image('print.jpg', 400, 400)])->assertRedirect();
        $color = FarmColor::where('name', 'Transparent')->firstOrFail();
        Storage::disk('public')->assertExists($color->photo_path);

        [$agent] = FarmAgent::issue('pi');
        $this->actingAs($this->admin)->post('/admin/farm/printers/new', [
            'name' => 'Kobra S1 #3', 'model' => 'Anycubic Kobra S1 Combo', 'key' => 'kobra-s1-03', 'mode' => 'agent', 'farm_agent_id' => $agent->id, 'enabled' => 1,
            'bed_x' => 250, 'bed_y' => 250, 'bed_z' => 250, 'nozzle_mm' => 0.4, 'machine_profile' => 'machine.json',
            'process_profiles' => '{"draft":"process_draft.json","standard":"process_standard.json","fine":"process_fine.json"}',
            'machine_overrides' => '{"printable_height":"250"}', 'process_overrides' => '', 'time_factor' => 1.15, 'weight_factor' => 1.02,
            'slots' => [['color' => $color->id, 'remaining_g' => 800, 'enabled' => 1], ['color' => '', 'remaining_g' => 0]],
        ])->assertRedirect('/admin/farm/printers');
        $second = FarmPrinter::where('key', 'kobra-s1-03')->firstOrFail();
        $this->assertSame(1.15, $second->time_factor);
        $this->assertSame(['printable_height' => '250'], $second->machine_overrides);
        $this->assertTrue($second->slots()->where('slot', 0)->firstOrFail()->enabled);
        $this->assertFalse($second->slots()->where('slot', 1)->firstOrFail()->enabled, 'an empty slot is never offered');

        // agent mode without an agent is refused
        $this->actingAs($this->admin)->post("/admin/farm/printers/{$second->id}", ['mode' => 'agent', 'farm_agent_id' => ''] + $second->only(['name', 'model', 'key', 'bed_x', 'bed_y', 'bed_z', 'nozzle_mm', 'machine_profile', 'time_factor', 'weight_factor']) + ['process_profiles' => '{}'])->assertSessionHasErrors('farm_agent_id');

        $settings = app(FarmSettings::class)->all();
        $this->actingAs($this->admin)->post('/admin/farm/settings', [
            'hourly_rate' => 50, 'vat_percent' => 0, 'rounding' => 5, 'topup_amounts' => '300, 600', 'require_approval' => 1, 'delivery_modes' => ['pickup'],
            'qualities' => json_encode($settings['qualities']), 'strengths' => json_encode(['low' => ['infill' => 8], 'standard' => ['infill' => 15], 'high' => ['infill' => 40]]),
        ] + array_intersect_key($settings, array_flip(['max_upload_mb', 'daily_slices_per_user', 'min_model_mm', 'bed_margin_mm', 'fixed_fee', 'min_price', 'shipping_price', 'topup_min', 'topup_max', 'generation_price', 'changeover_minutes', 'offline_after_seconds', 'terms_version', 'admin_email'])))->assertRedirect()->assertSessionHasNoErrors();

        $this->app->forgetScopedInstances();
        $fresh = app(FarmSettings::class);
        $this->assertSame(50, $fresh->get('hourly_rate') + 0);
        $this->assertSame([300, 600], $fresh->get('topup_amounts'));
        $this->assertTrue($fresh->get('require_approval'));
        $this->assertSame(40, $fresh->infillFor('high'));
        $this->assertSame(['pickup'], $fresh->get('delivery_modes'));
    }

    public function test_manual_printer_operator_walks_an_order_to_handover_and_records_calibration(): void
    {
        $order = $this->order();
        app(Wallet::class)->adjust($this->user, 1000, 'test', $this->admin->id);
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->json();
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $state['colors'][0]['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $state['colors'][0]['total']])->assertOk();

        foreach (['printing', 'done'] as $to) {
            $this->actingAs($this->admin)->post("/admin/farm/orders/{$order->token}/status", ['to' => $to])->assertRedirect()->assertSessionMissing('error');
        }
        $this->actingAs($this->admin)->post("/admin/farm/orders/{$order->token}/actuals", ['actual_minutes' => 30, 'actual_grams' => 9.5])->assertRedirect();
        $this->actingAs($this->admin)->post("/admin/farm/orders/{$order->token}/status", ['to' => 'handed_over'])->assertRedirect();

        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_HANDED_OVER, $order->status);
        $this->assertSame('admin', $order->actual_source);
        $this->assertDatabaseHas('credit_transactions', ['farm_order_id' => $order->id, 'type' => 'capture']);
        // a jump that the state machine does not know is refused
        $this->actingAs($this->admin)->post("/admin/farm/orders/{$order->token}/status", ['to' => 'queued'])->assertSessionHas('error');

        $this->actingAs($this->admin)->get('/admin/farm/printers')->assertOk()->assertSee('1 ');
    }

    public function test_admin_moves_a_colour_to_another_kind_and_replaces_its_photo(): void
    {
        $printer = FarmPrinter::firstOrFail();
        $color = $printer->slots()->where('slot', 2)->firstOrFail()->color;   // white PLA+
        $silk = FarmMaterial::where('code', 'PLA')->where('finish', 'silk')->firstOrFail();
        $this->actingAs($this->admin)->post("/admin/farm/colors/{$color->id}", ['farm_material_id' => $silk->id, 'name' => $color->name, 'hex' => $color->hex, 'enabled' => 1, 'in_stock' => 1, 'photo' => UploadedFile::fake()->image('a.jpg', 300, 300)])->assertRedirect();
        $color->refresh();
        $this->assertSame($silk->id, $color->farm_material_id, 'the spool now belongs to PLA Silk');
        $first = $color->photo_path;
        Storage::disk('public')->assertExists($first);

        // a new photo replaces the old file, the checkbox removes it
        $this->actingAs($this->admin)->post("/admin/farm/colors/{$color->id}", ['farm_material_id' => $silk->id, 'name' => $color->name, 'hex' => $color->hex, 'enabled' => 1, 'in_stock' => 1, 'photo' => UploadedFile::fake()->image('b.jpg', 300, 300)])->assertRedirect();
        $color->refresh();
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($color->photo_path);
        $this->actingAs($this->admin)->post("/admin/farm/colors/{$color->id}", ['farm_material_id' => $silk->id, 'name' => $color->name, 'hex' => $color->hex, 'enabled' => 1, 'in_stock' => 1, 'remove_photo' => 1])->assertRedirect();
        $this->assertNull($color->fresh()->photo_path);

        // the customer's colour list follows the kind, and every page carries the lightbox
        $order = $this->order();
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->json();
        $this->assertSame('PLA Silk', collect($state['colors'])->firstWhere('name', $color->name)['kind']);
        $this->actingAs($this->user)->get("/farm/orders/{$order->token}")->assertOk()->assertSee('id="mp-lightbox"', false);
        $this->actingAs($this->admin)->get('/admin/farm/materials')->assertOk()->assertSee('přeřazení');
    }

    public function test_the_plate_stays_occupied_while_a_print_runs_and_the_dashboard_buttons_answer_json(): void
    {
        $order = $this->order();
        $agent = FarmAgent::create(['name' => 'Agent 1', 'token_hash' => hash('sha256', 'secret')]);
        $printer = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $printer->update([
            'mode' => FarmPrinter::MODE_AGENT, 'farm_agent_id' => $agent->id, 'state' => FarmPrinter::STATE_PRINTING, 'last_seen_at' => now(), 'bed_clear' => false,
            'telemetry' => ['extruder' => 215.3, 'bed' => 55.0, 'dryer_state' => 'drying', 'dryer_temp' => 50.0, 'dryer_target' => 50.0, 'dryer_remain_min' => 173, 'dryer_rh' => 0],
        ]);
        $job = FarmPrintJob::create(['farm_order_id' => $order->id, 'farm_printer_id' => $printer->id, 'slot' => 0, 'status' => FarmPrintJob::STATUS_PRINTING, 'progress' => 40]);

        // the card: "clear plate" is greyed out, the ACE dryer line shows the live temperature and the time left
        $page = $this->actingAs($this->admin)->get('/admin/farm?lang=cs')->assertOk();
        $page->assertSee(__('farm.admin.bed_locked_hint'));
        $page->assertSee('name="clear" value="1" class="btn-primary text-sm" disabled', false);
        $page->assertSee('suší 50 / 50 °C, zbývá 2 h 53 min');
        $page->assertDontSee('dryer_remain_min');

        // the request itself refuses too (the button can be re-enabled from the dev tools), and answers JSON for the toast
        $this->actingAs($this->admin)->postJson(route('admin.farm.printers.bed', $printer), ['clear' => 1])
            ->assertStatus(422)->assertJson(['ok' => false, 'message' => __('farm.admin.bed_locked')]);
        $this->assertFalse($printer->fresh()->bed_clear);

        // the light does not care about the print
        $this->actingAs($this->admin)->postJson(route('admin.farm.printers.command', $printer), ['type' => 'light_on'])->assertOk()->assertJson(['ok' => true]);
        $this->assertSame(1, FarmCommand::where('type', FarmCommand::TYPE_LIGHT)->count());

        $job->update(['status' => FarmPrintJob::STATUS_DONE]);
        $printer->update(['state' => FarmPrinter::STATE_IDLE]);
        $this->actingAs($this->admin)->get('/admin/farm?lang=cs')->assertOk()->assertDontSee('class="btn-primary text-sm" disabled', false);
        $this->actingAs($this->admin)->postJson(route('admin.farm.printers.bed', $printer), ['clear' => 1])->assertOk()->assertJson(['ok' => true]);
        $this->assertTrue($printer->fresh()->bed_clear);

        // without JavaScript the old redirect + flash message still works
        $this->actingAs($this->admin)->post(route('admin.farm.printers.bed', $printer), ['clear' => 0])->assertRedirect()->assertSessionHas('status', __('farm.admin.saved'));
        $this->assertFalse($printer->fresh()->bed_clear);
    }
}
