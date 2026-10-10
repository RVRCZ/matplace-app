<?php

namespace App\Models;

use App\Domain\Tools\ToolVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The admin's switch of one tool of config/tools.php: shown to the public or not, with a note why.
 *
 * @property string $tool
 * @property bool $public
 * @property string|null $note
 * @property int|null $updated_by
 */
class ToolFlag extends Model
{
    protected $fillable = ['tool', 'public', 'note', 'updated_by'];

    protected $casts = ['public' => 'bool'];

    protected static function booted(): void
    {
        // the switches are read from the cache: a change shows at once, not after the cache runs out
        static::saved(fn () => ToolVisibility::forget());
        static::deleted(fn () => ToolVisibility::forget());
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
