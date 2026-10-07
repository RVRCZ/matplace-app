<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A picture printed in filament colours one on another: every height at which the machine swaps the spool, with the
 * spool it swaps to (`[{z, slot_id, color_id}, …]`, bottom to top). The one second colour of a plate with a text
 * (second_slot_id) stays as the first entry's copy, so older orders and pages keep reading.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->json('color_changes')->nullable()->after('second_color_id');
        });
    }

    public function down(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->dropColumn('color_changes');
        });
    }
};
