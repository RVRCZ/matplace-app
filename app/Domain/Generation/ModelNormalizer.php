<?php

namespace App\Domain\Generation;

use App\Engines\Exceptions\EngineException;
use App\Engines\Mesh\StlFile;
use App\Engines\Repair\PythonTool;

/**
 * Generated meshes come unit-sized and Y-up (GLB). Printing needs millimetres and Z-up, standing on the bed.
 * Python/trimesh does the real work; a pure-PHP path covers STL input when Python is missing (tests, dev).
 */
final class ModelNormalizer
{
    public function __construct(private readonly PythonTool $python) {}

    /** What the tool said about the last model it wrote (the base it got, the thinnest place of a pet figurine…). */
    public array $report = [];

    /**
     * Where the coloured GLB lies over the file the last normalisation wrote (what ModelFile.tool_params.glb keeps), from the
     * 4x4 matrices of the tool's report. $parent: the same record of the file the input came from, and $key which of its
     * matrices belongs to that input (matrix = the printed STL itself, to_full, to_source); the new matrices are then
     * composed with it, so they always lead from the coordinates of the original GLB. Null when nothing was measured
     * or the parent has no matrix (old files: the browser fits the colours by estimate).
     *
     * @return array{matrix: list<float>, base_top_z: float, to_full?: list<float>, to_source?: list<float>}|null
     */
    public function glbFit(?array $parent = null, string $key = 'matrix'): ?array
    {
        $r = $this->report;
        if (! is_array($r['to_out'] ?? null) || count($r['to_out']) !== 16) {
            return null;
        }
        $base = null;
        if ($parent !== null) {
            $base = $parent[$key] ?? null;
            if (! is_array($base) || count($base) !== 16) {
                return null;
            }
        }
        $lead = fn (array $m): array => $base === null ? array_map('floatval', $m) : self::multiply($m, $base);
        $fit = ['matrix' => $lead($r['to_out']), 'base_top_z' => (float) ($r['base_top_z'] ?? 0)];
        foreach (['to_full', 'to_source'] as $k) {
            if (is_array($r[$k] ?? null) && count($r[$k]) === 16) {
                $fit[$k] = $lead($r[$k]);
            }
        }

        return $fit;
    }

    /** a × b of two 4x4 matrices (row-major, 16 numbers): b is applied first. */
    public static function multiply(array $a, array $b): array
    {
        $o = [];
        for ($i = 0; $i < 4; $i++) {
            for ($j = 0; $j < 4; $j++) {
                $s = 0.0;
                for ($k = 0; $k < 4; $k++) {
                    $s += (float) $a[$i * 4 + $k] * (float) $b[$k * 4 + $j];
                }
                $o[] = round($s, 7);
            }
        }

        return $o;
    }

    /** @return string absolute path of the normalised binary STL */
    /** @param  string[]  $options  clean = drop dust fragments, pedestal = add a flat round base (figures, busts) */
    public function toPrintableStl(string $inPath, string $outPath, float $targetMaxMm, bool $yUp = true, array $options = [], array $extras = []): string
    {
        $targetMaxMm = max(5.0, min(1000.0, $targetMaxMm));
        $this->report = [];
        $ext = strtolower(pathinfo($inPath, PATHINFO_EXTENSION));

        if ($this->python->available()) {
            if (! empty($extras['name']) || ! empty($extras['dedication'])) {
                // Montserrat at its boldest (shape2d.text reads a variable family so): sturdy strokes for a 0.4 mm nozzle
                // and the Czech accents; DejaVu stays as the spare when the file is not there
                $font = base_path('engines/fonts/Montserrat-wght.ttf');
                $extras['font'] = is_file($font) ? $font : base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
            }
            $r = $this->python->run(['normalize', $inPath, $outPath, (string) $targetMaxMm, $yUp ? '1' : '0', implode(',', $options), json_encode((object) $extras, JSON_UNESCAPED_UNICODE)]);
            if (! empty($r['ok']) && is_file($outPath)) {
                $this->report = $r;

                return $outPath;
            }
            if ($ext !== 'stl') {
                throw new EngineException('Normalisation failed: '.($r['error'] ?? 'unknown'));
            }
        } elseif ($ext !== 'stl') {
            throw new EngineException('Python/trimesh is required to convert .'.$ext.' results.');
        }

        // PHP fallback: uniform scale only
        $max = StlFile::stats($inPath)->bbox->max();
        StlFile::scale($inPath, $outPath, $max > 0 ? $targetMaxMm / $max : 1.0);

        return $outPath;
    }
}
