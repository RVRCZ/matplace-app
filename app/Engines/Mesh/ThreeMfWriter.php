<?php

namespace App\Engines\Mesh;

use App\Engines\Exceptions\EngineException;

/**
 * A plain 3MF with one object per part, each told which filament prints it (Metadata/model_settings.config, the
 * way OrcaSlicer and its forks read it; verified on 19 Oct 2026 with Anycubic Slicer Next 2.0 and with OrcaSlicer
 * 2.4.0-beta, the farm's: "found 3mf from other vendor, split as instance", the extruder of each object honoured –
 * what made Orca fall over was the filament profiles' missing colours, see OrcaSlicer::filamentColours). Coordinates
 * are kept as they are: the farm places the parts on the plate itself and slices with `--arrange 0`.
 *
 * Copies of a piece are instances (docs/AL.md §10.13, §11.10): every object gets a build item per instance, the same
 * mesh moved (and turned) into place, so Orca slices the object once and prints it where every item stands
 * ("total object counts 4 in current print, need to slice 1"; 4 copies of a tree with supports in 65 s against 253 s
 * for the same four glued into one mesh, 8 Nov 2026).
 */
final class ThreeMfWriter
{
    /** The zip entries carry this time, not the moment of writing: the same content gives the same bytes (the slice cache hashes the project). */
    private const MTIME = 946684800;   // 1 Jan 2000

    /**
     * @param  list<array{name: string, stl: string, extruder: int, process?: array<string, string>}>  $objects  binary or
     *                                                                                                           ASCII STL per part, extruder 1-based, process settings of this object alone (Orca's per-object config: `ironing_type` off for an inlay)
     * @param  list<array{z: float, extruder: int, colour?: ?string}>  $toolChanges  changes of the filament at a height (mm, the print_z of the
     *                                                                               layer the new filament starts with), extruder 1-based: Orca's own per-layer tool change (Metadata/custom_gcode_per_layer.xml,
     *                                                                               type 2, mode MultiAsSingle), one filament printing the whole plate at a time – a tool changer (Prusa INDX) makes the change
     *                                                                               on its station (docs/AM.md §12j; verified with Orca 2.5 on 6 Nov 2026)
     * @param  list<array{0: float, 1: float, 2?: float, 3?: int}>  $instances  where the copies of the plate stand: [dx, dy, dz = 0, quarter turns about Z = 0]
     *                                                                          applied to every object's own coordinates (turned first, then moved), one build item per
     *                                                                          entry and object; all objects share the list, so the parts of one piece stay together.
     *                                                                          Empty = one item per object with the unit matrix, the mesh where it is
     */
    public static function write(string $path, array $objects, array $toolChanges = [], array $instances = []): void
    {
        if (! $objects) {
            throw new EngineException('A 3MF needs at least one object.');
        }
        $zip = new \ZipArchive;
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new EngineException('Cannot write '.$path);
        }
        $transforms = array_map([self::class, 'transform'], $instances ?: [[0.0, 0.0]]);
        $resources = '';
        $build = '';
        $config = '';
        foreach (array_values($objects) as $i => $o) {
            $id = $i + 1;
            $name = htmlspecialchars((string) $o['name'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $resources .= '<object id="'.$id.'" name="'.$name.'" type="model">'.self::mesh((string) $o['stl']).'</object>';
            foreach ($transforms as $t) {
                $build .= '<item objectid="'.$id.'" transform="'.$t.'"/>';
            }
            $own = '';
            foreach ((array) ($o['process'] ?? []) as $key => $value) {
                if (is_string($key) && preg_match('/^[a-z][a-z0-9_]{1,60}$/', $key) && is_scalar($value)) {
                    $own .= '<metadata key="'.$key.'" value="'.htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'"/>';
                }
            }
            $config .= '<object id="'.$id.'"><metadata key="name" value="'.$name.'"/><metadata key="extruder" value="'.max(1, (int) $o['extruder']).'"/>'.$own.'</object>';
        }
        $entries = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="model" ContentType="application/vnd.ms-package.3dmanufacturing-3dmodel+xml"/>'
                .'<Default Extension="config" ContentType="text/xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Target="/3D/3dmodel.model" Id="rel0" Type="http://schemas.microsoft.com/3dmanufacturing/2013/01/3dmodel"/></Relationships>',
            '3D/3dmodel.model' => '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<model unit="millimeter" xml:lang="en-US" xmlns="http://schemas.microsoft.com/3dmanufacturing/core/2015/02">'
                .'<metadata name="Application">matplace farm</metadata><resources>'.$resources.'</resources><build>'.$build.'</build></model>',
            'Metadata/model_settings.config' => '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<config>'.$config.'</config>',
        ];
        if ($toolChanges) {
            $entries['Metadata/custom_gcode_per_layer.xml'] = self::toolChangesXml($toolChanges);
        }
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
            if (method_exists($zip, 'setMtimeName')) {
                $zip->setMtimeName($name, self::MTIME);
            }
        }
        $zip->close();
    }

    /**
     * The 3MF transform of one instance: the twelve numbers of a 4 × 3 matrix in the order the specification writes them
     * (the three columns of the rotation, then the translation; libslic3r reads them back transposed). A quarter turn
     * about Z turns (x, y) into (−y, x), as PlateLayout turns a copy.
     *
     * @param  array{0: float, 1: float, 2?: float, 3?: int}  $instance
     */
    public static function transform(array $instance): string
    {
        $turn = ((int) ($instance[3] ?? 0) % 4 + 4) % 4;
        [$c, $s] = [[1, 0], [0, 1], [-1, 0], [0, -1]][$turn];
        $f = fn (float $v) => abs($v) < 0.00005 ? '0' : rtrim(rtrim(sprintf('%.4f', $v), '0'), '.');

        return implode(' ', [$c, $s, 0, -$s, $c, 0, 0, 0, 1, $f((float) $instance[0]), $f((float) $instance[1]), $f((float) ($instance[2] ?? 0.0))]);
    }

    /**
     * The per-layer custom G-code file of Orca / Bambu Studio with a tool change per entry: `top_z` is the print_z of the
     * layer the new filament starts with (the change comes before that layer), `type` 2 = ToolChange, `MultiAsSingle` =
     * one filament prints the whole plate until the next change (libslic3r CustomGCode, ToolOrdering::custom_tool_changes).
     *
     * @param  list<array{z: float, extruder: int, colour?: ?string}>  $toolChanges
     */
    public static function toolChangesXml(array $toolChanges): string
    {
        usort($toolChanges, fn ($a, $b) => $a['z'] <=> $b['z']);
        $rows = '';
        foreach ($toolChanges as $c) {
            $colour = htmlspecialchars((string) ($c['colour'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $rows .= '<layer top_z="'.rtrim(rtrim(sprintf('%.3f', (float) $c['z']), '0'), '.').'" type="2" extruder="'.max(1, (int) $c['extruder']).'" color="'.$colour.'" extra="" gcode="tool_change"/>'."\n";
        }

        return '<?xml version="1.0" encoding="utf-8"?>'."\n<custom_gcodes_per_layer>\n<plate>\n<plate_info id=\"1\"/>\n".$rows."<mode value=\"MultiAsSingle\"/>\n</plate>\n</custom_gcodes_per_layer>\n";
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
