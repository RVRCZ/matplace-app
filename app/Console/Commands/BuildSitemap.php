<?php

namespace App\Console\Commands;

use App\Support\Sitemaps;
use Illuminate\Console\Command;

/**
 * Writes the sitemaps (App\Support\Sitemaps) into storage/app/sitemaps; the site serves them at /sitemap.xml and
 * /sitemap-*.xml. Runs daily from the scheduler; run it by hand after a big import.
 */
class BuildSitemap extends Command
{
    protected $signature = 'matplace:sitemap';

    protected $description = 'Write the sitemaps for search engines';

    public function handle(Sitemaps $sitemaps): int
    {
        $written = $sitemaps->build();
        foreach ($written as $file => $count) {
            $this->line(sprintf('%-36s %6d', $file, $count));
        }
        $this->info(count($written).' files, '.array_sum($written).' addresses, index at '.url('/sitemap.xml'));

        return self::SUCCESS;
    }
}
