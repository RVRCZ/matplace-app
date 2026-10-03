<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An e-mail on its way out. One the AI wrote starts as a draft and leaves only after an admin approved it
 * (App\Domain\Mail\Outbox); system notifications are listed here as sent, for the overview.
 */
class OutgoingEmail extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';   // approved, being sent (a failed send stays here with its error)

    public const STATUS_SENT = 'sent';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = ['to', 'subject', 'body', 'locale', 'status', 'generated_by_ai', 'instruction', 'inbox_message_id', 'inbox_thread_id', 'in_reply_to', 'approved_by', 'sent_at', 'error'];

    /** A reply to a message of the shared mailbox: it leaves in that thread, from the mailbox's address. */
    public function isReply(): bool
    {
        return $this->inbox_thread_id !== null;
    }

    protected $casts = ['generated_by_ai' => 'bool', 'sent_at' => 'datetime'];

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
