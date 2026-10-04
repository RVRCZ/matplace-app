<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Videos used to go up to YouTube as private copies at once and wait there for the admin: every one of them counted
 * against the channel's daily upload limit, rejected ones included. Now a video waits here (`pending`) and goes up
 * only once the admin approved it (`approved_at`), public right after the upload.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_videos', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('decided_at');
        });
        // what still waited for an upload nobody asked for waits for the admin instead
        DB::table('farm_videos')->whereIn('status', ['queued', 'uploading', 'failed'])->whereNull('youtube_id')->update(['status' => 'pending', 'error' => null]);
    }

    public function down(): void
    {
        DB::table('farm_videos')->where('status', 'pending')->update(['status' => 'queued']);
        Schema::table('farm_videos', function (Blueprint $table) {
            $table->dropColumn('approved_at');
        });
    }
};
