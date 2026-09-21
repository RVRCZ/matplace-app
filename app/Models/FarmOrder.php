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

    public const TRANSITIONS = [
        self::STATUS_UPLOADED => [self::STATUS_SLICED, self::STATUS_FAILED, self::STATUS_CANCELLED],
        self::STATUS_SLICED => [self::STATUS_UPLOADED, self::STATUS_PAID, self::STATUS_CANCELLED],
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

    protected $attributes = ['currency' => 'CZK', 'quality' => 'standard', 'strength' => 'standard', 'unit_scale' => 1, 'delivery' => 'pickup'];

    protected $fillable = [
        'token', 'number', 'user_id', 'model_file_id', 'status', 'stage', 'error', 'error_detail', 'quality', 'strength', 'unit_scale',
        'farm_material_id', 'farm_color_id', 'farm_printer_id', 'farm_printer_slot_id', 'delivery', 'shipping_address', 'note',
        'check', 'orientation', 'print_stl_path', 'gcode_path', 'gcode_sha256', 'slice_params', 'slice_result', 'est_minutes',
        'est_grams', 'est_meters', 'supports_used', 'price', 'price_total', 'currency', 'terms_version', 'terms_accepted_at',
        'terms_ip', 'paid_at', 'approved_at', 'approved_by', 'queued_at', 'started_at', 'finished_at', 'handed_at', 'tracking',
        'actual_minutes', 'actual_grams', 'actual_source',
    ];

    protected $casts = [
        'unit_scale' => 'float', 'shipping_address' => 'array', 'check' => 'array', 'orientation' => 'array',
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
        return $this->printJobs()->latest('id')->first();
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
    public function cancellableByCustomer(): bool
    {
        return in_array($this->status, [self::STATUS_UPLOADED, self::STATUS_SLICED, self::STATUS_PAID, self::STATUS_QUEUED], true);
    }

    /** Directory on the farm disk holding this order's print STL, G-code and snapshots. */
    public function dir(): string
    {
        return 'orders/'.$this->token;
    }

    public function absoluteGcodePath(): ?string
    {
        return $this->gcode_path ? Storage::disk(config('farm.disk'))->path($this->gcode_path) : null;
    }

    public function absolutePrintStlPath(): ?string
    {
        return $this->print_stl_path ? Storage::disk(config('farm.disk'))->path($this->print_stl_path) : null;
    }
}
