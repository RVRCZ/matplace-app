<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Printer quotes: PDF + online link. Works with or without a platform inquiry (printer's own clients). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('token', 16)->unique();                   // /n/{token}
            $table->string('number', 20)->nullable();                // N-2026-0001 per printer
            $table->foreignId('printer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calculation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('model_file_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('inquiry_id')->nullable();
            $table->string('client_name', 160)->nullable();
            $table->string('client_email')->nullable();
            $table->string('title', 200)->nullable();
            $table->json('params')->nullable();                       // material, quality, infill, quantity, scale
            $table->json('lines');                                    // [{key, label, qty, unit_price, total}]
            $table->decimal('total', 10, 2)->default(0);
            $table->string('currency', 3)->default('CZK');
            $table->date('valid_until')->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->text('note')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('status', 12)->default('draft');           // draft | sent | viewed | accepted | declined | expired
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
