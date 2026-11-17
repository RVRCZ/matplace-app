<?php

namespace App\Domain\Generation;

use App\Jobs\BuildFarmTimelapse;
use App\Models\FarmOrder;
use App\Models\GenerationRequest;
use App\Models\ModelFile;
use Illuminate\Support\Facades\Storage;

/**
 * The customer's front photo of a bust or a pet figurine, kept only for the videos of the print. A generation forgets
 * its photos when it ends (GenerateModel::forgetPhoto), long before an order exists, so the front photo (never the side
 * views) is held next to the model file for HOLD_HOURS (matplace:prune), and the order made from that model takes a copy
 * into its farm folder. The copy lives as long as the time-lapse frames (BuildFarmTimelapse::KEEP_FRAMES_DAYS), goes
 * with them and with the order's files, and is read only by TimelapseCutter for the videos.
 */
class SourcePhotos
{
    public const HOLD_HOURS = 24;

    public const KINDS = ['bust', 'pet', 'figure'];

    /** The run is over and the model is there: keep the front photo beside the model file before the generation forgets it. */
    public function hold(GenerationRequest $req, ModelFile $file): void
    {
        $rel = $req->image_path;
        $local = Storage::disk('local');
        if (! $rel || ! in_array($req->description['kind'] ?? null, self::KINDS, true) || ! $local->exists($rel)) {
            return;
        }
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION)) ?: 'jpg';
        Storage::disk(ModelFile::DISK)->put($file->dir().'/source_hold.'.$ext, $local->get($rel));
    }

    /** An order is made of the model: it takes a copy of the held photo (when there is one). */
    public function keep(FarmOrder $order, ModelFile $file): void
    {
        $models = Storage::disk(ModelFile::DISK);
        $held = $file->origin === 'generated' ? ($models->files($file->dir()) ? collect($models->files($file->dir()))->first(fn ($f) => str_starts_with(basename($f), 'source_hold.')) : null) : null;
        if (! $held) {
            return;
        }
        Storage::disk(config('farm.disk'))->put($order->dir().'/source.'.pathinfo($held, PATHINFO_EXTENSION), $models->get($held));
    }

    /** The order's kept copy, absolute path, when it is there. */
    public function path(FarmOrder $order): ?string
    {
        $disk = Storage::disk(config('farm.disk'));
        $rel = collect($disk->files($order->dir()))->first(fn ($f) => preg_match('/^source\.(jpe?g|png|webp)$/i', basename($f)));

        return $rel ? $disk->path($rel) : null;
    }

    /** Where the copy of an order is (relative), for the places that delete it. */
    public static function copies(FarmOrder $order): array
    {
        $disk = Storage::disk(config('farm.disk'));

        return array_values(array_filter($disk->files($order->dir()), fn ($f) => str_starts_with(basename($f), 'source.')));
    }

    public static function keepDays(): int
    {
        return BuildFarmTimelapse::KEEP_FRAMES_DAYS;
    }
}
