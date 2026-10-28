<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The colours an uploaded 3MF is painted in (docs/AL.md §14, App\Domain\Files\PaintedColors): what colours, which join
 * their body, the triangle ranges of the preview by colour, the layers where colours meet. Null for anything else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_files', function (Blueprint $table) {
            $table->json('colors')->nullable()->after('mesh_report');
        });
    }

    public function down(): void
    {
        Schema::table('model_files', function (Blueprint $table) {
            $table->dropColumn('colors');
        });
    }
};
