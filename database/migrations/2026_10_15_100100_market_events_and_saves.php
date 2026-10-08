<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Markets, fairs and conventions where printed things are sold (/tools/vendors; table market_events, `events` is taken): what, where (with coordinates),
 * when, the stall fee, a link, and whether an admin has checked it. Visitors suggest events (status `suggested`),
 * the first batch comes with status `verify`; `verified` is what the admin confirmed. And the events an account saved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_events', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('type', 20);                        // craft | christmas | farmers | maker | comic | fair | swap | design
            $table->string('city', 80);
            $table->string('address', 160)->nullable();
            $table->string('country', 2)->default('CZ');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('url', 300)->nullable();
            $table->string('stall_fee', 120)->nullable();      // as the organiser states it: "1 500 Kč / den", "od 2 000 Kč"
            $table->text('note')->nullable();
            $table->string('status', 12)->default('verify');  // verified | verify | suggested
            $table->string('source', 300)->nullable();
            $table->string('suggested_by', 160)->nullable();  // an e-mail of the visitor who suggested it, if given
            $table->timestamps();
            $table->index(['status', 'starts_on']);
            $table->index(['country', 'lat', 'lng']);
        });
        Schema::create('event_saves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('market_events')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_saves');
        Schema::dropIfExists('market_events');
    }
};
