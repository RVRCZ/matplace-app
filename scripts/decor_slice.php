<?php

/**
 * Control slices of designs with decorations in filaments of their own (docs/AR.md §12): the design is built the way
 * the order builds it (ParametricGenerator::build, part by part), put on one plate as a 3MF with a filament per part
 * (ThreeMfWriter, copies as instances) and sliced by the farm's own slicer class with the farm's profiles and
 * PrepareFarmOrder::ONE_PLATE_PROCESS. A design whose colours change by height is sliced as one piece and its changes
 * are read from the design's notes. Nothing is stored, no database is touched.
 *
 *   ENGINE_SLICER=orca ORCA_XVFB=false ORCA_BIN=<orca-slicer.exe> php scripts/decor_slice.php cases.json [out dir for the G-code]
 *
 * cases.json: [{"name": "...", "kind": "medallion", "params": {...}, "copies": 1}, ...]; a picture in the params is
 * written "path:<file>" and is stored as an uploaded picture would be.
 */

use App\Domain\Tools\ParametricGenerator;
use App\Engines\DTO\SliceParams;
use App\Engines\Mesh\ThreeMfWriter;
use App\Engines\Slicer\OrcaSlicer;
use App\Jobs\PrepareFarmOrder;
use Illuminate\Http\UploadedFile;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cases = json_decode((string) file_get_contents($argv[1] ?? ''), true);
if (! is_array($cases)) {
    fwrite(STDERR, "usage: php scripts/decor_slice.php cases.json [out dir]\n");
    exit(2);
}
$keep = $argv[2] ?? null;
$keep && @mkdir($keep, 0777, true);
$tools = app(ParametricGenerator::class);
$slicer = new OrcaSlicer(config('engines.orca'));
$bed = 250.0;

$stored = [];
$pictures = function ($value) use (&$pictures, &$stored) {
    if (is_array($value)) {
        return array_map($pictures, $value);
    }
    if (is_string($value) && str_starts_with($value, 'path:')) {
        $path = substr($value, 5);

        return $stored[$path] ??= ParametricGenerator::storeArtwork(new UploadedFile($path, basename($path), mime_content_type($path) ?: null, null, true));
    }

    return $value;
};

foreach ($cases as $case) {
    $kind = (string) $case['kind'];
    $params = $pictures((array) $case['params']);
    $copies = max(1, (int) ($case['copies'] ?? 1));
    $row = ['name' => $case['name'], 'copies' => $copies];
    try {
        $whole = $tools->build($kind, $params);
        $notes = $whole['meta']['notes'];
        $size = $whole['meta']['bbox'];
        $row += ['size' => [$size['x'], $size['y'], $size['z']], 'filaments' => $notes['filaments'] ?? 1, 'multi' => ! empty($notes['multi_material']), 'one_plate' => ! empty($notes['one_plate_required']),
            'changes_by_height' => array_map(fn ($c) => $c['z'], (array) ($notes['color_changes'] ?? [])), 'warnings' => $notes['warnings'] ?? []];
        // where the copies stand: a grid round the middle of the plate, 4 mm apart
        $cols = (int) ceil(sqrt($copies));
        $rows = (int) ceil($copies / $cols);
        $stepX = $size['x'] + 4;
        $stepY = $size['y'] + 4;
        $instances = [];
        for ($i = 0; $i < $copies; $i++) {
            $instances[] = [$bed / 2 - ($cols * $stepX - 4) / 2 + ($i % $cols) * $stepX, $bed / 2 - ($rows * $stepY - 4) / 2 + intdiv($i, $cols) * $stepY];
        }
        $base = new SliceParams('PLA', 'standard', 15, false);
        $objects = [];
        $filaments = [];
        $temp = [];
        if (! empty($notes['multi_material'])) {
            // parts that share layers: each where the whole has it, a filament per colour
            $codes = [];
            $colours = ['body' => $notes['body_color'] ?? null, 'rim' => $notes['rim_color'] ?? null];
            foreach (array_merge((array) ($notes['colors'] ?? []), (array) ($notes['layers'] ?? []), array_filter((array) ($notes['decor'] ?? []), fn ($d) => ! empty($d['part']))) as $c) {
                $colours[$c['part']] = ['code' => $c['code'], 'hex' => $c['hex']];
            }
            foreach ((array) $notes['parts'] as $part) {
                $one = $tools->build($kind, $params, $part);
                $code = (string) ($colours[$part]['code'] ?? $part);
                if (! isset($codes[$code])) {
                    $codes[$code] = count($codes) + 1;
                    $filaments[] = ['profile' => 'filament_pla.json', 'overrides' => [], 'colour' => (string) ($colours[$part]['hex'] ?? '#888888'), 'volume_mm3' => 0.0];
                }
                $filaments[$codes[$code] - 1]['volume_mm3'] += (float) $one['meta']['volume_mm3'] * $copies;
                $objects[] = ['name' => $part, 'stl' => $one['path'], 'extruder' => $codes[$code]];
                $temp[] = $one['path'];
            }
            $row['parts'] = array_map(fn ($o) => $o['name'].'→T'.($o['extruder'] - 1), $objects);
        } else {
            $objects[] = ['name' => 'all', 'stl' => $whole['path'], 'extruder' => 1];
        }
        $project = sys_get_temp_dir().'/mp_decor_slice_'.bin2hex(random_bytes(5)).'.3mf';
        ThreeMfWriter::write($project, $objects, [], $instances);
        array_map('unlink', $temp);
        $run = $filaments
            ? $base->withFarmProfile(['machine' => 'machine.json', 'process' => 'process_standard.json', 'filament' => 'filament_pla.json'], [])->withFilaments($filaments, $project, PrepareFarmOrder::ONE_PLATE_PROCESS)
            : $base->withFarmProfile(['machine' => 'machine.json', 'process' => 'process_standard.json', 'filament' => 'filament_pla.json'], [])->withProject($project);
        $t = microtime(true);
        $r = $slicer->slice($whole['path'], $run->withoutThinning());
        $gcode = (string) file_get_contents((string) $r->gcodePath);
        $row += ['slice_s' => round(microtime(true) - $t, 1), 'minutes' => $r->minutes, 'grams' => round($r->grams, 2), 'grams_by_filament' => array_map(fn ($g) => round($g, 2), $r->filamentGrams),
            'changes' => $filaments ? $r->changes : count((array) ($notes['color_changes'] ?? [])), 'layers' => $r->layers, 'supports' => $r->supportsUsed,
            'tower' => preg_match('/^; ?(FEATURE|TYPE): ?Prime tower/mi', $gcode) === 1,
            'time' => preg_match('/; (?:model printing time|estimated printing time \(normal mode\))[^=]*= *([^\r\n;]+)/', $gcode, $m) ? trim($m[1]) : null];
        if ($keep && $r->gcodePath) {
            copy($r->gcodePath, $keep.'/'.preg_replace('/[^a-z0-9]+/i', '-', (string) $case['name']).'.gcode');
        }
        @unlink($project);
        @unlink($whole['path']);
    } catch (Throwable $e) {
        // (the project the slicer refused stays for a look by hand)
        if ($keep && isset($project) && is_file($project)) {
            rename($project, $keep.'/'.preg_replace('/[^a-z0-9]+/i', '-', (string) $case['name']).'.3mf');
        }
        $row['error'] = mb_substr($e->getMessage(), 0, 200)." … ".mb_substr($e->getMessage(), -500);
    }
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
}
