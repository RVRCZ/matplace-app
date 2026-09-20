<?php

namespace App\Mail;

class PrinterNewMessage extends InquiryMail
{
    protected function key(): string
    {
        return 'msg_printer';
    }

    protected function url(): string
    {
        return route('printer.inquiries.show', $this->inquiry);
    }
}
