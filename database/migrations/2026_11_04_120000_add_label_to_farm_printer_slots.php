<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The name of a spool position as the operator sees it ("Stojan 2", "ACE 1", "INDX 5"), typed in by the admin when the
 * machine's model does not imply it (App\Domain\Farm\SpoolPositions; TODO #66, docs/AL.md §19). Null = derived.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_printer_slots', function (Blueprint $table) {
            $table->string('label', 40)->nullable()->after('farm_color_id');
        });
    }

    public function down(): void
    {
        Schema::table('farm_printer_slots', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};
