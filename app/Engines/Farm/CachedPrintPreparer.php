<?php

namespace App\Engines\Farm;

use App\Engines\Contracts\PrintPreparer;
use App\Engines\DTO\Dimensions;
use App\Engines\DTO\PreparedMesh;
use Illuminate\Support\Facades\File;

/**
 * The same model, scale, plate and tool give the same prepared STL: another quality, strength or colour slices it
 * again but never repairs and turns it again (a damaged 611k-triangle model takes two minutes to repair, order
 * F26-000019). The repair itself is never simplified: what is kept is its full result.
 */
final class CachedPrintPreparer implements PrintPreparer
{
    public function __construct(private readonly PrintPreparer $inner, private readonly string $dir, private readonly int $keepDays = 14) {}

    public function name(): string
    {
        return $this->inner->name();
    }

    public function prepare(string $stlPath, string $outPath, float $unitScale, Dimensions $bed, bool $keepPose = false): PreparedMesh
    {
        $key = $this->key($stlPath, $unitScale, $bed, $keepPose);
        $stl = $this->dir.'/'.$key.'.stl';
        $json = $this->dir.'/'.$key.'.json';
        if (is_file($stl) && is_file($json) && is_array($m = json_decode((string) File::get($json), true))) {
            File::ensureDirectoryExists(dirname($outPath));
            File::copy($stl, $outPath);
            @touch($stl);   // still in use: the pruning counts from the last use
            @touch($json);

            return new PreparedMesh($outPath, $m['watertight'], (bool) $m['was_watertight'], (bool) $m['repaired'], (int) $m['open_edges'],
                Dimensions::fromArray($m['bbox']), (float) $m['volume_mm3'], (float) $m['area_mm2'], (int) $m['triangles'], (int) $m['shells'],
                (array) $m['orientation'] + ['reused' => true], (string) $m['engine']);
        }

        $mesh = $this->inner->prepare($stlPath, $outPath, $unitScale, $bed, $keepPose);
        try {
            File::ensureDirectoryExists($this->dir);
            File::copy($outPath, $stl);
            File::put($json, json_encode($mesh->toArray()));
            $this->prune();
        } catch (\Throwable) {
            // a full disk or a race with another worker: the order has its model, only the shortcut is missing
        }

        return $mesh;
    }

    private function key(string $stlPath, float $unitScale, Dimensions $bed, bool $keepPose): string
    {
        $tools = '';
        foreach (['farm_tool.py', 'repair_tool.py'] as $f) {
            $tools .= @md5_file(base_path('engines/python/'.$f));   // a changed tool prepares every model again
        }

        return sha1(implode('|', [md5_file($stlPath), round($unitScale, 6), $bed->x, $bed->y, $bed->z, (int) $keepPose, $this->inner->name(), $tools]));
    }

    private function prune(): void
    {
        if (random_int(1, 20) !== 1) {
            return;
        }
        $limit = time() - $this->keepDays * 86400;
        foreach (File::glob($this->dir.'/*') as $f) {
            if (@filemtime($f) < $limit) {
                @unlink($f);
            }
        }
    }
}
