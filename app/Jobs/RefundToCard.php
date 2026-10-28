<?php

namespace App\Jobs;

use App\Domain\Farm\CardRefunds;
use App\Models\PaymentRefund;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Money back to the card at the gateway, after the commit that decided it (App\Domain\Farm\CardRefunds::run). Tried
 * once: a refusal is written down and the operator decides (a second try could pay the money out twice).
 */
class RefundToCard implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    /** @param bool $fromCredit the money sits in the customer's credit (the order already gave it back) */
    public function __construct(public int $refundId, public bool $fromCredit) {}

    public function handle(CardRefunds $refunds): void
    {
        $refund = PaymentRefund::with(['payment.user', 'order'])->find($this->refundId);
        if ($refund) {
            $refunds->run($refund, $this->fromCredit);
        }
    }
}
