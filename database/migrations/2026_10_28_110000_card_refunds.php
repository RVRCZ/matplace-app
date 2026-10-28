<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money back to the card (docs/AO.md §2): every refund at the gateway of a card payment, and how many times an order
 * was printed again on its own after a failure (one automatic reprint, then the money goes back).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('CZK');
            $table->string('reason', 40);                                // print_failed | cancelled | cancelled_running | not_paid | admin
            $table->string('note', 300)->nullable();                     // the admin's words
            $table->string('gateway_ref', 120)->nullable();              // the gateway's refund id
            $table->string('status', 10)->default('pending');            // pending | done | failed
            $table->string('error', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['farm_order_id', 'status']);
        });
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('reprints')->default(0)->after('plates_done');
        });
    }

    public function down(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->dropColumn('reprints');
        });
        Schema::dropIfExists('payment_refunds');
    }
};
