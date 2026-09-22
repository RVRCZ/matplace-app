<?php

namespace App\Http\Controllers;

use App\Models\Calculation;
use App\Models\PricingProfile;
use App\Models\PrinterProfile;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;

/** /ucet — the one account: saved calculations, role switches, profile. */
class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->load('roles');
        $calculations = Calculation::with('modelFile')
            ->where('owner_user_id', $user->id)
            ->latest('id')->limit(30)->get();

        return view('account.index', ['user' => $user, 'calculations' => $calculations]);
    }

    public function profile(Request $request): View
    {
        $user = $request->user();

        return view('account.profile', [
            'user' => $user,
            'balance' => config('farm.enabled') ? app(\App\Domain\Farm\Wallet::class)->balance($user) : null,
            'orders' => config('farm.enabled') ? \App\Models\FarmOrder::with(['modelFile', 'color'])->where('user_id', $user->id)->latest('id')->limit(5)->get() : collect(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'street' => ['nullable', 'string', 'max:160'],
            'zip' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'size:2'],
            'locale' => ['nullable', 'in:cs,en,es'],
            'notify_email' => ['nullable', 'boolean'],
            'password' => ['nullable', 'confirmed', PasswordRule::min(8)],
        ]);
        $user->fill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'street' => $data['street'] ?? null,
            'zip' => $data['zip'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => strtoupper($data['country'] ?? $user->country ?? 'CZ'),
            'locale' => $data['locale'] ?? $user->locale,
            'notify_email' => $request->boolean('notify_email'),
        ]);
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        return back()->with('status', __('account.saved'));
    }

    /** Switch a role on: printer → creates the printer profile with a default price list. */
    public function enableRole(Request $request, string $role): RedirectResponse
    {
        abort_unless(in_array($role, [User::ROLE_PRINTER, User::ROLE_DESIGNER], true), 404);
        $user = $request->user();
        $user->setRole($role, true);

        if ($role === User::ROLE_PRINTER && ! $user->printerProfile) {
            $profile = PrinterProfile::create([
                'user_id' => $user->id,
                'display_name' => $user->name,
                'slug' => PrinterProfile::makeSlug($user->name),
                'contact_email' => $user->email,
                'contact_phone' => $user->phone,
                'visible' => false, // until the profile has a material and prices
            ]);
            PricingProfile::create(['printer_profile_id' => $profile->id, 'name' => 'Standard', 'is_default' => true]);

            return redirect()->route('printer.profile')->with('status', __('printer.welcome'));
        }

        return redirect()->route('account')->with('status', __('account.role_enabled'));
    }

    public function disableRole(Request $request, string $role): RedirectResponse
    {
        abort_unless(in_array($role, [User::ROLE_PRINTER, User::ROLE_DESIGNER], true), 404);
        $request->user()->setRole($role, false);

        return redirect()->route('account')->with('status', __('account.role_disabled'));
    }
}
