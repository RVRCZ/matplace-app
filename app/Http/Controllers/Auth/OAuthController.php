<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Social\SocialPublisher;
use App\Http\Controllers\Controller;
use App\Models\OauthIdentity;
use App\Models\User;
use App\Support\Locales;
use App\Support\Track;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

/**
 * Google / Facebook through Socialite. Three things can happen in the callback:
 *  - a known identity logs its account in;
 *  - an unknown identity joins the account with the same e-mail, or creates one (the e-mail counts as verified);
 *  - a logged-in person who asked for it from the profile (?link=1) gets the identity added to their account.
 */
class OAuthController extends Controller
{
    private const PROVIDERS = ['google', 'facebook'];

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::PROVIDERS, true) && config("services.$provider.client_id"), 404);
        if ($request->user() && ! $request->boolean('link')) {
            return redirect()->route('account');
        }
        // the callback address has no language in it: remember the one of the page the visitor came from
        session(['locale_once' => Locales::forApi($request), 'oauth_link' => $request->user() && $request->boolean('link') ? $request->user()->id : null]);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);
        $linkFor = $request->session()->pull('oauth_link');
        $linking = $request->user() && $linkFor === $request->user()->id;
        try {
            $remote = Socialite::driver($provider)->user();
        } catch (\Throwable) {
            return $linking
                ? redirect()->to(route('account.profile').'#security')->with('error', __('auth.oauth_failed'))
                : redirect()->route('login')->withErrors(['email' => __('auth.oauth_failed')]);
        }
        $identity = OauthIdentity::where('provider', $provider)->where('provider_id', $remote->getId())->first();

        if ($linking) {
            return $this->link($request->user(), $identity, $provider, (string) $remote->getId());
        }
        if ($request->user()) {
            return redirect()->route('account');
        }

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
                Track::event('register', $user, ['kind' => $provider, 'event_id' => 'register-'.$user->id]);
                app(SocialPublisher::class)->conversion('register', 'register-'.$user->id, $request, $user->email);
            } elseif (! $user->email_verified_at) {
                // Somebody registered this address with a password and never proved it was theirs. Its real owner has
                // just arrived through Google: the unproven password must not open the account any more.
                $user->forceFill(['email_verified_at' => now(), 'password' => null, 'remember_token' => null])->save();
            }
            OauthIdentity::create(['user_id' => $user->id, 'provider' => $provider, 'provider_id' => (string) $remote->getId()]);
        }

        if ($user->blocked_at || $user->isAnonymized()) {
            return redirect()->route('login')->withErrors(['email' => __('auth.blocked')]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        AuthController::claim($request);

        return redirect()->intended(route('account'));
    }

    /** "Link Google" from the profile: the identity joins the logged-in account, unless another account already owns it. */
    private function link(User $user, ?OauthIdentity $identity, string $provider, string $providerId): RedirectResponse
    {
        $back = redirect()->to(route('account.profile').'#security');
        if ($identity && $identity->user_id !== $user->id) {
            return $back->with('error', __('user.logins.taken', ['provider' => ucfirst($provider)]));
        }
        if (! $identity) {
            OauthIdentity::create(['user_id' => $user->id, 'provider' => $provider, 'provider_id' => $providerId]);
        }

        return $back->with('status', __('user.logins.connected', ['provider' => ucfirst($provider)]));
    }
}
