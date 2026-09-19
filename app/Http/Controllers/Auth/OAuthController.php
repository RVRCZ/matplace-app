<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OauthIdentity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

/** Google / Facebook login through Socialite. Links to an existing account by e-mail, otherwise creates one. */
class OAuthController extends Controller
{
    private const PROVIDERS = ['google', 'facebook'];

    public function redirect(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::PROVIDERS, true) && config("services.$provider.client_id"), 404);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);
        try {
            $remote = Socialite::driver($provider)->user();
        } catch (\Throwable) {
            return redirect()->route('login')->withErrors(['email' => __('auth.oauth_failed')]);
        }

        $identity = OauthIdentity::where('provider', $provider)->where('provider_id', $remote->getId())->first();
        $user = $identity?->user;

        if (! $user) {
            $email = strtolower((string) $remote->getEmail());
            if ($email === '') {
                return redirect()->route('login')->withErrors(['email' => __('auth.oauth_no_email')]);
            }
            $user = User::where('email', $email)->first();
            if (! $user) {
                $user = User::create([
                    'name' => $remote->getName() ?: $remote->getNickname() ?: explode('@', $email)[0],
                    'email' => $email,
                    'password' => null,
                    'locale' => app()->getLocale(),
                    'email_verified_at' => now(),
                    'avatar_path' => null,
                ]);
                $user->setRole(User::ROLE_CUSTOMER, true);
            } elseif (! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
            OauthIdentity::create(['user_id' => $user->id, 'provider' => $provider, 'provider_id' => (string) $remote->getId()]);
        }

        if ($user->blocked_at) {
            return redirect()->route('login')->withErrors(['email' => __('auth.blocked')]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        AuthController::claim($request);

        return redirect()->intended(route('account'));
    }
}
