<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 4: "Make it for me" → inquiry → dispatched to matching printers → offers (quotes with inquiry_id) → chat threads.
 * No payments, no escrow: the platform connects and steps back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('token', 16)->unique();                 // customer access: /i/{token}
            $table->foreignId('calculation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('model_file_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_email');
            $table->string('contact_phone', 30)->nullable();
            $table->string('country', 2)->default('CZ');
            $table->string('zip', 10)->nullable();
            $table->string('city', 100)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('material_code', 10);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->json('params')->nullable();                      // calculation params snapshot
            $table->json('summary')->nullable();                     // grams, minutes, dims snapshot for printers
            $table->text('note')->nullable();
            $table->date('wanted_by')->nullable();
            $table->string('delivery_pref', 10)->default('any');    // any | pickup | shipping
            $table->string('status', 12)->default('pending');       // pending (email unverified) | open | offered | accepted | done | cancelled | expired
            $table->string('verification_code', 40)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('accepted_quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('locale', 5)->default('cs');
            $table->timestamps();
        });

        Schema::create('inquiry_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('printer_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('rank')->default(0);
            $table->decimal('distance_km', 8, 1)->nullable();
            $table->decimal('auto_price', 10, 2)->nullable();       // from the printer's own price list
            $table->json('auto_breakdown')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('seen_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->string('decline_reason', 200)->nullable();
            $table->timestamps();
            $table->unique(['inquiry_id', 'printer_profile_id']);
        });

        Schema::create('threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('printer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->json('header')->nullable();                      // model + parameters snapshot shown on top of the chat
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('customer_read_at')->nullable();
            $table->timestamp('printer_read_at')->nullable();
            $table->timestamps();
            $table->unique(['inquiry_id', 'printer_profile_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained()->cascadeOnDelete();
            $table->string('sender', 10);                            // customer | printer | system
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name', 255)->nullable();
            $table->timestamps();
            $table->index(['thread_id', 'id']);
        });

        Schema::create('geocode_cache', function (Blueprint $table) {
            $table->id();
            $table->string('query', 160)->unique();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('display', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geocode_cache');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('threads');
        Schema::dropIfExists('inquiry_dispatches');
        Schema::dropIfExists('inquiries');
    }
};
