<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Quote extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_VIEWED = 'viewed';

    public const STATUS_CHANGE_REQUESTED = 'change';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    /** Line keys that are the printer's internal arithmetic: the customer sees them folded into one "production" line. */
    public const INTERNAL_KEYS = ['material', 'time', 'setup', 'margin', 'rounding', 'adjust', 'royalty', 'print', 'production'];

    protected $fillable = [
        'token', 'number', 'printer_profile_id', 'calculation_id', 'model_file_id', 'inquiry_id', 'client_name', 'client_email',
        'title', 'color', 'params', 'lines', 'cost', 'total', 'shipping_label', 'shipping_price', 'currency', 'valid_until', 'lead_time_days', 'note', 'pdf_path', 'status',
        'version', 'accepted_version', 'sent_at', 'viewed_at', 'accepted_at', 'declined_at', 'revoked_at', 'change_request', 'change_requested_at',
    ];

    protected $casts = [
        'params' => 'array', 'lines' => 'array', 'cost' => 'array', 'total' => 'float', 'shipping_price' => 'float', 'valid_until' => 'date',
        'version' => 'int', 'accepted_version' => 'int',
        'sent_at' => 'datetime', 'viewed_at' => 'datetime', 'accepted_at' => 'datetime', 'declined_at' => 'datetime',
        'revoked_at' => 'datetime', 'change_requested_at' => 'datetime',
    ];

    /** The cost sheet must never leave the printer's side, whatever serialises this model. */
    protected $hidden = ['cost'];

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

    public function versions(): HasMany
    {
        return $this->hasMany(QuoteVersion::class);
    }

    /** 32 random characters (≈190 bits): the link is the only key to the quote, so it has to be unguessable. */
    public static function newToken(): string
    {
        do {
            $t = Str::random(32);
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

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isOpen(): bool
    {
        return ! $this->isRevoked()
            && in_array($this->status, [self::STATUS_SENT, self::STATUS_VIEWED], true)
            && (! $this->valid_until || $this->valid_until->endOfDay()->isFuture());
    }

    /**
     * What the customer may see: internal arithmetic (material, machine time, margin, rounding…) becomes one production
     * line, extra items the printer added stay as they are, shipping is its own line.
     *
     * @return array<int, array{label:string, qty:float, unit_price:float, total:float}>
     */
    public function customerLines(): array
    {
        $qty = max(1, (int) ($this->params['quantity'] ?? $this->cost['quantity'] ?? 1));
        $production = 0.0;
        $extras = [];
        foreach ((array) $this->lines as $l) {
            if (in_array($l['key'] ?? 'custom', self::INTERNAL_KEYS, true)) {
                $production += (float) ($l['total'] ?? 0);
            } else {
                $extras[] = ['label' => (string) $l['label'], 'qty' => (float) $l['qty'], 'unit_price' => (float) $l['unit_price'], 'total' => (float) $l['total']];
            }
        }
        $out = [];
        if (abs($production) >= 0.005) {
            $out[] = ['label' => __('quote.line.production'), 'qty' => (float) $qty, 'unit_price' => round($production / $qty, 2), 'total' => round($production, 2)];
        }
        $out = array_merge($out, $extras);
        if ($this->shipping_label || $this->shipping_price > 0) {
            $out[] = ['label' => __('quote.line.shipping').($this->shipping_label ? ': '.$this->shipping_label : ''), 'qty' => 1.0, 'unit_price' => (float) $this->shipping_price, 'total' => (float) $this->shipping_price];
        }

        return $out;
    }

    /** Everything the customer is shown, frozen with each version that is sent. */
    public function customerSnapshot(): array
    {
        return [
            'number' => $this->number,
            'title' => $this->title,
            'material' => $this->params['material'] ?? null,
            'color' => $this->color,
            'quantity' => (int) ($this->params['quantity'] ?? $this->cost['quantity'] ?? 1),
            'lines' => $this->customerLines(),
            'total' => (float) $this->total,
            'currency' => $this->currency,
            'lead_time_days' => $this->lead_time_days,
            'valid_until' => $this->valid_until?->toDateString(),
            'note' => $this->note,
            'model_file_id' => $this->model_file_id,
        ];
    }
}
