<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;

/** An account made at a guest's card payment (App\Domain\Farm\GuestCheckout) gets its password from the confirmation mail. */
class GuestController extends Controller
{
    /**
     * The signed "set a password" link of the mail: a fresh reset token each time it is opened (a reset token alone
     * would expire in an hour), straight to the reset form. Once the account has a password of its owner's (the
     * e-mail is confirmed by the reset), the link only leads to the login.
     */
    public function password(User $user): RedirectResponse
    {
        if ($user->email_verified_at !== null || $user->isAnonymized()) {
            return redirect()->route('login')->with('status', __('farm.guest.password_set'));
        }

        return redirect()->route('password.reset', ['token' => Password::broker()->createToken($user), 'email' => $user->email]);
    }
}
