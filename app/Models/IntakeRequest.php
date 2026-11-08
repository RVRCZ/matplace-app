<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "A person takes it over" (TODO #78, docs/AN.md §22): the e-mail a customer left with an intake, their consent to the
 * photo being worked with, and the operator's reply from /admin/intake.
 */
class IntakeRequest extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_ANSWERED = 'answered';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = ['intake_id', 'email', 'name', 'phone', 'consent_at', 'wants_shape', 'status', 'reply', 'replied_at', 'replied_by', 'locale'];

    protected $casts = ['consent_at' => 'datetime', 'replied_at' => 'datetime', 'wants_shape' => 'bool'];

    public function intake(): BelongsTo
    {
        return $this->belongsTo(Intake::class);
    }

    public function repliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }
}
