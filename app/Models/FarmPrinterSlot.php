<?php

namespace App\Models;

use App\Domain\Farm\SpoolPositions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One spool position of a printer (a place on the Kobra X's rack, an ACE slot, the single spool holder). `slot` is the
 * tool number in the G-code; `label` the admin's own name of the position ("INDX 5"), null = the model's (SpoolPositions).
 */
class FarmPrinterSlot extends Model
{
    protected $fillable = ['farm_printer_id', 'slot', 'farm_color_id', 'label', 'remaining_g', 'enabled'];

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
     * The position as the operator sees it ("Stojan 2", "ACE 1"): the admin's own name, else the one the machine's model
     * implies for the n-th position in slot order (docs/AL.md §19).
     */
    public function positionLabel(): string
    {
        $own = trim((string) ($this->attributes['label'] ?? ''));
        if ($own !== '') {
            return $own;
        }
        $slots = $this->printer->slots;
        $index = $slots->search(fn (FarmPrinterSlot $s) => (int) $s->id === (int) $this->id);

        return SpoolPositions::name($this->printer, $index === false ? (int) $this->slot : (int) $index, $slots->count());
    }

    /**
     * Where the spool sits, in the operator's words ("Farm U #3 · ACE 3"): the printer's name and the position. What the
     * admin sees on the order page instead of the code (TODO #69, docs/AL.md §20); the customer sees neither.
     */
    public function placeLabel(): string
    {
        return $this->printer->name.' · '.$this->positionLabel();
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

    /**
     * The same rule before a slot exists: would this colour be allowed in a slot of this printer? A machine run by the
     * agent whose changer was taken off (FarmPrinter::withoutChanger) feeds its one spool straight into the head, so the
     * kind is at home there too (docs/AL.md §24).
     */
    public static function kindFits(?FarmPrinter $printer, ?FarmColor $color): bool
    {
        return ! $color?->material?->isManualOnly() || ! $printer?->changesSpools();
    }

    /**
     * A position of the changer that is not on the machine right now: the printer's switch "changer attached" is off and
     * this is not the directly fed spool (position 1). The row stays as it was – colour, grams, the offer tick – and
     * counts again the moment the switch is turned back on; meanwhile nothing is offered or printed from it.
     */
    public function isDetached(): bool
    {
        return (int) $this->slot !== FarmPrinter::DIRECT_SLOT && (bool) $this->printer?->withoutChanger();
    }

    /** May the farm offer and print from this position right now: it is on the machine and its kind may be fed there. */
    public function mayOffer(): bool
    {
        return ! $this->isDetached() && $this->kindFitsPrinter();
    }

    /**
     * The positions that are on their machines right now (isDetached, in SQL): the direct spool of every machine and
     * every position of a machine with its changer attached.
     */
    public function scopeFed(Builder $query): Builder
    {
        return $query->where(fn ($w) => $w->where('farm_printer_slots.slot', FarmPrinter::DIRECT_SLOT)
            ->orWhereHas('printer', fn ($p) => $p->where('changer_attached', true)));
    }

    /** Filament already promised to orders that have not finished printing. */
    public function reservedGrams(): float
    {
        return (float) FarmOrder::where('farm_printer_slot_id', $this->id)
            ->whereIn('status', FarmOrder::STATUSES_OPEN_PAID)
            ->sum('est_grams');
    }

    public function availableGrams(): float
    {
        return max(0.0, $this->remaining_g - $this->reservedGrams());
    }
}
