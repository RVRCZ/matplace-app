<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two public catalogues:
 *  - /models: designers' cards the farm prints (designer_models, step B) — gets categories here;
 *  - /model/{slug}: the old site's catalogue of links to models elsewhere, kept for search engines ("inspiration").
 *    catalog_models was a search index until now; it becomes the pages: slug, texts per language, a local picture,
 *    category, tags, licence flag, views.
 * Plus collections (hand-picked sets from both catalogues).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->string('slug', 110)->unique();
            $table->json('name');                                        // {cs: …, en: …, es: …}
            $table->foreignId('parent_id')->nullable()->constrained('catalog_categories')->nullOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('catalog_models', function (Blueprint $table) {
            $table->renameColumn('author', 'author_name');
            $table->renameColumn('active', 'visible');
        });
        Schema::table('catalog_models', function (Blueprint $table) {
            $table->string('slug', 270)->nullable()->unique();           // the same as on the old site: the addresses stay
            $table->string('source_locale', 5)->nullable();              // language of the text taken from the source
            $table->string('thumbnail_path')->nullable();                // public disk; preview_url stays as the remote fallback
            $table->json('images')->nullable();                          // further pictures (public disk paths)
            $table->boolean('license_restricted')->default(true);        // true = the farm must not print it for customers
            $table->foreignId('category_id')->nullable()->constrained('catalog_categories')->nullOnDelete();
            $table->json('tags')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            // AI classification into categories (admin, step F)
            $table->decimal('ai_confidence', 3, 2)->nullable();
            $table->boolean('ai_mismatch')->default(false);
            $table->string('ai_reason', 500)->nullable();
        });
        // `description` held one plain text; from now on it holds texts by language (JSON in the same text column,
        // so the full-text index over title, description and keywords stays as it is)
        DB::table('catalog_models')->whereNotNull('description')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                if (! str_starts_with(ltrim((string) $row->description), '{')) {
                    DB::table('catalog_models')->where('id', $row->id)->update(['description' => json_encode(['cs' => $row->description], JSON_UNESCAPED_UNICODE)]);
                }
            }
        });

        Schema::table('designer_models', function (Blueprint $table) {
            $table->foreignId('catalog_category_id')->nullable()->constrained('catalog_categories')->nullOnDelete();
            $table->unsignedSmallInteger('max_mm')->nullable();          // longest side of one piece: the S / M / L filter of /models
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->string('slug', 270)->unique();
            $table->json('title');
            $table->json('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->boolean('visible')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
        Schema::create('collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('designer_model_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_model_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->index(['collection_id', 'position']);
        });

        // an order made from an inspiration page remembers it: the note names the model, its author and licence (CC BY asks for it)
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->foreignId('catalog_model_id')->nullable()->constrained('catalog_models')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catalog_model_id');
        });
        Schema::dropIfExists('collection_items');
        Schema::dropIfExists('collections');
        Schema::table('designer_models', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catalog_category_id');
            $table->dropColumn('max_mm');
        });
        Schema::table('catalog_models', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'source_locale', 'thumbnail_path', 'images', 'license_restricted', 'tags', 'view_count', 'ai_confidence', 'ai_mismatch', 'ai_reason']);
        });
        Schema::table('catalog_models', function (Blueprint $table) {
            $table->renameColumn('author_name', 'author');
            $table->renameColumn('visible', 'active');
        });
        Schema::dropIfExists('catalog_categories');
    }
};
