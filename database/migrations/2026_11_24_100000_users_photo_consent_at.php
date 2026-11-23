<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a signed-in customer ticked "the photo is mine or I may use it, and I agree that we process it" for the
 * figure and pet tools. While it is set the page shows "consent given on …" instead of the box and the server
 * accepts a photo without it; "change" clears it again. A visitor without an account ticks every time, the box is never ticked for him.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('photo_consent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('photo_consent_at');
        });
    }
};
