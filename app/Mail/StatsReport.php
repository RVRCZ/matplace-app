<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The week in numbers for the owner (App\Domain\Stats\WeeklyReport, matplace:stats-report). */
class StatsReport extends Mailable
{
    use Queueable;

    /** @param  array{subject: string, intro: string, sections: list<array{title: string, lines: list<string>}>}  $report */
    public function __construct(public array $report, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->report['subject']);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.stats_report', with: ['report' => $this->report, 'url' => $this->url]);
    }
}
