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

    /** How many of the printer's messages a job remembers (the newest last; docs/AM.md §10). */
    public const MESSAGES_KEPT = 20;

    protected $fillable = [
        'farm_order_id', 'farm_printer_id', 'slot', 'plate', 'status', 'remote_filename', 'progress', 'telemetry', 'print_duration_s',
        'filament_used_mm', 'stats_estimated', 'message', 'message_at', 'messages', 'snapshot_path', 'snapshot_at', 'started_at', 'paused_at', 'paused_s', 'finished_at', 'reported_at',
    ];

    protected $casts = [
        'slot' => 'int', 'plate' => 'int', 'progress' => 'float', 'telemetry' => 'array', 'print_duration_s' => 'int', 'filament_used_mm' => 'float',
        'stats_estimated' => 'bool', 'paused_s' => 'int', 'messages' => 'array',
        'snapshot_at' => 'datetime', 'message_at' => 'datetime', 'started_at' => 'datetime', 'paused_at' => 'datetime', 'finished_at' => 'datetime', 'reported_at' => 'datetime',
    ];

    /**
     * A message the printer sent with this job (its reason for a pause, an error, what its screen says): the newest
     * is `message`/`message_at`, the last MESSAGES_KEPT are the history. The agent repeats a message in every report,
     * so the same words in a row are one entry. Fills in place; the caller saves.
     */
    public function noteMessage(?string $text): void
    {
        $text = trim((string) $text);
        if ($text === '') {
            return;
        }
        $history = array_values((array) ($this->messages ?? []));
        $last = $history ? end($history) : null;
        if (is_array($last) && ($last['m'] ?? null) === $text) {
            return;
        }
        $history[] = ['t' => now()->toIso8601String(), 'm' => mb_substr($text, 0, 300)];
        $this->messages = array_slice($history, -self::MESSAGES_KEPT);
        $this->message_at = now();
    }

    /** @return list<array{t: string, m: string}> the history, newest first */
    public function messageHistory(): array
    {
        return array_reverse(array_values((array) ($this->messages ?? [])));
    }

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
