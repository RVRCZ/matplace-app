<?php

namespace App\Mail;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/** Quote to the printer's client: link to the online version + PDF attached. */
class QuoteSent extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Quote $quote) {}

    public function envelope(): Envelope
    {
        $p = $this->quote->printerProfile;

        return new Envelope(
            subject: __('quote.mail.subject', ['number' => $this->quote->number, 'printer' => $p->display_name]),
            replyTo: $p->contact_email ? [$p->contact_email] : [],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.quote_sent', with: ['quote' => $this->quote, 'url' => route('quote.public', $this->quote)]);
    }

    public function attachments(): array
    {
        if (! $this->quote->pdf_path || ! Storage::disk('local')->exists($this->quote->pdf_path)) {
            return [];
        }

        return [Attachment::fromStorageDisk('local', $this->quote->pdf_path)->as(($this->quote->number ?: 'nabidka').'.pdf')->withMime('application/pdf')];
    }
}
