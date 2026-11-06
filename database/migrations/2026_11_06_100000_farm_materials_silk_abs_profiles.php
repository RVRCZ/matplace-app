<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PLA Silk and ABS kinds get their own base profile names (filament_pla_silk.json, filament_abs.json: copies of the
 * shared PLA and ASA profiles), so OrcaSlicer::machineVariant can find a machine's own flavour of them: the Prusa
 * CORE One INDX presets filament_pla_silk_coreone_indx8t.json and filament_abs_coreone_indx8t.json (TODO #70, Roman
 * 6 Nov 2026: "profily pro INDX ano"). Only rows still on the original profile move; a hand-set profile stays.
 */
return new class extends Migration
{
    private const MOVES = [
        // [where, from, to]
        [['code' => 'PLA', 'finish' => 'silk'], 'filament_pla.json', 'filament_pla_silk.json'],
        [['code' => 'ABS'], 'filament_asa.json', 'filament_abs.json'],
        [['code' => 'ABS+'], 'filament_asa.json', 'filament_abs.json'],
    ];

    public function up(): void
    {
        foreach (self::MOVES as [$where, $from, $to]) {
            $n = DB::table('farm_materials')->where($where)->where('filament_profile', $from)->update(['filament_profile' => $to]);
            Log::warning("farm_materials_silk_abs_profiles: {$n} rows ".json_encode($where)." {$from} -> {$to}");
        }
    }

    public function down(): void
    {
        foreach (self::MOVES as [$where, $from, $to]) {
            DB::table('farm_materials')->where($where)->where('filament_profile', $to)->update(['filament_profile' => $from]);
        }
    }
};
