<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One call of an AI service and what it cost (App\Support\AiUsage::record). */
class AiCall extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['kind', 'engine', 'tokens_in', 'tokens_out', 'cost_czk', 'user_id', 'session_id', 'subject_type', 'subject_id', 'duration_ms'];

    protected $casts = ['tokens_in' => 'int', 'tokens_out' => 'int', 'cost_czk' => 'float', 'duration_ms' => 'int', 'created_at' => 'datetime'];
}
