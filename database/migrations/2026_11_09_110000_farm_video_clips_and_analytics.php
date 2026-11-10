<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_videos', function (Blueprint $table) {
            $table->string('subject')->nullable()->after('title');
            $table->json('analytics')->nullable()->after('stats_at');
        });

        Schema::create('farm_video_clips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_video_id')->constrained('farm_videos')->cascadeOnDelete();
            $table->foreignId('farm_order_id')->constrained('farm_orders')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('path');
            $table->string('mime', 80);
            $table->unsignedInteger('size');
            $table->timestamps();
            $table->unique(['farm_video_id', 'kind']);
            $table->index(['farm_order_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_video_clips');
        Schema::table('farm_videos', function (Blueprint $table) {
            $table->dropColumn(['subject', 'analytics']);
        });
    }
};
