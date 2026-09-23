<?php

namespace Tests\Feature;

use App\Models\ModelFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/** features.marketplace = false: the calculator shows the slicer's facts, no prices, no inquiries, no printer pages. */
class MarketplaceSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        config(['features.marketplace' => false, 'tools.spare.available' => false]);
    }

    public function test_calculator_has_facts_and_no_prices(): void
    {
        $r = $this->get('/');
        $r->assertOk()->assertSee('"marketplace":false', false)->assertDontSee('id="cta-make"', false)->assertDontSee('id="inquiry-panel"', false);
        // no farm printer seeded here: no price list at all, the calculator shows the slicer's facts
        $this->assertSame([], $r->viewData('config')['orientation_profiles'] === config('pricing.orientation_profiles') ? [] : ['unexpected']);
        $r->assertSee(__('calc.facts.layers'))->assertDontSee(route('register', ['role' => 'printer']), false);

        $path = sys_get_temp_dir().'/mp_switch_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);
        $uuid = $this->postJson('/api/uploads', ['file' => new UploadedFile($path, 'cube.stl', null, null, true)])->json('file.uuid');
        $calc = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA', 'quality' => 'standard', 'infill' => 15])->assertCreated();
        // the slicer's facts are there for the facts card
        $this->assertSame(['normal', 'silent', 'sport'], array_keys($calc->json('calculation.slicer.minutes_by_mode')));
        $this->assertSame(100, $calc->json('calculation.slicer.layers'));
        $this->assertGreaterThan(0, $calc->json('calculation.slicer.meters'));
    }

    public function test_a_calculation_stored_with_printer_prices_shows_the_farm_price_now(): void
    {
        $this->seed(\Database\Seeders\FarmSeeder::class);
        $user = User::factory()->create();
        $path = sys_get_temp_dir().'/mp_old_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);
        $uuid = $this->actingAs($user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'cube.stl', null, null, true)])->json('file.uuid');
        $token = $this->actingAs($user)->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA', 'quality' => 'standard', 'infill' => 15])->json('calculation.token');
        // pretend it was computed with printers' lists before the switch was flipped
        $calc = \App\Models\Calculation::where('token', $token)->firstOrFail();
        $old = [['profile' => 'p39', 'label' => 'Print Fast', 'total' => 999, 'lead_time_days' => 3, 'unit' => ['material' => 1, 'time' => 1, 'royalty' => 0], 'setup' => 0, 'quantity' => 1, 'printer_profile_id' => 39]];
        $calc->forceFill(['prices' => $old, 'rough' => ($calc->rough ?? []) + ['prices' => $old], 'pricing_context' => ['printer_profile_ids' => [39]]])->save();

        $r = $this->get('/c/'.$token)->assertOk();
        $this->assertStringNotContainsString('Print Fast', $r->getContent());
        $this->assertSame('farm', $this->getJson('/api/calculations/'.$token)->json('calculation.prices.0.profile'));
        $this->actingAs($user)->get('/account')->assertOk()->assertDontSee('999');
    }

    public function test_marketplace_routes_answer_404_and_the_printer_role_is_hidden(): void
    {
        $this->postJson('/api/inquiries', [])->assertNotFound();
        $this->postJson('/api/spare-parts', [])->assertNotFound();
        $this->get('/tools/spare-part')->assertNotFound();
        $this->get('/printers/id/1')->assertNotFound();
        $this->get('/i/sometoken')->assertNotFound();
        $this->get('/q/sometoken')->assertNotFound();

        $user = User::factory()->create();
        $user->setRole(User::ROLE_PRINTER, true);
        $this->actingAs($user)->get('/printer')->assertNotFound();
        $this->actingAs($user)->get('/account')->assertOk()->assertDontSee(__('account.role.printer'));
        $this->actingAs($user)->get('/tools')->assertOk()->assertDontSee(route('tools.spare'));
    }

    public function test_farm_still_works_without_the_marketplace_and_is_the_only_price_list(): void
    {
        $this->seed(\Database\Seeders\FarmSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user)->get('/farm')->assertOk();
        $r = $this->get('/')->assertOk()->assertSee('id="cta-farm"', false);
        $profiles = $r->viewData('config')['orientation_profiles'];
        $this->assertCount(1, $profiles);
        $this->assertSame('farm', $profiles[0]['key']);
        $this->assertSame(1.07, $profiles[0]['time_factor'], 'the Kobra calibration is in the calculator price');

        // a calculation prices with the farm list only, whoever looks, and the number equals what /farm charges
        $path = sys_get_temp_dir().'/mp_switch_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);
        $uuid = $this->actingAs($user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'cube.stl', null, null, true)])->json('file.uuid');
        $calc = $this->actingAs($user)->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA', 'quality' => 'standard', 'infill' => 15])->assertCreated();
        $this->assertCount(1, $calc->json('calculation.prices'));
        $this->assertSame('farm', $calc->json('calculation.prices.0.profile'));
        $calcPrice = $calc->json('calculation.prices.0.total');

        $url = $this->actingAs($user)->postJson('/farm/orders', ['file' => $uuid])->json('url');
        $order = \App\Models\FarmOrder::where('token', basename($url))->firstOrFail();
        $this->assertEqualsWithDelta($calcPrice, $order->price_total, 0.001, 'calculator and farm agree');
    }
}
