<?php

namespace App\Models;

use App\Domain\Catalog\ModelPricing;
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
 * (non-commercial, paid, unknown) whose prints must not be sold, and the page says so. A model whose file we hold is
 * sliced once (catalog:slice): its page then names the print time and the price in three materials (docs/AN.md §13).
 */
class CatalogModel extends Model
{
    /** Licences that allow commercial use, i.e. selling the prints (CC BY and CC BY-SA with attribution). */
    public const COMMERCIAL_LICENSES = ['cc0', 'cc_by', 'cc_by_sa', 'free_commercial'];

    protected $fillable = [
        'legacy_id', 'slug', 'title', 'description', 'source_locale', 'keywords', 'source', 'external_id', 'external_url', 'preview_url',
        'thumbnail_path', 'images', 'license', 'license_restricted', 'author_name', 'category', 'category_id', 'tags', 'view_count',
        'est_grams', 'est_minutes', 'max_mm', 'file_available', 'model_file_id', 'visible', 'ai_confidence', 'ai_mismatch', 'ai_reason',
        'enriched_at', 'enriched_price_czk',
        'slice_minutes', 'slice_grams', 'slice_material', 'sliced_at', 'slice_error', 'slice_calculation_id',
    ];

    protected $casts = [
        'description' => 'array', 'images' => 'array', 'tags' => 'array', 'file_available' => 'bool', 'visible' => 'bool',
        'license_restricted' => 'bool', 'view_count' => 'int', 'ai_mismatch' => 'bool', 'ai_confidence' => 'float',
        'enriched_at' => 'datetime', 'enriched_price_czk' => 'float',
        'slice_minutes' => 'int', 'slice_grams' => 'float', 'sliced_at' => 'datetime',
    ];

    /** The key of `description` that keeps the source's own text once a text of ours replaced it (not a language). */
    public const ORIGINAL = 'original';

    /** Everything a page may be asked to have before search engines get it (config/catalog.php `index_requires`). */
    public const INDEX_NEEDS = ['enriched', 'price', 'file'];

    /** @return list<string> what config asks of an indexable page, in the order of INDEX_NEEDS */
    public static function indexNeeds(): array
    {
        return array_values(array_intersect(self::INDEX_NEEDS, (array) config('catalog.index_requires', self::INDEX_NEEDS)));
    }

    /**
     * Pages search engines may index: shown, and with everything config asks for – a text of our own, a real price
     * (the author's printable card, or the old site's slicer figures with a kind loaded on the farm), the file. The
     * sitemap lists exactly these; every other page says "noindex, follow" (Roman, 18 Oct 2026).
     */
    public function scopeIndexable(Builder $query): Builder
    {
        $needs = self::indexNeeds();
        $query->shown();
        if (in_array('enriched', $needs, true)) {
            $query->whereNotNull('catalog_models.enriched_at');
        }

        return $this->scopeIndexReady($query, array_values(array_diff($needs, ['enriched'])));
    }

    /**
     * The conditions of an indexable page that enrichment cannot add (the file, the price): what `catalog:enrich`
     * picks by, so that no money goes into a page that could not be indexed anyway.
     *
     * @param  list<string>|null  $needs  null = what config asks, minus `enriched`
     */
    public function scopeIndexReady(Builder $query, ?array $needs = null): Builder
    {
        $needs ??= array_values(array_diff(self::indexNeeds(), ['enriched']));
        if (in_array('file', $needs, true)) {
            $query->where(fn (Builder $w) => $w->where('catalog_models.file_available', true)->orWhereNotNull('catalog_models.model_file_id')
                ->orWhereHas('designerModels', fn (Builder $d) => $d->printable()));
        }
        if (in_array('price', $needs, true)) {
            if (! app(ModelPricing::class)->anyMaterial()) {
                return $query->whereRaw('1 = 0');   // nothing loaded on the farm: no price can be named for anything
            }
            $query->where(fn (Builder $w) => $w->where(fn (Builder $e) => $e->where('catalog_models.est_minutes', '>', 0)->where('catalog_models.est_grams', '>', 0))
                ->orWhere(fn (Builder $s) => $s->whereNotNull('catalog_models.sliced_at')->where('catalog_models.slice_minutes', '>', 0))
                ->orWhereHas('designerModels', fn (Builder $d) => $d->printable()->whereNotNull('slice_summary')));
        }

        return $query;
    }

    /** May search engines index this page? The same rule as the sitemap's (scopeIndexable), asked for one model. */
    public function isIndexable(): bool
    {
        return (bool) $this->getAttribute('visible') && $this->slug !== null && static::query()->indexable()->whereKey($this->id)->exists();
    }

    /**
     * What this page still lacks to be indexed, of what config asks (`enriched`, `price`, `file`); empty = indexable.
     *
     * @return list<string>
     */
    public function indexGaps(): array
    {
        $needs = self::indexNeeds();
        $card = in_array('file', $needs, true) || in_array('price', $needs, true) ? $this->printableCard() : null;
        $gaps = [];
        if (in_array('enriched', $needs, true) && ! $this->isEnriched()) {
            $gaps[] = 'enriched';
        }
        if (in_array('price', $needs, true) && ! (app(ModelPricing::class)->anyMaterial() && (($this->est_minutes > 0 && $this->est_grams > 0) || $this->hasSlice() || ($card && ! empty($card->slice_summary))))) {
            $gaps[] = 'price';
        }
        if (in_array('file', $needs, true) && ! ($this->file_available || $this->model_file_id || $card)) {
            $gaps[] = 'file';
        }

        return $gaps;
    }

    /** A text of our own was written for this model (catalog:enrich or the admin). */
    public function isEnriched(): bool
    {
        return $this->enriched_at !== null;
    }

    /**
     * The source's own text, kept beside ours once we wrote one: shown folded under "the author's original description".
     *
     * @return array{text: string, locale: string}|null
     */
    public function original(): ?array
    {
        $d = (array) $this->description;
        $text = trim((string) ($d[self::ORIGINAL] ?? ''));

        return $text !== '' ? ['text' => $text, 'locale' => (string) ($this->source_locale ?: 'en')] : null;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function categoryRow(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class, 'category_id');
    }

    /** The calculation the slice of the model's own file came from (catalog:slice): its page /c/<token> is where a visitor orders. */
    public function sliceCalculation(): BelongsTo
    {
        return $this->belongsTo(Calculation::class, 'slice_calculation_id');
    }

    /** The file we hold of this model, when we hold one. */
    public function modelFile(): BelongsTo
    {
        return $this->belongsTo(ModelFile::class, 'model_file_id');
    }

    /** The model's own file was sliced (catalog:slice, docs/AN.md §13): the page names the time and the price in three materials from it. */
    public function hasSlice(): bool
    {
        return $this->sliced_at !== null && (int) $this->slice_minutes > 0 && (float) $this->slice_grams > 0;
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
