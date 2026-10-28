<?php

namespace App\Console\Commands;

use App\Domain\YouTube\FarmVideos;
use App\Domain\YouTube\SeriesCutter;
use App\Domain\YouTube\TimelapseCutter;
use App\Models\FarmVideo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * The Short "Kolik stojí 3D tisk?" (docs/AL.md §13.7): n prints with a composed photo, drawn at random from different
 * tools, a second of each growing and the photographed piece with its price; waits for the admin in /admin/youtube
 * like every other video (Roman publishes). Nothing is called on YouTube here.
 *
 *   php artisan youtube:price-short --n=5 [--dry-run] [--seed=7]
 */
class YouTubePriceShort extends Command
{
    protected $signature = 'youtube:price-short {--n=5 : how many pieces} {--dry-run : pick and plan, build nothing} {--seed= : the random draw made repeatable}
        {--check-inputs : list every input file with its length and the window taken of it (with --dry-run: build nothing)}
        {--keep : leave the lists, texts and graph next to the video, to run ffmpeg by hand}';

    protected $description = 'Build the Short "Kolik stojí 3D tisk?" from photographed prints with their prices, for the admin to approve';

    public function handle(SeriesCutter $series, TimelapseCutter $cutter, FarmVideos $videos): int
    {
        $n = max(2, min(8, (int) $this->option('n')));
        $byTool = $series->priceCandidates();
        $orders = $series->pickForPrice($byTool, $n, $this->option('seed') !== null ? (int) $this->option('seed') : null);
        $this->line($byTool->sum(fn ($c) => $c->count()).' prints with a photo and a price, '.$byTool->count().' tools; picked '.$orders->count());
        if ($orders->count() < 2) {
            $this->warn('Fewer than 2 prints with a photo and a price: no Short.');

            return self::SUCCESS;
        }
        $disk = Storage::disk(config('farm.disk'));
        $disk->makeDirectory('series');
        $key = 'price-'.now()->format('Y-m-d').'-'.(FarmVideo::where('kind', FarmVideo::KIND_PRICE)->count() + 1);
        $cut = $series->priceShort($orders, $disk->path('series/'.$key.'.mp4'));
        $texts = $series->priceTexts($cut, $cut->music ? basename($cut->music) : null);
        $this->line(sprintf('%d pieces, %.1f s, music: %s', count($cut->chapters), $cut->seconds, $cut->music ? basename($cut->music) : '-'));
        foreach ($orders as $i => $o) {
            $this->line(sprintf('  %d. %s · %s · %s', $i + 1, $o->number, str_replace("\n", ' ', $cut->chapters[$i][0]), $cut->chapters[$i][1] ?? '-'));
        }
        $this->line('title: '.$texts['title']);
        if ($this->option('check-inputs')) {
            $this->inputs($cutter, $cut);
        }
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }
        if (! $cutter->render($cut, 900, (bool) $this->option('keep'))) {
            $this->error('ffmpeg failed: '.PHP_EOL.implode(PHP_EOL, array_slice(explode("\n", (string) $cutter->lastError()), -15)));
            $this->line('(the same in the log as "Farm video failed"'.($this->option('keep') ? '; the lists, texts and graph stayed next to the video' : '; --keep leaves the lists, texts and graph in place').')');

            return self::FAILURE;
        }
        $video = $videos->queueCollection(FarmVideo::KIND_PRICE, ['key' => $key, 'orders' => $orders->pluck('id')->all()], 'series/'.$key.'.mp4', $texts['title'], $texts['description'], $cut->music ? basename($cut->music) : null);
        $this->info('Waiting for approval in /admin/youtube ('.$video->path.').');

        return self::SUCCESS;
    }

    /** --check-inputs: every file the video is cut from, with its length and the window taken of it. */
    private function inputs(TimelapseCutter $cutter, $cut): void
    {
        $rows = $cutter->checkInputs($cut);
        $this->table(['input', 'there', 'kB', 'length', 'window', 'problem'], array_map(fn ($r) => [
            $r['file'], $r['exists'] ? 'yes' : 'NO', (int) round($r['size'] / 1024), $r['duration'] !== null ? number_format($r['duration'], 1).' s' : '?', $r['window'], $r['problem'] ?? '',
        ], $rows));
        $bad = array_filter($rows, fn ($r) => $r['problem'] !== null);
        $this->line($bad ? '<comment>'.count($bad).' input(s) with a problem</comment>' : '<info>every input is there</info>'.(array_filter($rows, fn ($r) => $r['duration'] === null && ! str_starts_with($r['window'], 'picture') && ! str_starts_with($r['window'], 'track')) ? ' (lengths unknown: no ffmpeg here?)' : ''));
    }
}
