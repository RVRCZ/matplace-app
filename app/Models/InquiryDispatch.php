<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InquiryDispatch extends Model
{
    protected $fillable = [
        'inquiry_id', 'printer_profile_id', 'rank', 'distance_km', 'auto_price', 'auto_breakdown', 'notified_at', 'seen_at', 'declined_at', 'decline_reason',
    ];

    protected $casts = ['auto_breakdown' => 'array', 'notified_at' => 'datetime', 'seen_at' => 'datetime', 'declined_at' => 'datetime', 'distance_km' => 'float', 'auto_price' => 'float'];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function printerProfile(): BelongsTo
    {
        return $this->belongsTo(PrinterProfile::class);
    }
}
