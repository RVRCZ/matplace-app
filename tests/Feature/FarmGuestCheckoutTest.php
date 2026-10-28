<?php

namespace Tests\Feature;

use App\Domain\Farm\GuestCheckout;
use App\Domain\Farm\Wallet;
use App\Mail\FarmGuestOrder;
use App\Models\AnonymousSession;
use App\Models\FarmOrder;
use App\Models\ModelFile;
use App\Models\Payment;
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
 * guest gets the confirmation with the links. Fake slicer, fake gateway, sync queue.
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
        config(['farm.public' => true]);
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
}
