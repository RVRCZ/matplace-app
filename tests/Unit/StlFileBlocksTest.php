<?php

namespace Tests\Unit;

use App\Domain\Farm\PlateLayout;
use App\Engines\DTO\Dimensions;
use App\Engines\DTO\PreparedMesh;
use App\Engines\Mesh\StlFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * StlFile reads and writes binary STL a block at a time (6× faster on a big mesh). What it writes goes to the slicer,
 * so it must be the same bytes as the triangle-at-a-time code it replaced (kept here as the reference), and the
 * statistics the same numbers: the same input gives the same G-code and the same price.
 */
class StlFileBlocksTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/mp_stlblocks_'.uniqid();
        File::ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /** A random binary STL (odd coordinates, more triangles than one block) and the same mesh as ASCII. */
    private function meshes(int $triangles): array
    {
        mt_srand(7);
        $bin = $this->dir.'/r.stl';
        $fh = StlFile::beginBinary($bin);
        $ascii = "solid r\n";
        for ($i = 0; $i < $triangles; $i++) {
            $tri = [];
            for ($k = 0; $k < 3; $k++) {
                $tri[] = [mt_rand(-50000, 90000) / 777.0, mt_rand(-20000, 40000) / 666.0, mt_rand(1000, 99000) / 1111.0];
            }
            StlFile::writeTriangle($fh, ...$tri);
            $ascii .= " facet normal 0 0 0\n  outer loop\n";
            foreach ($tri as $p) {
                $ascii .= sprintf("   vertex %.9g %.9g %.9g\n", ...$p);
            }
            $ascii .= "  endloop\n endfacet\n";
        }
        StlFile::endBinary($fh, $triangles);
        File::put($this->dir.'/r_ascii.stl', $ascii."endsolid r\n");

        return [$bin, $this->dir.'/r_ascii.stl'];
    }

    public function test_the_same_bytes_and_numbers_as_one_triangle_at_a_time(): void
    {
        foreach ($this->meshes(45013) as $src) {
            foreach ([1.0, 1.75, 0.254] as $f) {
                StlFile::scale($src, $this->dir.'/new.stl', $f);
                self::oldScale($src, $this->dir.'/old.stl', $f);
                $this->assertSame(md5_file($this->dir.'/old.stl'), md5_file($this->dir.'/new.stl'), "scale $f of ".basename($src));

                $newSize = StlFile::place($src, $this->dir.'/new.stl', $f, 128.0, 110.5);
                $oldSize = self::oldPlace($src, $this->dir.'/old.stl', $f, 128.0, 110.5);
                $this->assertSame(md5_file($this->dir.'/old.stl'), md5_file($this->dir.'/new.stl'), "place $f of ".basename($src));
                $this->assertSame($oldSize, $newSize);
            }
            $r = StlFile::stats($src);
            $this->assertSame(self::oldStats($src), [$r->volumeMm3, $r->areaMm2, $r->bbox->x, $r->bbox->y, $r->bbox->z, $r->triangles, $r->issues], basename($src));
        }
    }

    public function test_copies_on_a_plate_are_the_same_bytes(): void
    {
        [$bin] = $this->meshes(30001);
        $placed = $this->dir.'/piece.stl';
        StlFile::place($bin, $placed, 0.2, 0, 0);
        $bbox = StlFile::stats($placed)->bbox;
        $turns = [];
        foreach ([[4, new Dimensions(250, 250, 250)], [23, new Dimensions(90, 260, 250)]] as [$copies, $usable]) {
            $piece = new PreparedMesh($placed, true, true, false, 0, $bbox, 1.0, 1.0, 30001, 1, [], 'test');
            $new = PlateLayout::replicate($piece, $this->dir.'/plate_new.stl', $copies, $usable);
            $this->assertNotNull($new);
            $old = self::oldReplicate($placed, $this->dir.'/plate_old.stl', $copies, $bbox, $new->orientation['grid'], $new->orientation['grid_rotated']);
            $this->assertSame(md5_file($this->dir.'/plate_old.stl'), md5_file($this->dir.'/plate_new.stl'), "$copies copies");
            $this->assertSame($old, $new->triangles);
            $turns[] = $new->orientation['grid_rotated'];
        }
        $this->assertSame([false, true], $turns, 'both the plain and the turned grid are compared');
    }

    // ── the code before, verbatim apart from names ─────────────────────────────

    private static function oldScale(string $inPath, string $outPath, float $factor): int
    {
        $out = fopen($outPath, 'wb');
        fwrite($out, str_pad('matplace scaled '.$factor, 80, "\0"));
        fwrite($out, pack('V', 0));
        $n = 0;
        foreach (StlFile::triangles($inPath) as [$a, $b, $c]) {
            fwrite($out, pack('f3', 0, 0, 0)
                .pack('f3', $a[0] * $factor, $a[1] * $factor, $a[2] * $factor)
                .pack('f3', $b[0] * $factor, $b[1] * $factor, $b[2] * $factor)
                .pack('f3', $c[0] * $factor, $c[1] * $factor, $c[2] * $factor)
                .pack('v', 0));
            $n++;
        }
        fseek($out, 80);
        fwrite($out, pack('V', $n));
        fclose($out);

        return $n;
    }

    private static function oldPlace(string $inPath, string $outPath, float $factor, float $cx, float $cy): array
    {
        $lo = [INF, INF, INF];
        $hi = [-INF, -INF, -INF];
        foreach (StlFile::triangles($inPath) as $tri) {
            foreach ($tri as $v) {
                for ($i = 0; $i < 3; $i++) {
                    $lo[$i] = min($lo[$i], $v[$i]);
                    $hi[$i] = max($hi[$i], $v[$i]);
                }
            }
        }
        $move = [$cx - ($lo[0] + $hi[0]) / 2 * $factor, $cy - ($lo[1] + $hi[1]) / 2 * $factor, -$lo[2] * $factor];
        $fh = StlFile::beginBinary($outPath);
        $n = 0;
        foreach (StlFile::triangles($inPath) as $tri) {
            StlFile::writeTriangle($fh, ...array_map(fn ($v) => [$v[0] * $factor + $move[0], $v[1] * $factor + $move[1], $v[2] * $factor + $move[2]], $tri));
            $n++;
        }
        StlFile::endBinary($fh, $n);

        return [($hi[0] - $lo[0]) * $factor, ($hi[1] - $lo[1]) * $factor, ($hi[2] - $lo[2]) * $factor];
    }

    private static function oldStats(string $path): array
    {
        $min = [INF, INF, INF];
        $max = [-INF, -INF, -INF];
        $volume = 0.0;
        $area = 0.0;
        $count = 0;
        $degenerate = 0;
        foreach (StlFile::triangles($path) as [$a, $b, $c]) {
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
            $volume += ($a[0] * ($b[1] * $c[2] - $b[2] * $c[1])
                - $a[1] * ($b[0] * $c[2] - $b[2] * $c[0])
                + $a[2] * ($b[0] * $c[1] - $b[1] * $c[0])) / 6.0;
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
        $issues = [];
        if ($degenerate > 0) {
            $issues[] = 'degenerate_faces';
        }
        if ($volume < 0) {
            $issues[] = 'flipped_normals';
        }

        return [abs($volume), $area, $max[0] - $min[0], $max[1] - $min[1], $max[2] - $min[2], $count, $issues];
    }

    private static function oldReplicate(string $path, string $outPath, int $copies, Dimensions $d, array $grid, bool $rotated): int
    {
        [$cols, $rows] = $grid;
        $gap = PlateLayout::GAP_MM;
        $min = [INF, INF, INF];
        foreach (StlFile::triangles($path) as $tri) {
            foreach ($tri as $p) {
                for ($i = 0; $i < 3; $i++) {
                    $min[$i] = min($min[$i], $p[$i]);
                }
            }
        }
        $stepX = ($rotated ? $d->y : $d->x) + $gap;
        $stepY = ($rotated ? $d->x : $d->y) + $gap;
        $fh = StlFile::beginBinary($outPath);
        $n = 0;
        $placed = 0;
        for ($r = 0; $r < $rows && $placed < $copies; $r++) {
            for ($c = 0; $c < $cols && $placed < $copies; $c++, $placed++) {
                $ox = $c * $stepX;
                $oy = $r * $stepY;
                foreach (StlFile::triangles($path) as $tri) {
                    $out = [];
                    foreach ($tri as $p) {
                        $x = $p[0] - $min[0];
                        $y = $p[1] - $min[1];
                        $z = $p[2] - $min[2];
                        $out[] = $rotated ? [$ox + ($d->y - $y), $oy + $x, $z] : [$ox + $x, $oy + $y, $z];
                    }
                    StlFile::writeTriangle($fh, $out[0], $out[1], $out[2]);
                    $n++;
                }
            }
        }
        StlFile::endBinary($fh, $n);

        return $n;
    }
}
