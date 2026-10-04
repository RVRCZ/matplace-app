<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** How long each step of the work behind a customer's wait took (queue, conversion, repair, slicing…), seconds. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['model_files', 'calculations', 'farm_orders'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->json('timings')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['model_files', 'calculations', 'farm_orders'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('timings');
            });
        }
    }
};
