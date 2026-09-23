<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class FarmColor extends Model
{
    protected $attributes = ['in_stock' => true, 'sort' => 100];

    /** Override keys that go into the finished G-code; every other key changes the slice itself. */
    public const TEMP_KEYS = ['nozzle_temp', 'nozzle_temp_first', 'bed_temp'];

    protected $fillable = ['farm_material_id', 'code', 'name', 'name_en', 'hex', 'photo_path', 'drive_folder', 'print_overrides', 'test_notes', 'enabled', 'in_stock', 'sort'];

    protected $casts = ['enabled' => 'bool', 'in_stock' => 'bool', 'sort' => 'int', 'print_overrides' => 'array'];

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
}
