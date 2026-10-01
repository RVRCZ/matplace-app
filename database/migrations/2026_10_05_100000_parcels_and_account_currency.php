<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step D: parcels by Packeta and the currency of an account.
 *
 * farm_orders: delivery is now pickup | packeta_point | packeta_home (the old flat "shipping" becomes delivery
 * home), the price of the parcel and what Packeta gave us for it. users.currency is fixed for every account whose
 * ledger already holds a line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->string('delivery', 20)->default('pickup')->change();
            $table->decimal('shipping_price', 10, 2)->nullable()->after('shipping_address');   // in the order's currency, with VAT
            $table->string('packeta_packet_id', 32)->nullable()->after('tracking');
            $table->string('packeta_barcode', 40)->nullable()->after('packeta_packet_id');
            $table->string('tracking_url')->nullable()->after('packeta_barcode');
            $table->timestamp('shipped_at')->nullable()->after('handed_at');
        });

        DB::table('farm_orders')->where('delivery', 'shipping')->update(['delivery' => 'packeta_home']);

        // the admin's stored choice of delivery kinds, in the new names; the flat parcel price is gone (config/farm.php `shipping`)
        $modes = DB::table('farm_settings')->where('key', 'delivery_modes')->value('value');
        if ($modes !== null) {
            $list = (array) json_decode((string) $modes, true);
            $new = array_values(array_unique(array_merge(...array_map(fn ($m) => $m === 'shipping' ? ['packeta_point', 'packeta_home'] : [$m], $list ?: ['pickup']))));
            DB::table('farm_settings')->where('key', 'delivery_modes')->update(['value' => json_encode($new)]);
        }
        DB::table('farm_settings')->where('key', 'shipping_price')->delete();

        // money has already moved on these accounts: their currency is the one of their ledger
        foreach (DB::table('credit_transactions')->select('user_id', DB::raw('MIN(currency) as currency'))->groupBy('user_id')->get() as $row) {
            DB::table('users')->where('id', $row->user_id)->whereNull('currency')->update(['currency' => $row->currency ?: 'CZK']);
        }
    }

    public function down(): void
    {
        DB::table('farm_orders')->whereIn('delivery', ['packeta_point', 'packeta_home'])->update(['delivery' => 'shipping']);
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_price', 'packeta_packet_id', 'packeta_barcode', 'tracking_url', 'shipped_at']);
        });
    }
};
