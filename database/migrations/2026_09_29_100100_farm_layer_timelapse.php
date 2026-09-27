<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Layer-synced time-lapse per printer (park position, when to use it, the square crop) and the square Short per order. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_printers', function (Blueprint $table) {
            $table->json('timelapse')->nullable()->after('process_overrides');
        });
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->string('timelapse_short_path')->nullable()->after('timelapse_path');
        });
    }

    public function down(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->dropColumn('timelapse_short_path');
        });
        Schema::table('farm_printers', function (Blueprint $table) {
            $table->dropColumn('timelapse');
        });
    }
};
