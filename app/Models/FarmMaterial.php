<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FarmMaterial extends Model
{
    public const FINISHES = ['solid', 'matte', 'silk', 'luminous', 'glitter', 'special', 'flex', 'cf'];

    protected $attributes = ['finish' => 'solid', 'sort' => 100];

    protected $fillable = ['code', 'name', 'finish', 'filament_profile', 'filament_overrides', 'density', 'nozzle_temp', 'nozzle_temp_first', 'bed_temp', 'price_per_gram', 'notes', 'enabled', 'sort'];

    protected $casts = ['filament_overrides' => 'array', 'density' => 'float', 'price_per_gram' => 'float', 'enabled' => 'bool', 'nozzle_temp' => 'int', 'nozzle_temp_first' => 'int', 'bed_temp' => 'int', 'sort' => 'int'];

    /** "PLA+ matt" — what a person calls this kind. */
    public function label(): string
    {
        return $this->finish === 'solid' ? $this->name : $this->name.' '.__('farm.finish.'.$this->finish);
    }

    /**
     * Slicer settings that come from this material row: the profile file plus the temperatures. The temperatures
     * override whatever the profile says, so PLA+ and PETG print right with the one shipped PLA profile.
     */
    public function sliceOverrides(): array
    {
        $o = (array) $this->filament_overrides;
        if ($this->nozzle_temp) {
            $o['nozzle_temperature'] = [(string) $this->nozzle_temp];
            $o['nozzle_temperature_initial_layer'] = [(string) ($this->nozzle_temp_first ?: $this->nozzle_temp + 5)];
        }
        if ($this->bed_temp) {
            foreach (['hot_plate_temp', 'hot_plate_temp_initial_layer', 'textured_plate_temp', 'textured_plate_temp_initial_layer'] as $k) {
                $o[$k] = [(string) $this->bed_temp];
            }
        }

        return $o;
    }

    public function colors(): HasMany
    {
        return $this->hasMany(FarmColor::class);
    }
}
