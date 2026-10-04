<?php

namespace App\Console\Commands;

use App\Domain\Farm\FarmSettings;
use App\Domain\Stats\WeeklyReport;
use App\Mail\StatsReport as StatsReportMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * The week in numbers by e-mail: people, where they came from, how far they got, what was published and what it
 * brought — each next to the week before. Monday 7:00 to the farm's admin address (FARM_ADMIN_EMAIL).
 *
 *   php artisan matplace:stats-report [--to=somebody@example.com] [--days=7] [--print]
 */
class StatsReport extends Command
{
    protected $signature = 'matplace:stats-report {--to= : send here instead of the admin address} {--days=7} {--print : show the report, send nothing}';

    protected $description = 'Send the weekly statistics to the owner';

    public function handle(WeeklyReport $weekly, FarmSettings $settings): int
    {
        $report = $weekly->build(max(1, (int) $this->option('days')));
        if ($this->option('print')) {
            $this->line($report['subject']);
            $this->line($report['intro']);
            foreach ($report['sections'] as $section) {
                $this->newLine();
                $this->info($section['title']);
                foreach ($section['lines'] as $line) {
                    $this->line(' - '.$line);
                }
            }

            return self::SUCCESS;
        }
        $to = (string) ($this->option('to') ?: $settings->get('admin_email'));
        if ($to === '') {
            $this->warn('No address to send to (farm setting admin_email).');

            return self::SUCCESS;
        }
        Mail::to($to)->send(new StatsReportMail($report, route('admin.stats.funnel', ['days' => 7])));
        $this->info('Sent to '.$to.'.');

        return self::SUCCESS;
    }
}
