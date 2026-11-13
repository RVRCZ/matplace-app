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
    public const INDEX_NEEDS = ['enriched', 'value', 'price', 'file'];

    /** What config asks when it says nothing: a text of our own and something only we can say of the model (12 Nov 2026). */
    public const INDEX_DEFAULT = ['enriched', 'value'];

    /** @return list<string> what config asks of an indexable page, in the order of INDEX_NEEDS */
    public static function indexNeeds(): array
    {
        return array_values(array_intersect(self::INDEX_NEEDS, (array) config('catalog.index_requires', self::INDEX_DEFAULT)));
    }

    /**
     * Pages search engines may index: shown, and with everything config asks for. By default (12 Nov 2026, docs/AP.md
     * §14) a text of our own AND a value of our own: the price from a slice of the model's own file, a photo of a print
     * the farm made of it, or the author's printable card. The older conditions stay for the switch: `price` (also the
     * old site's slicer figures) and `file`. The sitemap lists exactly these pages; every other one says
     * "noindex, follow" and carries no Product (Roman, 18 Oct and 12 Nov 2026).
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
        if (in_array('value', $needs, true)) {
            $query->where(fn (Builder $w) => $w
                ->where(fn (Builder $s) => $s->whereNotNull('catalog_models.sliced_at')->where('catalog_models.slice_minutes', '>', 0)->where('catalog_models.slice_grams', '>', 0))
                ->orWhereHas('designerModels', fn (Builder $d) => $d->printable())
                ->orWhereExists(fn ($o) => self::printPhotoOrders($o->from('farm_orders'))->whereColumn('farm_orders.catalog_model_id', 'catalog_models.id')));
        }
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
        $card = array_intersect(['file', 'price', 'value'], $needs) ? $this->printableCard() : null;
        $gaps = [];
        if (in_array('enriched', $needs, true) && ! $this->isEnriched()) {
            $gaps[] = 'enriched';
        }
        if (in_array('value', $needs, true) && ! ($this->hasSlice() || $card || $this->hasPrintPhoto())) {
            $gaps[] = 'value';
        }
        if (in_array('price', $needs, true) && ! (app(ModelPricing::class)->anyMaterial() && (($this->est_minutes > 0 && $this->est_grams > 0) || $this->hasSlice() || ($card && ! empty($card->slice_summary))))) {
            $gaps[] = 'price';
        }
        if (in_array('file', $needs, true) && ! ($this->file_available || $this->model_file_id || $card)) {
            $gaps[] = 'file';
        }

        return $gaps;
    }

    /**
     * Orders whose photographed print may stand on a model's page: paid, finished, with a photo, and the customer agreed
     * to publishing (the model is somebody's choice, unlike a box made from sizes alone). One condition for the index
     * rule and for the page's block (App\Domain\Catalog\CatalogPhotos).
     *
     * @template T of \Illuminate\Database\Query\Builder|Builder
     *
     * @param  T  $orders
     * @return T
     */
    public static function printPhotoOrders($orders)
    {
        return $orders->whereNotNull('farm_orders.paid_at')->whereNotNull('farm_orders.photo_path')->where('farm_orders.video_consent', true)
            ->whereIn('farm_orders.status', [FarmOrder::STATUS_DONE, FarmOrder::STATUS_HANDED_OVER]);
    }

    /** The farm printed this model for somebody and photographed it (and may show the photo). */
    public function hasPrintPhoto(): bool
    {
        return self::printPhotoOrders(FarmOrder::query())->where('farm_orders.catalog_model_id', $this->id)->exists();
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
        return $this->thumbnail_path ? Storage::disk('public')->url($this->thumbnail_path) : (self::directPreview($this->preview_url) ?: null);
    }

    /**
     * A preview that was imported as an address of the old site on our own host (https://matplace.com/assets/thumbs/…)
     * goes straight to where the old site lives now (config/legacy.php `host`): the page never sends a browser or
     * Googlebot-Image through our redirect, and that address on our host can answer "gone" (docs/AP.md §14).
     */
    public static function directPreview(?string $url): ?string
    {
        $url = (string) $url;
        $own = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        if ($url === '' || $own === '' || ! in_array($host, [$own, 'www.'.$own], true) || ! preg_match('~^/(assets|thumbnails|blog-img)/~', $path)) {
            return $url !== '' ? $url : null;
        }

        return rtrim((string) config('legacy.host'), '/').$path;
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
