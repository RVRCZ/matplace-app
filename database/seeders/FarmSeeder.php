<?php

namespace Database\Seeders;

use App\Domain\Farm\ProfileLibrary;
use App\Models\FarmColor;
use App\Models\FarmMaterial;
use App\Models\FarmPrinter;
use Illuminate\Database\Seeder;

/**
 * Farm, phase 1: the filament catalogue (database/data/farm_filaments.json, built from the photo folders on the
 * matplace Drive), one Anycubic Kobra S1 Combo (250³, 0.4 mm, four ACE slots). Safe to run again: rows the admin
 * changed are kept, new catalogue entries are added.
 *
 *   php artisan db:seed --class=FarmSeeder
 */
class FarmSeeder extends Seeder
{
    /**
     * Material kinds. Temperatures are what the e-shop descriptions recommend (PLA+ 205–220 / 55–65) and what the first
     * farm prints used; only kinds with a verified slicer profile are enabled for customers.
     */
    private const KINDS = [
        // code, finish, name, profile, density, nozzle, first layer, bed, price/g, enabled, sort
        ['PLA', 'solid', 'PLA', 'filament_pla.json', 1.24, 210, 215, 55, 1.20, true, 10],
        ['PLA+', 'solid', 'PLA+', 'filament_pla.json', 1.24, 215, 225, 60, 1.30, true, 20],
        ['PLA+', 'matte', 'PLA+', 'filament_pla.json', 1.24, 215, 225, 60, 1.40, true, 21],
        ['PLA', 'silk', 'PLA', 'filament_pla.json', 1.24, 215, 220, 55, 1.50, true, 30],
        ['PLA', 'matte', 'PLA', 'filament_pla.json', 1.24, 210, 215, 55, 1.40, true, 31],
        ['PLA', 'luminous', 'PLA', 'filament_pla.json', 1.24, 215, 220, 55, 1.60, true, 32],
        ['PLA', 'glitter', 'PLA', 'filament_pla.json', 1.24, 215, 220, 55, 1.60, true, 33],
        ['PLA', 'special', 'PLA', 'filament_pla.json', 1.24, 210, 215, 55, 1.70, true, 34],
        ['PETG', 'solid', 'PETG', 'filament_petg.json', 1.27, 240, 245, 75, 1.40, true, 40],
        ['PETG-CF', 'cf', 'PETG CF', 'filament_petg.json', 1.30, 250, 255, 80, 2.50, false, 41],
        ['ABS', 'solid', 'ABS', 'filament_asa.json', 1.04, 250, 255, 100, 1.40, false, 50],
        ['ABS+', 'solid', 'ABS+', 'filament_asa.json', 1.04, 250, 255, 100, 1.50, false, 51],
        ['ASA', 'solid', 'ASA', 'filament_asa.json', 1.07, 250, 255, 100, 1.60, false, 52],
        ['TPU', 'flex', 'TPU', 'filament_tpu.json', 1.21, 225, 225, 50, 2.20, false, 60],
        ['PC', 'solid', 'PC', 'filament_asa.json', 1.20, 270, 275, 100, 2.80, false, 70],
    ];

    public function run(): void
    {
        $kinds = [];
        foreach (self::KINDS as [$code, $finish, $name, $profile, $density, $nozzle, $first, $bed, $price, $enabled, $sort]) {
            $kinds[$code.'|'.$finish] = FarmMaterial::firstOrCreate(['code' => $code, 'finish' => $finish], [
                'name' => $name, 'filament_profile' => $profile, 'density' => $density, 'nozzle_temp' => $nozzle, 'nozzle_temp_first' => $first,
                'bed_temp' => $bed, 'price_per_gram' => $price, 'enabled' => $enabled, 'sort' => $sort,
            ]);
        }

        $catalogue = json_decode((string) file_get_contents(database_path('data/farm_filaments.json')), true) ?: [];
        foreach ($catalogue as $i => $f) {
            $kind = $kinds[$f['material'].'|'.$f['finish']] ?? null;
            if (! $kind) {
                continue;
            }
            FarmColor::firstOrCreate(['drive_folder' => $f['drive_folder']], [
                'farm_material_id' => $kind->id, 'code' => $f['folder'], 'name' => $f['color'], 'name_en' => $f['color_en'] ?? null,
                'hex' => $f['hex'], 'enabled' => (bool) $kind->enabled, 'in_stock' => true, 'sort' => $i,
            ]);
        }

        $plaPlus = $kinds['PLA+|solid'];
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
            // curr_bed_type: without it the Orca CLI slices for a "Cool Plate" (bed 35 °C). The S1 printed on its textured
            // PEI plate until 24 Sep 2026, since then on a smooth PEI plate = Orca's "High Temp Plate" (the bed temperature
            // itself is rewritten per spool into the G-code anyway, GcodeSlot)
            'process_overrides' => ['enable_prime_tower' => '0', 'enable_support' => '1', 'support_type' => 'tree(auto)', 'support_threshold_angle' => '30', 'curr_bed_type' => 'High Temp Plate'],
        ]);

        if ($printer->wasRecentlyCreated) {
            // what was in the ACE on 22 Sep 2026: slot 3 white PLA+; the rest is for the operator to fill in
            $white = FarmColor::where('farm_material_id', $plaPlus->id)->where('name', 'bílá')->first();
            foreach ([0, 1, 2, 3] as $i) {
                $printer->slots()->create(['slot' => $i, 'farm_color_id' => $i === 2 ? $white?->id : null, 'remaining_g' => $i === 2 ? 900 : 0, 'enabled' => $i === 2 && $white !== null]);
            }
        }

        // second machine of the farm (23 Sep 2026): Kobra 3 Max Combo, bed slinger 420 x 420 x 500; same G9111 start
        // macro and T<n> spool selection as the S1, so the shared process/filament profiles serve it as well
        $max = FarmPrinter::firstOrCreate(['key' => 'kobra-3-max-01'], [
            'name' => 'Kobra 3 Max #1',
            'model' => 'Anycubic Kobra 3 Max Combo',
            'mode' => FarmPrinter::MODE_MANUAL,
            'bed_x' => 420, 'bed_y' => 420, 'bed_z' => 500, 'nozzle_mm' => 0.4,
            'time_factor' => 1.07, 'weight_factor' => 1.0,      // copied from the S1 until its own calibration print
            'machine_profile' => 'machine_kobra3max.json',
            'process_profiles' => ['draft' => 'process_draft.json', 'standard' => 'process_standard.json', 'fine' => 'process_fine.json'],
            'machine_overrides' => [],
            'process_overrides' => ['enable_prime_tower' => '0', 'enable_support' => '1', 'support_type' => 'tree(auto)', 'support_threshold_angle' => '30', 'curr_bed_type' => 'Textured PEI Plate'],
        ]);
        if ($max->wasRecentlyCreated) {
            foreach ([0, 1, 2, 3] as $i) {
                $max->slots()->create(['slot' => $i, 'farm_color_id' => null, 'remaining_g' => 0, 'enabled' => false]);
            }
        }

        // third machine (24 Sep 2026): a second Kobra S1, since 25 Sep 2026 with a 0.2 mm nozzle for fine work.
        // The nozzle has its own machine, process and filament profiles; the layer ladder follows it (FarmPrinter::layerFor)
        $s1b = FarmPrinter::firstOrCreate(['key' => 'kobra-s1-02'], [
            'name' => 'Kobra S1 #2',
            'model' => 'Anycubic Kobra S1 Combo',
            'mode' => FarmPrinter::MODE_AGENT,
            'bed_x' => 250, 'bed_y' => 250, 'bed_z' => 250, 'nozzle_mm' => 0.2,
            'time_factor' => 1.07, 'weight_factor' => 1.0,      // the S1 #1 numbers until this machine prints its own test
            'machine_profile' => 'machine_kobras1_n02.json',
            'process_profiles' => ['draft' => 'process_draft_n02.json', 'standard' => 'process_standard_n02.json', 'fine' => 'process_fine_n02.json'],
            'machine_overrides' => [],
            'process_overrides' => ['enable_prime_tower' => '0', 'enable_support' => '1', 'support_type' => 'tree(auto)', 'support_threshold_angle' => '30', 'curr_bed_type' => 'Textured PEI Plate'],
        ]);
        if ($s1b->wasRecentlyCreated) {
            foreach ([0, 1, 2, 3] as $i) {
                $s1b->slots()->create(['slot' => $i, 'farm_color_id' => null, 'remaining_g' => 0, 'enabled' => false]);
            }
        }

        // every enabled printer × kind gets its tuning row with the best known starting values
        app(ProfileLibrary::class)->sync();
    }
}
