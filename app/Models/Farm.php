<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place where printers stand: farm U in Útušice, B at Bory in Plzeň, S in Stod (docs/AQ.md). It has an address of its
 * own; the agent that runs the printers is a program on a PC there and does not define the farm. A pickup point would
 * hang here later (the farm's own address, or a partner shop at the place) – not built yet.
 */
class Farm extends Model
{
    protected $attributes = ['country' => 'CZ', 'sort' => 100, 'enabled' => true];

    protected $fillable = ['code', 'name', 'street', 'city', 'zip', 'country', 'note', 'sort', 'enabled'];

    protected $casts = ['sort' => 'int', 'enabled' => 'bool'];

    public function printers(): HasMany
    {
        return $this->hasMany(FarmPrinter::class);
    }

    /** "Farma U · Útušice": the heading of the farm's group in the overview. */
    public function label(): string
    {
        return trim((string) $this->city) !== '' ? $this->name.' · '.$this->city : $this->name;
    }

    /** The address on one line, only the parts that are filled in. */
    public function addressLine(): string
    {
        return implode(', ', array_filter([trim((string) $this->street), trim(trim((string) $this->zip).' '.trim((string) $this->city))]));
    }
}
