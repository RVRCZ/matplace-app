<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A big model (over the thinning threshold of triangles, or whose calculation took over a minute to slice) is not
 * sliced the moment its order is made: the order waits in `awaiting_choices` until the customer has chosen the
 * settings and confirmed the colour (TODO #41, docs/AL.md §11). `slice_run` counts the slices asked for, so a run
 * overtaken by a change of the choices knows to throw its result away; `slice_estimate_s` is what the page says the
 * slice will take, fixed when the order is made.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->string('status', 20)->change();   // + awaiting_choices
            $table->boolean('big_model')->default(false)->after('stage');
            $table->unsignedSmallInteger('slice_run')->default(0)->after('big_model');
            $table->unsignedInteger('slice_estimate_s')->nullable()->after('slice_run');
        });
    }

    public function down(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->dropColumn(['big_model', 'slice_run', 'slice_estimate_s']);
            $table->string('status', 14)->change();
        });
    }
};
