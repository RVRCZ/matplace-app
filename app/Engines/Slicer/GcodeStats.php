<?php

namespace App\Engines\Slicer;

/** Parses filament usage and print time from slicer G-code comments (OrcaSlicer / Bambu / PrusaSlicer style). */
final class GcodeStats
{
    public static function grams(string $gcode): ?float
    {
        if (preg_match('/total filament used \[g\]\s*=\s*([\d.]+)/', $gcode, $m)) {
            return (float) $m[1];
        }
        if (preg_match('/filament used \[g\]\s*=\s*([\d.]+)/', $gcode, $m)) {
            return (float) $m[1];
        }

        return null;
    }

    /** Filament length in metres; multi-slot machines list one value per slot ("3681.80, 0.00, 0.00, 0.00"). */
    public static function meters(string $gcode): ?float
    {
        if (preg_match('/^; (?:total )?filament used \[mm\]\s*=\s*([\d., ]+)/m', $gcode, $m)) {
            return array_sum(array_map('floatval', explode(',', $m[1]))) / 1000;
        }
        if (preg_match('/^; (?:total )?filament used \[m\]\s*=\s*([\d., ]+)/m', $gcode, $m)) {
            return array_sum(array_map('floatval', explode(',', $m[1])));
        }

        return null;
    }

    /** With supports on "auto" the slicer adds them only where needed: did it add any? */
    public static function hasSupports(string $gcode): bool
    {
        return (bool) preg_match('/^;\s*(?:TYPE|FEATURE):\s*Support/mi', $gcode);
    }

    public static function minutes(string $gcode): ?int
    {
        if (preg_match('/estimated printing time \(normal mode\)\s*=\s*(.+)/', $gcode, $m)) {
            return self::parseTime(trim($m[1]));
        }
        if (preg_match('/estimated printing time\s*=\s*(.+)/', $gcode, $m)) {
            return self::parseTime(trim($m[1]));
        }

        return null;
    }

    /** "1d 2h 23m 45s" → minutes (rounded, minimum 1). */
    public static function parseTime(string $s): int
    {
        $min = 0.0;
        if (preg_match('/(\d+)\s*d/', $s, $m)) {
            $min += (int) $m[1] * 1440;
        }
        if (preg_match('/(\d+)\s*h/', $s, $m)) {
            $min += (int) $m[1] * 60;
        }
        if (preg_match('/(\d+)\s*m/', $s, $m)) {
            $min += (int) $m[1];
        }
        if (preg_match('/(\d+)\s*s/', $s, $m)) {
            $min += (int) $m[1] / 60;
        }

        return max(1, (int) round($min));
    }
}
