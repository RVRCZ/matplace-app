<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

/** E-mail + password auth. Registration is only needed for "send to a printer" / "save" / printer tools. */
class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        if (! Auth::attempt(['email' => strtolower($data['email']), 'password' => $data['password']], $request->boolean('remember'))) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __('auth.failed')]);
        }
        $request->session()->regenerate();
        $this->claim($request);

        return redirect()->intended(route('account'));
    }

    public function showRegister(): View
    {
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
        event(new Registered($user));
        Auth::login($user, true);
        $request->session()->regenerate();
        $this->claim($request);

        $to = $request->input('role') === 'printer' ? route('account.roles.enable', 'printer') : route('account');

        return redirect()->intended($to);
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

    /** Attach files and calculations made before logging in to the account. */
    public static function claim(Request $request): void
    {
        $session = $request->attributes->get('anon_session');
        if ($session && $request->user()) {
            $request->user()->claimSession($session);
        }
    }
}
