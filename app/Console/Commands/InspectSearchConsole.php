<?php

namespace App\Console\Commands;

use App\Domain\Stats\SearchConsoleError;
use App\Domain\Stats\SearchConsoleIndex;
use App\Models\SearchConsoleAccount;
use Illuminate\Console\Command;

/**
 * The state of the index in one command (docs/AP.md §14): asks Google about the important addresses (config/seo.php
 * `inspect`) and about what the sitemap lists, reads the sitemaps the account holds, keeps both
 * (search_console_inspections, search_console_sitemaps) and prints the summary: indexed / discovered / unknown.
 *
 *   php artisan searchconsole:inspect                 the important addresses, then the sitemap's, up to the cap
 *   php artisan searchconsole:inspect --important     only the important ones
 *   php artisan searchconsole:inspect --cap=40
 *   php artisan searchconsole:inspect --dry-run       the addresses it would ask about, nothing is sent
 *   php artisan searchconsole:inspect --summary       only print what the database holds
 *
 * Google allows 2 000 inspections a day for a property: one run asks at most `--cap` addresses (config
 * `seo.inspect.cap`, 150), a day at most `seo.inspect.daily_cap` (1 800, counted from the stored rows), and an
 * address asked today is not asked again. The account's read-only grant is enough.
 */
class InspectSearchConsole extends Command
{
    protected $signature = 'searchconsole:inspect
        {--cap= : how many addresses this run asks about at most (default: config seo.inspect.cap)}
        {--important : only the important addresses of config/seo.php, not the sitemap}
        {--dry-run : list the addresses, ask nothing}
        {--summary : print the stored summary, ask nothing}';

    protected $description = 'Ask Google which of our important pages are in its index and what it made of the sitemaps';

    public function handle(SearchConsoleIndex $index): int
    {
        if ($this->option('summary')) {
            return $this->summary();
        }
        $cap = max(0, min((int) ($this->option('cap') ?? config('seo.inspect.cap', 150)), 2000, $index->leftToday()));
        $queue = $index->queue($cap, (bool) $this->option('important'));
        if ($this->option('dry-run')) {
            foreach ($queue as $item) {
                $this->line(($item['important'] ? '* ' : '  ').$item['url']);
            }
            $this->info(count($queue).' address(es) would be asked (cap '.$cap.', '.$index->leftToday().' left today); * = important.');

            return self::SUCCESS;
        }
        $account = SearchConsoleAccount::current();
        if (! $account || ! $account->ready()) {
            $this->error($account ? 'No property is chosen: pick one in /admin/search-console.' : 'Search Console is not connected: /admin/search-console → Připojit.');

            return self::FAILURE;
        }
        $this->line(sprintf('Property %s: %d address(es) to ask (cap %d, %d left today).', $account->site_url, count($queue), $cap, $index->leftToday()));

        try {
            $maps = $index->pullSitemaps();
            $this->line("Sitemaps listed by the account: {$maps}.");
            $result = $index->inspect($queue, fn (string $url, string $state) => $this->line(sprintf('%-11s %s', $state, $url), null, 'v'));
        } catch (SearchConsoleError $e) {
            $this->error($e->getMessage().($e->needsReconnect() ? ' Connect the account again in /admin/search-console.' : ''));

            return self::FAILURE;
        }
        if ($result['stopped']) {
            $this->warn("Stopped after {$result['asked']} address(es): {$result['stopped']}");
        } else {
            $this->info("Asked about {$result['asked']} address(es).");
        }
        $this->summary();

        return $result['stopped'] && $result['asked'] === 0 ? self::FAILURE : self::SUCCESS;
    }

    private function summary(): int
    {
        $summary = SearchConsoleIndex::summary();
        if (! $summary) {
            $this->line('Nothing was asked yet.');

            return self::SUCCESS;
        }
        foreach (SearchConsoleIndex::lines($summary) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
