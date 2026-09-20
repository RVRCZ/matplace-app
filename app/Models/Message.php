<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = ['thread_id', 'sender', 'user_id', 'body', 'attachment_path', 'attachment_name'];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sender' => $this->sender,
            'body' => $this->body,
            'attachment' => $this->attachment_path ? ['name' => $this->attachment_name, 'url' => route('api.threads.attachment', [$this->thread_id, $this->id])] : null,
            'at' => $this->created_at?->toIso8601String(),
        ];
    }
}
