<?php

namespace App\Domain\Farm;

use App\Engines\DTO\Dimensions;
use App\Engines\DTO\PreparedMesh;
use App\Engines\Mesh\StlFile;

/**
 * Several copies of one prepared piece on one plate: a grid with a gap between the pieces, turned 90° when that
 * fits more. The calculator promises "N pieces fit on the plate" with the same rule (calculator.ts piecesOnBed),
 * so what the customer was told is what the farm prints.
 */
final class PlateLayout
{
    public const GAP_MM = 5.0;

    public const MAX_COPIES = 64;

    /**
     * How many copies of a piece fit the usable plate, and in which grid.
     *
     * @return array{max: int, cols: int, rows: int, rotated: bool}
     */
    public static function capacity(Dimensions $piece, Dimensions $usable, float $gap = self::GAP_MM): array
    {
        if ($piece->x <= 0 || $piece->y <= 0 || $piece->z > $usable->z) {
            return ['max' => 0, 'cols' => 0, 'rows' => 0, 'rotated' => false];
        }
        $along = fn (float $size, float $room) => (int) floor(($room + $gap) / ($size + $gap));
        $plain = ['cols' => $along($piece->x, $usable->x), 'rows' => $along($piece->y, $usable->y), 'rotated' => false];
        $turned = ['cols' => $along($piece->y, $usable->x), 'rows' => $along($piece->x, $usable->y), 'rotated' => true];
        $best = $plain['cols'] * $plain['rows'] >= $turned['cols'] * $turned['rows'] ? $plain : $turned;

        return ['max' => $best['cols'] * $best['rows']] + $best;
    }

    /**
     * Writes the piece `copies` times into a new binary STL: the grid that fits (plain first, turned when that is the
     * only way), the whole set moved to start at the origin. Returns the plate as a prepared mesh with the same
     * checks as the piece, or null when the copies do not fit.
     */
    public static function replicate(PreparedMesh $piece, string $outPath, int $copies, Dimensions $usable, float $gap = self::GAP_MM): ?PreparedMesh
    {
        $d = $piece->bbox;
        $plain = self::capacity($d, $usable, $gap);
        // the piece as prepared (its orientation was chosen for printing) unless only a turned grid takes all copies
        $layout = ['cols' => 0, 'rows' => 0, 'rotated' => false];
        foreach ([['cols' => (int) floor(($usable->x + $gap) / ($d->x + $gap)), 'rows' => (int) floor(($usable->y + $gap) / ($d->y + $gap)), 'rotated' => false],
            ['cols' => (int) floor(($usable->x + $gap) / ($d->y + $gap)), 'rows' => (int) floor(($usable->y + $gap) / ($d->x + $gap)), 'rotated' => true]] as $try) {
            if ($copies <= $try['cols'] * $try['rows']) {
                $layout = $try;
                break;
            }
        }
        if ($layout['cols'] === 0 || $plain['max'] < $copies) {
            return null;
        }
        // as square a grid as the plate allows: fewer long rows, the set sits in the middle of the plate
        $cols = min($layout['cols'], max(1, (int) ceil(sqrt($copies))));
        while ($cols < $layout['cols'] && (int) ceil($copies / $cols) > $layout['rows']) {
            $cols++;
        }
        $rows = (int) ceil($copies / $cols);

        // the piece's own footprint (min corner) so every copy starts at its cell's origin
        $min = [INF, INF, INF];
        foreach (StlFile::triangles($piece->path) as $tri) {
            foreach ($tri as $p) {
                for ($i = 0; $i < 3; $i++) {
                    $min[$i] = min($min[$i], $p[$i]);
                }
            }
        }
        $stepX = ($layout['rotated'] ? $d->y : $d->x) + $gap;
        $stepY = ($layout['rotated'] ? $d->x : $d->y) + $gap;
        $fh = StlFile::beginBinary($outPath);
        $n = 0;
        $placed = 0;
        for ($r = 0; $r < $rows && $placed < $copies; $r++) {
            for ($c = 0; $c < $cols && $placed < $copies; $c++, $placed++) {
                $ox = $c * $stepX;
                $oy = $r * $stepY;
                foreach (StlFile::triangles($piece->path) as $tri) {
                    $out = [];
                    foreach ($tri as $p) {
                        $x = $p[0] - $min[0];
                        $y = $p[1] - $min[1];
                        $z = $p[2] - $min[2];
                        // a quarter turn about Z: (x, y) → (y_max - y, x)
                        $out[] = $layout['rotated'] ? [$ox + ($d->y - $y), $oy + $x, $z] : [$ox + $x, $oy + $y, $z];
                    }
                    StlFile::writeTriangle($fh, $out[0], $out[1], $out[2]);
                    $n++;
                }
            }
        }
        StlFile::endBinary($fh, $n);

        $plate = new Dimensions($cols * $stepX - $gap, $rows * $stepY - $gap, $d->z);

        return new PreparedMesh(
            path: $outPath,
            watertight: $piece->watertight,
            wasWatertight: $piece->wasWatertight,
            repaired: $piece->repaired,
            openEdges: $piece->openEdges * $copies,
            bbox: $plate,
            volumeMm3: $piece->volumeMm3 * $copies,
            areaMm2: $piece->areaMm2 * $copies,
            triangles: $n,
            shells: $piece->shells * $copies,
            orientation: $piece->orientation + ['copies' => $copies, 'grid' => [$cols, $rows], 'grid_rotated' => $layout['rotated']],
            engine: $piece->engine,
        );
    }
}
