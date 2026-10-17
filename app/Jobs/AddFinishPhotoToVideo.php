<?php

namespace App\Jobs;

use App\Domain\YouTube\FarmVideos;
use App\Domain\YouTube\YouTubeError;
use App\Models\FarmOrder;
use App\Models\FarmVideo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * A photo of the cleaned piece arrived from the photo box (or went away): the videos are built again so they end with
 * it. A video waiting for the admin simply goes up as the new file; a private copy already on YouTube is replaced
 * (and waits for the admin again). A published one stays (it has views); the admin decides.
 */
class AddFinishPhotoToVideo implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public readonly int $orderId) {}

    public function handle(FarmVideos $videos): void
    {
        $order = FarmOrder::find($this->orderId);
        if (! $order) {
            return;
        }
        (new BuildFarmTimelapse($order->id, rebuild: true))->handle();
        // the Short and the 16:9 alike
        foreach ($order->videos()->whereIn('status', [FarmVideo::STATUS_UPLOADED, FarmVideo::STATUS_SCHEDULED])->get() as $video) {
            try {
                $videos->replace($video);
            } catch (YouTubeError $e) {
                Log::warning('Replacing the video with the finish photo failed', ['order' => $order->id, 'kind' => $video->kind, 'error' => $e->getMessage()]);
            }
        }
    }
}
