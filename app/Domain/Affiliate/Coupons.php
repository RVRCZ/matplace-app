<?php

namespace App\Domain\Affiliate;

use App\Domain\Farm\FarmRefusal;
use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\PriceCalculator;
use App\Models\FarmOrder;
use App\Models\PromoCode;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Discount codes (docs/AT.md §3). The whole money rule is in two places, and both are used by everything that shows
 * or charges a price:
 *
 *  - `applyToPrice()` is called by OrderService::priceFor, the one place a price is made: the page, the quote, the
 *    order's stored price, what is held from the credit and what the card pays are all that one number;
 *  - `redeem()` counts the use inside the payment's own transaction (and refuses when the last use went meanwhile).
 *
 * The discount is taken from the print price (what the customer sees as "the print", with VAT), never from the
 * delivery and never from the designer's reward. It is worked out in the currency the customer pays in, on the
 * print price already rounded the way prices are, so it is never rounded twice: a percentage leaves the print price
 * rounded up to whole crowns (ten cents in euros), a fixed amount in crowns is converted the way prices are.
 */
final class Coupons
{
    public function __construct(private readonly FarmSettings $settings) {}

    /** The switch (farm settings `promo_codes_enabled`, off by default): off means no discount anywhere. */
    public function enabled(): bool
    {
        return (bool) $this->settings->get('promo_codes_enabled');
    }

    public function find(string $code): ?PromoCode
    {
        $code = PromoCode::normalize($code);

        return $code !== '' ? PromoCode::where('code', $code)->first() : null;
    }

    /**
     * Why this code cannot be used for this order now (a key of `farm.promo.refuse.<reason>`), null when it can.
     *
     * @param  float|null  $printCzk  the print price before the discount in crowns; null while the order is not priced (the minimum is then checked when it is)
     */
    public function problem(PromoCode $code, FarmOrder $order, ?float $printCzk): ?string
    {
        $now = now();
        if (! $code->active) {
            return 'inactive';
        }
        if ($code->valid_from && $now->lt($code->valid_from)) {
            return 'not_started';
        }
        if ($code->valid_until && $now->gt($code->valid_until)) {
            return 'expired';
        }
        $counted = $order->promo_counted_at !== null && $order->promo_released_at === null && (int) $order->promo_code_id === (int) $code->id;
        if ($code->max_uses !== null && (int) $code->uses >= $code->max_uses && ! $counted) {
            return 'used_up';
        }
        if ($printCzk !== null && $printCzk + 0.005 < (float) $code->min_order_czk) {
            return 'min_order';
        }
        if ($code->once_per_account && $order->user_id && $this->usedBy($code, $order->user_id, $order->id)) {
            return 'already_used';
        }

        return null;
    }

    /** The guest's e-mail may belong to an account that already used a once-per-account code: said before the card is asked for money. */
    public function problemForEmail(PromoCode $code, FarmOrder $order, string $email): ?string
    {
        $user = User::where('email', strtolower(trim($email)))->first();

        return $user && $code->once_per_account && $this->usedBy($code, $user->id, $order->id) ? 'already_used' : null;
    }

    private function usedBy(PromoCode $code, int $userId, ?int $exceptOrder): bool
    {
        return FarmOrder::where('user_id', $userId)->where('promo_code_id', $code->id)
            ->whereNotNull('promo_counted_at')->whereNull('promo_released_at')
            ->when($exceptOrder, fn ($q) => $q->where('id', '!=', $exceptOrder))->exists();
    }

    /**
     * The discount on a print price, in the currency it is paid in. `$print` is the print price already rounded the way
     * prices are (whole crowns, ten cents in euros).
     */
    public function discountOn(PromoCode $code, float $print, string $currency): float
    {
        $currency = strtoupper($currency);
        if ($code->kind === PromoCode::KIND_PERCENT) {
            $percent = max(0.0, min(100.0, (float) $code->value));
            $after = PriceCalculator::roundUp($print * (100.0 - $percent) / 100.0, $currency === Money::EUR ? 0.1 : 1.0);

            return max(0.0, round($print - min($print, $after), 2));
        }

        return max(0.0, round(min($print, Money::czk((float) $code->value)->to($currency)->amount), 2));
    }

    /**
     * Called by OrderService::priceFor on the finished breakdown: with a usable code on the order the print price, the
     * total and the lines `discount`, `print_before_discount`, `coupon` follow; without one the breakdown comes back
     * exactly as it was.
     *
     * @param  array<string,mixed>  $price  the breakdown in the order's currency (OrderService::inCurrency)
     * @param  float  $printCzk  the print price in crowns before the discount
     * @return array<string,mixed>
     */
    public function applyToPrice(FarmOrder $order, array $price, float $printCzk): array
    {
        if (! $order->promo_code_id || ! $this->enabled()) {
            return $price;
        }
        $code = $order->promoCode;
        if (! $code || $this->problem($code, $order, $printCzk) !== null) {
            return $price;
        }
        $discount = $this->discountOn($code, (float) $price['print_total'], (string) $price['currency']);
        if ($discount <= 0.0) {
            return $price;
        }
        $before = (float) $price['print_total'];

        return array_merge($price, [
            'print_total' => round($before - $discount, 2),
            'total' => round((float) $price['total'] - $discount, 2),
            'discount' => $discount, 'print_before_discount' => $before,
            'coupon' => ['code' => $code->code, 'kind' => $code->kind, 'value' => (float) $code->value],
        ]);
    }

    /** The print price in crowns before the discount, from a price breakdown made by priceFor (null: no breakdown yet). */
    public static function printCzkOf(?array $price): ?float
    {
        if (! $price || ! isset($price['print_total'])) {
            return null;
        }

        return (float) ($price['czk']['print_total'] ?? $price['print_before_discount'] ?? $price['print_total']);
    }

    /**
     * Counts the use of the code the price carries, in the transaction that pays the order. A code that is not in the
     * price (switched off, expired, below the minimum) is taken off the order, so that a paid order never names a code
     * that gave nothing.
     *
     * @param  array<string,mixed>  $price  the breakdown the order is paid with (priceFor)
     *
     * @throws FarmRefusal coupon_used_up – the last use went to another payment meanwhile
     */
    public function redeem(FarmOrder $order, array $price): void
    {
        if (! isset($price['coupon'])) {
            if ($order->promo_code_id) {
                $order->forceFill(['promo_code_id' => null, 'discount' => null])->save();
            }

            return;
        }
        $code = PromoCode::whereKey($order->promo_code_id)->lockForUpdate()->first();
        if (! $code || ($code->max_uses !== null && (int) $code->uses >= $code->max_uses)) {
            throw new FarmRefusal('coupon_used_up');
        }
        $code->increment('uses');
        $order->forceFill(['discount' => $price['discount'], 'promo_counted_at' => now(), 'promo_released_at' => null])->save();
    }

    /** The order failed or was refunded in full: its use of the code is given back (once). */
    public function release(FarmOrder $order): void
    {
        if (! $order->promo_code_id || ! $order->promo_counted_at || $order->promo_released_at) {
            return;
        }
        DB::transaction(function () use ($order) {
            $code = PromoCode::whereKey($order->promo_code_id)->lockForUpdate()->first();
            $code?->update(['uses' => max(0, (int) $code->uses - 1)]);
            $order->forceFill(['promo_released_at' => now()])->save();
        });
    }
}
