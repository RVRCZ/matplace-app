<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One attempt to print an order on a printer. A failed order that is printed again gets a new job. */
class FarmPrintJob extends Model
{
    public const STATUS_PENDING = 'pending';     // start command waits for the agent

    public const STATUS_SENT = 'sent';           // agent took the command: downloading, uploading, starting

    public const STATUS_PRINTING = 'printing';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_UNKNOWN = 'unknown';     // connection lost while printing: nobody knows until the agent is back

    public const ACTIVE = [self::STATUS_PENDING, self::STATUS_SENT, self::STATUS_PRINTING, self::STATUS_PAUSED, self::STATUS_UNKNOWN];

    protected $fillable = [
        'farm_order_id', 'farm_printer_id', 'slot', 'status', 'remote_filename', 'progress', 'telemetry', 'print_duration_s',
        'filament_used_mm', 'message', 'snapshot_path', 'snapshot_at', 'started_at', 'finished_at', 'reported_at',
    ];

    protected $casts = [
        'slot' => 'int', 'progress' => 'float', 'telemetry' => 'array', 'print_duration_s' => 'int', 'filament_used_mm' => 'float',
        'snapshot_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'reported_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(FarmOrder::class, 'farm_order_id');
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(FarmPrinter::class, 'farm_printer_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }
}
