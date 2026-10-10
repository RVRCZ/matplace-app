<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FarmMaterial extends Model
{
    public const FINISHES = ['solid', 'matte', 'silk', 'luminous', 'glitter', 'special', 'flex', 'cf'];

    /** The farm's own brand: every kind and colour of the first catalogue; a label names any other maker. */
    public const DEFAULT_MAKER = 'Matplace';

    protected $attributes = ['finish' => 'solid', 'manufacturer' => self::DEFAULT_MAKER, 'sort' => 100];

    protected $fillable = ['code', 'name', 'manufacturer', 'finish', 'filament_profile', 'filament_overrides', 'density', 'nozzle_temp', 'nozzle_temp_first', 'bed_temp', 'price_per_gram', 'notes', 'enabled', 'sort'];

    protected $casts = ['filament_overrides' => 'array', 'density' => 'float', 'price_per_gram' => 'float', 'enabled' => 'bool', 'nozzle_temp' => 'int', 'nozzle_temp_first' => 'int', 'bed_temp' => 'int', 'sort' => 'int'];

    /** "PLA+ matt", "PETG Prusament" — what a person calls this kind; the maker is said only when it is not ours. */
    public function label(): string
    {
        $l = $this->finish === 'solid' ? $this->name : $this->name.' '.__('farm.finish.'.$this->finish);

        return $this->maker() === self::DEFAULT_MAKER ? $l : $l.' '.$this->maker();
    }

    public function maker(): string
    {
        return trim((string) $this->manufacturer) ?: self::DEFAULT_MAKER;
    }

    /** A kind can be removed only while nothing points at it: no colour, no order. Otherwise it is switched off. */
    public function usage(): array
    {
        return array_filter([
            'colors' => $this->colors()->count(),
            'orders' => FarmOrder::where('farm_material_id', $this->id)->count(),
        ]);
    }

    /** A new kind with this one's profile, temperatures and price, to be renamed by the admin. */
    public function copy(): self
    {
        return new self($this->only(['name', 'manufacturer', 'finish', 'filament_profile', 'filament_overrides', 'density', 'nozzle_temp', 'nozzle_temp_first', 'bed_temp', 'price_per_gram', 'notes', 'sort']) + ['code' => $this->code, 'enabled' => false]);
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
