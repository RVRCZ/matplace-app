<?php

namespace App\Domain\Farm;

use App\Models\CreditTransaction;
use App\Models\FarmOrder;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Prepaid credit. The ledger is append-only and the balance is its sum, so every crown can be traced.
 * Spending locks the user row first: two simultaneous orders can never both pass the balance check.
 */
final class Wallet
{
    public function balance(User $user): float
    {
        return round((float) CreditTransaction::where('user_id', $user->id)->sum('amount'), 2);
    }

    /** Credit a paid top-up. Safe to call twice for the same payment (webhook retries): the second call does nothing. */
    public function topUp(Payment $payment): bool
    {
        try {
            CreditTransaction::create([
                'user_id' => $payment->user_id, 'type' => CreditTransaction::TYPE_TOPUP, 'amount' => $payment->amount,
                'currency' => $payment->currency, 'payment_id' => $payment->id, 'note' => $payment->gateway.' '.$payment->gateway_ref,
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    /** @throws InsufficientCredit */
    public function hold(FarmOrder $order): CreditTransaction
    {
        return DB::transaction(function () use ($order) {
            $user = User::whereKey($order->user_id)->lockForUpdate()->firstOrFail();
            if ($this->openHold($order) !== null) {
                throw new \LogicException('Order already holds credit.');
            }
            $price = (float) $order->price_total;
            $balance = $this->balance($user);
            if ($balance + 1e-6 < $price) {
                throw new InsufficientCredit($balance, $price);
            }

            return CreditTransaction::create([
                'user_id' => $user->id, 'type' => CreditTransaction::TYPE_HOLD, 'amount' => -$price,
                'currency' => $order->currency, 'farm_order_id' => $order->id,
            ]);
        });
    }

    /** The print is finished: the held amount is spent for good. */
    public function capture(FarmOrder $order): void
    {
        DB::transaction(function () use ($order) {
            if ($this->openHold($order) === null) {
                return;
            }
            CreditTransaction::create([
                'user_id' => $order->user_id, 'type' => CreditTransaction::TYPE_CAPTURE, 'amount' => 0,
                'currency' => $order->currency, 'farm_order_id' => $order->id, 'note' => (string) $order->price_total,
            ]);
        });
    }

    /** Give the money back: a release while it was only held, a refund once it had been captured. Returns the amount. */
    public function giveBack(FarmOrder $order, ?int $adminId = null, ?string $note = null): float
    {
        return DB::transaction(function () use ($order, $adminId, $note) {
            User::whereKey($order->user_id)->lockForUpdate()->first();
            $net = round((float) CreditTransaction::where('farm_order_id', $order->id)->sum('amount'), 2);
            if ($net >= 0) {
                return 0.0;   // nothing held or already returned
            }
            $captured = CreditTransaction::where('farm_order_id', $order->id)->where('type', CreditTransaction::TYPE_CAPTURE)->exists();
            CreditTransaction::create([
                'user_id' => $order->user_id, 'type' => $captured ? CreditTransaction::TYPE_REFUND : CreditTransaction::TYPE_RELEASE,
                'amount' => -$net, 'currency' => $order->currency, 'farm_order_id' => $order->id, 'note' => $note, 'created_by' => $adminId,
            ]);

            return -$net;
        });
    }

    public function adjust(User $user, float $amount, string $note, int $adminId): CreditTransaction
    {
        return CreditTransaction::create([
            'user_id' => $user->id, 'type' => CreditTransaction::TYPE_ADJUST, 'amount' => round($amount, 2),
            'currency' => app(FarmSettings::class)->get('currency'), 'note' => $note, 'created_by' => $adminId,
        ]);
    }

    /** The hold of this order that has been neither captured nor returned. */
    private function openHold(FarmOrder $order): ?CreditTransaction
    {
        $rows = CreditTransaction::where('farm_order_id', $order->id)->get();
        if (round((float) $rows->sum('amount'), 2) >= 0 || $rows->contains('type', CreditTransaction::TYPE_CAPTURE)) {
            return null;
        }

        return $rows->where('type', CreditTransaction::TYPE_HOLD)->last();
    }
}
