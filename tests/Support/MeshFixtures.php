<?php

namespace Tests\Support;

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

    /** Same cube as ASCII STL. */
    public static function cubeStlAscii(string $path, float $size = 20.0): void
    {
        $bin = $path.'.tmp.stl';
        self::cubeStl($bin, $size);
        $out = "solid cube\n";
        foreach (\App\Engines\Mesh\StlFile::triangles($bin) as [$a, $b, $c]) {
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
