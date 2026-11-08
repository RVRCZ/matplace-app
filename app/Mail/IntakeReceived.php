<?php

namespace App\Mail;

use App\Models\Intake;
use App\Models\IntakeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "We have your request" (TODO #78, docs/AN.md §22 E): the customer left an e-mail with what the home page's router
 * could not do by itself; a person answers within the farm's reply time. In the customer's language.
 */
class IntakeReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Intake $intake, public IntakeRequest $request, public int $hours) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('router.mail.received.subject', ['hours' => $this->hours]));
    }

    public function content(): Content
    {
        $text = __('router.mail.received.body', ['hours' => $this->hours, 'text' => $this->intake->text ? mb_substr($this->intake->text, 0, 500) : __('router.mail.received.no_text')]);

        return new Content(markdown: 'mail.plain', with: ['paragraphs' => preg_split('/\n{2,}/', trim($text)) ?: []]);
    }
}
