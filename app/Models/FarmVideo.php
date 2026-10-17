<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A print video on YouTube: the Short or the 16:9 of one farm order, or the weekly cut (no order). Moves only through App\Domain\YouTube\FarmVideos:
 *
 *   pending (waits for an admin, nothing on YouTube yet) → queued (approved) → uploading → scheduled → published | rejected
 *   scheduled: on YouTube, private until `scheduled_at` when YouTube makes it public (the stats run notices)
 *   uploaded: on YouTube but private (YouTube kept it so, or an older copy); the admin publishes it from there
 *   any state → withdrawn (the customer took the consent back; the YouTube copy is deleted)
 */
class FarmVideo extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_UPLOADING = 'uploading';

    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_FAILED = 'failed';

    /** The square Short (Reels and the Facebook page take it too) */
    public const KIND_SHORT = 'short';

    /** The classic 16:9 of the same print, its own video on the channel */
    public const KIND_WIDE = 'wide';

    /** "Tento týden na farmě": the prints of a week, no order (path, meta: week and orders) */
    public const KIND_WEEKLY = 'weekly';

    public const KINDS = [self::KIND_SHORT, self::KIND_WIDE, self::KIND_WEEKLY];

    protected $fillable = ['farm_order_id', 'kind', 'path', 'meta', 'status', 'youtube_id', 'title', 'description', 'error', 'uploaded_at', 'published_at', 'decided_by', 'decided_at', 'approved_at', 'scheduled_at', 'share', 'views', 'likes', 'comments', 'stats_at', 'score', 'music'];

    protected $casts = ['uploaded_at' => 'datetime', 'published_at' => 'datetime', 'decided_at' => 'datetime', 'approved_at' => 'datetime', 'scheduled_at' => 'datetime', 'share' => 'array', 'meta' => 'array', 'stats_at' => 'datetime',
        'views' => 'int', 'likes' => 'int', 'comments' => 'int', 'score' => 'int'];

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
