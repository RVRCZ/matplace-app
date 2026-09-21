<?php

namespace App\Mail;

use App\Models\FarmOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The customer's farm order changed its status. The status is fixed at send time: a later change gets its own mail. */
class FarmOrderStatus extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public FarmOrder $order, public string $status) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('farm.mail.'.$this->status.'.subject', ['number' => $this->order->number]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.farm_order_status', with: [
            'order' => $this->order,
            'status' => $this->status,
            'url' => route('farm.orders.show', $this->order),
            'reason' => $this->order->error ? __('farm.error.'.$this->order->error) : null,
        ]);
    }
}
