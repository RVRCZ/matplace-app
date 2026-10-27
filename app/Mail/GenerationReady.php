<?php

namespace App\Mail;

use App\Models\GenerationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * "Your model is ready": the one e-mail a visitor asked for while a model from a photo was being made (docs/AN.md
 * §12.5). The link opens the tool's page with the finished model; the address is wiped once the mail went out, so
 * nothing else can ever be sent to it from here.
 */
class GenerationReady extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public GenerationRequest $generation) {}

    /** Sends the mail once the model is done, if the visitor asked for it and it did not go out yet; then forgets the address. */
    public static function sendFor(GenerationRequest $generation): void
    {
        if (! $generation->notify_email || $generation->notified_at || $generation->status !== 'done') {
            return;
        }
        $email = (string) $generation->notify_email;
        $locale = (string) ($generation->notify_locale ?: 'cs');
        $generation->forceFill(['notified_at' => now(), 'notify_email' => null])->save();
        Mail::to($email)->locale($locale)->queue((new self($generation))->locale($locale));
    }

    /** The tool's page with this model on it: the pet's page for a pet, the figure's page for the rest. */
    public function url(): string
    {
        $kind = $this->generation->description['kind'] ?? null;
        $route = (string) config('tools.'.($kind === 'pet' ? 'pet' : 'figure').'.route');

        return route($route, ['generation' => $this->generation->token]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('figure.mail.subject'));
    }

    public function content(): Content
    {
        $text = __('figure.mail.body', ['url' => $this->url()]);

        return new Content(markdown: 'mail.plain', with: ['paragraphs' => preg_split('/\n{2,}/', trim($text)) ?: []]);
    }
}
