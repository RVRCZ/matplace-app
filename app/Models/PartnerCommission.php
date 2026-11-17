<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The commission of one paid order. pending (paid, the print is not finished) -> payable (finished: the customer's
 * money is final) -> paid (the operator paid it out and said so); void when the order failed or was refunded in full.
 */
class PartnerCommission extends Model
{
    public const STATE_PENDING = 'pending';

    public const STATE_PAYABLE = 'payable';

    public const STATE_PAID = 'paid';

    public const STATE_VOID = 'void';

    protected $fillable = ['partner_id', 'farm_order_id', 'base_amount', 'percent', 'amount', 'currency', 'state', 'paid_at', 'note'];

    protected $casts = ['base_amount' => 'float', 'percent' => 'float', 'amount' => 'float', 'paid_at' => 'datetime'];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(FarmOrder::class, 'farm_order_id');
    }
}
