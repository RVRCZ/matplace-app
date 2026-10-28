<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order without an account (docs/AO.md): a guest's order belongs to the anonymous session (cookie mp_sid) until
 * the card payment arrives; the webhook then gives it an account (a new one without a password, or the one the
 * e-mail belongs to). The guest's card payment has no account yet either.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreignId('anonymous_session_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anonymous_session_id');
        });
    }
};
