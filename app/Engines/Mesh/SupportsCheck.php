<?php

namespace App\Engines\Mesh;

use App\Engines\Exceptions\SupersededException;
use App\Engines\Repair\ScriptRunner;
use Illuminate\Support\Facades\Log;

/**
 * Does a model, standing as it will be printed, need supports at all (engines/python/supports_tool.py, docs/AL.md §22)?
 * The farm slices an "automatic" order with tree supports switched on and lets the slicer find the overhangs. The check
 * reads the triangles in half a second and says "not needed" only when nothing of the model can hang in the air;
 * whatever is unclear, and whatever goes wrong with the check itself, is "needed" and the order is sliced as before.
 *
 * Switched off as shipped (config engines.mesh.supports_check): Orca 2.5 turned out to lose next to nothing on a model
 * that needs no supports (6.6 s against 6.2 s and 20.4 s against 20.0 s on meshes of 350 000 triangles, 12 Nov 2026) –
 * the seconds supports cost go into building the ones a model really needs.
 */
final class SupportsCheck
{
    public function __construct(private readonly ScriptRunner $python, private readonly bool $enabled = true, private readonly int $timeout = 20) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param  string  $stlPath  the piece as the slicer gets it: binary STL, standing on the plate
     * @param  float  $thresholdDeg  the slicer's support threshold (faces flatter than this from the horizontal get supports)
     * @param  float  $layerMm  the layer of the slice (what lies within half of it above the lowest point is on the plate)
     * @return array{needed: bool, reason: string, seconds: float} `seconds` is the whole wait, the interpreter's start included
     *
     * @throws SupersededException when the order asked for another slice meanwhile (RunGuard)
     */
    public function check(string $stlPath, float $thresholdDeg, float $layerMm): array
    {
        $start = microtime(true);
        try {
            $r = $this->python->runScript('supports_tool.py', ['check', $stlPath, (string) $thresholdDeg, (string) $layerMm], $this->timeout);
        } catch (SupersededException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Supports check failed, the slicer decides', ['file' => basename($stlPath), 'error' => mb_substr($e->getMessage(), 0, 300)]);

            return ['needed' => true, 'reason' => 'error', 'seconds' => round(microtime(true) - $start, 2)];
        }
        // "not needed" only when the tool says so in as many words
        $needed = ! (($r['ok'] ?? false) === true && ($r['needed'] ?? true) === false);

        return ['needed' => $needed, 'reason' => (string) ($r['reason'] ?? 'unknown'), 'seconds' => round(microtime(true) - $start, 2)];
    }
}
