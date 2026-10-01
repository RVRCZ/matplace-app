<?php

namespace App\Models;

use App\Support\CurrencyMismatch;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the credit ledger. Append-only: corrections are new lines.
 *
 * An account keeps one currency. Its first line fixes it (users.currency); a later line in another currency is a
 * bug somewhere above and is refused here, before it can spoil the balance.
 */
class CreditTransaction extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_TOPUP = 'topup';       // + money came in

    public const TYPE_HOLD = 'hold';         // − reserved for an order

    public const TYPE_CAPTURE = 'capture';   // 0 the hold became final (printed)

    public const TYPE_RELEASE = 'release';   // + hold returned (cancelled / failed before it was captured)

    public const TYPE_REFUND = 'refund';     // + returned after capture

    public const TYPE_ADJUST = 'adjust';     // ± by an admin, with a note

    public const TYPE_CHARGE = 'charge';     // − paid at once for something delivered at once (a generation beyond the quota)

    public const TYPE_FORFEIT = 'forfeit';   // − unused credit given up when the owner deleted the account

    public const TYPE_ROYALTY = 'royalty';   // + a designer's reward for a finished print of their model

    public const TYPE_ROYALTY_REVERSAL = 'royalty_reversal';   // − the reward taken back when the print was refunded after it was done

    protected $fillable = ['user_id', 'type', 'amount', 'currency', 'farm_order_id', 'payment_id', 'note', 'created_by', 'designer_model_id'];

    protected $casts = ['amount' => 'float', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $line) {
            $user = User::find($line->user_id);
            $line->currency = strtoupper((string) ($line->currency ?: $user?->currency ?: Money::CZK));
            if (! $user) {
                return;
            }
            if (! $user->currency) {
                $user->forceFill(['currency' => $line->currency])->save();
            } elseif ($user->currency !== $line->currency) {
                throw new CurrencyMismatch((string) $user->currency, $line->currency, "Ledger of user {$user->id}.");
            }
        });
    }

    public function money(): Money
    {
        return new Money((float) $this->amount, (string) $this->currency);
    }

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
