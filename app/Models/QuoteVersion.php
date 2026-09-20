<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What the customer was shown at one moment: an acceptance or a change request always points at exactly one of these. */
class QuoteVersion extends Model
{
    protected $fillable = ['quote_id', 'version', 'snapshot', 'sent_at', 'accepted_at', 'accepted_ip', 'change_request'];

    protected $casts = ['snapshot' => 'array', 'sent_at' => 'datetime', 'accepted_at' => 'datetime'];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
