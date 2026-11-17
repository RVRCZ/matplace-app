<?php

namespace App\Domain\Affiliate;

use App\Domain\Farm\FarmSettings;
use App\Models\CreditTransaction;
use App\Models\FarmOrder;
use App\Models\Partner;
use App\Models\PartnerClick;
use App\Models\PartnerCommission;
use App\Support\Bots;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The partner programme (docs/AT.md §1). A partner shares /r/CODE; the click is remembered in the cookie `aff` for the
 * partner's own number of days; an order made with the cookie names the partner (the last click wins; a code that
 * belongs to a partner wins over the cookie); when the order is PAID a commission is written, once, from what the
 * customer paid net of VAT (print after the discount, plus delivery; not the designer's reward, which is the
 * designer's); it becomes payable when the print is done (the moment the customer's money is final and the designer
 * is credited); the operator marks it paid out by hand. Nothing here ever pays anybody.
 *
 * Nothing in here may break a payment: the callers from the payment path go through `safely()`.
 */
final class Affiliates
{
    public const COOKIE = 'aff';

    public function __construct(private readonly FarmSettings $settings) {}

    /** The switch (farm settings `affiliate_enabled`, off by default). */
    public function enabled(): bool
    {
        return (bool) $this->settings->get('affiliate_enabled');
    }

    public function defaultPercent(): float
    {
        return (float) $this->settings->get('affiliate_percent');
    }

    public function defaultDays(): int
    {
        return max(1, (int) $this->settings->get('affiliate_cookie_days'));
    }

    // ── the payout rule (Roman, 17 Nov 2026): monthly, once the payable sum reaches a minimum, smaller sums carried over ──

    public function payoutMinCzk(): float
    {
        return max(0.0, (float) $this->settings->get('affiliate_payout_min_czk'));
    }

    public function payoutDay(): int
    {
        return max(1, min(28, (int) $this->settings->get('affiliate_payout_day')));
    }

    /** The next payout day: this month's when it has not passed (today counts), else next month's. */
    public function nextPayout(?CarbonInterface $from = null): CarbonInterface
    {
        $from = ($from ?? now())->copy()->startOfDay();
        $this_month = $from->copy()->day($this->payoutDay());

        return $this_month->gte($from) ? $this_month : $from->copy()->startOfMonth()->addMonthNoOverflow()->day($this->payoutDay());
    }

    /**
     * The payable sums of a partner measured against the minimum: each currency is paid in its own, but whether the
     * partner has reached the minimum is decided on the sum of all of them in crowns (euros at the farm's rate).
     *
     * @param  array<string, float>  $payable  currency → amount
     * @return array{czk: float, due: bool, missing_czk: float}
     */
    public function dueState(array $payable): array
    {
        $czk = round(array_sum(array_map(fn ($amount, $cur) => $cur === 'CZK' ? (float) $amount : (float) $amount * Money::rate(), $payable, array_keys($payable))), 2);
        $min = $this->payoutMinCzk();

        return ['czk' => $czk, 'due' => $czk > 0 && $czk + 0.004 >= $min, 'missing_czk' => max(0.0, round($min - $czk, 2))];
    }

    // ── the link ────────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * The partner a click on /r/CODE belongs to, null for an unknown or switched-off code. Counts the click: not a
     * robot's, and a visitor (address + day, hashed) once a day.
     */
    public function click(Request $request, string $code): ?Partner
    {
        $partner = Partner::where('code', Partner::normalize($code))->where('active', true)->first();
        if (! $partner) {
            return null;
        }
        if (Bots::name($request) === null) {
            try {
                $day = now()->toDateString();
                PartnerClick::firstOrCreate(
                    ['partner_id' => $partner->id, 'day' => $day, 'visitor' => sha1($request->ip().'|'.$day.'|'.config('app.key'))],
                    ['referer' => mb_substr((string) $request->headers->get('referer'), 0, 300) ?: null, 'landing' => mb_substr((string) $request->query('to', '/'), 0, 300), 'created_at' => now()],
                );
            } catch (\Throwable $e) {
                Log::warning('Partner click not counted', ['partner' => $partner->id, 'error' => $e->getMessage()]);   // counting must never break the redirect
            }
        }

        return $partner;
    }

    /** Only a path of this site: no other host, no `//host`, no backslash tricks, no scheme. */
    public static function safeLanding(?string $to): string
    {
        $to = (string) $to;
        if ($to === '' || $to[0] !== '/' || str_starts_with($to, '//') || str_contains($to, '\\') || preg_match('/[\x00-\x1f]/', $to)) {
            return '/';
        }

        return $to;
    }

    // ── an order ────────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * An order has just been made (OrderService::create): the partner of the cookie is written on it. Not for the
     * partner's own account, an admin's order, a showcase or a test print; nothing when the programme is off or the
     * request has no cookie (a job, a command).
     */
    public function attributeNew(FarmOrder $order): void
    {
        if (! $this->enabled() || $order->isFree()) {
            return;
        }
        $code = (string) (request()->cookie(self::COOKIE) ?? '');
        if ($code === '' || ! preg_match(Partner::CODE_PATTERN, Partner::normalize($code))) {
            return;
        }
        $partner = Partner::where('code', Partner::normalize($code))->where('active', true)->first();
        if (! $partner || $this->excluded($order, $partner)) {
            return;
        }
        $order->forceFill(['partner_id' => $partner->id])->save();
    }

    /** The partner's own orders and an admin's earn nothing. */
    private function excluded(FarmOrder $order, Partner $partner): bool
    {
        return ($order->user_id && $partner->user_id === $order->user_id) || (bool) $order->user?->isAdmin();
    }

    /**
     * The order is paid (OrderFlow::pay, which both the credit and the card payment end in): the commission is written.
     * The partner is the one the order's counted code belongs to, else the one of the cookie. The percent is the
     * partner's at this moment and stays on the commission whatever is changed later.
     */
    public function onPaid(FarmOrder $order): void
    {
        if (! $this->enabled() || $order->isFree() || $order->paid_at === null) {
            return;
        }
        $order->loadMissing('promoCode.partner', 'partner', 'user');
        $viaCode = $order->promo_counted_at && ! $order->promo_released_at ? $order->promoCode?->partner : null;
        $partner = ($viaCode && $viaCode->active ? $viaCode : null) ?? $order->partner;
        if (! $partner || ! $partner->active || $this->excluded($order, $partner)) {
            return;
        }
        $base = self::baseNet($order);
        if ($base <= 0.004) {
            return;
        }
        $percent = (float) $partner->commission_percent;
        DB::transaction(function () use ($order, $partner, $base, $percent) {
            if ((int) $order->partner_id !== (int) $partner->id) {
                $order->forceFill(['partner_id' => $partner->id])->save();
            }
            PartnerCommission::firstOrCreate(['farm_order_id' => $order->id], [
                'partner_id' => $partner->id, 'base_amount' => $base, 'percent' => $percent, 'amount' => round($base * $percent / 100, 2),
                'currency' => (string) ($order->currency ?: 'CZK'), 'state' => PartnerCommission::STATE_PENDING,
            ]);
        });
    }

    /**
     * What the commission is counted from: the print after the discount plus the delivery, net of VAT, in the order's
     * currency, rounded once. The VAT is the one of the print (the delivery price is with the same VAT). The
     * designer's reward is not in it.
     */
    public static function baseNet(FarmOrder $order): float
    {
        $price = (array) $order->price;
        $gross = (float) ($price['print_total'] ?? 0) + (float) ($price['shipping'] ?? $order->shipping_price ?? 0);
        $vat = max(0.0, (float) ($price['inputs']['vat_percent'] ?? 0));

        return round($gross / (1 + $vat / 100), 2);
    }

    /** The print is done: what was pending becomes payable (OrderFlow::onDone). */
    public function onDone(FarmOrder $order): void
    {
        PartnerCommission::where('farm_order_id', $order->id)->where('state', PartnerCommission::STATE_PENDING)->update(['state' => PartnerCommission::STATE_PAYABLE]);
    }

    /**
     * Money of the order went back to the customer, or the order failed: the commission is reduced in proportion to
     * what was given back, or void when it was all of it, and the use of the discount code is given back with it.
     * Counted from the ledger (what the order's release and refund lines hold), so it is the same whichever way the
     * money went (a failure, a cancellation, the admin's refund, a refund to the card). A commission already paid out
     * is not touched; the note says what happened.
     */
    public function sync(FarmOrder $order): void
    {
        $order = FarmOrder::find($order->id);   // a copy of its own: the caller's unsaved state is left alone
        if (! $order) {
            return;
        }
        $total = (float) $order->price_total;
        $back = (float) CreditTransaction::where('farm_order_id', $order->id)->where('user_id', $order->user_id)
            ->whereIn('type', [CreditTransaction::TYPE_RELEASE, CreditTransaction::TYPE_REFUND])->sum('amount');
        $ended = in_array($order->status, [FarmOrder::STATUS_FAILED, FarmOrder::STATUS_CANCELLED], true);
        $ratio = $total > 0.004 ? min(1.0, max(0.0, $back / $total)) : ($ended ? 1.0 : 0.0);
        $full = $ratio >= 0.995;

        if ($full) {
            app(Coupons::class)->release($order);
        }
        $commission = PartnerCommission::where('farm_order_id', $order->id)->first();
        if (! $commission || $ratio <= 0.0) {
            return;
        }
        if ($commission->state === PartnerCommission::STATE_PAID) {
            $commission->update(['note' => mb_substr('order refunded '.round($ratio * 100).' % after the payout', 0, 300)]);

            return;
        }
        if ($full) {
            $commission->update(['state' => PartnerCommission::STATE_VOID, 'amount' => 0, 'note' => 'order refunded in full']);

            return;
        }
        $commission->update(['amount' => round((float) $commission->base_amount * (float) $commission->percent / 100 * (1 - $ratio), 2), 'note' => mb_substr('order refunded '.round($ratio * 100).' %', 0, 300)]);
    }

    /** One call from the payment path: whatever goes wrong here is logged and never reaches the payment. */
    public static function safely(callable $do, string $what): void
    {
        try {
            $do();
        } catch (\Throwable $e) {
            Log::warning('Partner programme: '.$what.' failed', ['error' => $e->getMessage()]);
        }
    }

    // ── payout ──────────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * The operator paid commissions out: the payable ones of a partner (or the ids named) become paid, with a note.
     *
     * @param  list<int>|null  $ids  commission ids; null = every payable one of the partner
     * @return int how many were marked
     */
    public function markPaid(Partner $partner, ?array $ids, string $note): int
    {
        return PartnerCommission::where('partner_id', $partner->id)->where('state', PartnerCommission::STATE_PAYABLE)
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->update(['state' => PartnerCommission::STATE_PAID, 'paid_at' => now(), 'note' => mb_substr($note, 0, 300)]);
    }

    /**
     * The numbers of a partner (the admin's list and the partner's own page).
     *
     * @return array<string, mixed> the money keys are maps currency → amount
     */
    public function stats(Partner $partner, int $days = 30): array
    {
        $since = now()->subDays($days - 1)->toDateString();
        $clicks = PartnerClick::where('partner_id', $partner->id)->where('day', '>=', $since)->selectRaw('day, count(*) as n')->groupBy('day')->pluck('n', 'day')->all();
        $byDay = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $byDay[$d] = (int) ($clicks[$d] ?? 0);
        }
        // money per currency: a partner may have orders in crowns and in euros, and the two are never added up
        $sums = fn (?string $state, string $column = 'amount') => PartnerCommission::where('partner_id', $partner->id)
            ->when($state !== null, fn ($q) => $q->where('state', $state), fn ($q) => $q->where('state', '!=', PartnerCommission::STATE_VOID))
            ->selectRaw('currency, sum('.$column.') as s')->groupBy('currency')->pluck('s', 'currency')->map(fn ($v) => round((float) $v, 2))->all();

        return [
            'clicks' => PartnerClick::where('partner_id', $partner->id)->count(),
            'clicks_by_day' => $byDay,
            'orders' => FarmOrder::where('partner_id', $partner->id)->count(),
            'paid_orders' => FarmOrder::where('partner_id', $partner->id)->whereNotNull('paid_at')->count(),
            'turnover' => $sums(null, 'base_amount'),
            'pending' => $sums(PartnerCommission::STATE_PENDING),
            'payable' => $sums(PartnerCommission::STATE_PAYABLE),
            'paid' => $sums(PartnerCommission::STATE_PAID),
            'payout' => $this->dueState($sums(PartnerCommission::STATE_PAYABLE)) + ['min_czk' => $this->payoutMinCzk(), 'next' => $this->nextPayout()->toDateString()],
        ];
    }
}
