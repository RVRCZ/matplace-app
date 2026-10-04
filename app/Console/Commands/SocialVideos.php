<?php

namespace App\Console\Commands;

use App\Domain\Social\VideoSharer;
use Illuminate\Console\Command;

/** Print videos that are public on YouTube go to the Facebook page and Instagram (every few minutes, App\Domain\Social\VideoSharer). */
class SocialVideos extends Command
{
    protected $signature = 'social:videos';

    protected $description = 'Post the approved print videos to the Facebook page and Instagram once they are public';

    public function handle(VideoSharer $sharer): int
    {
        $this->info('posted: '.$sharer->due());

        return self::SUCCESS;
    }
}
