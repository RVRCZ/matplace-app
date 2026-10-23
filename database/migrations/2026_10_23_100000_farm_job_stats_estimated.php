<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A print that ends without numbers from the printer (job 154, the dragon on the Kobra X, 15 hours overnight, 23 Oct
 * 2026: done with 0 s and 0 mm): the duration is taken from the job's own clock (started → finished, pauses taken
 * off) and the filament from the order's slice, and both are marked as estimates so the mail, the dashboard and the
 * statistics know. Pauses are counted from now on (`paused_at`, `paused_s`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_print_jobs', function (Blueprint $table) {
            $table->timestamp('paused_at')->nullable()->after('started_at');
            $table->unsignedInteger('paused_s')->default(0)->after('paused_at');
            $table->boolean('stats_estimated')->default(false)->after('filament_used_mm');
        });
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->boolean('stats_estimated')->default(false)->after('actual_source');
        });
    }

    public function down(): void
    {
        Schema::table('farm_print_jobs', function (Blueprint $table) {
            $table->dropColumn(['paused_at', 'paused_s', 'stats_estimated']);
        });
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->dropColumn('stats_estimated');
        });
    }
};
