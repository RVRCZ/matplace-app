<?php

namespace App\Engines\Project;

use App\Engines\Contracts\ProjectExporter;
use App\Engines\DTO\SliceParams;
use App\Engines\Exceptions\EngineException;
use App\Engines\Mesh\StlFile;
use App\Engines\Repair\PythonTool;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * 3MF projects PrusaSlicer opens with the printer and settings selected, built from Prusa's public vendor bundle
 * (PrusaResearch.ini). PrusaSlicer itself is not needed on the server: the project carries the flattened presets.
 */
final class PrusaProjectExporter implements ProjectExporter
{
    public function __construct(private readonly array $config, private readonly PythonTool $python) {}

    public function name(): string
    {
        return 'prusaslicer';
    }

    public function printers(): array
    {
        return array_map(fn (array $p) => [
            'id' => $p['id'], 'vendor' => $p['vendor'], 'vendor_label' => 'Prusa', 'model' => $p['model'], 'slicer' => 'prusaslicer',
            'bed' => $p['bed'], 'materials' => array_keys($p['filaments']),
        ], $this->catalog());
    }

    /** @return array<int, array<string, mixed>> */
    public function catalog(): array
    {
        $file = $this->config['catalog'];

        return is_file($file) ? (json_decode((string) file_get_contents($file), true)['printers'] ?? []) : [];
    }

    /** Download the newest vendor bundle (unless $download is false) and rebuild the catalogue. */
    public function rebuildCatalog(bool $download = true): array
    {
        $bundle = $this->config['bundle'];
        File::ensureDirectoryExists(dirname($bundle));
        $version = null;
        if ($download) {
            $base = rtrim($this->config['repository'], '/');
            $idx = Http::timeout(20)->get($base.'/index.idx');
            if ($idx->successful() && preg_match('/^(\d+\.\d+\.\d+)\s/m', $idx->body(), $m)) {
                $ini = Http::timeout(60)->get($base.'/'.$m[1].'.ini');
                if ($ini->successful() && str_contains($ini->body(), '[printer:')) {
                    File::put($bundle, $ini->body());
                    $version = $m[1];
                }
            }
        }
        if (! is_file($bundle)) {
            throw new EngineException('Prusa vendor bundle is missing and could not be downloaded.');
        }
        $r = $this->python->runScript('prusa_profiles.py', ['catalog', $bundle], 300);
        if (empty($r['ok'])) {
            throw new EngineException('Prusa catalogue failed: '.($r['error'] ?? 'unknown'));
        }
        File::put($this->config['catalog'], json_encode(['built_at' => now()->toIso8601String(), 'bundle_version' => $version, 'printers' => $r['printers']], JSON_UNESCAPED_UNICODE));

        return ['printers' => count($r['printers']), 'version' => $version];
    }

    public function export(string $stlPath, string $printerId, SliceParams $params, array $hints = []): string
    {
        $printer = collect($this->catalog())->firstWhere('id', $printerId);
        if (! $printer) {
            throw new EngineException('Unknown printer: '.$printerId);
        }
        $work = rtrim($this->config['work_dir'], '/').'/prusa_'.Str::random(12);
        File::ensureDirectoryExists($work);
        try {
            $mesh = $stlPath;
            if (abs($params->scale - 1.0) > 1e-6) {
                $mesh = $work.'/model.stl';
                StlFile::scale($stlPath, $mesh, $params->scale);
            }
            $kind = (string) ($hints['kind'] ?? '');
            // a lithophane needs the finest layers whatever the customer picked for price comparison
            $quality = $kind === 'lithophane' ? 'fine' : $params->quality;
            $out = rtrim($this->config['work_dir'], '/').'/projects/'.Str::uuid().'.3mf';
            File::ensureDirectoryExists(dirname($out));
            $r = $this->python->runScript('prusa_profiles.py', [
                'project', $this->config['bundle'], $printer['machine'],
                $printer['processes'][$quality] ?? $printer['processes']['standard'],
                $printer['filaments'][$params->materialCode] ?? $printer['filaments']['PLA'],
                $mesh, $out, json_encode(self::overrides($params, $hints)),
            ], 180);
            if (empty($r['ok']) || ! is_file($out)) {
                throw new EngineException('PrusaSlicer project failed: '.($r['error'] ?? 'unknown'));
            }

            return $out;
        } finally {
            File::deleteDirectory($work);
        }
    }

    /**
     * Customer choices + what this kind of model needs, in PrusaSlicer's own keys.
     *
     * @return array<string, string>
     */
    public static function overrides(SliceParams $p, array $hints): array
    {
        $kind = (string) ($hints['kind'] ?? '');
        $o = [
            'fill_density' => $p->infillPercent.'%',
            'support_material' => $p->supports ? '1' : '0',
            'support_material_auto' => '1',
            'spiral_vase' => $p->vaseMode ? '1' : '0',
        ];
        if ($p->supports && ($p->treeSupports || $kind === 'generated')) {
            $o['support_material_style'] = 'organic';
        }
        if ($p->infillPercent >= 100) {
            $o['fill_pattern'] = 'rectilinear';   // PrusaSlicer refuses other patterns at full density
        }
        if ($kind === 'lithophane') {
            $o = ['fill_density' => '100%', 'fill_pattern' => 'rectilinear', 'perimeters' => '12', 'support_material' => '0',
                'brim_width' => '5', 'brim_type' => 'outer_only', 'external_perimeter_speed' => '40'] + $o;
        }

        return $o;
    }
}
