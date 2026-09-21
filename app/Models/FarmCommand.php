<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Something the agent should do with a printer. The agent polls, takes pending commands and reports the result. */
class FarmCommand extends Model
{
    public const TYPE_START = 'start';

    public const TYPE_PAUSE = 'pause';

    public const TYPE_RESUME = 'resume';

    public const TYPE_CANCEL = 'cancel';

    protected $fillable = ['farm_printer_id', 'farm_print_job_id', 'type', 'payload', 'status', 'result', 'created_by', 'sent_at', 'finished_at'];

    protected $casts = ['payload' => 'array', 'sent_at' => 'datetime', 'finished_at' => 'datetime'];

    public function printer(): BelongsTo
    {
        return $this->belongsTo(FarmPrinter::class, 'farm_printer_id');
    }

    public function printJob(): BelongsTo
    {
        return $this->belongsTo(FarmPrintJob::class, 'farm_print_job_id');
    }
}
