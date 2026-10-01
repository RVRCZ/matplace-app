<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The link from the verification e-mail, and "send it again". The link works in any browser: it is the proof itself. */
class VerificationController extends Controller
{
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::find($id);
        $valid = $request->hasValidSignature() && $user && ! $user->isAnonymized() && hash_equals(sha1((string) $user->email), $hash);
        if (! $valid) {
            return redirect()->route($request->user() ? 'account' : 'login')->with('error', __('user.verify.invalid'));
        }
        $fresh = ! $user->hasVerifiedEmail();
        if ($fresh && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }
        if ($request->user()?->is($user)) {
            return redirect()->intended(route('account'))->with('status', __($fresh ? 'user.verify.done' : 'user.verify.already'));
        }

        // opened somewhere the person is not logged in (the mail app's own browser): the address is verified, they log in as usual
        return redirect()->route($request->user() ? 'account' : 'login')->with('status', __('user.verify.done_login'));
    }

    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return back()->with('status', __('user.verify.already'));
        }
        $user->sendEmailVerificationNotification();

        return back()->with('status', __('user.verify.sent', ['email' => $user->email]));
    }
}
