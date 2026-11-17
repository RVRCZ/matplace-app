<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A discount code for the print price of a farm order (App\Domain\Affiliate\Coupons, docs/AT.md). */
class PromoCode extends Model
{
    public const KIND_PERCENT = 'percent';

    public const KIND_FIXED = 'fixed';

    public const CODE_PATTERN = '/^[A-Z0-9_-]{2,32}$/';

    protected $fillable = ['code', 'kind', 'value', 'min_order_czk', 'valid_from', 'valid_until', 'max_uses', 'uses', 'once_per_account', 'active', 'partner_id', 'note'];

    protected $casts = ['value' => 'float', 'min_order_czk' => 'float', 'valid_from' => 'datetime', 'valid_until' => 'datetime', 'max_uses' => 'int', 'uses' => 'int', 'once_per_account' => 'bool', 'active' => 'bool'];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public static function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }
}
