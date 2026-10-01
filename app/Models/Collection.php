<?php

namespace App\Models;

use App\Support\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as Items;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/** A hand-picked set of models from both catalogues ("Gifts for teachers", "Desk organisers"). */
class Collection extends Model
{
    protected $fillable = ['legacy_id', 'slug', 'title', 'description', 'cover_path', 'visible', 'position'];

    protected $casts = ['title' => 'array', 'description' => 'array', 'visible' => 'bool', 'position' => 'int'];

    protected static function booted(): void
    {
        // which languages have collections at all is remembered (CollectionPageController::languages)
        static::saved(fn () => Cache::forget('collections.languages'));
        static::deleted(fn () => Cache::forget('collections.languages'));
    }

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

    public function publicUrl(?string $locale = null): string
    {
        return localized_route('collections.show', ['collection' => $this->slug], $locale);
    }

    /**
     * What the public page shows, in order: every item whose model can still be shown, as a plain card.
     * A designer's card that the farm prints leads to /models/{slug}, an inspiration model to /model/{slug}.
     *
     * @return Items<int, array{type: string, id: int, title: string, url: string, image: ?string, printable: bool, author: ?string}>
     */
    public function entries(?string $locale = null): Items
    {
        $locale ??= Locales::current();

        return $this->items()->with(['designerModel.images', 'designerModel.profile', 'catalogModel'])->get()->map(function (CollectionItem $item) use ($locale) {
            if ($card = $item->designerModel) {
                // only cards the farm can print right now, in a language the card has
                return $card->isPrintable() && in_array($locale, $card->locales(), true)
                    ? ['type' => 'designer_model', 'id' => $card->id, 'title' => $card->title, 'url' => $card->publicUrl($locale), 'image' => $card->coverUrl(), 'printable' => true, 'author' => $card->profile?->display_name]
                    : null;
            }
            $model = $item->catalogModel;
            if (! $model || ! $model->getAttribute('visible') || ! $model->slug || ! in_array($locale, $model->locales(), true)) {
                return null;
            }

            return ['type' => 'catalog_model', 'id' => $model->id, 'title' => $model->title, 'url' => localized_route('catalog.show', ['catalogModel' => $model->slug], $locale), 'image' => $model->thumbUrl(), 'printable' => false, 'author' => $model->author_name];
        })->filter()->values();
    }

    /** The cover: the uploaded one, else the picture of the first model. */
    public function coverUrl(): ?string
    {
        if ($this->cover_path) {
            return Storage::disk('public')->url($this->cover_path);
        }

        return $this->entries(Locales::DEFAULT)->first()['image'] ?? null;
    }
}
