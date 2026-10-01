<?php

namespace App\Domain\Farm;

use App\Models\CreditTransaction;
use App\Models\FarmOrder;
use App\Models\Payment;
use App\Models\User;
use App\Support\Currency;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Prepaid credit. The ledger is append-only and the balance is its sum, so every crown can be traced.
 * Spending locks the user row first: two simultaneous orders can never both pass the balance check.
 *
 * An account has one currency. The first line written to its ledger fixes it (a top-up paid in that currency, a
 * designer's first reward); a line in any other currency is refused (CreditTransaction, CurrencyMismatch).
 */
final class Wallet
{
    public function balance(User $user): Money
    {
        return new Money((float) CreditTransaction::where('user_id', $user->id)->sum('amount'), $user->currency ?: Currency::current($user));
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
            $price = new Money((float) $order->price_total, (string) $order->currency);
            // an account nothing has moved on yet has nothing to pay with, in whatever currency the order is
            $balance = $user->currency ? $this->balance($user) : new Money(0, $price->currency);
            if (! $balance->covers($price)) {
                throw new InsufficientCredit($balance->amount, $price->amount, $price->currency);
            }

            return CreditTransaction::create([
                'user_id' => $user->id, 'type' => CreditTransaction::TYPE_HOLD, 'amount' => -$price->amount,
                'currency' => $price->currency, 'farm_order_id' => $order->id,
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

    /**
     * Give the money back: a release while it was only held, a refund once it had been captured. Returns the amount.
     * $keep is the part that stays charged (a print stopped half-way): it is captured, the rest goes back.
     */
    public function giveBack(FarmOrder $order, ?int $adminId = null, ?string $note = null, float $keep = 0.0): float
    {
        return DB::transaction(function () use ($order, $adminId, $note, $keep) {
            User::whereKey($order->user_id)->lockForUpdate()->first();
            // the customer's own lines only: the designer's reward for the same order sits on another account
            $net = round((float) CreditTransaction::where('farm_order_id', $order->id)->where('user_id', $order->user_id)->sum('amount'), 2);
            if ($net >= 0) {
                return 0.0;   // nothing held or already returned
            }
            $captured = CreditTransaction::where('farm_order_id', $order->id)->where('user_id', $order->user_id)->where('type', CreditTransaction::TYPE_CAPTURE)->exists();
            $keep = round(min(max($keep, 0.0), -$net), 2);
            if ($keep > 0 && ! $captured) {
                CreditTransaction::create([
                    'user_id' => $order->user_id, 'type' => CreditTransaction::TYPE_CAPTURE, 'amount' => 0,
                    'currency' => $order->currency, 'farm_order_id' => $order->id, 'note' => (string) $keep, 'created_by' => $adminId,
                ]);
                $captured = true;
            }
            $back = round(-$net - $keep, 2);
            if ($back <= 0) {
                return 0.0;
            }
            CreditTransaction::create([
                'user_id' => $order->user_id, 'type' => $captured ? CreditTransaction::TYPE_REFUND : CreditTransaction::TYPE_RELEASE,
                'amount' => $back, 'currency' => $order->currency, 'farm_order_id' => $order->id, 'note' => $note, 'created_by' => $adminId,
            ]);
            // money returned for a finished print: the designer's reward for it goes back as well
            if ($captured) {
                $this->reverseRoyalty($order, $adminId);
            }

            return $back;
        });
    }

    /**
     * Pay for something that is delivered at once (a generation beyond the free quota): one capture line, no hold.
     *
     * @param  float  $czk  the price as it is defined, in crowns; an account in euros pays its euro price
     *
     * @throws InsufficientCredit
     */
    public function charge(User $user, float $czk, string $note): CreditTransaction
    {
        return DB::transaction(function () use ($user, $czk, $note) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $balance = $this->balance($user);
            $price = Money::czk($czk)->to($balance->currency);
            if (! $balance->covers($price)) {
                throw new InsufficientCredit($balance->amount, $price->amount, $price->currency);
            }

            return CreditTransaction::create([
                'user_id' => $user->id, 'type' => CreditTransaction::TYPE_CHARGE, 'amount' => -$price->amount,
                'currency' => $price->currency, 'note' => $note,
            ]);
        });
    }

    /**
     * The print is done: the designer of the printed card gets the reward frozen on the order (per piece × pieces),
     * all of it, in the currency of their account. Once per order; nothing for one's own model.
     */
    public function creditRoyalty(FarmOrder $order): ?CreditTransaction
    {
        $card = $order->designer_model_id ? $order->designerModel : null;
        $designer = $card?->profile?->user;
        $unit = (float) $order->royalty_czk;
        if (! $card || ! $designer || $unit <= 0 || $designer->id === $order->user_id || $designer->isAnonymized()) {
            return null;
        }

        return DB::transaction(function () use ($order, $card, $designer, $unit) {
            $designer = User::whereKey($designer->id)->lockForUpdate()->firstOrFail();
            if (CreditTransaction::where('farm_order_id', $order->id)->where('type', CreditTransaction::TYPE_ROYALTY)->exists()) {
                return null;
            }
            $currency = $this->currencyOf($designer);
            $czk = round($unit * max(1, (int) $order->copies), 2);
            $card->increment('order_count');

            return CreditTransaction::create([
                'user_id' => $designer->id, 'type' => CreditTransaction::TYPE_ROYALTY,
                // all of it, to the cent: nothing is rounded away from the designer
                'amount' => Money::czk($czk)->exactly($currency)->amount,
                'currency' => $currency, 'farm_order_id' => $order->id, 'designer_model_id' => $card->id, 'note' => $order->number,
            ]);
        });
    }

    /** Take a credited reward back (the print was refunded after it was done). Once; nothing when none was credited. */
    public function reverseRoyalty(FarmOrder $order, ?int $adminId = null): ?CreditTransaction
    {
        $credited = CreditTransaction::where('farm_order_id', $order->id)->where('type', CreditTransaction::TYPE_ROYALTY)->first();
        if (! $credited || CreditTransaction::where('farm_order_id', $order->id)->where('type', CreditTransaction::TYPE_ROYALTY_REVERSAL)->exists()) {
            return null;
        }

        return CreditTransaction::create([
            'user_id' => $credited->user_id, 'type' => CreditTransaction::TYPE_ROYALTY_REVERSAL, 'amount' => -$credited->amount,
            'currency' => $credited->currency, 'farm_order_id' => $order->id, 'designer_model_id' => $credited->designer_model_id,
            'note' => $order->number, 'created_by' => $adminId,
        ]);
    }

    /**
     * The currency of an account. It is fixed by the first money that moves on it: what the ledger already holds,
     * else (a designer's first reward) the country of the account: Czechia = CZK, anywhere else = EUR.
     */
    public function currencyOf(User $user): string
    {
        if (! $user->currency) {
            $used = CreditTransaction::where('user_id', $user->id)->latest('id')->value('currency');
            $user->forceFill(['currency' => $used ?: Currency::forCountry($user->country)])->save();
        }

        return (string) $user->currency;
    }

    /**
     * Another currency for an account, by an admin, and only while the account holds nothing: with money on it the
     * amount would have to be exchanged, and that is not ours to decide.
     *
     * @throws \DomainException when credit is left or an order holds some
     */
    public function changeCurrency(User $user, string $currency): void
    {
        $currency = (new Money(0, $currency))->currency;
        DB::transaction(function () use ($user, $currency) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $open = FarmOrder::where('user_id', $user->id)->whereIn('status', [FarmOrder::STATUS_PAID, FarmOrder::STATUS_QUEUED, FarmOrder::STATUS_PRINTING])->exists();
            if (! $this->balance($user)->isZero() || $open) {
                throw new \DomainException('The account still holds credit or a running order.');
            }
            $user->forceFill(['currency' => $currency])->save();
        });
    }

    /** The owner deletes the account and gives up what is left: one line, so the ledger still explains the zero. */
    public function forfeit(User $user, float $amount, string $note): CreditTransaction
    {
        return CreditTransaction::create([
            'user_id' => $user->id, 'type' => CreditTransaction::TYPE_FORFEIT, 'amount' => -round($amount, 2),
            'currency' => $this->currencyOf($user), 'note' => $note,
        ]);
    }

    /** By an admin, in the currency of the account. */
    public function adjust(User $user, float $amount, string $note, int $adminId): CreditTransaction
    {
        return CreditTransaction::create([
            'user_id' => $user->id, 'type' => CreditTransaction::TYPE_ADJUST, 'amount' => round($amount, 2),
            'currency' => $this->currencyOf($user), 'note' => $note, 'created_by' => $adminId,
        ]);
    }

    /** The hold of this order that has been neither captured nor returned. */
    private function openHold(FarmOrder $order): ?CreditTransaction
    {
        $rows = CreditTransaction::where('farm_order_id', $order->id)->where('user_id', $order->user_id)->get();
        if (round((float) $rows->sum('amount'), 2) >= 0 || $rows->contains('type', CreditTransaction::TYPE_CAPTURE)) {
            return null;
        }

        return $rows->where('type', CreditTransaction::TYPE_HOLD)->last();
    }
}
