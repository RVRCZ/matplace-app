<?php

namespace App\Domain\Farm;

/**
 * Layer-synced time-lapse: after every finished layer the head moves out of the camera's way and waits a moment,
 * the agent sees it standing at the park position and takes the picture. The object is then seen without the
 * nozzle in front of it, and every frame shows one more layer.
 *
 * Inserted right after OrcaSlicer's "; AFTER_LAYER_CHANGE" marker: at that point the slicer has already retracted
 * and wiped, and its next move is an absolute travel (X Y Z F) to the start of the new layer, so nothing has to be
 * put back afterwards. A file that does not look like that (absolute extrusion, no markers) is left alone.
 *
 * The first line tells the agent where to look for the head:  "; matplace timelapse park_x=.. park_y=.. dwell=.."
 */
final class TimelapseGcode
{
    public const HEADER = '; matplace timelapse';

    private const MARKER = '; AFTER_LAYER_CHANGE';

    private const RETRACT_MM = 0.8;

    /**
     * @param  array{park_x: float, park_y: float, dwell_ms?: int, lift_mm?: float, travel_mm_s?: float}  $o
     */
    public static function apply(string $gcode, array $o): string
    {
        if (str_starts_with($gcode, self::HEADER) || ! str_contains($gcode, self::MARKER) || preg_match('/^M82\b/m', $gcode)) {
            return $gcode;
        }
        $px = round((float) $o['park_x'], 2);
        $py = round((float) $o['park_y'], 2);
        $dwell = max(300, min(5000, (int) ($o['dwell_ms'] ?? 1000)));
        $lift = max(0.2, min(5.0, (float) ($o['lift_mm'] ?? 0.6)));
        $travel = (int) round(60 * max(50, min(600, (float) ($o['travel_mm_s'] ?? 200))));

        $z = 0.0;
        $f = null;
        $retracted = false;
        $seen = 0;
        $frames = 0;
        $out = [];
        foreach (preg_split('/\r?\n/', $gcode) as $line) {
            $out[] = $line;
            if (str_starts_with($line, 'G1 ') || str_starts_with($line, 'G0 ')) {
                $code = strstr($line, ';', true);
                $code = $code === false ? $line : $code;
                if (preg_match('/\bZ(-?[\d.]+)/', $code, $m)) {
                    $z = (float) $m[1];
                }
                if (preg_match('/\bF([\d.]+)/', $code, $m)) {
                    $f = $m[1];
                }
                if (preg_match('/\bE(-?[\d.]+)/', $code, $m) && (float) $m[1] != 0.0) {
                    $retracted = (float) $m[1] < 0;
                }

                continue;
            }
            if (! str_starts_with($line, self::MARKER)) {
                continue;
            }
            // the first marker comes before anything is printed: no picture of an empty plate
            if (++$seen === 1) {
                continue;
            }
            $block = ['; matplace timelapse frame'];
            if (! $retracted) {
                $block[] = 'G1 E-'.self::RETRACT_MM.' F2400';
            }
            $block[] = sprintf('G1 Z%.3f F900', $z + $lift);
            $block[] = sprintf('G1 X%s Y%s F%d', $px, $py, $travel);
            $block[] = 'G4 P'.$dwell;
            if (! $retracted) {
                $block[] = 'G1 E'.self::RETRACT_MM.' F2400';
            }
            if ($f !== null) {
                $block[] = 'G1 F'.$f;     // the slicer's next move may rely on the last feed rate
            }
            array_push($out, ...$block);
            $frames++;
        }
        if ($frames === 0) {
            return $gcode;
        }

        return sprintf('%s park_x=%s park_y=%s dwell=%d travel=%d frames=%d', self::HEADER, $px, $py, $dwell, (int) round($travel / 60), $frames)."\n".implode("\n", $out);
    }

    /** Park position and dwell written by apply(), for the agent and the tests; null for an untouched file. */
    public static function header(string $gcode): ?array
    {
        if (! preg_match('/^'.preg_quote(self::HEADER, '/').' park_x=(-?[\d.]+) park_y=(-?[\d.]+) dwell=(\d+)(?: travel=(\d+))? frames=(\d+)/', $gcode, $m)) {
            return null;
        }

        return ['park_x' => (float) $m[1], 'park_y' => (float) $m[2], 'dwell_ms' => (int) $m[3], 'travel_mm_s' => (int) ($m[4] ?: 200), 'frames' => (int) $m[5]];
    }

    /**
     * Minutes the parking adds to a print: one stop per layer after the first, each = lift and lower + the way
     * from the middle of the plate to the park position and back + the dwell (+ a little for accelerating).
     */
    public static function extraMinutes(string $gcode, array $o, float $bedX, float $bedY): int
    {
        $stops = max(0, substr_count($gcode, "\n".self::MARKER) - 1);
        if ($stops === 0 || preg_match('/^M82\b/m', $gcode)) {
            return 0;
        }
        $travel = max(50.0, min(600.0, (float) ($o['travel_mm_s'] ?? 200)));
        $distance = hypot((float) $o['park_x'] - $bedX / 2, (float) $o['park_y'] - $bedY / 2);
        $perStop = max(300, min(5000, (int) ($o['dwell_ms'] ?? 1000))) / 1000 + 2 * $distance / $travel
            + 2 * max(0.2, min(5.0, (float) ($o['lift_mm'] ?? 0.6))) / 15 + 0.3;

        return (int) ceil($stops * $perStop / 60);
    }

    /** Writes the time-lapse copy next to the given file and returns its path. */
    public static function fileFor(string $gcodePath, array $options): string
    {
        $target = preg_replace('/\.gcode$/', '', $gcodePath).'.tl-'.substr(md5(json_encode($options)), 0, 8).'.gcode';
        if (! is_file($target) || filemtime($target) < filemtime($gcodePath)) {
            file_put_contents($target, self::apply((string) file_get_contents($gcodePath), $options));
        }

        return $target;
    }
}
