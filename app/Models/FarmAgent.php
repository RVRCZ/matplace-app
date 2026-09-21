<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A farm-agent installation (one per site). It authenticates with a bearer token; we only keep its hash. */
class FarmAgent extends Model
{
    protected $fillable = ['name', 'token_hash', 'version', 'last_ip', 'last_heartbeat_at', 'revoked_at'];

    protected $casts = ['last_heartbeat_at' => 'datetime', 'revoked_at' => 'datetime'];

    protected $hidden = ['token_hash'];

    public function printers(): HasMany
    {
        return $this->hasMany(FarmPrinter::class);
    }

    /** @return array{0: self, 1: string} the agent and the plain token, which is never stored */
    public static function issue(string $name): array
    {
        $token = 'mpa_'.Str::random(48);

        return [self::create(['name' => $name, 'token_hash' => hash('sha256', $token)]), $token];
    }

    public function rotateToken(): string
    {
        $token = 'mpa_'.Str::random(48);
        $this->update(['token_hash' => hash('sha256', $token), 'revoked_at' => null]);

        return $token;
    }

    public static function findByToken(?string $token): ?self
    {
        if (! $token || ! str_starts_with($token, 'mpa_')) {
            return null;
        }

        return self::where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->first();
    }
}
