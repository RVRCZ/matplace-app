<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * More pieces than one plate takes: the order prints on several plates one after another. Every full plate shares one
 * G-code, the last (partly filled) plate has its own; the job knows which plate it prints.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('plates')->default(1)->after('copies');
            $table->unsignedSmallInteger('plates_done')->default(0)->after('plates');
            $table->unsignedSmallInteger('plate_copies')->default(1)->after('plates_done');   // pieces on a full plate
            $table->unsignedSmallInteger('rest_copies')->nullable()->after('plate_copies');   // pieces on the last plate when fewer
            $table->string('rest_gcode_path')->nullable()->after('gcode_sha256');
        });
        Schema::table('farm_print_jobs', function (Blueprint $table) {
            $table->unsignedSmallInteger('plate')->default(1)->after('slot');
        });
    }

    public function down(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->dropColumn(['plates', 'plates_done', 'plate_copies', 'rest_copies', 'rest_gcode_path']);
        });
        Schema::table('farm_print_jobs', function (Blueprint $table) {
            $table->dropColumn('plate');
        });
    }
};
