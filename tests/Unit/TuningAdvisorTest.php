<?php

namespace Tests\Unit;

use App\Domain\Farm\TuningAdvisor;
use PHPUnit\Framework\TestCase;

/** Observed defects on the test object → concrete setting changes with a reason, never outside sane bounds. */
class TuningAdvisorTest extends TestCase
{
    private array $candidate = ['nozzle_temp' => 220, 'nozzle_temp_first' => 225, 'bed_temp' => 60, 'filament' => ['fan_max_speed' => ['80'], 'overhang_fan_speed' => ['80']], 'process' => ['outer_wall_speed' => '150']];

    private function settings(array $r): array
    {
        $out = [];
        foreach ($r['advice'] as $a) {
            $out[$a['setting']] = $a['to'];
        }

        return $out;
    }

    public function test_a_clean_print_changes_nothing(): void
    {
        $r = TuningAdvisor::advise(['stringing' => 0, 'overhang_ok' => 70, 'bridge' => 'ok', 'corners' => 'ok', 'top' => 'ok', 'cube_x' => 15.02, 'cube_y' => 14.97, 'hole' => 7.98], $this->candidate, 'quick');
        $this->assertSame([], $r['advice']);
        $this->assertEquals($this->candidate, $r['overrides']);
    }

    public function test_stringing_cools_the_nozzle_and_lengthens_the_retraction(): void
    {
        $s = $this->settings($r = TuningAdvisor::advise(['stringing' => 3], $this->candidate, 'quick'));
        $this->assertSame(210, $s['nozzle_temp']);
        $this->assertSame(215, $r['overrides']['nozzle_temp_first']);
        $this->assertSame('1.2', $s['filament.filament_retraction_length']);
        $this->assertSame(['1.2'], $r['overrides']['filament']['filament_retraction_length']);
        $this->assertNotEmpty($r['notes'], 'wet filament reminder');
    }

    public function test_overhangs_and_bridges_ask_for_fan_and_slower_bridges(): void
    {
        $s = $this->settings(TuningAdvisor::advise(['overhang_ok' => 40, 'bridge' => 'sag'], $this->candidate, 'quick'));
        $this->assertSame('95', $s['filament.fan_max_speed'], '80 + 15');
        $this->assertSame('100', $s['filament.overhang_fan_speed']);
        $this->assertSame('40', $s['process.bridge_speed'], '50 − 10');
        $this->assertArrayNotHasKey('nozzle_temp', $s, '40° is not below 40');
    }

    public function test_dimensions_become_contour_and_hole_compensation(): void
    {
        $s = $this->settings(TuningAdvisor::advise(['cube_x' => 14.8, 'cube_y' => 14.8, 'hole' => 7.7, 'cube_z' => 14.7], $this->candidate, 'quick'));
        $this->assertSame('0.1', $s['process.xy_contour_compensation']);
        $this->assertSame('0.15', $s['process.xy_hole_compensation']);
        $r = TuningAdvisor::advise(['cube_z' => 14.7], $this->candidate, 'quick');
        $this->assertStringContainsString('Z-offset', $r['notes'][0]);
    }

    public function test_corners_top_bond_and_warping(): void
    {
        $s = $this->settings(TuningAdvisor::advise(['corners' => 'bulge', 'top' => 'gaps', 'bond' => 'weak', 'warp' => 'lift', 'elephant' => 2], $this->candidate, 'quick'));
        $this->assertSame('120', $s['process.outer_wall_speed'], '150 − 20 %');
        $this->assertSame('0.045', $s['filament.pressure_advance']);
        $this->assertSame('1', $s['filament.filament_flow_ratio'], '0.98 + 0.02');
        $this->assertSame('6', $s['process.top_shell_layers']);
        $this->assertSame(225, $s['nozzle_temp'], 'weak bond: +5');
        $this->assertSame('65', $s['filament.fan_max_speed'], 'weak bond: −15');
        $this->assertSame('outer_only', $s['process.brim_type']);
        $this->assertSame('0.3', $s['process.elefant_foot_compensation']);
        // bed: +5 for warping, −5 for the strong elephant foot → back where it was, so no advice line survives for it
        $this->assertSame(60, TuningAdvisor::advise(['warp' => 'lift', 'elephant' => 2], $this->candidate, 'quick')['overrides']['bed_temp']);
    }

    public function test_a_tower_takes_the_best_floor_temperature(): void
    {
        $r = TuningAdvisor::advise(['best_floor' => 3, 'stringing' => 1], $this->candidate, 'temp_tower', [230, 225, 220, 215, 210]);
        $this->assertSame(220, $r['overrides']['nozzle_temp']);
        $this->assertSame(225, $r['overrides']['nozzle_temp_first']);
        $this->assertSame('0.9', $this->settings($r)['filament.filament_retraction_length']);
    }

    public function test_bounds_hold(): void
    {
        $c = ['nozzle_temp' => 220, 'filament' => ['fan_max_speed' => ['95'], 'filament_retraction_length' => ['2.4']]];
        $s = $this->settings(TuningAdvisor::advise(['overhang_ok' => 30, 'stringing' => 3], $c, 'quick'));
        $this->assertSame('100', $s['filament.fan_max_speed']);
        $this->assertSame('2.5', $s['filament.filament_retraction_length']);
        $this->assertSame(210, $s['nozzle_temp'], 'stringing −10; the overhang rule does not cool a second time when stringing already did');
    }

    public function test_round_corners_weak_bond_and_ironing(): void
    {
        $s = $this->settings(TuningAdvisor::advise(['corners' => 'round', 'bond' => 'weak', 'ironing' => 'lines'], $this->candidate, 'detailed'));
        $this->assertSame('120', $s['process.outer_wall_speed']);
        $this->assertSame('3000', $s['process.outer_wall_acceleration'], '5000 − 40 %');
        $this->assertSame('0.045', $s['filament.pressure_advance']);
        $this->assertSame('9.6', $s['filament.filament_max_volumetric_speed'], '12 − 20 %');
        $this->assertSame('0.1', $s['process.ironing_spacing']);
        $this->assertSame('12%', $s['process.ironing_flow'], 'the percent unit survives');

        $c = $this->candidate + ['process' => ['ironing_flow' => '15%', 'ironing_speed' => '30']];
        $c['process'] = ['ironing_flow' => '15%', 'ironing_speed' => '30'] + $this->candidate['process'];
        $s = $this->settings(TuningAdvisor::advise(['ironing' => 'bumps'], $c, 'detailed'));
        $this->assertSame('12%', $s['process.ironing_flow']);
        $s = $this->settings(TuningAdvisor::advise(['ironing' => 'rough'], $c, 'detailed'));
        $this->assertSame('27', $s['process.ironing_speed']);
        $this->assertSame(225, $s['nozzle_temp']);
    }

    public function test_stringing_with_a_weak_bond_means_a_wet_spool_and_leaves_the_temperature_alone(): void
    {
        $r = TuningAdvisor::advise(['stringing' => 3, 'bond' => 'weak'], $this->candidate, 'detailed');
        $this->assertSame(220, $r['overrides']['nozzle_temp'], 'neither −10 nor +5');
        $s = $this->settings($r);
        $this->assertArrayNotHasKey('nozzle_temp', $s);
        $this->assertSame('1.2', $s['filament.filament_retraction_length']);
        $this->assertSame('65', $s['filament.fan_max_speed']);
        $this->assertStringContainsString('mokrý filament', $r['notes'][0]);
    }
}
