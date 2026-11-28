<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One item of a print's video media list (App\Domain\YouTube\StudioMedia): shown in `position` order, left out of the
 * built videos when not `included`. The app's own items (design, source, sliced, growth, photo) have no path: their
 * picture is found when the video is built; a clip or a picture the admin added has its file on the farm disk.
 */
class FarmVideoMedia extends Model
{
    protected $table = 'farm_video_media';

    public const KIND_DESIGN = 'design';     // the model as it was designed (the browser's coloured preview, else drawn by the server)

    public const KIND_SOURCE = 'source';     // the customer's photo the model was made from (only with the consent that names it)

    public const KIND_SLICED = 'sliced';     // the plate as it was sliced, every part in its filament colour

    public const KIND_GROWTH = 'growth';     // the print growing, from the camera frames

    public const KIND_PHOTO = 'photo';       // the photographed piece from the photo box

    public const KIND_CLIP = 'clip';         // a video the admin added (recorded or uploaded)

    public const KIND_IMAGE = 'image';       // a picture the admin added

    /** Made by the app: may be left out, never deleted. */
    public const AUTO = [self::KIND_DESIGN, self::KIND_SOURCE, self::KIND_SLICED, self::KIND_GROWTH, self::KIND_PHOTO];

    protected $fillable = ['farm_order_id', 'kind', 'path', 'mime', 'size', 'position', 'included', 'seconds', 'sound', 'meta'];

    protected $casts = ['included' => 'bool', 'sound' => 'bool', 'seconds' => 'float', 'size' => 'int', 'position' => 'int', 'meta' => 'array'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(FarmOrder::class, 'farm_order_id');
    }

    public function isAuto(): bool
    {
        return in_array($this->kind, self::AUTO, true);
    }

    public function isMoving(): bool
    {
        return in_array($this->kind, [self::KIND_GROWTH, self::KIND_CLIP], true);
    }
}
