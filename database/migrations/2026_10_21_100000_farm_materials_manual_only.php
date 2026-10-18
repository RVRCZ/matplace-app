<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A kind of filament that breaks in the filament changer (glow-in-the-dark PLA snapped in the ACE; order F26-000065
 * cancelled, TODO #2 of 14 Oct 2026). Its colours are fed straight from the spool holder by a person, so they go only
 * into the slots of a printer in manual mode and are offered only from there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_materials', function (Blueprint $table) {
            $table->boolean('manual_only')->default(false)->after('finish');
        });
    }

    public function down(): void
    {
        Schema::table('farm_materials', function (Blueprint $table) {
            $table->dropColumn('manual_only');
        });
    }
};
