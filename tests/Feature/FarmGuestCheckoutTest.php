<?php

namespace Tests\Feature;

use App\Domain\Farm\CardRefunds;
use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\GuestCheckout;
use App\Domain\Farm\OrderFlow;
use App\Domain\Farm\Wallet;
use App\Engines\Payment\FakeGateway;
use App\Mail\FarmAdminAlert;
use App\Mail\FarmCardRefund;
use App\Mail\FarmGuestOrder;
use App\Mail\FarmOrderStatus;
use App\Models\AnonymousSession;
use App\Models\CreditTransaction;
use App\Models\Event;
use App\Models\FarmOrder;
use App\Models\FarmPrintJob;
use App\Models\ModelFile;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * An order without an account (docs/AO.md): a guest makes the order under the anonymous session, chooses as anyone
 * does, pays by card with an e-mail; the webhook makes the account (or attaches the order to the e-mail's) and the
 * guest gets the confirmation with the links. A print that fails is printed once more on its own; the second failure,
 * a cancellation or a payment that could not pay the order sends the card's money back to the card (§2). Fake slicer,
 * fake gateway, sync queue.
 */
class FarmGuestCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private AnonymousSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        config(['farm.public' => true, 'farm.settings.admin_email' => 'farm@example.com']);
        FakeGateway::$refundFails = false;
        FakeGateway::$refunds = [];
    }

    protected function tearDown(): void
    {
        FakeGateway::$refundFails = false;
        FakeGateway::$refunds = [];
        parent::tearDown();
    }

    /** A guest's order paid by card through the webhook (and approved, when the farm asks for that): the order, its payment, the account. */
    private function paidGuestOrder(string $email = 'host@example.com'): array
    {
        $order = $this->guestOrder();
        $this->checkout($order, ['email' => $email])->assertOk();
        $payment = Payment::latest('id')->firstOrFail();
        $this->bankConfirms($payment);
        $order->refresh();
        $this->assertContains($order->status, ['paid', 'queued']);
        if ($order->status === FarmOrder::STATUS_PAID) {
            app(OrderFlow::class)->approve($order, $this->admin()->id);
        }

        return [$order->refresh(), $payment->refresh(), $order->user];
    }

    private function admin(): User
    {
        $admin = User::where('email', 'admin@example.com')->first() ?? User::factory()->create(['email' => 'admin@example.com']);
        $admin->setRole(User::ROLE_ADMIN, true);

        return $admin;
    }

    /** The operator says what the printer did (a manual machine: this is how "printing" and "failed" happen). */
    private function operator(FarmOrder $order, string $to, int $progress = 0): void
    {
        $this->actingAs($this->admin())->post("/admin/farm/orders/{$order->token}/status", ['to' => $to])->assertRedirect();
        if ($progress > 0) {
            $order->printJobs()->whereIn('status', FarmPrintJob::ACTIVE)->update(['progress' => $progress]);
        }
        auth()->logout();
    }

    private function balance(User $user): float
    {
        return round((float) CreditTransaction::where('user_id', $user->id)->sum('amount'), 2);
    }

    /** As a browser would: the anonymous session's cookie on every request (the test client keeps none). */
    private function asGuest(): static
    {
        return $this->withCredentials()->withUnencryptedCookie(AnonymousSession::COOKIE, $this->session->token);
    }

    /** Another browser: an anonymous session of its own. */
    private function stranger(): static
    {
        return $this->withCredentials()->withUnencryptedCookie(AnonymousSession::COOKIE, 'stranger-'.uniqid());
    }

    private function guestOrder(): FarmOrder
    {
        $path = sys_get_temp_dir().'/mp_guest_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20.0);
        $this->withCredentials()->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated();
        $file = ModelFile::latest('id')->firstOrFail();
        $this->session = AnonymousSession::findOrFail($file->anonymous_session_id);
        $r = $this->asGuest()->postJson('/farm/orders', ['file' => $file->uuid])->assertCreated();

        return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
    }

    /** @return array{slot: int, total: float} */
    private function offer(FarmOrder $order): array
    {
        $state = $this->asGuest()->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $this->assertSame('sliced', $state['status']);

        return ['slot' => $state['colors'][0]['slot'], 'total' => (float) $state['colors'][0]['total']];
    }

    private function checkout(FarmOrder $order, array $over = [])
    {
        $offer = $this->offer($order);

        return $this->asGuest()->postJson("/farm/orders/{$order->token}/checkout", $over + ['slot' => $offer['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $offer['total']]);
    }

    private function bankConfirms(Payment $payment): void
    {
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
    }

    public function test_a_guest_orders_pays_by_card_and_the_payment_makes_the_account(): void
    {
        $order = $this->guestOrder();
        $this->assertNull($order->user_id);
        $this->assertSame($this->session->id, $order->anonymous_session_id);

        // the session sees its order, a stranger does not
        $this->asGuest()->get("/farm/orders/{$order->token}")->assertOk()->assertSee('farm-email', false)->assertSee(__('farm.guest.sign_in'));
        $this->stranger()->getJson("/farm/orders/{$order->token}/status")->assertNotFound();
        $this->assertGuest();

        $state = $this->asGuest()->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $this->assertSame(0.0, (float) $state['balance'], 'a guest has no credit');
        $this->assertTrue($state['guest']);

        // no e-mail, no payment; credit is not a guest's way
        $this->checkout($order)->assertStatus(422)->assertJsonValidationErrors('email');
        $offer = $this->offer($order);
        $this->asGuest()->postJson("/farm/orders/{$order->token}/pay", ['slot' => $offer['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $offer['total']])->assertUnauthorized();

        $r = $this->checkout($order, ['email' => 'Novy@Example.com'])->assertOk();
        $payment = Payment::latest('id')->firstOrFail();
        $this->assertNull($payment->user_id);
        $this->assertSame('novy@example.com', $payment->context['email']);
        $this->assertSame(Payment::PURPOSE_ORDER, $payment->purpose);
        $this->assertSame(max(ceil($offer['total']), 15.0), $payment->amount, 'the whole price by card, never under the gateway floor');
        $this->assertStringContainsString('paid='.$payment->id, $r->json('checkout_url'));
        $this->assertSame(0, User::count(), 'nothing is made on the way to the gateway');

        $this->bankConfirms($payment);
        $user = User::where('email', 'novy@example.com')->firstOrFail();
        $order->refresh();
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame($this->session->id, $order->anonymous_session_id, 'the session keeps the order');
        $this->assertContains($order->status, ['paid', 'queued']);
        $this->assertNotNull($order->number);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertTrue($user->hasRole('customer'));
        $this->assertSame($user->id, ModelFile::first()->owner_user_id, 'the session\'s files go with the new account');
        $this->assertSame($user->id, $payment->fresh()->user_id);
        $this->assertSame('created', $payment->fresh()->context['account']);
        $this->assertSame('paid', $payment->fresh()->context['result']);
        $this->assertEqualsWithDelta($payment->amount - (float) $order->price_total, app(Wallet::class)->balance($user)->amount, 0.001);
        $this->assertGuest();

        Mail::assertQueued(FarmGuestOrder::class, fn (FarmGuestOrder $m) => $m->hasTo('novy@example.com') && $m->created && $m->order->is($order));
        $mail = new FarmGuestOrder($order->fresh(), true);
        $html = $mail->render();
        $this->assertStringContainsString('signature=', $html, 'the link to the order works without signing in');
        $this->assertStringContainsString(e(__('farm.guest.mail.password')), $html);

        // the session still sees the paid order; the signed link opens it in another browser
        $this->asGuest()->getJson("/farm/orders/{$order->token}/status")->assertOk()->assertJsonPath('status', $order->status);
        $this->stranger()->get(GuestCheckout::orderLink($order))->assertOk();
        $this->get("/farm/orders/{$order->token}")->assertOk();   // the same browser goes on without the signature

        // "set a password": a fresh reset token, the reset confirms the e-mail; after that the link leads to the login
        $to = $this->get(GuestCheckout::passwordLink($user))->assertRedirect()->headers->get('Location');
        $this->assertStringContainsString('/reset-password/', $to);
        parse_str((string) parse_url($to, PHP_URL_QUERY), $q);
        $token = basename((string) parse_url($to, PHP_URL_PATH));
        $this->post('/reset-password', ['token' => $token, 'email' => $q['email'], 'password' => 'novéheslo1', 'password_confirmation' => 'novéheslo1'])->assertRedirect();
        $this->assertTrue(Hash::check('novéheslo1', $user->fresh()->password));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get(GuestCheckout::passwordLink($user))->assertRedirect(route('login'));

        // the webhook again: no second account, no second mail
        $this->bankConfirms($payment);
        $this->assertSame(1, User::count());
        Mail::assertQueued(FarmGuestOrder::class, 1);
    }

    public function test_an_e_mail_of_an_existing_account_gets_the_order_attached_without_a_login(): void
    {
        $owner = User::factory()->create(['email' => 'stavajici@example.com']);
        $order = $this->guestOrder();
        $this->checkout($order, ['email' => 'stavajici@example.com'])->assertOk();
        $this->bankConfirms(Payment::latest('id')->firstOrFail());

        $order->refresh();
        $this->assertSame($owner->id, $order->user_id);
        $this->assertContains($order->status, ['paid', 'queued']);
        $this->assertSame(1, User::count(), 'no second account');
        $this->assertSame('attached', Payment::latest('id')->first()->context['account']);
        $this->assertSame($owner->id, $order->modelFile->owner_user_id, 'the order\'s model comes along');
        $this->assertNull($this->session->fresh()->claimed_by_user_id, 'the session is not given to an account it never signed in to');
        $this->assertGuest();
        Mail::assertQueued(FarmGuestOrder::class, fn (FarmGuestOrder $m) => $m->hasTo('stavajici@example.com') && ! $m->created);
        $this->assertStringContainsString(e(__('farm.guest.mail.attached', ['email' => 'stavajici@example.com'])), (new FarmGuestOrder($order, false))->render());

        // the guest's browser still follows the order; the owner sees it in the account
        $this->asGuest()->getJson("/farm/orders/{$order->token}/status")->assertOk();
        $this->stranger()->actingAs($owner)->getJson("/farm/orders/{$order->token}/status")->assertOk();
    }

    public function test_signing_in_takes_the_guest_order_into_the_account(): void
    {
        $user = User::factory()->create(['email' => 'prihlasim@example.com', 'password' => 'heslo1234']);
        $order = $this->guestOrder();
        $this->asGuest()->post('/login', ['email' => 'prihlasim@example.com', 'password' => 'heslo1234'])->assertRedirect();
        $this->assertSame($user->id, $order->fresh()->user_id);
    }

    public function test_while_the_farm_is_not_public_a_guest_signs_in_first(): void
    {
        config(['farm.public' => false]);
        $this->get('/farm')->assertRedirect(route('login'));
        $path = sys_get_temp_dir().'/mp_guest_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20.0);
        $this->withCredentials()->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated();
        $file = ModelFile::latest('id')->firstOrFail();
        $this->session = AnonymousSession::findOrFail($file->anonymous_session_id);
        $this->asGuest()->postJson('/farm/orders', ['file' => $file->uuid])->assertForbidden();
        $this->assertSame(0, FarmOrder::count());
    }

    public function test_the_calculator_button_makes_a_guest_order_too(): void
    {
        $this->get('/')->assertOk()->assertSee('data-store="'.route('farm.orders.store').'"', false)->assertSee(e(__('farm.cta_hint_guest')), false);
    }

    // ── §2: money back to the card, one automatic reprint ────────────────────────────────────────────────────────────

    public function test_a_failed_print_is_printed_again_and_the_second_failure_returns_everything_to_the_card(): void
    {
        [$order, $payment, $user] = $this->paidGuestOrder();
        $total = (float) $order->price_total;
        $leftover = round($payment->amount - $total, 2);
        $this->assertEqualsWithDelta($leftover, $this->balance($user), 0.001);

        // the first failure: printed again, the money stays held, the customer hears "we print it again"
        $this->operator($order, 'printing');
        $this->operator($order, 'failed');
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_QUEUED, $order->status);
        $this->assertSame(1, $order->reprints);
        $this->assertNull($order->error, 'back in the queue, nothing to show as an error');
        $this->assertSame([], FakeGateway::$refunds);
        $this->assertSame(0, PaymentRefund::count());
        $this->assertEqualsWithDelta($leftover, $this->balance($user), 0.001, 'the money stays held');
        $this->assertStringContainsString('automatic reprint 1 after operator', $order->events->pluck('note')->implode(' | '));
        Mail::assertQueued(FarmOrderStatus::class, fn (FarmOrderStatus $m) => $m->status === FarmOrderStatus::REPRINT && $m->hasTo('host@example.com'));
        Mail::assertNotQueued(FarmOrderStatus::class, fn (FarmOrderStatus $m) => $m->status === FarmOrder::STATUS_FAILED);
        $alert = Mail::queued(FarmAdminAlert::class)->first(fn (FarmAdminAlert $m) => str_contains($m->subjectLine, 'tisk selhal'));
        $this->assertNotNull($alert);
        $this->assertTrue(collect($alert->lines)->contains(fn ($l) => str_contains($l, 'tiskne znovu automaticky')), implode(' | ', $alert->lines));

        // the second failure: the order fails, the whole price (delivery included) goes back to the card
        $this->operator($order, 'printing');
        $this->operator($order, 'failed');
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_FAILED, $order->status);
        $this->assertSame(1, $order->reprints);
        $refund = PaymentRefund::sole();
        $this->assertSame(PaymentRefund::STATUS_DONE, $refund->status);
        $this->assertSame(PaymentRefund::REASON_PRINT_FAILED, $refund->reason);
        $this->assertEqualsWithDelta($total, $refund->amount, 0.001);
        $this->assertSame([[$payment->id, $total, 'print_failed']], FakeGateway::$refunds);
        $this->assertStringStartsWith('fake_refund_', (string) $refund->gateway_ref);
        $this->assertEqualsWithDelta($leftover, $this->balance($user), 0.001, 'the card got it back, the credit holds only the rounding');
        $this->assertSame(1, CreditTransaction::where('user_id', $user->id)->where('type', CreditTransaction::TYPE_CARD_REFUND)->count());
        Mail::assertQueued(FarmCardRefund::class, fn (FarmCardRefund $m) => $m->hasTo('host@example.com') && $m->refund->is($refund));
        Mail::assertQueued(FarmOrderStatus::class, fn (FarmOrderStatus $m) => $m->status === FarmOrder::STATUS_FAILED);
        $html = (new FarmOrderStatus($order, FarmOrder::STATUS_FAILED))->render();
        $this->assertStringContainsString(e(__('farm.mail.failed_card.body', ['name' => 'part.stl'])), $html);
        $this->assertStringNotContainsString('Kredit jsme vám vrátili', $html);
        $this->assertStringContainsString(e(__('farm.refund.mail.body', ['amount' => $order->total()->format()])), (new FarmCardRefund($refund))->render());
        $state = $this->asGuest()->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $this->assertEqualsWithDelta($total, $state['refunded'], 0.001);
        $this->assertSame(__('farm.refund.error.operator'), $state['error_text']);
        $event = Event::where('type', 'refund')->sole();
        $this->assertEquals(['order' => $order->id, 'value' => $total, 'currency' => 'CZK', 'kind' => 'print_failed', 'event_id' => 'refund-'.$refund->id], $event->meta);
        $this->assertStringContainsString('refunded '.number_format($total, 2, '.', ''), $order->events()->pluck('note')->implode(' | '));
    }

    public function test_a_cancellation_before_the_print_returns_everything_and_during_it_all_but_the_printed_share(): void
    {
        [$order] = $this->paidGuestOrder();
        $this->asGuest()->postJson("/farm/orders/{$order->token}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertEqualsWithDelta((float) $order->price_total, PaymentRefund::sole()->amount, 0.001);
        $this->assertSame(PaymentRefund::REASON_CANCELLED, PaymentRefund::sole()->reason);
        Mail::assertQueued(FarmOrderStatus::class, fn (FarmOrderStatus $m) => $m->status === FarmOrder::STATUS_CANCELLED);
        $this->assertStringContainsString(e(__('farm.mail.cancelled_card.body', ['name' => 'part.stl'])), (new FarmOrderStatus($order->refresh(), FarmOrder::STATUS_CANCELLED))->render());

        // cancelled while printing: the handling fee and the printed share stay charged, the rest goes to the card
        [$running] = $this->paidGuestOrder('druhy@example.com');
        $this->operator($running, 'printing', 40);
        $this->asGuest()->postJson("/farm/orders/{$running->token}/cancel")->assertOk();
        $running->refresh();
        $kept = (float) $running->price['charged_on_cancel'];
        $this->assertGreaterThan(0, $kept);
        $refund = PaymentRefund::where('farm_order_id', $running->id)->sole();
        $this->assertSame(PaymentRefund::REASON_CANCELLED_RUNNING, $refund->reason);
        $this->assertEqualsWithDelta((float) $running->price_total - $kept, $refund->amount, 0.001);
    }

    public function test_an_order_paid_from_credit_gets_credit_back_and_a_mixed_one_the_card_part_to_the_card(): void
    {
        $user = User::factory()->create(['email' => 'kredit@example.com']);
        $this->actingAs($user)->post('/account/credit', ['amount' => 1000])->assertRedirect();
        $this->bankConfirms(Payment::latest('id')->firstOrFail());
        $order = $this->ownOrder($user);
        $offer = $this->ownOffer($user, $order);
        $this->actingAs($user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $offer['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $offer['total']])->assertOk();
        $this->actingAs($user)->postJson("/farm/orders/{$order->token}/cancel")->assertOk();
        $this->assertSame(0, PaymentRefund::count(), 'credit stays credit');
        $this->assertEqualsWithDelta(1000.0, $this->balance($user), 0.001);

        // 10 Kč of credit, the rest by card: the card part goes to the card, the 10 Kč stay as credit
        $mixed = User::factory()->create(['email' => 'smes@example.com']);
        app(Wallet::class)->adjust($mixed, 10, 'test', $this->admin()->id);
        $order = $this->ownOrder($mixed);
        $offer = $this->ownOffer($mixed, $order);
        $this->actingAs($mixed)->postJson("/farm/orders/{$order->token}/checkout", ['slot' => $offer['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $offer['total']])->assertOk();
        $card = Payment::latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(max(ceil($offer['total'] - 10), 15), $card->amount, 0.001);
        $this->bankConfirms($card);
        $this->actingAs($mixed)->postJson("/farm/orders/{$order->token}/cancel")->assertOk();
        $refund = PaymentRefund::sole();
        $this->assertEqualsWithDelta($card->amount, $refund->amount, 0.001, 'no more than the card paid');
        $this->assertEqualsWithDelta(10.0, $this->balance($mixed), 0.001, 'what was paid from credit is credit again');
    }

    public function test_a_refund_the_gateway_refuses_stays_as_credit_tells_the_operator_and_can_be_done_by_hand(): void
    {
        [$order, $payment, $user] = $this->paidGuestOrder();
        FakeGateway::$refundFails = true;
        $this->asGuest()->postJson("/farm/orders/{$order->token}/cancel")->assertOk();
        $refund = PaymentRefund::sole();
        $this->assertSame(PaymentRefund::STATUS_FAILED, $refund->status);
        $this->assertSame('Fake refund refused.', $refund->error);
        $this->assertEqualsWithDelta($payment->amount, $this->balance($user), 0.001, 'the money waits as credit');
        Mail::assertNotQueued(FarmCardRefund::class);
        $alert = Mail::queued(FarmAdminAlert::class)->first(fn (FarmAdminAlert $m) => str_contains($m->subjectLine, 'vrácení na kartu selhalo'));
        $this->assertNotNull($alert);
        $admin = $this->admin();
        $this->actingAs($admin)->get("/admin/farm/orders/{$order->token}")->assertOk()
            ->assertSee('id="card-refund"', false)->assertSee('Fake refund refused.')->assertSee('data-refund="failed"', false);
        $this->actingAs($admin)->get('/admin/farm')->assertOk()->assertSee($order->number);

        // by hand: the money is in the credit (the order gave it back), so it leaves the credit
        FakeGateway::$refundFails = false;
        $this->actingAs($admin)->post("/admin/farm/orders/{$order->token}/card-refund", ['amount' => $order->price_total, 'note' => 'znovu'])->assertRedirect()->assertSessionHas('status');
        $this->assertSame(PaymentRefund::STATUS_DONE, PaymentRefund::latest('id')->first()->status);
        $this->assertEqualsWithDelta($payment->amount - (float) $order->price_total, $this->balance($user), 0.001);
        $this->assertNull(CardRefunds::failedFor($order));
        // more than the card paid and has not got back is refused
        $this->actingAs($admin)->post("/admin/farm/orders/{$order->token}/card-refund", ['amount' => 1000, 'note' => 'moc'])->assertSessionHasErrors('amount');
    }

    public function test_the_admin_refunds_part_of_a_finished_print_to_the_card(): void
    {
        [$order, , $user] = $this->paidGuestOrder();
        $this->operator($order, 'printing');
        $this->operator($order, 'done');
        $this->assertSame(FarmOrder::STATUS_DONE, $order->refresh()->status);
        $before = $this->balance($user);
        $this->actingAs($this->admin())->post("/admin/farm/orders/{$order->token}/card-refund", ['amount' => 20, 'note' => 'reklamace'])->assertRedirect()->assertSessionHas('status');
        $refund = PaymentRefund::sole();
        $this->assertSame(PaymentRefund::REASON_ADMIN, $refund->reason);
        $this->assertSame('reklamace', $refund->note);
        $this->assertEqualsWithDelta(20.0, $refund->amount, 0.001);
        $this->assertEqualsWithDelta($before, $this->balance($user), 0.001, 'the credit does not move: the order gives it back, the card takes it');
        $this->assertSame(1, CreditTransaction::where('farm_order_id', $order->id)->where('type', CreditTransaction::TYPE_REFUND)->count());
    }

    public function test_switched_off_the_print_fails_at_once_and_the_money_is_credit(): void
    {
        config(['farm.payments.auto_reprint' => false, 'farm.payments.auto_refund' => false]);
        [$order, $payment, $user] = $this->paidGuestOrder();
        $this->operator($order, 'printing');
        $this->operator($order, 'failed');
        $this->assertSame(FarmOrder::STATUS_FAILED, $order->refresh()->status);
        $this->assertSame(0, PaymentRefund::count());
        $this->assertEqualsWithDelta($payment->amount, $this->balance($user), 0.001);
        $this->assertStringContainsString(e(__('farm.mail.failed.body', ['name' => 'part.stl'])), (new FarmOrderStatus($order, FarmOrder::STATUS_FAILED))->render());
    }

    public function test_a_guest_payment_that_cannot_pay_the_order_goes_back_to_the_card(): void
    {
        $order = $this->guestOrder();
        $this->checkout($order, ['email' => 'pozde@example.com'])->assertOk();
        $payment = Payment::latest('id')->firstOrFail();
        // the prices go up while the guest is at the gateway
        $settings = app(FarmSettings::class);
        $settings->set('min_price', (float) $settings->get('min_price') * 3 + 100);
        $settings->set('fixed_fee', (float) $settings->get('fixed_fee') + 100);
        $this->bankConfirms($payment);

        $this->assertSame('sliced', $order->fresh()->status);
        $this->assertSame('price_changed', $payment->fresh()->context['result']);
        $refund = PaymentRefund::sole();
        $this->assertSame(PaymentRefund::REASON_NOT_PAID, $refund->reason);
        $this->assertEqualsWithDelta($payment->amount, $refund->amount, 0.001, 'all of it');
        $this->assertEqualsWithDelta(0.0, $this->balance(User::where('email', 'pozde@example.com')->sole()), 0.001);
        Mail::assertNotQueued(FarmGuestOrder::class);
        Mail::assertQueued(FarmCardRefund::class);
    }

    private function ownOrder(User $user): FarmOrder
    {
        $path = sys_get_temp_dir().'/mp_own_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20.0);
        $uuid = $this->actingAs($user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated()->json('file.uuid');

        return FarmOrder::where('token', basename($this->actingAs($user)->postJson('/farm/orders', ['file' => $uuid])->assertCreated()->json('url')))->firstOrFail();
    }

    /** @return array{slot: int, total: float} */
    private function ownOffer(User $user, FarmOrder $order): array
    {
        $state = $this->actingAs($user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();

        return ['slot' => $state['colors'][0]['slot'], 'total' => (float) $state['colors'][0]['total']];
    }

    // ── §3: measured without an account, limits, the week's clean-up ───────────────────────────────────────────────

    public function test_a_guest_order_is_measured_in_the_guest_s_visit_and_the_account_made_by_the_payment_is_a_checkout_registration(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36');
        [$order, , $user] = $this->paidGuestOrder();
        $created = Event::where('type', 'order_created')->where('meta->order', $order->id)->sole();
        $this->assertNull($created->user_id, 'made by a guest');
        $this->assertNotNull($created->session_id);
        $paid = Event::where('type', 'order_paid')->where('meta->order', $order->id)->sole();
        $this->assertSame($created->session_id, $paid->session_id, 'the payment joins the visit that made the order');
        $this->assertSame('card', $paid->meta['kind']);
        $register = Event::where('type', 'register')->sole();
        $this->assertSame($user->id, $register->user_id);
        $this->assertSame($created->session_id, $register->session_id);
        $this->assertEquals(['kind' => 'checkout', 'order' => $order->id, 'event_id' => 'register-'.$user->id], $register->meta);

        // an e-mail that has an account already is no registration
        User::factory()->create(['email' => 'uz@example.com']);
        $this->paidGuestOrder('uz@example.com');
        $this->assertSame(1, Event::where('type', 'register')->count());
    }

    public function test_a_guest_makes_only_so_many_orders_from_one_address_an_hour(): void
    {
        config(['farm.guest.orders_per_hour_ip' => 2]);
        $this->guestOrder();
        $this->guestOrder();
        $path = sys_get_temp_dir().'/mp_guest_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20.0);
        $this->asGuest()->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated();
        $file = ModelFile::latest('id')->firstOrFail();
        $this->asGuest()->postJson('/farm/orders', ['file' => $file->uuid])->assertStatus(429)->assertJsonPath('error', 'guest_limit');
        $this->assertSame(2, FarmOrder::count());
        // an account is not limited by the guests' count
        $user = User::factory()->create();
        $this->ownOrder($user);
        $this->assertSame(3, FarmOrder::count());
    }

    public function test_a_guest_order_nobody_paid_is_cancelled_after_a_week_and_a_paid_one_keeps_its_file(): void
    {
        [$paid] = $this->paidGuestOrder();
        $unpaid = $this->guestOrder();
        $fresh = $this->guestOrder();
        FarmOrder::whereKey([$paid->id, $unpaid->id])->update(['created_at' => now()->subDays(8)]);
        // the anonymous file of a paid order must survive the anonymous files' clean-up even when nobody owns it
        ModelFile::whereKey($paid->model_file_id)->update(['owner_user_id' => null, 'created_at' => now()->subDays(40)]);

        $this->artisan('matplace:prune')->assertSuccessful();

        $this->assertSame(FarmOrder::STATUS_CANCELLED, $unpaid->refresh()->status);
        $this->assertStringContainsString('guest order not paid within 7 days', $unpaid->events()->pluck('note')->implode(' | '));
        $this->assertSame(FarmOrder::STATUS_SLICED, $fresh->refresh()->status);
        $this->assertNotSame(FarmOrder::STATUS_CANCELLED, $paid->refresh()->status);
        $this->assertNotNull(ModelFile::find($paid->model_file_id));
    }
}
