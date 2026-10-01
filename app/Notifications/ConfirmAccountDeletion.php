<?php

namespace App\Notifications;

use Illuminate\Support\Facades\URL;

/** For accounts without a password: the deletion is confirmed from the mailbox instead. The link is good for 24 hours. */
class ConfirmAccountDeletion extends AccountMail
{
    public const HOURS = 24;

    public function __construct()
    {
        parent::__construct('delete');
    }

    protected function url(object $notifiable): ?string
    {
        return URL::temporarySignedRoute('account.delete.confirm', now()->addHours(self::HOURS), ['user' => $notifiable->getKey()]);
    }
}
