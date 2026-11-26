<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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

    protected $fillable = ['to', 'subject', 'body', 'attachments', 'locale', 'status', 'generated_by_ai', 'instruction', 'inbox_message_id', 'inbox_thread_id', 'in_reply_to', 'approved_by', 'sent_at', 'error'];

    /** Where the files of a draft live (disk `local`), docs/AU.md §8. */
    public const FILES_DISK = 'local';

    public const MAX_FILES = 6;

    public const MAX_FILE_KB = 8192;

    public function filesDir(): string
    {
        return 'mail-attachments/'.$this->id;
    }

    /**
     * The files that leave with this e-mail, each with its absolute path; missing files are left out.
     *
     * @return list<array{name: string, mime: string, path: string}>
     */
    public function files(): array
    {
        $disk = Storage::disk(self::FILES_DISK);
        $out = [];
        foreach ((array) $this->attachments as $a) {
            $rel = $this->filesDir().'/'.basename((string) ($a['name'] ?? ''));
            if (! empty($a['name']) && $disk->exists($rel)) {
                $out[] = ['name' => (string) $a['name'], 'mime' => (string) ($a['mime'] ?? 'application/octet-stream'), 'path' => $disk->path($rel)];
            }
        }

        return $out;
    }

    /** A reply to a message of the shared mailbox: it leaves in that thread, from the mailbox's address. */
    public function isReply(): bool
    {
        return $this->inbox_thread_id !== null;
    }

    protected $casts = ['generated_by_ai' => 'bool', 'sent_at' => 'datetime', 'attachments' => 'array'];

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
