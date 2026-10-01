<?php

namespace App\Http\Controllers;

use App\Domain\Account\AccountEraser;
use App\Domain\Account\EmailChange;
use App\Domain\Farm\Wallet;
use App\Models\OauthIdentity;
use App\Models\User;
use App\Notifications\ConfirmAccountDeletion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password as PasswordRule;

/** The sensitive part of the profile: login e-mail, password, linked sign-ins, deleting the account. */
class AccountSecurityController extends Controller
{
    private const ANCHOR = '#security';

    // ── e-mail ───────────────────────────────────────────────────────────────

    public function changeEmail(Request $request, EmailChange $change): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validateWithBag('email', [
            'new_email' => ['required', 'email', 'max:190'],
            'current_password' => [$user->hasPassword() ? 'required' : 'nullable', 'string'],
        ]);
        $email = strtolower(trim($data['new_email']));
        $error = match (true) {
            $email === strtolower($user->email) => __('user.email.same'),
            $user->hasPassword() && ! Hash::check((string) $data['current_password'], $user->password) => __('user.email.wrong_password'),
            $change->taken($email, $user) => __('user.email.taken'),
            default => null,
        };
        if ($error) {
            return $this->profile()->withErrors(['new_email' => $error], 'email')->withInput($request->only('new_email'));
        }
        $change->request($user, $email);

        return $this->profile()->with('status', __('user.email.requested', ['email' => $email]));
    }

    public function cancelEmail(Request $request, EmailChange $change): RedirectResponse
    {
        $change->cancel($request->user());

        return $this->profile()->with('status', __('user.email.cancelled'));
    }

    /** The link sent to the new address. Works without being logged in: the token is the proof. */
    public function confirmEmail(Request $request, string $token, EmailChange $change): RedirectResponse
    {
        $user = $change->confirm($token);
        if (! $user) {
            return redirect()->route($request->user() ? 'account.profile' : 'login')->with('error', __('user.email.expired'));
        }

        return redirect()->route($request->user()?->is($user) ? 'account.profile' : 'login')->with('status', __('user.email.confirmed', ['email' => $user->email]));
    }

    // ── password ─────────────────────────────────────────────────────────────

    public function password(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validateWithBag('password', [
            'current_password' => [$user->hasPassword() ? 'required' : 'nullable', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);
        if ($user->hasPassword() && ! Hash::check((string) $data['current_password'], $user->password)) {
            return $this->profile()->withErrors(['current_password' => __('user.email.wrong_password')], 'password');
        }
        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        return $this->profile()->with('status', __('user.password.changed'));
    }

    // ── linked sign-ins ──────────────────────────────────────────────────────

    public function disconnect(Request $request, OauthIdentity $identity): RedirectResponse
    {
        $user = $request->user();
        abort_unless($identity->user_id === $user->id, 404);
        // never saw off the branch the person sits on: a password or another sign-in must remain
        if (! $user->hasPassword() && $user->oauthIdentities()->count() < 2) {
            return $this->profile()->with('error', __('user.logins.last'));
        }
        $identity->delete();

        return $this->profile()->with('status', __('user.logins.disconnected', ['provider' => ucfirst($identity->provider)]));
    }

    // ── deleting the account ─────────────────────────────────────────────────

    /** With a password: delete now. Without one (Google / Facebook only): a link to the mailbox confirms it. */
    public function delete(Request $request, AccountEraser $eraser, Wallet $wallet): RedirectResponse
    {
        $user = $request->user();
        $request->validateWithBag('delete', $this->deleteRules($user, $wallet) + ['password' => [$user->hasPassword() ? 'required' : 'nullable', 'string']]);
        if (! $user->hasPassword()) {
            $user->notify(new ConfirmAccountDeletion);

            return $this->profile()->with('status', __('user.delete.mail_sent', ['email' => $user->email]));
        }
        if (! Hash::check((string) $request->input('password'), $user->password)) {
            return $this->profile()->withErrors(['password' => __('user.email.wrong_password')], 'delete');
        }

        return $this->eraseAndLeave($request, $user, $eraser);
    }

    /** The page behind the link from the e-mail: it only asks once more, the deletion itself is a POST. */
    public function confirmDelete(Request $request, User $user, Wallet $wallet): View|RedirectResponse
    {
        if (! $request->hasValidSignature() || $user->isAnonymized()) {
            return redirect()->route($request->user() ? 'account.profile' : 'home')->with('error', __('user.delete.invalid'));
        }

        return view('account.delete_confirm', [
            'user' => $user,
            'balance' => $wallet->balance($user),
            'action' => URL::temporarySignedRoute('account.delete.confirmed', now()->addMinutes(30), ['user' => $user->id]),
        ]);
    }

    public function confirmedDelete(Request $request, User $user, AccountEraser $eraser, Wallet $wallet): RedirectResponse
    {
        if (! $request->hasValidSignature() || $user->isAnonymized()) {
            return redirect()->route('home')->with('error', __('user.delete.invalid'));
        }
        $request->validate($this->deleteRules($user, $wallet));

        return $this->eraseAndLeave($request, $user, $eraser);
    }

    /** @return array<string, list<string>> */
    private function deleteRules(User $user, Wallet $wallet): array
    {
        return ['understand' => ['accepted']] + ($wallet->balance($user) > 0 ? ['credit' => ['accepted']] : []);
    }

    private function eraseAndLeave(Request $request, User $user, AccountEraser $eraser): RedirectResponse
    {
        $own = $request->user()?->is($user);
        $eraser->erase($user);
        if ($own) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('home')->with('status', __('user.delete.done'));
    }

    private function profile(): RedirectResponse
    {
        return redirect()->to(route('account.profile').self::ANCHOR);
    }
}
