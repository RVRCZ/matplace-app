<?php

namespace App\Jobs;

use App\Domain\YouTube\FarmVideos;
use App\Models\FarmVideo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Sends one print time-lapse to YouTube as a private video (App\Domain\YouTube\FarmVideos::upload). */
class UploadFarmVideo implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;     // FarmVideos re-queues itself on quota errors; anything else waits for the admin

    public function __construct(public readonly int $videoId) {}

    public function handle(FarmVideos $videos): void
    {
        if ($video = FarmVideo::find($this->videoId)) {
            $videos->upload($video);
        }
    }
}
