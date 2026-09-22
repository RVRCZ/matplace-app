<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Filament catalogue: a material is a kind (PLA, PLA+, PETG…) with its temperatures and slicer profile; a colour is one
 * filament of that kind with a name, a swatch, a photo and the Drive folder its photos come from. Product switches
 * (marketplace, farm) move from .env into farm_settings so an admin flips them without a deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_materials', function (Blueprint $table) {
            $table->string('finish', 12)->default('solid')->after('name');       // solid | matte | silk | luminous | glitter | special | flex | cf
            $table->unsignedSmallInteger('nozzle_temp')->nullable()->after('density');
            $table->unsignedSmallInteger('nozzle_temp_first')->nullable()->after('nozzle_temp');
            $table->unsignedSmallInteger('bed_temp')->nullable()->after('nozzle_temp_first');
            $table->text('notes')->nullable()->after('price_per_gram');
            $table->unsignedSmallInteger('sort')->default(100)->after('enabled');
        });
        // one row per kind + finish (PLA silk and PLA matte differ in temperatures and price)
        Schema::table('farm_materials', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['code', 'finish']);
        });

        Schema::table('farm_colors', function (Blueprint $table) {
            $table->string('code', 80)->nullable()->after('id');                 // catalogue code (the Drive folder name)
            $table->string('name_en', 80)->nullable()->after('name');
            $table->string('drive_folder', 64)->nullable()->after('photo_path');   // Google Drive folder with the photos
            $table->boolean('in_stock')->default(true)->after('enabled');
            $table->unsignedSmallInteger('sort')->default(100)->after('in_stock');
        });
    }

    public function down(): void
    {
        Schema::table('farm_colors', function (Blueprint $table) {
            $table->dropColumn(['code', 'name_en', 'drive_folder', 'in_stock', 'sort']);
        });
        Schema::table('farm_materials', function (Blueprint $table) {
            $table->dropUnique(['code', 'finish']);
            $table->unique('code');
            $table->dropColumn(['finish', 'nozzle_temp', 'nozzle_temp_first', 'bed_temp', 'notes', 'sort']);
        });
    }
};
