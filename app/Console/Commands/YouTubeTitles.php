<?php

namespace App\Console\Commands;

use App\Domain\YouTube\ChannelCurator;
use App\Domain\YouTube\YouTubeError;
use Illuminate\Console\Command;

/**
 * The table of the channel's videos for Roman to approve (docs/AL.md §13.5): today's title, the views, the title and
 * description the templates propose, the playlist, whether it is a 16:9 twin.
 *
 *   php artisan youtube:titles                                   docs/data/youtube-titles-<year>-<month>.csv, from the database
 *   php artisan youtube:titles --api                             + the channel's uploads the database does not know (reads the API, 2–3 quota units)
 *   php artisan youtube:titles --out=/tmp/titles.csv
 */
class YouTubeTitles extends Command
{
    protected $signature = 'youtube:titles {--out= : where the CSV goes (docs/data/youtube-titles-<year>-<month>.csv)} {--api : add the channel\'s uploads the database does not know (reads YouTube)}';

    protected $description = 'Write the table of the channel\'s videos with the titles they have and the titles they should have';

    public function handle(ChannelCurator $curator): int
    {
        $out = (string) ($this->option('out') ?: base_path('docs/data/youtube-titles-'.now()->format('Y-m').'.csv'));
        try {
            $rows = $curator->rows((bool) $this->option('api'));
        } catch (YouTubeError $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $curator->writeCsv($out, $rows);
        $this->table(['id', 'druh', 'zhlédnutí', 'dnešní titulek', 'nový titulek', 'playlist', 'duplikát'], array_map(fn ($r) => [$r['id'], $r['druh'], $r['zhlednuti'], mb_substr($r['dnesni_titulek'], 0, 40), mb_substr($r['novy_titulek'], 0, 40), $r['playlist'], $r['duplikat']], $rows));
        $this->info(count($rows).' videos → '.$out.' (nothing was changed on YouTube)');

        return self::SUCCESS;
    }
}
