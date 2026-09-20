<?php

namespace App\Mail;

use App\Models\Inquiry;
use App\Models\PrinterProfile;
use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Base for the small family of inquiry mails: one markdown view, subject key and URL per subclass. */
abstract class InquiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Inquiry $inquiry, public ?Quote $quote = null, public ?PrinterProfile $printer = null, public array $extra = []) {}

    abstract protected function key(): string;

    abstract protected function url(): string;

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('inquiry.mail.'.$this->key().'.subject', $this->vars()));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.inquiry', with: ['key' => $this->key(), 'vars' => $this->vars(), 'url' => $this->url()]);
    }

    protected function vars(): array
    {
        return [
            'model' => $this->inquiry->modelFile?->original_name ?? '3D model',
            'material' => $this->inquiry->material_code,
            'quantity' => $this->inquiry->quantity,
            'city' => $this->inquiry->city ?: ($this->inquiry->zip ?: '—'),
            'printer' => $this->printer?->display_name ?? $this->quote?->printerProfile?->display_name ?? '',
            'price' => $this->quote ? number_format($this->quote->total, 0, ',', ' ') : ($this->extra['price'] ?? ''),
            'lead' => $this->quote?->lead_time_days ?? '',
            'client' => $this->inquiry->contact_name ?: $this->inquiry->contact_email,
        ] + $this->extra;
    }
}
