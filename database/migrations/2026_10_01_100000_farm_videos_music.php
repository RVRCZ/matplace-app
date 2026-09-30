<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The background track a print video got on YouTube (file name in config youtube.music_dir). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_videos', function (Blueprint $table) {
            $table->string('music')->nullable()->after('score');
        });
    }

    public function down(): void
    {
        Schema::table('farm_videos', function (Blueprint $table) {
            $table->dropColumn('music');
        });
    }
};
