<?php

namespace App\Engines\Project;

use App\Engines\Contracts\ProjectExporter;
use App\Engines\DTO\SliceParams;
use App\Engines\Exceptions\EngineException;
use App\Engines\Mesh\StlFile;
use App\Engines\Repair\PythonTool;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * 3MF projects for OrcaSlicer / Bambu Studio and their forks, built from the vendor profiles that ship with OrcaSlicer.
 * The catalogue (which printers, which presets) is prepared by `php artisan matplace:printer-catalog`.
 */
final class OrcaProjectExporter implements ProjectExporter
{
    public function __construct(private readonly array $config, private readonly PythonTool $python) {}

    public function name(): string
    {
        return 'orca';
    }

    public function printers(): array
    {
        return array_map(fn (array $p) => [
            'id' => $p['id'], 'vendor' => $p['vendor'], 'vendor_label' => $p['vendor_label'], 'model' => $p['model'], 'slicer' => 'orca',
            'bed' => $p['bed'], 'materials' => array_keys($p['filaments']),
        ], $this->catalog());
    }

    /** @return array<int, array<string, mixed>> */
    public function catalog(): array
    {
        $file = $this->config['catalog'];
        if (! is_file($file)) {
            return [];
        }

        return json_decode((string) file_get_contents($file), true)['printers'] ?? [];
    }

    /** Rebuild the catalogue from the vendor profiles; with $verify every printer is test-exported and broken ones are dropped. */
    public function rebuildCatalog(bool $verify, ?\Closure $progress = null): array
    {
        $r = $this->python->runScript('orca_profiles.py', ['catalog', $this->config['vendor_profiles']], 300);
        if (empty($r['ok'])) {
            throw new EngineException('Printer catalogue failed: '.($r['error'] ?? 'unknown'));
        }
        $printers = $r['printers'];
        $dropped = [];
        if ($verify) {
            $cube = $this->workDir('verify').'/cube.stl';
            StlFile::writeBox($cube, 20, 20, 20);
            $ok = [];
            foreach ($printers as $p) {
                try {
                    $out = $this->run($cube, $p, new SliceParams('PLA'), []);
                    @unlink($out);
                    $ok[] = $p;
                } catch (\Throwable $e) {
                    $dropped[] = $p['id'];
                }
                $progress && $progress($p['id']);
            }
            File::deleteDirectory(dirname($cube));
            $printers = $ok;
        }
        File::ensureDirectoryExists(dirname($this->config['catalog']));
        File::put($this->config['catalog'], json_encode(['built_at' => now()->toIso8601String(), 'verified' => $verify, 'printers' => $printers], JSON_UNESCAPED_UNICODE));

        return ['printers' => count($printers), 'dropped' => $dropped];
    }

    public function export(string $stlPath, string $printerId, SliceParams $params, array $hints = []): string
    {
        $printer = collect($this->catalog())->firstWhere('id', $printerId);
        if (! $printer) {
            throw new EngineException('Unknown printer: '.$printerId);
        }

        return $this->run($stlPath, $printer, $params, $hints);
    }

    private function run(string $stlPath, array $printer, SliceParams $params, array $hints): string
    {
        if (! is_file($this->config['bin'])) {
            throw new EngineException('OrcaSlicer binary not found.');
        }
        $work = $this->workDir('project');
        try {
            $mesh = $stlPath;
            if (abs($params->scale - 1.0) > 1e-6) {
                $mesh = $work.'/model.stl';
                StlFile::scale($stlPath, $mesh, $params->scale);
            }
            // a lithophane needs the finest layers whatever the customer picked for price comparison
            $quality = ($hints['kind'] ?? '') === 'lithophane' ? 'fine' : $params->quality;
            $process = $printer['processes'][$quality] ?? $printer['processes']['standard'];
            $filament = $printer['filaments'][$params->materialCode] ?? $printer['filaments']['PLA'];

            $r = $this->python->runScript('orca_profiles.py', [
                'bundle', $this->config['vendor_profiles'], $printer['vendor'], $printer['machine'], $process, $filament, $work.'/set',
                json_encode(self::overrides($params, $hints)),
            ], 60);
            if (empty($r['ok'])) {
                throw new EngineException('Settings bundle failed: '.($r['error'] ?? 'unknown'));
            }

            $out = $work.'/project.3mf';
            $cmd = array_merge($this->config['xvfb'] ? ['xvfb-run', '-a'] : [], [
                $this->config['bin'], '--datadir', $work.'/data', '--orient', '0', '--arrange', '1',
                '--load-settings', $work.'/set/machine.json;'.$work.'/set/process.json',
                '--load-filaments', $work.'/set/filament.json',
                '--export-3mf', $out, $mesh,
            ]);
            File::ensureDirectoryExists($work.'/data');
            $res = Process::path($work)->env(['HOME' => $work, 'TMPDIR' => $work])->timeout(120)->run($cmd);
            if (! is_file($out) || filesize($out) < 500) {
                throw new EngineException('3MF export failed: '.mb_substr($res->output().$res->errorOutput(), -400));
            }
            $keep = rtrim($this->config['work_dir'], '/').'/projects/'.Str::uuid().'.3mf';
            File::ensureDirectoryExists(dirname($keep));
            File::move($out, $keep);

            return $keep;
        } finally {
            File::deleteDirectory($work);
        }
    }

    /**
     * Customer choices + what this kind of model needs → process settings.
     *
     * @return array<string, string>
     */
    public static function overrides(SliceParams $p, array $hints): array
    {
        $kind = (string) ($hints['kind'] ?? '');
        $o = [
            'sparse_infill_density' => $p->infillPercent.'%',
            'enable_support' => $p->supports ? '1' : '0',
            'spiral_mode' => $p->vaseMode ? '1' : '0',
        ];
        if ($p->supports && ($p->treeSupports || $kind === 'generated')) {
            $o['support_type'] = 'tree(auto)';
        }
        if ($kind === 'lithophane') {
            // the picture lives in the wall thickness: solid, drawn by perimeters only, slow outer wall, brim keeps the tall plate down
            $o = ['sparse_infill_density' => '100%', 'wall_loops' => '12', 'enable_support' => '0', 'brim_type' => 'outer_only', 'brim_width' => '5', 'outer_wall_speed' => '40'] + $o;
        }

        return $o;
    }

    private function workDir(string $tag): string
    {
        $dir = rtrim($this->config['work_dir'], '/').'/'.$tag.'_'.Str::random(12);
        File::ensureDirectoryExists($dir);

        return $dir;
    }
}
