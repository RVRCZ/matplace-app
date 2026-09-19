<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrinterPortfolioItem extends Model
{
    protected $fillable = ['printer_profile_id', 'photo_path', 'title', 'material_code', 'note', 'legacy_id'];
}
