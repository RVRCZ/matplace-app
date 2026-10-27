<?php

namespace App\Console\Commands;

use App\Domain\YouTube\ChannelCurator;
use App\Domain\YouTube\YouTubeError;
use Illuminate\Console\Command;

/**
 * The channel's playlists after the tools of the site (config youtube.playlists, docs/AL.md §13.5): which exist, which
 * public videos of ours belong in each. A dry run by default; --apply makes the missing ones and puts the videos in.
 *
 *   php artisan youtube:playlists            the plan from the database and the ids we kept (no API call)
 *   php artisan youtube:playlists --apply    playlists.insert + playlistItems.insert (50 quota units each)
 */
class YouTubePlaylists extends Command
{
    protected $signature = 'youtube:playlists {--apply : make the playlists and put the videos in}';

    protected $description = 'Plan or build the channel\'s playlists by the tool of every print (dry run unless --apply)';

    public function handle(ChannelCurator $curator): int
    {
        try {
            if (! $this->option('apply')) {
                foreach ($curator->playlistPlan() as $name => $p) {
                    $this->line('<info>'.$name.'</info> '.($p['id'] ? "({$p['id']})" : '(to be made)').': '.$p['has'].' in it, '.count($p['add']).' to add'.($p['add'] ? ': '.implode(', ', $p['add']) : ''));
                }
                $this->info('Dry run: nothing changed on YouTube. Add --apply once Roman has approved the playlists.');

                return self::SUCCESS;
            }
            $failed = 0;
            foreach ($curator->syncPlaylists() as $name => $r) {
                $failed += count($r['failed']);
                $this->line('<info>'.$name.'</info> ('.$r['id'].')'.($r['created'] ? ' made' : '').': added '.$r['added'].($r['failed'] ? ', failed '.implode('; ', $r['failed']) : ''));
            }

            return $failed ? self::FAILURE : self::SUCCESS;
        } catch (YouTubeError $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
