<?php

namespace Database\Seeders;

use App\Models\FarmColor;
use App\Models\FarmMaterial;
use App\Models\FarmPrinter;
use Illuminate\Database\Seeder;

/**
 * Farm, phase 1: one Anycubic Kobra S1 Combo (250³, 0.4 mm, four ACE slots) and PLA.
 * Safe to run again: existing rows are left as the admin changed them.
 *
 *   php artisan db:seed --class=FarmSeeder
 */
class FarmSeeder extends Seeder
{
    public function run(): void
    {
        $pla = FarmMaterial::firstOrCreate(['code' => 'PLA'], [
            'name' => 'PLA', 'filament_profile' => 'filament_pla.json', 'density' => 1.24, 'price_per_gram' => 1.20,
        ]);

        $colors = [];
        foreach ([['Černá', '#1b1b1d'], ['Bílá', '#f4f4f2'], ['Šedá', '#75787b'], ['Oranžová', '#f47a20']] as [$name, $hex]) {
            $colors[] = FarmColor::firstOrCreate(['farm_material_id' => $pla->id, 'name' => $name], ['hex' => $hex]);
        }

        $printer = FarmPrinter::firstOrCreate(['key' => 'kobra-s1-01'], [
            'name' => 'Kobra S1 #1',
            'model' => 'Anycubic Kobra S1 Combo',
            'mode' => FarmPrinter::MODE_MANUAL,
            'bed_x' => 250, 'bed_y' => 250, 'bed_z' => 250, 'nozzle_mm' => 0.4,
            // first calibration print (Benchy, 22 Sep 2026): the machine needed 40:16 for a 37:40 estimate, filament 3722 mm
            // for 3682 mm and 11 g on the scale for 10.98 g: time is 7 % slow, weight is right
            'time_factor' => 1.07, 'weight_factor' => 1.0,
            'machine_profile' => 'machine.json',
            'process_profiles' => ['draft' => 'process_draft.json', 'standard' => 'process_standard.json', 'fine' => 'process_fine.json'],
            // the shared calculator profile is deliberately oversized for pricing big parts; a real print needs the real plate
            'machine_overrides' => ['printable_area' => ['0x0', '250x0', '250x250', '0x250'], 'printable_height' => '250'],
            // one colour per print: no prime tower; supports only where the slicer finds overhangs, as trees
            'process_overrides' => ['enable_prime_tower' => '0', 'enable_support' => '1', 'support_type' => 'tree(auto)', 'support_threshold_angle' => '30'],
        ]);

        if ($printer->wasRecentlyCreated) {
            foreach ($colors as $i => $color) {
                $printer->slots()->create(['slot' => $i, 'farm_color_id' => $color->id, 'remaining_g' => 1000, 'enabled' => $i === 0]);
            }
        }
    }
}
