<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The order's history keeps the states it went through in columns as wide as the state used to be (14): the new
 * state `awaiting_choices` (TODO #41, 16 letters) did not fit, and the first big orders of 25 Oct 2026 died with a
 * 500 right after they were made. As wide as farm_orders.status now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_order_events', function (Blueprint $table) {
            $table->string('from', 20)->nullable()->change();
            $table->string('to', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('farm_order_events', function (Blueprint $table) {
            $table->string('from', 14)->nullable()->change();
            $table->string('to', 14)->change();
        });
    }
};
