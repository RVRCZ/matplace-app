<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The time-lapse of one farm order on YouTube. Moves only through App\Domain\YouTube\FarmVideos:
 *
 *   queued → uploading → uploaded (private, waits for an admin) → published | rejected
 *   any state → withdrawn (the customer took the consent back; the YouTube copy is deleted)
 */
class FarmVideo extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_UPLOADING = 'uploading';

    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_FAILED = 'failed';

    protected $fillable = ['farm_order_id', 'status', 'youtube_id', 'title', 'description', 'error', 'uploaded_at', 'published_at', 'decided_by', 'decided_at'];

    protected $casts = ['uploaded_at' => 'datetime', 'published_at' => 'datetime', 'decided_at' => 'datetime'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(FarmOrder::class, 'farm_order_id');
    }

    public function watchUrl(): ?string
    {
        return $this->youtube_id ? 'https://www.youtube.com/watch?v='.$this->youtube_id : null;
    }

    public function studioUrl(): ?string
    {
        return $this->youtube_id ? 'https://studio.youtube.com/video/'.$this->youtube_id.'/edit' : null;
    }
}
