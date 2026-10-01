<?php

namespace App\Models;

use App\Support\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A hand-picked set of models from both catalogues ("Gifts for teachers", "Desk organisers"). */
class Collection extends Model
{
    protected $fillable = ['legacy_id', 'slug', 'title', 'description', 'cover_path', 'visible', 'position'];

    protected $casts = ['title' => 'array', 'description' => 'array', 'visible' => 'bool', 'position' => 'int'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function items(): HasMany
    {
        return $this->hasMany(CollectionItem::class)->orderBy('position');
    }

    public function text(string $field, ?string $locale = null): string
    {
        $values = (array) $this->{$field};
        $locale ??= Locales::current();

        return trim((string) ($values[$locale] ?? ''));
    }

    /** Languages the collection has a title in. */
    public function locales(): array
    {
        return array_values(array_filter(Locales::SUPPORTED, fn (string $l) => trim((string) ($this->title[$l] ?? '')) !== ''));
    }
}
