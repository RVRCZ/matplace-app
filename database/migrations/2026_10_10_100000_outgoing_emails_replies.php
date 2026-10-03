<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A draft may be the reply to a message of the shared mailbox: it is sent in that thread, not as a new mail. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outgoing_emails', function (Blueprint $table) {
            $table->string('inbox_message_id', 64)->nullable()->index()->after('instruction');   // the message answered (Gmail id)
            $table->string('inbox_thread_id', 64)->nullable()->after('inbox_message_id');
            $table->string('in_reply_to', 250)->nullable()->after('inbox_thread_id');            // its Message-ID header
        });
    }

    public function down(): void
    {
        Schema::table('outgoing_emails', function (Blueprint $table) {
            $table->dropColumn(['inbox_message_id', 'inbox_thread_id', 'in_reply_to']);
        });
    }
};
