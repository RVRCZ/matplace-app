<?php

namespace Tests\Feature;

use App\Models\FarmMaterial;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The silk and ABS kinds point at base profiles of their own, in the seeder and, for rows that exist, by migration. */
class FarmSilkAbsProfilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_gives_silk_and_abs_their_own_base_profiles(): void
    {
        $this->seed(FarmSeeder::class);

        $this->assertSame('filament_pla_silk.json', FarmMaterial::where(['code' => 'PLA', 'finish' => 'silk'])->value('filament_profile'));
        $this->assertSame('filament_abs.json', FarmMaterial::where('code', 'ABS')->value('filament_profile'));
        $this->assertSame('filament_abs.json', FarmMaterial::where('code', 'ABS+')->value('filament_profile'));
        $this->assertSame('filament_asa.json', FarmMaterial::where('code', 'ASA')->value('filament_profile'));
        $this->assertSame('filament_asa.json', FarmMaterial::where('code', 'PC')->value('filament_profile'), 'PC stays on the ASA profile');
        $this->assertSame('filament_pla.json', FarmMaterial::where(['code' => 'PLA', 'finish' => 'matte'])->value('filament_profile'));
    }

    public function test_the_migration_moves_only_rows_still_on_the_original_profile(): void
    {
        $row = fn (string $code, string $finish, string $profile) => FarmMaterial::create(['code' => $code, 'finish' => $finish, 'name' => $code, 'filament_profile' => $profile, 'density' => 1.2, 'price_per_gram' => 1.5]);
        $silk = $row('PLA', 'silk', 'filament_pla.json');
        $matte = $row('PLA', 'matte', 'filament_pla.json');
        $abs = $row('ABS', 'solid', 'filament_asa.json');
        $absPlus = $row('ABS+', 'solid', 'filament_asa.json');
        $absOwn = $row('ABS', 'matte', 'filament_abs_sunlu.json');   // one code and finish per row: the hand-set case is another finish
        $pc = $row('PC', 'solid', 'filament_asa.json');

        $migration = require base_path('database/migrations/2026_11_06_100000_farm_materials_silk_abs_profiles.php');
        $migration->up();

        $this->assertSame('filament_pla_silk.json', $silk->fresh()->filament_profile);
        $this->assertSame('filament_abs_sunlu.json', $absOwn->fresh()->filament_profile, 'a hand-set profile stays');
        $this->assertSame('filament_pla.json', $matte->fresh()->filament_profile);
        $this->assertSame('filament_abs.json', $abs->fresh()->filament_profile);
        $this->assertSame('filament_abs.json', $absPlus->fresh()->filament_profile);
        $this->assertSame('filament_asa.json', $pc->fresh()->filament_profile);

        $migration->down();
        $this->assertSame('filament_pla.json', $silk->fresh()->filament_profile);
        $this->assertSame('filament_asa.json', $abs->fresh()->filament_profile);
        $this->assertSame('filament_abs_sunlu.json', $absOwn->fresh()->filament_profile);
    }
}
