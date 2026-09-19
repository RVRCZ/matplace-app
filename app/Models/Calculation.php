<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Calculation extends Model
{
    public const STATUS_ROUGH = 'rough';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SLICING = 'slicing';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'token', 'model_file_id', 'owner_user_id', 'anonymous_session_id', 'params', 'params_hash',
        'rough', 'slicer', 'slicer_engine', 'prices', 'status', 'error',
    ];

    protected $casts = [
        'params' => 'array',
        'rough' => 'array',
        'slicer' => 'array',
        'prices' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function modelFile(): BelongsTo
    {
        return $this->belongsTo(ModelFile::class);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }
}
