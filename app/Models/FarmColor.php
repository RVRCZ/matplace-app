<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class FarmColor extends Model
{
    protected $attributes = ['in_stock' => true, 'sort' => 100];

    protected $fillable = ['farm_material_id', 'code', 'name', 'name_en', 'hex', 'photo_path', 'drive_folder', 'enabled', 'in_stock', 'sort'];

    protected $casts = ['enabled' => 'bool', 'in_stock' => 'bool', 'sort' => 'int'];

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
