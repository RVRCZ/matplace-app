<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One visitor's first click on a partner's link in a day (the visitor is a hash of address and day, never an address). */
class PartnerClick extends Model
{
    public $timestamps = false;

    protected $fillable = ['partner_id', 'day', 'visitor', 'referer', 'landing', 'created_at'];

    protected $casts = ['day' => 'date', 'created_at' => 'datetime'];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
