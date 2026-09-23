<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every spool can want its own settings: a colour carries optional print overrides (temperatures apply to the finished
 * G-code, anything else means a re-slice) and the notes from its test prints. Finished orders get a quality rating
 * from the operator: with the measured time and weight it is the feedback the material settings are tuned from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_colors', function (Blueprint $table) {
            $table->json('print_overrides')->nullable()->after('drive_folder');   // nozzle_temp, nozzle_temp_first, bed_temp + slicer keys
            $table->text('test_notes')->nullable()->after('print_overrides');     // what the test object showed
        });
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('quality_rating')->nullable()->after('actual_source');   // 1–5 by the operator
            $table->string('quality_note', 500)->nullable()->after('quality_rating');
        });
    }

    public function down(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->dropColumn(['quality_rating', 'quality_note']);
        });
        Schema::table('farm_colors', function (Blueprint $table) {
            $table->dropColumn(['print_overrides', 'test_notes']);
        });
    }
};
