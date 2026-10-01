<?php

namespace App\Notifications;

use Illuminate\Support\Facades\URL;

/** "Is this address yours?" after registering with e-mail and password. The signed link is good for 24 hours. */
class VerifyEmailAddress extends AccountMail
{
    public const HOURS = 24;

    public function __construct()
    {
        parent::__construct('verify');
    }

    protected function url(object $notifiable): ?string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHours(self::HOURS), [
            'id' => $notifiable->getKey(),
            'hash' => sha1((string) $notifiable->getEmailForVerification()),
        ]);
    }
}
