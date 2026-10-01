<?php

namespace App\Models;

use App\Support\Locales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One card of a designer's portfolio. Without a file it is a picture with a link to where the model lives
 * (Printables, MakerWorld). With an uploaded and checked file the farm can print it and the designer earns
 * `royalty_czk` per piece.
 */
class DesignerModel extends Model
{
    public const SOURCES = ['printables', 'makerworld', 'manual'];

    public const FILE_NONE = 'none';

    public const FILE_CHECKING = 'checking';

    public const FILE_READY = 'ready';

    public const FILE_FAILED = 'failed';

    /** Licences a designer can give to a free download. */
    public const DOWNLOAD_LICENSES = ['cc_by', 'cc_by_sa', 'cc_by_nc', 'cc0'];

    public const MAX_IMAGES = 8;

    protected $attributes = ['source' => 'manual', 'file_status' => self::FILE_NONE, 'visible' => true, 'royalty_czk' => 25];

    protected $fillable = [
        'designer_profile_id', 'title', 'slug', 'description', 'source', 'source_locale', 'external_url', 'external_id', 'license_source',
        'is_remix', 'remix_source_url', 'remix_confirmed_at', 'model_file_id', 'royalty_czk', 'download_allowed', 'download_license',
        'author_confirmed_at', 'visible', 'catalog_model_id', 'slice_summary', 'file_status', 'file_check', 'tags', 'source_files',
        'catalog_category_id', 'max_mm',
    ];

    protected $casts = [
        'description' => 'array', 'is_remix' => 'bool', 'download_allowed' => 'bool', 'visible' => 'bool', 'royalty_czk' => 'float',
        'slice_summary' => 'array', 'file_check' => 'array', 'tags' => 'array', 'source_files' => 'array',
        'remix_confirmed_at' => 'datetime', 'author_confirmed_at' => 'datetime', 'view_count' => 'int', 'order_count' => 'int',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(DesignerProfile::class, 'designer_profile_id');
    }

    public function modelFile(): BelongsTo
    {
        return $this->belongsTo(ModelFile::class);
    }

    public function catalogModel(): BelongsTo
    {
        return $this->belongsTo(CatalogModel::class);
    }

    public function categoryRow(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class, 'catalog_category_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(DesignerModelImage::class)->orderBy('position');
    }

    public static function makeSlug(string $title, ?int $exceptId = null): string
    {
        $base = Str::limit(Str::slug($title) ?: 'model', 200, '');
        $slug = $base;
        $i = 1;
        while (static::where('slug', $slug)->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    /**
     * Cards the farm can print and the catalogue shows: visible, with a file that passed the check, confirmed
     * by its author, in a public profile. Everything else lives in the portfolio only.
     */
    public function scopePrintable(Builder $query): Builder
    {
        return $query->where('designer_models.visible', true)->whereNotNull('model_file_id')->whereNotNull('author_confirmed_at')
            ->where('file_status', self::FILE_READY)
            ->whereHas('profile', fn (Builder $p) => $p->where('visible', true));
    }

    public function isPrintable(): bool
    {
        // getAttribute: inside a model class "->visible" reads Eloquent's own protected list of serialised attributes
        // (on this model and on any other model), not the column
        return $this->getAttribute('visible') && $this->model_file_id !== null && $this->author_confirmed_at !== null
            && $this->file_status === self::FILE_READY && (bool) $this->profile?->getAttribute('visible');
    }

    /** A remix of somebody else's model needs the designer's word that the original allows it before a file is accepted. */
    public function needsRemixConfirmation(): bool
    {
        return $this->is_remix && $this->remix_confirmed_at === null;
    }

    /** Languages the card has a description in (the title is not translated). */
    public function locales(): array
    {
        return array_values(array_filter(Locales::SUPPORTED, fn (string $l) => trim((string) ($this->description[$l] ?? '')) !== ''));
    }

    public function describe(?string $locale = null): string
    {
        $d = (array) $this->description;
        $locale ??= Locales::current();

        return trim((string) ($d[$locale] ?? $d[Locales::DEFAULT] ?? $d['en'] ?? reset($d) ?: ''));
    }

    public function cover(): ?DesignerModelImage
    {
        $images = $this->relationLoaded('images') ? $this->images : $this->images()->get();

        return $images->firstWhere('is_cover', true) ?? $images->first();
    }

    /** Picture for a card in a grid: the cover, else the stored picture of the uploaded model. */
    public function coverUrl(bool $small = true): ?string
    {
        return $this->cover()?->url($small) ?? $this->modelFile?->previewUrl();
    }

    /** Where a click on the card leads: our page when the farm can print it, else the page at the source. */
    public function publicUrl(?string $locale = null, bool $withRef = false): string
    {
        return localized_route('models.show', ['designerModel' => $this->slug] + ($withRef ? ['ref' => $this->profile->slug] : []), $locale);
    }
}
