<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Send me my bust": a visitor without an account leaves an e-mail at a finished model from a photo and gets the link
 * to it (App\Mail\ModelLink). The address is kept (Roman, 24 Nov 2026: visitors from the ads leave and nothing of them
 * stays); one reminder goes out only when the visitor ticked it (`remind`, consent_at), and the link in every mail
 * unsubscribes. While a lead holds a model the model is not pruned (ModelLibrary::unordered).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_leads', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->index();
            $table->string('locale', 5)->default('cs');
            $table->foreignId('model_file_id')->nullable()->constrained('model_files')->nullOnDelete();
            $table->unsignedBigInteger('anonymous_session_id')->nullable();
            $table->boolean('remind')->default(false);
            $table->timestamp('consent_at')->nullable();
            $table->string('token', 40)->unique();
            $table->string('ip', 45)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_leads');
    }
};
