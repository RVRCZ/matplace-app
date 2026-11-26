<?php

namespace App\Mail;

use App\Domain\Ops\HealthChecks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The owner hears that a server check flipped (docs/AU.md): what became a problem, what got solved, what still lasts;
 * then the state of every check, so one mail says it all.
 */
class HealthAlert extends Mailable implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array{key: string, ok: bool, message: string, what: string}>  $changes  what = problem | solved | still
     * @param  list<array{key: string, ok: bool, message: string}>  $checks
     */
    public function __construct(public array $changes, public array $checks) {}

    public function envelope(): Envelope
    {
        $bad = array_values(array_filter($this->changes, fn (array $c) => $c['what'] !== 'solved'));
        $names = implode(', ', array_map(fn (array $c) => HealthChecks::label($c['key']), $bad ?: $this->changes));

        return new Envelope(subject: mb_substr(($bad ? '[hlídač] Problém: ' : '[hlídač] Vyřešeno: ').$names, 0, 180));
    }

    public function content(): Content
    {
        // under other names than the public properties: those reach the view too and would win
        $word = ['problem' => 'Problém', 'solved' => 'Vyřešeno', 'still' => 'Trvá'];
        $rows = array_map(fn (array $c) => ['what' => $word[$c['what']] ?? $c['what'], 'label' => HealthChecks::label($c['key']), 'message' => $c['message']], $this->changes);
        $all = array_map(fn (array $c) => ['ok' => $c['ok'], 'label' => HealthChecks::label($c['key']), 'message' => $c['message']], $this->checks);

        return new Content(markdown: 'mail.health_alert', with: ['rows' => $rows, 'all' => $all, 'url' => route('admin.health')]);
    }
}
