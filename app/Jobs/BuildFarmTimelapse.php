<?php

namespace App\Jobs;

use App\Domain\YouTube\FarmVideos;
use App\Domain\YouTube\TimelapseCutter;
use App\Domain\YouTube\VideoCut;
use App\Models\FarmOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * The frames the agent sent during the print become the order's videos, each with the order's track under it
 * (App\Domain\YouTube\TimelapseCutter, settings in config youtube.cut):
 *
 *   timelapse.mp4  the customer's, on the order page: logo, 3 2 1, the print, the finished piece, the photo, thank you
 *   short.mp4      the square Short for YouTube and Reels, under 20 s
 *   wide.mp4       the classic 16:9 for YouTube, 1920×1080, under 45 s
 *
 * Frames: one per layer taken with the head parked (TimelapseGcode, printer setting) when the print had them, else
 * one a minute. Needs ffmpeg on the server (`FFMPEG_BIN`); without it the frames stay and nothing else happens.
 */
class BuildFarmTimelapse implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public const KEEP_FRAMES_DAYS = 14;     // frames stay this long so a video can be built again (farm:timelapse, farm:weekly-video)

    /** @param  bool  $rebuild  build again although the order already has its videos (farm:timelapse) */
    public function __construct(public readonly int $orderId, public readonly bool $rebuild = false) {}

    public function handle(): void
    {
        $order = FarmOrder::find($this->orderId);
        if (! $order || ($order->timelapse_path && ! $this->rebuild)) {
            return;
        }
        $cutter = app(TimelapseCutter::class);
        $cuts = $this->cuts($order);
        if (! $cuts || ! $cutter->render($cuts['customer'], 300)) {
            return;   // a print shorter than a few minutes has no story to tell; a failure is in the log
        }
        $path = fn (VideoCut $cut) => $cutter->render($cut, 300) ? $order->dir().'/'.basename($cut->out) : null;
        $order->forceFill([
            'timelapse_path' => $order->dir().'/'.basename($cuts['customer']->out),
            'timelapse_short_path' => $path($cuts['short']),
            'timelapse_wide_path' => $path($cuts['wide']),
        ])->save();
        // the frames stay KEEP_FRAMES_DAYS (matplace:prune) so the videos can be built again after a change
        // the customer agreed to share it: the Short and the 16:9 wait for an admin in /admin/youtube
        app(FarmVideos::class)->queueFor($order);
    }

    /**
     * The three videos as they will be built (frames, filters, lengths, music), without running ffmpeg; null when
     * the print is too short.
     *
     * @return array{customer: VideoCut, short: VideoCut, wide: VideoCut}|null
     */
    public function cuts(FarmOrder $order): ?array
    {
        return app(TimelapseCutter::class)->cuts($order);
    }
}
