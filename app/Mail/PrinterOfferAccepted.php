<?php

namespace App\Mail;

class PrinterOfferAccepted extends InquiryMail
{
    protected function key(): string
    {
        return 'accepted';
    }

    protected function url(): string
    {
        return route('printer.inquiries.show', $this->inquiry);
    }
}
