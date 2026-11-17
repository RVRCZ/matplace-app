<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Somebody who shares a link of ours and earns a commission on the orders it brings (docs/AT.md). */
class Partner extends Model
{
    /** A code is 1-20 of A-Z 0-9 _ - ; the link is /r/CODE. */
    public const CODE_PATTERN = '/^[A-Z0-9_-]{1,20}$/';

    protected $fillable = ['user_id', 'name', 'code', 'email', 'commission_percent', 'cookie_days', 'active', 'channels', 'payout_details', 'notes', 'terms_version', 'terms_accepted_at'];

    protected $casts = ['commission_percent' => 'float', 'cookie_days' => 'int', 'active' => 'bool', 'terms_accepted_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(PartnerClick::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(PartnerCommission::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(FarmOrder::class);
    }

    public static function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    public function link(): string
    {
        return url('/r/'.$this->code);
    }
}
