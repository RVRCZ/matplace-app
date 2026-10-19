<?php

namespace App\Domain\Farm;

use App\Models\FarmColor;
use App\Models\FarmMaterial;

/**
 * One-colour print from a chosen spool position. The G-code is sliced once (tool 0, the order's base kind); the copy
 * that goes to the printer selects the slot the customer's colour sits in and carries that filament kind's
 * temperatures, so a model sliced as PLA prints right from a PLA+ or PETG spool. Verified on the Kobra S1 + ACE
 * under Rinkhals (22 Sep 2026): `T<n>` after the start macro switches the slot (macros t0…t3).
 */
final class GcodeSlot
{
    /** ;LAYER_CHANGE / ;Z:<top of the layer> / ;HEIGHT:<its thickness> */
    private const LAYER = '/^;LAYER_CHANGE[ \t]*\r?\n;Z:([\d.]+)[ \t]*\r?\n;HEIGHT:([\d.]+)[ \t]*\r?$/m';

    /**
     * The colours of a print as the machine swaps them: `T<n>` at the first layer that lies wholly above each change
     * height, bottom to top. It is the line OrcaSlicer itself writes for these machines on a filament change (their
     * change_filament_gcode is `T[next_extruder]`, the firmware does the cutting and the purging). Two changes that
     * fall into one layer: the upper one wins; a change to the slot already printing is not written.
     *
     * @param  list<array{slot:int, z:float}>  $changes
     */
    public static function colorChanges(string $gcode, array $changes): string
    {
        $changes = array_values(array_filter($changes, fn ($c) => is_array($c) && isset($c['slot'], $c['z']) && (float) $c['z'] > 0));
        if (! $changes) {
            return $gcode;
        }
        usort($changes, fn ($a, $b) => $a['z'] <=> $b['z']);
        // every layer: where its block ends in the file and the height of its middle
        $layers = [];
        $pos = 0;
        while (preg_match(self::LAYER, $gcode, $m, PREG_OFFSET_CAPTURE, $pos)) {
            $pos = $m[0][1] + strlen($m[0][0]);
            $layers[] = ['at' => $pos, 'mid' => (float) $m[1][0] - (float) $m[2][0] / 2];
        }
        if (! $layers) {
            return $gcode;
        }
        $at = [];                                           // layer index → slot; the upper change of one layer wins
        foreach ($changes as $c) {
            foreach ($layers as $i => $l) {
                // the slicer cuts a layer in its middle: the first layer whose middle is above the change is all new colour
                if ($l['mid'] >= (float) $c['z'] - 0.001) {
                    $at[$i] = (int) $c['slot'];
                    break;
                }
            }
        }
        ksort($at);
        $write = [];
        $current = self::startSlot($gcode);
        foreach ($at as $i => $slot) {
            if ($slot !== $current) {
                $write[$i] = $slot;
                $current = $slot;
            }
        }
        krsort($write);                                     // from the end of the file, so the earlier offsets stay valid
        foreach ($write as $i => $slot) {
            $gcode = self::insertAfterLayerChange($gcode, $layers[$i]['at'], 'T'.$slot.' ; colour change by matplace farm');
        }

        return $gcode;
    }

    /**
     * The second colour of a plate with a raised text: `T<n>` at the first layer that lies wholly above the plate.
     *
     * @param  array{slot:int, z:float}  $change
     */
    public static function secondColor(string $gcode, array $change): string
    {
        return self::colorChanges($gcode, [$change]);
    }

    /** The slot the print starts in: the tool line retarget() wrote (or the slicer's own), else 0. */
    private static function startSlot(string $gcode): ?int
    {
        return preg_match('/^T(\d)[ \t]*(?:;.*)?$/m', $gcode, $m) ? (int) $m[1] : null;
    }

    /** A line after the slicer's own layer-change block, when it is there, so the nozzle is already at the new height. */
    private static function insertAfterLayerChange(string $gcode, int $found, string $line): string
    {
        if (preg_match('/\G(?:(?!^;LAYER_CHANGE|^;TYPE:).*\r?\n){0,120}?^; AFTER_LAYER_CHANGE.*\r?\n/m', $gcode, $a, 0, $found + 1)) {
            $found = $found + 1 + strlen($a[0]);

            return substr($gcode, 0, $found).$line."\n".substr($gcode, $found);
        }

        return substr($gcode, 0, $found)."\n".$line.substr($gcode, $found);
    }

    /** @param  array{nozzle?: int, nozzle_first?: int, bed?: int}  $temps  degrees of the chosen kind; empty = keep the sliced ones */
    public static function retarget(string $gcode, int $slot, array $temps = []): string
    {
        // written for slot 1 (T0) as well: a file without any tool line is refused by the firmware of the S1 + ACE
        // ("cannot parse the file", 23 Sep 2026, order F26-000002)
        // The nozzle is heated and waited for before the tool line: when the ACE really has to change the spool (another
        // slot was loaded last), its macro refuses a cold nozzle ("nozzle must be hot first", U #2, 12 Oct 2026; the
        // start timeouts of 11 Oct were the same thing). A change in the middle of a print never saw it - the nozzle is hot.
        $hot = (int) (($temps['nozzle_first'] ?? 0) ?: (($temps['nozzle'] ?? 0) ? $temps['nozzle'] + 5 : 0));
        if ($hot < 100 && preg_match('/^G9111 bedTemp=\d+ extruderTemp=(\d+)/m', $gcode, $g)) {
            $hot = (int) $g[1];
        }
        $heat = $hot >= 100 ? 'M104 S'.$hot." ; matplace slot: heat before the spool change\nM109 S".$hot." ; matplace slot: wait for it\n" : '';
        $line = $heat.'T'.$slot.' ; slot chosen by matplace farm';
        $out = preg_replace('/^T0[ \t]*(;.*)?$/m', $line, $gcode, 1, $n);
        if ($n === 0) {
            // OrcaSlicer writes no tool line at all for a single-filament print: put one right after the start macro
            $out = preg_replace('/^(M117[ \t]*\r?\n)/m', '$1'.$line."\n", $out, 1, $n);
        }
        $out = $n > 0 ? (string) $out : $gcode;
        if (! empty($temps['nozzle'])) {
            $first = (int) (($temps['nozzle_first'] ?? 0) ?: $temps['nozzle'] + 5);
            $bed = (int) ($temps['bed'] ?? 0);
            // the start macro heats to the first-layer values, then the slicer sets the printing values per layer
            $out = (string) preg_replace_callback('/^G9111 bedTemp=(\d+) extruderTemp=(\d+)/m',
                fn ($m) => 'G9111 bedTemp='.($bed ?: $m[1]).' extruderTemp='.$first, $out, 1);
            // every nozzle temperature except the per-floor lines of a temperature tower (TowerGcode) and the heating
            // before the spool change above
            $out = (string) preg_replace('/^(M10[49]) S(?!0\b)\d+(?=\s*(?:;(?! matplace (?:tower|slot))|$))/m', '$1 S'.(int) $temps['nozzle'], $out);
            if ($bed) {
                $out = (string) preg_replace('/^(M1[49]0) S(?!0\b)\d+(?=\s*(?:;|$))/m', '$1 S'.$bed, $out);
            }
        }

        return $out;
    }

    /** The header line that tells the agent of the Kobra X which slot every filament of a plate comes from. */
    public const FILAMENTS_LINE = '; matplace filaments';

    /**
     * A plate of several filaments: the slicer wrote `T0`…`T<n-1>` for the filaments of the project (one per part, the
     * prime tower between them); the copy for the printer maps every one to the slot its spool sits in, says so in the
     * header (`; matplace filaments T0=2 T1=5`, within the first 16 kB, 0-based slots, every T<n> of the file listed –
     * the Kobra X agent turns it into ams_box_mapping, Rinkhals takes the T lines as they are), heats the nozzle
     * before the first spool is selected (as retarget does), and writes each filament's own temperatures where the
     * slicer set them for that filament, when the spools' kinds say so. The slicer's own CONFIG lists
     * (`filament_colour`, `filament_type`, `filament used [g]`) stay as written: the agent reads them.
     *
     * @param  array<int, int>  $slots  filament index (T0 = 0) → slot
     * @param  array<int, array{nozzle?: int, nozzle_first?: int, bed?: int}>  $temps  by filament index; empty = keep the sliced ones
     */
    public static function retargetMany(string $gcode, array $slots, array $temps = []): string
    {
        ksort($slots);
        if (! $slots) {
            return $gcode;
        }
        $header = self::FILAMENTS_LINE.' '.implode(' ', array_map(fn ($n, $slot) => 'T'.$n.'='.(int) $slot, array_keys($slots), $slots));
        $first = $temps[0] ?? [];
        $hot = (int) (($first['nozzle_first'] ?? 0) ?: (($first['nozzle'] ?? 0) ? $first['nozzle'] + 5 : 0));
        if ($hot < 100 && preg_match('/^G9111 bedTemp=\d+ extruderTemp=(\d+)/m', $gcode, $g)) {
            $hot = (int) $g[1];
        }
        $heat = $hot >= 100 ? 'M104 S'.$hot." ; matplace slot: heat before the spool change\nM109 S".$hot." ; matplace slot: wait for it\n" : '';

        $out = [];
        $current = 0;        // the filament printing now: its temperatures apply to the M104/M109 lines that follow
        $toolSeen = false;
        foreach (preg_split('/(?<=\n)/', $gcode) as $line) {
            $bare = rtrim($line, "\r\n");
            if (preg_match('/^T(\d)[ \t]*(?:;.*)?$/', $bare, $m)) {
                $n = (int) $m[1];
                $slot = $slots[$n] ?? $slots[0];
                $out[] = ($toolSeen ? '' : $heat).'T'.$slot.' ; matplace slot for filament '.$n."\n";
                $toolSeen = true;
                $current = $n;

                continue;
            }
            $t = $temps[$current] ?? [];
            if ($t && ! empty($t['nozzle']) && preg_match('/^(M10[49]) S(?!0\b)\d+(?=\s*(?:;(?! matplace (?:tower|slot))|$))/', $bare) && ! str_contains($bare, '; matplace slot')) {
                $out[] = preg_replace('/^(M10[49]) S\d+/', '$1 S'.(int) $t['nozzle'], $bare)."\n";

                continue;
            }
            $out[] = $line;
        }
        $gcode = implode('', $out);
        if (! $toolSeen) {
            // no tool line at all (a one-filament file): the first filament's slot right after the start macro
            $gcode = (string) preg_replace('/^(M117[ \t]*\r?\n)/m', '$1'.$heat.'T'.$slots[0]." ; matplace slot for filament 0\n", $gcode, 1);
        }
        if ($first && ! empty($first['nozzle'])) {
            $firstLayer = (int) (($first['nozzle_first'] ?? 0) ?: $first['nozzle'] + 5);
            $bed = (int) ($first['bed'] ?? 0);
            $gcode = (string) preg_replace_callback('/^G9111 bedTemp=(\d+) extruderTemp=(\d+)/m', fn ($m) => 'G9111 bedTemp='.($bed ?: $m[1]).' extruderTemp='.$firstLayer, $gcode, 1);
            if ($bed) {
                $gcode = (string) preg_replace('/^(M1[49]0) S(?!0\b)\d+(?=\s*(?:;|$))/m', '$1 S'.$bed, $gcode);
            }
        }
        // the map in the header: after the first line of the file, so a file with a long CONFIG block still carries it up front
        $cut = strpos($gcode, "\n");

        return $cut === false ? $header."\n".$gcode : substr($gcode, 0, $cut + 1).$header."\n".substr($gcode, $cut + 1);
    }

    /**
     * The retargeted copy of a plate of several filaments, next to the original (reused while the original is older).
     *
     * @param  array<int, int>  $slots  filament index → slot
     * @param  array<int, array{nozzle?: int, nozzle_first?: int, bed?: int}>  $temps  by filament index
     */
    public static function fileForMany(string $gcodePath, array $slots, array $temps = []): string
    {
        ksort($slots);
        $tag = '.hot.multi'.implode('_', array_map(fn ($n, $s) => $n.'to'.(int) $s, array_keys($slots), $slots))
            .($temps ? '-'.substr(sha1(json_encode($temps)), 0, 8) : '');
        $target = preg_replace('/\.gcode$/', '', $gcodePath).$tag.'.gcode';
        if (! is_file($target) || filemtime($target) < filemtime($gcodePath)) {
            file_put_contents($target, self::retargetMany((string) file_get_contents($gcodePath), $slots, $temps));
        }

        return $target;
    }

    /** @return array{nozzle?: int, nozzle_first?: int, bed?: int} the spool's own temperatures, else its kind's */
    public static function tempsOf(FarmColor|FarmMaterial|null $of): array
    {
        if ($of instanceof FarmColor) {
            return $of->temps();
        }

        return $of && $of->nozzle_temp ? ['nozzle' => (int) $of->nozzle_temp, 'nozzle_first' => (int) $of->nozzle_temp_first, 'bed' => (int) $of->bed_temp] : [];
    }

    /**
     * Writes the retargeted copy next to the original and returns its path (the original when nothing changes).
     *
     * @param  array{slot:int, z:float}|list<array{slot:int, z:float}>|null  $changes  one change (older callers) or the list of them
     */
    public static function fileFor(string $gcodePath, int $slot, array $temps = [], ?array $changes = null): string
    {
        $changes = $changes === null ? [] : (isset($changes['slot']) ? [$changes] : array_values($changes));
        // "hot": copies made before the heating-before-the-spool-change (12 Oct 2026) are not reused
        $tag = '.hot.slot'.$slot.(! empty($temps['nozzle']) ? '-'.$temps['nozzle'].'-'.(int) ($temps['bed'] ?? 0) : '')
            .($changes ? '.then'.implode('_', array_map(fn ($c) => $c['slot'].'at'.str_replace('.', '_', (string) $c['z']), $changes)) : '');
        $target = preg_replace('/\.gcode$/', '', $gcodePath).$tag.'.gcode';
        if (! is_file($target) || filemtime($target) < filemtime($gcodePath)) {
            $gcode = self::retarget((string) file_get_contents($gcodePath), $slot, $temps);
            file_put_contents($target, $changes ? self::colorChanges($gcode, $changes) : $gcode);
        }

        return $target;
    }
}
