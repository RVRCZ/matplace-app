<?php

namespace Tests\Feature;

use App\Domain\Farm\SpoolPositions;
use App\Models\FarmPrinter;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A Prusa CORE One INDX in the admin: eight positions named as tools, the driver names next to the key (docs/AM.md §12). */
class FarmPrinterPositionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tool_changer_offers_eight_tool_positions_and_other_machines_keep_their_numbers(): void
    {
        $this->seed(FarmSeeder::class);
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $kobra = FarmPrinter::firstOrFail();
        $indx = FarmPrinter::create([
            'name' => 'Core One INDX', 'model' => 'Prusa CORE One+ INDX', 'key' => 'core-one-01', 'mode' => FarmPrinter::MODE_AGENT,
            'farm_agent_id' => $kobra->farm_agent_id, 'enabled' => true, 'bed_x' => 248, 'bed_y' => 205, 'bed_z' => 270, 'nozzle_mm' => 0.4,
            'machine_profile' => 'machine_coreone_indx8t.json', 'process_profiles' => ['standard' => 'process_standard_coreone_indx8t.json'],
            'time_factor' => 1, 'weight_factor' => 1,
        ]);

        $this->assertTrue($indx->isToolChanger());
        $this->assertFalse($kobra->isToolChanger());
        $this->assertSame(8, $indx->positionRows());
        $this->assertSame(4, $kobra->positionRows());
        $this->assertSame('Nástroj 8', SpoolPositions::name($indx, 7, 8));
        $this->assertSame(['Nástroj 1', 'Nástroj 2'], SpoolPositions::names($indx, 2));
        $this->assertSame('ACE 3', SpoolPositions::name($kobra, 2, 4), 'the Kobra keeps its ACE names');
        $indx->slots()->create(['slot' => 4, 'label' => 'INDX levý']);
        $this->assertSame('INDX levý', $indx->slots()->first()->positionLabel(), 'what the admin typed wins');

        $this->actingAs($admin)->get("/admin/farm/printers/{$indx->id}")->assertOk()
            ->assertSee('Nástroj 1')->assertSee('Nástroj 8')->assertDontSee('Nástroj 9')->assertSee('prusalink');
        $this->actingAs($admin)->get("/admin/farm/printers/{$kobra->id}")->assertOk()->assertDontSee('Nástroj ');
        $this->actingAs($admin)->get('/admin/farm/printers/load')->assertOk()->assertSee('Nástroj 8')->assertSee('INDX levý')->assertSee('ACE 1');
    }
}
