<?php

namespace App\Console\Commands;

use App\Domain\Stats\SearchConsoleError;
use App\Domain\Stats\SearchConsolePull;
use App\Models\SearchConsoleAccount;
use App\Models\SearchConsoleRow;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Search Console into the database (docs/AN.md §14): the last --days days (three by default, Search Console finishes a
 * day two to three days later), or everything since --since. The first run of a freshly connected account, without
 * --since, goes sixteen months back: as far as the API keeps. Scheduled daily in routes/console.php; idempotent.
 *
 *   php artisan searchconsole:pull
 *   php artisan searchconsole:pull --since=2025-07-01
 */
class PullSearchConsole extends Command
{
    protected $signature = 'searchconsole:pull
        {--days=3 : how many days back from today}
        {--since= : from this day (YYYY-MM-DD) to today instead; the API keeps about 16 months}';

    protected $description = 'Pull the days of Search Console (pages, queries, countries) into search_console_rows';

    public function handle(SearchConsolePull $pull): int
    {
        $account = SearchConsoleAccount::current();
        if (! $account || ! $account->ready()) {
            $this->error($account ? 'No property is chosen: pick one in /admin/search-console.' : 'Search Console is not connected: /admin/search-console → Připojit.');

            return self::FAILURE;
        }
        $months = max(1, (int) config('seo.search_console.first_pull_months', 16));
        $earliest = now()->startOfDay()->subMonths($months);
        if ($this->option('since')) {
            $from = Carbon::parse((string) $this->option('since'))->startOfDay();
            if ($from->lt($earliest)) {
                $this->warn("Search Console keeps about {$months} months: starting at {$earliest->toDateString()} instead of {$from->toDateString()}.");
                $from = $earliest;
            }
        } elseif ($account->last_pulled_at === null) {
            $from = $earliest;
            $this->line("First run of this account: the whole {$months} months the API keeps, from {$from->toDateString()}.");
        } else {
            $from = now()->startOfDay()->subDays(max(1, (int) $this->option('days')));
        }
        $to = now()->startOfDay();
        $this->line(sprintf('Property %s, %s to %s (%d days), %s rows before.', $account->site_url, $from->toDateString(), $to->toDateString(), $from->diffInDays($to) + 1, number_format(SearchConsoleRow::count())));

        try {
            $result = $pull->pull($from, $to, fn (string $date, int $rows) => $this->line("{$date} · {$rows} rows"));
        } catch (SearchConsoleError $e) {
            $this->error($e->getMessage().($e->needsReconnect() ? ' Connect the account again in /admin/search-console.' : ''));

            return self::FAILURE;
        }
        $this->info(sprintf('Done: %d days, %d rows written, %s rows in all.', $result['days'], $result['rows'], number_format(SearchConsoleRow::count())));

        return self::SUCCESS;
    }
}
