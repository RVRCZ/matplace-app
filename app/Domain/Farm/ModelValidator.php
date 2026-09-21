<?php

namespace App\Domain\Farm;

use App\Engines\DTO\Dimensions;
use App\Engines\DTO\PreparedMesh;

/**
 * Farm rules for an uploaded model. Pure: the numbers come in, a verdict comes out.
 *
 *   errors    stop the order (code + data for a sentence the customer understands)
 *   warnings  are shown and the order goes on
 *   units     what the numbers most likely mean when they cannot be millimetres
 */
final class ModelValidator
{
    /** Multipliers the customer may pick: the file's numbers × this = millimetres. */
    public const UNITS = ['mm' => 1.0, 'cm' => 10.0, 'in' => 25.4, 'm' => 1000.0];

    /**
     * Guess from the raw size (as stored in the file) before anything is sliced.
     * An STL has no units; prints are rarely under 2 mm, so such numbers are almost surely metres or inches.
     *
     * @return array{unit: string, confident: bool}
     */
    public static function guessUnit(Dimensions $raw, Dimensions $bed): array
    {
        $max = $raw->max();
        if ($max <= 0) {
            return ['unit' => 'mm', 'confident' => false];
        }
        if ($max < 0.6) {
            return ['unit' => 'm', 'confident' => true];        // 0.12 → a 120 mm part drawn in metres
        }
        if ($max < 2.0) {
            // 1.5 could be 1.5 m (too big for any plate) or 1.5 in (38 mm): inches fit, metres do not
            return ['unit' => $raw->scaled(1000)->fits($bed->x, $bed->y, $bed->z) ? 'm' : 'in', 'confident' => true];
        }
        if ($max < 10.0) {
            return ['unit' => 'in', 'confident' => false];      // a small part in mm is possible too: ask
        }

        return ['unit' => 'mm', 'confident' => true];
    }

    /**
     * @param  array{min_model_mm: float, bed_margin_mm: float}  $rules
     * @return array{ok: bool, errors: array<int, array{code: string, data: array}>, warnings: array<int, array{code: string, data: array}>, dims: array}
     */
    public static function judge(PreparedMesh $mesh, Dimensions $bed, array $rules): array
    {
        $errors = $warnings = [];
        $d = $mesh->bbox;
        $margin = 2 * (float) $rules['bed_margin_mm'];
        $usable = new Dimensions($bed->x - $margin, $bed->y - $margin, $bed->z);

        if ($mesh->triangles < 4 || $mesh->volumeMm3 <= 0 || $d->max() <= 0) {
            $errors[] = ['code' => 'invalid_mesh', 'data' => []];
        } else {
            if ($d->max() < (float) $rules['min_model_mm']) {
                $errors[] = ['code' => 'too_small', 'data' => ['size' => round($d->max(), 2), 'min' => (float) $rules['min_model_mm']]];
            }
            // the preparer already turned the model; here it must fit as it stands (Z is the height)
            if ($d->z > $usable->z || ! self::fitsPlate($d, $usable)) {
                $errors[] = ['code' => 'exceeds_bed', 'data' => ['x' => round($d->x, 1), 'y' => round($d->y, 1), 'z' => round($d->z, 1), 'bed_x' => $usable->x, 'bed_y' => $usable->y, 'bed_z' => $usable->z]];
            }
            if ($mesh->watertight === false) {
                $errors[] = ['code' => 'not_watertight', 'data' => ['open_edges' => $mesh->openEdges]];
            }
        }

        if ($mesh->repaired) {
            $warnings[] = ['code' => 'repaired', 'data' => []];
        }
        if ($mesh->watertight === null) {
            $warnings[] = ['code' => 'unchecked_topology', 'data' => []];
        }
        if ($mesh->shells > 1) {
            $warnings[] = ['code' => 'multiple_shells', 'data' => ['shells' => $mesh->shells]];
        }
        if (! $errors && min($d->x, $d->y) < 1.2) {
            $warnings[] = ['code' => 'very_thin', 'data' => ['size' => round(min($d->x, $d->y), 2)]];
        }

        return ['ok' => ! $errors, 'errors' => $errors, 'warnings' => $warnings, 'dims' => $d->toArray()];
    }

    private static function fitsPlate(Dimensions $d, Dimensions $usable): bool
    {
        return ($d->x <= $usable->x && $d->y <= $usable->y) || ($d->y <= $usable->x && $d->x <= $usable->y);
    }
}
