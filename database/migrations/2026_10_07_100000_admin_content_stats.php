<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step F: what the admin works with. Search log, e-mails waiting for approval, banners, posts for Facebook and
 * Instagram, the daily summary old events are folded into, and where the AI's suggestion of a category is kept
 * until a person confirms it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // what people look for (no account, no address: only the words, the language and how much was found)
        Schema::create('search_queries', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->index();
            $table->string('locale', 5);
            $table->string('query', 200)->index();
            $table->unsignedSmallInteger('results_local')->default(0);
            $table->unsignedSmallInteger('results_external')->default(0);
            // "one person searched three times": a one-way mark that changes every day and leads to no session or account
            $table->char('visitor', 16)->nullable();
        });

        Schema::create('outgoing_emails', function (Blueprint $table) {
            $table->id();
            $table->string('to', 190);
            $table->string('subject', 250);
            $table->text('body');
            $table->string('locale', 5)->default('cs');
            $table->string('status', 10)->default('draft')->index();   // draft | approved | sent | rejected
            $table->boolean('generated_by_ai')->default(false);
            $table->text('instruction')->nullable();                    // what the admin asked the AI to write
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('image_path');
            $table->string('url', 500)->nullable();
            $table->string('locale', 5)->nullable();   // null = every language
            $table->boolean('active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        // what was published on the Facebook page and on Instagram, and what came of it
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 12);                  // facebook | instagram
            $table->string('subject_type', 30);              // designer_model | collection
            $table->unsignedBigInteger('subject_id');
            $table->text('text');
            $table->string('link', 500)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('status', 10)->default('draft');  // draft | posted | failed
            $table->string('external_id', 80)->nullable();
            $table->string('error', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });

        // events older than 13 months, folded to one row per day and kind (matplace:events-rollup)
        Schema::create('events_daily', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('type', 30);
            $table->string('source', 20)->nullable();
            $table->string('locale', 5)->nullable();
            $table->string('subject_type', 30)->nullable();
            $table->unsignedInteger('events')->default(0);
            $table->unsignedInteger('sessions')->default(0);
            $table->unique(['day', 'type', 'source', 'locale', 'subject_type'], 'events_daily_unique');
        });

        // the AI's suggestion of a category waits here when it is not sure enough to write it itself
        Schema::table('catalog_models', function (Blueprint $table) {
            $table->foreignId('ai_category_id')->nullable()->after('ai_reason')->constrained('catalog_categories')->nullOnDelete();
            $table->timestamp('ai_checked_at')->nullable()->after('ai_category_id');
        });
        Schema::table('designer_models', function (Blueprint $table) {
            $table->decimal('ai_confidence', 3, 2)->nullable();
            $table->boolean('ai_mismatch')->default(false);
            $table->string('ai_reason', 300)->nullable();
            $table->foreignId('ai_category_id')->nullable()->constrained('catalog_categories')->nullOnDelete();
            $table->timestamp('ai_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('designer_models', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_category_id');
            $table->dropColumn(['ai_confidence', 'ai_mismatch', 'ai_reason', 'ai_checked_at']);
        });
        Schema::table('catalog_models', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_category_id');
            $table->dropColumn('ai_checked_at');
        });
        foreach (['events_daily', 'social_posts', 'banners', 'outgoing_emails', 'search_queries'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
