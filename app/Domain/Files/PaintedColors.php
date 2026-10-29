<?php

namespace App\Domain\Files;

use App\Domain\Calculation\MaterialCatalog;
use App\Engines\Repair\PythonTool;
use App\Models\ModelFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * The colours an uploaded 3MF is painted in (TODO #50, docs/AL.md §14): Bambu Studio, OrcaSlicer and Anycubic Slicer
 * Next keep them as `paint_color` on every triangle, PrusaSlicer as `slic3rpe:mmu_segmentation`, older files as base
 * materials or an extruder per object (engines/python/colors_tool.py reads them all).
 *
 *   analyse    when the upload is processed (ProcessModelFile): the colours by area, the small ones (under
 *              engines.colors.merge_below per cent, and beyond FarmOrder::MAX_COLORS) joined to the colour of their
 *              body; the converted STL written again ordered by colour with `groups` (triangle ranges) for the viewer;
 *              how many 0.2 mm layers hold 2, 3, 4 colours (the colour changes of a one-plate print)
 *   parts      for the farm: one part per printed colour (color_1… by area), the painted areas as inlays 1.2 mm deep
 *              (the colour splitter of /tools/colors with the same merge), into files/<uuid>/parts where
 *              OrderService::partStl looks for parts; made once, when the order is prepared
 *
 * Stored on the file as `colors` (null for a file without colours):
 *   {v, status: done|failed, count, merged, too_many, colors: [{hex, share 0..1, merged, into?, extruder, sources}],
 *    groups: [{hex, from, to}]|null, layers: {"2": n, …}, height, split_triangles, ms, error?}
 */
final class PaintedColors
{
    public function __construct(private readonly PythonTool $python) {}

    /**
     * Reads the colours of a 3MF and orders its STL by them. Null when the file is not a 3MF; a failure is kept as
     * `status: failed` and never fails the upload (the model prints in one colour as before).
     *
     * @return array<string, mixed>|null
     */
    public function analyse(ModelFile $file, string $stlAbs): ?array
    {
        if ($file->ext !== '3mf') {
            return null;
        }
        $t = microtime(true);
        try {
            $r = $this->python->runScript('colors_tool.py', ['upload', $file->absolutePath(), $stlAbs, (string) json_encode([
                'merge_below' => (float) config('engines.colors.merge_below', 1.0), 'max_colors' => (int) config('engines.colors.max', 4),
            ])], (int) config('engines.colors.timeout', 180));
        } catch (\Throwable $e) {
            $r = ['ok' => false, 'error' => $e->getMessage()];
        }
        $ms = (int) round((microtime(true) - $t) * 1000);
        if (empty($r['ok'])) {
            Log::warning('3MF colours could not be read', ['file' => $file->id, 'error' => mb_substr((string) ($r['error'] ?? ''), 0, 300)]);

            return ['v' => 1, 'status' => 'failed', 'error' => mb_substr((string) ($r['code'] ?? $r['error'] ?? 'failed'), 0, 200), 'ms' => $ms];
        }
        if (empty($r['has_colors'])) {
            return null;   // one colour: nothing to show, nothing to change
        }

        return ['v' => 1, 'status' => 'done', 'count' => (int) $r['count'], 'merged' => (int) ($r['merged'] ?? 0), 'too_many' => (bool) ($r['too_many'] ?? false),
            'colors' => array_values((array) $r['colors']), 'groups' => $r['groups'] ?? null, 'layers' => (array) ($r['layers'] ?? []),
            'height' => (float) ($r['height'] ?? 0), 'split_triangles' => (int) ($r['split_triangles'] ?? 0), 'ms' => $ms];
    }

    /** The printed colours of a file painted in two or more (largest first), as hex. */
    public static function printed(ModelFile $file): array
    {
        $c = $file->colors;
        if (! is_array($c) || ($c['status'] ?? null) !== 'done' || (int) ($c['count'] ?? 0) < 2) {
            return [];
        }

        return array_values(array_column(array_filter((array) $c['colors'], fn ($x) => is_array($x) && empty($x['merged'])), 'hex'));
    }

    /** An upload (not a tool's design) painted in two printed colours or more: the farm prints it on one plate or not in colour. */
    public static function painted(?ModelFile $file): bool
    {
        return $file !== null && $file->origin !== 'tool' && self::printed($file) !== [];
    }

    /** The parts of a painted upload the farm prints: color_1… one per printed colour. */
    public static function partNames(ModelFile $file): array
    {
        $n = count(self::printed($file));

        return $n >= 2 ? array_map(fn ($i) => 'color_'.$i, range(1, $n)) : [];
    }

    /** The colour of one part (color_2 → the second printed colour), null when it is not one. */
    public static function partHex(ModelFile $file, string $part): ?string
    {
        // a design of /tools/colors: the colour its own report names for the part
        if ($file->origin === 'tool' && $file->origin_ref === 'colors') {
            $hex = collect((array) ($file->tool_params['report']['colors'] ?? []))->firstWhere('part', $part)['hex'] ?? null;

            return is_string($hex) ? $hex : null;
        }

        return preg_match('/^color_(\d{1,2})$/', $part, $m) ? (self::printed($file)[(int) $m[1] - 1] ?? null) : null;
    }

    /**
     * The parts of every printed colour as STL in files/<uuid>/parts (made once; the splitter takes about 10 s for the
     * turtle of 317 686 triangles). False when they cannot be made: the order then prints in one colour.
     */
    public function ensureParts(ModelFile $file): bool
    {
        $names = self::partNames($file);
        if (! $names) {
            return false;
        }
        $dir = Storage::disk(ModelFile::DISK)->path($file->dir().'/parts');
        $have = fn () => array_filter($names, fn ($n) => is_file($dir.'/'.$n.'.stl'));
        if (count($have()) === count($names)) {
            return true;
        }
        File::ensureDirectoryExists($dir);
        $out = $dir.'/all.stl';
        $t = microtime(true);
        try {
            $r = $this->python->runScript('edit_tool.py', ['colors', $file->absolutePath(), $out, (string) json_encode([
                'depth' => 1.2, 'in_place' => true, 'merge_below' => (float) config('engines.colors.merge_below', 1.0), 'max_colors' => (int) config('engines.colors.max', 4),
            ]), $dir], (int) config('engines.colors.timeout', 180) * 2);
        } catch (\Throwable $e) {
            $r = ['ok' => false, 'error' => $e->getMessage()];
        }
        $colors = (array) $file->colors;
        $colors['parts'] = ['ok' => ! empty($r['ok']) && count($have()) === count($names), 'ms' => (int) round((microtime(true) - $t) * 1000),
            'warnings' => (array) ($r['notes']['warnings'] ?? []), 'error' => empty($r['ok']) ? mb_substr((string) ($r['code'] ?? $r['error'] ?? ''), 0, 200) : null];
        $file->forceFill(['colors' => $colors])->save();
        if (! $colors['parts']['ok']) {
            Log::warning('3MF colour parts could not be made', ['file' => $file->id, 'error' => $colors['parts']['error'], 'have' => array_values($have())]);
        }

        return $colors['parts']['ok'];
    }

    /**
     * The painted 3MF itself as the project Orca slices on one plate (docs/AL.md §14): every colour mapped onto the
     * filament of its part (`$partFilament`: color_1 → 1-based filament), a merged colour onto its body's, scaled and
     * moved as the parts were on the plate, the project's purge matrix left to Orca (it computes one from the spools'
     * colours). Orca paints every layer exactly, no inlays: the turtle's yellow on its curved shell sliced as parts was
     * refused (the inlay hangs in the air on its own, the support check stops the slice), the painted file was not.
     * `$copies`: where every piece of the plate goes, as offsets from the first one (mm): the same object placed again.
     * False when it cannot be done (a colour from base materials has no extruder to map): the order then cannot print
     * in colour (PrepareFarmOrder says why and offers one colour).
     *
     * @param  array<string, int>  $partFilament
     * @param  array{0: float, 1: float, 2: float}  $move
     * @param  list<array{0: float, 1: float, 2: float}>  $copies
     */
    public function project(ModelFile $file, string $out, float $scale, array $move, array $partFilament, array $copies = [[0.0, 0.0, 0.0]]): bool
    {
        $c = $file->colors;
        $printed = self::printed($file);
        if ($file->ext !== '3mf' || ! $printed || ! is_array($c)) {
            return false;
        }
        $map = [];
        foreach ((array) $c['colors'] as $color) {
            $extruder = (int) ($color['extruder'] ?? 0);
            if ($extruder < 1) {
                return false;   // a colour from the file's materials: no extruder to map
            }
            $hex = empty($color['merged']) ? $color['hex'] : ($color['into'] ?? $printed[0]);
            $k = array_search($hex, $printed, true);
            $filament = $k === false ? null : ($partFilament['color_'.($k + 1)] ?? null);
            if (! $filament) {
                return false;
            }
            $map[(string) $extruder] = (int) $filament;
        }
        try {
            $r = $this->python->runScript('colors_tool.py', ['repaint', $file->absolutePath(), $out, (string) json_encode([
                'map' => $map, 'scale' => $scale, 'move' => array_values($move), 'keep_project' => true,
                'copies' => array_map(fn ($c) => array_map('floatval', array_values($c)), array_values($copies)),
            ])], (int) config('engines.colors.timeout', 180));
        } catch (\Throwable $e) {
            $r = ['ok' => false, 'error' => $e->getMessage()];
        }
        if (empty($r['ok']) || ! is_file($out)) {
            Log::warning('The painted 3MF could not be mapped onto the order\'s filaments', ['file' => $file->id, 'error' => mb_substr((string) ($r['error'] ?? ''), 0, 300)]);

            return false;
        }

        return true;
    }

    /**
     * The colour changes a one-plate print of the file needs and what they add per piece: one change per layer for every
     * colour beyond the first that the layer holds (the slicer ends a layer in the colour the next one starts with), from
     * the 0.2 mm layers the analysis counted, scaled to the quality's layer and the model's scale; each change flushes
     * `flush_mm3` into the tower and the infill and takes `change_minutes` (measured on the turtle with Orca 2.4 for the
     * S1 with ACE, docs/AL.md §14.5). Null for a file not painted in two colours or more.
     *
     * @return array{count: int, changes: int, grams: float, minutes: int}|null
     */
    public static function extra(ModelFile $file, string $quality = 'standard', float $scale = 1.0, string $material = 'PLA'): ?array
    {
        $c = $file->colors;
        if (self::printed($file) === []) {
            return null;
        }
        $layer = (float) (config('pricing.quality_layer_mm.'.$quality) ?: 0.2);
        $per02 = 0;
        foreach ((array) ($c['layers'] ?? []) as $k => $n) {
            $per02 += max(0, (int) $k - 1) * (int) $n;
        }
        $changes = (int) round($per02 * 0.2 / $layer * max(0.1, $scale));
        $density = app(MaterialCatalog::class)->density($material);
        $grams = $changes * (float) config('engines.colors.flush_mm3', 30) / 1000.0 * $density + (float) config('engines.colors.tower_g', 0.5);

        return ['count' => count(self::printed($file)), 'changes' => $changes, 'grams' => round($grams, 1), 'minutes' => (int) ceil($changes * (float) config('engines.colors.change_minutes', 2.2))];
    }

    /**
     * What the pages show of the colours: the list by area (share 0..1, merged ones with the colour they joined), the
     * preview's triangle ranges and the counts. Null for a file without colours or whose colours could not be read.
     *
     * @return array{colors: list<array<string, mixed>>, groups: ?list<array<string, mixed>>, info: array<string, mixed>}|null
     */
    public static function describe(ModelFile $file): ?array
    {
        $c = $file->colors;
        if (! is_array($c) || ($c['status'] ?? null) !== 'done') {
            return null;
        }

        return [
            'colors' => array_map(fn ($x) => array_filter(['hex' => $x['hex'], 'share' => (float) $x['share'], 'merged' => (bool) ($x['merged'] ?? false), 'into' => $x['into'] ?? null], fn ($v) => $v !== null), (array) $c['colors']),
            'groups' => $c['groups'] ?? null,
            'info' => ['count' => (int) $c['count'], 'merged' => (int) ($c['merged'] ?? 0), 'too_many' => (bool) ($c['too_many'] ?? false), 'status' => 'done'],
        ];
    }
}
