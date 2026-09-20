<?php

namespace App\Mail;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells the printer their client asked for a change of the quote. */
class QuoteChangeRequested extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Quote $quote) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('quote.mail.change_subject', ['number' => $this->quote->number]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.quote_change', with: ['quote' => $this->quote, 'url' => route('printer.quotes.edit', $this->quote)]);
    }
}
