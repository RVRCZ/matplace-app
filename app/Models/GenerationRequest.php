<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class GenerationRequest extends Model
{
    protected $fillable = [
        'paid_credit',
        'token', 'owner_user_id', 'anonymous_session_id', 'ip', 'type', 'prompt', 'image_path', 'views', 'description', 'engine',
        'external_id', 'status', 'cost_cents', 'result_model_file_id', 'error', 'image_sha256', 'target_mm', 'progress', 'source_request_id',
    ];

    protected $casts = ['description' => 'array', 'views' => 'array', 'target_mm' => 'int', 'progress' => 'int', 'cost_cents' => 'int'];

    /** Every stored photo of this request: front (image_path) plus the extra sides, view → relative path on the local disk. */
    public function photoPaths(): array
    {
        return array_filter(['front' => $this->image_path] + (array) ($this->views ?? []));
    }

    /** Delete the photos from disk and forget their paths (personal photos are not kept once the run is over). */
    public function forgetPhotos(): void
    {
        $paths = array_values($this->photoPaths());
        if ($paths) {
            Storage::disk('local')->delete($paths);
        }
        $this->update(['image_path' => null, 'views' => null]);
    }

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
