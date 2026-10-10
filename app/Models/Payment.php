<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment at a gateway: a credit top-up from the account page, or a card payment for one order made from the order's
 * page (`order_pay`: the money is credited the same way, and the order is then paid from it by the webhook; `context`
 * holds what the customer chose on the page and, once tried, the `result`).
 */
class Payment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const PURPOSE_TOPUP = 'credit_topup';

    public const PURPOSE_ORDER = 'order_pay';

    protected $attributes = ['currency' => 'CZK', 'status' => self::STATUS_PENDING, 'purpose' => self::PURPOSE_TOPUP];

    protected $fillable = ['user_id', 'farm_order_id', 'gateway', 'gateway_ref', 'purpose', 'amount', 'currency', 'status', 'paid_at', 'raw', 'context'];

    protected $casts = ['amount' => 'float', 'paid_at' => 'datetime', 'raw' => 'array', 'context' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(FarmOrder::class, 'farm_order_id');
    }
}
