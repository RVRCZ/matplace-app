<?php

namespace App\Console\Commands;

use App\Domain\Farm\PublicStats;
use App\Domain\YouTube\FarmVideos;
use App\Domain\YouTube\SeriesCutter;
use App\Domain\YouTube\TimelapseCutter;
use App\Models\FarmVideo;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * "Jak funguje naše 3D tisková farma" (docs/AL.md §13.7): the month's prints between the cards that tell the steps,
 * the photographed pieces and the farm's numbers, 3 to 5 minutes 16:9 with chapters; waits for the admin in
 * /admin/youtube (never published by itself). The first evening of a month for the month that ended (scheduler).
 *
 *   php artisan farm:monthly-video [--month=2026-10] [--dry-run]
 */
class FarmMonthlyVideo extends Command
{
    protected $signature = 'farm:monthly-video {--month= : YYYY-MM (default: the month that ended last)} {--dry-run : list the prints and the chapters, build nothing}';

    protected $description = 'Build the monthly video "Jak funguje naše 3D tisková farma" for the admin to approve';

    public function handle(SeriesCutter $series, TimelapseCutter $cutter, FarmVideos $videos): int
    {
        $tz = (string) config('youtube.publish_timezone', 'Europe/Prague');
        $given = (string) $this->option('month');
        if ($given !== '' && ! preg_match('/^\d{4}-\d{2}$/', $given)) {
            $this->error('The month looks like 2026-10.');

            return self::FAILURE;
        }
        $month = ($given !== '' ? Carbon::createFromFormat('Y-m-d', $given.'-01', $tz) : Carbon::now($tz)->subMonthNoOverflow())->startOfMonth();
        $found = $series->monthCandidates($month);
        $orders = $series->fitMonth($found);
        $min = (int) config('youtube.cut.monthly.min_prints', 4);
        $this->line($month->format('Y-m').' ('.$series->monthLabel($month).'): '.$found->count().' prints'.($found->isEmpty() ? '' : ': '.$found->pluck('number')->implode(', '))
            .($orders->count() < $found->count() ? '; '.$orders->count().' fit into '.(int) config('youtube.cut.monthly.max_seconds').' s' : ''));
        if ($orders->count() < $min) {
            $this->warn('Fewer than '.$min.' prints with a time-lapse: no monthly video.');

            return self::SUCCESS;
        }
        $numbers = PublicStats::summary();
        $disk = Storage::disk(config('farm.disk'));
        $disk->makeDirectory('series');
        $path = 'series/month-'.$month->format('Y-m').'.mp4';
        $cut = $series->monthly($orders, $month, $numbers, $disk->path($path));
        $track = $cut->music ? basename($cut->music) : null;
        $texts = $series->monthlyTexts($cut, $month, $numbers, $track);
        $this->line(sprintf('%d inputs, %.1f s (%d:%02d), music: %s', count(array_keys($cut->inputs, '-i', true)), $cut->seconds, intdiv((int) $cut->seconds, 60), (int) $cut->seconds % 60, $track ?? '-'));
        foreach ($cut->chapters as [$at, $name]) {
            $this->line(sprintf('  %d:%02d %s', intdiv((int) $at, 60), (int) $at % 60, $name));
        }
        $this->line('title: '.$texts['title']);
        if ($cut->seconds < (float) config('youtube.cut.monthly.min_seconds', 180)) {
            $this->warn(sprintf('Shorter than %d s: a quiet month. Built anyway; the admin decides.', (int) config('youtube.cut.monthly.min_seconds', 180)));
        }
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }
        if (! $cutter->render($cut, 2400)) {
            $this->error('ffmpeg failed (see the log).');

            return self::FAILURE;
        }
        $video = $videos->queueCollection(FarmVideo::KIND_MONTHLY, ['key' => $month->format('Y-m'), 'orders' => $orders->pluck('id')->values()->all()], $path, $texts['title'], $texts['description'], $track);
        $this->info($video->youtube_id ? 'Built, but the month is on YouTube already: replace it in /admin/youtube.' : 'Waiting for approval in /admin/youtube ('.$video->path.').');

        return self::SUCCESS;
    }
}
