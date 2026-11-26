<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The last known state of one server check (docs/AU.md): the owner hears when `ok` flips, and once a day while it stays bad. */
class HealthCheck extends Model
{
    protected $fillable = ['key', 'ok', 'message', 'checked_at', 'changed_at', 'alerted_at'];

    protected $casts = ['ok' => 'bool', 'checked_at' => 'datetime', 'changed_at' => 'datetime', 'alerted_at' => 'datetime'];
}
