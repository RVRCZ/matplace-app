<?php

namespace Tests\Unit;

use App\Engines\Slicer\GcodeStats;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\TestCase;

class GcodeStatsTest extends TestCase
{
    public function test_parses_orca_footer(): void
    {
        $g = "; filament used [mm] = 7654.3\n; filament used [g] = 3.59\n; total filament used [g] = 22.86\n; estimated printing time (normal mode) = 41m 14s\n";
        $this->assertSame(22.86, GcodeStats::grams($g));
        $this->assertSame(41, GcodeStats::minutes($g));
    }

    public function test_falls_back_to_single_filament_line(): void
    {
        $g = "; filament used [g] = 3.59\n; estimated printing time (normal mode) = 1d 2h 3m 10s\n";
        $this->assertSame(3.59, GcodeStats::grams($g));
        $this->assertSame(1440 + 120 + 3, GcodeStats::minutes($g));
    }

    public function test_missing_values(): void
    {
        $this->assertNull(GcodeStats::grams('G1 X10'));
        $this->assertNull(GcodeStats::minutes('G1 X10'));
        $this->assertSame(1, GcodeStats::parseTime('5s'));
    }

    /** Read from a file a block at a time: the same numbers as from the whole string, wherever the blocks break. */
    public function test_a_file_read_in_blocks_gives_what_the_string_gives(): void
    {
        $orca = '; HEADER_BLOCK_START
; total layer number: 240
; estimated printing time (normal mode) = 9h 1m
; HEADER_BLOCK_END
'
            .str_repeat(';TYPE:Outer wall
G1 X1 Y2 E0.1
', 300).';TYPE:Support
G1 X3
'.str_repeat('G1 X5 Y6 E0.2
', 200)
            .'; filament used [mm] = 3681.80, 12.5, 0.00, 0.00
; filament used [g] = 3.59
; total filament used [g] = 22.86
'
            .'; estimated printing time (normal mode) = 1d 2h 3m 4s
; estimated printing time (silent mode) = 2d 0h 0m 1s
; estimated printing time (sport mode) = 20h 5m
';
        $samples = [
            'orca' => $orca,
            'prusa' => 'G1 X1
; filament used [m] = 3.25
; filament used [g] = 9.8
; estimated printing time = 41m 14s',
            'nothing' => 'G1 X10
G1 Y2
',
            'no newline at the end' => '; total filament used [g] = 1.5',
            'feature comments' => '; FEATURE: Support interface
; filament used [g] = 2
',
        ];
        $dir = sys_get_temp_dir().'/mp_gstats_'.uniqid();
        @mkdir($dir);
        foreach ($samples as $name => $g) {
            file_put_contents($dir.'/g.gcode', $g);
            $want = ['grams' => GcodeStats::grams($g), 'minutes' => GcodeStats::minutes($g), 'meters' => GcodeStats::meters($g),
                'minutes_by_mode' => GcodeStats::minutesByMode($g), 'layers' => GcodeStats::layers($g), 'has_supports' => GcodeStats::hasSupports($g)];
            foreach ([7, 64, 1000, 8388608] as $block) {
                $this->assertSame($want, GcodeStats::fromFile($dir.'/g.gcode', $block), "$name, blocks of $block");
            }
        }
        array_map('unlink', glob($dir.'/*'));
        rmdir($dir);
    }
}
