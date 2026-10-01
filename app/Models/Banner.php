<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** A picture with a link on the home page; shown in one language or (locale null) in all of them. */
class Banner extends Model
{
    protected $fillable = ['title', 'image_path', 'url', 'locale', 'active', 'position'];

    protected $casts = ['active' => 'bool', 'position' => 'int'];

    /** The banners a page in this language shows, in their order. */
    public function scopeFor(Builder $query, string $locale): Builder
    {
        return $query->where('active', true)->where(fn (Builder $q) => $q->whereNull('locale')->orWhere('locale', $locale))->orderBy('position')->orderBy('id');
    }

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }
}
