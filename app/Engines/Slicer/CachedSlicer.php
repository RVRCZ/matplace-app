<?php

namespace App\Engines\Slicer;

use App\Engines\Contracts\Slicer;
use App\Engines\DTO\Dimensions;
use App\Engines\DTO\SliceParams;
use App\Engines\DTO\SliceResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The same mesh with the same parameters, profiles and slicer gives the same G-code: slicing it again (a calculation
 * opened from a shared link, a re-slice back to the quality chosen before, "print again") hands out a copy of the
 * stored result instead of minutes of OrcaSlicer. The key holds everything the G-code depends on: the mesh bytes,
 * every parameter and override, the content of every profile file, the slicer binary and our own code around it.
 */
final class CachedSlicer implements Slicer
{
    /** Our code that writes the slicer's input or reads its output: a change there is a different result. */
    private const CODE = ['OrcaSlicer.php', 'GcodeStats.php', '../Mesh/StlFile.php'];

    public function __construct(
        private readonly Slicer $inner,
        private readonly string $dir,
        private readonly string $gcodeDir,
        /** @var list<string> directories with profile files */
        private readonly array $profileDirs,
        private readonly ?string $binary,
        private readonly int $keepDays = 7,
        private readonly int $maxMb = 3000,
    ) {}

    public function name(): string
    {
        return $this->inner->name();
    }

    public function supportedFormats(): array
    {
        return $this->inner->supportedFormats();
    }

    public function measure(string $meshPath): Dimensions
    {
        return $this->inner->measure($meshPath);
    }

    public function slice(string $meshPath, SliceParams $params): SliceResult
    {
        $start = hrtime(true);
        $key = $this->key($meshPath, $params);
        $gcode = $this->dir.'/'.$key.'.gcode';
        $json = $this->dir.'/'.$key.'.json';
        if (is_file($json) && is_array($r = json_decode((string) File::get($json), true)) && (! $r['gcode'] || is_file($gcode))) {
            $copy = null;
            if ($r['gcode']) {
                // the caller moves its G-code away (farm order) or keeps it (calculation): always a copy of its own
                $copy = $this->gcodeDir.'/'.Str::uuid().'.gcode';
                File::ensureDirectoryExists($this->gcodeDir);
                File::copy($gcode, $copy);
                @touch($gcode);   // still in use: the pruning counts from the last use
            }
            @touch($json);

            return new SliceResult((float) $r['grams'], (int) $r['minutes'], new Dimensions((float) $r['dims'][0], (float) $r['dims'][1], (float) $r['dims'][2]),
                (bool) $r['supports_used'], $copy, (array) $r['warnings'], (array) $r['raw'], $r['meters'] === null ? null : (float) $r['meters'],
                (array) $r['minutes_by_mode'], $r['layers'] === null ? null : (int) $r['layers'],
                ['slice_cached' => true, 'cache_s' => round((hrtime(true) - $start) / 1e9, 2)] + (array) ($r['timings'] ?? []));
        }

        $result = $this->inner->slice($meshPath, $params);
        try {
            File::ensureDirectoryExists($this->dir);
            if ($result->gcodePath && is_file($result->gcodePath)) {
                File::copy($result->gcodePath, $gcode.'.tmp');
                rename($gcode.'.tmp', $gcode);
            }
            // the timings of the run that made it, so a hit still says what slicing it took (double slice…)
            $timings = array_intersect_key($result->timings, array_flip(['slice1_s', 'slice2_s', 'double']));
            File::put($json, json_encode([
                'grams' => $result->grams, 'minutes' => $result->minutes, 'dims' => [$result->dims->x, $result->dims->y, $result->dims->z],
                'supports_used' => $result->supportsUsed, 'gcode' => (bool) $result->gcodePath, 'warnings' => $result->warnings, 'raw' => $result->raw,
                'meters' => $result->meters, 'minutes_by_mode' => $result->minutesByMode, 'layers' => $result->layers,
                'timings' => array_combine(array_map(fn ($k) => 'made_'.$k, array_keys($timings)), $timings),
            ]));
            $this->prune();
        } catch (\Throwable) {
            // a full disk or a race with another worker: the slice is there, only the shortcut is missing
        }

        return $result->withTimings(['slice_cached' => false]);
    }

    public function key(string $meshPath, SliceParams $params): string
    {
        $profiles = [];
        foreach ($this->profileDirs as $dir) {
            foreach (File::glob(rtrim($dir, '/').'/*.json') ?: [] as $f) {
                $profiles[basename($dir).'/'.basename($f)] = md5_file($f);
            }
        }
        ksort($profiles);
        $code = array_map(fn (string $f) => @md5_file(__DIR__.'/'.$f), self::CODE);
        $bin = $this->binary && is_file($this->binary) ? filesize($this->binary).':'.filemtime($this->binary) : '';

        return sha1(implode('|', [
            $this->inner->name(), md5_file($meshPath), json_encode($params->toArray()), json_encode($profiles), implode(',', $code), $bin,
        ]));
    }

    /** Now and then: what was not used for a week goes, and the oldest goes while the cache is over its size. */
    private function prune(): void
    {
        if (random_int(1, 20) !== 1) {
            return;
        }
        $limit = time() - $this->keepDays * 86400;
        $files = [];
        foreach (File::glob($this->dir.'/*') ?: [] as $f) {
            $t = @filemtime($f);
            if ($t !== false && $t < $limit) {
                @unlink($f);
            } elseif ($t !== false) {
                $files[$f] = [$t, (int) @filesize($f)];
            }
        }
        $total = array_sum(array_column($files, 1));
        uasort($files, fn ($a, $b) => $a[0] <=> $b[0]);
        foreach ($files as $f => [, $size]) {
            if ($total <= $this->maxMb * 1048576) {
                break;
            }
            @unlink($f);
            $total -= $size;
        }
    }
}
