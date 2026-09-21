<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Something on the farm needs a person: an order to approve or start by hand, a failed print, a printer gone offline. */
class FarmAdminAlert extends Mailable implements ShouldQueue
{
    use Queueable;

    /** @param string[] $lines */
    public function __construct(public string $subjectLine, public array $lines, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[farm] '.$this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.farm_admin_alert', with: ['subjectLine' => $this->subjectLine, 'lines' => $this->lines, 'url' => $this->url]);
    }
}
