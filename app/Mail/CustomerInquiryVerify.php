<?php

namespace App\Mail;

class CustomerInquiryVerify extends InquiryMail
{
    protected function key(): string
    {
        return 'verify';
    }

    protected function url(): string
    {
        return route('inquiry.verify', [$this->inquiry, $this->inquiry->verification_code]);
    }
}
