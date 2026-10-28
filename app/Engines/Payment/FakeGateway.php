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
    /** Tests: the next refunds are refused, as a gateway would (an expired card, a payment refunded already). */
    public static bool $refundFails = false;

    /** Tests: what was refunded, [payment id, amount, reason] each. */
    public static array $refunds = [];

    public function name(): string
    {
        return 'fake';
    }

    public function checkoutUrl(Payment $payment, string $successUrl, string $cancelUrl): string
    {
        $payment->update(['gateway_ref' => 'fake_'.$payment->id]);

        return $successUrl;
    }

    public function refund(Payment $payment, float $amount, string $reason): string
    {
        if (self::$refundFails) {
            throw new EngineException('Fake refund refused.');
        }
        self::$refunds[] = [$payment->id, $amount, $reason];

        return 'fake_refund_'.$payment->id.'_'.count(self::$refunds);
    }

    public function parseWebhook(Request $request): ?array
    {
        if ($request->header('X-Fake-Signature') !== 'fake') {
            throw new EngineException('Fake webhook signature invalid.');
        }

        return ['ref' => (string) $request->input('ref'), 'paid' => (bool) $request->input('paid', true), 'expired' => (bool) $request->input('expired', false), 'raw' => $request->all()];
    }
}
