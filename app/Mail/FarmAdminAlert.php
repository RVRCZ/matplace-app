<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The farm talking to its operator: an order paid, a print started or finished (with the camera's last picture), and
 * whatever needs a person – an order to approve or start by hand, a failed print, a printer gone offline. `kind` is
 * the switch in the farm's settings the mail went out under (paid | printing | done | problem).
 */
class FarmAdminAlert extends Mailable implements ShouldQueue
{
    use Queueable;

    /** @param string[] $lines */
    public function __construct(public string $subjectLine, public array $lines, public string $url, public string $kind = 'problem', public ?string $attachment = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[farm] '.$this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.farm_admin_alert', with: ['subjectLine' => $this->subjectLine, 'lines' => $this->lines, 'url' => $this->url]);
    }

    /** @return list<Attachment> the picture of the finished print, when the mail carries one */
    public function attachments(): array
    {
        return $this->attachment && is_file($this->attachment) ? [Attachment::fromPath($this->attachment)->as('snimek.jpg')->withMime('image/jpeg')] : [];
    }
}
