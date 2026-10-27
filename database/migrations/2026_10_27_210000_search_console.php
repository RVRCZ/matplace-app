<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Search Console through the API (docs/AN.md §14): the one connected Google account with its property, and the rows
 * `searchconsole:pull` keeps: a day, a page, a query (null = the page's own total, which Search Console gives without
 * the anonymised queries) and a country, with clicks, impressions, CTR and position. The unique key is a hash of the
 * four, since MariaDB cannot index a 1 000-character page beside a 500-character query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_console_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('site_url', 255)->nullable();              // sc-domain:matplace.com or https://matplace.com/
            $table->json('sites')->nullable();                        // the properties the account may read (sites.list)
            $table->text('refresh_token');                            // encrypted
            $table->text('access_token')->nullable();                 // encrypted
            $table->timestamp('access_expires_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_pulled_at')->nullable();
            $table->date('last_pulled_from')->nullable();
            $table->date('last_pulled_to')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('search_console_rows', function (Blueprint $table) {
            $table->id();
            $table->char('row_key', 40)->unique();                    // sha1(date|page|query|country)
            $table->date('date');
            $table->string('page', 1000);
            $table->string('query', 500)->nullable();
            $table->char('country', 3)->nullable();                   // ISO 3166-1 alpha-3, lower case, as the API gives it
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 6, 4)->default(0);
            $table->decimal('position', 7, 2)->nullable();
            $table->timestamps();
            $table->index(['date', 'country']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_console_rows');
        Schema::dropIfExists('search_console_accounts');
    }
};
