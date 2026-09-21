<?php

namespace App\Engines\Payment;

use App\Engines\Contracts\PaymentGateway;
use App\Engines\Exceptions\EngineException;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Tests and local development without Stripe keys. The "payment page" is our own success URL and the webhook is a
 * plain JSON post {"ref": "...", "paid": true} signed with the shared secret "fake".
 */
final class FakeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function checkoutUrl(Payment $payment, string $successUrl, string $cancelUrl): string
    {
        $payment->update(['gateway_ref' => 'fake_'.$payment->id]);

        return $successUrl;
    }

    public function parseWebhook(Request $request): ?array
    {
        if ($request->header('X-Fake-Signature') !== 'fake') {
            throw new EngineException('Fake webhook signature invalid.');
        }

        return ['ref' => (string) $request->input('ref'), 'paid' => (bool) $request->input('paid', true), 'expired' => (bool) $request->input('expired', false), 'raw' => $request->all()];
    }
}
