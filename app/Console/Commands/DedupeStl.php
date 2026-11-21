<?php

namespace App\Console\Commands;

use App\Models\ModelFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Models stored before 20 Nov 2026 keep a sound binary STL twice: original.stl as it came and model.stl, its copy
 * (ProcessModelFile). The copy goes and the model reads its original, as new models do. Only where both files are
 * byte for byte the same; a model touched within the last hour is left for the next run (a job may be reading it).
 */
class DedupeStl extends Command
{
    protected $signature = 'matplace:dedupe-stl {--dry-run}';

    protected $description = 'Remove model.stl where it is a byte-for-byte copy of original.stl';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $disk = Storage::disk(ModelFile::DISK);
        $n = 0;
        $bytes = 0;
        $differ = 0;
        ModelFile::where('ext', 'stl')->whereNotNull('stl_path')->whereColumn('stl_path', '!=', 'storage_path')->where('updated_at', '<', now()->subHour())
            ->orderBy('id')->chunkById(100, function ($files) use ($disk, $dry, &$n, &$bytes, &$differ) {
                foreach ($files as $f) {
                    if ($f->stl_path !== $f->dir().'/model.stl' || ! $disk->exists($f->stl_path) || ! $disk->exists($f->storage_path)) {
                        continue;
                    }
                    $copy = $disk->path($f->stl_path);
                    $original = $disk->path($f->storage_path);
                    if (filesize($copy) !== filesize($original) || hash_file('xxh128', $copy) !== hash_file('xxh128', $original)) {
                        $differ++;

                        continue;
                    }
                    $n++;
                    $bytes += filesize($copy);
                    if (! $dry) {
                        // the row first: from now on every reader is sent to the original, then the copy goes
                        ModelFile::whereKey($f->id)->update(['stl_path' => $f->storage_path]);
                        @unlink($copy);
                    }
                }
            });
        $this->info(sprintf('%s%d copies, %.1f GB; %d models keep a model.stl that differs from the original', $dry ? '[dry-run] ' : 'removed ', $n, $bytes / 1073741824, $differ));

        return self::SUCCESS;
    }
}
