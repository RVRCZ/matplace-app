<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A photo of our own print offered as the picture of a colour (TODO #71, docs/AL.md §21): taken by the photo box for an
 * order, copied to farm/colors/proposals/ so the order's clean-up does not take it, and waiting for the admin's yes or no
 * in the materials list. An approved one becomes the colour's `photo_path` with `photo_source` print.
 */
class FarmColorPhotoProposal extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = ['farm_color_id', 'farm_order_id', 'path', 'status', 'note', 'decided_at', 'decided_by'];

    protected $casts = ['decided_at' => 'datetime'];

    public function color(): BelongsTo
    {
        return $this->belongsTo(FarmColor::class, 'farm_color_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(FarmOrder::class, 'farm_order_id');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
