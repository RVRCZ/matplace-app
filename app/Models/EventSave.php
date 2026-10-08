<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An event an account saved on /tools/vendors. */
class EventSave extends Model
{
    protected $fillable = ['user_id', 'event_id'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(MarketEvent::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
