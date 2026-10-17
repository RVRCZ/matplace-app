<?php

namespace App\Domain\Farm;

use App\Engines\DTO\Dimensions;
use App\Engines\DTO\PreparedMesh;
use App\Engines\Mesh\StlFile;

/**
 * Several copies of one prepared piece on one plate. A piece whose shadow fills most of its rectangle (boxes,
 * stands) goes in a grid with a gap between the pieces, turned 90° when that fits more. A piece whose shadow leaves
 * much of the rectangle empty (a triangular sign, an L-shaped tag, a round charm) goes in rows by its shadow
 * (Footprint): every other piece turned 180° so slopes fit into each other, the rows slid together as far as the
 * shadows allow, the whole set tried in four quarter turns; never fewer pieces than the grid. The calculator asks
 * the same code (POST /api/calc/plate), so what the customer was told is what the farm prints.
 */
final class PlateLayout
{
    public const GAP_MM = 5.0;

    public const MAX_COPIES = 64;

    /** A shadow filling more than this share of its rectangle keeps the grid (the rows win too little to bother). */
    public const FILL_GRID = 0.85;

    /** How many earlier pieces of a row (and of a row below) a new piece is checked against while sliding. */
    private const REACH = 3;

    /**
     * How many copies of the prepared piece fit the usable plate, by its shadow when it has one.
     *
     * @return array{max: int, method: string}&array<string, mixed>
     */
    public static function capacityOf(PreparedMesh $piece, Dimensions $usable, float $gap = self::GAP_MM): array
    {
        return self::plan(Footprint::fromArray($piece->footprint), $piece->bbox, $usable, null, $gap);
    }

    /**
     * The layout for `copies` pieces (null: as many as fit). method "grid": cols × rows as capacity() says;
     * method "rows": placements [quarter turns, x, y] that move the piece's own coordinates onto the plate (the set
     * starting at the origin), size [width, depth] of the set.
     *
     * @return array{max: int, method: string}&array<string, mixed>
     */
    public static function plan(?Footprint $shadow, Dimensions $piece, Dimensions $usable, ?int $copies = null, float $gap = self::GAP_MM): array
    {
        $grid = ['method' => 'grid'] + self::capacity($piece, $usable, $gap);
        if ($shadow === null || $grid['max'] === 0 || $shadow->fill() > self::FILL_GRID) {
            return $grid;
        }
        $rows = self::rows($shadow, $usable, $gap, $copies);

        // never fewer than the grid: a layout that fits no more (or failed its own check) leaves the grid as it was
        return $rows !== null && $rows['max'] > $grid['max'] ? $rows : $grid;
    }

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
     * Writes the piece `copies` times into a new binary STL: in rows by its shadow when plan() says so, else the grid
     * that fits (plain first, turned when that is the only way); the whole set moved to start at the origin. Returns
     * the plate as a prepared mesh with the same checks as the piece, or null when the copies do not fit.
     */
    public static function replicate(PreparedMesh $piece, string $outPath, int $copies, Dimensions $usable, float $gap = self::GAP_MM): ?PreparedMesh
    {
        $plan = self::plan(Footprint::fromArray($piece->footprint), $piece->bbox, $usable, $copies, $gap);
        if ($plan['method'] === 'rows') {
            return $plan['max'] >= $copies ? self::writeRows($piece, $outPath, $plan) : null;
        }

        return self::replicateGrid($piece, $outPath, $copies, $usable, $gap);
    }

    /** The grid, unchanged since before the rows (StlFileBlocksTest holds it to the same bytes). */
    private static function replicateGrid(PreparedMesh $piece, string $outPath, int $copies, Dimensions $usable, float $gap): ?PreparedMesh
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
        [$min] = StlFile::bounds($piece->path);
        $stepX = ($layout['rotated'] ? $d->y : $d->x) + $gap;
        $stepY = ($layout['rotated'] ? $d->x : $d->y) + $gap;
        $rotated = (bool) $layout['rotated'];
        $dy = $d->y;
        [$mx, $my, $mz] = $min;
        $fh = StlFile::beginBinary($outPath);
        $n = 0;
        $placed = 0;
        for ($r = 0; $r < $rows && $placed < $copies; $r++) {
            for ($c = 0; $c < $cols && $placed < $copies; $c++, $placed++) {
                $ox = $c * $stepX;
                $oy = $r * $stepY;
                // a block of triangles at a time, one pack per triangle (the copies of a big model took minutes)
                foreach (StlFile::records($piece->path) as $block) {
                    $out = '';
                    foreach ($block as $v) {
                        $t = [];
                        for ($k = 4; $k <= 12; $k += 3) {
                            $x = $v[$k] - $mx;
                            $y = $v[$k + 1] - $my;
                            $z = $v[$k + 2] - $mz;
                            // a quarter turn about Z: (x, y) → (y_max - y, x)
                            $t[] = $rotated ? [$ox + ($dy - $y), $oy + $x, $z] : [$ox + $x, $oy + $y, $z];
                        }
                        // the normal of the copy as it lies (a turned copy has a turned normal); the slicer tells
                        // binary from ASCII by these bytes (StlFile::normal)
                        $out .= pack('f12v', ...array_merge(StlFile::normal($t[0], $t[1], $t[2]), $t[0], $t[1], $t[2], [0]));
                    }
                    fwrite($fh, $out);
                    $n += count($block);
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

    /**
     * Rows by the shadow grown by half the gap (two grown shadows may touch, the pieces are then a gap apart): every
     * variant of a quarter turn of the set × pieces all alike or every other one turned 180° × the odd rows alike,
     * turned or shifted by half a step; the one holding most, and for `copies` the most compact one that holds
     * them. Null when no variant fits or the chosen one fails the overlap check.
     *
     * @return array<string, mixed>|null
     */
    private static function rows(Footprint $shadow, Dimensions $usable, float $gap, ?int $copies): ?array
    {
        $grown = Footprint::grown($shadow->points, $gap / 2);
        // grown shadows fill a room one gap larger than the usable plate (half a gap on every side)
        $roomX = $usable->x + $gap;
        $roomY = $usable->y + $gap;
        $variants = [];
        foreach ([0, 1, 2, 3] as $q) {
            $types = [];
            $mins = [];
            foreach ([0, 1] as $t) {
                $poly = Footprint::turned($grown, $q + 2 * $t);
                [$x0, $y0] = Footprint::box($poly);
                $types[$t] = Footprint::moved($poly, -$x0, -$y0);
                $mins[$t] = [$x0, $y0];
            }
            [, , $w, $h] = Footprint::box($types[0]);
            if ($w > $roomX + 1e-9 || $h > $roomY + 1e-9) {
                continue;
            }
            // piece t at p_t overlaps piece s at p_s exactly when p_t - p_s lies inside diff[s][t]
            $diff = [];
            foreach ([0, 1] as $s) {
                foreach ([0, 1] as $t) {
                    $diff[$s][$t] = Footprint::minkowski($types[$s], Footprint::turned($types[$t], 2));
                }
            }
            foreach ([false, true] as $alt) {
                $even = self::row($diff, 0, $alt, 0.0, $w, $roomX);
                if ($even['x'] === []) {
                    continue;
                }
                // the odd rows: alike or turned 180°, moved by eighths of the row's repeat (half a step = circles in
                // a honeycomb; other eighths let a turned row's slopes fall into the gaps of the row below)
                $count = count($even['x']);
                $repeat = $alt ? ($count > 2 ? $even['x'][2] - $even['x'][0] : 2 * $w) : ($count > 1 ? $even['x'][1] - $even['x'][0] : $w);
                foreach ([0, 1] as $start) {
                    for ($e = 0; $e < 8; $e++) {
                        $odd = $start === 0 && $e === 0 ? $even : self::row($diff, $start, $alt, $repeat * $e / 8, $w, $roomX);
                        if ($odd['x'] !== []) {
                            $variants[] = ['q' => $q, 'alt' => $alt, 'odd' => ($start ? 'turned' : 'same').($e ? ' +'.$e.'/8' : ''), 'kinds' => [$even, $odd], 'diff' => $diff, 'w' => $w, 'h' => $h, 'mins' => $mins];
                        }
                    }
                }
            }
        }
        if ($variants === []) {
            return null;
        }

        // the most pieces: every row as long as the plate allows; ties keep the earlier (simpler) variant
        $max = 0;
        $best = null;
        foreach ($variants as $i => $v) {
            $stack = self::stack($v, PHP_INT_MAX, $roomY);
            if ($stack['count'] > $max) {
                [$max, $best] = [$stack['count'], [$i, PHP_INT_MAX, $stack]];
            }
        }
        $want = $copies === null ? $max : min($copies, $max);
        if ($copies !== null && $copies <= $max) {
            // fewer pieces than fit: shorter rows until the set is as square as it gets (as the grid does)
            $score = null;
            foreach ($variants as $i => $v) {
                for ($k = 1; $k <= count($v['kinds'][0]['x']); $k++) {
                    $stack = self::stack($v, $k, $roomY, $copies);
                    if ($stack['count'] < $copies) {
                        continue;
                    }
                    $s = [max($stack['width'], $stack['height']), $stack['width'] * $stack['height']];
                    if ($score === null || $s < $score) {
                        [$score, $best] = [$s, [$i, $k, $stack]];
                    }
                }
            }
        }
        [$i, $k, $stack] = $best;
        $v = $variants[$i];

        // the pieces in row order, the first `want` of them, as turns and moves of the piece's own coordinates
        $placed = [];
        foreach ($stack['rows'] as [$kind, $y]) {
            $row = $v['kinds'][$kind];
            foreach (array_slice($row['x'], 0, $k) as $j => $x) {
                if (count($placed) === $want) {
                    break 2;
                }
                $t = $row['t'][$j];
                $placed[] = [($v['q'] + 2 * $t) % 4, $x - $v['mins'][$t][0], $y - $v['mins'][$t][1]];
            }
        }
        // the set starts at the origin by the real shadows (the grown ones reach half a gap further)
        $x0 = $y0 = INF;
        $x1 = $y1 = -INF;
        foreach ($placed as [$turn, $tx, $ty]) {
            [$a, $b, $c, $d] = Footprint::box(Footprint::turned($shadow->points, $turn));
            [$x0, $y0, $x1, $y1] = [min($x0, $a + $tx), min($y0, $b + $ty), max($x1, $c + $tx), max($y1, $d + $ty)];
        }
        $placed = array_map(fn ($p) => [$p[0], round($p[1] - $x0, 4), round($p[2] - $y0, 4)], $placed);
        $size = [$x1 - $x0, $y1 - $y0];
        if ($size[0] > $usable->x + 1e-6 || $size[1] > $usable->y + 1e-6 || ! self::apart($grown, $placed)) {
            return null;
        }

        return ['method' => 'rows', 'max' => $max, 'placements' => $placed, 'size' => [round($size[0], 2), round($size[1], 2)],
            'turn' => $v['q'], 'alternate' => $v['alt'], 'odd_rows' => $v['odd'], 'rows' => count($stack['rows']), 'per_row' => min($k, count($v['kinds'][0]['x']))];
    }

    /**
     * One row from the left: each piece slid against the pieces before it as far as their grown shadows allow.
     *
     * @return array{x: list<float>, t: list<int>}
     */
    private static function row(array $diff, int $start, bool $alt, float $startX, float $w, float $roomX): array
    {
        $xs = [];
        $ts = [];
        for ($i = 0; $i < 16 * self::MAX_COPIES; $i++) {
            $t = $alt ? ($start + $i) % 2 : $start;
            $x = $i > 0 ? max($startX, $xs[$i - 1]) : $startX;
            // the earlier pieces from the nearest back; one that ends left of the new piece ends the search
            for ($j = $i - 1; $j >= 0 && $x < $xs[$j] + $w; $j--) {
                $span = Footprint::spanAtY($diff[$ts[$j]][$t], 0.0);
                if ($span !== null) {
                    $x = max($x, $xs[$j] + $span[1]);
                }
            }
            if ($x + $w > $roomX + 1e-9) {
                break;
            }
            $xs[] = $x;
            $ts[] = $t;
        }

        return ['x' => $xs, 't' => $ts];
    }

    /**
     * Rows one above the other (even rows of the first kind, odd of the second), each pushed down onto the rows
     * below as far as the shadows allow, at most `k` pieces a row; stops at the plate's far edge or at `enough`.
     *
     * @return array{rows: list<array{0: int, 1: float}>, count: int, width: float, height: float}
     */
    private static function stack(array $v, int $k, float $roomY, int $enough = PHP_INT_MAX): array
    {
        $h = $v['h'];
        $w = $v['w'];
        $n = [min($k, count($v['kinds'][0]['x'])), min($k, count($v['kinds'][1]['x']))];
        $dy = [];
        // how far a row of kind b must sit above a row of kind a
        $rise = function (int $a, int $b) use (&$dy, $v, $n, $w): float {
            if (! isset($dy[$a][$b])) {
                $up = 0.0;
                $below = $v['kinds'][$a];
                $above = $v['kinds'][$b];
                for ($i = 0; $i < $n[$b]; $i++) {
                    for ($j = 0; $j < $n[$a]; $j++) {
                        $dx = $above['x'][$i] - $below['x'][$j];
                        if (abs($dx) < $w) {
                            $span = Footprint::spanAtX($v['diff'][$below['t'][$j]][$above['t'][$i]], $dx);
                            if ($span !== null) {
                                $up = max($up, $span[1]);
                            }
                        }
                    }
                }
                $dy[$a][$b] = $up;
            }

            return $dy[$a][$b];
        };
        $rows = [];
        $count = 0;
        $width = 0.0;
        for ($r = 0; $count < $enough && $r < 16 * self::MAX_COPIES; $r++) {
            $kind = $r % 2;
            $y = $r > 0 ? $rows[$r - 1][1] : 0.0;
            // the rows below from the nearest down; one that ends below the new row ends the search
            for ($m = $r - 1; $m >= 0 && $rows[$m][1] + $h > $y; $m--) {
                $y = max($y, $rows[$m][1] + $rise($rows[$m][0], $kind));
            }
            if ($y + $h > $roomY + 1e-9) {
                break;
            }
            $rows[] = [$kind, $y];
            $count += $n[$kind];
            $width = max($width, $v['kinds'][$kind]['x'][$n[$kind] - 1] + $w);
        }

        return ['rows' => $rows, 'count' => $count, 'width' => $width, 'height' => $rows === [] ? 0.0 : $rows[count($rows) - 1][1] + $h];
    }

    /** No two grown shadows overlap (touching is fine): the check a layout must pass before it is used. */
    private static function apart(array $grown, array $placed): bool
    {
        $polys = [];
        $boxes = [];
        foreach ($placed as [$turn, $tx, $ty]) {
            $p = Footprint::moved(Footprint::turned($grown, $turn), $tx, $ty);
            $polys[] = $p;
            $boxes[] = Footprint::box($p);
        }
        $n = count($polys);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                [$a0, $b0, $a1, $b1] = $boxes[$i];
                [$c0, $d0, $c1, $d1] = $boxes[$j];
                if ($a1 <= $c0 || $c1 <= $a0 || $b1 <= $d0 || $d1 <= $b0) {
                    continue;
                }
                if (Footprint::overlap($polys[$i], $polys[$j], 1e-4)) {
                    return false;
                }
            }
        }

        return true;
    }

    /** The copies of the piece where the rows put them: turned about Z, moved, standing on Z = 0 as prepared. */
    private static function writeRows(PreparedMesh $piece, string $outPath, array $plan): PreparedMesh
    {
        [$min] = StlFile::bounds($piece->path);
        $mz = $min[2];
        $lo = [INF, INF, INF];
        $hi = [-INF, -INF, -INF];
        $fh = StlFile::beginBinary($outPath);
        $n = 0;
        foreach ($plan['placements'] as [$turn, $tx, $ty]) {
            foreach (StlFile::records($piece->path) as $block) {
                $out = '';
                foreach ($block as $v) {
                    $t = [];
                    for ($k = 4; $k <= 12; $k += 3) {
                        $x = $v[$k];
                        $y = $v[$k + 1];
                        $z = $v[$k + 2] - $mz;
                        // a quarter turn about Z is (x, y) → (-y, x)
                        $p = match ($turn) {
                            0 => [$tx + $x, $ty + $y, $z],
                            1 => [$tx - $y, $ty + $x, $z],
                            2 => [$tx - $x, $ty - $y, $z],
                            3 => [$tx + $y, $ty - $x, $z],
                        };
                        for ($i = 0; $i < 3; $i++) {
                            $lo[$i] = min($lo[$i], $p[$i]);
                            $hi[$i] = max($hi[$i], $p[$i]);
                        }
                        $t[] = $p;
                    }
                    $out .= pack('f12v', ...array_merge(StlFile::normal($t[0], $t[1], $t[2]), $t[0], $t[1], $t[2], [0]));
                }
                fwrite($fh, $out);
                $n += count($block);
            }
        }
        StlFile::endBinary($fh, $n);
        $copies = count($plan['placements']);

        return new PreparedMesh(
            path: $outPath,
            watertight: $piece->watertight,
            wasWatertight: $piece->wasWatertight,
            repaired: $piece->repaired,
            openEdges: $piece->openEdges * $copies,
            bbox: new Dimensions($hi[0] - $lo[0], $hi[1] - $lo[1], $hi[2] - $lo[2]),
            volumeMm3: $piece->volumeMm3 * $copies,
            areaMm2: $piece->areaMm2 * $copies,
            triangles: $n,
            shells: $piece->shells * $copies,
            orientation: $piece->orientation + ['copies' => $copies, 'layout' => 'rows', 'rows' => $plan['rows'], 'per_row' => $plan['per_row'],
                'set_turn' => $plan['turn'] * 90, 'alternate' => $plan['alternate'], 'odd_rows' => $plan['odd_rows']],
            engine: $piece->engine,
        );
    }
}
