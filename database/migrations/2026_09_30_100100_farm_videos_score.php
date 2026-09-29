<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** How interesting a print video is likely to be (0-100, FarmVideos::score), to sort the approval list. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_videos', function (Blueprint $table) {
            $table->unsignedTinyInteger('score')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('farm_videos', function (Blueprint $table) {
            $table->dropColumn('score');
        });
    }
};
