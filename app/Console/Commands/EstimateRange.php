<?php

namespace App\Console\Commands;

use App\Domain\Calculation\ShapeRange;
use App\Engines\Repair\PythonTool;
use App\Models\Calculation;
use App\Models\ModelFile;
use Illuminate\Console\Command;

/**
 * How the estimate on the first page meets the slice (TODO #41, docs/M.md §6). From a CSV of finished calculations
 * (the coordinator's export, docs/data/odhad-vs-rez-2026-10-25-prod.csv): how many slices landed inside the range the
 * page showed, and how many would inside the range of today's constants recounted from each row's shape; the widths
 * beside. `--export=N` writes such a CSV of the last N finished calculations from the database first (production: the
 * coordinator runs it, this session reads no production data); `--overhang` measures the overhang share of every
 * exported file that has none stored (engines/python/overhang_tool.py), so the overhang term can be calibrated on real
 * slices one day. Markdown tables, ready for the docs.
 */
class EstimateRange extends Command
{
    protected $signature = 'matplace:estimate-range {csv : the CSV to read (semicolons), or to write with --export}
        {--export= : write the last N finished calculations to the CSV instead of reading it}
        {--overhang : with --export, measure the overhang share of files that have none stored (seconds per big file)}';

    protected $description = 'Estimate against slice: hits and widths of the price and time ranges, from a CSV of calculations (--export writes one)';

    public const COLUMNS = ['calc_id', 'day', 'triangles', 'bbox_x', 'bbox_y', 'bbox_z', 'quality', 'infill', 'supports_param', 'scale', 'rough_grams', 'rough_minutes_farm', 'rough_minutes_min', 'rough_minutes_max', 'rough_price_min', 'rough_price_max', 'rough_price', 'slicer_grams', 'slicer_minutes', 'slicer_supports_used', 'final_price', 'slice_s', 'decimate_s',
        // since 25 Oct 2026: what the estimate saw of the shape, for the next calibration
        'volume_mm3', 'area_mm2', 'shell_mm3', 'material_mm3', 'overhang_share'];

    public function handle(ShapeRange $range): int
    {
        $path = (string) $this->argument('csv');
        if ($this->option('export') !== null) {
            return $this->export($path, max(1, (int) $this->option('export')), (bool) $this->option('overhang'));
        }
        if (! is_file($path)) {
            $this->error("No such file: $path");

            return self::FAILURE;
        }
        $rows = $this->read($path);
        if (! $rows) {
            $this->error('No usable rows (a row needs bbox, rough grams and minutes, rough price, slicer minutes and final price).');

            return self::FAILURE;
        }
        $step = max(1, (int) config('pricing.round_to', 1));
        foreach ($rows as &$r) {
            // the range of today's constants from what the row says of the shape (without an overhang column the slicer's choice counts as "auto")
            $f = $range->factors($r['bbox_z'] * $r['scale'], $r['fill'], $r['overhang'], $r['supports'], (int) round($r['rough_minutes']), $r['triangles'] > 0 ? (int) $r['triangles'] : null);
            $r['new_price'] = [floor($r['rough_price'] * $f['price'][0] / $step) * $step, ceil($r['rough_price'] * $f['price'][1] / $step) * $step];
            $r['new_time'] = [$r['rough_minutes'] * $f['time'][0], $r['rough_minutes'] * $f['time'][1]];
        }
        unset($r);

        $this->line(sprintf('%d calculations, %d distinct shapes (%s).', count($rows), count(array_unique(array_column($rows, 'shape'))), basename($path)));
        $this->newLine();
        $this->line('| what | inside | above / below | median hi/lo | p90 hi/lo | shapes fully inside |');
        $this->line('|---|---|---|---|---|---|');
        $stored = array_filter($rows, fn ($r) => $r['stored_price'] !== null);
        if ($stored) {
            $this->line(self::summary($stored, 'price, the range the page showed', fn ($r) => $r['stored_price'], fn ($r) => $r['final_price']));
        }
        $this->line(self::summary($rows, 'price, the range by shape of today\'s constants', fn ($r) => $r['new_price'], fn ($r) => $r['final_price']));
        $storedTime = array_filter($rows, fn ($r) => $r['stored_time'] !== null);
        if ($storedTime) {
            $this->line(self::summary($storedTime, 'time, the range the page showed', fn ($r) => $r['stored_time'], fn ($r) => $r['slicer_minutes']));
        }
        $this->line(self::summary($rows, 'time, the range by shape of today\'s constants', fn ($r) => $r['new_time'], fn ($r) => $r['slicer_minutes']));
        $this->newLine();
        $this->line('| class | n | price inside | price median hi/lo | time inside | time median hi/lo |');
        $this->line('|---|---|---|---|---|---|');
        $classes = [
            'flat, under 20 mm' => fn ($r) => $r['bbox_z'] * $r['scale'] < 20,
            'tall, 80 mm and more' => fn ($r) => $r['bbox_z'] * $r['scale'] >= 80,
            'thin, under 0.1 g/cm³ of its box' => fn ($r) => $r['fill'] < 0.1,
            'compact, 0.3 g/cm³ and more' => fn ($r) => $r['fill'] >= 0.3,
            'supports on' => fn ($r) => $r['supports'] === true,
            'supports left to the slicer' => fn ($r) => $r['supports'] === null,
            'supports off' => fn ($r) => $r['supports'] === false,
            'small, estimate under 30 min' => fn ($r) => $r['rough_minutes'] < 30,
            '200 000 to a million triangles' => fn ($r) => $r['triangles'] >= 2e5 && $r['triangles'] < 1e6,
            'over a million triangles' => fn ($r) => $r['triangles'] >= 1e6,
        ];
        foreach ($classes as $label => $sel) {
            $sub = array_values(array_filter($rows, $sel));
            if (! $sub) {
                continue;
            }
            $p = self::stats($sub, fn ($r) => $r['new_price'], fn ($r) => $r['final_price']);
            $t = self::stats($sub, fn ($r) => $r['new_time'], fn ($r) => $r['slicer_minutes']);
            $this->line(sprintf('| %s | %d | %.1f %% | %.2f | %.1f %% | %.2f |', $label, count($sub), $p['inside'] * 100, $p['median'], $t['inside'] * 100, $t['median']));
        }

        return self::SUCCESS;
    }

    /** One line of the first table. */
    private static function summary(array $rows, string $label, callable $lohi, callable $value): string
    {
        $s = self::stats($rows, $lohi, $value);

        return sprintf('| %s | %d/%d (%.1f %%) | %d / %d | %.2f | %.2f | %.1f %% |', $label, $s['hits'], count($rows), $s['inside'] * 100, $s['above'], $s['below'], $s['median'], $s['p90'], $s['shapes'] * 100);
    }

    /** @return array{hits: int, inside: float, above: int, below: int, median: float, p90: float, shapes: float} */
    private static function stats(array $rows, callable $lohi, callable $value): array
    {
        $hits = $above = $below = 0;
        $widths = [];
        $shapes = [];
        foreach ($rows as $r) {
            [$lo, $hi] = $lohi($r);
            $v = $value($r);
            $ok = $lo <= $v && $v <= $hi;
            $hits += $ok ? 1 : 0;
            $above += $v > $hi ? 1 : 0;
            $below += $v < $lo ? 1 : 0;
            $widths[] = $lo > 0 ? $hi / $lo : INF;
            $shapes[$r['shape']] = ($shapes[$r['shape']] ?? true) && $ok;
        }
        sort($widths);
        $n = count($widths);

        return [
            'hits' => $hits, 'inside' => $hits / max(1, count($rows)), 'above' => $above, 'below' => $below,
            'median' => $widths[intdiv($n - 1, 2)], 'p90' => $widths[(int) floor(0.9 * ($n - 1))],
            'shapes' => count($shapes) ? count(array_filter($shapes)) / count($shapes) : 0.0,
        ];
    }

    /** The CSV's rows the comparison can use, with the shape features counted (no scale column = scale 1, no overhang column = unknown). */
    private function read(string $path): array
    {
        $fh = fopen($path, 'r');
        $head = fgetcsv($fh, 0, ';');
        $rows = [];
        while (($line = fgetcsv($fh, 0, ';')) !== false) {
            if (count($line) < count($head)) {
                continue;
            }
            $r = array_combine($head, array_map('trim', $line));
            $num = fn (string $k) => isset($r[$k]) && $r[$k] !== '' && $r[$k] !== 'null' ? (float) $r[$k] : null;
            $need = [$num('bbox_x'), $num('bbox_y'), $num('bbox_z'), $num('rough_grams'), $num('rough_minutes_farm'), $num('rough_price'), $num('slicer_minutes'), $num('final_price')];
            if (in_array(null, $need, true) || $need[4] <= 0 || $need[5] <= 0) {
                continue;
            }
            [$x, $y, $z, $grams, $minutes, $price, $sliced, $final] = $need;
            $scale = $num('scale') ?: 1.0;
            $sp = $r['supports_param'] ?? 'null';
            $rows[] = [
                'id' => (int) ($r['calc_id'] ?? 0), 'triangles' => $num('triangles') ?? 0.0, 'bbox_z' => $z, 'scale' => $scale,
                'fill' => ShapeRange::fill($grams, ['x' => $x, 'y' => $y, 'z' => $z], $scale),
                'overhang' => $num('overhang_share'),
                'supports' => in_array($sp, ['true', '1'], true) ? true : (in_array($sp, ['false', '0'], true) ? false : null),
                'rough_minutes' => $minutes, 'rough_price' => $price, 'slicer_minutes' => $sliced, 'final_price' => $final,
                'stored_price' => $num('rough_price_min') !== null && $num('rough_price_max') !== null ? [$num('rough_price_min'), $num('rough_price_max')] : null,
                'stored_time' => $num('rough_minutes_min') !== null && $num('rough_minutes_max') !== null ? [$num('rough_minutes_min'), $num('rough_minutes_max')] : null,
                'shape' => implode('|', [$r['triangles'] ?? '', round($x), round($y), round($z)]),
            ];
        }
        fclose($fh);

        return $rows;
    }

    /** The last N finished calculations with their estimates and slices, in the columns of the coordinator's export plus scale, the time range and the shape. */
    private function export(string $path, int $n, bool $overhang): int
    {
        $calcs = Calculation::query()->with('modelFile')->where('status', Calculation::STATUS_DONE)->whereNotNull('slicer')->whereNotNull('rough')->latest('id')->limit($n)->get();
        $fh = fopen($path, 'w');
        fputcsv($fh, self::COLUMNS, ';');
        $count = 0;
        $measured = [];   // overhang share by file: the same file calculated ten times is measured once
        foreach ($calcs as $c) {
            $rough = (array) $c->rough;
            $slicer = (array) $c->slicer;
            $prices = (array) ($c->prices ?? []);
            $params = (array) $c->params;
            $file = $c->modelFile;
            $bbox = (array) ($file?->bbox ?? []);
            $sp = $params['supports'] ?? null;
            $share = $rough['shape']['overhang_share'] ?? null;
            if ($share === null && $overhang && $file) {
                $share = $measured[$file->id] ??= $this->measureOverhang($file);
            }
            fputcsv($fh, [
                $c->id, $c->created_at?->toDateString(), $file?->triangles, $bbox['x'] ?? null, $bbox['y'] ?? null, $bbox['z'] ?? null,
                $params['quality'] ?? null, $params['infill'] ?? null, $sp === null ? 'null' : ($sp ? 'true' : 'false'), $params['scale'] ?? 1,
                $rough['grams'] ?? null, $rough['prices'][0]['minutes'] ?? $rough['minutes'] ?? null, $rough['minutes_min'] ?? null, $rough['minutes_max'] ?? null,
                $rough['price_min'] ?? null, $rough['price_max'] ?? null, $rough['prices'][0]['total'] ?? null,
                $slicer['grams'] ?? null, $slicer['minutes'] ?? null, ! empty($slicer['supports_used']) ? 1 : 0, $prices[0]['total'] ?? null,
                $c->timings['slice_s'] ?? null, $c->timings['decimate_s'] ?? null,
                $file?->volume_mm3, $file?->area_mm2, $rough['shell_mm3'] ?? null, $rough['material_mm3'] ?? null, $share,
            ], ';');
            $count++;
        }
        fclose($fh);
        $this->info("$count calculations written to $path".($overhang ? ', '.count(array_filter($measured, fn ($v) => $v !== null)).' files measured for overhangs' : ''));

        return self::SUCCESS;
    }

    /** The overhang share of a stored file, the way the browser measures it; null when the file is gone or the tool fails. */
    private function measureOverhang(ModelFile $file): ?float
    {
        $path = $file->absoluteStlPath();
        if (! $path || ! is_file($path)) {
            return null;
        }
        try {
            $data = app(PythonTool::class)->runScript('overhang_tool.py', [$path], 180);

            return isset($data['overhang_share']) ? (float) $data['overhang_share'] : null;
        } catch (\Throwable $e) {
            $this->warn("overhangs of file {$file->id}: ".$e->getMessage());

            return null;
        }
    }
}
