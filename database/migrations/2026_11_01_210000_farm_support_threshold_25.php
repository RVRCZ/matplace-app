<?php

use App\Models\FarmPrinterMaterial;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The farm's support threshold is 25° (docs/AL.md §17): every printer that carries its own value gets it, a printer without
 * the key takes it from config farm.support_threshold_angle. A kind's tuned row wins over the printer, so a row above 25
 * comes down as its next version; a row at 25 or less and a spool's own settings stay. What each had goes to the log, so
 * down() has nothing to do.
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
            Log::info('farm_support_threshold_25', ['printer' => $row->id, 'was' => $overrides['support_threshold_angle']]);
            $overrides['support_threshold_angle'] = '25';
            DB::table('farm_printers')->where('id', $row->id)->update(['process_overrides' => json_encode($overrides)]);
        }

        foreach (FarmPrinterMaterial::whereNull('farm_color_id')->orderBy('id')->get() as $row) {
            $overrides = (array) $row->overrides;
            $was = $overrides['process']['support_threshold_angle'] ?? null;
            if ($was === null || (int) $was <= 25) {
                continue;
            }
            Log::info('farm_support_threshold_25', ['row' => $row->id, 'was' => $was]);
            $overrides['process']['support_threshold_angle'] = '25';
            $row->revise($overrides, 'manual', 'TODO #63: '.$was.' → 25 (docs/AL.md §17)');
        }
    }

    public function down(): void {}
};
