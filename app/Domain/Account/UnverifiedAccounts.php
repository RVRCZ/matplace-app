<?php

namespace App\Domain\Account;

use App\Models\CreditTransaction;
use App\Models\DesignerProfile;
use App\Models\FarmOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts nobody confirmed and nobody used: what robots leave behind (docs/AH.md §10; 58 "Test" accounts on
 * 18 Oct 2026). Only these are deleted, for good (there is nothing to keep): the e-mail never confirmed, older than
 * the given days, no order of any kind, no line in the credit ledger, no model or calculation of their own, no sign-in
 * through Google or Facebook, no designer profile and no role but the customer's. Anything else stays.
 * Used by /admin/users and `php artisan users:prune-unverified`.
 */
final class UnverifiedAccounts
{
    public const DAYS = 7;

    /** @return Builder<User> */
    public function query(int $days = self::DAYS): Builder
    {
        return User::query()
            ->whereNull('email_verified_at')
            ->whereNull('anonymized_at')
            ->where('created_at', '<=', now()->subDays(max(1, $days)))
            ->whereNotExists(fn ($q) => $q->from((new FarmOrder)->getTable())->whereColumn('user_id', 'users.id'))
            ->whereNotExists(fn ($q) => $q->from((new CreditTransaction)->getTable())->whereColumn('user_id', 'users.id'))
            ->whereNotExists(fn ($q) => $q->from((new DesignerProfile)->getTable())->whereColumn('user_id', 'users.id'))
            ->whereDoesntHave('modelFiles')
            ->whereDoesntHave('calculations')
            ->whereDoesntHave('oauthIdentities')
            ->whereDoesntHave('roles', fn ($r) => $r->where('role', '!=', User::ROLE_CUSTOMER));
    }

    /** Deletes what query() finds; returns how many. Each account is checked again right before it goes. */
    public function prune(int $days = self::DAYS, ?int $by = null): int
    {
        $gone = 0;
        $this->query($days)->orderBy('id')->chunkById(100, function ($users) use ($days, &$gone) {
            foreach ($users as $user) {
                DB::transaction(function () use ($user, $days, &$gone) {
                    if (! $this->query($days)->whereKey($user->id)->lockForUpdate()->exists()) {
                        return;
                    }
                    if (Schema::hasTable('sessions')) {
                        DB::table('sessions')->where('user_id', $user->id)->delete();
                    }
                    DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                    $user->roles()->delete();
                    $user->delete();
                    $gone++;
                });
            }
        });
        Log::info('unverified accounts pruned', ['count' => $gone, 'days' => $days, 'by' => $by]);

        return $gone;
    }
}
