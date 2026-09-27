<?php

namespace App\Jobs;

use App\Domain\YouTube\FarmVideos;
use App\Models\FarmOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * The frames the agent sent during the print become a short MP4 the customer can watch and share:
 *
 *   black -> the logo appears and fades away -> 3, 2, 1 -> the print grows -> the finished piece holds still
 *   -> "Thank you for printing with us" and the logo.
 *
 * Frames: one per layer taken with the head parked (TimelapseGcode, printer setting) when the print had them,
 * else one a minute. A second, square video for YouTube Shorts starts and ends on the finished piece.
 *
 * Needs ffmpeg on the server (`FFMPEG_BIN`, default `ffmpeg`); without it the frames stay and nothing else happens.
 */
class BuildFarmTimelapse implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    private const FPS = 24;

    private const FRAME_SECONDS = 0.125;    // time-based frames (one a minute): one minute of printing = 1/8 s

    private const HOLD_SECONDS = 4.0;       // the finished piece, still

    private const SHORT_SECONDS = 28.0;     // the square Short aims at this length, never beyond SHORT_MAX_FRAMES

    private const SHORT_MAX_FRAMES = 1000;

    public function __construct(public readonly int $orderId) {}

    public function handle(): void
    {
        $order = FarmOrder::find($this->orderId);
        $disk = Storage::disk(config('farm.disk'));
        if (! $order || $order->timelapse_path) {
            return;
        }
        [$frames, $frameSeconds] = $this->frames($order);
        if ($frames->count() < 4) {
            return;   // a print shorter than a few minutes has no story to tell
        }
        [$w, $h] = $this->size($disk->path($frames->first()));
        $dir = $disk->path($order->dir());
        $list = $dir.'/frames.txt';
        $thanks = $dir.'/thanks.txt';
        file_put_contents($list, $frames->map(fn ($f) => "file '".$disk->path($f)."'\nduration ".$frameSeconds)->implode("\n")."\nfile '".$disk->path($frames->last())."'\n");
        file_put_contents($thanks, __('farm.timelapse.thanks', [], $order->user?->locale ?? config('app.locale')));
        $out = $dir.'/timelapse.mp4';
        $r = Process::timeout(240)->run([
            $this->ffmpeg(), '-y', '-loglevel', 'error',
            '-f', 'concat', '-safe', '0', '-i', $list,            // 0: the print
            '-loop', '1', '-t', '8', '-i', public_path('img/logo.png'),   // 1: the logo
            '-filter_complex', $this->graph($w, $h, $frames->count() * $frameSeconds, $thanks),
            '-map', '[v]', '-r', (string) self::FPS, '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '26', '-movflags', '+faststart', $out,
        ]);
        @unlink($list);
        @unlink($thanks);
        if (! $r->successful() || ! is_file($out)) {
            Log::warning('Farm time-lapse failed', ['order' => $order->id, 'error' => mb_substr($r->errorOutput(), 0, 500)]);

            return;
        }
        $order->forceFill(['timelapse_path' => $order->dir().'/timelapse.mp4', 'timelapse_short_path' => $this->short($order, $frames)])->save();
        // the frames did their job
        $disk->deleteDirectory($order->dir().'/frames');
        $disk->deleteDirectory($order->dir().'/frames_layer');
        // the customer agreed to share it: up to YouTube as a private video, an admin decides the rest
        app(FarmVideos::class)->queueFor($order);
    }

    /**
     * Pictures taken after every layer with the head parked (TimelapseGcode) when there are enough of them, else the
     * one-a-minute pictures. Layer pictures end with the last timed picture taken after them: the finished piece.
     *
     * @return array{0: Collection<int,string>, 1: float} frames and seconds per frame
     */
    private function frames(FarmOrder $order): array
    {
        $disk = Storage::disk(config('farm.disk'));
        $jpgs = fn (string $dir) => collect($disk->files($order->dir().'/'.$dir))->filter(fn ($f) => str_ends_with($f, '.jpg'))
            ->sortBy(fn ($f) => (float) basename($f, '.jpg'))->values();
        $timed = $jpgs('frames');
        $layer = $jpgs('frames_layer');
        if ($layer->count() < 10) {
            return [$timed, self::FRAME_SECONDS];
        }
        $after = (float) basename((string) $layer->last(), '.jpg');
        $end = $timed->last(fn ($f) => (float) basename($f, '.jpg') > $after);
        $frames = $end ? $layer->push($end) : $layer;

        // 10 to 60 seconds of growing, one layer at least 1/24 s
        return [$frames, max(1 / self::FPS, min(self::FRAME_SECONDS, 30 / $frames->count()))];
    }

    /**
     * The square Short for YouTube (and Reels): the finished piece first, then the layers growing, the finished
     * piece held at the end so the loop closes, a small logo in the corner. No intro: the first second decides.
     */
    private function short(FarmOrder $order, Collection $frames): ?string
    {
        $disk = Storage::disk(config('farm.disk'));
        $step = (int) ceil($frames->count() / self::SHORT_MAX_FRAMES);
        $picked = $frames->filter(fn ($f, $i) => $i % $step === 0 || $i === $frames->count() - 1)->values();
        $seconds = max(1 / self::FPS, min(0.2, self::SHORT_SECONDS / $picked->count()));
        $last = $disk->path((string) $picked->last());
        $lines = ["file '{$last}'\nduration 1.0"];
        foreach ($picked as $f) {
            $lines[] = "file '".$disk->path($f)."'\nduration {$seconds}";
        }
        $lines[] = "file '{$last}'\nduration 2.0";
        $lines[] = "file '{$last}'";
        $list = $disk->path($order->dir().'/short.txt');
        file_put_contents($list, implode("\n", $lines)."\n");

        [$w, $h] = @getimagesize($disk->path((string) $picked->first())) ?: [1280, 720];
        [$cx, $cy, $side] = $this->crop($order, (int) $w, (int) $h);
        $size = min(1080, $side - $side % 2);
        $out = $disk->path($order->dir().'/short.mp4');
        $r = Process::timeout(240)->run([
            $this->ffmpeg(), '-y', '-loglevel', 'error',
            '-f', 'concat', '-safe', '0', '-i', $list,
            '-i', public_path('img/logo.png'),
            '-filter_complex', sprintf(
                '[0:v]crop=%d:%d:%d:%d,scale=%d:%d,fps=%d,format=yuv420p[print];[1:v]scale=%d:-2[logo];[print][logo]overlay=W-w-%d:H-h-%d,format=yuv420p[v]',
                $side, $side, $cx, $cy, $size, $size, self::FPS, (int) round($size * 0.22), (int) round($size * 0.03), (int) round($size * 0.03)
            ),
            '-map', '[v]', '-r', (string) self::FPS, '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-movflags', '+faststart', $out,
        ]);
        @unlink($list);
        if (! $r->successful() || ! is_file($out)) {
            Log::warning('Farm Short failed', ['order' => $order->id, 'error' => mb_substr($r->errorOutput(), 0, 500)]);

            return null;
        }

        return $order->dir().'/short.mp4';
    }

    /** The square out of the camera picture: the printer's setting (admin), else the centre. Always inside the picture. */
    private function crop(FarmOrder $order, int $w, int $h): array
    {
        $t = $order->printer?->timelapseSettings() ?? [];
        $side = min($w, $h, (int) ($t['crop_size'] ?? 0) ?: min($w, $h));
        $x = $t['crop_x'] ?? intdiv($w - $side, 2);
        $y = $t['crop_y'] ?? intdiv($h - $side, 2);

        return [max(0, min($w - $side, (int) $x)), max(0, min($h - $side, (int) $y)), $side - $side % 2];
    }

    private function ffmpeg(): string
    {
        return (string) config('farm.ffmpeg', 'ffmpeg');
    }

    /** ffmpeg filter graph: intro, the print with the finished piece held, outro; all at the frame size, 24 fps. */
    private function graph(int $w, int $h, float $printSeconds, string $thanksFile): string
    {
        $fps = self::FPS;
        $font = $this->font();
        $logoW = (int) round($w * 0.5);
        $intro = 7.0;                                    // logo 0-3.5 s, countdown 3.5-6.5 s, a breath of black
        $print = $printSeconds + self::HOLD_SECONDS;
        $outro = 5.0;
        $count = collect([[3, 3.5], [2, 4.5], [1, 5.5]])->map(fn ($c) => sprintf(
            "drawtext=fontfile='%s':text='%d':fontsize=%d:fontcolor=white:x=(w-text_w)/2:y=(h-text_h)/2:enable='between(t,%s,%s)'",
            $font, $c[0], (int) round($h * 0.4), $c[1], $c[1] + 1
        ))->implode(',');

        return implode(';', [
            // the logo, once for the intro and once for the outro
            "[1:v]scale={$logoW}:-2,format=rgba,split[logo_a][logo_b]",
            // intro: black, the logo fades in and out, then 3 2 1
            '[logo_a]fade=in:st=0.3:d=1:alpha=1,fade=out:st=2.5:d=0.8:alpha=1[logo_in]',
            "color=c=black:s={$w}x{$h}:r={$fps}:d={$intro}[bg_in]",
            "[bg_in][logo_in]overlay=(W-w)/2:(H-h)/2:shortest=1,{$count},format=yuv420p[intro]",
            // the print at frame size, the last frame held, fade to black at the end
            sprintf('[0:v]scale=%d:%d,fps=%d,tpad=stop_mode=clone:stop_duration=%s,fade=out:st=%s:d=0.8,format=yuv420p[print]', $w, $h, $fps, self::HOLD_SECONDS, $print - 0.8),
            // outro: thank you above the logo, fading in
            "color=c=black:s={$w}x{$h}:r={$fps}:d={$outro}[bg_out]",
            '[logo_b]scale='.(int) round($w * 0.4).':-2[logo_small]',
            sprintf("[bg_out]drawtext=fontfile='%s':textfile='%s':fontsize=%d:fontcolor=white:x=(w-text_w)/2:y=h*0.28[thanks]", $font, $this->path($thanksFile), (int) round($h * 0.07)),
            '[thanks][logo_small]overlay=(W-w)/2:H*0.42:shortest=1,fade=in:st=0:d=0.8,format=yuv420p[outro]',
            '[intro][print][outro]concat=n=3:v=1:a=0[v]',
        ]);
    }

    /** The bold DejaVu that ships with dompdf: the same file on every install, covers Czech and Spanish. */
    private function font(): string
    {
        return $this->path((string) config('farm.font', base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf')));
    }

    /** A file name inside the filter graph: forward slashes, the drive colon escaped. */
    private function path(string $file): string
    {
        return str_replace(['\\', ':'], ['/', '\\:'], $file);
    }

    /** Even dimensions (libx264 wants them), capped at 1280 px wide. */
    private function size(string $frame): array
    {
        [$w, $h] = @getimagesize($frame) ?: [1280, 960];
        if ($w > 1280) {
            $h = (int) round($h * 1280 / $w);
            $w = 1280;
        }

        return [$w - $w % 2, $h - $h % 2];
    }
}
