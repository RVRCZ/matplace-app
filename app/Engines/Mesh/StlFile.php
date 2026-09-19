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

    /** Geometry statistics in millimetres. */
    public static function stats(string $path): MeshReport
    {
        $min = [INF, INF, INF];
        $max = [-INF, -INF, -INF];
        $volume = 0.0;
        $area = 0.0;
        $count = 0;
        $degenerate = 0;

        foreach (self::triangles($path) as [$a, $b, $c]) {
            $count++;
            foreach ([$a, $b, $c] as $p) {
                for ($i = 0; $i < 3; $i++) {
                    if ($p[$i] < $min[$i]) {
                        $min[$i] = $p[$i];
                    }
                    if ($p[$i] > $max[$i]) {
                        $max[$i] = $p[$i];
                    }
                }
            }
            // signed volume of tetrahedron (origin, a, b, c)
            $volume += ($a[0] * ($b[1] * $c[2] - $b[2] * $c[1])
                - $a[1] * ($b[0] * $c[2] - $b[2] * $c[0])
                + $a[2] * ($b[0] * $c[1] - $b[1] * $c[0])) / 6.0;
            // area = |(b-a) x (c-a)| / 2
            $ux = $b[0] - $a[0];
            $uy = $b[1] - $a[1];
            $uz = $b[2] - $a[2];
            $vx = $c[0] - $a[0];
            $vy = $c[1] - $a[1];
            $vz = $c[2] - $a[2];
            $cx = $uy * $vz - $uz * $vy;
            $cy = $uz * $vx - $ux * $vz;
            $cz = $ux * $vy - $uy * $vx;
            $t = sqrt($cx * $cx + $cy * $cy + $cz * $cz) / 2.0;
            if ($t <= 1e-12) {
                $degenerate++;
            }
            $area += $t;
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

    /** Writes a uniformly scaled binary STL copy. */
    public static function scale(string $inPath, string $outPath, float $factor): int
    {
        $out = fopen($outPath, 'wb');
        if (! $out) {
            throw new EngineException('Cannot write '.$outPath);
        }
        fwrite($out, str_pad('matplace scaled '.$factor, self::BINARY_HEADER, "\0"));
        fwrite($out, pack('V', 0));
        $n = 0;
        foreach (self::triangles($inPath) as [$a, $b, $c]) {
            fwrite($out, pack('f3', 0, 0, 0)
                .pack('f3', $a[0] * $factor, $a[1] * $factor, $a[2] * $factor)
                .pack('f3', $b[0] * $factor, $b[1] * $factor, $b[2] * $factor)
                .pack('f3', $c[0] * $factor, $c[1] * $factor, $c[2] * $factor)
                .pack('v', 0));
            $n++;
        }
        fseek($out, self::BINARY_HEADER);
        fwrite($out, pack('V', $n));
        fclose($out);

        return $n;
    }

    /** Starts a binary STL writer; returns handle. Finish with endBinary(). */
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
