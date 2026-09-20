<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GenerationRequest extends Model
{
    protected $fillable = [
        'token', 'owner_user_id', 'anonymous_session_id', 'ip', 'type', 'prompt', 'image_path', 'description', 'engine',
        'external_id', 'status', 'cost_cents', 'result_model_file_id', 'error', 'image_sha256', 'target_mm', 'progress', 'source_request_id',
    ];

    protected $casts = ['description' => 'array', 'target_mm' => 'int', 'progress' => 'int', 'cost_cents' => 'int'];

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function resultFile(): BelongsTo
    {
        return $this->belongsTo(ModelFile::class, 'result_model_file_id');
    }

    /** How many runs this visitor started today (IP + session + user combined, whichever is higher). */
    public static function usedToday(?string $ip, ?int $sessionId, ?int $userId): int
    {
        $since = now()->startOfDay();
        // cached answers cost nothing and do not count
        $q = static::where('created_at', '>=', $since)->whereIn('type', ['image', 'text'])->where('engine', 'not like', '%+cache');
        if ($userId) {
            return (clone $q)->where('owner_user_id', $userId)->count(); // accounts are counted on their own (shared IPs!)
        }
        $guest = (clone $q)->whereNull('owner_user_id');

        return max(
            $ip ? (clone $guest)->where('ip', $ip)->count() : 0,
            $sessionId ? (clone $guest)->where('anonymous_session_id', $sessionId)->count() : 0,
        );
    }
}
