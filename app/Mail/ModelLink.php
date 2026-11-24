<?php

namespace App\Mail;

use App\Models\GenerationRequest;
use App\Models\ModelFile;
use App\Models\ModelLead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The link to a model from a photo a visitor asked for at the finished model (App\Models\ModelLead), or (`reminder`) the
 * one reminder he ticked. Never the file itself: the link opens the model on our page with the way to the print.
 */
class ModelLink extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ModelLead $lead, public bool $reminder = false) {}

    /** The tool's page with the generation on it when this file is its result; else the calculator with the file open. */
    public static function urlFor(ModelFile $file): string
    {
        $generation = GenerationRequest::where('result_model_file_id', $file->id)->first();
        if ($generation) {
            $kind = $generation->description['kind'] ?? null;
            $route = (string) config('tools.'.($kind === 'pet' ? 'pet' : 'figure').'.route');

            return route($route, ['generation' => $generation->token]);
        }

        return route('home', ['open' => $file->uuid]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __($this->reminder ? 'keep.mail.remind.subject' : 'keep.mail.subject'));
    }

    public function content(): Content
    {
        $file = $this->lead->modelFile;

        return new Content(markdown: 'mail.model_link', with: [
            'intro' => __($this->reminder ? 'keep.mail.remind.intro' : 'keep.mail.intro'),
            'url' => $file ? self::urlFor($file) : route('home'),
            'image' => $file?->preview_path ? route('api.files.preview', $file->uuid) : null,
            'button' => __('keep.mail.button'),
            'after' => __('keep.mail.after'),
            'footer' => __($this->lead->remind ? 'keep.mail.footer.remind' : 'keep.mail.footer'),
            'unsubscribe' => $this->lead->remind ? route('keep.unsubscribe', $this->lead->token) : null,
            'unsubscribeLabel' => __('keep.mail.unsubscribe'),
        ]);
    }
}
