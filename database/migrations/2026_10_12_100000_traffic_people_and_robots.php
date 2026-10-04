<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statistics that count people (docs/O.md): a browser of the staff is remembered and left out, addresses nobody
 * answers to are collected for a decision about redirects, and the events can be read by day.
 */
return new class extends Migration
{
    public function up(): void
    {
        // an admin signed in from this browser once: nothing it does is a visitor's doing, signed in or not
        Schema::table('anonymous_sessions', function (Blueprint $table) {
            $table->boolean('staff')->default(false);
        });

        // the overview reads whole days of every type
        Schema::table('events', function (Blueprint $table) {
            $table->index('created_at');
        });

        // addresses that ended in "not found": candidates for config/legacy.php
        Schema::create('missing_pages', function (Blueprint $table) {
            $table->id();
            $table->string('path', 190)->unique();
            $table->unsignedInteger('hits')->default(0);          // seen by the application, people and robots
            $table->unsignedInteger('bot_hits')->default(0);      // of those, robots
            $table->unsignedInteger('log_hits')->default(0);      // from the web server's log, before the application counted
            $table->unsignedInteger('log_bot_hits')->default(0);
            $table->string('referer', 190)->nullable();           // the last other site that linked here (host and path)
            $table->timestamp('first_at')->nullable();
            $table->timestamp('live_at')->nullable();             // since when the application counts this address itself
            $table->timestamp('last_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missing_pages');
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
        Schema::table('anonymous_sessions', function (Blueprint $table) {
            $table->dropColumn('staff');
        });
    }
};
