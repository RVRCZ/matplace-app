<?php

namespace App\Console\Commands;

use App\Domain\YouTube\YouTubeClient;
use App\Models\FarmVideo;
use App\Models\YouTubeAccount;
use Illuminate\Console\Command;

class YouTubeAnalytics extends Command
{
    protected $signature = 'youtube:analytics';

    protected $description = 'Store 28-day retention and traffic sources for the channel videos';

    public function handle(YouTubeClient $youtube): int
    {
        if (! YouTubeAccount::current()) {
            $this->warn('No YouTube channel is connected.');

            return self::SUCCESS;
        }
        $videos = FarmVideo::whereNotNull('youtube_id')->whereIn('status', [FarmVideo::STATUS_UPLOADED, FarmVideo::STATUS_SCHEDULED, FarmVideo::STATUS_PUBLISHED])->get();
        $updated = 0;
        foreach ($videos as $video) {
            $video->update(['analytics' => $youtube->analytics((string) $video->youtube_id, $video->kind === FarmVideo::KIND_SHORT)]);
            $updated++;
        }
        $this->info("Analytics updated: {$updated} videos.");

        return self::SUCCESS;
    }
}
