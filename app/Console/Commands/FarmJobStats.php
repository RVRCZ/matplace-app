<?php

namespace App\Console\Commands;

use App\Domain\Farm\AgentService;
use App\Models\FarmPrintJob;
use Illuminate\Console\Command;

/**
 * Finished print jobs without numbers from the printer get them estimated after the fact (AgentService::ensureStats,
 * docs/AL.md §10.12): the duration from the job's own clock, the filament from the order's slice, both marked as
 * estimates; the order's actuals are added up again. Job 154 (the dragon on the Kobra X, 23 Oct 2026) was the first.
 */
class FarmJobStats extends Command
{
    protected $signature = 'farm:job-stats {--job= : one job by id} {--missing : every done or failed job with 0 s or 0 mm}';

    protected $description = 'Estimate the duration and filament of finished print jobs the printer gave no numbers for';

    public function handle(AgentService $agents): int
    {
        $jobs = FarmPrintJob::with('order')
            ->when($this->option('job'), fn ($q) => $q->whereKey((int) $this->option('job')))
            ->when(! $this->option('job'), fn ($q) => $q->whereIn('status', [FarmPrintJob::STATUS_DONE, FarmPrintJob::STATUS_FAILED, FarmPrintJob::STATUS_CANCELLED])
                ->where(fn ($q) => $q->whereNull('print_duration_s')->orWhere('print_duration_s', 0)->orWhereNull('filament_used_mm')->orWhere('filament_used_mm', 0)))
            ->get();
        if (! $this->option('job') && ! $this->option('missing')) {
            $this->error('Say --job=<id> or --missing.');

            return self::FAILURE;
        }
        foreach ($jobs as $job) {
            if (! $job->order) {
                continue;
            }
            $before = [$job->print_duration_s, $job->filament_used_mm];
            $agents->ensureStats($job, $job->order, durationOnly: $job->status !== FarmPrintJob::STATUS_DONE);
            $job->save();
            if ($job->status === FarmPrintJob::STATUS_DONE) {
                $agents->recordOrderActuals($job->order);
            }
            $this->line(sprintf('job %d (%s, order %s): %s s / %s mm → %d s / %s mm%s', $job->id, $job->status, $job->order->number ?? $job->order->id,
                $before[0] ?? '–', $before[1] ?? '–', $job->print_duration_s, $job->filament_used_mm ?? '–', $job->stats_estimated ? ' (estimated)' : ''));
        }
        $this->info($jobs->count().' job(s) looked at.');

        return self::SUCCESS;
    }
}
