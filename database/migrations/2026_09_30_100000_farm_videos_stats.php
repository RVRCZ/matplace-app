<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** YouTube numbers of each print video, refreshed daily (youtube:stats). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_videos', function (Blueprint $table) {
            $table->unsignedBigInteger('views')->nullable()->after('published_at');
            $table->unsignedBigInteger('likes')->nullable()->after('views');
            $table->unsignedBigInteger('comments')->nullable()->after('likes');
            $table->timestamp('stats_at')->nullable()->after('comments');
        });
    }

    public function down(): void
    {
        Schema::table('farm_videos', function (Blueprint $table) {
            $table->dropColumn(['views', 'likes', 'comments', 'stats_at']);
        });
    }
};
