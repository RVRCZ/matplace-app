<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The nine digits Packeta wants on the Z-BOX keypad when the operator consigns the parcel there instead of at
 * a branch (packetInfo → consignPassword). Kept with the order so the admin page shows it next to the label
 * and nobody has to look it up in the carrier's client section.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->string('packeta_consign_code', 20)->nullable()->after('packeta_barcode');
        });
    }

    public function down(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->dropColumn('packeta_consign_code');
        });
    }
};
