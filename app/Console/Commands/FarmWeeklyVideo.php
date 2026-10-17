<?php

namespace App\Console\Commands;

use App\Domain\YouTube\FarmVideos;
use App\Domain\YouTube\TimelapseCutter;
use App\Models\FarmOrder;
use App\Models\FarmVideo;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * "Tento týden na farmě": the prints of one week in one 16:9 video with chapters, waiting for the admin in
 * /admin/youtube like any other (never published by itself). Every Sunday evening for the week that ends (scheduler).
 * In it: prints finished that week whose customer agreed, not made from photos (FarmVideos::PRIVATE_KINDS), not
 * rejected for the channel, with their frames still on the disk (BuildFarmTimelapse::KEEP_FRAMES_DAYS).
 */
class FarmWeeklyVideo extends Command
{
    protected $signature = 'farm:weekly-video {--week= : ISO week, e.g. 2026-W42 (default: the week that ends today or ended last)} {--dry-run : list the prints, build nothing}';

    protected $description = 'Build the weekly cut "Tento týden na farmě" for the admin to approve';

    public function handle(TimelapseCutter $cutter, FarmVideos $videos): int
    {
        $tz = (string) config('youtube.publish_timezone', 'Europe/Prague');
        $week = (string) ($this->option('week') ?: now($tz)->subDays(now($tz)->isSunday() ? 0 : 7)->format('o-\WW'));
        if (! preg_match('/^(\d{4})-W(\d{2})$/', $week, $m)) {
            $this->error('The week looks like 2026-W42.');

            return self::FAILURE;
        }
        $monday = Carbon::now($tz)->setISODate((int) $m[1], (int) $m[2])->startOfDay();
        $orders = $this->prints($monday, $videos)->filter(fn (FarmOrder $o) => $cutter->frames($o)->count() >= 4)->values();
        $min = (int) config('youtube.cut.weekly.min_prints', 3);
        $this->line($week.' ('.$cutter->weekLabel($monday).'): '.$orders->count().' prints'.($orders->isEmpty() ? '' : ': '.$orders->pluck('number')->implode(', ')));
        if ($orders->count() < $min) {
            Log::info('Weekly farm video: not enough prints', ['week' => $week, 'prints' => $orders->count(), 'needed' => $min]);
            $this->warn('Fewer than '.$min.' prints with frames: no weekly video.');

            return self::SUCCESS;
        }
        $disk = Storage::disk(config('farm.disk'));
        $disk->makeDirectory('weekly');
        $cut = $cutter->weekly($orders, $monday, $disk->path('weekly/'.$week.'.mp4'));
        $this->line(sprintf('%d prints in it, %.1f s, music: %s', count($cut->chapters), $cut->seconds, $cut->music ? basename($cut->music) : '-'));
        foreach ($cut->chapters as [$at, $name]) {
            $this->line(sprintf('  %d:%02d %s', intdiv((int) $at, 60), (int) $at % 60, $name));
        }
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }
        if (! $cutter->render($cut, 1800)) {
            $this->error('ffmpeg failed (see the log).');

            return self::FAILURE;
        }
        $locale = (string) config('youtube.language');
        $dates = $cutter->weekLabel($monday);
        $track = $cut->music ? basename($cut->music) : null;
        $ids = array_values(array_filter(array_column($cut->chapters, 3)));
        $video = $videos->queueWeekly($week, 'weekly/'.$week.'.mp4', $ids, mb_substr(__('youtube.weekly.title', ['dates' => $dates], $locale), 0, 100),
            $videos->weeklyDescription($dates, $cut->chapters, $track), $track);
        $this->info($video->youtube_id ? 'Built, but the week is on YouTube already: replace it in /admin/youtube.' : 'Waiting for approval in /admin/youtube.');

        return self::SUCCESS;
    }

    /** @return Collection<int, FarmOrder> the week's prints that may be in the video, oldest first */
    private function prints(Carbon $monday, FarmVideos $videos): Collection
    {
        return FarmOrder::with('modelFile')->whereIn('kind', [FarmOrder::KIND_PRINT, FarmOrder::KIND_SHOWCASE])
            ->where('video_consent', true)->whereNotNull('timelapse_path')
            ->whereBetween('finished_at', [$monday->copy()->utc(), $monday->copy()->addWeek()->utc()])
            ->whereDoesntHave('videos', fn ($q) => $q->where('status', FarmVideo::STATUS_REJECTED))
            ->orderBy('finished_at')->get()
            ->reject(fn (FarmOrder $o) => in_array($o->modelFile?->kind(), FarmVideos::PRIVATE_KINDS, true) || in_array($o->modelFile?->origin, FarmVideos::PRIVATE_KINDS, true))
            ->values();
    }
}
