<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A raised text printed in its own colour: the spool the printer switches to at the height where the text starts. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('second_slot_id')->nullable()->after('farm_printer_slot_id');
            $table->unsignedBigInteger('second_color_id')->nullable()->after('second_slot_id');
        });
    }

    public function down(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->dropColumn(['second_slot_id', 'second_color_id']);
        });
    }
};
