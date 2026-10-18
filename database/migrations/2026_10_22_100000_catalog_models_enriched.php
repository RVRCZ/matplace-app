<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which inspiration models have a text of our own (written here, not translated from the source) and the price that
 * text was made with: the models worth indexing (Roman, 18 Oct 2026: index only what has something of its own; the
 * rest stays reachable with noindex). `enriched_at` null = the page still speaks the source's words.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_models', function (Blueprint $table) {
            $table->timestamp('enriched_at')->nullable()->after('view_count')->index();
            $table->decimal('enriched_price_czk', 10, 2)->nullable()->after('enriched_at');   // one piece in the cheapest loaded kind at that moment
        });
    }

    public function down(): void
    {
        Schema::table('catalog_models', function (Blueprint $table) {
            $table->dropIndex(['enriched_at']);
            $table->dropColumn(['enriched_at', 'enriched_price_czk']);
        });
    }
};
