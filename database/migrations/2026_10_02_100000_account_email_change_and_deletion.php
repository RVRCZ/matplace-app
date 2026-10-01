<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The account as a customer's place: a verified e-mail, a safe change of the e-mail, a default delivery address and
 * pickup point, the account's currency (set by the first payment, step D) and deletion that keeps the bookkeeping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('currency', 3)->nullable();                 // CZK | EUR, null until the first payment or reward locks it
            $table->string('pending_email', 190)->nullable();          // the new address, until its owner confirms it
            $table->string('pending_email_token', 64)->nullable();     // sha256 of the token in the confirmation link
            $table->timestamp('pending_email_at')->nullable();         // the link is good for 24 hours from here
            $table->string('delivery_name', 120)->nullable();          // who the parcel is for (default: the account name)
            $table->json('pickup_point')->nullable();                  // favourite Packeta point: {id, name, carrier_id, country}
            $table->timestamp('deleted_at')->nullable();               // the owner asked for the account to go
            $table->timestamp('anonymized_at')->nullable();            // personal data scrubbed; orders and the ledger stay
        });

        Schema::table('model_files', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable();               // deleted by the owner; the row stays while an old order needs its name
        });

        // Verification becomes a condition for ordering from now on. People who already have an account (imported from the
        // old site, or registered on beta before this) are not sent back to their inbox: their address counts as verified.
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => DB::raw('created_at')]);
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('model_files', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['currency', 'pending_email', 'pending_email_token', 'pending_email_at', 'delivery_name', 'pickup_point', 'deleted_at', 'anonymized_at']);
        });
    }
};
