<?php

namespace App\Jobs;

use App\Domain\YouTube\FarmVideos;
use App\Domain\YouTube\StudioCutter;
use App\Domain\YouTube\TimelapseCutter;
use App\Models\FarmOrder;
use App\Models\FarmVideo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * The video studio (docs/AL.md §13.10): the Short and the 16:9 of a print built again from its media list and texts.
 * A change waits a few seconds (later()): a run of changes (dragging, ticking, typing) builds once, by the last job.
 * A video already on YouTube is never built again (its media are locked, only its texts change); the old file stays
 * until the new one is finished, so a failed build loses nothing. The page reads state() to show what goes on.
 */
class BuildStudioVideos implements ShouldQueue
{
    use Queueable;

    public const TIMEOUT = 840;   // under the database queue's retry_after (900 s on the server), else a long build is started twice

    public const DELAY = 8;

    public int $timeout = self::TIMEOUT;

    public int $tries = 1;

    public function __construct(public readonly int $orderId, public readonly int $version) {}

    /** Something changed: build in a few seconds, unless another change comes first. */
    public static function later(FarmOrder $order, int $delay = self::DELAY): void
    {
        Cache::add(self::versionKey($order->id), 0, now()->addDay());
        $version = (int) Cache::increment(self::versionKey($order->id));
        self::remember($order->id, 'waiting');
        self::dispatch($order->id, $version)->delay(now()->addSeconds($delay));
    }

    /** @return array{state: string, at: ?string, errors: array<string, string>} waiting, building, done (or none) */
    public static function state(int $orderId): array
    {
        return (array) Cache::get(self::stateKey($orderId), []) + ['state' => 'none', 'at' => null, 'errors' => []];
    }

    public static function versionKey(int $orderId): string
    {
        return 'studio-version:'.$orderId;
    }

    private static function stateKey(int $orderId): string
    {
        return 'studio-state:'.$orderId;
    }

    private static function remember(int $orderId, string $state, array $errors = []): void
    {
        Cache::put(self::stateKey($orderId), ['state' => $state, 'at' => now()->toIso8601String(), 'errors' => $errors], now()->addDay());
    }

    public function handle(StudioCutter $studio, TimelapseCutter $cutter): void
    {
        if ((int) Cache::get(self::versionKey($this->orderId)) !== $this->version) {
            return;   // a newer change follows with its own job
        }
        $order = FarmOrder::find($this->orderId);
        if (! $order) {
            return;
        }
        $lock = Cache::lock('studio-build:'.$this->orderId, self::TIMEOUT);
        if (! $lock->get()) {
            self::dispatch($this->orderId, $this->version)->delay(now()->addSeconds(30));   // one build at a time

            return;
        }
        try {
            self::remember($order->id, 'building');
            app(FarmVideos::class)->followPriceSwitch($order);
            $errors = [];
            foreach ([FarmVideo::KIND_SHORT, FarmVideo::KIND_WIDE] as $kind) {
                $video = $order->videos()->where('kind', $kind)->latest('id')->first();
                if ($video?->youtube_id) {
                    continue;   // on YouTube: the media are locked
                }
                $cut = $studio->plan($order, $kind, $video);
                if (! $cut) {
                    $errors[$kind] = 'Ve videu není nic k ukázání: zapněte aspoň jednu položku.';

                    continue;
                }
                $new = $cut->out.'.new.mp4';
                if (! $cutter->render($cut->to($new), 300) || ! rename($new, $cut->out)) {
                    @unlink($new);
                    $errors[$kind] = 'Video se nepodařilo postavit, zůstává předchozí verze. '.mb_substr((string) $cutter->lastError(), -300);

                    continue;
                }
                $order->forceFill([$kind === FarmVideo::KIND_WIDE ? 'timelapse_wide_path' : 'timelapse_short_path' => $order->dir().'/'.basename($cut->out)])->save();
                $video?->update(['meta' => ['built_at' => now()->toIso8601String(), 'seconds' => round($cut->seconds, 1)] + (array) $video->meta]);
            }
            self::remember($order->id, 'done', $errors);
        } finally {
            $lock->release();
        }
    }
}
