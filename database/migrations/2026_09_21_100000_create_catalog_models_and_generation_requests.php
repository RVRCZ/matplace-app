<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * catalog_models: search index of existing models (legacy catalogue import + future additions). No files, only
 * title/keywords/preview/link/licence — used by LocalCatalogSearch to answer "is there a ready-made model for this part?".
 * generation_requests: text/photo → model runs with daily quotas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_models', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->text('keywords')->nullable();              // space separated, cs + en
            $table->string('source', 20)->default('other');   // printables | makerworld | makeronline | cults3d | drive | own | other
            $table->string('external_id', 100)->nullable();
            $table->string('external_url', 500)->nullable();
            $table->string('preview_url', 500)->nullable();
            $table->string('license', 40)->nullable();
            $table->string('author', 150)->nullable();
            $table->string('category', 120)->nullable();
            $table->unsignedSmallInteger('est_grams')->nullable();
            $table->unsignedInteger('est_minutes')->nullable();
            $table->unsignedSmallInteger('max_mm')->nullable();
            $table->boolean('file_available')->default(false); // we hold the file (own/drive) → can be calculated directly
            $table->foreignId('model_file_id')->nullable()->constrained('model_files')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['source', 'external_id']);
        });
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE catalog_models ADD FULLTEXT ft_catalog (title, description, keywords)');
        }

        Schema::create('generation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('token', 16)->unique();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('anonymous_session_id')->nullable()->constrained('anonymous_sessions')->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('type', 10);                        // text | image
            $table->text('prompt')->nullable();
            $table->string('image_path')->nullable();
            $table->json('description')->nullable();           // VisionDescriber output
            $table->string('engine', 30)->nullable();
            $table->string('external_id', 120)->nullable();
            $table->string('status', 12)->default('queued');   // queued | running | done | failed
            $table->unsignedInteger('cost_cents')->default(0);
            $table->foreignId('result_model_file_id')->nullable()->constrained('model_files')->nullOnDelete();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['ip', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_requests');
        Schema::dropIfExists('catalog_models');
    }
};
