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
    /** @param  array{nozzle?: int, nozzle_first?: int, bed?: int}  $temps  degrees of the chosen kind; empty = keep the sliced ones */
    public static function retarget(string $gcode, int $slot, array $temps = []): string
    {
        // written for slot 1 (T0) as well: a file without any tool line is refused by the firmware of the S1 + ACE
        // ("cannot parse the file", 23 Sep 2026, order F26-000002)
        $line = 'T'.$slot.' ; slot chosen by matplace farm';
        $out = preg_replace('/^T0[ \t]*(;.*)?$/m', $line, $gcode, 1, $n);
        if ($n === 0) {
            // OrcaSlicer writes no tool line at all for a single-filament print: put one right after the start macro
            $out = preg_replace('/^(M117[ \t]*\r?\n)/m', '$1'.$line."\n", $out, 1, $n);
        }
        $out = $n > 0 ? (string) $out : $gcode;
        if (! empty($temps['nozzle'])) {
            $first = (int) ($temps['nozzle_first'] ?: $temps['nozzle'] + 5);
            $bed = (int) ($temps['bed'] ?? 0);
            // the start macro heats to the first-layer values, then the slicer sets the printing values per layer
            $out = (string) preg_replace_callback('/^G9111 bedTemp=(\d+) extruderTemp=(\d+)/m',
                fn ($m) => 'G9111 bedTemp='.($bed ?: $m[1]).' extruderTemp='.$first, $out, 1);
            $out = (string) preg_replace('/^(M10[49]) S(?!0\b)\d+(?=\s*(?:;|$))/m', '$1 S'.(int) $temps['nozzle'], $out);
            if ($bed) {
                $out = (string) preg_replace('/^(M1[49]0) S(?!0\b)\d+(?=\s*(?:;|$))/m', '$1 S'.$bed, $out);
            }
        }

        return $out;
    }

    /** @return array{nozzle?: int, nozzle_first?: int, bed?: int} the spool's own temperatures, else its kind's */
    public static function tempsOf(FarmColor|FarmMaterial|null $of): array
    {
        if ($of instanceof FarmColor) {
            return $of->temps();
        }

        return $of && $of->nozzle_temp ? ['nozzle' => (int) $of->nozzle_temp, 'nozzle_first' => (int) $of->nozzle_temp_first, 'bed' => (int) $of->bed_temp] : [];
    }

    /** Writes the retargeted copy next to the original and returns its path (the original when nothing changes). */
    public static function fileFor(string $gcodePath, int $slot, array $temps = []): string
    {
        $tag = '.slot'.$slot.(! empty($temps['nozzle']) ? '-'.$temps['nozzle'].'-'.(int) ($temps['bed'] ?? 0) : '');
        $target = preg_replace('/\.gcode$/', '', $gcodePath).$tag.'.gcode';
        if (! is_file($target) || filemtime($target) < filemtime($gcodePath)) {
            file_put_contents($target, self::retarget((string) file_get_contents($gcodePath), $slot, $temps));
        }

        return $target;
    }
}
