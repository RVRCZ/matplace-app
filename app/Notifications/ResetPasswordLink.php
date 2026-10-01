<?php

namespace App\Notifications;

/** The "forgot password" e-mail in the account's language. */
class ResetPasswordLink extends AccountMail
{
    public function __construct(private readonly string $token)
    {
        parent::__construct('reset', ['minutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60)]);
    }

    protected function url(object $notifiable): ?string
    {
        return route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
    }
}
