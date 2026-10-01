<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The farm has no place to hand prints over, so pickup in person stops being offered: it leaves the admin's stored
 * choice of delivery kinds (the default in config/farm.php is without it as well). Orders that already chose it
 * keep it. The admin can tick it again in the farm's settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        $stored = DB::table('farm_settings')->where('key', 'delivery_modes')->value('value');
        if ($stored === null) {
            return;
        }
        $modes = array_values(array_diff((array) json_decode((string) $stored, true), ['pickup'])) ?: ['packeta_point', 'packeta_home'];
        DB::table('farm_settings')->where('key', 'delivery_modes')->update(['value' => json_encode($modes)]);
    }

    public function down(): void
    {
        // nothing: whether pickup is offered is the admin's setting
    }
};
