<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Eloquent row for a printer's price list; App\Domain\Calculation\PricingProfile is the pure DTO used for maths. */
class PricingProfile extends Model
{
    protected $fillable = [
        'printer_profile_id', 'name', 'is_default', 'hourly_rate', 'price_per_gram', 'setup_fee', 'margin_pct', 'min_price',
        'lead_time_days', 'express_pct', 'qty_discounts', 'finishing', 'time_factor',
    ];

    protected $casts = ['is_default' => 'bool', 'qty_discounts' => 'array', 'finishing' => 'array'];

    public function printerProfile(): BelongsTo
    {
        return $this->belongsTo(PrinterProfile::class);
    }
}
