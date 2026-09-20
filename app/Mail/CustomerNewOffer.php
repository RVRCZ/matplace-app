<?php

namespace App\Mail;

class CustomerNewOffer extends InquiryMail
{
    protected function key(): string
    {
        return 'new_offer';
    }

    protected function url(): string
    {
        return route('inquiry.show', $this->inquiry);
    }
}
