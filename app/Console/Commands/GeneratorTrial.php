<?php

namespace App\Console\Commands;

use App\Domain\Generation\ModelNormalizer;
use App\Engines\Contracts\ModelGenerator;
use App\Engines\DTO\GenerationHandle;
use App\Engines\DTO\GenerationOptions;
use App\Engines\DTO\GenerationStatus;
use App\Engines\Generator\FakeGenerator;
use App\Engines\Generator\MeshyGenerator;
use App\Engines\Generator\TripoGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The same photos through several generators, each result made printable the way a bust on the site is (cut across
 * the chest, on its base, closed): the files to print and lay next to each other (docs/TODO.md #1, Meshy against Tripo).
 * Writes <out>/<photo>-<engine>.stl, the raw mesh beside it and report.json; touches no table of the site.
 *
 *   php artisan matplace:generator-trial face.jpg dog.jpg child.jpg --engines=tripo,meshy
 *
 * Every photo is SENT to every engine named and every task is PAID with that engine's credits: the command says how
 * much before it starts and asks (--yes skips the question).
 */
class GeneratorTrial extends Command
{
    protected $signature = 'matplace:generator-trial {photos* : photos (jpg, png, webp)} {--engines=tripo,meshy : generators to compare}
        {--size=80 : the largest size of the print in mm} {--kind=bust : bust | figure | plain (how the result is made printable)}
        {--quality=detailed : standard | detailed geometry} {--out= : folder for the results (default storage/app/trial/<time>)}
        {--wait=600 : seconds to wait for one model} {--yes : do not ask before spending credits}';

    protected $description = 'Compare generators on the same photos: one printable STL per photo and engine, with the time and the cost';

    public function handle(ModelNormalizer $normalizer): int
    {
        $photos = (array) $this->argument('photos');
        foreach ($photos as $photo) {
            if (! is_file($photo)) {
                $this->error('No such photo: '.$photo);

                return self::FAILURE;
            }
        }
        $engines = [];
        foreach (array_filter(array_map('trim', explode(',', (string) $this->option('engines')))) as $name) {
            $engine = $this->engine($name);
            if (! $engine) {
                $this->error('Unknown generator: '.$name.' (tripo, meshy)');

                return self::FAILURE;
            }
            $engines[$name] = $engine;
        }
        $quality = $this->option('quality') === 'standard' ? 'standard' : 'detailed';
        $options = new GenerationOptions(targetSizeMm: (float) $this->option('size'), geometryQuality: $quality);
        $kind = in_array($this->option('kind'), ['bust', 'figure', 'plain'], true) ? (string) $this->option('kind') : 'bust';

        $this->line(count($photos).' photo(s) × '.count($engines).' generator(s), '.$quality.' geometry. Every photo is sent to every generator.');
        if (! $this->option('yes') && ! $this->confirm('This spends credits at '.implode(' and ', array_keys($engines)).'. Go on?')) {
            return self::SUCCESS;
        }
        $out = rtrim((string) ($this->option('out') ?: storage_path('app/trial/'.now()->format('Ymd-His'))), '/\\');
        File::ensureDirectoryExists($out);

        $rows = [];
        foreach ($photos as $photo) {
            $stem = Str::slug(pathinfo($photo, PATHINFO_FILENAME)) ?: 'photo';
            foreach ($engines as $name => $engine) {
                $row = ['photo' => basename($photo), 'engine' => $name, 'ok' => false, 'seconds' => null, 'cost_cents' => null, 'stl' => null, 'raw' => null, 'error' => null, 'report' => []];
                $started = microtime(true);
                try {
                    $handle = $engine->fromImage($photo, null, $options);
                    $row['cost_cents'] = (int) ($handle->meta['credits'] ?? $engine->estimatedCostCents());
                    $status = $this->await($engine, $handle, (int) $this->option('wait'));
                    if ($status->state !== GenerationStatus::DONE || ! $status->meshPath) {
                        throw new \RuntimeException($status->error ?: 'no model within the time given');
                    }
                    $row['seconds'] = round(microtime(true) - $started, 1);
                    $ext = strtolower(pathinfo($status->meshPath, PATHINFO_EXTENSION)) ?: 'glb';
                    $stl = $out.'/'.$stem.'-'.$name.'.stl';
                    // what the site does with a bust: clean, cut across the chest, on its base, turned to the front
                    $normalizer->toPrintableStl($status->meshPath, $stl, (float) $this->option('size'), $ext === 'glb',
                        $kind === 'plain' ? ['clean', 'solid'] : ['clean', 'pedestal', 'solid'],
                        array_filter(['front' => $kind === 'bust' ? 'auto' : null, 'cut' => $kind === 'bust' ? 'bust' : null, 'tidy' => $kind === 'plain' ? null : true]));
                    $raw = $out.'/'.$stem.'-'.$name.'-raw.'.$ext;
                    File::move($status->meshPath, $raw);
                    $row = ['ok' => true, 'stl' => basename($stl), 'raw' => basename($raw), 'report' => $normalizer->report, 'stl_bytes' => filesize($stl), 'raw_bytes' => filesize($raw)] + $row;
                } catch (\Throwable $e) {
                    $row['error'] = mb_substr($e->getMessage(), 0, 300);
                    $row['seconds'] = round(microtime(true) - $started, 1);
                }
                $rows[] = $row;
                $this->line(sprintf('%-24s %-6s %s', $row['photo'], $name, $row['ok'] ? $row['seconds'].' s, '.$row['cost_cents'].' cents → '.$row['stl'] : 'FAILED: '.$row['error']));
            }
        }
        File::put($out.'/report.json', (string) json_encode(['at' => now()->toIso8601String(), 'kind' => $kind, 'quality' => $quality, 'size_mm' => (float) $this->option('size'), 'rows' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->table(['photo', 'engine', 'ok', 'seconds', 'cents', 'file'], array_map(fn ($r) => [$r['photo'], $r['engine'], $r['ok'] ? 'yes' : 'no', $r['seconds'], $r['cost_cents'], $r['stl'] ?? $r['error']], $rows));
        $this->line('Results: '.$out);

        return collect($rows)->every(fn ($r) => $r['ok']) ? self::SUCCESS : self::FAILURE;
    }

    /** A generator by its name, with the keys of this installation (config/ai.php); "fake" is for tests. */
    private function engine(string $name): ?ModelGenerator
    {
        return match ($name) {
            'tripo' => new TripoGenerator((array) config('ai.tripo')),
            'meshy' => new MeshyGenerator((array) config('ai.meshy')),
            'fake' => app()->environment('production') ? null : new FakeGenerator,
            default => null,
        };
    }

    /** Asks for the model every few seconds until it is there, it failed, or the time is up. */
    private function await(ModelGenerator $engine, GenerationHandle $handle, int $seconds): GenerationStatus
    {
        $until = microtime(true) + max(1, $seconds);
        do {
            $status = $engine->poll($handle);
            if ($status->isFinal()) {
                return $status;
            }
            $this->output->write('.');
            usleep(app()->runningUnitTests() ? 1000 : 5_000_000);
        } while (microtime(true) < $until);

        return new GenerationStatus(GenerationStatus::FAILED, error: 'no model within '.$seconds.' s');
    }
}
