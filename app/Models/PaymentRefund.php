<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money returned to the card a payment came from (App\Domain\Farm\CardRefunds): pending while the gateway is asked,
 * done with its refund id, failed with its words (the money then stays as credit and the operator decides).
 */
class PaymentRefund extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    public const REASON_PRINT_FAILED = 'print_failed';      // printed again once, failed again

    public const REASON_CANCELLED = 'cancelled';            // the customer cancelled before the print started

    public const REASON_CANCELLED_RUNNING = 'cancelled_running';   // cancelled during the print: the printed share stays charged

    public const REASON_NOT_PAID = 'not_paid';              // a guest's payment arrived, the order could not be paid with it

    public const REASON_ADMIN = 'admin';                    // by hand from the admin's order page

    protected $fillable = ['payment_id', 'farm_order_id', 'amount', 'currency', 'reason', 'note', 'gateway_ref', 'status', 'error', 'created_by'];

    protected $casts = ['amount' => 'float'];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(FarmOrder::class, 'farm_order_id');
    }
}
