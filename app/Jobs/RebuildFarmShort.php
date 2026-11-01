<?php

namespace App\Jobs;

use App\Domain\YouTube\TimelapseCutter;
use App\Models\FarmVideo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * "Postavit znovu" in /admin/youtube (docs/AL.md §13.8): a waiting Short from its frames with today's settings. Only the
 * Short, the customer's video stays as it is; the old file stays until the new one is finished, so a failed build or
 * an approval in the meantime loses nothing.
 */
class RebuildFarmShort implements ShouldQueue
{
    use Queueable;

    public const TIMEOUT = 900;

    public int $timeout = self::TIMEOUT;

    public int $tries = 1;

    public function __construct(public readonly int $videoId) {}

    public static function lockKey(int $videoId): string
    {
        return 'farm-video-rebuild:'.$videoId;
    }

    public function handle(TimelapseCutter $cutter): void
    {
        try {
            $video = FarmVideo::find($this->videoId);
            $order = $video?->order;
            if (! $order) {
                return;
            }
            if ($cutter->frames($order)->count() < 4) {
                $video->update(['error' => 'Postavit znovu: snímků tisku je málo, video zůstalo, jak bylo.']);

                return;
            }
            $cut = $cutter->short($order);
            $new = $cut->out.'.new.mp4';
            if (! $cutter->render($cut->to($new), 300) || ! rename($new, $cut->out)) {
                @unlink($new);
                $video->update(['error' => 'Postavit znovu se nepovedlo, video zůstalo, jak bylo (podrobnosti v logu jako „Farm video failed“).']);

                return;
            }
            $order->forceFill(['timelapse_short_path' => $order->dir().'/'.basename($cut->out)])->save();
            $video->update(['error' => null]);
        } finally {
            Cache::forget(self::lockKey($this->videoId));
        }
    }
}
