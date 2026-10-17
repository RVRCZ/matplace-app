<?php

namespace App\Domain\Farm;

use App\Engines\Mesh\StlFile;

/**
 * The shadow a piece casts on the plate in its printing pose: the convex hull of its vertices seen from above, in
 * millimetres, counter-clockwise, at most MAX_POINTS corners rounded to 0.1 mm. PlateLayout lays copies by it
 * instead of by the bounding rectangle (a triangular sign or an L-shaped tag leaves a third of its rectangle empty).
 */
final class Footprint
{
    public const MAX_POINTS = 64;

    /** @param  list<array{0: float, 1: float}>  $points  convex, counter-clockwise, no repeated corner */
    private function __construct(public readonly array $points) {}

    /**
     * The hull of any points (a hull that comes out with more than MAX_POINTS corners is replaced by the polygon of
     * MAX_POINTS directions around it: never smaller than the hull, at most a hair bigger).
     *
     * @param  iterable<array{0: float|int, 1: float|int}>  $points
     */
    public static function fromPoints(iterable $points, int $decimals = 1): ?self
    {
        $hull = self::hull($points);
        if (count($hull) > self::MAX_POINTS) {
            $hull = self::hull(self::around($hull, self::MAX_POINTS));
        }
        $hull = self::hull(array_map(fn ($p) => [round($p[0], $decimals), round($p[1], $decimals)], $hull));

        return count($hull) >= 3 ? new self($hull) : null;
    }

    /** What PreparedMesh keeps (and the prepared-model cache stores): [[x, y], …]; anything else gives null. */
    public static function fromArray(mixed $points): ?self
    {
        if (! is_array($points) || count($points) < 3 || count($points) > 4 * self::MAX_POINTS) {
            return null;
        }
        $clean = [];
        foreach ($points as $p) {
            if (! is_array($p) || ! is_numeric($p[0] ?? null) || ! is_numeric($p[1] ?? null)) {
                return null;
            }
            $clean[] = [(float) $p[0], (float) $p[1]];
        }

        return self::fromPoints($clean);
    }

    /**
     * The hull of an STL's vertices (PHP: the fallback preparer and the calculator's uploads, cached by the caller).
     * Block by block, the running hull merged with each block's points: a mesh of millions of triangles never holds
     * more than a block of points at a time.
     */
    public static function fromStl(string $path, float $scale = 1.0): ?self
    {
        $hull = [];
        foreach (StlFile::records($path) as $block) {
            $pts = $hull;
            foreach ($block as $v) {
                $pts[] = [$v[4], $v[5]];
                $pts[] = [$v[7], $v[8]];
                $pts[] = [$v[10], $v[11]];
            }
            $hull = self::hull($pts);
        }
        if ($scale !== 1.0) {
            $hull = array_map(fn ($p) => [$p[0] * $scale, $p[1] * $scale], $hull);
        }

        return self::fromPoints($hull);
    }

    /** @return list<array{0: float, 1: float}> */
    public function toArray(): array
    {
        return $this->points;
    }

    public function scaled(float $factor): self
    {
        return new self(array_map(fn ($p) => [$p[0] * $factor, $p[1] * $factor], $this->points));
    }

    public function area(): float
    {
        return self::polygonArea($this->points);
    }

    /** @return array{0: float, 1: float, 2: float, 3: float} min x, min y, max x, max y */
    public function bounds(): array
    {
        return self::box($this->points);
    }

    /** How much of its bounding rectangle the shadow fills (1 = a rectangle). */
    public function fill(): float
    {
        [$x0, $y0, $x1, $y1] = $this->bounds();
        $box = ($x1 - $x0) * ($y1 - $y0);

        return $box > 0 ? $this->area() / $box : 1.0;
    }

    // ── convex polygon helpers (PlateLayout uses them on the grown shadows) ──────────────────────────────────────

    /**
     * Andrew's monotone chain; collinear points dropped. The points are sorted by array_multisort (in C: a block of
     * 60 000 points of a big mesh takes a hundredth of a second, usort with a closure took half a second).
     *
     * @param  iterable<array{0: float|int, 1: float|int}>  $points
     * @return list<array{0: float, 1: float}>
     */
    public static function hull(iterable $points): array
    {
        $xs = [];
        $ys = [];
        foreach ($points as $q) {
            $xs[] = (float) $q[0];
            $ys[] = (float) $q[1];
        }
        $n = count($xs);
        if ($n < 3) {
            return array_map(fn ($x, $y) => [$x, $y], $xs, $ys);
        }
        array_multisort($xs, SORT_NUMERIC, $ys, SORT_NUMERIC);
        $chain = function (bool $up) use ($xs, $ys, $n): array {
            $hx = [];
            $hy = [];
            $k = 0;
            for ($s = 0; $s < $n; $s++) {
                $i = $up ? $s : $n - 1 - $s;
                $x = $xs[$i];
                $y = $ys[$i];
                while ($k >= 2 && ($hx[$k - 1] - $hx[$k - 2]) * ($y - $hy[$k - 2]) - ($hy[$k - 1] - $hy[$k - 2]) * ($x - $hx[$k - 2]) <= 1e-12) {
                    $k--;
                }
                $hx[$k] = $x;
                $hy[$k] = $y;
                $k++;
            }
            $out = [];
            for ($i = 0; $i < $k - 1; $i++) {   // the chain's last point is the other chain's first
                $out[] = [$hx[$i], $hy[$i]];
            }

            return $out;
        };

        return array_merge($chain(true), $chain(false));
    }

    /**
     * The polygon of `n` evenly turned directions that holds the convex polygon: each side touches it.
     *
     * @param  list<array{0: float, 1: float}>  $poly
     * @return list<array{0: float, 1: float}>
     */
    public static function around(array $poly, int $n): array
    {
        $dirs = [];
        for ($k = 0; $k < $n; $k++) {
            $a = 2 * M_PI * $k / $n;
            $c = cos($a);
            $s = sin($a);
            $h = -INF;
            foreach ($poly as $p) {
                $h = max($h, $p[0] * $c + $p[1] * $s);
            }
            $dirs[] = [$c, $s, $h];
        }
        $out = [];
        for ($k = 0; $k < $n; $k++) {
            [$c1, $s1, $h1] = $dirs[$k];
            [$c2, $s2, $h2] = $dirs[($k + 1) % $n];
            $det = $c1 * $s2 - $s1 * $c2;
            $out[] = [($h1 * $s2 - $s1 * $h2) / $det, ($c1 * $h2 - $h1 * $c2) / $det];
        }

        return $out;
    }

    /**
     * Minkowski sum of two convex counter-clockwise polygons (edges merged by angle).
     *
     * @param  list<array{0: float, 1: float}>  $a
     * @param  list<array{0: float, 1: float}>  $b
     * @return list<array{0: float, 1: float}>
     */
    public static function minkowski(array $a, array $b): array
    {
        $a = self::fromLowest($a);
        $b = self::fromLowest($b);
        $na = count($a);
        $nb = count($b);
        $out = [];
        $i = $j = 0;
        while ($i < $na || $j < $nb) {
            $out[] = [$a[$i % $na][0] + $b[$j % $nb][0], $a[$i % $na][1] + $b[$j % $nb][1]];
            $ea = [$a[($i + 1) % $na][0] - $a[$i % $na][0], $a[($i + 1) % $na][1] - $a[$i % $na][1]];
            $eb = [$b[($j + 1) % $nb][0] - $b[$j % $nb][0], $b[($j + 1) % $nb][1] - $b[$j % $nb][1]];
            $cross = $ea[0] * $eb[1] - $ea[1] * $eb[0];
            if ($j >= $nb || ($i < $na && $cross > 0)) {
                $i++;
            } elseif ($i >= $na || $cross < 0) {
                $j++;
            } else {
                $i++;
                $j++;
            }
        }

        return self::hull($out);
    }

    /**
     * The convex polygon grown by `r` on every side: the sum with a 16-sided polygon drawn around a circle of
     * radius r (so never closer than r anywhere, at most 2 % of r further at the corners).
     *
     * @param  list<array{0: float, 1: float}>  $poly
     * @return list<array{0: float, 1: float}>
     */
    public static function grown(array $poly, float $r): array
    {
        if ($r <= 0) {
            return $poly;
        }
        $disc = [];
        $R = $r / cos(M_PI / 16);
        for ($k = 0; $k < 16; $k++) {
            $a = 2 * M_PI * ($k + 0.5) / 16;
            $disc[] = [$R * cos($a), $R * sin($a)];
        }

        return self::minkowski($poly, $disc);
    }

    /**
     * Turned by quarter turns about the origin (exact: no sine of 90° in floating point).
     *
     * @param  list<array{0: float, 1: float}>  $poly
     * @return list<array{0: float, 1: float}>
     */
    public static function turned(array $poly, int $quarters): array
    {
        $q = (($quarters % 4) + 4) % 4;

        return array_map(fn ($p) => match ($q) {
            0 => [$p[0], $p[1]],
            1 => [-$p[1], $p[0]],
            2 => [-$p[0], -$p[1]],
            3 => [$p[1], -$p[0]],
        }, $poly);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $poly
     * @return list<array{0: float, 1: float}>
     */
    public static function moved(array $poly, float $dx, float $dy): array
    {
        return array_map(fn ($p) => [$p[0] + $dx, $p[1] + $dy], $poly);
    }

    /**
     * Where the horizontal line y = c crosses the convex polygon: [from x, to x], or null when it misses.
     *
     * @param  list<array{0: float, 1: float}>  $poly
     * @return array{0: float, 1: float}|null
     */
    public static function spanAtY(array $poly, float $c): ?array
    {
        return self::span($poly, $c, 0, 1);
    }

    /**
     * Where the vertical line x = c crosses the convex polygon: [from y, to y], or null.
     *
     * @param  list<array{0: float, 1: float}>  $poly
     * @return array{0: float, 1: float}|null
     */
    public static function spanAtX(array $poly, float $c): ?array
    {
        return self::span($poly, $c, 1, 0);
    }

    /**
     * Do two convex polygons overlap by more than `eps` (touching is not overlapping)? Separating axis test.
     *
     * @param  list<array{0: float, 1: float}>  $a
     * @param  list<array{0: float, 1: float}>  $b
     */
    public static function overlap(array $a, array $b, float $eps = 1e-6): bool
    {
        foreach ([$a, $b] as $poly) {
            $n = count($poly);
            for ($i = 0; $i < $n; $i++) {
                $p = $poly[$i];
                $q = $poly[($i + 1) % $n];
                $ax = $q[1] - $p[1];
                $ay = $p[0] - $q[0];
                $len = hypot($ax, $ay);
                if ($len < 1e-12) {
                    continue;
                }
                $ax /= $len;
                $ay /= $len;
                [$a0, $a1] = self::project($a, $ax, $ay);
                [$b0, $b1] = self::project($b, $ax, $ay);
                if ($a1 <= $b0 + $eps || $b1 <= $a0 + $eps) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $poly
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public static function box(array $poly): array
    {
        $x0 = $y0 = INF;
        $x1 = $y1 = -INF;
        foreach ($poly as [$x, $y]) {
            $x0 = min($x0, $x);
            $y0 = min($y0, $y);
            $x1 = max($x1, $x);
            $y1 = max($y1, $y);
        }

        return [$x0, $y0, $x1, $y1];
    }

    /** @param  list<array{0: float, 1: float}>  $poly */
    public static function polygonArea(array $poly): float
    {
        $s = 0.0;
        $n = count($poly);
        for ($i = 0; $i < $n; $i++) {
            $s += $poly[$i][0] * $poly[($i + 1) % $n][1] - $poly[($i + 1) % $n][0] * $poly[$i][1];
        }

        return abs($s) / 2;
    }

    /** @return array{0: float, 1: float} */
    private static function project(array $poly, float $ax, float $ay): array
    {
        $lo = INF;
        $hi = -INF;
        foreach ($poly as [$x, $y]) {
            $d = $x * $ax + $y * $ay;
            $lo = min($lo, $d);
            $hi = max($hi, $d);
        }

        return [$lo, $hi];
    }

    /** The crossing of the line (coordinate `$on` = c) with the polygon along the other coordinate `$along`. */
    private static function span(array $poly, float $c, int $along, int $on): ?array
    {
        $lo = INF;
        $hi = -INF;
        $n = count($poly);
        for ($i = 0; $i < $n; $i++) {
            $p = $poly[$i];
            $q = $poly[($i + 1) % $n];
            $pa = $p[$on] - $c;
            $qa = $q[$on] - $c;
            if (abs($pa) < 1e-9) {
                $lo = min($lo, $p[$along]);
                $hi = max($hi, $p[$along]);
            }
            if (($pa < 0 && $qa > 0) || ($pa > 0 && $qa < 0)) {
                $v = $p[$along] + ($q[$along] - $p[$along]) * $pa / ($pa - $qa);
                $lo = min($lo, $v);
                $hi = max($hi, $v);
            }
        }

        return $lo <= $hi ? [$lo, $hi] : null;
    }

    /** Counter-clockwise from the lowest corner (then leftmost), as the Minkowski merge needs. */
    private static function fromLowest(array $poly): array
    {
        $k = 0;
        foreach ($poly as $i => $p) {
            if ($p[1] < $poly[$k][1] || ($p[1] === $poly[$k][1] && $p[0] < $poly[$k][0])) {
                $k = $i;
            }
        }

        return array_merge(array_slice($poly, $k), array_slice($poly, 0, $k));
    }
}
