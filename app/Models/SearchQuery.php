<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What somebody searched for and how much was found (App\Domain\Stats\SearchLog). No person is attached to it. */
class SearchQuery extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['locale', 'query', 'results_local', 'results_external', 'visitor'];

    protected $casts = ['results_local' => 'int', 'results_external' => 'int', 'created_at' => 'datetime'];
}
