<?php

namespace App\Engines\Slicer;

use App\Engines\Contracts\Slicer;
use App\Engines\DTO\Dimensions;
use App\Engines\DTO\SliceParams;
use App\Engines\DTO\SliceResult;
use App\Engines\Exceptions\SlicerException;
use App\Engines\Mesh\StlFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Headless OrcaSlicer CLI (AppImage, xvfb). Ported from the legacy SlicerService and generalised:
 * one machine profile, filament profile per material, process profile per quality.
 *
 * Expects STL input (other formats are converted earlier in the pipeline). Scale is applied by
 * rewriting the STL, because the CLI has no reliable --scale across versions.
 */
final class OrcaSlicer implements Slicer
{
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'orca';
    }

    public function supportedFormats(): array
    {
        return ['stl'];
    }

    public function slice(string $meshPath, SliceParams $params): SliceResult
    {
        $this->assertAvailable();
        $work = $this->workDir('job');
        try {
            $mesh = $this->prepareMesh($meshPath, $params, $work);
            $filament = $this->profile('filaments', $params->materialCode, 'material');
            $process = $this->profile('processes', $params->quality, 'quality');
            $machine = $this->config['profiles'].'/'.$this->config['machine'];

            $attempt = function (bool $supports) use ($work, $mesh, $params, $filament, $process, $machine): array {
                $proc = json_decode((string) File::get($process), true) ?: [];
                if ($params->vaseMode) {
                    $proc['spiral_mode'] = '1';
                    $proc['sparse_infill_density'] = '0%';
                    $proc['wall_loops'] = '1';
                    $proc['top_shell_layers'] = '0';
                } else {
                    $proc['spiral_mode'] = '0';
                    $proc['sparse_infill_density'] = $params->infillPercent.'%';
                }
                $proc['enable_support'] = $supports ? '1' : '0';
                if ($supports && $params->treeSupports) {
                    $proc['support_type'] = 'tree(auto)';
                    $proc['support_style'] = 'default';
                }
                $tag = $supports ? 'sup' : 'std';
                $procFile = $work.'/process_'.$tag.'.json';
                File::put($procFile, json_encode($proc));
                $out = $work.'/out_'.$tag;
                File::ensureDirectoryExists($out);

                $cmd = array_merge($this->prefix(), [
                    $this->config['bin'],
                    '--datadir', $work.'/data',
                    '--arrange', '1',
                    '--load-settings', $machine.';'.$procFile,
                    '--load-filaments', $filament,
                    '--slice', '0',
                    '--outputdir', $out,
                    $mesh,
                ]);
                $result = Process::path($work)->env(['HOME' => $work, 'TMPDIR' => $work])
                    ->timeout($this->config['timeout'])->run($cmd);

                return ['gcodes' => File::glob($out.'/*.gcode'), 'raw' => $result->output().$result->errorOutput()];
            };

            $wantSupports = $params->supports ?? false;
            $r = $attempt($wantSupports);
            $autoSupports = false;
            if (! $r['gcodes'] && $params->supports === null && ! $params->vaseMode) {
                $r2 = $attempt(true);
                if ($r2['gcodes']) {
                    $r = $r2;
                    $autoSupports = true;
                }
            }
            if (! $r['gcodes']) {
                throw new SlicerException('Slice failed (no gcode): '.mb_substr($r['raw'], -600));
            }

            $gcodePath = $r['gcodes'][0];
            $gcode = (string) File::get($gcodePath);
            $grams = GcodeStats::grams($gcode);
            $minutes = GcodeStats::minutes($gcode);
            if ($grams === null) {
                throw new SlicerException('Could not parse filament grams from gcode.');
            }

            $keep = $this->config['work_dir'].'/gcode/'.Str::uuid().'.gcode';
            File::ensureDirectoryExists(dirname($keep));
            File::move($gcodePath, $keep);

            $dims = StlFile::stats($mesh)->bbox;
            $warnings = [];
            if ($autoSupports) {
                $warnings[] = 'supports_added';
            }

            return new SliceResult(
                grams: $grams,
                minutes: $minutes ?? 1,
                dims: $dims,
                supportsUsed: $wantSupports || $autoSupports,
                gcodePath: $keep,
                warnings: $warnings,
                raw: ['engine' => 'orca', 'tree_supports' => ($wantSupports || $autoSupports) && $params->treeSupports, 'filament' => basename($filament), 'process' => basename($process)],
            );
        } finally {
            File::deleteDirectory($work);
        }
    }

    public function measure(string $meshPath): Dimensions
    {
        return StlFile::stats($meshPath)->bbox;
    }

    private function prepareMesh(string $meshPath, SliceParams $params, string $work): string
    {
        if (abs($params->scale - 1.0) < 1e-6) {
            return $meshPath;
        }
        $scaled = $work.'/scaled.stl';
        StlFile::scale($meshPath, $scaled, $params->scale);

        return $scaled;
    }

    private function profile(string $group, string $key, string $what): string
    {
        $file = $this->config[$group][$key] ?? null;
        if (! $file) {
            throw new SlicerException("No slicer profile for {$what} '{$key}'.");
        }
        $path = $this->config['profiles'].'/'.$file;
        if (! is_file($path)) {
            throw new SlicerException("Slicer profile missing: {$path}");
        }

        return $path;
    }

    private function prefix(): array
    {
        return $this->config['xvfb'] ? ['xvfb-run', '-a'] : [];
    }

    private function workDir(string $tag): string
    {
        $dir = rtrim($this->config['work_dir'], '/').'/'.$tag.'_'.Str::random(12);
        File::ensureDirectoryExists($dir.'/data');

        return $dir;
    }

    private function assertAvailable(): void
    {
        if (! is_file($this->config['bin'])) {
            throw new SlicerException('OrcaSlicer binary not found: '.$this->config['bin']);
        }
    }
}
