<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Printer speed class: the slicer measures a fast reference printer, slower printers multiply the time. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_profiles', function (Blueprint $table) {
            $table->decimal('time_factor', 4, 2)->default(1.00)->after('express_pct');
        });
    }

    public function down(): void
    {
        Schema::table('pricing_profiles', function (Blueprint $table) {
            $table->dropColumn('time_factor');
        });
    }
};
