<?php

namespace App\Mail;

use App\Domain\Farm\GuestCheckout;
use App\Models\FarmOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A guest paid an order by card (App\Domain\Farm\GuestCheckout): it is paid, here is the order (a signed link that
 * works without signing in) and, when the payment made the account, how to set its password. When the e-mail already
 * had an account, the mail says the order was attached to it.
 */
class FarmGuestOrder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public FarmOrder $order, public bool $created) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('farm.guest.mail.subject', ['number' => $this->order->number]));
    }

    public function content(): Content
    {
        // the links are made when the mail is written, in its language
        return new Content(markdown: 'mail.farm_guest_order', with: [
            'order' => $this->order,
            'url' => GuestCheckout::orderLink($this->order),
            'passwordUrl' => $this->created && $this->order->user ? GuestCheckout::passwordLink($this->order->user) : null,
            'total' => $this->order->total()?->format(),
        ]);
    }
}
