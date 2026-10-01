<?php

namespace App\Notifications;

/** Sent to the NEW address of an account: the e-mail changes only after this link is opened. */
class ConfirmNewEmail extends AccountMail
{
    public function __construct(private readonly string $token)
    {
        parent::__construct('email_change');
    }

    protected function url(object $notifiable): ?string
    {
        return route('account.email.confirm', ['token' => $this->token]);
    }
}
