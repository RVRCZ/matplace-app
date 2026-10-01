<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * An e-mail an admin approved in /admin/emails: a subject and a plain text, in the site's mail layout.
 * It carries the id of its row in outgoing_emails so the "sent" overview does not list it a second time.
 */
class PlainMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $title, public string $text, public ?int $outgoingId = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title);
    }

    public function headers(): Headers
    {
        return new Headers(text: $this->outgoingId ? ['X-Matplace-Outgoing' => (string) $this->outgoingId] : []);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.plain', with: ['paragraphs' => preg_split('/\n{2,}/', trim($this->text)) ?: []]);
    }
}
