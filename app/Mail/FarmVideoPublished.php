<?php

namespace App\Mail;

use App\Models\FarmVideo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Your video is on YouTube": the customer who agreed to share the time-lapse gets its link once it is public, with a
 * word of thanks for passing it on and the way to the tool that made the model (marked utm_source=email and the
 * video's id, so the statistics see what the mail brings).
 */
class FarmVideoPublished extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public FarmVideo $video, public string $toolLink) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('youtube.mail.subject', ['number' => (string) $this->video->order?->number]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.farm_video_published', with: [
            'order' => $this->video->order,
            'watchUrl' => (string) $this->video->watchUrl(),
            'orderUrl' => $this->video->order ? route('farm.orders.show', $this->video->order) : url('/'),
            'link' => $this->toolLink,
        ]);
    }
}
