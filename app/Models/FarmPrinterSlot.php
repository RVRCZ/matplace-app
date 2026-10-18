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

    /**
     * The spool's code: the machine's code and the slot counted from 1 ("U33" = farm U, printer 3, slot 3). What the
     * customer sees next to a colour and the operator next to a job, to find the machine and the spool.
     */
    public function code(): string
    {
        return $this->printer->shortCode().($this->slot + 1);
    }

    /**
     * May this spool be offered from this machine? A kind that breaks in the filament changer (FarmMaterial::manual_only)
     * is fed straight from the holder by a person, so it is offered only from a printer in manual mode; such a colour
     * in a slot of an agent-driven machine (old data, or a machine switched to the agent since) counts as not loaded.
     */
    public function kindFitsPrinter(): bool
    {
        return self::kindFits($this->printer, $this->color);
    }

    /** The same rule before a slot exists: would this colour be allowed in a slot of this printer? */
    public static function kindFits(?FarmPrinter $printer, ?FarmColor $color): bool
    {
        return ! $color?->material?->isManualOnly() || ! $printer?->isAgentDriven();
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
