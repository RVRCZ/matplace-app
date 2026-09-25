<?php

namespace Tests\Feature;

use App\Engines\Slicer\OrcaSlicer;
use Tests\TestCase;

/** A farm printer with its own machine profile slices with that machine's flavour of the shared filament profile. */
class OrcaProfileVariantTest extends TestCase
{
    private function variant(?string $filament, ?string $machine): ?string
    {
        $slicer = new OrcaSlicer(config('engines.orca'));

        return (fn () => $this->machineVariant($filament, $machine))->call($slicer);
    }

    public function test_the_kobra_3_max_gets_its_own_filament_profiles_and_the_rest_keep_the_shared_one(): void
    {
        $this->assertSame('filament_pla_kobra3max.json', $this->variant('filament_pla.json', 'machine_kobra3max.json'));
        $this->assertSame('filament_petg_kobra3max.json', $this->variant('filament_petg.json', 'machine_kobra3max.json'));
        $this->assertSame('filament_asa.json', $this->variant('filament_asa.json', 'machine_kobra3max.json'), 'no ASA preset for the K3 Max: shared');
        $this->assertSame('filament_pla.json', $this->variant('filament_pla.json', 'machine.json'), 'the S1 profile is the shared one');
        $this->assertSame('filament_pla.json', $this->variant('filament_pla.json', null));

        $k3m = json_decode(file_get_contents(base_path('engines/orca/profiles/filament_pla_kobra3max.json')), true);
        $this->assertSame('Anycubic PLA @Anycubic Kobra 3 Max 0.4 nozzle', $k3m['name']);
        $this->assertSame(['0.965'], $k3m['filament_flow_ratio']);
        $this->assertSame(['0'], $k3m['additional_cooling_fan_speed'], 'no auxiliary fan on the K3 Max');
    }
}
