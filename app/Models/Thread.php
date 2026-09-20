<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One conversation = one inquiry × one printer. Header carries the model and parameters so both sides see the same thing. */
class Thread extends Model
{
    protected $fillable = ['inquiry_id', 'printer_profile_id', 'quote_id', 'header', 'last_message_at', 'customer_read_at', 'printer_read_at'];

    protected $casts = ['header' => 'array', 'last_message_at' => 'datetime', 'customer_read_at' => 'datetime', 'printer_read_at' => 'datetime'];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function printerProfile(): BelongsTo
    {
        return $this->belongsTo(PrinterProfile::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public static function open(Inquiry $inquiry, PrinterProfile $printer, ?Quote $quote = null): self
    {
        $thread = static::firstOrCreate(['inquiry_id' => $inquiry->id, 'printer_profile_id' => $printer->id], [
            'quote_id' => $quote?->id,
            'header' => [
                'model' => $inquiry->modelFile?->original_name,
                'material' => $inquiry->material_code,
                'quantity' => $inquiry->quantity,
                'params' => $inquiry->params,
                'summary' => $inquiry->summary,
            ],
        ]);
        if ($quote && ! $thread->quote_id) {
            $thread->update(['quote_id' => $quote->id]);
        }

        return $thread;
    }

    public function post(string $sender, ?string $body, ?User $user = null, ?string $attachmentPath = null, ?string $attachmentName = null): Message
    {
        $m = $this->messages()->create([
            'sender' => $sender, 'user_id' => $user?->id, 'body' => $body, 'attachment_path' => $attachmentPath, 'attachment_name' => $attachmentName,
        ]);
        $this->forceFill(['last_message_at' => now()] + ($sender === 'customer' ? ['customer_read_at' => now()] : ($sender === 'printer' ? ['printer_read_at' => now()] : [])))->save();

        return $m;
    }

    public function unreadFor(string $side): int
    {
        $since = $side === 'customer' ? $this->customer_read_at : $this->printer_read_at;
        $other = $side === 'customer' ? 'printer' : 'customer';
        $q = $this->messages()->whereIn('sender', [$other, 'system']);
        if ($since) {
            $q->where('created_at', '>', $since);
        }

        return $q->count();
    }
}
