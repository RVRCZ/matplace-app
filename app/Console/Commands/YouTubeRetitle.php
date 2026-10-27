<?php

namespace App\Console\Commands;

use App\Domain\YouTube\ChannelCurator;
use Illuminate\Console\Command;

/**
 * Renames the channel's videos after the approved table (docs/AL.md §13.5). A dry run by default: it only says what
 * would change. Nothing touches YouTube without --apply, and the 16:9 twins are hidden only with --unlist as well.
 *
 *   php artisan youtube:retitle docs/data/youtube-titles-2026-10.csv
 *   php artisan youtube:retitle docs/data/youtube-titles-2026-10.csv --apply [--unlist]
 */
class YouTubeRetitle extends Command
{
    protected $signature = 'youtube:retitle {csv : the approved table (youtube:titles)} {--apply : really change the videos on YouTube} {--unlist : with --apply, hide the rows whose navrh says unlisted}';

    protected $description = 'Give the channel\'s videos the approved titles and descriptions (dry run unless --apply)';

    public function handle(ChannelCurator $curator): int
    {
        $csv = (string) $this->argument('csv');
        if (! is_file($csv)) {
            $this->error("No file {$csv}.");

            return self::FAILURE;
        }
        $rows = $curator->readCsv($csv);
        $plan = $curator->plan($rows);
        foreach ($plan['retitle'] as $c) {
            $this->line("<comment>{$c['id']}</comment>  {$c['from']}\n    → <info>{$c['to']}</info>".($c['description'] ? '  (+ popis)' : ''));
        }
        foreach ($plan['unlist'] as $id) {
            $this->line("<comment>{$id}</comment>  → unlisted");
        }
        $this->line(count($plan['retitle']).' to rename, '.count($plan['unlist']).' to unlist, of '.count($rows).' rows');
        if (! $this->option('apply')) {
            $this->info('Dry run: nothing changed on YouTube. Add --apply (and --unlist) once Roman has approved the table.');

            return self::SUCCESS;
        }
        $done = $curator->apply($rows, (bool) $this->option('unlist'));
        $this->info("renamed {$done['retitled']}, unlisted {$done['unlisted']}, failed ".count($done['failed']).', missing on YouTube '.count($done['missing']));
        foreach ($done['failed'] as $f) {
            $this->error($f);
        }
        foreach ($done['missing'] as $id) {
            $this->warn($id.': YouTube no longer knows this video (deleted in Studio?) – marked missing, left out of the table from now on');
        }

        return $done['failed'] ? self::FAILURE : self::SUCCESS;
    }
}
