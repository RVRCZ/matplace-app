<?php

namespace App\Console\Commands;

use App\Domain\YouTube\FarmVideos;
use App\Domain\YouTube\HowtoCutter;
use App\Domain\YouTube\TimelapseCutter;
use App\Models\FarmVideo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Builds a how-to video from its spec (scripts/video/*.json, App\Domain\YouTube\HowtoCutter): the 16:9 video, the
 * vertical Short, cs and en subtitles and popis.txt (title and description for YouTube) into the spec's out_dir.
 * With --queue both videos wait for the admin in /admin/youtube (kind howto); on the server, where the recording is not,
 * --skip-build queues files built elsewhere and copied into --out.
 */
class FarmHowtoVideo extends Command
{
    protected $signature = 'farm:howto-video {spec : the JSON, e.g. scripts/video/howto.json}
        {--out= : folder for the results (default: out_dir of the spec)}
        {--dry-run : plan only: segments, lengths, what is missing}
        {--skip-build : take the videos already in the out folder}
        {--queue : put both videos into /admin/youtube for approval}';

    protected $description = 'Build a how-to video (16:9 and Short, subtitles, description) from its JSON spec';

    public function handle(HowtoCutter $howto, TimelapseCutter $cutter, FarmVideos $videos): int
    {
        $file = (string) $this->argument('spec');
        $spec = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (! is_array($spec) || empty($spec['name'])) {
            $this->error('Not a spec: '.$file);

            return self::FAILURE;
        }
        $out = rtrim(str_replace('\\', '/', (string) ($this->option('out') ?: $spec['out_dir'] ?? storage_path('app/howto'))), '/');
        if (! is_dir($out)) {
            mkdir($out, 0775, true);
        }
        $paths = ['wide' => $out.'/'.$spec['name'].'.mp4', 'short' => $out.'/'.$spec['name'].'-short.mp4'];
        $captions = [];
        foreach ($paths as $variant => $path) {
            $plan = $howto->plan($spec, $variant, $path);
            $captions[$variant] = $plan['captions'];
            $this->line(sprintf('%s: %.1f s, %d inputs, music %s', $variant, $plan['cut']->seconds, count(array_keys($plan['cut']->inputs, '-i', true)), $plan['cut']->music ? basename($plan['cut']->music) : '-'));
            foreach ($plan['skipped'] as $missing) {
                $this->warn('  missing, left out: '.$missing);
            }
            if ($this->option('dry-run') || $this->option('skip-build')) {
                continue;
            }
            if (! $cutter->render($plan['cut'], 1800)) {
                $this->error($variant.': ffmpeg failed: '.PHP_EOL.implode(PHP_EOL, array_slice(explode("\n", (string) $cutter->lastError()), -15)));

                return self::FAILURE;
            }
            $this->info('  '.$path);
        }
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }
        $music = $howto->music($spec);
        ['title' => $title, 'description' => $description] = $howto->description($spec, $music);
        file_put_contents($out.'/popis.txt', $title."\n\n".$description."\n");
        foreach (['cs', 'en'] as $lang) {
            file_put_contents($out.'/titulky.'.$lang.'.srt', $howto->srt($captions['wide'], $lang));
            file_put_contents($out.'/titulky-short.'.$lang.'.srt', $howto->srt($captions['short'], $lang));
        }
        $this->info('  '.$out.'/popis.txt, titulky.{cs,en}.srt');
        if (! $this->option('queue')) {
            return self::SUCCESS;
        }
        $disk = Storage::disk(config('farm.disk'));
        foreach ($paths as $variant => $path) {
            if (! is_file($path)) {
                $this->error('No '.$path.' to queue.');

                return self::FAILURE;
            }
            $stored = 'howto/'.basename($path);
            $disk->put($stored, fopen($path, 'rb'));
            $video = $videos->queueHowto((string) $spec['name'], $variant, $stored, $title, $description, $music ? basename($music) : null, $howto->target($spec));
            $this->info(sprintf('  %s → /admin/youtube (%s)', $variant, $video->youtube_id ? 'already on YouTube: replace it there' : FarmVideo::STATUS_PENDING));
        }

        return self::SUCCESS;
    }
}
