<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** History of an order: every status change with who made it. Written once, never edited. */
class FarmOrderEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['farm_order_id', 'from', 'to', 'actor', 'actor_id', 'note'];

    protected $casts = ['created_at' => 'datetime'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(FarmOrder::class, 'farm_order_id');
    }
}
