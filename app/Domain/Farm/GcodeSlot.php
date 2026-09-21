<?php

namespace App\Domain\Farm;

/**
 * One-colour print from a chosen spool position. The G-code is sliced once for tool 0; the copy that goes to the
 * printer selects the slot the customer's colour sits in. Only the stand-alone tool line is changed ("T0" → "T2"),
 * exactly the line Anycubic Slicer Next writes after the start macro.
 *
 * TO VERIFY ON THE MACHINE: that the Kobra S1 + ACE under Rinkhals honours T<n> for a print started through
 * Moonraker. Until that is confirmed the farm seeder enables slot 0 only.
 */
final class GcodeSlot
{
    public static function retarget(string $gcode, int $slot): string
    {
        if ($slot === 0) {
            return $gcode;
        }

        return (string) preg_replace('/^T0[ \t]*(;.*)?$/m', 'T'.$slot.' ; slot chosen by matplace farm', $gcode);
    }

    /** Writes the retargeted copy next to the original and returns its path (the original when nothing changes). */
    public static function fileFor(string $gcodePath, int $slot): string
    {
        if ($slot === 0) {
            return $gcodePath;
        }
        $target = preg_replace('/\.gcode$/', '', $gcodePath).'.slot'.$slot.'.gcode';
        if (! is_file($target) || filemtime($target) < filemtime($gcodePath)) {
            file_put_contents($target, self::retarget((string) file_get_contents($gcodePath), $slot));
        }

        return $target;
    }
}
