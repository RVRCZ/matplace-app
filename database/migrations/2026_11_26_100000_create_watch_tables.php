<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The watch (docs/AU.md): which people's mails the owner was told about, and the state of every server check. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailbox_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 64)->unique();      // Gmail's id of the message
            $table->string('thread_id', 64)->nullable();
            $table->string('from', 190);
            $table->string('from_name', 120)->nullable();
            $table->string('subject', 250)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('alerted_at')->nullable();      // when the owner was mailed about it
            $table->timestamp('reminded_at')->nullable();     // the once-a-day "still unread"
            $table->timestamps();
        });

        Schema::create('health_checks', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->boolean('ok')->default(true);
            $table->string('message', 500)->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('changed_at')->nullable();      // when ok last flipped
            $table->timestamp('alerted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_checks');
        Schema::dropIfExists('mailbox_alerts');
    }
};
