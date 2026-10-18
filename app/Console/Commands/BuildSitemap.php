<?php

namespace App\Console\Commands;

use App\Models\CatalogModel;
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
        // the inspiration catalogue: only pages with something of their own are listed, the rest answer "noindex, follow"
        $c = $sitemaps->catalogCounts();
        $this->line(sprintf('Inspiration catalogue: %d indexable of %d shown pages, %d noindex (not listed); the index needs: %s.',
            $c['indexable'], $c['shown'], $c['shown'] - $c['indexable'], implode(', ', CatalogModel::indexNeeds()) ?: 'nothing'));

        return self::SUCCESS;
    }
}
