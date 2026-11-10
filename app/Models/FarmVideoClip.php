<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmVideoClip extends Model
{
    public const KINDS = ['removal', 'turn', 'detail', 'with_source', 'in_hand'];

    protected $fillable = ['farm_video_id', 'farm_order_id', 'kind', 'path', 'mime', 'size'];

    protected $casts = ['size' => 'int'];

    public function video(): BelongsTo
    {
        return $this->belongsTo(FarmVideo::class, 'farm_video_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(FarmOrder::class, 'farm_order_id');
    }
}
