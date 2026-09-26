<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Print videos on YouTube: the connected channel, the customer's consent, one video row per order. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('youtube_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('channel_id', 64);
            $table->string('channel_title')->nullable();
            $table->text('refresh_token');                          // encrypted
            $table->text('access_token')->nullable();               // encrypted
            $table->timestamp('access_expires_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('farm_orders', function (Blueprint $table) {
            $table->boolean('video_consent')->default(false)->after('timelapse_path');
            $table->timestamp('video_consent_at')->nullable()->after('video_consent');
        });

        Schema::create('farm_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 12)->index();                  // App\Models\FarmVideo::STATUS_*
            $table->string('youtube_id', 32)->nullable();
            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_videos');
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->dropColumn(['video_consent', 'video_consent_at']);
        });
        Schema::dropIfExists('youtube_accounts');
    }
};
