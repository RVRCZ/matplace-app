<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Locales;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * E-mail + password auth. An account is needed to order a print and to keep models; everything else works without one.
 * After logging in a visitor goes back to where they were working (a tool, the calculator), not to the account.
 */
class AuthController extends Controller
{
    public function showLogin(Request $request): View
    {
        $this->rememberOrigin($request);

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        // a deleted (anonymised) account has no way in
        $credentials = ['email' => strtolower($data['email']), 'password' => $data['password'], fn ($query) => $query->whereNull('anonymized_at')];
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __('auth.failed')]);
        }
        if ($request->user()->blocked_at) {
            Auth::logout();

            return back()->withInput($request->only('email'))->withErrors(['email' => __('auth.blocked')]);
        }
        $request->session()->regenerate();
        $this->claim($request);

        return redirect()->intended(route('account'));
    }

    public function showRegister(Request $request): View
    {
        $this->rememberOrigin($request);

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', PasswordRule::min(8)],
            'terms' => ['accepted'],
            'website' => ['prohibited'], // honeypot
        ]);
        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'locale' => app()->getLocale(),
        ]);
        $user->setRole(User::ROLE_CUSTOMER, true);
        Auth::login($user, true);
        $request->session()->regenerate();
        $this->claim($request);
        // sends the verification e-mail (User::sendEmailVerificationNotification) in the language of the page
        event(new Registered($user));

        return redirect()->intended(route('account'))->with('status', __('user.verify.registered', ['email' => $user->email]));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function showForgot(): View
    {
        return view('auth.forgot');
    }

    public function sendReset(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink(['email' => strtolower($request->input('email'))]);

        // Always the same answer, so the form does not reveal which e-mails exist.
        return back()->with('status', __('auth.reset_sent'));
    }

    public function showReset(Request $request, string $token): View
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);
        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password)])->save();
        });
        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', __('auth.reset_done'));
    }

    /**
     * A visitor who opens the login or registration page from one of our pages goes back there afterwards.
     * (A page that demanded the login has already stored itself; that one wins.)
     */
    private function rememberOrigin(Request $request): void
    {
        if ($request->session()->has('url.intended')) {
            return;
        }
        $referer = (string) $request->headers->get('referer');
        if ($referer === '' || parse_url($referer, PHP_URL_HOST) !== $request->getHost()) {
            return;
        }
        $path = preg_replace('#^/('.Locales::pattern().')(?=/|$)#', '', '/'.ltrim((string) parse_url($referer, PHP_URL_PATH), '/')) ?: '/';
        if (preg_match('#^/(login|register|forgot-password|reset-password|email|auth|logout|account/(email|delete))(/|$)#', $path)) {
            return;
        }
        $request->session()->put('url.intended', $referer);
    }

    /** Attach files and calculations made before logging in to the account. */
    public static function claim(Request $request): void
    {
        $session = $request->attributes->get('anon_session');
        if ($session && $request->user()) {
            $request->user()->claimSession($session);
        }
    }
}
