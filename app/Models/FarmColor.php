<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class FarmColor extends Model
{
    protected $fillable = ['farm_material_id', 'name', 'hex', 'photo_path', 'enabled'];

    protected $casts = ['enabled' => 'bool'];

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
