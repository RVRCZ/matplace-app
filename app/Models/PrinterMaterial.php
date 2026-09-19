<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrinterMaterial extends Model
{
    protected $fillable = ['printer_profile_id', 'material_code', 'price_per_gram', 'colors', 'in_stock'];

    protected $casts = ['colors' => 'array', 'in_stock' => 'bool'];
}
