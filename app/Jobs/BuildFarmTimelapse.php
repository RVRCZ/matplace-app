<?php

namespace App\Jobs;

use App\Domain\YouTube\FarmVideos;
use App\Domain\YouTube\StudioCutter;
use App\Domain\YouTube\TimelapseCutter;
use App\Domain\YouTube\VideoCut;
use App\Models\FarmOrder;
use App\Models\FarmVideo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * The frames the agent sent during the print become the order's videos, each with the order's track under it
 * (App\Domain\YouTube\TimelapseCutter, settings in config youtube.cut):
 *
 *   timelapse.mp4  the customer's, on the order page: logo, 3 2 1, the print, the finished piece, the photo, thank you
 *   short.mp4      the Short 9:16 for YouTube and Reels, from the order's media list (StudioCutter, the video studio)
 *   wide.mp4       the classic 16:9 for YouTube, 1920×1080, from the same list
 *   growth.mp4     the print growing, kept after the frames go: later builds take it
 *
 * Frames: one per layer taken with the head parked (TimelapseGcode, printer setting) when the print had them, else
 * one a minute. Needs ffmpeg on the server (`FFMPEG_BIN`); without it the frames stay and nothing else happens.
 */
class BuildFarmTimelapse implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public const KEEP_FRAMES_DAYS = 14;     // frames stay this long at most so a video can be built again (farm:timelapse, farm:weekly-video); a closed order's go sooner (OrderFiles)

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
        $order->forceFill(['timelapse_path' => $order->dir().'/'.basename($cuts['customer']->out)])->save();
        // the print growing, kept after the frames go (KEEP_FRAMES_DAYS): the video studio builds from it later
        $studio = app(StudioCutter::class);
        $studio->growthMaster($order);
        // the Short and the 16:9 from the order's media list, as the video studio builds them (the old cut if that fails)
        $order->forceFill([
            'timelapse_short_path' => $path($studio->plan($order, FarmVideo::KIND_SHORT) ?? $cuts['short']) ?? $path($cuts['short']),
            'timelapse_wide_path' => $path($studio->plan($order, FarmVideo::KIND_WIDE) ?? $cuts['wide']) ?? $path($cuts['wide']),
        ])->save();
        // the customer agreed to share it: the Short and the 16:9 wait for an admin in /admin/youtube
        $short = app(FarmVideos::class)->queueFor($order);
        // the Short opens with its title: one settled in the queue (a proposed title) differs from the default it was built with
        if ($short && $short->kind === FarmVideo::KIND_SHORT && $studio->intro($order, FarmVideo::KIND_SHORT, $short) !== $studio->intro($order, FarmVideo::KIND_SHORT, null)) {
            BuildStudioVideos::later($order, 0);
        }
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
