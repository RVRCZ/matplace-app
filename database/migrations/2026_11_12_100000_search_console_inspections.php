<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The state of the index, asked of Google by `searchconsole:inspect` (docs/AP.md §14): one row per address and day
 * (what the URL Inspection API said of it) and one row per sitemap and day (what the account lists). An address is
 * keyed by a hash, since MariaDB cannot make a 1 000-character address unique beside a date. Widths: `state` is one of
 * our six short words (App\Models\SearchConsoleInspection::STATES, the longest has 10 letters); Google's own words
 * are cut to their columns when they are written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_console_inspections', function (Blueprint $table) {
            $table->id();
            $table->char('url_key', 40);                              // sha1(url)
            $table->date('checked_on');
            $table->string('url', 1000);
            $table->string('state', 20);                              // indexed | discovered | crawled | unknown | excluded | error
            $table->string('verdict', 40)->nullable();                // PASS, NEUTRAL, FAIL …
            $table->string('coverage', 190)->nullable();              // "Discovered - currently not indexed" …
            $table->string('robots_state', 40)->nullable();
            $table->string('indexing_state', 40)->nullable();
            $table->string('fetch_state', 40)->nullable();
            $table->string('google_canonical', 1000)->nullable();
            $table->string('user_canonical', 1000)->nullable();
            $table->timestamp('last_crawl_at')->nullable();
            $table->boolean('important')->default(false);             // from config/seo.php `inspect`, not only from the sitemap
            $table->string('error', 190)->nullable();
            $table->timestamps();
            $table->unique(['url_key', 'checked_on']);
            $table->index('checked_on');
        });

        Schema::create('search_console_sitemaps', function (Blueprint $table) {
            $table->id();
            $table->date('checked_on');
            $table->string('path', 255);
            $table->boolean('is_index')->default(false);
            $table->boolean('is_pending')->default(false);
            $table->timestamp('last_submitted_at')->nullable();
            $table->timestamp('last_downloaded_at')->nullable();
            $table->unsignedInteger('warnings')->default(0);
            $table->unsignedInteger('errors')->default(0);
            $table->unsignedInteger('submitted')->default(0);
            $table->unsignedInteger('indexed')->default(0);
            $table->timestamps();
            $table->unique(['checked_on', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_console_sitemaps');
        Schema::dropIfExists('search_console_inspections');
    }
};
