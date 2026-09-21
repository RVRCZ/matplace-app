<?php

namespace App\Engines\Payment;

use App\Engines\Contracts\PaymentGateway;
use App\Engines\Exceptions\EngineException;
use App\Models\Payment;
use Illuminate\Http\Request;
use Stripe\StripeClient;
use Stripe\Webhook;

/** Stripe Checkout (hosted page). The same Stripe account as the legacy site, with its own webhook endpoint and secret. */
final class StripeGateway implements PaymentGateway
{
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'stripe';
    }

    public function checkoutUrl(Payment $payment, string $successUrl, string $cancelUrl): string
    {
        if (empty($this->config['secret'])) {
            throw new EngineException('Stripe is not configured (STRIPE_SECRET_KEY).');
        }
        $session = (new StripeClient($this->config['secret']))->checkout->sessions->create([
            'mode' => 'payment',
            'client_reference_id' => (string) $payment->id,
            'customer_email' => $payment->user->email,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($payment->currency),
                    'unit_amount' => (int) round($payment->amount * 100),
                    'product_data' => ['name' => __('farm.credit.stripe_item')],
                ],
            ]],
            'metadata' => ['payment_id' => (string) $payment->id, 'purpose' => $payment->purpose],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);
        $payment->update(['gateway_ref' => $session->id]);

        return (string) $session->url;
    }

    public function parseWebhook(Request $request): ?array
    {
        try {
            $event = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), (string) ($this->config['webhook_secret'] ?? ''));
        } catch (\Throwable $e) {
            throw new EngineException('Stripe webhook signature invalid: '.$e->getMessage());
        }
        if (! in_array($event->type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.expired'], true)) {
            return null;
        }
        $session = $event->data->object;

        return [
            'ref' => (string) $session->id,
            'paid' => $event->type !== 'checkout.session.expired' && ($session->payment_status ?? null) === 'paid',
            'expired' => $event->type === 'checkout.session.expired',
            'raw' => ['event' => $event->id, 'type' => $event->type, 'amount_total' => $session->amount_total ?? null, 'currency' => $session->currency ?? null],
        ];
    }
}
