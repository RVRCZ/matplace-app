<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A payment at a gateway. Today its only purpose is a credit top-up. */
class Payment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    protected $attributes = ['currency' => 'CZK', 'status' => self::STATUS_PENDING, 'purpose' => 'credit_topup'];

    protected $fillable = ['user_id', 'gateway', 'gateway_ref', 'purpose', 'amount', 'currency', 'status', 'paid_at', 'raw'];

    protected $casts = ['amount' => 'float', 'paid_at' => 'datetime', 'raw' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
