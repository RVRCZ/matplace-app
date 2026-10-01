<?php

namespace App\Models;

use App\Support\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A category shared by both catalogues (the old site's tree; a designer picks one for a card). */
class CatalogCategory extends Model
{
    protected $fillable = ['legacy_id', 'slug', 'name', 'parent_id', 'position'];

    protected $casts = ['name' => 'array', 'position' => 'int'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function label(?string $locale = null): string
    {
        $names = (array) $this->name;
        $locale ??= Locales::current();

        return (string) ($names[$locale] ?? $names['en'] ?? $names[Locales::DEFAULT] ?? $this->slug);
    }

    /** This category and everything under it (two levels are all the tree has). */
    public function withChildrenIds(): array
    {
        return array_merge([$this->id], self::where('parent_id', $this->id)->pluck('id')->all());
    }
}
