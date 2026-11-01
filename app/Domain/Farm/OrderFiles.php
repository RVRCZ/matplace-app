<?php

namespace App\Domain\Farm;

use App\Models\FarmOrder;
use App\Models\FarmPrintJob;
use App\Models\FarmVideo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;

/**
 * The print files of an order nothing will print from any more (docs/AL.md §16). Whether an order still needs them is
 * known by its state, not by the date of a file. What people see stays: the videos, the last picture, the order page,
 * whose preview falls back to the customer's own file; "print again" starts from that file too.
 */
final class OrderFiles
{
    public const CLOSED = [FarmOrder::STATUS_HANDED_OVER, FarmOrder::STATUS_CANCELLED, FarmOrder::STATUS_FAILED, FarmOrder::STATUS_DONE];

    /** In the order's folder, by name; anything else there stays (videos, snapshot.jpg, *.json, *.txt, photos/, what we don't know). */
    private const REMOVED = ['*.gcode', '*.3mf', 'print.stl', 'plate.stl', 'part-*.stl', 'plate-part-*.stl', 'rest.stl', '*.stl.stage', 'supports.bin'];

    private const FRAMES = ['frames', 'frames_layer'];

    /**
     * Orders closed for farm.files_retention_days. The clock is the last change of state in the order's history:
     * finished_at and handed_at say nothing about a failed or a cancelled order, and updated_at moves with every note.
     * A job still on a printer holds the order whatever its state says (the agent downloads the G-code).
     *
     * @return LazyCollection<int, FarmOrder>
     */
    public function closed(): LazyCollection
    {
        $before = now()->subDays(max(1, (int) config('farm.files_retention_days', 7)));
        $moved = fn ($q) => $q->whereColumn('from', '!=', 'to');

        return FarmOrder::whereIn('status', self::CLOSED)
            ->whereDoesntHave('printJobs', fn ($q) => $q->whereIn('status', FarmPrintJob::ACTIVE))
            ->whereDoesntHave('events', fn ($q) => $moved($q)->where('created_at', '>=', $before))
            ->where(fn ($q) => $q->whereHas('events', $moved)->orWhere('updated_at', '<', $before))
            ->lazyById(200);
    }

    /**
     * Deletes what the order has left and forgets the paths.
     *
     * @return int|null the bytes that went, null when there was nothing to do
     */
    public function prune(FarmOrder $order, bool $dry = false): ?int
    {
        $disk = Storage::disk(config('farm.disk'));
        $dir = $order->dir();
        $files = array_values(array_filter($disk->files($dir), function (string $file) {
            foreach (self::REMOVED as $pattern) {
                if (fnmatch($pattern, basename($file))) {
                    return true;
                }
            }

            return false;
        }));
        // a Short waiting for the admin can still be built again from its frames: they go by KEEP_FRAMES_DAYS (matplace:prune)
        $frames = $this->waitsForRebuild($order) ? [] : array_values(array_intersect($disk->directories($dir), array_map(fn ($name) => $dir.'/'.$name, self::FRAMES)));
        $paths = $this->withoutPaths($order);
        if (! $files && ! $frames && ! $paths) {
            return null;
        }

        $bytes = 0;
        foreach ($frames as $frameDir) {
            $size = array_sum(array_map(fn ($f) => $disk->size($f), $disk->allFiles($frameDir)));
            if ($dry || $disk->deleteDirectory($frameDir)) {
                $bytes += $size;
            } else {
                Log::warning('Farm order files: a folder stayed', ['order' => $order->id, 'path' => $frameDir]);
            }
        }
        foreach ($files as $file) {
            $size = $disk->size($file);
            if ($dry || $disk->delete($file)) {
                $bytes += $size;
            } else {
                Log::warning('Farm order files: a file stayed', ['order' => $order->id, 'path' => $file]);   // the next run tries again
            }
        }
        if (! $dry) {
            // not an event of the order: the "Čeká na vás" list compares updated_at, and it would bring every old order back
            $order->timestamps = false;
            $order->forceFill($paths + ['files_pruned_at' => now()])->saveQuietly();
            $order->timestamps = true;
        }

        return $bytes;
    }

    private function waitsForRebuild(FarmOrder $order): bool
    {
        return $order->videos()->where('kind', FarmVideo::KIND_SHORT)->whereIn('status', [FarmVideo::STATUS_PENDING, FarmVideo::STATUS_UPLOADED])->exists();
    }

    /** @return array<string, mixed> the path columns that still name something, emptied */
    private function withoutPaths(FarmOrder $order): array
    {
        $out = array_map(fn () => null, array_filter($order->only(['print_stl_path', 'gcode_path', 'gcode_sha256', 'rest_gcode_path'])));
        $plates = (array) $order->part_plates;
        $file = ['stl_path', 'gcode_path', 'sha256'];
        if (array_filter($plates, fn ($p) => is_array($p) && array_intersect_key($p, array_flip($file)))) {
            // the parts themselves stay: the order page lists them and "print again" takes their colours
            $out['part_plates'] = array_map(fn ($p) => is_array($p) ? array_diff_key($p, array_flip($file)) : $p, $plates);
        }

        return $out;
    }
}
