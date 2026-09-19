<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calculations', function (Blueprint $table) {
            // which price lists were used: {printer_profile_ids: [...], own_printer_profile_id: int|null, lat, lng}
            $table->json('pricing_context')->nullable()->after('prices');
        });
    }

    public function down(): void
    {
        Schema::table('calculations', function (Blueprint $table) {
            $table->dropColumn('pricing_context');
        });
    }
};
