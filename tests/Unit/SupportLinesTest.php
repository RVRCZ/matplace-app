<?php

namespace Tests\Unit;

use App\Domain\Farm\GcodeSlot;
use App\Engines\Gcode\SupportLines;
use App\Engines\Mesh\StlFile;
use PHPUnit\Framework\TestCase;

class SupportLinesTest extends TestCase
{
    public function test_support_moves_become_segments_and_the_part_footprint_ignores_the_purge_line(): void
    {
        $gcode = implode("\n", [
            'M83', 'G92 E0',
            ';TYPE:Custom', 'G1 X89 Y255 F18000', 'G1 X158 Y255 E3.7',     // purge line: not part of the footprint
            ';LAYER_CHANGE', ';Z:0.2', 'G1 Z0.2 F900',
            ';TYPE:Outer wall', 'G1 X100 Y100 F6000', 'G1 X120 Y100 E1', 'G1 X120 Y110 E0.5', 'G1 X100 Y110 E1', 'G1 X100 Y100 E0.5',
            ';TYPE:Support', 'G1 X130 Y100 F6000', 'G1 X132 Y100 E0.1', 'G1 X132 Y102 E0.1', 'G1 X132 Y102 E-0.8',   // last one retracts
            ';TYPE:Support interface', 'G1 Z0.4', 'G1 X134 Y102 E0.1',
            ';TYPE:Sparse infill', 'G1 X110 Y105 E0.3',
        ])."\n";
        $in = tempnam(sys_get_temp_dir(), 'gc');
        $out = tempnam(sys_get_temp_dir(), 'sb');
        file_put_contents($in, $gcode);

        $r = SupportLines::extract($in, $out);

        $this->assertSame(3, $r['segments']);
        $this->assertSame([100.0, 100.0, 120.0, 110.0], $r['model']);
        $bin = file_get_contents($out);
        $this->assertSame(32 + 3 * 24, strlen($bin));
        $head = unpack('g8', $bin);
        $this->assertSame([1.0, 3.0, 100.0, 100.0, 120.0, 110.0], array_values(array_slice($head, 0, 6)));
        $first = unpack('g6', $bin, 32);
        $this->assertEqualsWithDelta([130, 100, 0.2, 132, 100, 0.2], array_values($first), 1e-4);
        $third = unpack('g6', $bin, 32 + 48);
        $this->assertEqualsWithDelta([132, 102, 0.4, 134, 102, 0.4], array_values($third), 1e-4, 'the Z move before it moved the pen up');
        unlink($in);
        unlink($out);
    }

    public function test_no_supports_means_no_file(): void
    {
        $in = tempnam(sys_get_temp_dir(), 'gc');
        $out = tempnam(sys_get_temp_dir(), 'sb');
        file_put_contents($in, ";TYPE:Outer wall\nG1 X10 Y10 E1\nG1 X20 Y10 E1\n");
        $this->assertNull(SupportLines::extract($in, $out));
        unlink($in);
        unlink($out);
    }

    /** The slicer no longer arranges (and turns) the model: we put it on the middle of the bed ourselves. */
    public function test_a_model_is_placed_on_the_middle_of_the_bed_without_turning(): void
    {
        $in = sys_get_temp_dir().'/mp_place_'.uniqid().'.stl';
        $out = $in.'.placed.stl';
        StlFile::writeBox($in, 60, 50, 23);
        $size = StlFile::place($in, $out, 0.5, 125, 125);
        $this->assertEqualsWithDelta([30.0, 25.0, 11.5], $size, 0.001);
        $lo = [INF, INF, INF];
        $hi = [-INF, -INF, -INF];
        foreach (StlFile::triangles($out) as $tri) {
            foreach ($tri as $v) {
                for ($i = 0; $i < 3; $i++) {
                    $lo[$i] = min($lo[$i], $v[$i]);
                    $hi[$i] = max($hi[$i], $v[$i]);
                }
            }
        }
        $this->assertEqualsWithDelta([110.0, 112.5, 0.0], $lo, 0.001);
        $this->assertEqualsWithDelta([140.0, 137.5, 11.5], $hi, 0.001);
        @unlink($in);
        @unlink($out);
    }

    /** A sign 3 mm thick with a raised text: the printer switches to the second spool at the first layer of the text. */
    public function test_the_second_colour_starts_with_the_first_layer_above_the_plate(): void
    {
        $layer = fn (int $n, float $z, float $h = 0.2) => ";LAYER_CHANGE\n;Z:{$z}\n;HEIGHT:{$h}\n; BEFORE_LAYER_CHANGE\nG1 Z{$z}\n; AFTER_LAYER_CHANGE {$n} @ {$z}mm\n;TYPE:Outer wall\nG1 X10 Y10 E1\n";
        $gcode = "G9111 bedTemp=55 extruderTemp=220\nM117\nT2 ; slot chosen by matplace farm\n";
        foreach ([2.6, 2.8, 3.0, 3.2, 3.4] as $n => $z) {
            $gcode .= $layer($n + 13, $z);
        }
        $out = GcodeSlot::secondColor($gcode, ['slot' => 1, 'z' => 3.0]);
        $this->assertSame(1, substr_count($out, 'second colour by matplace farm'));
        $this->assertStringContainsString("; AFTER_LAYER_CHANGE 16 @ 3.2mm\nT1 ; second colour by matplace farm\n;TYPE:Outer wall", $out);
        $this->assertSame($gcode, str_replace("T1 ; second colour by matplace farm\n", '', $out), 'nothing else is touched');

        // layers that do not end on the top of the plate: the layer cut above the plate is the first one of the text
        $odd = "M117\n".$layer(10, 2.8, 0.28).$layer(11, 3.08, 0.28).$layer(12, 3.36, 0.28);
        $this->assertStringContainsString("; AFTER_LAYER_CHANGE 12 @ 3.36mm\nT3 ;", GcodeSlot::secondColor($odd, ['slot' => 3, 'z' => 3.0]));
        // a model lower than the change keeps its single colour
        $this->assertSame($odd, GcodeSlot::secondColor($odd, ['slot' => 3, 'z' => 9.0]));
    }
}
