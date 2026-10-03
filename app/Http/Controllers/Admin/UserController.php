<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Account\AccountEraser;
use App\Domain\Designer\DesignerProfiles;
use App\Domain\Farm\Wallet;
use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\DesignerProfile;
use App\Models\FarmOrder;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * /admin/users: who has an account. Finding a person, what they did (prints, credit, designer profile, sign-ins),
 * the roles, a password link when somebody is locked out, and deleting an account the way the person would
 * (anonymised, the bookkeeping stays).
 */
class UserController extends Controller
{
    public const ROLES = ['admin', 'designer'];

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $role = in_array($request->query('role'), self::ROLES, true) ? (string) $request->query('role') : null;
        $users = User::query()->with('roles')
            ->withCount(['modelFiles'])
            ->addSelect(['orders_count' => FarmOrder::selectRaw('count(*)')->whereColumn('user_id', 'users.id')->whereNotNull('paid_at')])
            ->addSelect(['balance' => CreditTransaction::selectRaw('coalesce(sum(amount), 0)')->whereColumn('user_id', 'users.id')])
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")))
            ->when($role, fn ($w) => $w->whereHas('roles', fn ($r) => $r->where('role', $role)->whereNull('disabled_at')))
            ->when($request->query('deleted') === '1', fn ($w) => $w->whereNotNull('anonymized_at'))
            ->when($request->query('deleted') !== '1', fn ($w) => $w->whereNull('anonymized_at'))
            ->orderByDesc('id')->paginate(50)->withQueryString();

        return view('admin.users.index', [
            'users' => $users, 'q' => $q, 'role' => $role, 'deleted' => $request->query('deleted') === '1',
            'counts' => [
                'all' => User::whereNull('anonymized_at')->count(),
                'verified' => User::whereNotNull('email_verified_at')->whereNull('anonymized_at')->count(),
                'designers' => UserRole::where('role', 'designer')->whereNull('disabled_at')->count(),
                'month' => User::where('created_at', '>=', now()->subDays(30))->count(),
            ],
        ]);
    }

    public function show(Request $request, User $user, Wallet $wallet): View
    {
        $user->load(['roles', 'oauthIdentities']);

        return view('admin.users.show', [
            'user' => $user,
            'balance' => $wallet->balance($user),
            'ledger' => CreditTransaction::where('user_id', $user->id)->latest('id')->limit(15)->get(),
            'orders' => FarmOrder::where('user_id', $user->id)->with(['modelFile', 'color'])->latest('id')->limit(20)->get(),
            'ordersTotal' => FarmOrder::where('user_id', $user->id)->count(),
            'designer' => DesignerProfile::where('user_id', $user->id)->first(),
            'filesCount' => $user->modelFiles()->count(),
            'calculationsCount' => $user->calculations()->count(),
            'events' => DB::table('events')->where('user_id', $user->id)->selectRaw('type, count(*) as n, max(created_at) as last_at')->groupBy('type')->orderByDesc('n')->get(),
            'isSelf' => $user->id === $request->user()->id,
        ]);
    }

    /** A role switched on or off. Nobody takes their own admin role away (the last admin would lock everyone out). */
    public function role(Request $request, User $user, DesignerProfiles $profiles): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', 'in:'.implode(',', self::ROLES)], 'enabled' => ['required', 'boolean']]);
        $enabled = $request->boolean('enabled');
        if ($data['role'] === 'admin' && ! $enabled && $user->id === $request->user()->id) {
            return back()->with('error', 'Vlastní roli admin si odebrat nemůžete.');
        }
        if ($data['role'] === 'designer' && $enabled && ! $user->email_verified_at) {
            return back()->with('error', 'Designérský profil jde zapnout jen ověřenému e-mailu.');
        }
        if ($data['role'] === 'designer' && $enabled) {
            // the same as when the person switches it on: the profile comes with the role
            $profiles->enable($user);
        } elseif ($data['role'] === 'designer') {
            // the role goes, the profile stays hidden with its cards (it comes back the moment the role is on again)
            $user->setRole('designer', false);
            DesignerProfile::where('user_id', $user->id)->update(['visible' => false]);
        } else {
            $user->setRole($data['role'], $enabled);
        }

        return back()->with('status', 'Role '.$data['role'].' '.($enabled ? 'zapnutá' : 'vypnutá').'.');
    }

    /** The same link the "forgotten password" form sends; for somebody who writes that they cannot sign in. */
    public function resetLink(User $user): RedirectResponse
    {
        if ($user->isAnonymized()) {
            return back()->with('error', 'Smazaný účet.');
        }
        Password::sendResetLink(['email' => $user->email]);

        return back()->with('status', 'Odkaz na nové heslo odešel na '.$user->email.'.');
    }

    /** Deleted the way the person would do it in the profile: anonymised, orders and bookkeeping stay. */
    public function erase(Request $request, User $user, AccountEraser $eraser): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Vlastní účet se maže z profilu, ne z administrace.');
        }
        if ($user->isAdmin()) {
            return back()->with('error', 'Nejdřív odeberte roli admin.');
        }
        if ($request->input('confirm') !== $user->email) {
            return back()->with('error', 'Pro smazání opište e-mail účtu.');
        }
        $eraser->erase($user);

        return redirect()->route('admin.users.show', $user)->with('status', 'Účet je smazaný (anonymizovaný). Zakázky a kniha kreditu zůstaly.');
    }
}
