<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** A picture of a portfolio card on the public disk: "<n>.webp" (large) and "<n>_s.webp" (for grids). */
class DesignerModelImage extends Model
{
    protected $fillable = ['designer_model_id', 'path', 'position', 'is_cover'];

    protected $casts = ['is_cover' => 'bool', 'position' => 'int'];

    public function model(): BelongsTo
    {
        return $this->belongsTo(DesignerModel::class, 'designer_model_id');
    }

    public function smallPath(): string
    {
        return preg_replace('/\.webp$/', '_s.webp', $this->path);
    }

    public function url(bool $small = false): string
    {
        return Storage::disk('public')->url($small ? $this->smallPath() : $this->path);
    }
}
