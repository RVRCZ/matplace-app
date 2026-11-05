<?php

namespace App\Engines\Slicer;

use App\Domain\Calculation\RoughEstimator;
use App\Engines\Contracts\Slicer;
use App\Engines\DTO\Dimensions;
use App\Engines\DTO\SliceParams;
use App\Engines\DTO\SliceResult;
use App\Engines\Mesh\StlFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Deterministic slicer for tests and local development without OrcaSlicer.
 * Uses real geometry (volume, area) and the rough estimator, so numbers are plausible and repeatable.
 */
final class FakeSlicer implements Slicer
{
    public function __construct(private readonly RoughEstimator $rough) {}

    public function name(): string
    {
        return 'fake';
    }

    public function supportedFormats(): array
    {
        return ['stl'];
    }

    public function slice(string $meshPath, SliceParams $params): SliceResult
    {
        $stats = StlFile::stats($meshPath);
        $est = $this->rough->estimate(
            volumeMm3: $stats->volumeMm3,
            areaMm2: $stats->areaMm2,
            materialCode: $params->materialCode,
            quality: $params->quality,
            infillPercent: $params->infillPercent,
            supports: (bool) $params->supports,
            scale: $params->scale,
        );

        $grams = round($est['grams'] * 1.03, 1); // "precise" differs slightly from rough on purpose
        $minutes = (int) round($est['minutes'] * 0.97);
        if (($params->overrides['process']['ironing_type'] ?? 'no ironing') !== 'no ironing') {
            $minutes = (int) round($minutes * 1.15);   // the ironing pass over the top surfaces takes its minutes, as a real slice does
        }
        $meters = round($grams / 1.24 / (M_PI * 0.875 ** 2), 2); // 1.75 mm PLA
        $layers = max(1, (int) ceil($stats->bbox->z * $params->scale / ['draft' => 0.28, 'standard' => 0.2, 'fine' => 0.12][$params->quality]));
        // a plate of several filaments: the grams split by the parts' volumes (a prime tower adds a tenth), a change on
        // every layer for every filament after the first – the shape a real file has (OrcaSlicer, 19 Oct 2026)
        $byFilament = [];
        $changes = 0;
        $tools = [0];
        if ($params->filaments) {
            $volumes = array_map(fn ($f) => max(0.0, (float) ($f['volume_mm3'] ?? 0)), array_values($params->filaments));
            $sum = array_sum($volumes) ?: count($volumes);
            $grams = round($grams * 1.1, 1);
            foreach ($volumes as $v) {
                $byFilament[] = round($grams * ($sum === count($volumes) && ! array_sum($volumes) ? 1 / count($volumes) : $v / $sum), 1);
            }
            // a tool changer's list has placeholders between the tools it prints from (nothing of them, 0.00 in the header, no
            // change for them, docs/AM.md §12j): the changes go among the filaments with a volume (all of them when none has one)
            $tools = array_keys(array_filter($volumes, fn ($v) => $v > 0)) ?: array_keys($volumes);
            $changes = (count($tools) - 1) * $layers;
            $minutes += $changes * 3;   // the ACE takes about three minutes a change
        }

        return new SliceResult(
            grams: $grams,
            minutes: $minutes,
            dims: $stats->bbox->scaled($params->scale),
            supportsUsed: (bool) $params->supports,
            gcodePath: $params->keepGcode ? $this->stubGcode($grams, $meters, $minutes, $byFilament, $changes, $layers, $tools) : null,
            warnings: [],
            raw: ['engine' => 'fake'],
            meters: $meters,
            minutesByMode: ['normal' => $minutes, 'silent' => (int) round($minutes * 1.7), 'sport' => (int) round($minutes * 0.86)],
            layers: $layers,
            filamentGrams: $byFilament,
            changes: $changes,
        );
    }

    /**
     * The farm stores and ships the G-code, so the fake engine produces a file with the same header lines a real one has;
     * a plate of several filaments gets the tool changes, the per-filament figures and the CONFIG lists the agent reads.
     *
     * @param  list<float>  $byFilament
     * @param  list<int>  $tools  the filaments the plate prints from (T<n>), the first one first
     */
    private function stubGcode(float $grams, float $meters, int $minutes, array $byFilament = [], int $changes = 0, int $layers = 1, array $tools = [0]): string
    {
        $path = rtrim((string) config('engines.orca.work_dir'), '/').'/gcode/'.Str::uuid().'.gcode';
        File::ensureDirectoryExists(dirname($path));
        $n = max(1, count($byFilament));
        $first = $tools[0] ?? 0;
        $body = '';
        foreach (range(1, $byFilament ? $layers : 1) as $layer) {
            $z = round($layer * 0.2, 2);
            $body .= ";LAYER_CHANGE\n;Z:{$z}\n;HEIGHT:0.2\nG1 Z{$z} F600\nG1 X10 Y10 F3000\n";
            foreach (array_slice($tools, 1) as $t) {
                $body .= "; CP TOOLCHANGE START\nT{$t}\nM104 S205\nG1 X20 Y20 E1\n; CP TOOLCHANGE END\n";
            }
            if (count($tools) > 1) {
                $body .= "T{$first}\nM104 S215\nG1 X10 Y12 E1\n";
            }
        }
        $mm = $byFilament ? implode(', ', array_map(fn ($g) => round($g / 1.24 / (M_PI * 0.875 ** 2) * 1000, 2), $byFilament)) : round($meters * 1000, 2);
        $g = $byFilament ? implode(', ', $byFilament) : (string) $grams;
        File::put($path, "; HEADER_BLOCK_START\n; generated by FakeSlicer\n; total layer number: {$layers}\n; HEADER_BLOCK_END\nG9111 bedTemp=55 extruderTemp=220\nM117\nT{$first}\nG1 X10 Y10 F3000\n".$body
            ."; filament used [mm] = {$mm}\n; filament used [g] = {$g}\n; total filament used [g] = {$grams}\n".($byFilament ? "; total filament change = {$changes}\n" : '')
            .'; estimated printing time (normal mode) = '.intdiv($minutes, 60).'h '.($minutes % 60)."m 0s\n"
            .'; filament_colour = '.implode(';', array_fill(0, $n, '#F2754E'))."\n; filament_type = ".implode(';', array_fill(0, $n, 'PLA'))."\n");

        return $path;
    }

    public function measure(string $meshPath): Dimensions
    {
        return StlFile::stats($meshPath)->bbox;
    }
}
