<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Quote extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_VIEWED = 'viewed';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'token', 'number', 'printer_profile_id', 'calculation_id', 'model_file_id', 'inquiry_id', 'client_name', 'client_email',
        'title', 'params', 'lines', 'total', 'currency', 'valid_until', 'lead_time_days', 'note', 'pdf_path', 'status',
        'sent_at', 'viewed_at', 'accepted_at', 'declined_at',
    ];

    protected $casts = [
        'params' => 'array', 'lines' => 'array', 'total' => 'float', 'valid_until' => 'date',
        'sent_at' => 'datetime', 'viewed_at' => 'datetime', 'accepted_at' => 'datetime', 'declined_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function printerProfile(): BelongsTo
    {
        return $this->belongsTo(PrinterProfile::class);
    }

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(Calculation::class);
    }

    public function modelFile(): BelongsTo
    {
        return $this->belongsTo(ModelFile::class);
    }

    public static function newToken(): string
    {
        do {
            $t = Str::lower(Str::random(10));
        } while (static::where('token', $t)->exists());

        return $t;
    }

    /** Sequential number per printer and year: N-2026-0007. */
    public static function nextNumber(int $printerProfileId): string
    {
        $year = now()->year;
        $count = static::where('printer_profile_id', $printerProfileId)->whereYear('created_at', $year)->count() + 1;

        return sprintf('N-%d-%04d', $year, $count);
    }

    /** Sum of line totals, rounded to whole CZK. */
    public static function sumLines(array $lines): float
    {
        return round(array_sum(array_map(fn ($l) => (float) ($l['total'] ?? 0), $lines)), 0);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_VIEWED], true)
            && (! $this->valid_until || $this->valid_until->endOfDay()->isFuture());
    }
}
