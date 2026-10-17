<?php

/**
 * Pictures of how copies of a piece are laid on a plate: the grid by the rectangle (as before) next to the rows by the
 * shadow (PlateLayout::plan). For docs/AE.md, and to look at a layout when a customer's piece seems laid oddly.
 *
 *   php scripts/plate_layout_png.php [out dir]                 the four test shapes on the S1 and the Max
 *   php scripts/plate_layout_png.php out dir path/to/piece.stl  a prepared piece (as it lies: its shadow is read from the file)
 */

require __DIR__.'/../vendor/autoload.php';

use App\Domain\Farm\Footprint;
use App\Domain\Farm\PlateLayout;
use App\Engines\DTO\Dimensions;

$out = $argv[1] ?? __DIR__.'/../docs/img/plate-layout';
@mkdir($out, 0777, true);
$plates = ['s1' => 246.0, 'max' => 416.0];   // usable: the plate minus the farm's 2 mm margin on each side

$circle = [];
for ($k = 0; $k < 96; $k++) {
    $circle[] = [25 + 25 * cos(2 * M_PI * $k / 96), 25 + 25 * sin(2 * M_PI * $k / 96)];
}
$shapes = isset($argv[2])
    ? [pathinfo($argv[2], PATHINFO_FILENAME) => Footprint::fromStl($argv[2])?->points ?? throw new RuntimeException('no shadow in '.$argv[2])]
    : [
        'triangle' => [[0, 0], [40, 0], [20, 60]],
        'ell' => [[0, 0], [60, 0], [60, 20], [20, 20], [20, 60], [0, 60]],
        'circle' => $circle,
        'rect' => [[0, 0], [30, 0], [30, 80], [0, 80]],
    ];

foreach ($plates as $plateName => $side) {
    $usable = new Dimensions($side, $side, 250);
    foreach ($shapes as $name => $pts) {
        $shadow = Footprint::fromPoints($pts);
        [$x0, $y0, $x1, $y1] = $shadow->bounds();
        $d = new Dimensions($x1 - $x0, $y1 - $y0, 3);
        $grid = PlateLayout::capacity($d, $usable);
        $plan = PlateLayout::plan($shadow, $d, $usable);
        $pieces = [];
        // the grid as PlateLayout::replicateGrid lays it: the piece's own corner at every cell
        $stepX = ($grid['rotated'] ? $d->y : $d->x) + PlateLayout::GAP_MM;
        $stepY = ($grid['rotated'] ? $d->x : $d->y) + PlateLayout::GAP_MM;
        $local = Footprint::moved($shadow->points, -$x0, -$y0);
        for ($r = 0; $r < $grid['rows']; $r++) {
            for ($c = 0; $c < $grid['cols']; $c++) {
                $p = $grid['rotated'] ? Footprint::moved(Footprint::turned($local, 1), $d->y, 0) : $local;
                $pieces['grid'][] = Footprint::moved($p, $c * $stepX, $r * $stepY);
            }
        }
        foreach ($plan['method'] === 'rows' ? $plan['placements'] : [] as [$turn, $tx, $ty]) {
            $pieces['rows'][] = Footprint::moved(Footprint::turned($shadow->points, $turn), $tx, $ty);
        }

        $scale = 1.2;
        $pad = 24;
        $w = (int) ($side * $scale);
        $img = imagecreatetruecolor(2 * $w + 3 * $pad, $w + 2 * $pad + 28);
        $white = imagecolorallocate($img, 255, 255, 255);
        $plateBg = imagecolorallocate($img, 240, 242, 245);
        $edge = imagecolorallocate($img, 170, 175, 185);
        $ink = imagecolorallocate($img, 40, 44, 52);
        $fillA = imagecolorallocate($img, 200, 212, 235);
        $fillB = imagecolorallocate($img, 170, 205, 190);
        imagefill($img, 0, 0, $white);
        $panels = ['grid' => sprintf('dnes: mrizka %d ks', $grid['max']), 'rows' => $plan['method'] === 'rows' ? sprintf('nove: rady %d ks', $plan['max']) : sprintf('nove: zustava mrizka %d ks', $plan['max'])];
        $i = 0;
        foreach ($panels as $key => $label) {
            $ox = $pad + $i * ($w + $pad);
            $oy = $pad + 28;
            imagefilledrectangle($img, $ox, $oy, $ox + $w, $oy + $w, $plateBg);
            imagerectangle($img, $ox, $oy, $ox + $w, $oy + $w, $edge);
            foreach ($pieces[$key] ?? ($key === 'rows' ? $pieces['grid'] : []) as $n => $poly) {
                $flat = [];
                foreach ($poly as [$x, $y]) {
                    $flat[] = $ox + $x * $scale;
                    $flat[] = $oy + $w - $y * $scale;   // y up, as on the plate seen from above
                }
                imagefilledpolygon($img, $flat, $n % 2 ? $fillB : $fillA);
                imagepolygon($img, $flat, $ink);
            }
            imagestring($img, 3, $ox, $pad, $label, $ink);
            $i++;
        }
        imagestring($img, 2, $pad, 6, sprintf('%s, %s %.0f x %.0f mm, kus %.0f x %.0f mm, mezera %.0f mm', $name, strtoupper($plateName), $side, $side, $d->x, $d->y, PlateLayout::GAP_MM), $ink);
        $file = $out.'/'.$name.'-'.$plateName.'.png';
        imagepng($img, $file);
        imagedestroy($img);
        printf("%-10s %-4s grid %3d  new %3d (%s)  -> %s\n", $name, $plateName, $grid['max'], $plan['max'], $plan['method'], $file);
    }
}
