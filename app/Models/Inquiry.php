<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Inquiry extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_OPEN = 'open';

    public const STATUS_OFFERED = 'offered';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'token', 'kind', 'color', 'details', 'calculation_id', 'model_file_id', 'customer_user_id', 'contact_name', 'contact_email', 'contact_phone', 'country',
        'zip', 'city', 'lat', 'lng', 'material_code', 'quantity', 'params', 'summary', 'note', 'wanted_by', 'delivery_pref', 'status',
        'verification_code', 'verified_at', 'accepted_quote_id', 'accepted_at', 'done_at', 'expires_at', 'locale',
    ];

    protected $casts = [
        'details' => 'array',
        'params' => 'array', 'summary' => 'array', 'wanted_by' => 'date', 'verified_at' => 'datetime', 'accepted_at' => 'datetime',
        'done_at' => 'datetime', 'expires_at' => 'datetime', 'lat' => 'float', 'lng' => 'float',
    ];

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public static function newToken(): string
    {
        do {
            $t = Str::lower(Str::random(12));
        } while (static::where('token', $t)->exists());

        return $t;
    }

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(Calculation::class);
    }

    public function modelFile(): BelongsTo
    {
        return $this->belongsTo(ModelFile::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(InquiryDispatch::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Quote::class, 'inquiry_id');
    }

    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class);
    }

    public function acceptedQuote(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'accepted_quote_id');
    }

    public function isOpenForOffers(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_OFFERED], true)
            && (! $this->expires_at || $this->expires_at->isFuture());
    }

    /** Customer may see this inquiry: owner, or anyone with the token (link = access, like a shared calculation). */
    public function accessibleBy(?User $user): bool
    {
        return true;
    }
}
