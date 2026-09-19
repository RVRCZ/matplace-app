<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculations', function (Blueprint $table) {
            $table->id();
            $table->string('token', 16)->unique();          // public share token (/k/{token})
            $table->foreignId('model_file_id')->constrained('model_files')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('anonymous_session_id')->nullable()->constrained('anonymous_sessions')->nullOnDelete();
            $table->json('params');                          // material, quality, infill, supports, scale, quantity
            $table->string('params_hash', 40);
            $table->json('rough')->nullable();               // grams, minutes, price_min, price_max, prices[]
            $table->json('slicer')->nullable();              // SliceResult
            $table->string('slicer_engine', 20)->nullable();
            $table->json('prices')->nullable();              // PriceBreakdown[] from the precise slice
            $table->string('status', 20)->default('rough');  // rough | queued | slicing | done | failed
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['model_file_id', 'params_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculations');
    }
};
