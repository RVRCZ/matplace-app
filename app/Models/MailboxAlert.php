<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A person's mail in the shared box the watch told the owner about (docs/AU.md). One row per Gmail message. */
class MailboxAlert extends Model
{
    protected $fillable = ['message_id', 'thread_id', 'from', 'from_name', 'subject', 'received_at', 'alerted_at', 'reminded_at'];

    protected $casts = ['received_at' => 'datetime', 'alerted_at' => 'datetime', 'reminded_at' => 'datetime'];
}
