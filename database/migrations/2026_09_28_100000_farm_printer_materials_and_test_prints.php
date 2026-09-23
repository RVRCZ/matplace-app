<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tuning a filament per machine. A kind (PLA+, PETG…) says what it needs in general; what it needs on THIS printer,
 * and optionally what one spool needs on it, is a farm_printer_materials row: slicer overrides, temperatures, how
 * sure we are (untested / testing / tuned) and where the values came from. Test prints are farm orders of kind
 * `test`: no customer, no price, straight to the queue, and they remember which row and which candidate settings
 * they were printed with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_printer_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_printer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_color_id')->nullable()->constrained()->cascadeOnDelete();   // null = the kind on this printer
            $table->json('overrides')->nullable();          // nozzle_temp, nozzle_temp_first, bed_temp, process {…}, filament {…}
            $table->string('status', 10)->default('untested');   // untested | testing | tuned
            $table->string('source', 10)->default('generic');    // generic | library | inherited | test | manual
            $table->unsignedSmallInteger('version')->default(1);
            $table->unsignedTinyInteger('score')->nullable();    // 1–5 from the last evaluated test
            $table->timestamp('tested_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('history')->nullable();            // earlier versions: [{version, overrides, source, note, at}]
            $table->timestamps();
            $table->unique(['farm_printer_id', 'farm_material_id', 'farm_color_id'], 'farm_printer_materials_unique');
        });

        Schema::table('farm_orders', function (Blueprint $table) {
            $table->string('kind', 8)->default('print')->after('status');          // print | test
            $table->foreignId('farm_printer_material_id')->nullable()->after('farm_printer_slot_id')->constrained()->nullOnDelete();
            $table->json('test_params')->nullable()->after('note');                // object, floors, temperatures, candidate overrides
        });
    }

    public function down(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('farm_printer_material_id');
            $table->dropColumn(['kind', 'test_params']);
        });
        Schema::dropIfExists('farm_printer_materials');
    }
};
