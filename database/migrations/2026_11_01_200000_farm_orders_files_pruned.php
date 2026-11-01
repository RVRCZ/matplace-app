<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When matplace:prune took the print files of a closed order (docs/AL.md §16): the admin page says so instead of a
 * link, and the order cannot go back to the queue without a G-code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->timestamp('files_pruned_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->dropColumn('files_pruned_at');
        });
    }
};
