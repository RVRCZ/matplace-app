<?php

namespace App\Engines\Gcode;

/**
 * The support structures of a sliced print, pulled out of the G-code for the customer's 3D preview.
 *
 * Every extrusion move under an Orca `;TYPE:Support…` marker becomes one line segment. The file is little-endian
 * float32: a header of 8 numbers (version 1, segment count, model extrusion bbox min x, min y, max x, max y, 0, 0),
 * then count × (x1 y1 z1 x2 y2 z2) in printer coordinates. The bbox of the model's own extrusions lets the viewer
 * shift the lines onto the STL, wherever the slicer placed the part on the plate.
 */
final class SupportLines
{
    private const MAX_SEGMENTS = 60000;   // a preview, not a toolpath: thin long prints so the browser stays quick

    /**
     * @return array{segments:int, model:array{0:float,1:float,2:float,3:float}}|null null when the print has no supports
     */
    public static function extract(string $gcodePath, string $outPath): ?array
    {
        $fh = fopen($gcodePath, 'rb');
        if (! $fh) {
            return null;
        }
        $x = $y = $z = 0.0;
        $support = false;
        $type = false;
        $segments = [];
        $model = [INF, INF, -INF, -INF];
        while (($line = fgets($fh)) !== false) {
            if ($line[0] === ';') {
                if (str_starts_with($line, ';TYPE:')) {
                    // "Custom" is the printer's own G-code (purge line, tool change): neither the part nor a support
                    $type = ! str_starts_with($line, ';TYPE:Custom');
                    $support = str_starts_with($line, ';TYPE:Support');
                }

                continue;
            }
            if (! (str_starts_with($line, 'G1 ') || str_starts_with($line, 'G0 '))) {
                continue;
            }
            $px = $x;
            $py = $y;
            $pz = $z;
            $e = 0.0;
            foreach (explode(' ', rtrim($line)) as $i => $word) {
                if ($i === 0 || $word === '') {
                    continue;
                }
                $v = (float) substr($word, 1);
                match ($word[0]) {
                    'X' => $x = $v,
                    'Y' => $y = $v,
                    'Z' => $z = $v,
                    'E' => $e = $v,
                    default => null,
                };
            }
            if ($e <= 0 || ($x === $px && $y === $py)) {
                continue;   // travel, retraction or a pure Z move
            }
            if ($support) {
                $segments[] = pack('g6', $px, $py, $pz, $x, $y, $z);
            } elseif ($type) {
                $model = [min($model[0], $x), min($model[1], $y), max($model[2], $x), max($model[3], $y)];
            }
        }
        fclose($fh);
        if (! $segments || ! is_finite($model[0])) {
            return null;
        }
        $stride = max(1, (int) ceil(count($segments) / self::MAX_SEGMENTS));
        if ($stride > 1) {
            $segments = array_values(array_filter($segments, fn ($k) => $k % $stride === 0, ARRAY_FILTER_USE_KEY));
        }
        $n = count($segments);
        file_put_contents($outPath, pack('g8', 1, $n, $model[0], $model[1], $model[2], $model[3], 0, 0).implode('', $segments));

        return ['segments' => $n, 'model' => $model];
    }
}
