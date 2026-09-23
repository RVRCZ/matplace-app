<?php

namespace Tests\Unit;

use App\Engines\Gcode\SupportLines;
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
}
