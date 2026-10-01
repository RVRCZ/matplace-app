<?php

namespace App\Domain\Account;

use App\Models\User;
use App\Notifications\AccountMail;
use App\Notifications\ConfirmNewEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Changing the login e-mail in two steps: the new address is kept aside (users.pending_email) until its owner
 * clicks the link sent there. The old address hears about the request and about the change, so a stolen session
 * cannot move an account quietly.
 */
final class EmailChange
{
    public const HOURS = 24;

    public function taken(string $email, ?User $except = null): bool
    {
        return User::where('email', $email)->when($except, fn ($q) => $q->whereKeyNot($except->id))->exists();
    }

    public function request(User $user, string $email): void
    {
        $email = strtolower(trim($email));
        $token = Str::random(48);
        $user->forceFill(['pending_email' => $email, 'pending_email_token' => hash('sha256', $token), 'pending_email_at' => now()])->save();

        $locale = $user->preferredLocale();
        Notification::route('mail', $email)->notify((new ConfirmNewEmail($token))->locale($locale));
        $user->notify(new AccountMail('email_changing', ['new' => $email]));
    }

    public function cancel(User $user): void
    {
        $user->forceFill(['pending_email' => null, 'pending_email_token' => null, 'pending_email_at' => null])->save();
    }

    /**
     * The link from the new address. Null when the token is unknown, older than 24 hours, or the address was taken
     * by another account in the meantime (the request is then dropped).
     */
    public function confirm(string $token): ?User
    {
        return DB::transaction(function () use ($token) {
            $user = User::where('pending_email_token', hash('sha256', $token))->lockForUpdate()->first();
            if (! $user || ! $user->pending_email || $user->isAnonymized()) {
                return null;
            }
            $fresh = $user->pending_email_at && $user->pending_email_at->gt(now()->subHours(self::HOURS));
            if (! $fresh || $this->taken($user->pending_email, $user)) {
                $this->cancel($user);

                return null;
            }
            $old = $user->email;
            $new = $user->pending_email;
            $user->forceFill([
                'email' => $new, 'email_verified_at' => now(),
                'pending_email' => null, 'pending_email_token' => null, 'pending_email_at' => null,
            ])->save();
            DB::table('password_reset_tokens')->where('email', $old)->delete();
            Notification::route('mail', $old)->notify((new AccountMail('email_changed', ['new' => $new]))->locale($user->preferredLocale()));

            return $user;
        });
    }
}
