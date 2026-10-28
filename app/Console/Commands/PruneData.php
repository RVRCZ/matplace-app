<?php

namespace App\Console\Commands;

use App\Domain\Farm\OrderFlow;
use App\Domain\Tools\Artwork;
use App\Jobs\BuildFarmTimelapse;
use App\Models\FarmOrder;
use App\Models\GenerationRequest;
use App\Models\ModelFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Retention: what nobody claimed does not stay forever.
 *  - identification photos older than 1 day (the description stays, the picture goes),
 *  - anonymous model files + calculations older than N days that no account, inquiry or quote refers to,
 *  - leftover slicer gcode older than 7 days,
 *  - farm orders of big models whose customer never asked for the exact price (awaiting_choices for a week),
 *  - guests' farm orders nobody paid within a week (docs/AO.md).
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

        GenerationRequest::where(fn ($q) => $q->whereNotNull('image_path')->orWhereNotNull('views'))->where('created_at', '<', now()->subDay())->each(function (GenerationRequest $r) use (&$photos, $dry) {
            $photos += count($r->photoPaths());
            if (! $dry) {
                $r->forgetPhotos();
            }
        });

        ModelFile::whereNull('owner_user_id')
            ->where('created_at', '<', now()->subDays($days))
            ->whereNotIn('id', DB::table('inquiries')->whereNotNull('model_file_id')->select('model_file_id'))
            ->whereNotIn('id', DB::table('quotes')->whereNotNull('model_file_id')->select('model_file_id'))
            ->whereNotIn('id', DB::table('catalog_models')->whereNotNull('model_file_id')->select('model_file_id'))
            // a paid order prints from it (a guest's file is the account's after the payment, this only makes sure): the
            // order would go with it (model_file_id cascades)
            ->whereNotIn('id', DB::table('farm_orders')->whereNotNull('paid_at')->select('model_file_id'))
            ->each(function (ModelFile $f) use (&$files, $dry) {
                $files++;
                if (! $dry) {
                    Storage::disk(ModelFile::DISK)->deleteDirectory($f->dir());
                    $f->delete(); // calculations cascade
                }
            });

        // raw generator output (tens of MB each) is only a short safety net
        $raws = 0;
        foreach (glob(Storage::disk(ModelFile::DISK)->path('files').'/*/raw.*') ?: [] as $raw) {
            if (filemtime($raw) < now()->subDays(7)->getTimestamp()) {
                $raws++;
                if (! $dry) {
                    @unlink($raw);
                }
            }
        }
        $this->line("raw generator files removed: {$raws}");

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

        // farm time-lapse frames: kept a while after the videos are built, so they can be built again
        $frameDirs = 0;
        $farm = Storage::disk(config('farm.disk'));
        foreach (array_merge(glob($farm->path('orders').'/*/frames') ?: [], glob($farm->path('orders').'/*/frames_layer') ?: []) as $dir) {
            if (filemtime($dir) < time() - BuildFarmTimelapse::KEEP_FRAMES_DAYS * 86400) {
                $frameDirs++;
                if (! $dry) {
                    $farm->deleteDirectory(substr($dir, strlen($farm->path(''))));
                }
            }
        }
        $this->line("farm frame folders removed: {$frameDirs}");

        // uploaded artwork and half-built tool previews live for a day (a created model keeps its own copy)
        $temp = 0;
        foreach (array_merge(glob(storage_path('app/tmp/artwork/*')) ?: [], glob(storage_path('app/tmp/param/*')) ?: []) as $t) {
            if (is_file($t) && filemtime($t) < time() - 86400) {
                $temp++;
                if (! $dry) {
                    @unlink($t);
                }
            }
        }

        // pictures uploaded into the tools stay 30 days ("my pictures")
        $temp += Artwork::prune($dry);

        // a big model's order waits for the customer's choices (docs/AL.md §11); one nobody came back to is closed
        // like any unpaid order the customer walked away from: cancelled, nothing was held
        $waitingDays = max(1, (int) config('farm.big_model.waiting_days', 7));
        $flow = app(OrderFlow::class);
        $waiting = 0;
        FarmOrder::where('status', FarmOrder::STATUS_AWAITING_CHOICES)->where('created_at', '<', now()->subDays($waitingDays))->get()
            ->each(function (FarmOrder $o) use (&$waiting, $dry, $flow, $waitingDays) {
                $waiting++;
                if (! $dry) {
                    $flow->move($o, FarmOrder::STATUS_CANCELLED, 'system', null, "no choices within {$waitingDays} days");
                }
            });
        $this->line("farm orders waiting for choices cancelled: {$waiting}");

        // a guest's order nobody paid (docs/AO.md): cancelled after a week like the waiting ones; nothing was held. The
        // file stays with the session and goes when anonymous files go (the cancelled order with it)
        $guestDays = max(1, (int) config('farm.guest.unpaid_days', 7));
        $guests = 0;
        FarmOrder::whereNull('user_id')->whereNull('paid_at')->where('created_at', '<', now()->subDays($guestDays))
            ->whereIn('status', [FarmOrder::STATUS_AWAITING_CHOICES, FarmOrder::STATUS_UPLOADED, FarmOrder::STATUS_SLICED, FarmOrder::STATUS_FAILED])->get()
            ->each(function (FarmOrder $o) use (&$guests, $dry, $flow, $guestDays) {
                $guests++;
                if (! $dry) {
                    $flow->move($o, FarmOrder::STATUS_CANCELLED, 'system', null, "guest order not paid within {$guestDays} days");
                }
            });
        $this->line("unpaid guest orders cancelled: {$guests}");

        $this->info(($dry ? '[dry-run] ' : '')."photos={$photos} files={$files} gcodes={$gcodes} temp={$temp}");

        return self::SUCCESS;
    }
}
