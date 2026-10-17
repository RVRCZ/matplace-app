<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * More than one video per print (docs/AD.md): the square Short, the classic 16:9 (`farm_orders.timelapse_wide_path`)
 * and the weekly cut without an order (`path`, `meta` with the week and its orders). Old rows are Shorts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->string('timelapse_wide_path')->nullable()->after('timelapse_short_path');
        });

        Schema::table('farm_videos', function (Blueprint $table) {
            $table->string('kind', 8)->default('short')->after('farm_order_id');   // App\Models\FarmVideo::KIND_*
            $table->string('path')->nullable()->after('kind');                     // the weekly cut's file on the farm disk
            $table->json('meta')->nullable()->after('music');                      // the weekly cut: week and orders
        });

        Schema::table('farm_videos', function (Blueprint $table) {
            // the new pair index first: MySQL keeps the foreign key on it while the old unique one goes
            $table->unique(['farm_order_id', 'kind']);
            $table->dropUnique(['farm_order_id']);
            $table->unsignedBigInteger('farm_order_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('farm_videos', function (Blueprint $table) {
            $table->unique('farm_order_id');
            $table->dropUnique(['farm_order_id', 'kind']);
            $table->dropColumn(['kind', 'path', 'meta']);
        });

        Schema::table('farm_orders', function (Blueprint $table) {
            $table->dropColumn('timelapse_wide_path');
        });
    }
};
