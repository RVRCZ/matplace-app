<?php

namespace Tests\Feature;

use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\Wallet;
use App\Models\CreditTransaction;
use App\Models\Event;
use App\Models\FarmOrder;
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
 * The sales path on the order page (docs/prompts/prodejni-cesta-farma.md, 25 Oct 2026): the card pays the order itself
 * and never less than the gateway takes, the delivery is preselected so the sum is on the page at once, the words
 * say "card or credit" and never "you must top up", and the top-up and the payment are measured. Fake slicer, fake
 * gateway, sync queue.
 */
final class FarmSalesPathTest extends TestCase
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
        $this->user = User::factory()->create(['email' => 'customer@example.com']);
    }

    private function order(): FarmOrder
    {
        $path = sys_get_temp_dir().'/mp_sales_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20.0);
        $uuid = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated()->json('file.uuid');
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $uuid])->assertCreated();

        return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
    }

    private function state(FarmOrder $order): array
    {
        return $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
    }

    /** @return array{slot: int, total: float} the colour the page ticks first and its price with pickup */
    private function offer(FarmOrder $order): array
    {
        $state = $this->state($order);
        $this->assertSame('sliced', $state['status']);
        $c = collect($state['colors'])->firstWhere('sliced', true) ?? $state['colors'][0];

        return ['slot' => $c['slot'], 'total' => (float) $c['total']];
    }

    /** Credit on the account without a gateway in between (an adjustment, as the admin makes one). */
    private function giveCredit(float $amount, string $currency = 'CZK'): void
    {
        $this->user->forceFill(['currency' => $currency])->save();
        $before = app(Wallet::class)->balance($this->user->fresh())->amount;
        CreditTransaction::create(['user_id' => $this->user->id, 'type' => CreditTransaction::TYPE_TOPUP, 'amount' => $amount, 'currency' => $currency, 'note' => 'test']);
        $this->assertEqualsWithDelta($before + $amount, app(Wallet::class)->balance($this->user->fresh())->amount, 0.001);
    }

    public function test_the_card_pays_what_is_missing_and_never_less_than_the_gateway_takes(): void
    {
        $order = $this->order();
        $offer = $this->offer($order);
        $this->assertSame(15.0, (float) $this->state($order)['min_charge'], 'the page knows the floor of a card payment in crowns');
        // four crowns short: the card is charged the gateway's floor, the rest stays as credit
        $this->giveCredit($offer['total'] - 4);
        $r = $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/checkout", ['slot' => $offer['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $offer['total']])->assertOk();
        $this->assertSame(15.0, (float) $r->json('amount'));
        $payment = Payment::where('farm_order_id', $order->id)->latest('id')->firstOrFail();
        $this->assertSame(15.0, (float) $payment->amount);
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_QUEUED, $order->status);
        $this->assertEqualsWithDelta($offer['total'], $order->price_total, 0.001, 'the order costs what was shown, not what the card took');
        $this->assertEqualsWithDelta(11.0, app(Wallet::class)->balance($this->user->fresh())->amount, 0.001, 'fifteen paid, four needed: eleven stay as credit');
        // the order's payment is measured with its value and currency, by card
        $paid = Event::where('type', 'order_paid')->where('meta->order', $order->id)->first();
        $this->assertNotNull($paid);
        $this->assertSame(['value' => $offer['total'], 'currency' => 'CZK', 'kind' => 'card'], ['value' => (float) $paid->meta['value'], 'currency' => $paid->meta['currency'], 'kind' => $paid->meta['kind']]);
        // the credit that arrived for the order is a top-up of its own kind
        $topup = Event::where('type', 'topup')->where('meta->event_id', 'topup-'.$payment->id)->first();
        $this->assertNotNull($topup);
        $this->assertSame(['value' => 15.0, 'currency' => 'CZK', 'kind' => 'order'], ['value' => (float) $topup->meta['value'], 'currency' => $topup->meta['currency'], 'kind' => $topup->meta['kind']]);

        // a bigger shortfall is charged as it is, rounded up to whole crowns
        $second = $this->order();
        $offer2 = $this->offer($second);
        $r = $this->actingAs($this->user)->postJson("/farm/orders/{$second->token}/checkout", ['slot' => $offer2['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $offer2['total']])->assertOk();
        $this->assertSame(ceil($offer2['total'] - 11.0), (float) $r->json('amount'));
    }

    public function test_an_account_in_euros_pays_whole_euros_by_card_and_the_floor_is_fifty_cents(): void
    {
        $this->user->forceFill(['currency' => 'EUR', 'country' => 'DE'])->save();
        $order = $this->order();
        $state = $this->state($order);
        $this->assertSame('EUR', $state['currency']);
        $this->assertSame(0.5, (float) $state['min_charge']);
        $offer = $this->offer($order);
        $r = $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/checkout", ['slot' => $offer['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $offer['total']])->assertOk();
        $this->assertSame(ceil($offer['total']), (float) $r->json('amount'), 'whole euros');
        $this->assertSame('EUR', $r->json('currency'));
        // a changed price is never charged silently
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/checkout", ['slot' => $offer['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $offer['total'] + 1])->assertStatus(422)->assertJsonPath('error', 'price_changed');
    }

    public function test_the_delivery_is_preselected_the_last_way_used_or_the_cheapest_one(): void
    {
        // nothing ordered before, parcels only: the pickup point is the cheaper kind to Czechia
        app(FarmSettings::class)->set('delivery_modes', ['packeta_point', 'packeta_home']);
        $order = $this->order();
        $this->assertSame('packeta_point', $this->state($order)['delivery']);
        // an account in Austria: only home delivery goes there
        $this->user->forceFill(['country' => 'AT'])->save();
        $this->assertSame('packeta_home', $this->state($order)['delivery']);
        $this->user->forceFill(['country' => 'CZ'])->save();
        // the farm hands prints over in person: free, so first
        app(FarmSettings::class)->set('delivery_modes', ['packeta_point', 'packeta_home', 'pickup']);
        $this->assertSame('pickup', $this->state($order)['delivery']);
        // a paid order before went home: the next one opens with that
        app(FarmSettings::class)->set('delivery_modes', ['packeta_point', 'packeta_home']);
        FarmOrder::factory()->paid(now()->subDay())->create(['user_id' => $this->user->id, 'delivery' => 'packeta_home']);
        $this->assertSame('packeta_home', $this->state($order)['delivery']);
        // a way no longer on offer is not preselected
        app(FarmSettings::class)->set('delivery_modes', ['packeta_point']);
        $this->assertSame('packeta_point', $this->state($order)['delivery']);
        // paid: what was chosen, whatever the history says
        app(FarmSettings::class)->set('delivery_modes', ['packeta_point', 'packeta_home']);
        $this->giveCredit(2000);
        $offer = $this->offer($order);
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $offer['slot'], 'delivery' => 'packeta_point', 'terms' => true, 'expected_total' => $offer['total'] + 99, 'address' => ['name' => 'Jan Novák', 'phone' => '+420777123456', 'country' => 'CZ', 'point' => ['id' => '79', 'name' => 'Z-BOX Praha', 'carrier_id' => '', 'country' => 'CZ']]])->assertOk();
        $this->assertSame('packeta_point', $this->state($order)['delivery']);
    }

    public function test_the_words_say_card_or_credit_and_the_page_shows_the_steps_and_the_sum_in_three_languages(): void
    {
        $order = $this->order();
        foreach (['cs', 'en', 'es'] as $lang) {
            app()->setLocale($lang);
            foreach (['farm.lead', 'farm.credit.lead', 'farm.order.pay', 'farm.order.pay_short', 'farm.order.card_kept', 'farm.refuse.credit'] as $key) {
                $this->assertNotSame($key, __($key), "$key ($lang)");
                $this->assertDoesNotMatchRegularExpression('/dobít kartou|top up by card|recargar con tarjeta|musíte dobít|must top up|debe recargar/iu', __($key), "$key ($lang)");
            }
            $this->assertStringContainsString('Stripe', __('farm.terms.items.3'), "the terms name the card ($lang)");
            $html = (string) $this->actingAs($this->user)->get($this->localized("/farm/orders/{$order->token}", $lang))->assertOk()->getContent();
            foreach (['farm-total-line', 'farm-pay-note', __('farm.order.step_material_hint'), __('farm.order.step_order_hint')] as $needle) {
                $this->assertStringContainsString($needle, $html, "$needle ($lang)");
            }
            // the two links the script fills later lead somewhere before it runs (the audit's href="#")
            $this->assertMatchesRegularExpression('~id="farm-short" href="[^"#]*/farm/orders/'.$order->token.'/short\.mp4"~', $html, "short link ($lang)");
            $this->assertMatchesRegularExpression('~id="farm-topup" href="[^"#]*/account/credit\?back='.$order->token.'"~', $html, "top-up link ($lang)");
            $this->assertStringNotContainsString('href="#"', $html, "no placeholder links ($lang)");
            foreach (['farm.order.ship.total', 'farm.order.ship.total_estimate', 'farm.order.pay_note_round', 'farm.order.pay_note_min'] as $key) {
                $this->assertStringContainsString($key, $html, "$key reaches the script ($lang)");
                $this->assertNotSame($key, __($key), "$key ($lang)");
            }
        }
        app()->setLocale('cs');
    }

    public function test_the_start_page_counts_the_models_left_in_every_language(): void
    {
        $forms = [
            'cs' => ['Dnes už další model spočítat nemůžete.', 'ještě 1 model.', 'ještě 3 modely.', 'ještě 7 modelů.'],
            'en' => ['cannot calculate another model today', '1 more model today', '3 more models today', '7 more models today'],
            'es' => ['otro modelo', '1 modelo más', '3 modelos más', '7 modelos más'],
        ];
        foreach ($forms as $lang => $expected) {
            foreach ([0, 1, 3, 7] as $i => $n) {
                $this->assertStringContainsString($expected[$i], trans_choice('farm.slices_left', $n, ['n' => $n], $lang), "$lang, $n left");
            }
        }
        // a customer with a daily allowance reads the right form on the start page
        app(FarmSettings::class)->set('daily_slices_per_user', 3);
        $this->actingAs($this->user)->get('/farm')->assertOk()->assertSee('ještě 3 modely.');
    }

    public function test_the_top_up_and_a_refused_payment_are_measured(): void
    {
        // the customer goes to the gateway for credit
        $this->actingAs($this->user)->post('/account/credit', ['amount' => 300])->assertRedirect();
        $payment = Payment::latest('id')->firstOrFail();
        $started = Event::where('type', 'topup_started')->latest('id')->first();
        $this->assertNotNull($started);
        $this->assertSame(['value' => 300.0, 'currency' => 'CZK', 'event_id' => 'topup-'.$payment->id], ['value' => (float) $started->meta['value'], 'currency' => $started->meta['currency'], 'event_id' => $started->meta['event_id']]);
        // the bank says yes, twice: one top-up event, in the visit that started it
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $topups = Event::where('type', 'topup')->get();
        $this->assertCount(1, $topups);
        $this->assertSame(['value' => 300.0, 'currency' => 'CZK', 'kind' => 'topup'], ['value' => (float) $topups[0]->meta['value'], 'currency' => $topups[0]->meta['currency'], 'kind' => $topups[0]->meta['kind']]);
        $this->assertSame($started->session_id, $topups[0]->session_id);
        $this->assertSame($this->user->id, $topups[0]->user_id);
        $this->assertEqualsWithDelta(300.0, app(Wallet::class)->balance($this->user->fresh())->amount, 0.001, 'the money arrived once');

        // a payment from credit refused because the price moved: a failure of the pay step, with its code
        $order = $this->order();
        $offer = $this->offer($order);
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $offer['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $offer['total'] + 1])->assertStatus(422)->assertJsonPath('error', 'price_changed');
        $failed = Event::where('type', 'failed')->where('meta->order', $order->id)->latest('id')->first();
        $this->assertNotNull($failed);
        $this->assertSame(['step' => 'pay', 'code' => 'price_changed'], ['step' => $failed->meta['step'], 'code' => $failed->meta['code']]);
        // not enough credit is no failure: the card takes over (402 with what is missing)
        $this->giveCredit(-290);
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $offer['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $offer['total']])->assertStatus(402);
        $this->assertSame(1, Event::where('type', 'failed')->where('meta->order', $order->id)->count());
        // paid from credit: the payment carries its value and currency
        $this->giveCredit(1000);
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $offer['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $offer['total']])->assertOk();
        $paid = Event::where('type', 'order_paid')->where('meta->order', $order->id)->first();
        $this->assertSame(['value' => $offer['total'], 'currency' => 'CZK', 'kind' => 'credit'], ['value' => (float) $paid->meta['value'], 'currency' => $paid->meta['currency'], 'kind' => $paid->meta['kind']]);
    }
}
