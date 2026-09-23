<?php

namespace Tests\Unit;

use App\Domain\Farm\GcodeSlot;
use App\Domain\Farm\ModelValidator;
use App\Engines\DTO\Dimensions;
use App\Engines\Farm\PhpPrintPreparer;
use App\Engines\Mesh\StlFile;
use App\Engines\Mesh\StlTopology;
use App\Engines\Slicer\GcodeStats;
use PHPUnit\Framework\TestCase;
use Tests\Support\MeshFixtures;

class FarmModelValidatorTest extends TestCase
{
    private const RULES = ['min_model_mm' => 5.0, 'bed_margin_mm' => 2.0];

    private array $tmp = [];

    protected function tearDown(): void
    {
        array_map(fn ($f) => is_file($f) && unlink($f), $this->tmp);
        parent::tearDown();
    }

    private function path(string $tag): string
    {
        return $this->tmp[] = sys_get_temp_dir().'/mp_val_'.$tag.'_'.uniqid().'.stl';
    }

    private function bed(): Dimensions
    {
        return new Dimensions(250, 250, 250);
    }

    private function prepared(string $in, float $unitScale = 1.0)
    {
        return (new PhpPrintPreparer)->prepare($in, $this->path('out'), $unitScale, $this->bed());
    }

    /** Cube without its two bottom triangles: an open box. */
    private function openCube(float $size = 20): string
    {
        $full = $this->path('full');
        MeshFixtures::cubeStl($full, $size);
        $open = $this->path('open');
        $fh = StlFile::beginBinary($open);
        $n = 0;
        foreach (StlFile::triangles($full) as $i => [$a, $b, $c]) {
            if ($i >= 2) {
                StlFile::writeTriangle($fh, $a, $b, $c);
                $n++;
            }
        }
        StlFile::endBinary($fh, $n);

        return $open;
    }

    public function test_binary_and_ascii_cubes_are_closed_solids(): void
    {
        $bin = $this->path('bin');
        MeshFixtures::cubeStl($bin, 20);
        $ascii = $this->path('ascii');
        MeshFixtures::cubeStlAscii($ascii, 20);

        foreach ([$bin, $ascii] as $file) {
            $t = StlTopology::check($file);
            $this->assertTrue($t['watertight']);
            $this->assertSame(12, $t['triangles']);
            $this->assertSame(0, $t['open_edges']);
            $verdict = ModelValidator::judge($this->prepared($file), $this->bed(), self::RULES);
            $this->assertTrue($verdict['ok']);
            $this->assertSame(['x' => 20.0, 'y' => 20.0, 'z' => 20.0], $verdict['dims']);
        }
    }

    public function test_a_hole_is_found_and_counted_by_its_rim(): void
    {
        $t = StlTopology::check($this->openCube());
        $this->assertFalse($t['watertight']);
        $this->assertSame(4, $t['open_edges']);   // the square rim; the diagonal went with both triangles

        $verdict = ModelValidator::judge($this->prepared($this->openCube()), $this->bed(), self::RULES);
        $this->assertFalse($verdict['ok']);
        $this->assertSame('not_watertight', $verdict['errors'][0]['code']);
    }

    public function test_size_limits_too_big_too_small_and_the_safety_margin(): void
    {
        $cube = function (float $s) {
            $p = $this->path('s');
            MeshFixtures::cubeStl($p, $s);

            return ModelValidator::judge($this->prepared($p), $this->bed(), self::RULES);
        };
        $this->assertTrue($cube(246)['ok'], '250 − 2 × 2 mm margin');
        $this->assertSame('exceeds_bed', $cube(247)['errors'][0]['code']);
        $this->assertSame('too_small', $cube(3)['errors'][0]['code']);
        $this->assertTrue($cube(5)['ok']);
    }

    public function test_a_long_part_may_lie_either_way_but_never_stand_taller_than_the_printer(): void
    {
        $box = function (float $x, float $y, float $z) {
            $p = $this->path('b');
            StlFile::writeBox($p, $x, $y, $z);

            return ModelValidator::judge($this->prepared($p), $this->bed(), self::RULES);
        };
        $this->assertTrue($box(240, 30, 10)['ok']);
        $this->assertTrue($box(30, 240, 10)['ok']);
        $this->assertSame('exceeds_bed', $box(30, 30, 260)['errors'][0]['code']);
    }

    public function test_unit_guess_metres_inches_and_millimetres(): void
    {
        $bed = $this->bed();
        $this->assertSame(['unit' => 'm', 'confident' => true], ModelValidator::guessUnit(new Dimensions(0.12, 0.08, 0.05), $bed));
        $this->assertSame(['unit' => 'in', 'confident' => true], ModelValidator::guessUnit(new Dimensions(1.5, 1.0, 0.5), $bed), '1.5 m would not fit any plate, 1.5 in does');
        $this->assertSame(['unit' => 'in', 'confident' => false], ModelValidator::guessUnit(new Dimensions(4, 2, 1), $bed), 'could be a small mm part: ask');
        $this->assertSame(['unit' => 'mm', 'confident' => true], ModelValidator::guessUnit(new Dimensions(120, 80, 50), $bed));
    }

    public function test_units_are_applied_before_the_size_is_judged(): void
    {
        $p = $this->path('inch');
        StlFile::writeBox($p, 2, 1, 0.5);   // inches
        $this->assertSame('too_small', ModelValidator::judge($this->prepared($p), $this->bed(), self::RULES)['errors'][0]['code']);

        $verdict = ModelValidator::judge($this->prepared($p, ModelValidator::UNITS['in']), $this->bed(), self::RULES);
        $this->assertTrue($verdict['ok']);
        $this->assertEqualsWithDelta(50.8, $verdict['dims']['x'], 0.01);
    }

    public function test_gcode_is_retargeted_to_the_chosen_slot_and_nothing_else_changes(): void
    {
        $gcode = "G9111 bedTemp=55 extruderTemp=220\nM117\nT0\nG1 X10 T0 ; not a tool line\nM104 T0 S220\n";
        $this->assertSame($gcode, GcodeSlot::retarget($gcode, 0));
        $out = GcodeSlot::retarget($gcode, 2);
        $this->assertStringContainsString("\nT2 ; slot chosen by matplace farm\n", $out);
        $this->assertStringContainsString('G1 X10 T0 ; not a tool line', $out);
        $this->assertStringContainsString('M104 T0 S220', $out);

        // OrcaSlicer writes no tool line for a single-colour print: the slot goes right after the start macro
        $orca = "; generated by OrcaSlicer\nG9111 bedTemp=55 extruderTemp=215\nM117\nM106 P3 S153\nG90\n";
        $this->assertSame("; generated by OrcaSlicer\nG9111 bedTemp=55 extruderTemp=215\nM117\nT2 ; slot chosen by matplace farm\nM106 P3 S153\nG90\n", GcodeSlot::retarget($orca, 2));
        $this->assertSame($orca, GcodeSlot::retarget($orca, 0));
    }

    public function test_gcode_gets_the_temperatures_of_the_chosen_filament_kind(): void
    {
        // sliced as PLA (215 first layer / 205 / bed 55), printed from a PETG spool in slot 2
        $g = "G9111 bedTemp=55 extruderTemp=215
M117
M106 P3 S153
M104 S205 ; set nozzle temperature
M140 S55 ; set bed temperature
M109 S205
M190 S55
G1 X10
M140 S0 ; turn off heatbed
M104 S0 ; turn off temperature
";
        $out = GcodeSlot::retarget($g, 1, ['nozzle' => 240, 'nozzle_first' => 245, 'bed' => 75]);
        $this->assertStringContainsString("G9111 bedTemp=75 extruderTemp=245
M117
T1 ; slot chosen by matplace farm
", $out);
        $this->assertStringContainsString('M104 S240 ; set nozzle temperature', $out);
        $this->assertStringContainsString('M140 S75 ; set bed temperature', $out);
        $this->assertStringContainsString("M109 S240
M190 S75
", $out);
        $this->assertStringContainsString("M140 S0 ; turn off heatbed
M104 S0 ; turn off temperature", $out, 'switching the heaters off stays');
        $this->assertSame($g, GcodeSlot::retarget($g, 0), 'slot 0 and no temperatures: untouched');
        // no first-layer value: nozzle + 5
        $this->assertStringContainsString('extruderTemp=225', GcodeSlot::retarget($g, 0, ['nozzle' => 220]));
    }

    public function test_gcode_header_gives_metres_for_multi_slot_machines_and_detects_supports(): void
    {
        $g = "; filament used [mm] = 3681.80, 0.00, 0.00, 0.00\n; total filament used [g] = 10.98\n;TYPE:Outer wall\n";
        $this->assertEqualsWithDelta(3.6818, GcodeStats::meters($g), 0.0001);
        $this->assertFalse(GcodeStats::hasSupports($g));
        $this->assertTrue(GcodeStats::hasSupports($g.";TYPE:Support\n"));
        $this->assertTrue(GcodeStats::hasSupports($g.";TYPE:Support interface\n"));
    }
}
