<?php

namespace Tests\Feature;

use App\Domain\Farm\GcodeSlot;
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

    /**
     * The CORE One INDX has its own preset for every kind the farm can load there (TODO #70 F, Roman 6 Nov 2026: yes).
     * PLA Silk and ABS kinds reach theirs only through a base profile of their own: a kind on filament_pla.json would
     * get the plain PLA preset, and a base that does not exist is "Slicer profile missing" on every other machine.
     */
    public function test_the_core_one_indx_gets_its_own_preset_for_every_kind_including_silk_and_abs(): void
    {
        foreach (['pla', 'petg', 'asa', 'tpu', 'abs', 'pla_silk', 'prusament_pla', 'prusament_petg'] as $kind) {
            $this->assertSame("filament_{$kind}_coreone_indx8t.json", $this->variant("filament_{$kind}.json", 'machine_coreone_indx8t.json'), $kind);
        }
        $slicer = new OrcaSlicer(config('engines.orca'));
        $file = fn (string $name) => (fn () => $this->profileFile($name))->call($slicer);
        $this->assertFileExists($file('filament_pla_silk.json'), 'the base the silk kinds point at');
        $this->assertFileExists($file('filament_abs.json'), 'the base the ABS kinds point at');

        // the bases are the shared PLA and ASA profiles under another preset name: Anycubic machines print as before
        $same = fn (string $a, string $b) => array_diff_key(json_decode(file_get_contents(base_path("engines/orca/profiles/{$a}")), true), ['name' => 1, 'filament_settings_id' => 1])
            == array_diff_key(json_decode(file_get_contents(base_path("engines/orca/profiles/{$b}")), true), ['name' => 1, 'filament_settings_id' => 1]);
        $this->assertTrue($same('filament_pla_silk.json', 'filament_pla.json'));
        $this->assertTrue($same('filament_abs.json', 'filament_asa.json'));
        $this->assertSame('filament_pla_silk.json', $this->variant('filament_pla_silk.json', 'machine.json'), 'the S1 keeps the shared base');
        $this->assertSame('filament_abs.json', $this->variant('filament_abs.json', 'machine_kobrax.json'));

        $silk = json_decode(file_get_contents(base_path('engines/orca/profiles/filament_pla_silk_coreone_indx8t.json')), true);
        $this->assertSame(['PLA'], $silk['filament_type']);
        $abs = json_decode(file_get_contents(base_path('engines/orca/profiles/filament_abs_coreone_indx8t.json')), true);
        $this->assertSame(['ABS'], $abs['filament_type']);
    }

    public function test_the_kobra_x_gets_its_own_profiles_that_the_servers_slicer_can_read(): void
    {
        $this->assertSame('filament_pla_kobrax.json', $this->variant('filament_pla.json', 'machine_kobrax.json'));
        $this->assertSame('filament_petg_kobrax.json', $this->variant('filament_petg.json', 'machine_kobrax.json'));
        // Anycubic's ASA preset for the Kobra X says 205 °C and full fan (a PLA copy): the shared one stays
        $this->assertSame('filament_asa.json', $this->variant('filament_asa.json', 'machine_kobrax.json'));
        $this->assertSame('filament_tpu.json', $this->variant('filament_tpu.json', 'machine_kobrax.json'));

        $x = json_decode(file_get_contents(base_path('engines/orca/profiles/machine_kobrax.json')), true);
        $this->assertSame('Anycubic Kobra X 0.4 nozzle', $x['name']);
        $this->assertSame(['0x0', '260x0', '260x260', '0x260'], $x['printable_area']);
        // OrcaSlicer 2.4 on the server refuses Anycubic's ["0"] here ("Invalid parameter value(s)", CLI -18, 14 Oct 2026)
        $s1 = json_decode(file_get_contents(base_path('engines/orca/profiles/machine.json')), true);
        $this->assertSame($s1['retraction_distances_when_cut'], $x['retraction_distances_when_cut']);
        $this->assertEquals(260, $x['printable_height']);
        // the start is the S1's: the macro heats with these two values and M117 follows - what the spool rewrite expects
        $this->assertStringStartsWith("G9111 bedTemp=[first_layer_bed_temperature] extruderTemp=[first_layer_temperature[initial_tool]]\nM117\n", $x['machine_start_gcode']);
        // Anycubic Slicer Next's own filament change asks for variables OrcaSlicer 2.4 does not know
        $this->assertStringContainsString('T[next_extruder]', $x['change_filament_gcode']);
        $this->assertDoesNotMatchRegularExpression('/ace_t_|flush_length_\d/', $x['change_filament_gcode']);
        // no purge line of the S1 at Y255: on a 260 mm bed that would be inside the plate (the start macro purges)
        $this->assertSame('', trim((string) ($x['before_layer_change_gcode'] ?? '')));

        $pla = json_decode(file_get_contents(base_path('engines/orca/profiles/filament_pla_kobrax.json')), true);
        $this->assertSame('Anycubic PLA @Anycubic Kobra X 0.4 nozzle', $pla['name']);
        $this->assertSame(['0.96'], $pla['filament_flow_ratio']);

        // what the slicer writes from that start, rewritten for the spool in slot 3: heated, then the tool line
        $start = str_replace(['[first_layer_bed_temperature]', '[first_layer_temperature[initial_tool]]'], ['60', '220'], strtok($x['machine_start_gcode'], "\n"))."\nM117\nG90\nM104 S205 ; set nozzle temperature\n";
        $out = GcodeSlot::retarget($start, 2, ['nozzle' => 215, 'nozzle_first' => 225, 'bed' => 60]);
        $this->assertStringStartsWith("G9111 bedTemp=60 extruderTemp=225\nM117\nM104 S225 ; matplace slot: heat before the spool change\nM109 S225 ; matplace slot: wait for it\nT2 ; slot chosen by matplace farm\n", $out);
    }
}
