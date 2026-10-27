<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The slice of an inspiration model's own file (catalog:slice, docs/AN.md §13): the minutes and grams of one piece in
 * the material it was sliced in, when, the calculation it came from (its page /c/<token> is where a visitor orders), or
 * why it failed. Widths against MariaDB: minutes up to 4 billion, grams up to 9 999 999.9, the error cut to 120 chars.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_models', function (Blueprint $table) {
            $table->unsignedInteger('slice_minutes')->nullable()->after('enriched_price_czk');
            $table->decimal('slice_grams', 8, 1)->nullable()->after('slice_minutes');
            $table->string('slice_material', 10)->nullable()->after('slice_grams');
            $table->timestamp('sliced_at')->nullable()->after('slice_material')->index();
            $table->string('slice_error', 120)->nullable()->after('sliced_at');
            $table->foreignId('slice_calculation_id')->nullable()->after('slice_error')->constrained('calculations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('catalog_models', function (Blueprint $table) {
            $table->dropConstrainedForeignId('slice_calculation_id');
            $table->dropIndex(['sliced_at']);
            $table->dropColumn(['slice_minutes', 'slice_grams', 'slice_material', 'sliced_at', 'slice_error']);
        });
    }
};
