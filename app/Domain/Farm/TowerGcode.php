<?php

namespace App\Domain\Farm;

/**
 * Temperature tower: the object is sliced at one temperature, then every floor gets its own `M104` written into the
 * G-code at the first layer of that floor. OrcaSlicer marks layers with `;LAYER_CHANGE` followed by `;Z:<height>`.
 * The inserted lines carry the marker `; matplace tower`, which GcodeSlot::retarget leaves alone.
 */
final class TowerGcode
{
    public const MARK = '; matplace tower';

    /**
     * @param  array<int, int>  $temps  degrees per floor, bottom first
     * @return array{gcode: string, floors_set: int}
     */
    public static function apply(string $gcode, float $floorHeightMm, array $temps): array
    {
        $temps = array_values(array_map('intval', $temps));
        if (count($temps) < 2 || $floorHeightMm <= 0) {
            return ['gcode' => $gcode, 'floors_set' => 0];
        }
        $current = 0;
        $set = 0;
        $out = preg_replace_callback('/^;Z:([0-9.]+)[ \t]*\r?$/m', function ($m) use (&$current, &$set, $temps, $floorHeightMm) {
            $z = (float) $m[1];
            $floor = min(count($temps) - 1, (int) floor(($z - 0.001) / $floorHeightMm));
            if ($floor <= $current) {
                return $m[0];
            }
            $current = $floor;
            $set++;

            return $m[0]."\nM104 S".$temps[$floor].' '.self::MARK.' floor '.($floor + 1);
        }, $gcode);

        return ['gcode' => (string) $out, 'floors_set' => $set];
    }

    /** Floor → temperature list from a centre and a step: 5 floors, 215 °C, step −5 → 225, 220, 215, 210, 205 bottom-up? No: bottom hottest. */
    public static function ladder(int $floors, int $start, int $step): array
    {
        $out = [];
        for ($i = 0; $i < $floors; $i++) {
            $out[] = $start + $i * $step;
        }

        return $out;
    }
}
