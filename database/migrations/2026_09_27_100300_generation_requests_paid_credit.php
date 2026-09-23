<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A generation beyond the free daily quota can be paid from the farm credit; the price paid is kept on the request. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generation_requests', function (Blueprint $table) {
            $table->decimal('paid_credit', 8, 2)->nullable()->after('cost_cents');
        });
    }

    public function down(): void
    {
        Schema::table('generation_requests', function (Blueprint $table) {
            $table->dropColumn('paid_credit');
        });
    }
};
