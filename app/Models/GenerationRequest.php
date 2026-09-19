<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GenerationRequest extends Model
{
    protected $fillable = [
        'token', 'owner_user_id', 'anonymous_session_id', 'ip', 'type', 'prompt', 'image_path', 'description', 'engine',
        'external_id', 'status', 'cost_cents', 'result_model_file_id', 'error',
    ];

    protected $casts = ['description' => 'array'];

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
        $q = static::where('created_at', '>=', $since)->where('type', '!=', 'describe');
        $counts = [
            $ip ? (clone $q)->where('ip', $ip)->count() : 0,
            $sessionId ? (clone $q)->where('anonymous_session_id', $sessionId)->count() : 0,
            $userId ? (clone $q)->where('owner_user_id', $userId)->count() : 0,
        ];

        return max($counts);
    }
}
