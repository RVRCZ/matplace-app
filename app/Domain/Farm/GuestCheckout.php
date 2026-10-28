<?php

namespace App\Domain\Farm;

use App\Mail\FarmGuestOrder;
use App\Models\FarmOrder;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * An order without an account (docs/AO.md). A guest's order belongs to the anonymous session that made it; the guest
 * pays it by card with an e-mail, and when the bank confirms the payment (the webhook) the order gets an account before
 * the money is credited, so the rest is the same as for anyone paying by card (Wallet::topUp, OrderFlow::payFromCard):
 *
 *  - the e-mail is nobody's: a new account without a password (a random one, the e-mail not confirmed), and it takes
 *    over what the session made (files, calculations, other guest orders), as a registration would;
 *  - the e-mail is an account's: the order is attached to it, nothing else, and nobody is signed in.
 *
 * The guest keeps seeing the order through the session; the confirmation mail carries a signed link to the order and,
 * for a new account, one to set the password.
 */
final class GuestCheckout
{
    /** How long the links in the confirmation mail work. */
    public const ORDER_LINK_DAYS = 90;

    public const PASSWORD_LINK_DAYS = 14;

    /**
     * The guest's payment has arrived: the account it goes to. Null when the payment is not a guest's (it already has
     * an account, or it is not for an order). Safe to call twice: the second call finds the account set.
     *
     * @return array{user: User, created: bool}|null
     */
    public function attach(Payment $payment): ?array
    {
        if ($payment->user_id !== null || $payment->purpose !== Payment::PURPOSE_ORDER) {
            return null;
        }
        $email = strtolower(trim((string) ($payment->context['email'] ?? '')));
        if ($email === '' || ! $payment->farm_order_id) {
            return null;
        }

        return DB::transaction(function () use ($payment, $email) {
            // the gateway may call twice at once: one of them gives the account
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if (! $payment || $payment->user_id !== null) {
                return null;
            }
            $context = (array) $payment->context;
            $address = (array) ($context['address'] ?? []);
            $user = User::where('email', $email)->first();
            $created = false;
            if (! $user) {
                $user = User::create(array_filter([
                    'name' => trim((string) ($address['name'] ?? '')) ?: Str::before($email, '@'),
                    'email' => $email,
                    // nobody knows it: the customer sets one from the mail (set-password link) or "forgot password"
                    'password' => Str::random(48),
                    'locale' => (string) ($context['locale'] ?? config('app.locale')),
                    // the delivery of this order fills the profile for the next one
                    'phone' => $address['phone'] ?? null, 'street' => $address['street'] ?? null, 'city' => $address['city'] ?? null,
                    'zip' => $address['zip'] ?? null, 'country' => isset($address['country']) ? strtoupper((string) $address['country']) : null,
                ], fn ($v) => $v !== null && $v !== ''));
                $user->setRole(User::ROLE_CUSTOMER, true);
                $created = true;
            }
            // remembered for a webhook delivered again: the confirmation says whether the account is new
            $payment->forceFill(['user_id' => $user->id, 'context' => array_merge($context, ['account' => $created ? 'created' : 'attached'])])->save();
            $order = FarmOrder::whereKey($payment->farm_order_id)->lockForUpdate()->first();
            if ($order && $order->user_id === null) {
                $order->forceFill(['user_id' => $user->id])->save();
                $order->events()->create(['from' => $order->status, 'to' => $order->status, 'actor' => 'system',
                    'note' => $created ? 'paid by a guest: new account '.$user->id : 'paid by a guest: attached to the account '.$user->id.' of the e-mail']);
            }
            $session = $order?->anonymousSession;
            if ($created && $session) {
                // as a registration does: the files and calculations of the session, and its other orders without an account
                $user->claimSession($session);
            } elseif ($order && ($file = $order->modelFile) && $file->owner_user_id === null && $file->anonymous_session_id === $order->anonymous_session_id) {
                // somebody's account: only this order's model comes along (print again, my models)
                $file->forceFill(['owner_user_id' => $user->id])->save();
            }

            return ['user' => $user, 'created' => $created];
        });
    }

    /** The guest's confirmation: the order is paid, here is the link to it (and, with a new account, to set a password). */
    public function confirm(FarmOrder $order, bool $created): void
    {
        $user = $order->user;
        if (! $user?->email) {
            return;
        }
        try {
            Mail::to($user->email)->locale($user->locale ?: config('app.locale'))->queue(new FarmGuestOrder($order, $created));
        } catch (\Throwable $e) {
            Log::warning('Guest order mail failed', ['order' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    /** The order's page for whoever holds the mail, without signing in (OrderController::authorizeOrder). */
    public static function orderLink(FarmOrder $order): string
    {
        return URL::temporarySignedRoute('farm.orders.show', now()->addDays(self::ORDER_LINK_DAYS), ['order' => $order]);
    }

    /** "Set a password" for an account made at a guest's payment: a fresh reset token when it is opened (GuestController::password). */
    public static function passwordLink(User $user): string
    {
        return URL::temporarySignedRoute('farm.guest.password', now()->addDays(self::PASSWORD_LINK_DAYS), ['user' => $user->id]);
    }
}
