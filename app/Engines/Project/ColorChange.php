<?php

namespace App\Engines\Project;

/**
 * A filament change written into a slicer project: the printer stops at a height, the operator swaps the spool, the
 * print goes on in the second colour. That is how a QR code, a raised text or a logo gets its contrast on a
 * single-nozzle printer; without it the printed code is one colour and cannot be read.
 *
 * OrcaSlicer and Bambu Studio read Metadata/custom_gcode_per_layer.xml, PrusaSlicer reads
 * Metadata/Prusa_Slicer_custom_gcode_per_print_z.xml; both name the layer by its top (print_z), and that layer is the
 * first one printed in the new colour. So the change goes on the first layer whose top lies above the plate.
 * Checked with the real programs on 30 Sep 2026: PrusaSlicer 2.9.6 honours a "colour change" entry (type 0) and
 * writes its own M600; OrcaSlicer ignores type 0 on a single-nozzle machine, but a "custom G-code" entry (type 4)
 * with the text in `extra` comes out as M600 at that layer.
 */
final class ColorChange
{
    /**
     * @param  float  $z  where the plate ends and the second colour starts (mm, as the model stands in the project)
     * @return float|null the print_z the change was written at; null when the project could not be read
     */
    public static function add(string $project, float $z, string $hex = '#2B2B2B'): ?float
    {
        return self::addAll($project, [['z' => $z, 'hex' => $hex]])[0] ?? null;
    }

    /**
     * Several changes in one project: a picture whose colours lie one on another changes filament at every step.
     * Two changes that fall into the same layer are one change, to the later colour.
     *
     * @param  list<array{z: float, hex?: string}>  $changes  bottom to top
     * @return list<float>|null the print_z of every change written; null when the project could not be read
     */
    public static function addAll(string $project, array $changes): ?array
    {
        $zip = new \ZipArchive;
        if ($zip->open($project) !== true) {
            return null;
        }
        try {
            $orca = $zip->getFromName('Metadata/project_settings.config');
            $prusa = $zip->getFromName('Metadata/Slic3r_PE.config');
            if ($orca === false && $prusa === false) {
                return null;
            }
            [$first, $layer] = $orca !== false ? self::orcaLayers((string) $orca) : self::prusaLayers((string) $prusa);
            $at = [];
            foreach ($changes as $change) {
                $hex = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($change['hex'] ?? '')) ? (string) $change['hex'] : '#2B2B2B';
                $at[number_format(self::layerAbove((float) $change['z'], $first, $layer), 2, '.', '')] = $hex;
            }
            ksort($at, SORT_NUMERIC);
            $lines = '';
            foreach ($at as $height => $hex) {
                $lines .= $orca !== false
                    ? "<layer top_z=\"{$height}\" type=\"4\" extruder=\"1\" color=\"{$hex}\" extra=\"M600\" gcode=\"M600\"/>\n"
                    : "<code print_z=\"{$height}\" type=\"0\" extruder=\"1\" color=\"{$hex}\" extra=\"\" gcode=\"M600\"/>\n";
            }
            if ($orca !== false) {
                $zip->addFromString('Metadata/custom_gcode_per_layer.xml', "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<custom_gcodes_per_layer>\n<plate>\n<plate_info id=\"1\"/>\n"
                    .$lines."<mode value=\"SingleExtruder\"/>\n</plate>\n</custom_gcodes_per_layer>\n");
            } else {
                $zip->addFromString('Metadata/Prusa_Slicer_custom_gcode_per_print_z.xml', "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<custom_gcodes_per_print_z>\n"
                    .$lines."<mode value=\"SingleExtruder\"/>\n</custom_gcodes_per_print_z>\n");
            }

            return array_map('floatval', array_keys($at));
        } finally {
            $zip->close();
        }
    }

    /** The top of the first layer that lies above z (a layer ending exactly at z still belongs to the plate). */
    public static function layerAbove(float $z, float $first, float $layer): float
    {
        if ($z < $first - 1e-6) {
            return round($first, 3);
        }
        $n = (int) floor(($z - $first) / $layer + 1e-6) + 1;

        return round($first + $n * $layer, 3);
    }

    /** @return array{0: float, 1: float} first layer height, layer height */
    private static function orcaLayers(string $json): array
    {
        $cfg = json_decode($json, true) ?: [];
        $one = fn ($v, float $default) => is_array($v) ? (float) ($v[0] ?? $default) : (is_numeric($v) ? (float) $v : $default);
        $layer = $one($cfg['layer_height'] ?? null, 0.2);

        return [$one($cfg['initial_layer_print_height'] ?? null, $layer), $layer];
    }

    /** @return array{0: float, 1: float} */
    private static function prusaLayers(string $ini): array
    {
        $get = function (string $key, float $default) use ($ini): float {
            return preg_match('/^;?\s*'.preg_quote($key, '/').'\s*=\s*([0-9.]+)/m', $ini, $m) ? (float) $m[1] : $default;
        };
        $layer = $get('layer_height', 0.2);

        return [$get('first_layer_height', $layer), $layer];
    }
}
