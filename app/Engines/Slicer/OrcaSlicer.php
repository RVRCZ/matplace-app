<?php

namespace App\Engines\Slicer;

use App\Engines\Contracts\Slicer;
use App\Engines\DTO\Dimensions;
use App\Engines\DTO\SliceParams;
use App\Engines\DTO\SliceResult;
use App\Engines\Exceptions\SlicerException;
use App\Engines\Mesh\StlFile;
use App\Support\Stopwatch;
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
        $clock = new Stopwatch;
        try {
            $mesh = $clock->measure('mesh', fn () => $this->prepareMesh($meshPath, $params, $work));
            $placed = null;
            $machine = $this->patched($this->profileFile($params->profiles['machine'] ?? null) ?? $this->config['profiles'].'/'.$this->config['machine'], $params->overrides['machine'] ?? [], $work.'/machine.json');
            // a farm printer pairs its own machine profile with the shared process/filament profiles (written for the
            // Kobra S1): the pairing is deliberate, so the presets are declared compatible or the CLI refuses them (-17)
            $pairing = $params->profiles ? ['compatible_printers' => [(string) (json_decode((string) File::get($machine), true)['name'] ?? '')], 'compatible_printers_condition' => ''] : [];
            $filamentName = $this->machineVariant($params->profiles['filament'] ?? null, $params->profiles['machine'] ?? null);
            $filament = $this->patched($this->profileFile($filamentName) ?? $this->profile('filaments', $params->materialCode, 'material'), ($params->overrides['filament'] ?? []) + $pairing, $work.'/filament.json');
            $process = $this->profileFile($params->profiles['process'] ?? null) ?? $this->profile('processes', $params->quality, 'quality');
            // we put the model in the middle of the bed ourselves: the slicer's own arranging turns it as it likes
            // (Baby Turtle, 27 Sep 2026: by 90°), and then neither the preview of supports nor our plate layout fits
            if ($bed = $this->bedOf($machine)) {
                $size = $clock->measure('mesh', fn () => StlFile::place($mesh, $work.'/placed.stl', 1.0, $bed['cx'], $bed['cy']));
                if ($size[0] <= $bed['x'] && $size[1] <= $bed['y']) {
                    $placed = $work.'/placed.stl';
                }
            }

            $attempt = function (bool $supports) use ($work, $mesh, $placed, $params, $filament, $process, $machine, $pairing): array {
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
                // the printer's own process settings win over everything above (farm: supports on auto, no prime tower…)
                foreach (($params->overrides['process'] ?? []) + $pairing as $k => $v) {
                    $proc[$k] = $v;
                }
                $tag = $supports ? 'sup' : 'std';
                $procFile = $work.'/process_'.$tag.'.json';
                File::put($procFile, json_encode($proc));
                $out = $work.'/out_'.$tag;
                File::ensureDirectoryExists($out);

                $cmd = array_merge($this->prefix(), [
                    $this->config['bin'],
                    '--datadir', $work.'/data',
                    '--arrange', $placed ? '0' : '1',
                    '--load-settings', $machine.';'.$procFile,
                    '--load-filaments', $filament,
                    '--slice', '0',
                    '--outputdir', $out,
                    $placed ?? $mesh,
                ]);
                $result = Process::path($work)->env(['HOME' => $work, 'TMPDIR' => $work])
                    ->timeout($this->config['timeout'])->run($cmd);

                return ['gcodes' => File::glob($out.'/*.gcode'), 'raw' => $result->output().$result->errorOutput()];
            };

            $forced = ($params->overrides['process']['enable_support'] ?? null) === '1';
            $wantSupports = $forced || ($params->supports ?? false);
            $r = $clock->measure('slice1', fn () => $attempt($wantSupports));
            $autoSupports = false;
            if (! $r['gcodes'] && $params->supports === null && ! $params->vaseMode) {
                // what the slicer said to the run without supports: the data for deciding on supports up front
                $clock->note('double', true)->note('slice1_error', mb_substr(trim($r['raw']), -300));
                $r2 = $clock->measure('slice2', fn () => $attempt(true));
                if ($r2['gcodes']) {
                    $r = $r2;
                    $autoSupports = true;
                }
            }
            if (! $r['gcodes']) {
                throw new SlicerException('Slice failed (no gcode): '.mb_substr($r['raw'], -600));
            }

            $gcodePath = $r['gcodes'][0];
            $parseStart = hrtime(true);
            $gcode = (string) File::get($gcodePath);
            $grams = GcodeStats::grams($gcode);
            $minutes = GcodeStats::minutes($gcode);
            if ($grams === null) {
                throw new SlicerException('Could not parse filament grams from gcode.');
            }

            $keep = $this->config['work_dir'].'/gcode/'.Str::uuid().'.gcode';
            File::ensureDirectoryExists(dirname($keep));
            File::move($gcodePath, $keep);

            $meters = GcodeStats::meters($gcode);
            $byMode = GcodeStats::minutesByMode($gcode);
            $layers = GcodeStats::layers($gcode);
            $supportsUsed = $forced ? GcodeStats::hasSupports($gcode) : ($wantSupports || $autoSupports);
            unset($gcode);
            $clock->add('parse', (hrtime(true) - $parseStart) / 1e9)->note('gcode_mb', round(filesize($keep) / 1048576, 1));

            $dims = $clock->measure('mesh', fn () => StlFile::stats($mesh)->bbox);
            $warnings = [];
            if ($autoSupports) {
                $warnings[] = 'supports_added';
            }

            return new SliceResult(
                grams: $grams,
                minutes: $minutes ?? 1,
                dims: $dims,
                // supports switched on for the whole farm are "auto": they count only when the slicer really built some
                supportsUsed: $supportsUsed,
                gcodePath: $keep,
                warnings: $warnings,
                raw: ['engine' => 'orca', 'tree_supports' => ($wantSupports || $autoSupports) && $params->treeSupports, 'filament' => basename($filament), 'process' => basename($process), 'machine' => basename($machine)],
                meters: $meters,
                minutesByMode: $byMode,
                layers: $layers,
                timings: $clock->toArray(),
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

    /** A profile named by a farm printer/material row: uploaded profiles first, then the ones shipped with the app. */
    private function profileFile(?string $name): ?string
    {
        if (! $name) {
            return null;
        }
        $name = basename($name);   // a row can never point outside the profile directories
        foreach ([config('farm.profiles_dir'), $this->config['profiles']] as $dir) {
            if ($dir && is_file($dir.'/'.$name)) {
                return $dir.'/'.$name;
            }
        }
        throw new SlicerException("Slicer profile missing: {$name}");
    }

    /**
     * The machine's own flavour of a shared filament profile: machine_kobra3max.json + filament_pla.json →
     * filament_pla_kobra3max.json when it exists (flow, pressure advance, cooling differ between machines),
     * else the shared profile itself.
     */
    private function machineVariant(?string $filament, ?string $machine): ?string
    {
        if (! $filament || ! $machine || ! preg_match('/^machine_(\w+)\.json$/', basename($machine), $m)) {
            return $filament;
        }
        $variant = preg_replace('/\.json$/', '_'.$m[1].'.json', basename($filament));
        foreach ([config('farm.profiles_dir'), $this->config['profiles']] as $dir) {
            if ($dir && is_file($dir.'/'.$variant)) {
                return $variant;
            }
        }

        return $filament;
    }

    /** Copy of a profile with settings replaced; the profile itself when there is nothing to replace. */
    private function patched(string $profile, array $overrides, string $target): string
    {
        if (! $overrides) {
            return $profile;
        }
        $json = json_decode((string) File::get($profile), true) ?: [];
        File::put($target, json_encode(array_merge($json, $overrides)));

        return $target;
    }

    /**
     * The printable rectangle of a machine profile: its size and its middle in printer coordinates.
     *
     * @return array{x:float,y:float,cx:float,cy:float}|null null when the profile does not say (inherited presets)
     */
    private function bedOf(string $machineFile): ?array
    {
        $area = json_decode((string) File::get($machineFile), true)['printable_area'] ?? null;
        if (! is_array($area) || count($area) < 3) {
            return null;
        }
        $xs = $ys = [];
        foreach ($area as $point) {
            if (! preg_match('/^\s*(-?[\d.]+)\s*x\s*(-?[\d.]+)\s*$/', (string) $point, $m)) {
                return null;
            }
            $xs[] = (float) $m[1];
            $ys[] = (float) $m[2];
        }
        $x = max($xs) - min($xs);
        $y = max($ys) - min($ys);

        return $x > 0 && $y > 0 ? ['x' => $x, 'y' => $y, 'cx' => (min($xs) + max($xs)) / 2, 'cy' => (min($ys) + max($ys)) / 2] : null;
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
