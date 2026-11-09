<?php

namespace App\Models;

use App\Domain\Farm\FarmSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class FarmColor extends Model
{
    protected $attributes = ['in_stock' => true, 'sort' => 100];

    /** Override keys that go into the finished G-code; every other key changes the slice itself. */
    public const TEMP_KEYS = ['nozzle_temp', 'nozzle_temp_first', 'bed_temp'];

    /**
     * Where the photo came from (TODO #71, docs/AL.md §21): the Drive export, an upload by the admin, the e-shop page the
     * colour was read from, or a photo of our own print. An e-shop photo is for the admin alone (Roman, 7 Nov 2026).
     */
    public const PHOTO_DRIVE = 'drive';

    public const PHOTO_UPLOAD = 'upload';

    public const PHOTO_ESHOP = 'eshop';

    public const PHOTO_PRINT = 'print';

    protected $fillable = ['farm_material_id', 'code', 'name', 'name_en', 'manufacturer', 'hex', 'photo_path', 'photo_source', 'drive_folder', 'source_url', 'source_meta', 'print_overrides', 'test_notes', 'enabled', 'in_stock', 'sort'];

    protected $casts = ['enabled' => 'bool', 'in_stock' => 'bool', 'sort' => 'int', 'print_overrides' => 'array', 'source_meta' => 'array'];

    /** "PLA+, modrá/blue, Matplace": kind, both names and the maker – how the admin tells spools apart in every list. */
    public function fullLabel(): string
    {
        $names = trim((string) $this->name_en) !== '' && mb_strtolower((string) $this->name_en) !== mb_strtolower((string) $this->name) ? $this->name.'/'.$this->name_en : $this->name;

        return ($this->material?->label() ?? '?').', '.$names.', '.$this->maker();
    }

    /** Who made this spool: its own maker, else its kind's. */
    public function maker(): string
    {
        return trim((string) $this->manufacturer) ?: ($this->material?->maker() ?? FarmMaterial::DEFAULT_MAKER);
    }

    /**
     * Where this colour is in use, by count: slots it is loaded in, orders that were printed from it, tuning rows of
     * this very spool. A colour in use is switched off instead of removed (an order keeps pointing at what it was printed with).
     */
    public function usage(): array
    {
        return array_filter([
            'slots' => $this->slots()->count(),
            'orders' => FarmOrder::where('farm_color_id', $this->id)->orWhere('second_color_id', $this->id)->count(),
            'tuning' => FarmPrinterMaterial::where('farm_color_id', $this->id)->count(),
        ]);
    }

    /** Temperatures this spool prints with: its own, else its kind's. Empty when neither says anything. */
    public function temps(): array
    {
        $o = (array) $this->print_overrides;
        $m = $this->material;
        $nozzle = (int) ($o['nozzle_temp'] ?? $m?->nozzle_temp ?? 0);
        if (! $nozzle) {
            return [];
        }

        return ['nozzle' => $nozzle, 'nozzle_first' => (int) ($o['nozzle_temp_first'] ?? $m?->nozzle_temp_first ?? 0), 'bed' => (int) ($o['bed_temp'] ?? $m?->bed_temp ?? 0)];
    }

    /** Slicer settings (not temperatures) this spool wants: speeds, cooling, retraction… A print for it is re-sliced. */
    public function sliceOverrides(): array
    {
        return array_diff_key((array) $this->print_overrides, array_flip(self::TEMP_KEYS));
    }

    /** Name in the visitor's language; the catalogue is Czech first, English second. */
    public function displayName(): string
    {
        return app()->getLocale() !== 'cs' && $this->name_en ? $this->name_en : $this->name;
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(FarmMaterial::class, 'farm_material_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(FarmPrinterSlot::class);
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    /**
     * The photo a customer may see: ours (the Drive export, an upload, a photo of our print), never the e-shop's
     * (Roman, 7 Nov 2026; docs/AL.md §21). A row without a source (the catalogue before TODO #71) counts as ours.
     * Every place that shows a colour's photo to a customer goes through here; the admin's pages use photoUrl().
     */
    public function customerPhoto(): ?string
    {
        // an e-shop's photo goes to customers only while the farm setting allows it (Roman 9 Nov 2026: yes by default)
        if ($this->photo_source === self::PHOTO_ESHOP && ! app(FarmSettings::class)->get('eshop_photos_public')) {
            return null;
        }

        return $this->photoUrl();
    }
}
