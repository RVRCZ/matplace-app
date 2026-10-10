<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A card payment made for one order from its page: the payment remembers the order and what the customer chose
 * (spool, delivery, address, note), so the webhook can top up the credit and pay the order in one go.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('farm_order_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->json('context')->nullable()->after('raw');   // the choices of the order page; `result` once the webhook tried to pay
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('farm_order_id');
            $table->dropColumn('context');
        });
    }
};
