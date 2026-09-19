<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AnonymousSession extends Model
{
    public const COOKIE = 'mp_sid';

    public const COOKIE_MINUTES = 60 * 24 * 30;

    protected $fillable = ['token', 'ip', 'user_agent', 'claimed_by_user_id', 'last_seen_at'];

    protected $casts = ['last_seen_at' => 'datetime'];

    public static function start(?string $ip, ?string $userAgent): self
    {
        return static::create([
            'token' => Str::random(48),
            'ip' => $ip ? substr($ip, 0, 45) : null,
            'user_agent' => $userAgent ? substr($userAgent, 0, 255) : null,
            'last_seen_at' => now(),
        ]);
    }

    public function modelFiles(): HasMany
    {
        return $this->hasMany(ModelFile::class);
    }

    public function calculations(): HasMany
    {
        return $this->hasMany(Calculation::class);
    }
}
