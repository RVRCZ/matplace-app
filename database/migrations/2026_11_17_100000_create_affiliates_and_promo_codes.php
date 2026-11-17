<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The partner programme and discount codes (docs/AT.md). Both are switched off in the farm's settings until the owner
 * says otherwise; the tables and the order's columns are only there.
 *
 *  partners            who shares the link: code (A-Z 0-9 _ -, up to 20), commission percent and cookie days of their own
 *  partner_clicks      one row per visitor and day (the address is kept as a hash of address + day, never as an address)
 *  partner_commissions one per order: base net of VAT, the percent frozen at payment, state pending|payable|paid|void
 *  promo_codes         percent or fixed amount in crowns, minimum order, window, maximum uses, once per account, partner
 *  farm_orders         partner_id (who gets the commission), promo_code_id (the code applied), discount (frozen at payment)
 *
 * Widths are checked against what is written into them in tests/Feature/AffiliateColumnsTest (SQLite does not enforce them).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('code', 20)->unique();
            $table->string('email', 255);
            $table->decimal('commission_percent', 5, 2)->default(10);
            $table->unsignedSmallInteger('cookie_days')->default(30);
            $table->boolean('active')->default(true);
            $table->string('channels', 300)->nullable();           // where they will share the link (told at registration)
            $table->text('payout_details')->nullable();             // free text: account number, PayPal...
            $table->text('notes')->nullable();                      // the operator's own
            $table->string('terms_version', 20)->nullable();        // the programme's terms accepted at registration
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->string('visitor', 40);                          // sha1(address | day | app key): the same person counts once a day
            $table->string('referer', 300)->nullable();
            $table->string('landing', 300)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['partner_id', 'day', 'visitor']);
            $table->index(['partner_id', 'day']);
        });

        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('kind', 10);                              // percent | fixed
            $table->decimal('value', 10, 2);                         // percent (0-100) or crowns
            $table->decimal('min_order_czk', 10, 2)->default(0);     // minimum price of the print before the discount, in crowns
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->unsignedInteger('max_uses')->nullable();         // null = unlimited
            $table->unsignedInteger('uses')->default(0);             // orders paid with it and not refunded in full
            $table->boolean('once_per_account')->default(true);
            $table->boolean('active')->default(true);
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('partner_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_order_id')->unique()->constrained()->cascadeOnDelete();   // one per order
            $table->decimal('base_amount', 10, 2);                    // the order's print and delivery after the discount, net of VAT, as paid
            $table->decimal('percent', 5, 2);                         // the partner's percent at the moment of payment, frozen
            $table->decimal('amount', 10, 2);                         // base x percent, less what was refunded
            $table->string('currency', 3);
            $table->string('state', 10)->default('pending');          // pending | payable | paid | void
            $table->timestamp('paid_at')->nullable();
            $table->string('note', 300)->nullable();
            $table->timestamps();
            $table->index(['partner_id', 'state']);
        });

        Schema::table('farm_orders', function (Blueprint $table) {
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->foreignId('promo_code_id')->nullable()->constrained('promo_codes')->nullOnDelete();
            $table->decimal('discount', 10, 2)->nullable();           // frozen at payment, in the order's currency
            $table->timestamp('promo_counted_at')->nullable();        // the use was counted when the order was paid
            $table->timestamp('promo_released_at')->nullable();       // ... and given back (failed, refunded in full)
        });
    }

    public function down(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partner_id');
            $table->dropConstrainedForeignId('promo_code_id');
            $table->dropColumn(['discount', 'promo_counted_at', 'promo_released_at']);
        });
        Schema::dropIfExists('partner_commissions');
        Schema::dropIfExists('promo_codes');
        Schema::dropIfExists('partner_clicks');
        Schema::dropIfExists('partners');
    }
};
