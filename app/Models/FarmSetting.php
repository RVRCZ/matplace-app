<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One overridden farm setting; defaults live in config/farm.php. Read through App\Domain\Farm\FarmSettings. */
class FarmSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected $casts = ['value' => 'json'];
}
