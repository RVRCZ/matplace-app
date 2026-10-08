<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A selling plan an account saved on /tools/plan: products, fixed costs, seasonality, the steps ticked off. */
class SellPlan extends Model
{
    public const MAX_PER_USER = 20;

    protected $fillable = ['user_id', 'name', 'data'];

    protected $casts = ['data' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
