<?php

namespace Tests\Unit;

use App\Engines\Slicer\GcodeStats;
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
}
