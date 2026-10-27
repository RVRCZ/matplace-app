<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Send me the result" (docs/AN.md §12.5): a model from a photo takes 2 to 4 minutes, so a visitor may leave an e-mail
 * and get one message with the link when it is done, with no account. The address is wiped once the mail went out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generation_requests', function (Blueprint $table) {
            $table->string('notify_email', 190)->nullable()->after('error');
            $table->string('notify_locale', 5)->nullable()->after('notify_email');
            $table->timestamp('notified_at')->nullable()->after('notify_locale');
        });
    }

    public function down(): void
    {
        Schema::table('generation_requests', function (Blueprint $table) {
            $table->dropColumn(['notify_email', 'notify_locale', 'notified_at']);
        });
    }
};
