<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FarmMaterial extends Model
{
    protected $fillable = ['code', 'name', 'filament_profile', 'filament_overrides', 'density', 'price_per_gram', 'enabled'];

    protected $casts = ['filament_overrides' => 'array', 'density' => 'float', 'price_per_gram' => 'float', 'enabled' => 'bool'];

    public function colors(): HasMany
    {
        return $this->hasMany(FarmColor::class);
    }
}
