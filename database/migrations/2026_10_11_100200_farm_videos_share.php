<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Where else an approved video goes besides YouTube (facebook, instagram), chosen by the admin at the approval. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_videos', function (Blueprint $table) {
            $table->json('share')->nullable()->after('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('farm_videos', function (Blueprint $table) {
            $table->dropColumn('share');
        });
    }
};
