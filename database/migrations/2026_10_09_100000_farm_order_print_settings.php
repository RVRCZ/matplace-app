<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Print settings beyond the presets. `print_settings` is the customer's "advanced" choice (infill percent, walls,
 * top and bottom layers; null = the presets as before). `admin_overrides` are process settings of the slicer the
 * admin writes onto one order (a drawing says "3 perimeters, gyroid infill"); they win over everything else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->json('print_settings')->nullable()->after('supports');
            $table->json('admin_overrides')->nullable()->after('print_settings');
        });
    }

    public function down(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->dropColumn(['print_settings', 'admin_overrides']);
        });
    }
};
