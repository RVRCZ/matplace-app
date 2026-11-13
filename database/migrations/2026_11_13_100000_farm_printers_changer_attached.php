<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "The changer (ACE) is attached" – a switch of a printer (docs/AL.md §24). The ACE of a Kobra S1 or a Kobra 3 Max can be
 * taken off at any time; the machine then prints from the one spool fed straight into the head (position 1), its other
 * positions stay in the database as they were and come back when the switch is turned on again. True for every machine
 * until an admin says otherwise, so nothing changes with the deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_printers', function (Blueprint $table) {
            $table->boolean('changer_attached')->default(true)->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('farm_printers', function (Blueprint $table) {
            $table->dropColumn('changer_attached');
        });
    }
};
