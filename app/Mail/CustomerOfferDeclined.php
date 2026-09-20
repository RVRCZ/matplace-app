<?php

namespace App\Mail;

class CustomerOfferDeclined extends InquiryMail
{
    protected function key(): string
    {
        return 'cancelled';
    }

    protected function url(): string
    {
        return route('printer.inquiries.show', $this->inquiry);
    }
}
