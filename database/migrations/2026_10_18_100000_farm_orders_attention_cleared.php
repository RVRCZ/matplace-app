<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Čeká na vás" on the farm dashboard lists orders by their state; the admin may clear the list without touching the
 * orders (no state change, no mail to the customer). An order comes back when something changes on it later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->timestamp('attention_cleared_at')->nullable()->after('handed_at');
        });
    }

    public function down(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->dropColumn('attention_cleared_at');
        });
    }
};
