<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who made the filament. Every kind and every colour of the catalogue so far is the farm's own "Matplace" brand;
 * spools of other makers come one by one, so a kind is unique by code + finish + maker (PETG by two makers can
 * differ in temperatures and price), and a colour names its maker only when it differs from its kind's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_materials', function (Blueprint $table) {
            $table->string('manufacturer', 80)->default('Matplace')->after('name');
        });
        Schema::table('farm_materials', function (Blueprint $table) {
            $table->dropUnique(['code', 'finish']);
            $table->unique(['code', 'finish', 'manufacturer']);
        });
        Schema::table('farm_colors', function (Blueprint $table) {
            $table->string('manufacturer', 80)->nullable()->after('name_en');   // null = the kind's maker
        });
    }

    public function down(): void
    {
        Schema::table('farm_colors', function (Blueprint $table) {
            $table->dropColumn('manufacturer');
        });
        Schema::table('farm_materials', function (Blueprint $table) {
            $table->dropUnique(['code', 'finish', 'manufacturer']);
            $table->unique(['code', 'finish']);
            $table->dropColumn('manufacturer');
        });
    }
};
