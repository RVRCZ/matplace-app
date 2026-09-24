<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Extra photos of the same subject (left, back, right) for multi-view generation; the front stays in image_path. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generation_requests', function (Blueprint $table) {
            $table->json('views')->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('generation_requests', function (Blueprint $table) {
            $table->dropColumn('views');
        });
    }
};
