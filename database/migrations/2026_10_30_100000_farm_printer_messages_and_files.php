<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the printers say and what sits on their disks (agent 0.2.9, docs/AM.md §10). F26-000108 paused on B3 with the
 * reason only on the printer's screen: the agent now sends the printer's words in every report, the job keeps the last
 * message with its time and a short history (the last 20 as JSON on the job: bounded, read only with the job, no
 * joins), the printer keeps its last message even outside a print. The printer's file list (names, sizes, dates, free
 * space, the last cleanup's summary) is one JSON per printer as well: it is a snapshot the agent refreshes whole.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_print_jobs', function (Blueprint $table) {
            $table->timestamp('message_at')->nullable()->after('message');
            $table->json('messages')->nullable()->after('message_at');
        });
        Schema::table('farm_printers', function (Blueprint $table) {
            $table->string('message', 300)->nullable()->after('telemetry');
            $table->timestamp('message_at')->nullable()->after('message');
            $table->json('files')->nullable()->after('message_at');
            $table->timestamp('files_at')->nullable()->after('files');
        });
    }

    public function down(): void
    {
        Schema::table('farm_print_jobs', function (Blueprint $table) {
            $table->dropColumn(['message_at', 'messages']);
        });
        Schema::table('farm_printers', function (Blueprint $table) {
            $table->dropColumn(['message', 'message_at', 'files', 'files_at']);
        });
    }
};
