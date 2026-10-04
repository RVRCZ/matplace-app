<?php

namespace App\Engines\Mesh;

use App\Engines\DTO\Dimensions;
use App\Engines\DTO\MeshReport;
use App\Engines\Exceptions\EngineException;

/**
 * Pure-PHP STL reader/writer: bounding box, signed volume, surface area, uniform scaling.
 * Handles binary and ASCII STL. Streams the file, so large meshes do not need to fit in memory twice.
 * Watertightness is not detected here (see MeshRepair engines).
 */
final class StlFile
{
    private const BINARY_HEADER = 80;

    private const TRIANGLE_BYTES = 50;

    private const BLOCK = 20000;   // triangles read at a time (1 MB)

    /** Geometry statistics in millimetres. */
    public static function stats(string $path): MeshReport
    {
        $min = [INF, INF, INF];
        $max = [-INF, -INF, -INF];
        $volume = 0.0;
        $area = 0.0;
        $count = 0;
        $degenerate = 0;

        foreach (self::records($path) as $block) {
            foreach ($block as $v) {
                // a = 4..6, b = 7..9, c = 10..12 (1..3 is the normal)
                $count++;
                for ($k = 4; $k <= 12; $k += 3) {
                    for ($i = 0; $i < 3; $i++) {
                        if ($v[$k + $i] < $min[$i]) {
                            $min[$i] = $v[$k + $i];
                        }
                        if ($v[$k + $i] > $max[$i]) {
                            $max[$i] = $v[$k + $i];
                        }
                    }
                }
                // signed volume of tetrahedron (origin, a, b, c)
                $volume += ($v[4] * ($v[8] * $v[12] - $v[9] * $v[11])
                    - $v[5] * ($v[7] * $v[12] - $v[9] * $v[10])
                    + $v[6] * ($v[7] * $v[11] - $v[8] * $v[10])) / 6.0;
                // area = |(b-a) x (c-a)| / 2
                $ux = $v[7] - $v[4];
                $uy = $v[8] - $v[5];
                $uz = $v[9] - $v[6];
                $vx = $v[10] - $v[4];
                $vy = $v[11] - $v[5];
                $vz = $v[12] - $v[6];
                $cx = $uy * $vz - $uz * $vy;
                $cy = $uz * $vx - $ux * $vz;
                $cz = $ux * $vy - $uy * $vx;
                $t = sqrt($cx * $cx + $cy * $cy + $cz * $cz) / 2.0;
                if ($t <= 1e-12) {
                    $degenerate++;
                }
                $area += $t;
            }
        }

        if ($count === 0) {
            throw new EngineException('STL has no triangles: '.basename($path));
        }

        $issues = [];
        if ($degenerate > 0) {
            $issues[] = 'degenerate_faces';
        }
        if ($volume < 0) {
            $issues[] = 'flipped_normals';
        }

        return new MeshReport(
            watertight: false, // unknown without topology check
            volumeMm3: abs($volume),
            areaMm2: $area,
            bbox: new Dimensions($max[0] - $min[0], $max[1] - $min[1], $max[2] - $min[2]),
            triangles: $count,
            shells: 1,
            flippedNormals: $volume < 0,
            issues: $issues,
            engine: 'php-stl',
        );
    }

    /**
     * Writes a binary STL copy scaled and moved so that the middle of its footprint lies at ($cx, $cy) and it stands
     * on Z = 0. Returns the size of the copy [x, y, z].
     *
     * @return array{0:float,1:float,2:float}
     */
    public static function place(string $inPath, string $outPath, float $factor, float $cx, float $cy): array
    {
        [$lo, $hi] = self::bounds($inPath);
        if (! is_finite($lo[0])) {
            throw new EngineException('No triangles in '.$inPath);
        }
        $move = [$cx - ($lo[0] + $hi[0]) / 2 * $factor, $cy - ($lo[1] + $hi[1]) / 2 * $factor, -$lo[2] * $factor];
        self::transform($inPath, self::beginBinary($outPath), $factor, $move);

        return [($hi[0] - $lo[0]) * $factor, ($hi[1] - $lo[1]) * $factor, ($hi[2] - $lo[2]) * $factor];
    }

    /** Writes a uniformly scaled binary STL copy. */
    public static function scale(string $inPath, string $outPath, float $factor): int
    {
        $out = fopen($outPath, 'wb');
        if (! $out) {
            throw new EngineException('Cannot write '.$outPath);
        }
        fwrite($out, str_pad('matplace scaled '.$factor, self::BINARY_HEADER, "\0"));
        fwrite($out, pack('V', 0));

        return self::transform($inPath, $out, $factor, null);
    }

    /** Starts a binary STL writer; returns handle. Finish with endBinary(). */
    /** Axis-aligned box from the origin, binary STL (smoke tests of engines). */
    public static function writeBox(string $outPath, float $x, float $y, float $z): void
    {
        $v = [[0, 0, 0], [$x, 0, 0], [$x, $y, 0], [0, $y, 0], [0, 0, $z], [$x, 0, $z], [$x, $y, $z], [0, $y, $z]];
        $faces = [[0, 2, 1], [0, 3, 2], [4, 5, 6], [4, 6, 7], [0, 1, 5], [0, 5, 4], [2, 3, 7], [2, 7, 6], [0, 4, 7], [0, 7, 3], [1, 2, 6], [1, 6, 5]];
        $fh = self::beginBinary($outPath);
        foreach ($faces as [$a, $b, $c]) {
            self::writeTriangle($fh, $v[$a], $v[$b], $v[$c]);
        }
        self::endBinary($fh, count($faces));
    }

    public static function beginBinary(string $outPath)
    {
        $fh = fopen($outPath, 'wb');
        if (! $fh) {
            throw new EngineException('Cannot write '.$outPath);
        }
        fwrite($fh, str_repeat("\0", self::BINARY_HEADER).pack('V', 0));

        return $fh;
    }

    /** @param  array{0:float,1:float,2:float}  $a */
    public static function writeTriangle($fh, array $a, array $b, array $c): void
    {
        fwrite($fh, pack('f3', 0, 0, 0).pack('f3', ...$a).pack('f3', ...$b).pack('f3', ...$c).pack('v', 0));
    }

    public static function endBinary($fh, int $count): void
    {
        fseek($fh, self::BINARY_HEADER);
        fwrite($fh, pack('V', $count));
        fclose($fh);
    }

    /**
     * Smallest and largest coordinate of all vertices.
     *
     * @return array{0:array{0:float,1:float,2:float},1:array{0:float,1:float,2:float}} [lo, hi]; INF when there is nothing
     */
    public static function bounds(string $path): array
    {
        $lo = [INF, INF, INF];
        $hi = [-INF, -INF, -INF];
        foreach (self::records($path) as $block) {
            foreach ($block as $v) {
                for ($k = 4; $k <= 12; $k += 3) {
                    for ($i = 0; $i < 3; $i++) {
                        if ($v[$k + $i] < $lo[$i]) {
                            $lo[$i] = $v[$k + $i];
                        }
                        if ($v[$k + $i] > $hi[$i]) {
                            $hi[$i] = $v[$k + $i];
                        }
                    }
                }
            }
        }

        return [$lo, $hi];
    }

    /**
     * Triangles in blocks, each triangle as unpack('f12') gives it: [1..3] normal, [4..6] a, [7..9] b, [10..12] c.
     * A binary file is read a block at a time (a read and a generator step per triangle made a big mesh take
     * seconds); ASCII keeps its double precision, as triangles() gives it.
     *
     * @return \Generator<int, list<array<int,float>>>
     */
    public static function records(string $path): \Generator
    {
        if (! is_file($path)) {
            throw new EngineException('File not found: '.$path);
        }
        if (! self::isBinary($path)) {
            $block = [];
            foreach (self::asciiTriangles($path) as [$a, $b, $c]) {
                $block[] = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => $a[0], 5 => $a[1], 6 => $a[2], 7 => $b[0], 8 => $b[1], 9 => $b[2], 10 => $c[0], 11 => $c[1], 12 => $c[2]];
                if (count($block) === self::BLOCK) {
                    yield $block;
                    $block = [];
                }
            }
            if ($block) {
                yield $block;
            }

            return;
        }
        $fh = fopen($path, 'rb');
        try {
            fseek($fh, self::BINARY_HEADER);
            $count = unpack('V', fread($fh, 4))[1];
            for ($done = 0; $done < $count;) {
                $want = min(self::BLOCK, $count - $done);
                $buf = fread($fh, $want * self::TRIANGLE_BYTES);
                $got = $buf === false ? 0 : intdiv(strlen($buf), self::TRIANGLE_BYTES);
                if ($got === 0) {
                    break;
                }
                $block = [];
                for ($i = 0, $o = 0; $i < $got; $i++, $o += self::TRIANGLE_BYTES) {
                    $block[] = unpack('f12', $buf, $o);
                }
                yield $block;
                $done += $got;
                if ($got < $want) {
                    break;   // a truncated file: what is there, as before
                }
            }
        } finally {
            fclose($fh);
        }
    }

    /**
     * Writes every triangle as v × factor + move (move null: scaled only) to an open binary writer and closes it.
     * The same arithmetic as one triangle at a time before, so the same bytes; one pack and one write per block.
     */
    private static function transform(string $inPath, $fh, float $f, ?array $move): int
    {
        $n = 0;
        foreach (self::records($inPath) as $block) {
            $out = '';
            if ($move === null) {
                foreach ($block as $v) {
                    $out .= pack('f12v', 0, 0, 0, $v[4] * $f, $v[5] * $f, $v[6] * $f, $v[7] * $f, $v[8] * $f, $v[9] * $f, $v[10] * $f, $v[11] * $f, $v[12] * $f, 0);
                }
            } else {
                [$mx, $my, $mz] = $move;
                foreach ($block as $v) {
                    $out .= pack('f12v', 0, 0, 0, $v[4] * $f + $mx, $v[5] * $f + $my, $v[6] * $f + $mz, $v[7] * $f + $mx, $v[8] * $f + $my, $v[9] * $f + $mz, $v[10] * $f + $mx, $v[11] * $f + $my, $v[12] * $f + $mz, 0);
                }
            }
            fwrite($fh, $out);
            $n += count($block);
        }
        self::endBinary($fh, $n);

        return $n;
    }

    public static function isBinary(string $path): bool
    {
        $size = filesize($path);
        if ($size < self::BINARY_HEADER + 4) {
            return false;
        }
        $fh = fopen($path, 'rb');
        $head = fread($fh, self::BINARY_HEADER + 4);
        fclose($fh);
        $count = unpack('V', substr($head, self::BINARY_HEADER, 4))[1];
        if ($size === self::BINARY_HEADER + 4 + $count * self::TRIANGLE_BYTES) {
            return true;
        }

        return ! str_starts_with(ltrim(substr($head, 0, 6)), 'solid');
    }

    /**
     * Streams triangles as [[ax,ay,az],[bx,by,bz],[cx,cy,cz]].
     *
     * @return \Generator<int, array{0:array,1:array,2:array}>
     */
    public static function triangles(string $path): \Generator
    {
        if (! is_file($path)) {
            throw new EngineException('File not found: '.$path);
        }

        return self::isBinary($path) ? self::binaryTriangles($path) : self::asciiTriangles($path);
    }

    private static function binaryTriangles(string $path): \Generator
    {
        $fh = fopen($path, 'rb');
        fseek($fh, self::BINARY_HEADER);
        $count = unpack('V', fread($fh, 4))[1];
        for ($i = 0; $i < $count; $i++) {
            $chunk = fread($fh, self::TRIANGLE_BYTES);
            if ($chunk === false || strlen($chunk) < self::TRIANGLE_BYTES) {
                break;
            }
            $v = unpack('f12', $chunk); // normal(3) + 3 vertices
            yield [
                [$v[4], $v[5], $v[6]],
                [$v[7], $v[8], $v[9]],
                [$v[10], $v[11], $v[12]],
            ];
        }
        fclose($fh);
    }

    private static function asciiTriangles(string $path): \Generator
    {
        $fh = fopen($path, 'rb');
        $verts = [];
        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if (str_starts_with($line, 'vertex')) {
                $p = preg_split('/\s+/', $line);
                if (count($p) >= 4) {
                    $verts[] = [(float) $p[1], (float) $p[2], (float) $p[3]];
                    if (count($verts) === 3) {
                        yield $verts;
                        $verts = [];
                    }
                }
            }
        }
        fclose($fh);
    }
}
