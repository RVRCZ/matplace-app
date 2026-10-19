<?php

namespace App\Engines\Mesh;

use App\Engines\Exceptions\EngineException;

/**
 * A plain 3MF with one object per part, each told which filament prints it (Metadata/model_settings.config, the
 * way OrcaSlicer and its forks read it; verified on 19 Oct 2026 with Anycubic Slicer Next 2.0 and with OrcaSlicer
 * 2.4.0-beta, the farm's: "found 3mf from other vendor, split as instance", the extruder of each object honoured –
 * what made Orca fall over was the filament profiles' missing colours, see OrcaSlicer::filamentColours). Coordinates
 * are kept as they are: the farm places the parts on the plate itself and slices with `--arrange 0`.
 */
final class ThreeMfWriter
{
    /**
     * @param  list<array{name: string, stl: string, extruder: int}>  $objects  binary or ASCII STL per part, extruder 1-based
     */
    public static function write(string $path, array $objects): void
    {
        if (! $objects) {
            throw new EngineException('A 3MF needs at least one object.');
        }
        $zip = new \ZipArchive;
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new EngineException('Cannot write '.$path);
        }
        $resources = '';
        $build = '';
        $config = '';
        foreach (array_values($objects) as $i => $o) {
            $id = $i + 1;
            $name = htmlspecialchars((string) $o['name'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $resources .= '<object id="'.$id.'" name="'.$name.'" type="model">'.self::mesh((string) $o['stl']).'</object>';
            $build .= '<item objectid="'.$id.'" transform="1 0 0 0 1 0 0 0 1 0 0 0"/>';
            $config .= '<object id="'.$id.'"><metadata key="name" value="'.$name.'"/><metadata key="extruder" value="'.max(1, (int) $o['extruder']).'"/></object>';
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="model" ContentType="application/vnd.ms-package.3dmanufacturing-3dmodel+xml"/>'
            .'<Default Extension="config" ContentType="text/xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Target="/3D/3dmodel.model" Id="rel0" Type="http://schemas.microsoft.com/3dmanufacturing/2013/01/3dmodel"/></Relationships>');
        $zip->addFromString('3D/3dmodel.model', '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<model unit="millimeter" xml:lang="en-US" xmlns="http://schemas.microsoft.com/3dmanufacturing/core/2015/02">'
            .'<metadata name="Application">matplace farm</metadata><resources>'.$resources.'</resources><build>'.$build.'</build></model>');
        $zip->addFromString('Metadata/model_settings.config', '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<config>'.$config.'</config>');
        $zip->close();
    }

    /** The triangles of an STL as a 3MF mesh, vertices shared where they coincide (to a hundredth of a micrometre). */
    private static function mesh(string $stl): string
    {
        $index = [];
        $vertices = '';
        $triangles = '';
        $n = 0;
        foreach (StlFile::triangles($stl) as $tri) {
            $ids = [];
            foreach ($tri as $v) {
                $key = sprintf('%.5f %.5f %.5f', $v[0], $v[1], $v[2]);
                if (! isset($index[$key])) {
                    $index[$key] = $n++;
                    $vertices .= '<vertex x="'.rtrim(rtrim(sprintf('%.5f', $v[0]), '0'), '.').'" y="'.rtrim(rtrim(sprintf('%.5f', $v[1]), '0'), '.').'" z="'.rtrim(rtrim(sprintf('%.5f', $v[2]), '0'), '.').'"/>';
                }
                $ids[] = $index[$key];
            }
            if ($ids[0] !== $ids[1] && $ids[1] !== $ids[2] && $ids[0] !== $ids[2]) {
                $triangles .= '<triangle v1="'.$ids[0].'" v2="'.$ids[1].'" v3="'.$ids[2].'"/>';
            }
        }
        if ($n === 0) {
            throw new EngineException('No triangles in '.$stl);
        }

        return '<mesh><vertices>'.$vertices.'</vertices><triangles>'.$triangles.'</triangles></mesh>';
    }
}
