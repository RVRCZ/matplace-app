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

    /** A print that left as a parcel has its own text: "on its way", with the link to follow it. */
    private function key(): string
    {
        return $this->status === FarmOrder::STATUS_HANDED_OVER && $this->order->tracking_url ? 'shipped' : $this->status;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('farm.mail.'.$this->key().'.subject', ['number' => $this->order->number]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.farm_order_status', with: [
            'order' => $this->order,
            'status' => $this->key(),
            'url' => route('farm.orders.show', $this->order),
            'trackingUrl' => $this->key() === 'shipped' ? $this->order->tracking_url : null,
            'reason' => $this->order->error ? __('farm.error.'.$this->order->error) : null,
        ]);
    }
}
