<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * One thing a visitor handed in on the home page (TODO #78, docs/AN.md §22): a photo, a 3D file or words, and what the
 * assistant made of it. The token is the only handle the browser has (the tool page fetches the photo with it).
 */
class Intake extends Model
{
    public const KIND_PHOTO = 'photo';

    public const KIND_FILE = 'file';

    public const KIND_TEXT = 'text';

    public const ROUTE_TOOL = 'tool';

    public const ROUTE_SHAPE = 'shape';

    public const ROUTE_HUMAN = 'human';

    public const ROUTE_FILE = 'file';

    public const STATUS_NEW = 'new';

    public const STATUS_OPENED = 'opened';

    public const STATUS_HUMAN = 'human';

    public const STATUS_CLOSED = 'closed';

    /** Where an intake's files live on the local disk. */
    public const DIR = 'intake';

    /** Photos of an intake go after this many days (matplace:prune); the row stays. */
    public const PHOTO_DAYS = 30;

    protected $fillable = [
        'token', 'anonymous_session_id', 'user_id', 'kind', 'photo_path', 'photo_model_path', 'model_file_id', 'text', 'locale',
        'route', 'tool_key', 'params', 'confidence', 'summary', 'answer', 'wants_shape', 'model', 'tokens_in', 'tokens_out', 'cost_czk',
        'duration_ms', 'status', 'opened_at', 'photos_pruned_at',
    ];

    protected $casts = [
        'params' => 'array', 'answer' => 'array', 'wants_shape' => 'bool', 'confidence' => 'float', 'cost_czk' => 'float',
        'tokens_in' => 'int', 'tokens_out' => 'int', 'duration_ms' => 'int', 'opened_at' => 'datetime', 'photos_pruned_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public static function newToken(): string
    {
        do {
            $t = Str::random(24);
        } while (static::where('token', $t)->exists());

        return $t;
    }

    public function modelFile(): BelongsTo
    {
        return $this->belongsTo(ModelFile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function request(): HasOne
    {
        return $this->hasOne(IntakeRequest::class);
    }

    public function dir(): string
    {
        return self::DIR.'/'.$this->token;
    }

    /** The picture the tool pages and the model get: the normalised JPEG, else the upload itself. */
    public function picturePath(): ?string
    {
        return $this->photo_model_path ?: $this->photo_path;
    }

    public function hasPhoto(): bool
    {
        return $this->picturePath() !== null && $this->photos_pruned_at === null;
    }
}
