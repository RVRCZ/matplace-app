<?php

namespace App\Mail;

use App\Domain\Mail\MailboxWatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

/**
 * The owner hears that a person wrote to the shared mailbox (docs/AU.md): who, when, the subject, the first lines and a
 * link to the message in the admin. One mail for all the new ones of a pass; `reminder` = the same for mails still
 * unread a day later.
 */
class NewMailAlert extends Mailable implements ShouldQueue
{
    use Queueable;

    /** @param  list<array<string, mixed>>  $messages  rows of Mailbox::recent() */
    public function __construct(public array $messages, public string $address, public bool $reminder = false) {}

    public function envelope(): Envelope
    {
        $n = count($this->messages);
        $first = $this->messages[0] ?? null;
        $who = $first ? ((string) ($first['from_name'] ?? '') ?: (string) $first['from']) : '';
        $subject = match (true) {
            $this->reminder && $n === 1 => 'Stále nepřečtený e-mail v '.$this->address.': '.$who,
            $this->reminder => $n.' stále nepřečtené e-maily v '.$this->address,
            $n === 1 => 'Nový e-mail v '.$this->address.': '.$who.' – '.((string) ($first['subject'] ?? '') ?: '(bez předmětu)'),
            default => $n.' nové e-maily v '.$this->address,
        };

        return new Envelope(subject: mb_substr($subject, 0, 180));
    }

    public function content(): Content
    {
        $rows = array_map(function (array $m): array {
            $when = MailboxWatch::when($m['date'] ?? null);

            return [
                'who' => (string) ($m['from_name'] ?? '') ?: (string) $m['from'], 'from' => (string) $m['from'],
                'when' => $when ? $when->timezone(config('app.timezone'))->format('j. n. Y H:i') : '',
                'subject' => (string) ($m['subject'] ?? '') ?: '(bez předmětu)', 'snippet' => Str::limit((string) ($m['snippet'] ?? ''), 300),
                'url' => route('admin.emails.inbox.show', $m['id']), 'draft_url' => $m['draft_url'] ?? null,
            ];
        }, $this->messages);

        return new Content(markdown: 'mail.new_mail_alert', with: ['rows' => $rows, 'address' => $this->address, 'reminder' => $this->reminder, 'url' => route('admin.emails.inbox')]);
    }
}
