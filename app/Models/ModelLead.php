<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An e-mail a visitor left at a finished model from a photo ("send me my bust", App\Mail\ModelLink): the link went out
 * at once; one reminder later only with `remind` (consent_at says when it was ticked). See the migration.
 */
class ModelLead extends Model
{
    /** Days the reminder waits after the link went out. */
    public const REMIND_AFTER_DAYS = 2;

    /** Days a lead keeps its model from being pruned. */
    public const HOLD_DAYS = 180;

    protected $fillable = ['email', 'locale', 'model_file_id', 'anonymous_session_id', 'remind', 'consent_at', 'token', 'ip', 'sent_at', 'reminded_at', 'unsubscribed_at'];

    protected function casts(): array
    {
        return ['remind' => 'boolean', 'consent_at' => 'datetime', 'sent_at' => 'datetime', 'reminded_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (ModelLead $lead) {
            $lead->token = $lead->token ?: Str::random(40);
        });
    }

    public function modelFile(): BelongsTo
    {
        return $this->belongsTo(ModelFile::class);
    }
}
