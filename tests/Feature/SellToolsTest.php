<?php

namespace Tests\Feature;

use App\Domain\Sell\Cost;
use App\Domain\Sell\Plan;
use App\Domain\Sell\Profit;
use App\Models\Calculation;
use App\Models\ModelFile;
use App\Models\SellPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The selling and planning pages: the arithmetic in PHP (the page's script mirrors it), the pages, the prefills. */
class SellToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_cost_of_a_piece_adds_up(): void
    {
        // the defaults: 40 g of 600 Kč/kg, 2.5 h at 120 W and 6.5 Kč/kWh, a 12 000 Kč printer over 5 000 h, 5 % scrap, 10 min at 250 Kč/h, 40 % margin
        $r = Cost::calculate([]);
        $this->assertEqualsWithDelta(24.0, $r['material'], 0.01);
        $this->assertEqualsWithDelta(1.95, $r['energy'], 0.01);
        $this->assertEqualsWithDelta(6.0, $r['wear'], 0.01);
        $this->assertEqualsWithDelta(31.95 / 0.95 - 31.95, $r['scrap'], 0.01);          // the failed prints, carried by the good ones
        $this->assertEqualsWithDelta(41.67, $r['labour'], 0.01);
        $this->assertEqualsWithDelta(75.30, $r['cost'], 0.01);
        $this->assertEqualsWithDelta(30.12, $r['margin'], 0.01);
        $this->assertEqualsWithDelta(105.42, $r['price'], 0.01);
        $this->assertEqualsWithDelta(12.05, $r['per_hour'], 0.01);
        // no scrap, no labour, no margin: the machine's costs alone; out-of-range input is clamped, nonsense ignored
        $plain = Cost::calculate(['scrap_pct' => 0, 'labour_minutes' => 0, 'margin_pct' => 0, 'other' => 'abc', 'hours' => -5]);
        $this->assertSame(0.0, $plain['scrap']);
        $this->assertSame(0.0, $plain['labour']);
        $this->assertSame($plain['cost'], $plain['price']);
        $this->assertEqualsWithDelta(24.0, $plain['cost'], 0.01);                            // hours clamped to 0: only the filament is left
        $this->assertSame(0.0, $plain['per_hour']);
        $this->assertEquals(90.0, Cost::clean(['scrap_pct' => 500])['scrap_pct']);
    }

    public function test_the_cost_page_renders_and_takes_the_grams_and_hours_of_a_calculation(): void
    {
        $this->get('/tools/cost')->assertOk()->assertSee(__('tools.cost.title'))->assertSee('data-sell="cost"', false)->assertSee('data-param="filament_kg"', false)->assertSee('window.MP_SELL', false)->assertSee(__('sell.cost.next.profit'));
        $this->get('/en/tools/cost')->assertOk()->assertSee('Filament price')->assertSee('Work out the profit of a sale');
        $this->get('/es/tools/cost')->assertOk()->assertSee('Precio del filamento');
        $this->assertStringContainsString('data-filter="sell"', (string) $this->get('/tools')->assertOk()->getContent());

        $file = ModelFile::create(['uuid' => '11111111-1111-4111-8111-111111111111', 'original_name' => 'Drak.stl', 'ext' => 'stl', 'mime' => 'model/stl', 'size_bytes' => 10, 'sha256' => str_repeat('a', 64), 'storage_path' => 'files/x/original.stl', 'origin' => 'upload', 'status' => ModelFile::STATUS_READY]);
        Calculation::create(['token' => 'tok-cost-test-1', 'model_file_id' => $file->id, 'params' => ['material' => 'PLA', 'quantity' => 2], 'params_hash' => 'h', 'status' => Calculation::STATUS_DONE,
            'slicer' => ['grams' => 90.0, 'minutes' => 300], 'prices' => [['profile' => 'a', 'total' => 500.0], ['profile' => 'b', 'total' => 420.0]]]);
        $page = $this->get('/tools/cost?from=tok-cost-test-1')->assertOk();
        $page->assertSee('value="45"', false)->assertSee('value="2.5"', false)->assertSee(__('sell.cost.us.title'))->assertSee('210');           // per piece: 90 g / 2, 300 min / 2, the cheaper price / 2
        $this->get('/tools/cost?from=no-such-token')->assertOk()->assertDontSee(__('sell.cost.us.title'));
    }

    public function test_the_profit_of_a_sale_takes_the_platform_fees_off(): void
    {
        // Etsy, the defaults: 350 + 89 shipping = 439 paid; 0.20 USD listing, 6.5 % commission, 4 % + 10 Kč payment, shipping paid back out, cost 120
        $r = Profit::calculate([]);
        $this->assertSame('etsy', $r['platform']);
        $this->assertEqualsWithDelta(439.0, $r['revenue'], 0.01);
        $this->assertEqualsWithDelta(4.30, $r['fees']['listing'], 0.01);
        $this->assertEqualsWithDelta(28.54, $r['fees']['transaction'], 0.01);
        $this->assertEqualsWithDelta(27.56, $r['fees']['payment'], 0.01);
        $this->assertSame(0.0, $r['fees']['currency']);
        $this->assertEqualsWithDelta(60.40, $r['fees_total'], 0.01);
        $this->assertEqualsWithDelta(289.60, $r['net'], 0.01);
        $this->assertEqualsWithDelta(169.60, $r['profit'], 0.01);
        $this->assertEqualsWithDelta(48.5, $r['margin_pct'], 0.05);
        $this->assertSame(0, $r['break_even']);
        $this->assertSame('2026-10-08', $r['as_of']);
        // VAT registered: 21 % of what was paid goes to the state; a foreign listing: 2.5 % conversion
        $vat = Profit::calculate(['vat' => true, 'foreign' => true]);
        $this->assertEqualsWithDelta(439 - 439 / 1.21, $vat['vat'], 0.01);
        $this->assertEqualsWithDelta(439 * 0.025, $vat['fees']['currency'], 0.01);
        $this->assertEqualsWithDelta(169.60 - $vat['vat'] - $vat['fees']['currency'], $vat['profit'], 0.02);
        // other platforms: Fler's commission alone; a market's stall spread over the day; a shop's monthly plan over the month
        $this->assertEqualsWithDelta(181.71, Profit::calculate(['platform' => 'fler'])['profit'], 0.01);
        $fair = Profit::calculate(['platform' => 'fair', 'stall_fee' => 1500, 'stall_pieces' => 15]);
        $this->assertEqualsWithDelta(100.0, $fair['fees']['stall'], 0.01);
        $this->assertEqualsWithDelta(125.61, $fair['profit'], 0.01);
        $shop = Profit::calculate(['platform' => 'shopify', 'monthly_pieces' => 20]);
        $this->assertEqualsWithDelta(39 * 21.5 / 20, $shop['fees']['monthly'], 0.01);
        $this->assertSame(0.0, Profit::calculate(['platform' => 'matplace'])['fees_total']);
        // the break-even: fixed costs over the profit per piece; never, when a piece loses money
        $this->assertSame(12, Profit::calculate(['fixed_monthly' => 2000])['break_even']);
        $this->assertNull(Profit::calculate(['fixed_monthly' => 2000, 'cost' => 400])['break_even']);
        // a discount comes off the price before anything else; unknown platforms fall back to Etsy
        $this->assertEqualsWithDelta(35.0, Profit::calculate(['discount_pct' => 10])['discount'], 0.01);
        $this->assertSame('etsy', Profit::clean(['platform' => 'ebay'])['platform']);
    }

    public function test_the_profit_page_renders_with_the_cost_and_price_handed_over(): void
    {
        $this->get('/tools/profit')->assertOk()->assertSee(__('tools.profit.title'))->assertSee('data-sell="profit"', false)->assertSee('data-choice="platform"', false)->assertSee(__('sell.platform.fler'))->assertSee('data-param="stall_fee"', false);
        $this->get('/tools/profit?cost=75.3&price=105.42')->assertOk()->assertSee('value="75.3"', false)->assertSee('value="105.42"', false);
        $this->get('/en/tools/profit')->assertOk()->assertSee('Three prices side by side');
        $this->get('/es/tools/profit')->assertOk()->assertSee('Tres precios lado a lado');
        // every platform of the config has a name in every language and a dated source
        foreach (config('sell.platforms') as $key => $fee) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $fee['as_of'], $key);
            $this->assertNotEmpty($fee['source'], $key);
            foreach (['cs', 'en', 'es'] as $locale) {
                $this->assertNotSame('sell.platform.'.$key, __('sell.platform.'.$key, [], $locale));
            }
        }
    }

    public function test_a_year_of_selling_adds_up_month_by_month_and_lists_the_steps_to_take(): void
    {
        $in = ['products' => [['name' => 'Drak', 'cost' => 75.3, 'price' => 105.42, 'qty' => 10], ['name' => '', 'cost' => 40, 'price' => 120, 'qty' => 5, 'photo' => true, 'listed' => true]], 'fixed' => 500, 'season' => 'flat', 'start' => 1, 'channel' => 'etsy', 'done' => ['photo:Drak']];
        $r = Plan::calculate($in);
        $this->assertCount(12, $r['months']);
        $this->assertEqualsWithDelta(1654.2, $r['months'][0]['revenue'], 0.01);           // 10 × 105.42 + 5 × 120
        $this->assertEqualsWithDelta(953.0, $r['months'][0]['variable'], 0.01);           // 10 × 75.3 + 5 × 40
        $this->assertEqualsWithDelta(201.2, $r['months'][0]['profit'], 0.01);
        $this->assertSame(1, $r['break_even_month']);
        $this->assertEqualsWithDelta(12 * 1654.2, $r['revenue'], 0.05);
        $this->assertEqualsWithDelta(12 * 201.2, $r['profit'], 0.05);
        $this->assertEqualsWithDelta(180, $r['pieces'], 0.01);
        // the steps read off what is missing: the dragon is not photographed (ticked off already) nor listed; the nameless one is complete
        $this->assertSame(['photo:Drak', 'list:Drak'], array_column($r['steps'], 'id'));
        $this->assertSame([true, false], array_column($r['steps'], 'done'));
        // fixed costs the sales never cover: no break-even; a product without a price or a cost, no channel, no fixed costs: all in the list
        $this->assertNull(Plan::calculate(array_merge($in, ['fixed' => 3000]))['break_even_month']);
        $bare = Plan::calculate(['products' => [['name' => 'A', 'price' => 50, 'cost' => 80]]]);
        $this->assertSame(['price_below_cost', 'photo', 'list', 'channel', 'fixed'], array_column($bare['steps'], 'key'));
        $this->assertSame(['cost', 'price', 'photo', 'list', 'channel', 'fixed'], array_column(Plan::calculate(['products' => [['name' => 'B']]])['steps'], 'key'));
        $this->assertSame(['add_product', 'channel', 'fixed'], array_column(Plan::calculate([])['steps'], 'key'));
        // the season: Christmas sells 13 ordinary months in a year; the plan can start in November
        $xmas = Plan::calculate(['products' => [['cost' => 10, 'price' => 30, 'qty' => 15]], 'season' => 'christmas', 'start' => 11]);
        $this->assertEqualsWithDelta(15 * 13 * 30, $xmas['revenue'], 0.05);
        $this->assertSame(11, $xmas['months'][0]['month']);
        $this->assertEqualsWithDelta(15 * 2.0 * 30, $xmas['months'][0]['revenue'], 0.05);
        // twelve multipliers of the visitor's own make the season custom; too many products are cut at twenty
        $custom = Plan::clean(['months' => array_fill(0, 12, 2), 'season' => 'flat', 'products' => array_fill(0, 25, ['name' => 'x'])]);
        $this->assertSame('custom', $custom['season']);
        $this->assertCount(20, $custom['products']);
    }

    public function test_the_plan_page_its_pdf_and_the_plans_of_an_account(): void
    {
        $this->get('/tools/plan')->assertOk()->assertSee(__('tools.plan.title'))->assertSee('data-sell="plan"', false)->assertSee('id="plan-product-row"', false)->assertSee('id="plan-pdf-form"', false)->assertSee(__('sell.plan.save.login'));
        $this->get('/en/tools/plan')->assertOk()->assertSee('What to do this week');
        $this->get('/es/tools/plan')->assertOk()->assertSee('Qué hacer esta semana');
        $plan = ['name' => 'Vánoce 2026', 'products' => [['name' => 'Drak', 'cost' => 75.3, 'price' => 105.42, 'qty' => 10]], 'fixed' => 500, 'season' => 'christmas', 'start' => 11, 'channel' => 'etsy'];
        $pdf = $this->post('/tools/plan/pdf', ['plan' => json_encode($plan)]);
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());
        $this->assertStringContainsString('vanoce-2026.pdf', (string) $pdf->headers->get('Content-Disposition'));
        // a guest cannot save; an account keeps up to twenty plans under their names
        $this->getJson('/api/sell/plans')->assertStatus(401);
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/sell/plans')->assertOk()->assertJsonCount(0, 'plans');
        $this->actingAs($user)->postJson('/api/sell/plans', ['name' => 'Vánoce 2026', 'data' => $plan])->assertCreated()->assertJsonPath('plan.name', 'Vánoce 2026')->assertJsonPath('plan.data.season', 'christmas');
        $this->actingAs($user)->postJson('/api/sell/plans', ['name' => 'Vánoce 2026', 'data' => array_merge($plan, ['fixed' => 900])])->assertCreated();
        $this->assertSame(1, SellPlan::where('user_id', $user->id)->count());                   // the same name is replaced
        $this->assertSame(900.0, (float) SellPlan::where('user_id', $user->id)->first()->data['fixed']);
        $this->actingAs($user)->postJson('/api/sell/plans', ['name' => '', 'data' => $plan])->assertStatus(422);
        $this->get('/tools/plan')->assertSee(__('sell.plan.save.account'));
        $id = SellPlan::where('user_id', $user->id)->first()->id;
        $other = User::factory()->create();
        $this->actingAs($other)->deleteJson('/api/sell/plans/'.$id)->assertOk();
        $this->assertSame(1, SellPlan::where('user_id', $user->id)->count());                   // somebody else's plan stays
        $this->actingAs($user)->deleteJson('/api/sell/plans/'.$id)->assertOk();
        $this->assertSame(0, SellPlan::count());
        for ($i = 0; $i < SellPlan::MAX_PER_USER; $i++) {
            SellPlan::create(['user_id' => $user->id, 'name' => 'p'.$i, 'data' => []]);
        }
        $this->actingAs($user)->postJson('/api/sell/plans', ['name' => 'one more', 'data' => $plan])->assertStatus(422)->assertJsonPath('error', 'too_many');
    }
}
