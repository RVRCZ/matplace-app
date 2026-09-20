<?php

namespace App\Console\Commands;

use App\Models\GenerationRequest;
use App\Models\ModelFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Retention: what nobody claimed does not stay forever.
 *  - identification photos older than 1 day (the description stays, the picture goes),
 *  - anonymous model files + calculations older than N days that no account, inquiry or quote refers to,
 *  - leftover slicer gcode older than 7 days.
 */
class PruneData extends Command
{
    protected $signature = 'matplace:prune {--dry-run}';

    protected $description = 'Delete unclaimed anonymous uploads, old photos and temporary engine files';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $days = (int) config('uploads.anonymous_retention_days', 30);
        $photos = 0;
        $files = 0;

        GenerationRequest::whereNotNull('image_path')->where('created_at', '<', now()->subDay())->each(function (GenerationRequest $r) use (&$photos, $dry) {
            $photos++;
            if (! $dry) {
                Storage::disk('local')->delete($r->image_path);
                $r->update(['image_path' => null]);
            }
        });

        ModelFile::whereNull('owner_user_id')
            ->where('created_at', '<', now()->subDays($days))
            ->whereNotIn('id', DB::table('inquiries')->whereNotNull('model_file_id')->select('model_file_id'))
            ->whereNotIn('id', DB::table('quotes')->whereNotNull('model_file_id')->select('model_file_id'))
            ->whereNotIn('id', DB::table('catalog_models')->whereNotNull('model_file_id')->select('model_file_id'))
            ->each(function (ModelFile $f) use (&$files, $dry) {
                $files++;
                if (! $dry) {
                    Storage::disk(ModelFile::DISK)->deleteDirectory($f->dir());
                    $f->delete(); // calculations cascade
                }
            });

        $gcodeDir = rtrim((string) config('engines.orca.work_dir'), '/').'/gcode';
        $gcodes = 0;
        foreach (glob($gcodeDir.'/*.gcode') ?: [] as $g) {
            if (filemtime($g) < time() - 7 * 86400) {
                $gcodes++;
                if (! $dry) {
                    @unlink($g);
                }
            }
        }

        $this->info(($dry ? '[dry-run] ' : '')."photos={$photos} files={$files} gcodes={$gcodes}");

        return self::SUCCESS;
    }
}
