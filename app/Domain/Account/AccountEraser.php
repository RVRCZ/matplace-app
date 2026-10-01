<?php

namespace App\Domain\Account;

use App\Domain\Farm\OrderFlow;
use App\Domain\Farm\Wallet;
use App\Events\AccountErasing;
use App\Models\AnonymousSession;
use App\Models\Calculation;
use App\Models\FarmOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Deleting an account = taking the person out of it. The row stays, because orders, payments and the credit ledger
 * are bookkeeping and must keep an owner; everything that says who the owner was goes.
 *
 * Goes: name, e-mail, phone, address, pickup point, avatar, password, linked sign-ins, sessions, calculations,
 *       models nothing needs, delivery addresses of closed orders, unused credit (one "forfeit" line in the ledger).
 * Stays: orders, payments, ledger lines; prints already paid run to the end (they keep their own copy of the file).
 */
final class AccountEraser
{
    public function __construct(private readonly Wallet $wallet, private readonly ModelLibrary $models) {}

    public function erase(User $user): void
    {
        if ($user->isAnonymized()) {
            return;
        }
        // orders that were never paid are simply dropped (outside the transaction: the flow sends its own commands)
        $flow = app(OrderFlow::class);
        FarmOrder::where('user_id', $user->id)->whereIn('status', [FarmOrder::STATUS_UPLOADED, FarmOrder::STATUS_SLICED])->whereNull('paid_at')->get()
            ->each(fn (FarmOrder $o) => $o->canMoveTo(FarmOrder::STATUS_CANCELLED) ? $flow->move($o, FarmOrder::STATUS_CANCELLED, 'user', $user->id, 'account deleted') : null);

        $avatar = $user->avatar_path;
        DB::transaction(function () use ($user) {
            $balance = $this->wallet->balance($user);
            if ($balance > 0) {
                $this->wallet->forfeit($user, $balance, __('user.delete.forfeit_note', [], $user->preferredLocale()));
            }
            // other features clean up after themselves (designer cards, statistics): listeners run right here, in the transaction
            event(new AccountErasing($user));
            $this->models->deleteAllOf($user);
            Calculation::where('owner_user_id', $user->id)->delete();
            // a parcel that was delivered (or never sent) no longer needs to know where it went
            FarmOrder::where('user_id', $user->id)->whereIn('status', [FarmOrder::STATUS_HANDED_OVER, FarmOrder::STATUS_CANCELLED, FarmOrder::STATUS_FAILED])
                ->update(['shipping_address' => null, 'note' => null, 'terms_ip' => null]);

            $user->oauthIdentities()->delete();
            AnonymousSession::where('claimed_by_user_id', $user->id)->update(['ip' => null, 'user_agent' => null]);
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            $user->forceFill([
                'name' => __('user.delete.name', [], 'cs'),
                'email' => 'deleted-'.$user->id.'@invalid',
                'email_verified_at' => null,
                'password' => null, 'remember_token' => null,
                'phone' => null, 'phone_verified_at' => null,
                'street' => null, 'zip' => null, 'city' => null, 'lat' => null, 'lng' => null,
                'delivery_name' => null, 'pickup_point' => null, 'avatar_path' => null,
                'pending_email' => null, 'pending_email_token' => null, 'pending_email_at' => null,
                'notify_email' => false, 'notify_push' => false,
                'deleted_at' => now(), 'anonymized_at' => now(),
            ])->save();
        });

        if ($avatar) {
            Storage::disk('public')->delete($avatar);
        }
    }
}
