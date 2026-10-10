<?php

namespace Tests\Feature;

use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\Wallet;
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
 * "Pay by card" from the order's page: what is missing is paid at the gateway, the webhook credits it and pays the
 * order with the choices made on the page - no top-up page in between. Fake slicer, fake gateway, sync queue.
 */
class FarmCardPaymentTest extends TestCase
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
        $path = sys_get_temp_dir().'/mp_card_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20.0);
        $uuid = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated()->json('file.uuid');
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $uuid])->assertCreated();

        return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
    }

    /** @return array{slot: int, total: float} */
    private function offer(FarmOrder $order): array
    {
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $this->assertSame('sliced', $state['status']);

        return ['slot' => $state['colors'][0]['slot'], 'total' => (float) $state['colors'][0]['total']];
    }

    private function checkout(FarmOrder $order, array $over = [])
    {
        $offer = $this->offer($order);

        return $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/checkout", $over + ['slot' => $offer['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $offer['total'], 'note' => 'na poličku']);
    }

    private function bankConfirms(Payment $payment): void
    {
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
    }

    public function test_an_account_without_credit_pays_the_order_by_card_and_the_webhook_starts_it(): void
    {
        $order = $this->order();
        $total = $this->offer($order)['total'];

        $r = $this->checkout($order)->assertOk();
        $payment = Payment::latest('id')->firstOrFail();
        $this->assertSame(Payment::PURPOSE_ORDER, $payment->purpose);
        $this->assertSame($order->id, $payment->farm_order_id);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame(ceil($total), $payment->amount, 'the whole crowns that are missing');
        $this->assertSame('pickup', $payment->context['delivery']);
        $this->assertSame('na poličku', $payment->context['note']);
        $this->assertStringContainsString('paid='.$payment->id, $r->json('checkout_url'), 'the gateway sends the customer back to the order');
        $this->assertSame('sliced', $order->fresh()->status, 'nothing is paid on the customer\'s return alone');
        $this->assertSame(0.0, app(Wallet::class)->balance($this->user)->amount);

        // back from the gateway: the page says the payment is being processed
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $this->assertSame(['status' => 'pending', 'result' => null], $state['card']);

        $this->bankConfirms($payment);
        $order->refresh();
        $this->assertContains($order->status, ['paid', 'queued'], 'the webhook credited the money and paid the order with the choices of the page');
        $this->assertNotNull($order->number);
        $this->assertSame('na poličku', $order->note);
        $this->assertEqualsWithDelta(ceil($total) - $total, app(Wallet::class)->balance($this->user->fresh())->amount, 0.001, 'the odd heller stays as credit');
        $this->assertSame('paid', $payment->fresh()->context['result']);
        $state = $this->actingAs($this->user->fresh())->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $this->assertSame(['status' => 'paid', 'result' => 'paid'], $state['card']);

        // the gateway says it twice: nothing is paid twice
        $this->bankConfirms($payment);
        $this->assertEqualsWithDelta(ceil($total) - $total, app(Wallet::class)->balance($this->user->fresh())->amount, 0.001);
        $this->assertSame(1, FarmOrder::whereNotNull('number')->count());
    }

    public function test_a_payment_that_arrives_after_the_price_moved_stays_as_credit_and_the_page_says_so(): void
    {
        $order = $this->order();
        $this->checkout($order)->assertOk();
        $payment = Payment::latest('id')->firstOrFail();

        // the prices go up while the customer is at the gateway (a small cube costs the minimum price, so that one moves too)
        $settings = app(FarmSettings::class);
        $settings->set('min_price', (float) $settings->get('min_price') * 3 + 100);
        $settings->set('fixed_fee', (float) $settings->get('fixed_fee') + 100);
        $this->bankConfirms($payment);

        $this->assertSame('sliced', $order->fresh()->status, 'the order was not paid at a price the customer never saw');
        $this->assertSame($payment->amount, app(Wallet::class)->balance($this->user->fresh())->amount, 'the money is credit now');
        $this->assertSame('price_changed', $payment->fresh()->context['result']);
        $state = $this->actingAs($this->user->fresh())->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $this->assertSame(['status' => 'paid', 'result' => 'price_changed'], $state['card']);
        $this->assertGreaterThan(0, $state['balance']);
    }

    public function test_with_enough_credit_the_card_button_simply_pays_from_credit(): void
    {
        $this->actingAs($this->user)->post('/account/credit', ['amount' => 1000])->assertRedirect();
        $this->bankConfirms(Payment::latest('id')->firstOrFail());
        $this->user->refresh();

        $order = $this->order();
        $r = $this->checkout($order)->assertOk();
        $this->assertNull($r->json('checkout_url'));
        $this->assertContains($r->json('status'), ['paid', 'queued']);
        $this->assertSame(0, Payment::where('purpose', Payment::PURPOSE_ORDER)->count(), 'no payment at the gateway was needed');
    }

    public function test_the_choices_are_checked_before_the_customer_is_sent_to_the_gateway(): void
    {
        $order = $this->order();
        $this->checkout($order, ['terms' => false])->assertStatus(422);
        $this->checkout($order, ['expected_total' => 1.0])->assertStatus(422)->assertJsonPath('error', 'price_changed');
        $this->assertSame(0, Payment::count());
        // another customer's order does not even exist for them
        $other = User::factory()->create();
        $this->actingAs($other)->postJson("/farm/orders/{$order->token}/checkout", ['slot' => 1, 'delivery' => 'pickup', 'terms' => true, 'expected_total' => 10])->assertNotFound();
    }
}
