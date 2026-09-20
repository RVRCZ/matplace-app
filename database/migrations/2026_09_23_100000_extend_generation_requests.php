<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generation_requests', function (Blueprint $table) {
            $table->string('image_sha256', 64)->nullable()->after('image_path')->index(); // same photo → reuse the result
            $table->unsignedSmallInteger('target_mm')->nullable()->after('image_sha256');   // largest dimension of the real object
            $table->unsignedTinyInteger('progress')->default(0)->after('status');
            $table->foreignId('source_request_id')->nullable()->after('progress');         // the "describe" request the photo came from
        });
    }

    public function down(): void
    {
        Schema::table('generation_requests', function (Blueprint $table) {
            $table->dropColumn(['image_sha256', 'target_mm', 'progress', 'source_request_id']);
        });
    }
};
