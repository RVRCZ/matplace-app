<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrinterMachine extends Model
{
    protected $fillable = ['printer_profile_id', 'name', 'technology', 'bed_x', 'bed_y', 'bed_z', 'nozzle_mm', 'count'];
}
