<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('anonymous_session_id')->nullable()->constrained('anonymous_sessions')->nullOnDelete();
            $table->string('original_name');
            $table->string('ext', 10);
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64)->index();
            $table->string('storage_path');              // original upload, relative to the "models" disk
            $table->string('stl_path')->nullable();      // normalised binary STL used by slicer + viewer
            $table->string('preview_path')->nullable();
            $table->json('bbox')->nullable();
            $table->decimal('volume_mm3', 16, 3)->nullable();
            $table->decimal('area_mm2', 16, 3)->nullable();
            $table->unsignedInteger('triangles')->nullable();
            $table->json('mesh_report')->nullable();
            $table->string('origin', 20)->default('upload'); // upload | generated | catalog | portfolio
            $table->string('origin_ref')->nullable();
            $table->string('status', 20)->default('uploaded'); // uploaded | processing | ready | failed
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_files');
    }
};
