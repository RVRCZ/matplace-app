<?php

namespace App\Mail;

class PrinterNewInquiry extends InquiryMail
{
    protected function key(): string
    {
        return 'new_inquiry';
    }

    protected function url(): string
    {
        return route('printer.inquiries.show', $this->inquiry);
    }
}
