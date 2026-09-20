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

    /** @return string absolute path of the normalised binary STL */
    /** @param  string[]  $options  clean = drop dust fragments, pedestal = add a flat round base (figures, busts) */
    public function toPrintableStl(string $inPath, string $outPath, float $targetMaxMm, bool $yUp = true, array $options = []): string
    {
        $targetMaxMm = max(5.0, min(1000.0, $targetMaxMm));
        $ext = strtolower(pathinfo($inPath, PATHINFO_EXTENSION));

        if ($this->python->available()) {
            $r = $this->python->run(['normalize', $inPath, $outPath, (string) $targetMaxMm, $yUp ? '1' : '0', implode(',', $options)]);
            if (! empty($r['ok']) && is_file($outPath)) {
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
