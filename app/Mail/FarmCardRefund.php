<?php

namespace App\Mail;

use App\Models\PaymentRefund;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Money of an order went back to the card (App\Domain\Farm\CardRefunds): how much, and that the bank takes a few days. */
class FarmCardRefund extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PaymentRefund $refund) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('farm.refund.mail.subject', ['number' => $this->refund->order?->number]));
    }

    public function content(): Content
    {
        $order = $this->refund->order;

        return new Content(markdown: 'mail.farm_card_refund', with: [
            'order' => $order,
            'amount' => Money::of((float) $this->refund->amount, $this->refund->currency)->format(),
            // why, in the customer's words: printed twice without luck, cancelled, cancelled while printing, not paid, by us
            'why' => __('farm.refund.mail.why.'.$this->refund->reason),
            'url' => route('farm.orders.show', $order),
        ]);
    }
}
