<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The farm's support threshold is 35° (docs/AL.md §17): every printer that carries its own value gets it, a printer without
 * the key takes it from config farm.support_threshold_angle. What each printer had goes to the log, so down() has nothing to do.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('farm_printers')->orderBy('id')->get(['id', 'process_overrides']) as $row) {
            $overrides = json_decode((string) $row->process_overrides, true);
            if (! is_array($overrides) || ! array_key_exists('support_threshold_angle', $overrides)) {
                continue;
            }
            Log::info('farm_support_threshold_35', ['printer' => $row->id, 'was' => $overrides['support_threshold_angle']]);
            $overrides['support_threshold_angle'] = '35';
            DB::table('farm_printers')->where('id', $row->id)->update(['process_overrides' => json_encode($overrides)]);
        }
    }

    public function down(): void {}
};
