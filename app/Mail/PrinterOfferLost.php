<?php

namespace App\Mail;

class PrinterOfferLost extends InquiryMail
{
    protected function key(): string
    {
        return 'lost';
    }

    protected function url(): string
    {
        return route('printer.inquiries.show', $this->inquiry);
    }
}
