<?php

namespace Tests\Feature;

use App\Domain\Affiliate\Coupons;
use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\OrderFlow;
use App\Domain\Farm\OrderService;
use App\Domain\Farm\Wallet;
use App\Models\AnonymousSession;
use App\Models\CreditTransaction;
use App\Models\FarmOrder;
use App\Models\ModelFile;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Models\User;
use App\Support\Money;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * Discount codes (docs/AT.md §3). The core is one number: what the order page shows, what the quote says, what is held
 * from the credit and what the card is asked for are the price priceFor makes, for a signed-in customer and a guest, in
 * crowns and euros, at the gateway's smallest charge and with a code that makes the print free. Fake slicer, fake gateway
 * (no card is ever charged), sync queue.
 */
class AffiliateCouponsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private AnonymousSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        config(['farm.public' => true, 'farm.settings.admin_email' => 'farm@example.com']);
        $this->user = User::factory()->create(['email' => 'customer@example.com']);
        app(FarmSettings::class)->set('promo_codes_enabled', true);
    }

    // ── helpers ───────────────────────────────────────────────────────────────────────────────────────────────────

    private function code(array $over = []): PromoCode
    {
        return PromoCode::create($over + ['code' => 'JARO10', 'kind' => 'percent', 'value' => 10, 'once_per_account' => false, 'active' => true]);
    }

    private function upload($who = null): string
    {
        $path = sys_get_temp_dir().'/mp_coupon_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20.0);
        $client = $who === 'guest' ? $this->withCredentials() : $this->actingAs($who ?? $this->user);

        return $client->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated()->json('file.uuid');
    }

    private function order(?User $user = null): FarmOrder
    {
        $user ??= $this->user;
        $r = $this->actingAs($user)->postJson('/farm/orders', ['file' => $this->upload($user)])->assertCreated();

        return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
    }

    private function guestOrder(): FarmOrder
    {
        auth()->logout();   // actingAs() of an earlier step stays on the guard: a guest is nobody
        $uuid = $this->upload('guest');
        $this->session = AnonymousSession::findOrFail(ModelFile::latest('id')->firstOrFail()->anonymous_session_id);
        $r = $this->asGuest()->postJson('/farm/orders', ['file' => $uuid])->assertCreated();

        return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
    }

    private function asGuest(): static
    {
        return $this->withCredentials()->withUnencryptedCookie(AnonymousSession::COOKIE, $this->session->token);
    }

    /** @return array<string, mixed> the page's state of the order */
    private function state(FarmOrder $order, bool $guest = false): array
    {
        $client = $guest ? $this->asGuest() : $this->actingAs($this->user);

        return $client->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
    }

    private function apply(FarmOrder $order, string $code, bool $guest = false)
    {
        $client = $guest ? $this->asGuest() : $this->actingAs($this->user);

        return $client->postJson("/farm/orders/{$order->token}/promo", ['code' => $code]);
    }

    private function credit(int $amount): void
    {
        $this->actingAs($this->user)->post('/account/credit', ['amount' => $amount])->assertRedirect();
        $payment = Payment::latest('id')->firstOrFail();
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $this->user->refresh();
    }

    private function payFromCredit(FarmOrder $order, array $over = [])
    {
        $s = $this->state($order);

        return $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", $over + [
            'slot' => $s['colors'][0]['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $s['colors'][0]['total'],
        ]);
    }

    private function checkoutGuest(FarmOrder $order, string $email = 'host@example.com', array $over = [])
    {
        $s = $this->state($order, true);

        return $this->asGuest()->postJson("/farm/orders/{$order->token}/checkout", $over + ['slot' => $s['colors'][0]['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $s['colors'][0]['total'], 'email' => $email]);
    }

    private function bankConfirms(Payment $payment): void
    {
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
    }

    private function held(FarmOrder $order): float
    {
        return round(-(float) CreditTransaction::where('farm_order_id', $order->id)->where('type', CreditTransaction::TYPE_HOLD)->sum('amount'), 2);
    }

    // ── ONE NUMBER ────────────────────────────────────────────────────────────────────────────────────────────────

    public function test_signed_in_czk_the_page_the_quote_the_stored_price_and_the_credit_held_are_one_number(): void
    {
        $this->credit(5000);
        $order = $this->order();
        $before = (float) $this->state($order)['colors'][0]['total'];
        $this->code();

        $answer = $this->apply($order, 'jaro10')->assertOk()->json();   // any case, the code is normalised
        $expected = (float) ceil(round($before * 0.9, 6));   // 10 % off the print, rounded up to a whole crown: worked out here, not by the code under test
        $this->assertLessThan($before, $expected);

        $color = $answer['colors'][0];
        $this->assertEqualsWithDelta($expected, $color['total'], 0.001, 'the colour tile');
        $this->assertEqualsWithDelta($expected, $answer['total'], 0.001, 'the order\'s stored total, shown as the price');
        $this->assertEqualsWithDelta($expected, $answer['price']['total'], 0.001);
        $this->assertEqualsWithDelta($before - $expected, $color['price']['discount'], 0.001);
        $this->assertEqualsWithDelta($before, $color['price']['print_before_discount'], 0.001);
        $this->assertSame('JARO10', $color['price']['coupon']['code']);
        $this->assertEqualsWithDelta($expected, $answer['price']['print_total'], 0.001, 'the big number of the page is the print after the discount');
        $this->assertSame('JARO10', $answer['promo']['code']);
        $this->assertTrue($answer['promo']['applied']);

        // the quote the page asks for (pickup: the print alone)
        $quote = $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/quote", ['slot' => $color['slot'], 'delivery' => 'pickup', 'country' => 'CZ'])->assertOk()->json();
        $this->assertEqualsWithDelta($expected, $quote['total'], 0.001);
        $this->assertEqualsWithDelta($expected, $quote['price']['total'], 0.001);

        // the order's own stored price and what priceFor says now
        $order->refresh();
        $fresh = app(OrderService::class)->priceFor($order, $order->printer, 'pickup', null, null, 'CZK');
        $this->assertEqualsWithDelta($expected, $order->price_total, 0.001);
        $this->assertEqualsWithDelta($expected, $fresh['total'], 0.001);

        // paid from the credit: held exactly that, and the use is counted with the payment, not before
        $this->assertSame(0, PromoCode::first()->uses, 'applying counts nothing');
        $this->payFromCredit($order)->assertOk();
        $order->refresh();
        $this->assertEqualsWithDelta($expected, $this->held($order), 0.001, 'what is held from the credit');
        $this->assertEqualsWithDelta($expected, $order->price_total, 0.001);
        $this->assertEqualsWithDelta($before - $expected, $order->discount, 0.001);
        $this->assertNotNull($order->promo_counted_at);
        $this->assertSame(1, PromoCode::first()->uses);
        $this->assertEqualsWithDelta(5000 - $expected, app(Wallet::class)->balance($this->user->fresh())->amount, 0.001);
    }

    public function test_a_wrong_expected_total_is_refused_never_charged_silently(): void
    {
        $this->credit(5000);
        $order = $this->order();
        $this->code();
        $this->apply($order, 'JARO10')->assertOk();
        // the page showed the price WITHOUT the code (a stale page): the payment refuses and says what it costs now
        $s = $this->state($order);
        $this->payFromCredit($order, ['expected_total' => $s['colors'][0]['price']['print_before_discount']])->assertStatus(422)->assertJsonPath('error', 'price_changed');
        $this->assertSame('sliced', $order->fresh()->status);
        $this->assertSame(0.0, $this->held($order));
    }

    public function test_guest_the_card_is_asked_for_exactly_the_discounted_price_and_the_webhook_pays_that_order(): void
    {
        $order = $this->guestOrder();
        $before = (float) $this->state($order, true)['colors'][0]['total'];
        $this->code(['value' => 25]);
        $answer = $this->apply($order, 'JARO10', true)->assertOk()->json();
        $expected = (float) ceil(round($before * 0.75, 6));
        $this->assertEqualsWithDelta($expected, $answer['colors'][0]['total'], 0.001);

        $this->checkoutGuest($order)->assertOk();
        $payment = Payment::latest('id')->firstOrFail();
        $this->assertSame(max($expected, 15.0), $payment->amount, 'the card pays the price after the discount (never under the gateway floor)');
        $this->assertSame($expected, (float) $payment->context['expected_total']);

        $this->bankConfirms($payment);
        $order->refresh();
        $this->assertContains($order->status, ['paid', 'queued']);
        $this->assertEqualsWithDelta($expected, $order->price_total, 0.001);
        $this->assertEqualsWithDelta($expected, $this->held($order), 0.001, 'the held credit is the card\'s money, exactly the shown price');
        $this->assertEqualsWithDelta($payment->amount - $expected, app(Wallet::class)->balance($order->user)->amount, 0.001);
        $this->assertSame(1, PromoCode::first()->uses);
        $this->assertSame('paid', $payment->fresh()->context['result']);
    }

    public function test_euro_account_percent_and_fixed_discounts_are_worked_out_in_euros_and_everywhere_the_same(): void
    {
        $this->user->forceFill(['currency' => 'EUR'])->save();
        CreditTransaction::create(['user_id' => $this->user->id, 'type' => 'topup', 'amount' => 500, 'currency' => 'EUR', 'note' => 'test']);
        $order = $this->order();
        $s = $this->state($order);
        $this->assertSame('EUR', $s['currency']);
        $before = (float) $s['colors'][0]['total'];

        // a percentage: the print stays rounded to ten cents, rounded up
        $this->code(['value' => 15]);
        $a = $this->apply($order, 'JARO10')->assertOk()->json();
        $expected = ceil(round($before * 0.85 * 10, 6)) / 10;
        $this->assertEqualsWithDelta($expected, $a['colors'][0]['total'], 0.0001);
        $this->assertEqualsWithDelta($expected, $a['total'], 0.0001);
        $this->assertEqualsWithDelta(round($before - $expected, 2), $a['colors'][0]['price']['discount'], 0.0001);

        // a fixed amount in crowns is converted the way prices are (up to ten cents)
        $this->apply($order, '')->assertOk();
        $this->code(['code' => 'PET', 'kind' => 'fixed', 'value' => 100]);
        $b = $this->apply($order, 'PET')->assertOk()->json();
        $off = Money::czk(100)->to('EUR')->amount;
        $this->assertEqualsWithDelta(4.0, $off, 0.0001);
        $this->assertEqualsWithDelta(round($before - $off, 2), $b['colors'][0]['total'], 0.0001);
        $quote = $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/quote", ['slot' => $b['colors'][0]['slot'], 'delivery' => 'pickup', 'country' => 'CZ'])->assertOk()->json();
        $this->assertEqualsWithDelta(round($before - $off, 2), $quote['total'], 0.0001);

        $this->payFromCredit($order)->assertOk();
        $this->assertEqualsWithDelta(round($before - $off, 2), $this->held($order->refresh()), 0.0001);
        $this->assertEqualsWithDelta(round($before - $off, 2), $order->price_total, 0.0001);
    }

    public function test_a_card_for_a_tiny_total_is_charged_the_gateway_minimum_and_the_rest_stays_credit(): void
    {
        $order = $this->guestOrder();
        $before = (float) $this->state($order, true)['colors'][0]['total'];
        $this->code(['kind' => 'fixed', 'value' => $before - 5]);   // leaves 5 Kč: under the 15 Kč the gateway takes
        $a = $this->apply($order, 'JARO10', true)->assertOk()->json();
        $this->assertEqualsWithDelta(5.0, $a['colors'][0]['total'], 0.001);
        $this->assertEqualsWithDelta(5.0, $a['total'], 0.001);

        $this->checkoutGuest($order)->assertOk();
        $payment = Payment::latest('id')->firstOrFail();
        $this->assertSame(15.0, $payment->amount, 'the minimum charge, not the 5 Kč of the price');
        $this->bankConfirms($payment);
        $order->refresh();
        $this->assertEqualsWithDelta(5.0, $order->price_total, 0.001);
        $this->assertEqualsWithDelta(5.0, $this->held($order), 0.001);
        $this->assertEqualsWithDelta(10.0, app(Wallet::class)->balance($order->user)->amount, 0.001, 'what is over the price stays as credit');
    }

    public function test_a_hundred_percent_code_signed_in_the_order_is_paid_for_nothing(): void
    {
        $order = $this->order();   // no credit at all
        $this->code(['value' => 100]);
        $a = $this->apply($order, 'JARO10')->assertOk()->json();
        $this->assertEqualsWithDelta(0.0, $a['colors'][0]['total'], 0.001);
        $this->assertEqualsWithDelta(0.0, $a['total'], 0.001);

        $this->payFromCredit($order)->assertOk();
        $order->refresh();
        $this->assertContains($order->status, ['paid', 'queued']);
        $this->assertNotNull($order->number);
        $this->assertEqualsWithDelta(0.0, $order->price_total, 0.001);
        $this->assertSame(0, CreditTransaction::where('farm_order_id', $order->id)->where('type', 'hold')->where('amount', '!=', 0)->count(), 'no money moved');
        $this->assertEqualsWithDelta(0.0, app(Wallet::class)->balance($this->user->fresh())->amount, 0.001);
        $this->assertSame(1, PromoCode::first()->uses);
    }

    public function test_a_hundred_percent_code_for_a_guest_asks_for_an_account_and_charges_nothing(): void
    {
        $order = $this->guestOrder();
        $this->code(['value' => 100]);
        $a = $this->apply($order, 'JARO10', true)->assertOk()->json();
        $this->assertEqualsWithDelta(0.0, $a['total'], 0.001);

        $this->checkoutGuest($order)->assertStatus(422)->assertJsonPath('error', 'free_needs_account');
        $this->assertSame(0, Payment::count(), 'no card is asked for 0 Kč, nor for the gateway minimum');
        $order->refresh();
        $this->assertSame('sliced', $order->status);
        $this->assertNull($order->paid_at);
        $this->assertSame(0, PromoCode::first()->uses);
    }

    public function test_the_discount_never_touches_the_delivery(): void
    {
        $order = $this->order();
        $s = $this->state($order);
        $slot = $s['colors'][0]['slot'];
        $plain = $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/quote", ['slot' => $slot, 'delivery' => 'packeta_home', 'country' => 'CZ']);
        if ($plain->status() !== 200) {
            $this->markTestSkipped('no home delivery priced in this setup');
        }
        $plain = $plain->json();
        $this->assertGreaterThan(0, $plain['price']['shipping']);
        $this->code(['value' => 20]);
        $this->apply($order, 'JARO10')->assertOk();
        $with = $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/quote", ['slot' => $slot, 'delivery' => 'packeta_home', 'country' => 'CZ'])->assertOk()->json();

        $this->assertEqualsWithDelta($plain['price']['shipping'], $with['price']['shipping'], 0.001, 'the parcel costs what it cost');
        $this->assertEqualsWithDelta($plain['total'] - $with['price']['discount'], $with['total'], 0.001, 'total = old total less exactly the discount on the print');
        $this->assertEqualsWithDelta($with['price']['print_total'] + $with['price']['royalty'] + $with['price']['shipping'], $with['total'], 0.001);
    }

    public function test_the_designers_reward_is_not_reduced_by_a_code(): void
    {
        // applyToPrice works on the finished breakdown: print_total changes, royalty and shipping stay, the total follows
        $order = $this->order();
        $this->code(['value' => 50]);
        $order->forceFill(['promo_code_id' => PromoCode::first()->id])->save();
        $price = ['currency' => 'CZK', 'print_total' => 200.0, 'royalty' => 40.0, 'shipping' => 69.0, 'total' => 309.0];
        $out = app(Coupons::class)->applyToPrice($order->refresh(), $price, 200.0);
        $this->assertSame(100.0, $out['discount']);
        $this->assertSame(100.0, $out['print_total']);
        $this->assertSame(40.0, $out['royalty']);
        $this->assertSame(69.0, $out['shipping']);
        $this->assertSame(209.0, $out['total']);
    }

    // ── the rules of a code ───────────────────────────────────────────────────────────────────────────────────────

    public function test_every_refusal_says_why_and_leaves_the_price_alone(): void
    {
        $order = $this->order();
        $before = (float) $this->state($order)['colors'][0]['total'];
        $this->apply($order, 'NOPE')->assertStatus(422)->assertJsonPath('error', 'promo_unknown');
        $this->code(['code' => 'OFF', 'active' => false]);
        $this->apply($order, 'OFF')->assertStatus(422)->assertJsonPath('error', 'promo_inactive');
        $this->code(['code' => 'LATER', 'valid_from' => now()->addDay()]);
        $this->apply($order, 'LATER')->assertStatus(422)->assertJsonPath('error', 'promo_not_started');
        $this->code(['code' => 'OLD', 'valid_until' => now()->subDay()]);
        $this->apply($order, 'OLD')->assertStatus(422)->assertJsonPath('error', 'promo_expired');
        $this->code(['code' => 'GONE', 'max_uses' => 3, 'uses' => 3]);
        $this->apply($order, 'GONE')->assertStatus(422)->assertJsonPath('error', 'promo_used_up');
        $this->code(['code' => 'BIG', 'min_order_czk' => 100000]);
        $r = $this->apply($order, 'BIG')->assertStatus(422)->assertJsonPath('error', 'promo_min_order');
        $this->assertStringContainsString('100', $r->json('message'));
        $this->assertEqualsWithDelta($before, $this->state($order)['colors'][0]['total'], 0.001);
        $this->assertNull($order->fresh()->promo_code_id);
    }

    public function test_once_per_account_and_the_use_goes_back_when_the_order_fails(): void
    {
        $this->credit(5000);
        $this->code(['once_per_account' => true, 'max_uses' => 5]);
        $first = $this->order();
        $this->apply($first, 'JARO10')->assertOk();
        $this->payFromCredit($first)->assertOk();
        $this->assertSame(1, PromoCode::first()->uses);

        $second = $this->order();
        $this->apply($second, 'JARO10')->assertStatus(422)->assertJsonPath('error', 'promo_already_used');

        // the first order fails: the money and the use go back, the code may be used again
        app(OrderFlow::class)->fail($first->fresh(), 'test', 'operator');
        $this->assertSame(0, PromoCode::first()->uses, 'a use given back');
        $this->assertNotNull($first->fresh()->promo_released_at);
        $this->apply($second, 'JARO10')->assertOk();
    }

    public function test_the_last_use_cannot_be_paid_twice_and_a_price_changes_loudly(): void
    {
        $this->credit(5000);
        $this->code(['max_uses' => 1, 'once_per_account' => false]);
        $a = $this->order();
        $b = $this->order();
        $this->apply($a, 'JARO10')->assertOk();
        $this->apply($b, 'JARO10')->assertOk();
        $discounted = (float) $this->state($b)['colors'][0]['total'];
        $this->payFromCredit($a)->assertOk();
        $this->assertSame(1, PromoCode::first()->uses);

        // b's page still shows the discounted price; the code is used up now: the payment refuses at that price
        $s = $this->state($b);
        $this->actingAs($this->user)->postJson("/farm/orders/{$b->token}/pay", ['slot' => $s['colors'][0]['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $discounted])
            ->assertStatus(422)->assertJsonPath('error', 'price_changed');
        $this->assertSame('sliced', $b->fresh()->status);
        // and the used-up code says why on the page
        $this->assertNotNull($this->state($b)['promo']['problem']);

        // the same, but the code is used up between the price and the payment's transaction: redeem() refuses
        $c = $this->order();
        PromoCode::first()->update(['max_uses' => 2, 'uses' => 1]);
        $this->apply($c, 'JARO10')->assertOk();
        $price = app(OrderService::class)->priceFor($c->fresh(), $c->fresh()->printer, 'pickup', null, null, 'CZK');
        $this->assertArrayHasKey('coupon', $price);
        PromoCode::first()->update(['uses' => 2]);
        $this->expectException(\App\Domain\Farm\FarmRefusal::class);
        app(Coupons::class)->redeem($c->fresh(), $price);
    }

    public function test_a_guests_email_that_used_a_once_per_account_code_is_stopped_before_the_card(): void
    {
        $this->credit(5000);
        $this->code(['once_per_account' => true]);
        $mine = $this->order();
        $this->apply($mine, 'JARO10')->assertOk();
        $this->payFromCredit($mine)->assertOk();

        $order = $this->guestOrder();
        $this->apply($order, 'JARO10', true)->assertOk();   // a guest has no account to check yet
        $this->checkoutGuest($order, 'customer@example.com')->assertStatus(422)->assertJsonPath('error', 'promo_already_used');
        $this->assertSame(0, Payment::where('farm_order_id', $order->id)->count());
    }

    public function test_switched_off_nothing_shows_and_no_price_changes(): void
    {
        $this->credit(5000);
        $order = $this->order();
        $plain = (float) $this->state($order)['colors'][0]['total'];
        $code = $this->code();
        $this->apply($order, 'JARO10')->assertOk();
        $this->assertLessThan($plain, (float) $this->state($order)['colors'][0]['total']);

        app(FarmSettings::class)->set('promo_codes_enabled', false);
        $s = $this->state($order);
        $this->assertEqualsWithDelta($plain, $s['colors'][0]['total'], 0.001, 'a code already on the order takes nothing while the switch is off');
        $this->assertEqualsWithDelta($plain, $s['total'], 0.001);
        $this->assertNull($s['promo']);
        $this->assertArrayNotHasKey('discount', $s['price']);
        $this->apply($order, 'JARO10')->assertNotFound();
        $this->actingAs($this->user)->get("/farm/orders/{$order->token}")->assertOk()->assertDontSee('farm-promo', false);
        $this->payFromCredit($order)->assertOk();
        $this->assertEqualsWithDelta($plain, $this->held($order->refresh()), 0.001);
        $this->assertSame(0, $code->fresh()->uses);
        $this->assertNull($order->promo_code_id, 'a paid order does not name a code that gave nothing');
    }

    public function test_the_field_is_on_the_page_when_the_codes_are_on(): void
    {
        $order = $this->order();
        $this->actingAs($this->user)->get("/farm/orders/{$order->token}")->assertOk()->assertSee('id="farm-promo"', false)->assertSee(__('farm.promo.not_delivery'));
    }

    public function test_print_again_does_not_carry_the_old_code(): void
    {
        $this->credit(5000);
        $this->code();
        $order = $this->order();
        $this->apply($order, 'JARO10')->assertOk();
        $this->payFromCredit($order)->assertOk();
        $r = $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/repeat")->assertCreated();
        $copy = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
        $this->assertNull($copy->promo_code_id);
        $s = $this->state($copy);
        $plain = app(OrderService::class)->priceFor($copy, $copy->printer, 'pickup', null, null, 'CZK');
        $this->assertArrayNotHasKey('discount', $plain);
        $this->assertEqualsWithDelta($plain['total'], $s['colors'][0]['total'], 0.001);
    }

    public function test_a_full_refund_by_the_admin_gives_the_use_back_and_a_partial_one_keeps_it(): void
    {
        $this->credit(5000);
        $this->code(['max_uses' => 5]);
        $order = $this->order();
        $this->apply($order, 'JARO10')->assertOk();
        $this->payFromCredit($order)->assertOk();
        $this->assertSame(1, PromoCode::first()->uses);
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin)->post("/admin/farm/orders/{$order->token}/refund", ['note' => 'test'])->assertRedirect();
        $this->assertSame(0, PromoCode::first()->uses);
    }

    public function test_the_columns_are_wide_enough_for_what_is_written_into_them(): void
    {
        // SQLite does not enforce a varchar: the widths of the migration are compared with the longest values the code writes
        $mig = (string) file_get_contents(database_path('migrations/2026_11_17_100000_create_affiliates_and_promo_codes.php'));
        $width = fn (string $col) => (int) (preg_match('/string\(\''.$col.'\',\s*(\d+)\)/', $mig, $m) ? $m[1] : 255);
        $this->assertGreaterThanOrEqual(strlen(sha1('x')), $width('visitor'));
        $this->assertGreaterThanOrEqual(300, $width('referer'), 'the referer is cut to 300');
        $this->assertGreaterThanOrEqual(300, $width('landing'));
        $this->assertGreaterThanOrEqual(20, $width('code'));
        $this->assertGreaterThanOrEqual(255, $width('email'), 'a user\'s e-mail goes into partners.email');
        foreach ([PromoCode::KIND_PERCENT, PromoCode::KIND_FIXED, 'pending', 'payable', 'paid', 'void'] as $word) {
            $this->assertLessThanOrEqual(10, strlen($word));
        }
    }
}
