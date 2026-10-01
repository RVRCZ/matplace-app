<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line of our own statistics (App\Support\Track). Written once, never changed. */
class Event extends Model
{
    public const UPDATED_AT = null;

    public const VIEW = 'view';

    public const REF_VISIT = 'ref_visit';

    public const VISIT = 'visit';   // the first page of a browser session (App\Support\Track::visit)

    protected $fillable = ['session_id', 'user_id', 'locale', 'type', 'subject_type', 'subject_id', 'source', 'ref_slug', 'utm', 'meta'];

    protected $casts = ['utm' => 'array', 'meta' => 'array', 'created_at' => 'datetime'];
}
