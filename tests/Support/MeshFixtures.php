<?php

namespace Tests\Support;

use App\Engines\Mesh\StlFile;

/** Builds small meshes on the fly so tests do not depend on binary fixtures. */
final class MeshFixtures
{
    /** Axis-aligned cube [0,size]^3 as binary STL (12 triangles, outward normals). */
    public static function cubeStl(string $path, float $size = 20.0): void
    {
        $s = $size;
        $v = [
            [0, 0, 0], [$s, 0, 0], [$s, $s, 0], [0, $s, 0],
            [0, 0, $s], [$s, 0, $s], [$s, $s, $s], [0, $s, $s],
        ];
        $faces = [
            [0, 2, 1], [0, 3, 2],       // bottom (z=0), normal -z
            [4, 5, 6], [4, 6, 7],       // top, +z
            [0, 1, 5], [0, 5, 4],       // front (y=0), -y
            [2, 3, 7], [2, 7, 6],       // back, +y
            [0, 4, 7], [0, 7, 3],       // left (x=0), -x
            [1, 2, 6], [1, 6, 5],       // right, +x
        ];
        $fh = fopen($path, 'wb');
        fwrite($fh, str_pad('cube', 80, "\0").pack('V', count($faces)));
        foreach ($faces as [$a, $b, $c]) {
            fwrite($fh, pack('f3', 0, 0, 0).pack('f3', ...$v[$a]).pack('f3', ...$v[$b]).pack('f3', ...$v[$c]).pack('v', 0));
        }
        fclose($fh);
    }

    /**
     * A spool standing on one flange (core 20 mm, flanges 36 mm, 40 mm high), turned from its profile: a closed body
     * that two halves pulled apart sideways let go, and halves pulled up and down do not.
     */
    public static function spoolStl(string $path, int $segments = 64): void
    {
        $profile = [[0, 0], [18, 0], [18, 6], [10, 6], [10, 34], [18, 34], [18, 40], [0, 40]];
        $at = fn (array $p, int $j) => [$p[0] * cos(2 * M_PI * $j / $segments), $p[0] * sin(2 * M_PI * $j / $segments), $p[1]];
        $tris = [];
        for ($i = 0; $i < count($profile) - 1; $i++) {
            [$p, $q] = [$profile[$i], $profile[$i + 1]];
            for ($j = 0; $j < $segments; $j++) {
                $k = ($j + 1) % $segments;
                if ($p[0] > 0) {
                    $tris[] = [$at($p, $j), $at($p, $k), $at($q, $k)];
                }
                if ($q[0] > 0) {
                    $tris[] = [$at($p, $j), $at($q, $k), $at($q, $j)];
                }
            }
        }
        $fh = fopen($path, 'wb');
        fwrite($fh, str_pad('spool', 80, "\0").pack('V', count($tris)));
        foreach ($tris as [$a, $b, $c]) {
            fwrite($fh, pack('f3', 0, 0, 0).pack('f3', ...$a).pack('f3', ...$b).pack('f3', ...$c).pack('v', 0));
        }
        fclose($fh);
    }

    /**
     * A bust the way a generator hands it over: an oval chest, a neck and a head, closed, standing on the bed, but
     * with the lowest 3 mm of the chest torn into random notches and spikes about 1.3 mm wide (the generator's open
     * edge after the rebuild). Height 85 mm, chest 60 x 34 mm. Deterministic (seeded).
     */
    public static function tornBustStl(string $path): void
    {
        $n = 144;
        // rings bottom to top: [z, half width, half depth]; the lowest three are the torn zone
        $rings = [[0, 30, 17], [1.5, 30, 17], [3, 30, 17], [4.5, 29.8, 16.9], [15, 29, 16.5], [30, 28, 16], [45, 26.5, 15], [50, 24, 14], [53, 10, 10], [58, 9, 9], [62, 11, 11], [68, 13.5, 13.5], [75, 14, 14], [81, 12, 12], [84.5, 6, 6]];
        mt_srand(20261004);
        $pts = [];
        foreach ($rings as $i => [$z, $a, $b]) {
            $row = [];
            for ($j = 0; $j < $n; $j++) {
                $t = 2 * M_PI * $j / $n;
                $f = $i < 3 ? 0.72 + 0.3 * mt_rand(0, 1000) / 1000 : 1.0;
                $row[] = [$f * $a * cos($t), $f * $b * sin($t), $z];
            }
            $pts[] = $row;
        }
        $tris = [];
        $bottom = [0, 0, -0.01];
        $top = [0, 0, 85];
        for ($j = 0; $j < $n; $j++) {
            $k = ($j + 1) % $n;
            $tris[] = [$bottom, $pts[0][$k], $pts[0][$j]];
            $last = count($pts) - 1;
            $tris[] = [$top, $pts[$last][$j], $pts[$last][$k]];
            for ($i = 0; $i < $last; $i++) {
                $tris[] = [$pts[$i][$j], $pts[$i][$k], $pts[$i + 1][$k]];
                $tris[] = [$pts[$i][$j], $pts[$i + 1][$k], $pts[$i + 1][$j]];
            }
        }
        $fh = fopen($path, 'wb');
        fwrite($fh, str_pad('torn bust', 80, "\0").pack('V', count($tris)));
        foreach ($tris as [$a, $b, $c]) {
            fwrite($fh, pack('f3', 0, 0, 0).pack('f3', ...$a).pack('f3', ...$b).pack('f3', ...$c).pack('v', 0));
        }
        fclose($fh);
    }

    /** Same cube as ASCII STL. */
    public static function cubeStlAscii(string $path, float $size = 20.0): void
    {
        $bin = $path.'.tmp.stl';
        self::cubeStl($bin, $size);
        $out = "solid cube\n";
        foreach (StlFile::triangles($bin) as [$a, $b, $c]) {
            $out .= "facet normal 0 0 0\n outer loop\n";
            foreach ([$a, $b, $c] as $p) {
                $out .= sprintf("  vertex %s %s %s\n", $p[0], $p[1], $p[2]);
            }
            $out .= " endloop\nendfacet\n";
        }
        $out .= "endsolid cube\n";
        file_put_contents($path, $out);
        unlink($bin);
    }

    /** Minimal 3MF with one cube object and a build item transform (scale 2, translate 10). */
    public static function cube3mf(string $path, float $size = 10.0, ?string $transform = '2 0 0 0 2 0 0 0 2 10 10 10'): void
    {
        $s = $size;
        $verts = [[0, 0, 0], [$s, 0, 0], [$s, $s, 0], [0, $s, 0], [0, 0, $s], [$s, 0, $s], [$s, $s, $s], [0, $s, $s]];
        $faces = [[0, 2, 1], [0, 3, 2], [4, 5, 6], [4, 6, 7], [0, 1, 5], [0, 5, 4], [2, 3, 7], [2, 7, 6], [0, 4, 7], [0, 7, 3], [1, 2, 6], [1, 6, 5]];
        $xml = '<?xml version="1.0" encoding="UTF-8"?><model unit="millimeter" xml:lang="en-US" xmlns="http://schemas.microsoft.com/3dmanufacturing/core/2015/02"><resources><object id="1" type="model"><mesh><vertices>';
        foreach ($verts as $v) {
            $xml .= sprintf('<vertex x="%s" y="%s" z="%s"/>', $v[0], $v[1], $v[2]);
        }
        $xml .= '</vertices><triangles>';
        foreach ($faces as $f) {
            $xml .= sprintf('<triangle v1="%d" v2="%d" v3="%d"/>', $f[0], $f[1], $f[2]);
        }
        $xml .= '</triangles></mesh></object></resources><build><item objectid="1"'.($transform ? ' transform="'.$transform.'"' : '').'/></build></model>';

        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="model" ContentType="application/vnd.ms-package.3dmanufacturing-3dmodel+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Target="/3D/3dmodel.model" Id="rel0" Type="http://schemas.microsoft.com/3dmanufacturing/2013/01/3dmodel"/></Relationships>');
        $zip->addFromString('3D/3dmodel.model', $xml);
        $zip->close();
    }
}
