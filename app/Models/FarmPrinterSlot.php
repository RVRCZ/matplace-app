<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One spool position of a printer (ACE slot, or the single spool holder). `slot` is the tool number in the G-code. */
class FarmPrinterSlot extends Model
{
    protected $fillable = ['farm_printer_id', 'slot', 'farm_color_id', 'remaining_g', 'enabled'];

    protected $casts = ['slot' => 'int', 'remaining_g' => 'float', 'enabled' => 'bool'];

    public function printer(): BelongsTo
    {
        return $this->belongsTo(FarmPrinter::class, 'farm_printer_id');
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(FarmColor::class, 'farm_color_id');
    }

    /** Filament already promised to orders that have not finished printing. */
    public function reservedGrams(): float
    {
        return (float) FarmOrder::where('farm_printer_slot_id', $this->id)
            ->whereIn('status', [FarmOrder::STATUS_PAID, FarmOrder::STATUS_QUEUED, FarmOrder::STATUS_PRINTING])
            ->sum('est_grams');
    }

    public function availableGrams(): float
    {
        return max(0.0, $this->remaining_g - $this->reservedGrams());
    }
}
