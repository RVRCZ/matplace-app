<?php

namespace App\Mail;

class CustomerNewMessage extends InquiryMail
{
    protected function key(): string
    {
        return 'msg_customer';
    }

    protected function url(): string
    {
        return route('inquiry.show', $this->inquiry);
    }
}
