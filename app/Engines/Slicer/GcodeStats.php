<?php

namespace App\Engines\Slicer;

/** Parses filament usage and print time from slicer G-code comments (OrcaSlicer / Bambu / PrusaSlicer style). */
final class GcodeStats
{
    /**
     * Everything below from a file, read a block of whole lines at a time: a 100-hour print is tens of MB and was
     * read into one string. The same patterns and the same "first one wins" as the string functions, so the same
     * numbers.
     *
     * @return array{grams: ?float, minutes: ?int, meters: ?float, minutes_by_mode: array<string,int>, layers: ?int, has_supports: bool}
     */
    public static function fromFile(string $path, int $block = 8388608): array
    {
        $patterns = [
            'grams_total' => '/total filament used \[g\]\s*=\s*([\d.]+)/',
            'grams' => '/filament used \[g\]\s*=\s*([\d.]+)/',
            'mm' => '/^; (?:total )?filament used \[mm\]\s*=\s*([\d., ]+)/m',
            'm' => '/^; (?:total )?filament used \[m\]\s*=\s*([\d., ]+)/m',
            'normal' => '/estimated printing time \(normal mode\)\s*=\s*(.+)/',
            'time' => '/estimated printing time\s*=\s*(.+)/',
            'layers' => '/^; total layer number:\s*(\d+)/m',
            'supports' => '/^;\s*(?:TYPE|FEATURE):\s*Support/mi',
        ];
        $first = [];
        $modes = [];
        $fh = fopen($path, 'rb');
        if (! $fh) {
            throw new \RuntimeException('Cannot read '.$path);
        }
        $rest = '';
        try {
            while (true) {
                $data = fread($fh, $block);
                $eof = $data === false || $data === '';
                $text = $rest.($eof ? '' : $data);
                if (! $eof) {
                    // whole lines only: what follows the last newline waits for the next block
                    $cut = strrpos($text, "\n");
                    if ($cut === false) {
                        $rest = $text;

                        continue;
                    }
                    $rest = substr($text, $cut + 1);
                    $text = substr($text, 0, $cut + 1);
                }
                foreach ($patterns as $key => $re) {
                    if (! isset($first[$key]) && preg_match($re, $text, $m)) {
                        $first[$key] = $m[1] ?? true;
                    }
                }
                if (preg_match_all('/^; estimated printing time \((\w+) mode\)\s*=\s*(.+)$/m', $text, $mm, PREG_SET_ORDER)) {
                    foreach ($mm as $m) {
                        $modes[$m[1]] = self::parseTime(trim($m[2]));
                    }
                }
                if ($eof) {
                    break;
                }
            }
        } finally {
            fclose($fh);
        }

        $grams = isset($first['grams_total']) ? (float) $first['grams_total'] : (isset($first['grams']) ? (float) $first['grams'] : null);
        $time = $first['normal'] ?? $first['time'] ?? null;
        $minutes = $time === null ? null : self::parseTime(trim($time));
        $meters = isset($first['mm']) ? array_sum(array_map('floatval', explode(',', $first['mm']))) / 1000
            : (isset($first['m']) ? array_sum(array_map('floatval', explode(',', $first['m']))) : null);
        if (! $modes && $minutes !== null) {
            $modes['normal'] = $minutes;
        }

        return [
            'grams' => $grams, 'minutes' => $minutes, 'meters' => $meters, 'minutes_by_mode' => $modes,
            'layers' => isset($first['layers']) ? (int) $first['layers'] : null, 'has_supports' => isset($first['supports']),
        ];
    }

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

    /**
     * Print time per speed mode of the machine (Anycubic/Orca write normal, silent and sport). Key → minutes.
     * A slicer without modes gives ['normal' => …] only.
     *
     * @return array<string,int>
     */
    public static function minutesByMode(string $gcode): array
    {
        $out = [];
        if (preg_match_all('/^; estimated printing time \((\w+) mode\)\s*=\s*(.+)$/m', $gcode, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                $out[$m[1]] = self::parseTime(trim($m[2]));
            }
        }
        if (! $out && ($n = self::minutes($gcode)) !== null) {
            $out['normal'] = $n;
        }

        return $out;
    }

    public static function layers(string $gcode): ?int
    {
        return preg_match('/^; total layer number:\s*(\d+)/m', $gcode, $m) ? (int) $m[1] : null;
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
