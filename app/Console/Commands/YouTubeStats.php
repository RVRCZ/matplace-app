<?php

namespace App\Console\Commands;

use App\Domain\YouTube\FarmVideos;
use App\Domain\YouTube\YouTubeError;
use Illuminate\Console\Command;

/** Views, likes and comments of the print videos from YouTube (daily, and the button in /admin/youtube). */
class YouTubeStats extends Command
{
    protected $signature = 'youtube:stats';

    protected $description = 'Refresh the YouTube numbers of the farm print videos';

    public function handle(FarmVideos $videos): int
    {
        try {
            $this->info('videos updated: '.$videos->refreshStats());
        } catch (YouTubeError $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
