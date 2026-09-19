<?php

namespace App\Mail;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells the printer their client accepted the quote. */
class QuoteAccepted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Quote $quote) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('quote.mail.accepted_subject', ['number' => $this->quote->number]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.quote_accepted', with: ['quote' => $this->quote, 'url' => route('printer.quotes.edit', $this->quote)]);
    }
}
