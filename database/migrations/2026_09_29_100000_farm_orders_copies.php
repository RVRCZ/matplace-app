<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Several copies of the model on one plate, printed in one go: the calculator's "N pieces fit on the plate" carried through. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('copies')->default(1)->after('strength');
        });
    }

    public function down(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->dropColumn('copies');
        });
    }
};
