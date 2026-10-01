<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One short e-mail about the account: a subject, a paragraph or two, at most one button.
 * The texts live in lang/<locale>/user.php under mail.<key>; the mail is written in the receiver's language
 * (User::preferredLocale(), or ->locale() for an address that is not an account).
 */
class AccountMail extends Notification
{
    /** @param  array<string, string|int>  $vars */
    public function __construct(protected string $key, protected array $vars = [], protected ?string $actionUrl = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__("user.mail.{$this->key}.subject", $this->vars).' · matplace')
            ->greeting(__('user.mail.greeting'))
            ->salutation(__('user.mail.salutation'));
        foreach ((array) __("user.mail.{$this->key}.lines", $this->vars) as $line) {
            $mail->line($line);
        }
        if ($url = $this->url($notifiable)) {
            $mail->action(__("user.mail.{$this->key}.action"), $url);
        }
        foreach ((array) __("user.mail.{$this->key}.after", $this->vars) as $line) {
            $mail->line($line);
        }

        return $mail;
    }

    /** Built while the mail is rendered, so the link carries the language of the mail. */
    protected function url(object $notifiable): ?string
    {
        return $this->actionUrl;
    }
}
