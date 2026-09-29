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
        $in = fopen('php://temp', 'r+b');
        fwrite($in, $gcode);
        rewind($in);
        $out = fopen('php://temp', 'r+b');
        $header = self::transform($in, $out, $o);
        fclose($in);
        if ($header === null) {
            fclose($out);

            return $gcode;
        }
        rewind($out);
        $body = (string) stream_get_contents($out);
        fclose($out);

        return $header."\n".$body;
    }

    /**
     * Line by line from $in to $out, so a G-code of any size (a 90 MB dragon slices to 123 MB) needs no more memory
     * than one line. Returns the header line, or null when the file is left as it is (already done, absolute
     * extrusion, no layer markers).
     *
     * @param  resource  $in
     * @param  resource  $out
     */
    private static function transform($in, $out, array $o): ?string
    {
        $first = fgets($in);
        if ($first === false || str_starts_with($first, self::HEADER)) {
            return null;
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
        $absoluteE = false;
        for ($raw = $first; $raw !== false; $raw = fgets($in)) {
            fwrite($out, $raw);
            $line = rtrim($raw, "\r\n");
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
            if (preg_match('/^M82\b/', $line)) {
                $absoluteE = true;

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
            // a marker on the very last line has no line break of its own
            fwrite($out, (str_ends_with($raw, "\n") ? '' : "\n").implode("\n", $block)."\n");
            $frames++;
        }
        if ($frames === 0 || $absoluteE) {
            return null;
        }

        return sprintf('%s park_x=%s park_y=%s dwell=%d travel=%d frames=%d', self::HEADER, $px, $py, $dwell, (int) round($travel / 60), $frames);
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
        $stops = max(0, substr_count("\n".$gcode, "\n".self::MARKER) - 1);

        return preg_match('/^M82\b/m', $gcode) ? 0 : self::minutesFor($stops, $o, $bedX, $bedY);
    }

    /** extraMinutes() for a G-code file, read line by line. */
    public static function extraMinutesForFile(string $path, array $o, float $bedX, float $bedY): int
    {
        $fh = @fopen($path, 'rb');
        if (! $fh) {
            return 0;
        }
        $markers = 0;
        while (($line = fgets($fh)) !== false) {
            if (str_starts_with($line, self::MARKER)) {
                $markers++;
            } elseif (str_starts_with($line, 'M82') && preg_match('/^M82\b/', $line)) {
                fclose($fh);

                return 0;
            }
        }
        fclose($fh);

        return self::minutesFor(max(0, $markers - 1), $o, $bedX, $bedY);
    }

    private static function minutesFor(int $stops, array $o, float $bedX, float $bedY): int
    {
        if ($stops === 0) {
            return 0;
        }
        $travel = max(50.0, min(600.0, (float) ($o['travel_mm_s'] ?? 200)));
        $distance = hypot((float) $o['park_x'] - $bedX / 2, (float) $o['park_y'] - $bedY / 2);
        $perStop = max(300, min(5000, (int) ($o['dwell_ms'] ?? 1000))) / 1000 + 2 * $distance / $travel
            + 2 * max(0.2, min(5.0, (float) ($o['lift_mm'] ?? 0.6))) / 15 + 0.3;

        return (int) ceil($stops * $perStop / 60);
    }

    /** Writes the time-lapse copy next to the given file and returns its path (the given one when nothing changes). */
    public static function fileFor(string $gcodePath, array $options): string
    {
        $target = preg_replace('/\.gcode$/', '', $gcodePath).'.tl-'.substr(md5(json_encode($options)), 0, 8).'.gcode';
        if (is_file($target) && filemtime($target) >= filemtime($gcodePath)) {
            return $target;
        }
        $in = fopen($gcodePath, 'rb');
        $body = fopen($target.'.part', 'w+b');
        $header = self::transform($in, $body, $options);
        fclose($in);
        if ($header === null) {
            fclose($body);
            @unlink($target.'.part');

            return $gcodePath;
        }
        rewind($body);
        $out = fopen($target.'.tmp', 'wb');
        fwrite($out, $header."\n");
        stream_copy_to_stream($body, $out);
        fclose($out);
        fclose($body);
        @unlink($target.'.part');
        rename($target.'.tmp', $target);

        return $target;
    }
}
