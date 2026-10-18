<?php

namespace Tests\Feature;

use App\Models\Calculation;
use App\Models\FarmColor;
use App\Models\FarmMaterial;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\User;
use Database\Seeders\FarmSeeder;
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
        $this->seed(FarmSeeder::class);
        $user = User::factory()->create();
        $path = sys_get_temp_dir().'/mp_old_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);
        $uuid = $this->actingAs($user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'cube.stl', null, null, true)])->json('file.uuid');
        $token = $this->actingAs($user)->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA', 'quality' => 'standard', 'infill' => 15])->json('calculation.token');
        // pretend it was computed with printers' lists before the switch was flipped
        $calc = Calculation::where('token', $token)->firstOrFail();
        $old = [['profile' => 'p39', 'label' => 'Print Fast', 'total' => 999, 'lead_time_days' => 3, 'unit' => ['material' => 1, 'time' => 1, 'royalty' => 0], 'setup' => 0, 'quantity' => 1, 'printer_profile_id' => 39]];
        $calc->forceFill(['prices' => $old, 'rough' => ($calc->rough ?? []) + ['prices' => $old], 'pricing_context' => ['printer_profile_ids' => [39]]])->save();

        $r = $this->get('/c/'.$token)->assertOk();
        $this->assertStringNotContainsString('Print Fast', $r->getContent());
        $this->assertSame('farm', $this->getJson('/api/calculations/'.$token)->json('calculation.prices.0.profile'));
        // (the icon sprite of the layout is path data full of digits: it is not what the page says)
        $account = (string) $this->actingAs($user)->get('/account')->assertOk()->getContent();
        $this->assertStringNotContainsString('999', (string) preg_replace('/<svg[^>]*focusable="false">.*?<\/svg>/s', '', $account));
    }

    /**
     * The instant estimate in the browser and the exact price count with the same kind: the farm's list comes per
     * material, and a material the farm has no kind of is priced in the cheapest kind weighed as that material – so the
     * lists of PLA and ASA differ in the price per gram by the two densities alone, until an ASA kind is loaded.
     */
    public function test_the_instant_estimate_gets_a_farm_list_per_material_that_differs_only_by_kind_and_density(): void
    {
        $this->seed(FarmSeeder::class);   // one spool loaded: white PLA+ (density 1.24)
        $lists = collect($this->get('/')->assertOk()->viewData('config')['orientation_profiles'])->keyBy('material');
        $pla = $lists['PLA'];
        $asa = $lists['ASA'];
        $this->assertSame(__('farm.price_label', ['material' => 'PLA+']), $pla['label']);
        $this->assertSame(__('farm.price_label_cheapest', ['material' => 'PLA+']), $asa['label']);
        $this->assertEqualsWithDelta($pla['price_per_gram'] * 1.24 / 1.07, $asa['price_per_gram'], 0.0001, 'ASA grams weighed as PLA+');
        foreach (['key', 'hourly_rate', 'setup_fee', 'margin_pct', 'min_price', 'lead_time_days', 'time_factor'] as $same) {
            $this->assertSame($pla[$same], $asa[$same], $same);
        }
        $this->assertEqualsWithDelta($pla['price_per_gram'] * 1.24 / 1.27, $lists['PETG']['price_per_gram'], 0.0001, 'PETG likewise, in PLA+');

        // the farm loads ASA: its list prices ASA at the ASA kind's own price per gram and says so
        $kind = FarmMaterial::where('code', 'ASA')->firstOrFail();
        $kind->update(['enabled' => true]);
        $black = FarmColor::create(['farm_material_id' => $kind->id, 'code' => 'ASA_black', 'name' => 'černá', 'hex' => '#111111', 'enabled' => true, 'in_stock' => true]);
        FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail()->slots()->where('slot', 0)->firstOrFail()->update(['farm_color_id' => $black->id, 'remaining_g' => 800, 'enabled' => true]);
        $loaded = collect($this->get('/')->viewData('config')['orientation_profiles'])->keyBy('material');
        $this->assertSame(__('farm.price_label', ['material' => 'ASA']), $loaded['ASA']['label']);
        $this->assertEqualsWithDelta((float) $kind->price_per_gram, $loaded['ASA']['price_per_gram'], 0.0001);
        $this->assertSame($pla['price_per_gram'], $loaded['PLA']['price_per_gram'], 'PLA stays PLA+');
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
        $this->seed(FarmSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user)->get('/farm')->assertOk();
        config(['farm.public' => true]);
        $r = $this->get('/')->assertOk()->assertSee('id="cta-farm"', false);
        // one farm list per FDM material of the calculator (resin has none), every one the farm's
        $profiles = $r->viewData('config')['orientation_profiles'];
        $this->assertSame(['PLA', 'PETG', 'ASA', 'TPU', 'PA'], array_column($profiles, 'material'));
        $this->assertSame(['farm'], array_values(array_unique(array_column($profiles, 'key'))));
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
        $order = FarmOrder::where('token', basename($url))->firstOrFail();
        $this->assertEqualsWithDelta($calcPrice, $order->price_total, 0.001, 'calculator and farm agree');
    }
}
