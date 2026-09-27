<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * One print ordered from the farm. The status only moves along TRANSITIONS and only through
 * App\Domain\Farm\OrderFlow, which also writes the event, moves the credit and notifies the customer.
 */
class FarmOrder extends Model
{
    public const STATUS_UPLOADED = 'uploaded';        // model taken over, being checked / oriented / sliced

    public const STATUS_SLICED = 'sliced';            // price known, waiting for the customer

    public const STATUS_PAID = 'paid';                // credit held; waits for approval when the farm requires it

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PRINTING = 'printing';

    public const STATUS_DONE = 'done';                // printed, waiting to be taken off the plate / handed over

    public const STATUS_HANDED_OVER = 'handed_over';  // picked up or shipped

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const KIND_PRINT = 'print';            // a customer's order

    public const KIND_TEST = 'test';              // a tuning test print of the farm itself: no price, sliced → queued

    public const TRANSITIONS = [
        self::STATUS_UPLOADED => [self::STATUS_SLICED, self::STATUS_FAILED, self::STATUS_CANCELLED],
        self::STATUS_SLICED => [self::STATUS_UPLOADED, self::STATUS_PAID, self::STATUS_CANCELLED, self::STATUS_QUEUED],   // → queued: tests only (OrderFlow)
        self::STATUS_PAID => [self::STATUS_QUEUED, self::STATUS_CANCELLED],
        self::STATUS_QUEUED => [self::STATUS_PRINTING, self::STATUS_FAILED, self::STATUS_CANCELLED],
        self::STATUS_PRINTING => [self::STATUS_DONE, self::STATUS_FAILED, self::STATUS_CANCELLED, self::STATUS_QUEUED],
        self::STATUS_DONE => [self::STATUS_HANDED_OVER, self::STATUS_FAILED],
        self::STATUS_FAILED => [self::STATUS_QUEUED, self::STATUS_UPLOADED, self::STATUS_CANCELLED],
        self::STATUS_HANDED_OVER => [],
        self::STATUS_CANCELLED => [],
    ];

    /** The customer's money is held or spent in these states. */
    public const STATUSES_COMMITTED = [self::STATUS_PAID, self::STATUS_QUEUED, self::STATUS_PRINTING, self::STATUS_DONE, self::STATUS_HANDED_OVER];

    protected $attributes = ['currency' => 'CZK', 'quality' => 'standard', 'strength' => 'standard', 'copies' => 1, 'plates' => 1, 'plates_done' => 0, 'plate_copies' => 1, 'unit_scale' => 1, 'delivery' => 'pickup', 'kind' => 'print'];

    protected $fillable = [
        'quality_rating', 'quality_note', 'timelapse_path', 'kind', 'farm_printer_material_id', 'test_params',
        'token', 'number', 'user_id', 'model_file_id', 'status', 'stage', 'error', 'error_detail', 'quality', 'strength', 'copies', 'plates', 'plates_done', 'plate_copies', 'rest_copies', 'unit_scale',
        'farm_material_id', 'farm_color_id', 'farm_printer_id', 'farm_printer_slot_id', 'delivery', 'shipping_address', 'note',
        'check', 'orientation', 'print_stl_path', 'gcode_path', 'gcode_sha256', 'rest_gcode_path', 'slice_params', 'slice_result', 'est_minutes',
        'est_grams', 'est_meters', 'supports_used', 'price', 'price_total', 'currency', 'terms_version', 'terms_accepted_at',
        'terms_ip', 'paid_at', 'approved_at', 'approved_by', 'queued_at', 'started_at', 'finished_at', 'handed_at', 'tracking',
        'actual_minutes', 'actual_grams', 'actual_source',
    ];

    protected $casts = [
        'unit_scale' => 'float', 'copies' => 'int', 'plates' => 'int', 'plates_done' => 'int', 'plate_copies' => 'int', 'rest_copies' => 'int', 'shipping_address' => 'array', 'check' => 'array', 'orientation' => 'array', 'test_params' => 'array',
        'slice_params' => 'array', 'slice_result' => 'array', 'price' => 'array', 'est_minutes' => 'int', 'est_grams' => 'float',
        'est_meters' => 'float', 'supports_used' => 'bool', 'price_total' => 'float', 'actual_minutes' => 'int', 'actual_grams' => 'float',
        'terms_accepted_at' => 'datetime', 'paid_at' => 'datetime', 'approved_at' => 'datetime', 'queued_at' => 'datetime',
        'started_at' => 'datetime', 'finished_at' => 'datetime', 'handed_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function modelFile(): BelongsTo
    {
        return $this->belongsTo(ModelFile::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(FarmMaterial::class, 'farm_material_id');
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(FarmColor::class, 'farm_color_id');
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(FarmPrinter::class, 'farm_printer_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(FarmPrinterSlot::class, 'farm_printer_slot_id');
    }

    /** The tuning row (printer × kind, or printer × spool) a test print was made for. */
    public function printerMaterial(): BelongsTo
    {
        return $this->belongsTo(FarmPrinterMaterial::class, 'farm_printer_material_id');
    }

    public function isTest(): bool
    {
        return $this->kind === self::KIND_TEST;
    }

    public function events(): HasMany
    {
        return $this->hasMany(FarmOrderEvent::class)->orderBy('id');
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(FarmPrintJob::class)->orderBy('id');
    }

    public function latestJob(): ?FarmPrintJob
    {
        // the relation orders by id ascending; without reorder() that first ORDER BY would win and the oldest job came back
        return $this->printJobs()->reorder()->latest('id')->first();
    }

    public function canMoveTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isCommitted(): bool
    {
        return in_array($this->status, self::STATUSES_COMMITTED, true);
    }

    /** The customer may still walk away and get the held credit back. */
    /** Before it starts for free; a running print too, paid for as far as it got (OrderFlow::onStopped). */
    public function cancellableByCustomer(): bool
    {
        return in_array($this->status, [self::STATUS_UPLOADED, self::STATUS_SLICED, self::STATUS_PAID, self::STATUS_QUEUED, self::STATUS_PRINTING], true);
    }

    /** Directory on the farm disk holding this order's print STL, G-code and snapshots. */
    public function dir(): string
    {
        return 'orders/'.$this->token;
    }

    /** The G-code of one plate: every full plate shares the main file, a partly filled last plate has its own. */
    public function absoluteGcodePath(?int $plate = null): ?string
    {
        $rel = $plate !== null && $plate >= $this->plates && $this->rest_gcode_path ? $this->rest_gcode_path : $this->gcode_path;

        return $rel ? Storage::disk(config('farm.disk'))->path($rel) : null;
    }

    /** The plate a job started now would print (1-based). */
    public function nextPlate(): int
    {
        return min($this->plates, $this->plates_done + 1);
    }

    /** Pieces on each plate, first to last: [4, 4, 1] for 9 pieces when four fit. */
    public function plateLayout(): array
    {
        if ($this->plates <= 1) {
            return [$this->copies];
        }
        $full = array_fill(0, $this->plates - 1, $this->plate_copies);
        $full[] = $this->rest_copies ?: $this->plate_copies;

        return $full;
    }

    public function absolutePrintStlPath(): ?string
    {
        return $this->print_stl_path ? Storage::disk(config('farm.disk'))->path($this->print_stl_path) : null;
    }

    /** Support structures for the 3D preview (App\Engines\Gcode\SupportLines), when the slice built any. */
    public function absoluteSupportsPath(): ?string
    {
        $path = Storage::disk(config('farm.disk'))->path($this->dir().'/supports.bin');

        return is_file($path) ? $path : null;
    }
}
