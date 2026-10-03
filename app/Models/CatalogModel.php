<?php

namespace App\Models;

use App\Engines\DTO\ModelCandidate;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * The inspiration catalogue: models that live elsewhere (Printables, MakerWorld, MakerOnline, Cults3D), taken over
 * from the old site with their addresses (/model/{slug}). A page links to the source; the customer brings the file and
 * prints it on a rented printer. Every licence allows that for one's own use; `license_restricted` marks the ones
 * (non-commercial, paid, unknown) whose prints must not be sold, and the page says so.
 */
class CatalogModel extends Model
{
    /** Licences that allow commercial use, i.e. selling the prints (CC BY and CC BY-SA with attribution). */
    public const COMMERCIAL_LICENSES = ['cc0', 'cc_by', 'cc_by_sa', 'free_commercial'];

    protected $fillable = [
        'legacy_id', 'slug', 'title', 'description', 'source_locale', 'keywords', 'source', 'external_id', 'external_url', 'preview_url',
        'thumbnail_path', 'images', 'license', 'license_restricted', 'author_name', 'category', 'category_id', 'tags', 'view_count',
        'est_grams', 'est_minutes', 'max_mm', 'file_available', 'model_file_id', 'visible', 'ai_confidence', 'ai_mismatch', 'ai_reason',
    ];

    protected $casts = [
        'description' => 'array', 'images' => 'array', 'tags' => 'array', 'file_available' => 'bool', 'visible' => 'bool',
        'license_restricted' => 'bool', 'view_count' => 'int', 'ai_mismatch' => 'bool', 'ai_confidence' => 'float',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function categoryRow(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class, 'category_id');
    }

    /** Designers' cards of this very model (imported from the same address). */
    public function designerModels(): HasMany
    {
        return $this->hasMany(DesignerModel::class);
    }

    public function scopeShown(Builder $query): Builder
    {
        return $query->where('catalog_models.visible', true)->whereNotNull('catalog_models.slug');
    }

    public static function restricts(?string $license): bool
    {
        return ! in_array((string) $license, self::COMMERCIAL_LICENSES, true);
    }

    /** CC BY and CC BY-SA oblige whoever prints the model for somebody to name its author. */
    public function needsAttribution(): bool
    {
        return in_array($this->license, ['cc_by', 'cc_by_sa'], true);
    }

    /** Languages the page exists in: Czech always (the old addresses), the others only with a text of their own. */
    public function locales(): array
    {
        $d = (array) $this->description;

        return array_values(array_filter(Locales::SUPPORTED, fn (string $l) => $l === Locales::DEFAULT || trim((string) ($d[$l] ?? '')) !== ''));
    }

    /** Text for a language; the Czech page falls back to the text of the source when nobody translated it yet. */
    public function describe(?string $locale = null): string
    {
        $d = (array) $this->description;
        $locale ??= Locales::current();

        return trim((string) ($d[$locale] ?? ($locale === Locales::DEFAULT ? ($d[$this->source_locale] ?? $d['en'] ?? reset($d) ?: '') : '')));
    }

    /** Language the text returned by describe() is really written in (for the lang attribute of the paragraph). */
    public function describedIn(?string $locale = null): string
    {
        $d = (array) $this->description;
        $locale ??= Locales::current();

        return trim((string) ($d[$locale] ?? '')) !== '' ? $locale : (string) ($this->source_locale ?: 'en');
    }

    public function thumbUrl(): ?string
    {
        return $this->thumbnail_path ? Storage::disk('public')->url($this->thumbnail_path) : ($this->preview_url ?: null);
    }

    /** @return list<string> the thumbnail first, then the further pictures */
    public function imageUrls(): array
    {
        $urls = array_map(fn (string $p) => Storage::disk('public')->url($p), (array) $this->images);

        return array_values(array_unique(array_filter(array_merge([$this->thumbUrl()], $urls))));
    }

    /** "Model: title, author, address, licence": written into the note of an order made from this page. */
    public function attribution(): string
    {
        return 'Model: '.implode(', ', array_filter([$this->title, $this->author_name, $this->external_url, $this->license ? strtoupper(str_replace('_', ' ', $this->license)) : null]));
    }

    /** A designer's printable card of this model, when its author is on matplace. */
    public function printableCard(): ?DesignerModel
    {
        return $this->designerModels()->printable()->with('profile')->first();
    }

    /** A web address a visitor can open; the index also holds non-web references (drive folders). */
    public function hasWebLink(): bool
    {
        return (bool) preg_match('~^https?://~i', (string) $this->external_url);
    }

    /** Named after the site the link opens, never after us: "Open on matplace" must not lead to Printables. */
    public function linkOrigin(): ?string
    {
        if (! $this->hasWebLink()) {
            return null;
        }
        $host = strtolower((string) parse_url((string) $this->external_url, PHP_URL_HOST));
        foreach (['printables' => 'printables', 'makerworld' => 'makerworld', 'makeronline' => 'makeronline', 'cults3d' => 'cults3d', 'thingiverse' => 'thingiverse', 'thangs' => 'thangs'] as $needle => $origin) {
            if (str_contains($host, $needle)) {
                return $origin;
            }
        }

        return preg_replace('/^www\./', '', $host) ?: null;
    }

    public function toCandidate(float $score = 0.0): ModelCandidate
    {
        return new ModelCandidate(
            source: 'local',
            externalId: (string) $this->id,
            title: $this->title,
            previewUrl: $this->thumbUrl(),
            // with a page of its own the card opens it; without one (no slug yet) it opens the source
            externalUrl: $this->slug ? route('catalog.show', $this->slug) : ($this->hasWebLink() ? $this->external_url : null),
            license: $this->license,
            authorName: $this->author_name,
            localModelId: $this->id,
            score: $score,
            fileAvailable: $this->file_available,
            origin: $this->slug ? 'inspiration' : $this->linkOrigin(),
        );
    }
}
