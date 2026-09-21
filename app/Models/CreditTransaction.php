<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of the credit ledger. Append-only: corrections are new lines. */
class CreditTransaction extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_TOPUP = 'topup';       // + money came in

    public const TYPE_HOLD = 'hold';         // − reserved for an order

    public const TYPE_CAPTURE = 'capture';   // 0 the hold became final (printed)

    public const TYPE_RELEASE = 'release';   // + hold returned (cancelled / failed before it was captured)

    public const TYPE_REFUND = 'refund';     // + returned after capture

    public const TYPE_ADJUST = 'adjust';     // ± by an admin, with a note

    protected $fillable = ['user_id', 'type', 'amount', 'currency', 'farm_order_id', 'payment_id', 'note', 'created_by'];

    protected $casts = ['amount' => 'float', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(FarmOrder::class, 'farm_order_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
