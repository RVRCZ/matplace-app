<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A printer whose connection flaps (Wi‑Fi dropping every half hour) sent the admin an "offline" mail at every drop:
 * 200 of them in two days. `offline_notified_at` keeps driving the state; `offline_alerted_at` remembers when the
 * admin was last told and keeps the mails hours apart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_printers', function (Blueprint $table) {
            $table->timestamp('offline_alerted_at')->nullable()->after('offline_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('farm_printers', function (Blueprint $table) {
            $table->dropColumn('offline_alerted_at');
        });
    }
};
