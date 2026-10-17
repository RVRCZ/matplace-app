<?php

namespace Tests\Support;

use App\Engines\Mesh\StlFile;

/**
 * The QR code off a rendered sign: its raised modules read from the top faces of the STL (the code is the only thing
 * at the top height when the sign has no caption), written as a picture a reader can scan. The sign may lie anywhere:
 * the first dark module sits four modules (the quiet zone) from the code's corner, so the corner is found from the
 * modules themselves.
 */
final class QrRaster
{
    /**
     * @return list<list<bool>> rows from the top, as segno's matrix: true = dark module
     */
    public static function modules(string $stlPath, float $module, int $cells): array
    {
        $tops = [];
        $top = -INF;
        foreach (StlFile::triangles($stlPath) as $t) {
            $z = max($t[0][2], $t[1][2], $t[2][2]);
            if ($z > $top + 1e-3) {
                $top = $z;
                $tops = [];
            }
            if (abs($t[0][2] - $top) < 1e-3 && abs($t[1][2] - $top) < 1e-3 && abs($t[2][2] - $top) < 1e-3) {
                $tops[] = [min($t[0][0], $t[1][0], $t[2][0]), min($t[0][1], $t[1][1], $t[2][1]), max($t[0][0], $t[1][0], $t[2][0]), max($t[0][1], $t[1][1], $t[2][1])];
            }
        }
        $ox = min(array_column($tops, 0)) - 4 * $module;
        $oy = min(array_column($tops, 1)) - 4 * $module;
        $grid = array_fill(0, $cells, array_fill(0, $cells, false));
        foreach ($tops as [$x0, $y0, $x1, $y1]) {
            // every cell whose centre lies in the rectangle (the modules are axis-aligned rectangles, two triangles each)
            for ($i = max(0, (int) floor(($x0 - $ox) / $module)); $i < min($cells, (int) ceil(($x1 - $ox) / $module)); $i++) {
                for ($j = max(0, (int) floor(($y0 - $oy) / $module)); $j < min($cells, (int) ceil(($y1 - $oy) / $module)); $j++) {
                    $cx = $ox + ($i + 0.5) * $module;
                    $cy = $oy + ($j + 0.5) * $module;
                    if ($cx > $x0 - 1e-6 && $cx < $x1 + 1e-6 && $cy > $y0 - 1e-6 && $cy < $y1 + 1e-6) {
                        $grid[$cells - 1 - $j][$i] = true;
                    }
                }
            }
        }

        return $grid;
    }

    /** @param  list<list<bool>>  $grid */
    public static function png(array $grid, string $path, int $px = 8): void
    {
        $n = count($grid);
        $img = imagecreate($n * $px, $n * $px);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $white);
        foreach ($grid as $r => $row) {
            foreach ($row as $c => $dark) {
                if ($dark) {
                    imagefilledrectangle($img, $c * $px, $r * $px, ($c + 1) * $px - 1, ($r + 1) * $px - 1, $black);
                }
            }
        }
        imagepng($img, $path);
        imagedestroy($img);
    }
}
