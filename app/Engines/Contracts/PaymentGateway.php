<?php

namespace App\Engines\Contracts;

use App\Engines\Exceptions\EngineException;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Money in. The application creates a pending Payment, the gateway returns where to send the customer, and later
 * tells us (webhook) which payment was really paid. Nothing is credited on the customer's return alone.
 */
interface PaymentGateway
{
    /** @return string URL of the gateway's payment page; stores the gateway reference on the payment */
    public function checkoutUrl(Payment $payment, string $successUrl, string $cancelUrl): string;

    /**
     * Verify and read a webhook call.
     *
     * @return array{ref: string, paid: bool, expired: bool, raw: array}|null null = not an event about a payment
     *
     * @throws EngineException when the signature does not verify
     */
    public function parseWebhook(Request $request): ?array;

    /**
     * Send money of a paid payment back to the card it came from (part of it or all).
     *
     * @param  string  $reason  ours, for the gateway's records (PaymentRefund::REASON_*)
     * @return string the gateway's id of the refund
     *
     * @throws EngineException when the gateway refuses or cannot be reached
     */
    public function refund(Payment $payment, float $amount, string $reason): string;

    public function name(): string;
}
