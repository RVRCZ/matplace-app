<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Files that leave with an approved e-mail (docs/AU.md §8): pictures of the order or the admin's own, stored with the draft. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outgoing_emails', function (Blueprint $table) {
            $table->json('attachments')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('outgoing_emails', function (Blueprint $table) {
            $table->dropColumn('attachments');
        });
    }
};
