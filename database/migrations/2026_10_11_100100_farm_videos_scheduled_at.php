<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An approved video is not made public the moment it is uploaded: it gets the next free publishing slot (one video a
 * day in the evening, config youtube.publish_times) and YouTube flips it public at that time (`status.publishAt`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_videos', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('farm_videos', function (Blueprint $table) {
            $table->dropColumn('scheduled_at');
        });
    }
};
