<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A design of several separately printed parts (a box and its lid, the plates of a layered picture, the parts of a
 * painted 3MF) ordered as separate prints, every part from its own spool: `by_parts` switches it on, `part_plates`
 * lists the plates bottom to top as they print (`[{part, slot_id, color_id, copies, gcode_path, minutes, grams, …}]`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->boolean('by_parts')->default(false)->after('color_changes');
            $table->json('part_plates')->nullable()->after('by_parts');
        });
    }

    public function down(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->dropColumn(['by_parts', 'part_plates']);
        });
    }
};
