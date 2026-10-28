<?php

namespace App\Domain\Farm;

use App\Engines\Contracts\PaymentGateway;
use App\Jobs\RefundToCard;
use App\Mail\FarmCardRefund;
use App\Models\CreditTransaction;
use App\Models\FarmOrder;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Support\Money;
use App\Support\Track;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Money of an order paid by card goes back to the card (docs/AO.md §2). A card payment for an order is credited and
 * held like any credit (Wallet), so the ledger says where the money is:
 *
 *  - from credit: the order already gave its money back (Wallet::giveBack after a failure or a cancellation, or a
 *    guest's payment that could not pay the order): the card refund takes it out of the credit again (one
 *    `card_refund` line), as far as the card paid for the order; what was paid from credit stays credit;
 *  - for the order: the money is still held or captured (an admin refunds a finished print): a `refund` line on the
 *    order and a `card_refund` line, so the balance does not move and the order holds that much less.
 *
 * The gateway is asked in a job after the commit (RefundToCard); only a refund the gateway took is written into the
 * ledger. A refused one stays as credit (or as it was), the order's history and the operator's mail say so, and the
 * order is on the admin's "Čeká na vás" list until somebody acts.
 */
final class CardRefunds
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /** The paid card payments made for this order from its page, newest first. */
    public function payments(FarmOrder $order)
    {
        return Payment::where('farm_order_id', $order->id)->where('purpose', Payment::PURPOSE_ORDER)->where('status', Payment::STATUS_PAID)->latest('id')->get();
    }

    /** Card money of the order that has not gone back yet (refunds waiting at the gateway count as gone). */
    public function refundable(FarmOrder $order): float
    {
        return round($this->payments($order)->sum(fn (Payment $p) => $this->leftOf($p)), 2);
    }

    /** What the gateway has given back for this order. */
    public function refunded(FarmOrder $order): float
    {
        return round((float) PaymentRefund::where('farm_order_id', $order->id)->where('status', PaymentRefund::STATUS_DONE)->sum('amount'), 2);
    }

    /** The order was paid (at least partly) by card from its page and its money goes back there: what the customer's mails say. */
    public function goesToCard(FarmOrder $order): bool
    {
        return (bool) config('farm.payments.auto_refund', true) && $this->payments($order)->isNotEmpty();
    }

    /**
     * The order's error for the customer: an order whose money goes back to the card says so (`farm.refund.error.<code>`,
     * "Peníze vracíme na kartu") where the old text says "Kredit jsme vám vrátili".
     */
    public static function errorText(FarmOrder $order, array $data = []): string
    {
        $code = (string) $order->error;
        $card = 'farm.refund.error.'.$code;

        return __(Lang::has($card) && app(self::class)->goesToCard($order) ? $card : 'farm.error.'.$code, $data);
    }

    /** A refund the gateway refused and nobody has done since: the operator must act (the order's attention). */
    public static function failedFor(FarmOrder $order): ?PaymentRefund
    {
        $failed = PaymentRefund::where('farm_order_id', $order->id)->where('status', PaymentRefund::STATUS_FAILED)->latest('id')->first();

        return $failed && ! PaymentRefund::where('farm_order_id', $order->id)->where('status', PaymentRefund::STATUS_DONE)->where('id', '>', $failed->id)->exists() ? $failed : null;
    }

    /**
     * The order has just given `$back` back to the customer's credit (OrderFlow::onStopped): the card part of it goes
     * on to the card. Nothing when it is switched off, nothing for an order paid from credit only.
     *
     * @return float what goes to the card
     */
    public function afterGiveBack(FarmOrder $order, float $back, string $reason): float
    {
        if (! config('farm.payments.auto_refund', true) || $back <= 0.004) {
            return 0.0;
        }

        return $this->start($order, $back, $reason, fromCredit: true);
    }

    /**
     * A guest's card payment arrived but could not pay its order (the price moved, the spool is gone): the guest never
     * wanted credit, so the whole payment goes back.
     */
    public function notPaid(Payment $payment): float
    {
        if (! config('farm.payments.auto_refund', true) || ! $payment->order) {
            return 0.0;
        }

        return $this->start($payment->order, $this->leftOf($payment), PaymentRefund::REASON_NOT_PAID, fromCredit: true, only: $payment);
    }

    /**
     * "Vrátit na kartu" on the admin's order page. Where the money is decides the ledger: the order gave it back to the
     * credit already (failed, cancelled: nothing of it is held any more) → from credit; else for the order.
     *
     * @return float what goes to the card (capped by what the card paid and has not got back)
     */
    public function byAdmin(FarmOrder $order, float $amount, string $note, int $adminId): float
    {
        $held = round(-(float) CreditTransaction::where('farm_order_id', $order->id)->where('user_id', $order->user_id)->sum('amount'), 2);

        return $this->start($order, $amount, PaymentRefund::REASON_ADMIN, fromCredit: $held <= 0.004, note: $note, adminId: $adminId);
    }

    /**
     * Writes the refunds (newest payment first) and hands them to the gateway job.
     *
     * @return float the amount on its way
     */
    private function start(FarmOrder $order, float $amount, string $reason, bool $fromCredit, ?string $note = null, ?int $adminId = null, ?Payment $only = null): float
    {
        $left = round(min($amount, $only ? $this->leftOf($only) : $this->refundable($order)), 2);
        if ($fromCredit && $order->user_id) {
            // the money must still be in the credit (spent meanwhile on another order: what is left of it)
            $left = round(min($left, max(0.0, (float) CreditTransaction::where('user_id', $order->user_id)->sum('amount'))), 2);
        }
        if ($left <= 0.004) {
            return 0.0;
        }
        $total = $left;
        foreach ($only ? [$only] : $this->payments($order) as $payment) {
            $take = round(min($left, $this->leftOf($payment)), 2);
            if ($take <= 0.004) {
                continue;
            }
            $refund = PaymentRefund::create([
                'payment_id' => $payment->id, 'farm_order_id' => $order->id, 'amount' => $take, 'currency' => $payment->currency,
                'reason' => $reason, 'note' => $note ? mb_substr($note, 0, 300) : null, 'status' => PaymentRefund::STATUS_PENDING, 'created_by' => $adminId,
            ]);
            RefundToCard::dispatch($refund->id, $fromCredit)->afterCommit();
            $left = round($left - $take, 2);
            if ($left <= 0.004) {
                break;
            }
        }

        return round($total - max(0.0, $left), 2);
    }

    /**
     * The gateway's part (RefundToCard): ask it, then write the ledger, the order's history, the customer's mail and
     * the statistics; a refusal is written down and told to the operator. Runs once per refund.
     */
    public function run(PaymentRefund $refund, bool $fromCredit): void
    {
        if ($refund->status !== PaymentRefund::STATUS_PENDING) {
            return;
        }
        $payment = $refund->payment;
        $order = $refund->order;
        try {
            $ref = $this->gateway->refund($payment, (float) $refund->amount, $refund->reason);
        } catch (\Throwable $e) {
            $refund->update(['status' => PaymentRefund::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 300)]);
            Log::error('Card refund failed', ['refund' => $refund->id, 'payment' => $payment->id, 'error' => $e->getMessage()]);
            if ($order) {
                $order->events()->create(['from' => $order->status, 'to' => $order->status, 'actor' => 'system', 'note' => mb_substr("card refund #{$refund->id} of ".number_format((float) $refund->amount, 2, '.', '')." {$refund->currency} failed: ".$e->getMessage(), 0, 500)]);
                $order->touch();   // back on the admin's "Čeká na vás" list
                $flow = app(OrderFlow::class);
                $flow->alertAdmin(self::t('refund_failed', ['number' => $order->number ?: $order->token]), $order, [
                    ...$flow->orderLines($order),
                    self::t('line_refund_amount', ['amount' => Money::of((float) $refund->amount, $refund->currency)->format()]),
                    self::t('line_what', ['what' => $e->getMessage()]),
                    self::t($fromCredit ? 'line_refund_failed_credit' : 'line_refund_failed_order'),
                ]);
            }

            return;
        }
        DB::transaction(function () use ($refund, $ref, $payment, $order, $fromCredit) {
            $refund->update(['status' => PaymentRefund::STATUS_DONE, 'gateway_ref' => $ref]);
            if (! $payment->user_id) {
                return;   // a guest's payment that never reached an account: nothing was credited
            }
            User::whereKey($payment->user_id)->lockForUpdate()->first();
            $number = $order?->number ?: $order?->token;
            if (! $fromCredit) {
                // the order still held (or had captured) the money: it gives that much back first
                CreditTransaction::create(['user_id' => $payment->user_id, 'type' => CreditTransaction::TYPE_REFUND, 'amount' => $refund->amount,
                    'currency' => $refund->currency, 'farm_order_id' => $order?->id, 'note' => 'card refund #'.$refund->id, 'created_by' => $refund->created_by]);
            }
            // out of the credit, to the card; on no order (the order's own lines stay what it held and returned)
            CreditTransaction::create(['user_id' => $payment->user_id, 'type' => CreditTransaction::TYPE_CARD_REFUND, 'amount' => -$refund->amount,
                'currency' => $refund->currency, 'note' => trim('#'.$refund->id.' '.$number), 'created_by' => $refund->created_by]);
            $order?->events()->create(['from' => $order->status, 'to' => $order->status, 'actor' => 'system', 'note' => 'refunded '.number_format((float) $refund->amount, 2, '.', '')." {$refund->currency} to the card ({$refund->reason}, {$ref})"]);
        });
        Track::refunded($refund);
        $user = $payment->user;
        if ($order && $user?->email && $user->notify_email !== false) {
            try {
                Mail::to($user->email)->locale($user->locale ?: config('app.locale'))->queue(new FarmCardRefund($refund));
            } catch (\Throwable $e) {
                Log::warning('Card refund mail failed', ['refund' => $refund->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /** What of one payment has not gone back (done or on its way). */
    private function leftOf(Payment $payment): float
    {
        return round(max(0.0, (float) $payment->amount - (float) $payment->refunds()->whereIn('status', [PaymentRefund::STATUS_PENDING, PaymentRefund::STATUS_DONE])->sum('amount')), 2);
    }

    /** The operator reads Czech. */
    private static function t(string $key, array $replace = []): string
    {
        return Lang::get('farm.refund.admin.'.$key, $replace, 'cs');
    }
}
